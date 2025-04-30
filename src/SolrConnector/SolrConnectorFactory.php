<?php

namespace Drupal\search_api_solr\SolrConnector;

use Drupal\Core\Plugin\Factory\ContainerFactory;

/**
 * SolrConnector plugin factory.
 */
class SolrConnectorFactory extends ContainerFactory {

  /** @var \Drupal\search_api_solr\SolrConnectorInterface[] $instances */
  protected static array $instances = [];

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    ksort($configuration);

    $configuration_hash = md5($plugin_id . json_encode($configuration));

    if (!isset(self::$instances[$configuration_hash])) {
      self::$instances[$configuration_hash] = parent::createInstance($plugin_id, $configuration);
    }

    return self::$instances[$configuration_hash];
  }

}
