<?php

namespace Drupal\search_api_solr;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Drupal\search_api_solr\Solarium\Autocomplete\Query as AutocompleteQuery;
use Solarium\Component\ComponentAwareQueryInterface;
use Solarium\Core\Query\Result\ResultInterface;

/**
 * Provides a helper method for loading the search backend.
 */
trait SolrAutocompleteBackendTrait {

  /**
   * Returns a Solarium autocomplete query.
   *
   * @param \Drupal\search_api_solr\SolrBackendInterface $backend
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed.
   * @param string $user_input
   *   The complete user input for the fulltext search keywords so far.
   *
   * @return \Drupal\search_api_solr\Solarium\Autocomplete\Query|null
   *   The Solarium autocomplete query or NULL if the Solr version is not
   *   compatible.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getAutocompleteQuery(SolrBackendInterface $backend, &$incomplete_key, &$user_input) {
    // Make the input lowercase as the indexed data is (usually) also all
    // lowercase.
    $incomplete_key = mb_strtolower($incomplete_key);
    $user_input = mb_strtolower($user_input);
    $connector = $backend->getSolrConnector();
    $solr_version = $connector->getSolrVersion();
    if (version_compare($solr_version, '6.5', '=')) {
      $this->getLogger()
        ->error('Solr 6.5.x contains a bug that breaks the autocomplete feature. Downgrade to 6.4.x or upgrade to 6.6.x at least.');
      return NULL;
    }

    return $connector->getAutocompleteQuery();
  }

  /**
   * Set the spellcheck parameters for the solarium autocomplete query.
   *
   * @param \Drupal\search_api_solr\SolrBackendInterface $backend
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   * @param \Drupal\search_api_solr\Solarium\Autocomplete\Query $solarium_query
   *   A Solarium autocomplete query.
   * @param string $user_input
   *   The user input.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  protected function setAutocompleteSpellCheckQuery(SolrBackendInterface $backend, QueryInterface $query, AutocompleteQuery $solarium_query, $user_input) {
    $backend->setSpellcheck($solarium_query, $query, [
      'keys' => [$user_input],
      'count' => $query->getOption('limit') ?? 1,
    ]);
  }

  /**
   * Get the spellcheck suggestions from the autocomplete query result.
   *
   * @param \Solarium\Core\Query\Result\ResultInterface $result
   *   An autocomplete query result.
   * @param \Drupal\search_api_autocomplete\Suggestion\SuggestionFactory $suggestion_factory
   *   The suggestion factory.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of suggestions.
   */
  protected function getAutocompleteSpellCheckSuggestions(ResultInterface $result, SuggestionFactory $suggestion_factory) {
    $suggestions = [];
    foreach ($this->extractSpellCheckSuggestions($result) as $spellcheck_suggestions) {
      foreach ($spellcheck_suggestions as $keys) {
        $suggestions[] = $suggestion_factory->createFromSuggestedKeys($keys);
      }
    }
    return $suggestions;
  }

  /**
   * Get the spellcheck suggestions from the autocomplete query result.
   *
   * @param \Solarium\Core\Query\Result\ResultInterface $result
   *   An autocomplete query result.
   *
   * @return array
   *   An array of suggestions.
   */
  protected function extractSpellCheckSuggestions(ResultInterface $result) {
    $suggestions = [];
    if ($spellcheck_results = $result->getComponent(ComponentAwareQueryInterface::COMPONENT_SPELLCHECK)) {
      foreach ($spellcheck_results as $term_result) {
        $keys = [];
        /** @var \Solarium\Component\Result\Spellcheck\Suggestion $term_result */
        foreach ($term_result->getWords() as $correction) {
          $keys[] = $correction['word'];
        }
        if ($keys) {
          $suggestions[$term_result->getOriginalTerm()] = $keys;
        }
      }
    }
    return $suggestions;
  }

  /**
   * Removes duplicated autocomplete suggestions from the given array.
   *
   * @param array $suggestions
   *   The array of suggestions.
   */
  protected function filterDuplicateAutocompleteSuggestions(array &$suggestions) {
    $added_suggestions = [];
    $added_urls = [];
    /** @var \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface $suggestion */
    foreach ($suggestions as $key => $suggestion) {
      if (
        !in_array($suggestion->getSuggestedKeys(), $added_suggestions, TRUE) ||
        !in_array($suggestion->getUrl(), $added_urls, TRUE)
      ) {
        $added_suggestions[] = $suggestion->getSuggestedKeys();
        $added_urls[] = $suggestion->getUrl();
      }
      else {
        unset($suggestions[$key]);
      }
    }
  }

}
