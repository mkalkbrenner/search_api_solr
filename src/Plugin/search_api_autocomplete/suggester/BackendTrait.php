<?php

namespace Drupal\search_api_solr\Plugin\search_api_autocomplete\suggester;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api_solr\SolrAutocompleteInterface;

/**
 * Provides a helper method for loading the search backend.
 * @deprecated in search_api_solr:4.3.0 and is removed from search_api_solr:5.0.0. Use the
 *   \Drupal\search_api_solr_autocomplete\Plugin\search_api_autocomplete\suggester\BackendTrait instead
 *
 * @see https://www.drupal.org/node/3254186
 */
trait BackendTrait {

  use LoggerTrait;

  /**
   * Retrieves the backend for the given index, if it supports autocomplete.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return \Drupal\search_api_solr\SolrAutocompleteInterface|null
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
        return $backend;
      }
    }
    catch (\Exception $e) {
      watchdog_exception('search_api', $e);
    }
    return NULL;
  }

}
