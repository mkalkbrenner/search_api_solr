<?php

namespace Drupal\search_api_solr\Plugin\search_api_autocomplete\suggester;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggester\SuggesterPluginBase;

/**
 * Provides a suggester plugin that retrieves suggestions from the server.
 *
 * The server needs to support the "search_api_autocomplete" feature for this to
 * work.
 */
class Spellcheck extends SuggesterPluginBase implements PluginFormInterface {

  use PluginFormTrait;
  use BackendTrait;

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
    if (!($backend = static::getBackend($this->getSearch()->getIndex()))) {
      return [];
    }

    return $backend->getSpellcheckSuggestions($query, $this->getSearch(), $incomplete_key, $user_input);
  }

}
