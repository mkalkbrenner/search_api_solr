<?php

namespace Drupal\search_api_solr\Plugin\search_api_autocomplete\suggester;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\suggester\Server;

/**
 * Provides a suggester that retrieves suggestions from Solr's Terms component.
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
