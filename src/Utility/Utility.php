<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Language\LanguageInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ParseMode\ParseModeInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrFieldTypeInterface;
use Solarium\Core\Client\Request;

/**
 * Utility functions specific to solr.
 */
class Utility {

  /**
   * Retrieves Solr-specific data for available data types.
   *
   * Returns the data type information for both the default Search API data
   * types and custom data types defined by hook_search_api_data_type_info().
   * Names for default data types are not included, since they are not relevant
   * to the Solr service class.
   *
   * We're adding some extra Solr field information for the default search api
   * data types (as well as on behalf of a couple contrib field types). The
   * extra information we're adding is documented in
   * search_api_solr_hook_search_api_data_type_info(). You can use the same
   * additional keys in hook_search_api_data_type_info() to support custom
   * dynamic fields in your indexes with Solr.
   *
   * @param string|null $type
   *   (optional) A specific type for which the information should be returned.
   *   Defaults to returning all information.
   *
   * @return array|null
   *   If $type was given, information about that type or NULL if it is unknown.
   *   Otherwise, an array of all types. The format in both cases is the same as
   *   for search_api_get_data_type_info().
   *
   * @see search_api_get_data_type_info()
   * @see search_api_solr_hook_search_api_data_type_info()
   */
  public static function getDataTypeInfo($type = NULL) {
    $types = &drupal_static(__FUNCTION__);

    if (!isset($types)) {
      // Grab the stock search_api data types.
      /** @var \Drupal\search_api\DataType\DataTypePluginManager $data_type_service */
      $data_type_service = \Drupal::service('plugin.manager.search_api.data_type');
      $types = $data_type_service->getDefinitions();

      // Add our extras for the default search api fields.
      $types = NestedArray::mergeDeep($types, array(
        'text' => array(
          'prefix' => 't',
        ),
        'string' => array(
          'prefix' => 's',
        ),
        'integer' => array(
          // Use trie field for better sorting.
          'prefix' => 'it',
        ),
        'decimal' => array(
          // Use trie field for better sorting.
          'prefix' => 'ft',
        ),
        'date' => array(
          'prefix' => 'd',
        ),
        'duration' => array(
          // Use trie field for better sorting.
          'prefix' => 'it',
        ),
        'boolean' => array(
          'prefix' => 'b',
        ),
        'uri' => array(
          'prefix' => 's',
        ),
      ));

      // Extra data type info.
      $extra_types_info = array(
        // Provided by Search API Location module.
        'location' => array(
          'prefix' => 'loc',
        ),
        // @todo Who provides that type?
        'geohash' => array(
          'prefix' => 'geo',
        ),
        // Provided by Search API Location module.
        'rpt' => [
          'prefix' => 'rpt',
        ],
      );

      // For the extra types, only add our extra info if it's already been
      // defined.
      foreach ($extra_types_info as $key => $info) {
        if (array_key_exists($key, $types)) {
          // Merge our extras into the data type info.
          $types[$key] += $info;
        }
      }
    }

    // Return the info.
    if (isset($type)) {
      return $types[$type] ?? NULL;
    }
    return $types;
  }

  /**
   * Returns a unique hash for the current site.
   *
   * This is used to identify Solr documents from different sites within a
   * single Solr server.
   *
   * @return string
   *   A unique site hash, containing only alphanumeric characters.
   */
  public static function getSiteHash() {
    // Copied from apachesolr_site_hash().
    if (!($hash = \Drupal::config('search_api_solr.settings')->get('site_hash'))) {
      global $base_url;
      $hash = substr(base_convert(sha1(uniqid($base_url, TRUE)), 16, 36), 0, 6);
      \Drupal::configFactory()->getEditable('search_api_solr.settings')->set('site_hash', $hash)->save();
    }
    return $hash;
  }

  /**
   * Retrieves a list of all config files of a server's Solr backend.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The Solr server whose files should be retrieved.
   * @param string $dir_name
   *   (optional) The directory that should be searched for files. Defaults to
   *   the root config directory.
   *
   * @return array
   *   An associative array of all config files in the given directory. The keys
   *   are the file names, values are arrays with information about the file.
   *   The files are returned in alphabetical order and breadth-first.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   If a problem occurred while retrieving the files.
   */
  public static function getServerFiles(ServerInterface $server, $dir_name = NULL) {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = $server->getBackend();
    $response = $backend->getSolrConnector()->getFile($dir_name);

    // Search for directories and recursively merge directory files.
    $files_data = json_decode($response->getBody(), TRUE);
    $files_list = $files_data['files'];
    $dir_length = strlen($dir_name) + 1;
    $result = array('' => array());

    foreach ($files_list as $file_name => $file_info) {
      // Annoyingly, Solr 4.7 changed the way the admin/file handler returns
      // the file names when listing directory contents: the returned name is
      // now only the base name, not the complete path from the config root
      // directory. We therefore have to check for this case.
      if ($dir_name && substr($file_name, 0, $dir_length) !== "$dir_name/") {
        $file_name = "$dir_name/" . $file_name;
      }
      if (empty($file_info['directory'])) {
        $result[''][$file_name] = $file_info;
      }
      else {
        $result[$file_name] = static::getServerFiles($server, $file_name);
      }
    }

    ksort($result);
    ksort($result['']);
    return array_reduce($result, 'array_merge', array());
  }

  /**
   * Returns the highlighted keys from a snippet highlighted by Solr.
   *
   * @param string|array $snippets
   *   The snippet(s) to format.
   *
   * @return array
   *   The highlighted keys.
   */
  public static function getHighlightedKeys($snippets) {
    if (is_string($snippets)) {
      $snippets = [$snippets];
    }

    $keys = [[]];

    foreach ($snippets as $snippet) {
      if (preg_match_all('@\[HIGHLIGHT\](.+?)\[/HIGHLIGHT\]@', $snippet, $matches)) {
        $keys[] = $matches[1];
      }
    }

    return array_unique(array_merge(...$keys));
  }

  /**
   * Changes highlighting tags from our custom, HTML-safe ones to HTML.
   *
   * @param string|array $snippet
   *   The snippet(s) to format.
   * @param string|array $prefix
   *   (optional) The opening tag to replace "[HIGHLIGHT]".
   *   Defaults to "<strong>".
   * @param string|array $suffix
   *   (optional) The closing tag to replace "[/HIGHLIGHT]".
   *   Defaults to "</strong>".
   *
   * @return string|array
   *   The snippet(s), properly formatted as HTML.
   */
  public static function formatHighlighting($snippet, $prefix = '<strong>', $suffix = '</strong>') {
    return str_replace(['[HIGHLIGHT]', '[/HIGHLIGHT]'], [$prefix, $suffix], $snippet);
  }

  /**
   * Encodes field names to avoid characters that are not supported by solr.
   *
   * Solr doesn't restrict the characters used to build field names. But using
   * non java identifiers within a field name can cause different kind of
   * trouble when running queries. Java identifiers are only consist of
   * letters, digits, '$' and '_'. See
   * https://issues.apache.org/jira/browse/SOLR-3996 and
   * http://docs.oracle.com/cd/E19798-01/821-1841/bnbuk/index.html
   * For full compatibility the '$' has to be avoided, too. And there're more
   * restrictions regarding the field name itself. See
   * https://cwiki.apache.org/confluence/display/solr/Defining+Fields
   * "Field names should consist of alphanumeric or underscore characters only
   * and not start with a digit ... Names with both leading and trailing
   * underscores (e.g. _version_) are reserved." Field names starting with
   * digits or underscores are already avoided by our schema. The same is true
   * for the names of field types. See
   * https://cwiki.apache.org/confluence/display/solr/Field+Type+Definitions+and+Properties
   * "It is strongly recommended that names consist of alphanumeric or
   * underscore characters only and not start with a digit. This is not
   * currently strictly enforced."
   *
   * This function therefore encodes all forbidden characters in their
   * hexadecimal equivalent encapsulated by a leading sequence of '_X' and a
   * termination character '_'. Example:
   * "tm_entity:node/body" becomes "tm_entity_X3a_node_X2f_body".
   *
   * As a consequence the sequence '_X' itself needs to be encoded if it occurs
   * within a field name. Example: "last_XMas" becomes "last_X5f58_Mas".
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The encoded field name.
   */
  public static function encodeSolrName($field_name) {
    return preg_replace_callback('/([^\da-zA-Z_]|_X)/u',
      function ($matches) {
        return '_X' . bin2hex($matches[1]) . '_';
      },
      $field_name);
  }

  /**
   * Decodes solr field names.
   *
   * This function therefore decodes all forbidden characters from their
   * hexadecimal equivalent encapsulated by a leading sequence of '_X' and a
   * termination character '_'. Example:
   * "tm_entity_X3a_node_X2f_body" becomes "tm_entity:node/body".
   *
   * @see encodeSolrDynamicFieldName() for details.
   *
   * @param string $field_name
   *   Encoded field name.
   *
   * @return string
   *   The decoded field name
   */
  public static function decodeSolrName($field_name) {
    return preg_replace_callback('/_X([\dabcdef]+?)_/',
      function ($matches) {
        return hex2bin($matches[1]);
      },
      $field_name);
  }

  /**
   * Returns the Solr settings for the given index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   *
   * @return array
   *   An associative array of settings.
   */
  public static function getIndexSolrSettings(IndexInterface $index): array {
    return \search_api_solr_merge_default_index_third_party_settings(
      $index->getThirdPartySettings('search_api_solr')
    );
  }

  /**
   * Returns whether the index only contains "solr_*" datasources.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   *
   * @return bool
   *   TRUE if the index only contains "solr_*" datasources, FALSE otherwise.
   */
  public static function hasIndexJustSolrDatasources(IndexInterface $index): bool {
    static $datasources = [];

    if (!isset($datasources[$index->id()])) {
      $datasource_ids = $index->getDatasourceIds();
      $datasource_ids = array_filter($datasource_ids, function ($datasource_id) {
        return strpos($datasource_id, 'solr_') !== 0;
      });
      $datasources[$index->id()] = !$datasource_ids;
    }

    return $datasources[$index->id()];
  }

  /**
   * Returns whether the index only contains "solr_document" datasources.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   *
   * @return bool
   *   TRUE if the index only contains "solr_document" datasources, FALSE
   *   otherwise.
   */
  public static function hasIndexJustSolrDocumentDatasource(IndexInterface $index): bool {
    static $datasources = [];

    if (!isset($datasources[$index->id()])) {
      $datasource_ids = $index->getDatasourceIds();
      $datasources[$index->id()] = ((1 === count($datasource_ids)) && in_array('solr_document', $datasource_ids));
    }

    return $datasources[$index->id()];
  }

  /**
   * Returns the timezone for a query.
   *
   * There's a fallback mechanism to get the time zone:
   * 1. time zone configured for the index
   * 2. the current user's time zone
   * 3. site default time zone
   * 4. storage time zone (UTC)
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Solr index.
   *
   * @return string
   *   The timezone.
   */
  public static function getTimeZone(IndexInterface $index): string {
    $settings = self::getIndexSolrSettings($index);
    $system_date = \Drupal::config('system.date');
    $timezone = '';
    if ($settings['advanced']['timezone']) {
      $timezone = $settings['advanced']['timezone'];
    }
    else {
      if ($system_date->get('timezone.user.configurable')) {
        $timezone = \Drupal::currentUser()->getAccount()->getTimeZone();
      }
    }
    if (!$timezone) {
      $timezone = $system_date->get('timezone.default') ?: date_default_timezone_get();
    }
    return $timezone ?: DateTimeItemInterface::STORAGE_TIMEZONE;
  }

  /**
   * Flattens keys and fields into a single search string.
   *
   * Formatting the keys into a Solr query can be a bit complex. Keep in mind
   * that the default operator is OR. For some combinations we had to take
   * decisions because different interpretations are possible and we have to
   * ensure that stop words in boolean combinations don't lead to zero results.
   * Therefore this function will produce these queries:
   *
   * Careful interpreting this, phrase  and sloppy phrase queries will represent
   * different phrases as A & B. To be very clear, A could equal multiple words.
   *
   * @code
   * #conjunction | #negation | fields | parse mode     | return value
   * ---------------------------------------------------------------------------
   * AND          | FALSE     | []     | terms / phrase | +(+A +B)
   * AND          | TRUE      | []     | terms / phrase | -(+A +B)
   * OR           | FALSE     | []     | terms / phrase | +(A B)
   * OR           | TRUE      | []     | terms / phrase | -(A B)
   * AND          | FALSE     | [x]    | terms / phrase | +(x:(+A +B)^1)
   * AND          | TRUE      | [x]    | terms / phrase | -(x:(+A +B)^1)
   * OR           | FALSE     | [x]    | terms / phrase | +(x:(A B)^1)
   * OR           | TRUE      | [x]    | terms / phrase | -(x:(A B)^1)
   * AND          | FALSE     | [x,y]  | terms          | +((+(x:A^1 y:A^1) +(x:B^1 y:B^1)) x:(+A +B)^1 y:(+A +B)^1)
   * AND          | FALSE     | [x,y]  | phrase         | +(x:(+A +B)^1 y:(+A +B)^1)
   * AND          | TRUE      | [x,y]  | terms          | -((+(x:A^1 y:A^1) +(x:B^1 y:B^1)) x:(+A +B)^1 y:(+A +B)^1)
   * AND          | TRUE      | [x,y]  | phrase         | -(x:(+A +B)^1 y:(+A +B)^1)
   * OR           | FALSE     | [x,y]  | terms          | +(((x:A^1 y:A^1) (x:B^1 y:B^1)) x:(A B)^1 y:(A B)^1)
   * OR           | FALSE     | [x,y]  | phrase         | +(x:(A B)^1 y:(A B)^1)
   * OR           | TRUE      | [x,y]  | terms          | -(((x:A^1 y:A^1) (x:B^1 y:B^1)) x:(A B)^1 y:(A B)^1)
   * OR           | TRUE      | [x,y]  | phrase         | -(x:(A B)^1 y:(A B)^1)
   * AND          | FALSE     | [x,y]  | sloppy_terms   | +(x:(+"A"~10000000 +"B"~10000000)^1 y:(+"A"~10000000 +"B"~10000000)^1)
   * AND          | TRUE      | [x,y]  | sloppy_terms   | -(x:(+"A"~10000000 +"B"~10000000)^1 y:(+"A"~10000000 +"B"~10000000)^1)
   * OR           | FALSE     | [x,y]  | sloppy_terms   | +(x:("A"~10000000 "B"~10000000)^1 y:("A"~10000000 "B"~10000000)^1)
   * OR           | TRUE      | [x,y]  | sloppy_terms   | -(x:("A"~10000000 "B"~10000000)^1 y:("A"~10000000 "B"~10000000)^1)
   * AND          | FALSE     | [x,y]  | sloppy_phrase  | +(x:(+"A"~10000000 +"B"~10000000)^1 y:(+"A"~10000000 +"B"~10000000)^1)
   * AND          | TRUE      | [x,y]  | sloppy_phrase  | -(x:(+"A"~10000000 +"B"~10000000)^1 y:(+"A"~10000000 +"B"~10000000)^1)
   * OR           | FALSE     | [x,y]  | sloppy_phrase  | +(x:("A"~10000000 "B"~10000000)^1 y:("A"~10000000 "B"~10000000)^1)
   * OR           | TRUE      | [x,y]  | sloppy_phrase  | -(x:("A"~10000000 "B"~10000000)^1 y:("A"~10000000 "B"~10000000)^1)
   * AND          | FALSE     | [x,y]  | edismax        | +({!edismax qf=x^1,y^1}+A +B)
   * AND          | TRUE      | [x,y]  | edismax        | -({!edismax qf=x^1,y^1}+A +B)
   * OR           | FALSE     | [x,y]  | edismax        | +({!edismax qf=x^1,y^1}A B)
   * OR           | TRUE      | [x,y]  | edismax        | -({!edismax qf=x^1,y^1}A B)
   * AND / OR     | FALSE     | [x]    | direct         | +(x:(A)^1)
   * AND / OR     | TRUE      | [x]    | direct         | -(x:(A)^1)
   * AND / OR     | FALSE     | [x,y]  | direct         | +(x:(A)^1 y:(A)^1)
   * AND / OR     | TRUE      | [x,y]  | direct         | -(x:(A)^1 y:(A)^1)
   * AND          | FALSE     | []     | keys           | +A +B
   * AND          | TRUE      | []     | keys           | -(+A +B)
   * OR           | FALSE     | []     | keys           | A B
   * OR           | TRUE      | []     | keys           | -(A B)
   * @endcode
   *
   * @param array|string $keys
   *   The keys array to flatten, formatted as specified by
   *   \Drupal\search_api\Query\QueryInterface::getKeys() or a phrase string.
   * @param array $fields
   *   (optional) An array of field names.
   * @param string $parse_mode_id
   *   (optional) The parse mode ID. Defaults to "phrase".
   * @param array $options
   *   (optional) An array of options.
   *
   * @return string
   *   A Solr query string representing the same keys.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public static function flattenKeys($keys, array $fields = [], string $parse_mode_id = 'phrase', array $options = []): string {
    switch ($parse_mode_id) {
      case 'keys':
        if (!empty($fields)) {
          throw new SearchApiSolrException(sprintf('Parse mode %s could not handle fields.', $parse_mode_id));
        }
        break;

      case 'edismax':
      case 'direct':
        if (empty($fields)) {
          throw new SearchApiSolrException(sprintf('Parse mode %s requires fields.', $parse_mode_id));
        }
        break;
    }

    $k = [];
    $pre = '+';
    $neg = '';
    $query_parts = [];
    $sloppiness = '';
    $fuzziness = '';

    if (is_array($keys)) {
      $queryHelper = \Drupal::service('solarium.query_helper');

      if (isset($keys['#conjunction']) && $keys['#conjunction'] === 'OR') {
        $pre = '';
      }

      if (!empty($keys['#negation'])) {
        $neg = '-';
      }

      $escaped = $keys['#escaped'] ?? FALSE;

      foreach ($keys as $key_nr => $key) {
        // We cannot use \Drupal\Core\Render\Element::children() anymore because
        // $keys is not a valid render array.
        if (!$key || strpos($key_nr, '#') === 0) {
          continue;
        }
        if (is_array($key)) {
          if ('edismax' === $parse_mode_id) {
            throw new SearchApiSolrException('Incompatible parse mode.');
          }
          if ($subkeys = self::flattenKeys($key, $fields, $parse_mode_id, $options)) {
            $query_parts[] = $subkeys;
          }
        }
        elseif ($escaped) {
          $k[] = trim($key);
        }
        else {
          $key = trim($key);
          switch ($parse_mode_id) {
            // Using the 'phrase' or 'sloppy_phrase' parse mode, Search API
            // provides one big phrase as keys. Using the 'terms' parse mode,
            // Search API provides chunks of single terms as keys. But these
            // chunks might contain not just real terms but again a phrase if
            // you enter something like this in the search box:
            // term1 "term2 as phrase" term3.
            // This will be converted in this keys array:
            // ['term1', 'term2 as phrase', 'term3'].
            // To have Solr behave like the database backend, these three
            // "terms" should be handled like three phrases.
            case 'terms':
            case 'sloppy_terms':
            case 'phrase':
            case 'sloppy_phrase':
            case 'edismax':
            case 'keys':
              $k[] = $queryHelper->escapePhrase($key);
              break;

            case 'fuzzy_terms':
              if (strpos($key, ' ')) {
                $k[] = $queryHelper->escapePhrase($key);
              }
              else {
                $k[] = $queryHelper->escapeTerm($key);
              }
              break;

            default:
              throw new SearchApiSolrException('Incompatible parse mode.');
          }
        }
      }
    }
    elseif (is_string($keys)) {
      switch ($parse_mode_id) {
        case 'direct':
          $pre = '';
          $k[] = '(' . trim($keys) . ')';
          break;

        default:
          throw new SearchApiSolrException('Incompatible parse mode.');
      }
    }

    if ($k) {
      switch ($parse_mode_id) {
        case 'edismax':
          $query_parts[] = "({!edismax qf='" . implode(' ', $fields) . "'}" . $pre . implode(' ' . $pre, $k) . ')';
          break;

        case 'keys':
          $query_parts[] = $pre . implode(' ' . $pre, $k);
          break;

        case 'sloppy_terms':
        case 'sloppy_phrase':
          if (isset($options['slop'])) {
            $sloppiness = '~' . $options['slop'];
          }
        // No break! Execute 'default', too. 'terms' will be skipped when $k
        // just contains one element.
        case 'fuzzy_terms':
          if (!$sloppiness && isset($options['fuzzy'])) {
            $fuzziness = '~' . $options['fuzzy'];
          }
        // No break! Execute 'default', too. 'terms' will be skipped when $k
        // just contains one element.
        case 'terms':
          if (count($k) > 1 && count($fields) > 0) {
            $key_parts = [];
            foreach ($k as $l) {
              $field_parts = [];
              foreach ($fields as $f) {
                $field = $f;
                $boost = '';
                // Split on operators:
                // - boost (^)
                // - fixed score (^=)
                if ($split = preg_split('/([\^])/', $f, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)) {
                  $field = array_shift($split);
                  $boost = implode('', $split);
                }
                $field_parts[] = $field . ':' . $l . $boost;
              }
              $key_parts[] = $pre . '(' . implode(' ', $field_parts) . ')';
            }
            $query_parts[] = '(' . implode(' ', $key_parts) . ')';
          }
        // No break! Execute 'default', too.
        default:
          foreach ($k as &$term_or_phrase) {
            // Just add sloppiness when if we really have a phrase, indicated
            // by double quotes and terms separated by blanks.
            if ($sloppiness && strpos($term_or_phrase, ' ') && strpos($term_or_phrase, '"') === 0) {
              $term_or_phrase .= $sloppiness;
            }
            // Otherwise just add fuzziness when if we really have a term.
            elseif ($fuzziness && !strpos($term_or_phrase, ' ') && strpos($term_or_phrase, '"') !== 0) {
              $term_or_phrase .= $fuzziness;
            }
            unset($term_or_phrase);
          }

          if (count($fields) > 0) {
            foreach ($fields as $f) {
              $field = $f;
              $boost = '';
              // Split on operators:
              // - boost (^)
              // - fixed score (^=)
              if ($split = preg_split('/([\^])/', $f, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE)) {
                $field = array_shift($split);
                $boost = implode('', $split);
              }
              $query_parts[] = $field . ':(' . $pre . implode(' ' . $pre, $k) . ')' . $boost;
            }
          }
          else {
            $query_parts[] = '(' . $pre . implode(' ' . $pre, $k) . ')';
          }
      }
    }

    if (count($query_parts) === 1) {
      return $neg . reset($query_parts);
    }

    if (count($query_parts) > 1) {
      return $neg . '(' . implode(' ', $query_parts) . ')';
    }

    return '';
  }

}
