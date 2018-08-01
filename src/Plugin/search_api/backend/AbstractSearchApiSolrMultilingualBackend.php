<?php

namespace Drupal\search_api_solr\Plugin\search_api\backend;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api_solr\Solarium\Autocomplete\Query as AutocompleteQuery;
use Drupal\search_api_solr\SolrMultilingualBackendInterface;
use Drupal\search_api_solr\Utility\Utility;
use Solarium\Component\ComponentAwareQueryInterface;
use Solarium\Core\Query\QueryInterface as SolariumQueryInterface;
use Solarium\QueryType\Select\Query\Query;
use Solarium\Component\ResponseParser\FacetSet;

/**
 * The name of the language field might be change in future releases of
 * search_api. @see https://www.drupal.org/node/2641392 for details.
 * Therefor we define a constant here that could be easily changed.
 */
define('SEARCH_API_LANGUAGE_FIELD_NAME', 'search_api_language');

/**
 * A abstract base class for all multilingual Solr Search API backends.
 */
abstract class AbstractSearchApiSolrMultilingualBackend extends SearchApiSolrBackend implements SolrMultilingualBackendInterface {

  /**
   * The unprocessed search keys.
   *
   * @var mixed
   */
  protected $origKeys = FALSE;

  /**
   * Creates and deploys a missing dynamic Solr field if the server supports it.
   *
   * @param string $solr_field_name
   *   The name of the new dynamic Solr field.
   *
   * @param string $solr_field_type_name
   *   The name of the Solr Field Type to be used for the new dynamic Solr
   *   field.
   */
  abstract protected function createSolrDynamicField($solr_field_name, $solr_field_type_name);

  /**
   * Creates and deploys a missing Solr Field Type if the server supports it.
   *
   * @param string $solr_field_type_name
   *   The name of the Solr Field Type.
   */
  abstract protected function createSolrMultilingualFieldType($solr_field_type_name);

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['multilingual'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Multilingual'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    $form['multilingual']['sasm_limit_search_page_to_content_language'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit to current content language.'),
      '#description' => $this->t('Limit all search results for custom queries or search pages not managed by Views to current content language if no language is specified in the query.'),
      '#default_value' => isset($this->configuration['sasm_limit_search_page_to_content_language']) ? $this->configuration['sasm_limit_search_page_to_content_language'] : FALSE,
    ];
    $form['multilingual']['sasm_search_page_include_language_independent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include language independent content in search results.'),
      '#description' => $this->t('This option will include content without a language assigned in the results of custom queries or search pages not managed by Views. For example, if you search for English content, but have an article with languague of "undefined", you will see those results as well. If you disable this option, you will only see content that matches the language.'),
      '#default_value' => isset($this->configuration['sasm_search_page_include_language_independent']) ? $this->configuration['sasm_search_page_include_language_independent'] : FALSE,
    ];
    $form['multilingual']['sasm_language_unspecific_fallback_on_schema_issues'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use language fallbacks.'),
      '#description' => $this->t('This option is suitable for two use-cases. First, if you have languages like "de" and "de-at", both could be handled by a shared configuration for "de". Second, new languages will be handled by language-unspecific fallback configuration until the schema gets updated on your Solr server.'),
      '#default_value' => isset($this->configuration['sasm_language_unspecific_fallback_on_schema_issues']) ? $this->configuration['sasm_language_unspecific_fallback_on_schema_issues'] : TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Since the form is nested into another, we can't simply use #parents for
    // doing this array restructuring magic. (At least not without creating an
    // unnecessary dependency on internal implementation.)
    foreach ($values['multilingual'] as $key => $value) {
      $form_state->setValue($key, $value);
    }

    // Clean-up the form to avoid redundant entries in the stored configuration.
    $form_state->unsetValue('multilingual');

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Adjusts the language filter before converting the query into a Solr query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object.
   */
  protected function alterSearchApiQuery(QueryInterface $query) {
    // Do not modify 'Server index status' queries.
    // @see https://www.drupal.org/node/2668852
    if ($query->hasTag('server_index_status') || $query->hasTag('mlt')) {
      return;
    }

    parent::alterSearchApiQuery($query);

    $languages = $query->getLanguages();

    // If there are no languages set, we need to set them.
    // As an example, a language might be set by a filter in a search view.
    if (empty($languages)) {
      if (!$query->hasTag('views') && $this->configuration['sasm_limit_search_page_to_content_language']) {
        // Limit the language to the current language being used.
        $languages[] = \Drupal::languageManager()
          ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
          ->getId();
      }
      else {
        // If the query is generated by views and/or the query isn't limited by
        // any languages we have to search for all languages using their
        // specific fields.
        $languages = array_keys(\Drupal::languageManager()->getLanguages());
      }
    }

    if ($this->configuration['sasm_search_page_include_language_independent']) {
      $languages[] = LanguageInterface::LANGCODE_NOT_SPECIFIED;
      $languages[] = LanguageInterface::LANGCODE_NOT_APPLICABLE;
    }

    $query->setLanguages($languages);
  }

  /**
   * Modify the query before it is sent to solr.
   *
   * Replaces all language unspecific fulltext query fields by language specific
   * ones.
   *
   * @param \Solarium\Core\Query\QueryInterface $solarium_query
   *   The Solarium select query object.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The \Drupal\search_api\Query\Query object representing the executed
   *   search query.
   */
  protected function preQuery(SolariumQueryInterface $solarium_query, QueryInterface $query) {
    // Do not modify 'Server index status' queries.
    // @see https://www.drupal.org/node/2668852
    if ($query->hasTag('server_index_status')) {
      return;
    }

    parent::preQuery($solarium_query, $query);

    $language_ids = $query->getLanguages();

    if (!empty($language_ids)) {
      $index = $query->getIndex();
      $fulltext_fields = $this->getQueryFulltextFields($query);
      $field_names = $this->getSolrFieldNames($index);

      $language_specific_fields = [];
      foreach ($language_ids as $language_id) {
        foreach ($fulltext_fields as $fulltext_field) {
          $field_name = $field_names[$fulltext_field];
          $language_specific_fields[$language_id][$field_name] = Utility::encodeSolrName(Utility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($field_name, $language_id));
        }
      }

      $components = $solarium_query->getComponents();
      if (isset($components[ComponentAwareQueryInterface::COMPONENT_HIGHLIGHTING])) {
        $hl = $solarium_query->getHighlighting();
        $highlighted_fields = $hl->getFields();

        foreach ($field_names as $field_name) {
          if (isset($highlighted_fields[$field_name])) {
            $exchanged = FALSE;
            foreach ($language_ids as $language_id) {
              if (isset($language_specific_fields[$language_id][$field_name])) {
                $language_specific_field = $language_specific_fields[$language_id][$field_name];
                // Copy the already set highlighting options over to the language
                // specific fields. getField() creates a new one first.
                $highlighted_field = $hl->getField($language_specific_field);
                $highlighted_field->setOptions($highlighted_fields[$field_name]->getOptions());
                $highlighted_field->setName($language_specific_field);
                $exchanged = TRUE;
              }
            }
            if ($exchanged) {
              $hl->removeField($field_name);
            }
          }
        }
      }

      if (empty($this->configuration['retrieve_data'])) {
        // We need the language to be part of the result to modify the result
        // accordingly in extractResults().
        $solarium_query->addField($field_names[SEARCH_API_LANGUAGE_FIELD_NAME]);
      }

      if ($query->hasTag('mlt')) {
        $mlt_fields = [];
        foreach ($language_ids as $language_id) {
          /** @var \Solarium\QueryType\MoreLikeThis\Query $solarium_query */
          foreach ($solarium_query->getMltFields() as $mlt_field) {
            if (isset($language_specific_fields[$language_id][$mlt_field])) {
              $mlt_fields[] = $language_specific_fields[$language_id][$mlt_field];
            }
            else {
              // Make sure untranslated fields are still kept.
              $mlt_fields[$mlt_field] = $mlt_field;
            }
          }
        }
        $solarium_query->setMltFields($mlt_fields);
      }
      elseif ($keys = $query->getKeys()) {
        /** @var \Solarium\QueryType\Select\Query\Query $solarium_query */
        $edismax = $solarium_query->getEDisMax();
        if ($solr_fields = $edismax->getQueryFields()) {
          $new_keys = [];

          foreach ($language_ids as $language_id) {
            $new_solr_fields = $solr_fields;
            foreach ($fulltext_fields as $fulltext_field) {
              $field_name = $field_names[$fulltext_field];
              $new_solr_fields = str_replace($field_name, $language_specific_fields[$language_id][$field_name], $new_solr_fields);
            }
            if ($new_solr_fields == $solr_fields) {
              // If there's no change for the first language, there won't be
              // any change for the other languages, too.
              return;
            }
            $new_solr_fields = explode(' ', $new_solr_fields);
            foreach ($new_solr_fields as $solrField) {
              $flat_keys = $this->flattenKeys($keys, [$solrField], $query->getParseMode()->getPluginId());
              if (
                strpos($flat_keys, '(') !== 0 &&
                strpos($flat_keys, '+(') !== 0 &&
                strpos($flat_keys, '-(') !== 0
              ) {
                $flat_keys = '(' . $flat_keys . ')';
              }
              $new_keys[] = $flat_keys;
            }
          }

          if (count($new_keys) > 1) {
            $new_keys['#conjunction'] = 'OR';
          }
          $new_keys['#escaped'] = TRUE;

          // Preserve the original keys to be set again in postQuery().
          $this->origKeys = $query->getOriginalKeys();

          // The orginal keys array is now already flatten once per language.
          // that means that we already build Solr query strings containing
          // fields and keys. Now we set these query strings again as keys
          // using an OR conjunction but remove all query fields. That will
          // cause the parent class to just concatenate the language specific
          // query strings using OR as they are.
          $query->keys($new_keys);
          $edismax->setQueryFields([]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilterQueries(QueryInterface $query, array $solr_fields, array $index_fields, array &$options) {
    $condition_group = $query->getConditionGroup();
    $conditions = $condition_group->getConditions();
    if (empty($conditions) || empty($query->getLanguages())) {
      return parent::getFilterQueries($query, $solr_fields, $index_fields, $options);
    }

    $fq = [];
    foreach ($conditions as $condition) {
      $language_fqs = [];
      foreach ($query->getLanguages() as $langcode) {
        $language_specific_condition_group = $query->createConditionGroup();
        $language_specific_condition_group->addCondition(SEARCH_API_LANGUAGE_FIELD_NAME, $langcode);
        $language_specific_conditions = &$language_specific_condition_group->getConditions();
        $language_specific_conditions[] = $condition;
        $language_fqs = array_merge($language_fqs, $this->reduceFilterQueries(
          $this->createFilterQueries($language_specific_condition_group, $this->getLanguageSpecificSolrFieldNames($langcode, $solr_fields, reset($index_fields)->getIndex()), $index_fields, $options),
          $condition_group
        ));
      }
      $language_aware_condition_group = $query->createConditionGroup('OR');
      $fq = array_merge($fq, $this->reduceFilterQueries($language_fqs, $language_aware_condition_group, TRUE));
    }

    return $fq;
  }

  /**
   * Gets a language-specific mapping from Drupal to Solr field names.
   *
   * @param string $langcode
   *   The lanaguage to get the mapping for.
   * @param array $solr_fields
   *   The mapping from Drupal to Solr field names.
   * @param \Drupal\search_api\IndexInterface $index_fields
   *   The fields handled by the curent index.
   *
   * @return array
   *   The language-specific mapping from Drupal to Solr field names.
   */
  protected function getLanguageSpecificSolrFieldNames($lancgcode, array $solr_fields, IndexInterface $index) {
    // @todo Caching.
    foreach ($index->getFulltextFields() as $fulltext_field) {
      $solr_fields[$fulltext_field] = Utility::encodeSolrName(Utility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($solr_fields[$fulltext_field], $lancgcode));
    }
    return $solr_fields;
  }

  /**
   * @inheritdoc
   */
  protected function alterSolrResponseBody(&$body, QueryInterface $query) {
    $data = json_decode($body);

    $index = $query->getIndex();
    $field_names = $this->getSolrFieldNames($index, TRUE);
    $doc_languages = [];

    if (isset($data->response)) {
      foreach ($data->response->docs as $doc) {
        $language_id = $doc_languages[$this->createId($index->id(), $doc->{$field_names['search_api_id']})] = $doc->{$field_names[SEARCH_API_LANGUAGE_FIELD_NAME]};
        foreach (array_keys(get_object_vars($doc)) as $language_specific_field_name) {
          $field_name = Utility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($language_specific_field_name);
          if ($field_name != $language_specific_field_name) {
            if (Utility::getLanguageIdFromLanguageSpecificSolrDynamicFieldName($language_specific_field_name) == $language_id) {
              $doc->{$field_name} = $doc->{$language_specific_field_name};
              unset($doc->{$language_specific_field_name});
            }
          }
        }
      }
    }

    if (isset($data->highlighting)) {
      foreach ($data->highlighting as $solr_id => &$item) {
        foreach (array_keys(get_object_vars($item)) as $language_specific_field_name) {
          $field_name = Utility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($language_specific_field_name);
          if ($field_name != $language_specific_field_name) {
            if (Utility::getLanguageIdFromLanguageSpecificSolrDynamicFieldName($language_specific_field_name) == $doc_languages[$solr_id]) {
              $item->{$field_name} = $item->{$language_specific_field_name};
              unset($item->{$language_specific_field_name});
            }
          }
        }
      }
    }

    if (isset($data->facet_counts)) {
      $facet_set_helper = new FacetSet();
      foreach (get_object_vars($data->facet_counts->facet_fields) as $language_specific_field_name => $facet_terms) {
        $field_name = Utility::getSolrDynamicFieldNameForLanguageSpecificSolrDynamicFieldName($language_specific_field_name);
        if ($field_name != $language_specific_field_name) {
          if (isset($data->facet_counts->facet_fields->{$field_name})) {
            // @todo this simple merge of all language specific fields to one
            //   language unspecific fields should be configurable.
            $key_value = $facet_set_helper->convertToKeyValueArray($data->facet_counts->facet_fields->{$field_name}) +
              $facet_set_helper->convertToKeyValueArray($facet_terms);
            $facet_terms = [];
            foreach ($key_value as $key => $value) {
              // @todo check for NULL key of "missing facets".
              $facet_terms[] = $key;
              $facet_terms[] = $value;
            }
          }
          $data->facet_counts->facet_fields->{$field_name} = $facet_terms;
        }
      }
    }

    $body = json_encode($data);
  }

  /**
   * {@inheritdoc}
   */
  protected function postQuery(ResultSetInterface $results, QueryInterface $query, $response) {
    if ($this->origKeys) {
      $query->keys($this->origKeys);
    }

    parent::postQuery($results, $query, $response);
  }

  /**
   * Replaces language unspecific fulltext fields by language specific ones.
   *
   * @param \Solarium\QueryType\Update\Query\Document\Document[] $documents
   *   An array of \Solarium\QueryType\Update\Query\Document\Document objects
   *   ready to be indexed, generated from $items array.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index for which items are being indexed.
   * @param array $items
   *   An array of items being indexed.
   *
   * @see hook_search_api_solr_documents_alter()
   */
  protected function alterSolrDocuments(array &$documents, IndexInterface $index, array $items) {
    parent::alterSolrDocuments($documents, $index, $items);

    $fulltext_fields = $index->getFulltextFields();
    $multiple_field_names = $this->getSolrFieldNames($index);
    $field_names = $this->getSolrFieldNames($index, TRUE);
    $fulltext_field_names = array_filter(array_flip($multiple_field_names) + array_flip($field_names),
      function ($value) use ($fulltext_fields) {
        return in_array($value, $fulltext_fields);
      }
    );

    $field_name_map_per_language = [];
    foreach ($documents as $document) {
      $fields = $document->getFields();
      $language_id = $fields[$field_names[SEARCH_API_LANGUAGE_FIELD_NAME]];
      foreach ($fields as $monolingual_solr_field_name => $field_value) {
        if (array_key_exists($monolingual_solr_field_name, $fulltext_field_names)) {
          $multilingual_solr_field_name = Utility::getLanguageSpecificSolrDynamicFieldNameForSolrDynamicFieldName($monolingual_solr_field_name, $language_id);
          $field_name_map_per_language[$language_id][$monolingual_solr_field_name] = Utility::encodeSolrName($multilingual_solr_field_name);
        }
      }
    }
    foreach ($field_name_map_per_language as $language_id => $map) {
      $solr_field_type_name = Utility::encodeSolrName('text' . '_' . $language_id);
      if (!$this->isPartOfSchema('fieldTypes', $solr_field_type_name) &&
        !$this->createSolrMultilingualFieldType($solr_field_type_name) &&
        !$this->hasLanguageUndefinedFallback()
      ) {
        throw new SearchApiSolrException('Missing field type ' . $solr_field_type_name . ' in schema.');
      }

      // Handle dynamic fields for multilingual tm and ts.
      foreach (['ts', 'tm'] as $prefix) {
        $multilingual_solr_field_name = Utility::encodeSolrName(Utility::getLanguageSpecificSolrDynamicFieldPrefix($prefix, $language_id)) . '*';
        if (!$this->isPartOfSchema('dynamicFields', $multilingual_solr_field_name) &&
          !$this->createSolrDynamicField($multilingual_solr_field_name, $solr_field_type_name) &&
          !$this->hasLanguageUndefinedFallback()
        ) {
          throw new SearchApiSolrException('Missing dynamic field ' . $multilingual_solr_field_name . ' in schema.');
        }
      }
    }

    foreach ($documents as $document) {
      $fields = $document->getFields();
      foreach ($field_name_map_per_language as $language_id => $map) {
        if (/* @todo CLIR || */
          $fields[$field_names[SEARCH_API_LANGUAGE_FIELD_NAME]] == $language_id
        ) {
          foreach ($fields as $monolingual_solr_field_name => $value) {
            if (isset($map[$monolingual_solr_field_name])) {
              if ('twm_suggest' != $monolingual_solr_field_name) {
                $document->addField($map[$monolingual_solr_field_name], $value, $document->getFieldBoost($monolingual_solr_field_name));
                $document->removeField($monolingual_solr_field_name);
              }
            }
          }
        }
      }
    }
  }

  /**
   * Indicates if an 'element' is part of the Solr server's schema.
   *
   * @param string $kind
   *   The kind of the element, for example 'dynamicFields' or 'fieldTypes'.
   *
   * @param string $name
   *   The name of the element.
   *
   * @return bool
   *   True if an element of the given kind and name exists, false otherwise.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function isPartOfSchema($kind, $name) {
    static $previous_calls;

    $state_key = 'sasm.' . $this->getServer()->id() . '.schema_parts';
    $state = \Drupal::state();
    $schema_parts = $state->get($state_key);
    // @todo reset that drupal state from time to time

    if (
      !is_array($schema_parts) || empty($schema_parts[$kind]) ||
      (!in_array($name, $schema_parts[$kind]) && !isset($previous_calls[$kind]))
    ) {
      $response = $this->getSolrConnector()
        ->coreRestGet('schema/' . strtolower($kind));
      if (empty($response[$kind])) {
        throw new SearchApiSolrException('Missing information about ' . $kind . ' in response to REST request.');
      }
      // Delete the old state.
      $schema_parts[$kind] = [];
      foreach ($response[$kind] as $row) {
        $schema_parts[$kind][] = $row['name'];
      }
      $state->set($state_key, $schema_parts);
      $previous_calls[$kind] = TRUE;
    }

    return in_array($name, $schema_parts[$kind]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSchemaLanguageStatistics() {
    $available = $this->getSolrConnector()->pingCore();
    $stats = [];
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $solr_field_type_name = Utility::encodeSolrName('text' . '_' . $language->getId());
      $stats[$language->getId()] = $available ? $this->isPartOfSchema('fieldTypes', $solr_field_type_name) : FALSE;
    }
    return $stats;
  }

  /**
   * {@inheritdoc}
   */
  public function hasLanguageUndefinedFallback() {
    return isset($this->configuration['sasm_language_unspecific_fallback_on_schema_issues']) ?
      $this->configuration['sasm_language_unspecific_fallback_on_schema_issues'] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function setFacets(QueryInterface $query, Query $solarium_query, array $field_names) {
    parent::setFacets($query, $solarium_query, $field_names);

    if ($languages = $query->getLanguages()) {
      foreach ($languages as $language) {
        $language_specific_field_names = $this->getLanguageSpecificSolrFieldNames($language, $field_names, $query->getIndex());
        parent::setFacets($query, $solarium_query, array_diff_assoc($language_specific_field_names, $field_names));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getAutocompleteFields(QueryInterface $query) {
    $fl = [];
    $solr_field_names = $this->getSolrFieldNames($query->getIndex());
    foreach ($query->getLanguages() as $langcode) {
      $fulltext_fields = $this->getQueryFulltextFields($query);
      $language_specific_fulltext_fields = $this->getLanguageSpecificSolrFieldNames($langcode, $solr_field_names, $query->getIndex());
      foreach ($fulltext_fields as $fulltext_field) {
        $fl[] = $language_specific_fulltext_fields[$fulltext_field];
      }
    }
    return $fl;
  }

  /**
   * {@inheritdoc}
   */
  protected function setAutocompleteSuggesterQuery(QueryInterface $query, AutocompleteQuery $solarium_query, $user_input, $options = []) {
    if (isset($options['context_filter_tags']) && in_array('drupal/langcode:multilingual', $options['context_filter_tags'])) {
      $langcodes = $query->getLanguages();
      if (count($langcodes) == 1) {
        $langcode = reset($langcodes);
        $options['context_filter_tags'] = str_replace('drupal/langcode:multilingual', 'drupal/langcode:' . $langcode, $options['context_filter_tags']);
        $options['dictionary'] = $langcode;
      }
      else {
        foreach ($options['context_filter_tags'] as $key => $tag) {
          if ('drupal/langcode:multilingual' == $tag) {
            unset($options['context_filter_tags'][$key]);
            break;
          }
        }
      }
    }

    parent::setAutocompleteSuggesterQuery($query, $solarium_query, $user_input, $options);
  }

}
