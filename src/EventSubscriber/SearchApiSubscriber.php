<?php

namespace Drupal\search_api_solr\EventSubscriber;

use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search API events subscriber.
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * Adds the mapping to treat some Solr special fields as fulltext in views.
   *
   * @param mixed $event
   *   The Search API event.
   * @param array $mapping
   *   An associative array with data types as the keys and Views field data
   *   definitions as the values. In addition to all normally defined data
   *   types, keys can also be "options" for any field with an options list,
   *   "entity" for general entity-typed fields or "entity:ENTITY_TYPE"
   *   (with "ENTITY_TYPE" being the machine name of an entity type)
   *   for entities of that type.
   *
   * @see Drupal/search_api/src/Event/SearchApiEvents.php
   */
  public function onMappingViewsFiledHandlers($event, array &$mapping) {

    $mapping['solr_text_omit_norms'] =
    $mapping['solr_text_suggester'] =
    $mapping['solr_text_unstemmed'] =
    $mapping['solr_text_wstoken'] = [
      'argument' => [
        'id' => 'search_api',
      ],
      'filter' => [
        'id' => 'search_api_fulltext',
      ],
      'sort' => [
        'id' => 'search_api',
      ],
    ];
  }

  /**
   * Subscribed events getter.
   */
  public static function getSubscribedEvents() {
    // Workaround to avoid a fatal error during site install in some cases.
    // @see https://www.drupal.org/project/facets/issues/3199156
    if (!class_exists('\Drupal\search_api\Event\SearchApiEvents', TRUE)) {
      return [];
    }

    return [
      SearchApiEvents::MAPPING_VIEWS_FIELD_HANDLERS => 'onMappingViewsFiledHandlers',
    ];

  }

}
