<?php

namespace Drupal\search_api_solr_autocomplete\Plugin\search_api_autocomplete\suggester;

use Drupal\search_api\IndexInterface;
use Drupal\search_api_solr\SolrAutocompleteInterface;

/**
 * Provides a helper method for loading the search backend.
 */
trait BackendTrait {

  /**
   * Retrieves the backend for the given index, if it supports autocomplete.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return \Drupal\search_api_solr\SolrBackendInterfaceInterface|null
   *   The backend plugin of the index's server, if it exists and supports
   *   autocomplete; NULL otherwise.
   */
  protected static function getBackend(IndexInterface $index) {
    try {
      if (
        $index->hasValidServer() &&
        ($server = $index->getServerInstance()) &&
        ($backend = $server->getBackend()) &&
        $backend instanceof SolrAutocompleteInterface &&
        $server->supportsFeature('search_api_autocomplete')
      ) {
        /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
        return $backend;
      }
    }
    catch (\Exception $e) {
      watchdog_exception('search_api', $e);
    }
    return NULL;
  }

}
