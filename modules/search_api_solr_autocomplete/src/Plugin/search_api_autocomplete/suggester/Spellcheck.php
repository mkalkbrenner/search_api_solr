<?php

namespace Drupal\search_api_solr_autocomplete\Plugin\search_api_autocomplete\suggester;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Drupal\search_api_solr\SolrAutocompleteBackendTrait;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr_autocomplete\Event\PreSpellcheckQueryEvent;

/**
 * Provides a suggester plugin that retrieves suggestions from the server.
 *
 * The server needs to support the "search_api_autocomplete" feature for this to
 * work.
 *
 * @SearchApiAutocompleteSuggester(
 *   id = "search_api_solr_spellcheck",
 *   label = @Translation("Solr Spellcheck"),
 *   description = @Translation("Suggest corrections for the entered words based on Solr's spellcheck component. Note: Be careful when activating this feature if you run multiple indexes in one Solr core! The spellcheck component is not able to distinguish between the different indexes and returns suggestions for the complete core. If you run multiple indexes in one core you might get suggestions that lead to zero results on a specific index!"),
 * )
 */
class Spellcheck extends SuggesterPluginBase implements PluginFormInterface {

  use PluginFormTrait;
  use BackendTrait;
  use SolrAutocompleteBackendTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   */
  public static function supportsSearch(SearchInterface $search) {
    /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
    $backend = static::getBackend($search->getIndex());
    return ($backend && version_compare($backend->getSolrConnector()->getSolrMajorVersion(), '4', '>='));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   */
  public function getAutocompleteSuggestions(QueryInterface $query, $incomplete_key, $user_input) {
    $backend = static::getBackend($this->getSearch()->getIndex());

    if (!$backend) {
      return [];
    }

    return $this->getSpellcheckSuggestions($backend, $query, $incomplete_key, $user_input);
  }

  /**
   * Autocompletion suggestions for some user input using Spellcheck component.
   *
   * @param \Drupal\search_api_solr\SolrBackendInterface $backend
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the base search, with all completely entered words
   *   in the user input so far as the search keys.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed. Might be empty, in which case all user input up to now was
   *   considered completed. Then, additional keywords for the search could be
   *   suggested.
   * @param string $user_input
   *   The complete user input for the fulltext search keywords so far.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of autocomplete suggestions.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getSpellcheckSuggestions(SolrBackendInterface $backend, QueryInterface $query, $incomplete_key, $user_input) {
    $suggestions = [];
    if ($solarium_query = $this->getAutocompleteQuery($backend, $incomplete_key, $user_input)) {
      try {
        $suggestion_factory = new SuggestionFactory($user_input);
        $this->setAutocompleteSpellCheckQuery($backend, $query, $solarium_query, $user_input);
        // Allow modules to alter the solarium autocomplete query.
        \Drupal::moduleHandler()->alterDeprecated('hook_search_api_solr_spellcheck_autocomplete_query_alter is deprecated will be removed in Search API Solr 4.3.0. Handle the PreSpellcheckQueryEvent instead.', 'search_api_solr_spellcheck_autocomplete_query', $solarium_query, $query);
        $event = new PreSpellcheckQueryEvent($query, $solarium_query);
        $backend->dispatch($event);
        $result = $backend->getSolrConnector()->autocomplete($solarium_query, $backend->getCollectionEndpoint($query->getIndex()));
        $suggestions = $this->getAutocompleteSpellCheckSuggestions($result, $suggestion_factory);
        // Filter out duplicate suggestions.
        $this->filterDuplicateAutocompleteSuggestions($suggestions);
      }
      catch (SearchApiException $e) {
        watchdog_exception('search_api_solr', $e);
      }
    }

    return $suggestions;
  }

}
