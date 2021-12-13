<?php

namespace Drupal\search_api_solr\Plugin\search_api_autocomplete\suggester;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\suggester\Server;

/**
 * Provides a suggester that retrieves suggestions from Solr's Terms component.
 *
 * @deprecated in search_api_solr:4.3.0 and is removed from search_api_solr:5.0.0. Use the
 *    \Drupal\search_api_solr_autocomplete\Plugin\search_api_autocomplete\suggester\Terms instead
 *
 * @see https://www.drupal.org/node/3254186
 */
class Terms extends Server {

  use BackendTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_autocomplete\SearchApiAutocompleteException
   */
  public function getAutocompleteSuggestions(QueryInterface $query, $incomplete_key, $user_input) {
    if (!($backend = static::getBackend($this->getSearch()->getIndex()))) {
      return [];
    }

    if ($this->configuration['fields']) {
      $query->setFulltextFields($this->configuration['fields']);
    }
    else {
      $query->setFulltextFields($query->getIndex()->getFulltextFields());
    }

    return $backend->getAutocompleteSuggestions($query, $this->getSearch(), $incomplete_key, $user_input);
  }

}
