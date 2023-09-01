<?php

namespace Drupal\search_api_solr;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_solr\TypedData\SolrFieldDefinition;
use Psr\Log\LoggerInterface;

/**
 * Manages the discovery of Solr fields.
 */
class SolrFieldManager implements SolrFieldManagerInterface {

  use UseCacheBackendTrait;
  use StringTranslationTrait;
  use LoggerTrait;

  /**
   * Static cache of field definitions per Solr server.
   *
   * @var array
   */
  protected $fieldDefinitions;

  /**
   * Storage for Search API servers.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $serverStorage;

  /**
   * HTTP Client to request shard servers.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a new SorFieldManager.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger for Search API.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(CacheBackendInterface $cache_backend, EntityTypeManagerInterface $entityTypeManager, LoggerInterface $logger) {
    $this->cacheBackend = $cache_backend;
    $this->serverStorage = $entityTypeManager->getStorage('search_api_server');
    $this->setLogger($logger);
    $this->httpClient = new \GuzzleHttp\Client();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function getFieldDefinitions(IndexInterface $index) {
    // We need to prevent the use of the field definition cache when we are
    // about to save changes, or the property check in Index::presave will work
    // with stale cached data and remove newly added property definitions.
    // We take the presence of $index->original as indicator that the config
    // entity is being saved.
    if (!empty($index->original)) {
      return $this->buildFieldDefinitions($index);
    }

    $index_id = $index->id();
    if (!isset($this->fieldDefinitions[$index_id])) {
      // Not prepared, try to load from cache.
      $cid = 'solr_field_definitions:' . $index_id;
      if ($cache = $this->cacheGet($cid)) {
        $field_definitions = $cache->data;
      }
      else {
        $field_definitions = $this->buildFieldDefinitions($index);
        $this->cacheSet($cid, $field_definitions, Cache::PERMANENT, $index->getCacheTagsToInvalidate());
      }

      $this->fieldDefinitions[$index_id] = $field_definitions;
    }
    return $this->fieldDefinitions[$index_id];
  }

  /**
   * Builds the field definitions for a Solr server.
   *
   * Initially the defintions will be built from a the response of a luke query
   * handler directly from Solr. But once added to the Drupal config, the
   * definitions will be a mix of the Drupal config and not yet used fields from
   * Solr. This strategy also covers scenarios when the Solr server is
   * temporarily offline or re-indexed and prevents exceptions in Drupal's admin
   * UI.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index from which we are retrieving field information.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function buildFieldDefinitions(IndexInterface $index) {
    $solr_fields = [];

    if ($this->isShardEnabled($index) === TRUE) {
      $solr_fields = $this->buildFieldDefinitionsFromShardServers($index);
    }
    elseif ($this->isShardEnabled($index) === FALSE) {
      $solr_fields = $this->buildFieldDefinitionsFromSolr($index);
    }

    $config_fields = $this->buildFieldDefinitionsFromConfig($index);
    $fields = $solr_fields + $config_fields;
    /*** @var \Drupal\Core\TypedData\DataDefinitionInterface $field */
    foreach ($config_fields as $key => $field) {
      // Always use the type as already configured in Drupal previously.
      $fields[$key]->setDataType($field->getDataType());
    }
    return $fields;
  }

  protected function isShardEnabled(IndexInterface $index) {
    if ($index->getDatasources()) {
      return (bool)$index->getDatasource('solr_document')
        ->getConfiguration()['enable_shards'];
    }

    return NULL;
  }

  /**
   * Builds the field definitions from exiting index config.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index from which we are retrieving field information.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   */
  protected function buildFieldDefinitionsFromConfig(IndexInterface $index) {
    $fields = [];
    foreach ($index->getFields() as $index_field) {
      $solr_field = $index_field->getPropertyPath();
      $field = new SolrFieldDefinition(['schema' => '']);
      $field->setLabel($index_field->getLabel());
      $field->setDataType($index_field->getType());
      $fields[$solr_field] = $field;
    }
    return $fields;
  }

  /**
   * Builds the field definitions for a Solr server from its Luke handler.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index from which we are retrieving field information.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function buildFieldDefinitionsFromSolr(IndexInterface $index) {
    /** @var \Drupal\search_api\ServerInterface|null $server */
    $server = $index->getServerInstance();
    // Load the server entity.
    if ($server === NULL) {
      throw new \InvalidArgumentException('The Search API server could not be loaded.');
    }

    // In case the targeted Solr index may not have fields (yet) we'll return an
    // empty list.
    $fields = [];

    // Don't attempt to connect to server if config is disabled. Cache will
    // clear itself when server config is enabled again.
    if ($server->status()) {
      $backend = $server->getBackend();
      if (!$backend instanceof SolrBackendInterface) {
        throw new \InvalidArgumentException("The Search API server's backend must be an instance of SolrBackendInterface.");
      }
      try {
        $connector = $backend->getSolrConnector();
        if ($connector instanceof SolrCloudConnectorInterface) {
          $connector->setCollectionNameFromEndpoint(
            $backend->getCollectionEndpoint($index)
          );
        }

        $luke = $connector->getLuke();
        foreach ($luke['fields'] as $name => $definition) {
          $fields[$name] = $this->setFieldDataType($name, $definition);
        }
      } catch (SearchApiSolrException $e) {
        $this->getLogger()
          ->error('Could not connect to server %server, %message', [
            '%server' => $server->id(),
            '%message' => $e->getMessage(),
          ]);
      }
    }
    return $fields;
  }

  /**
   * Builds the field definitions for a Solr server from its Luke handler.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index from which we are retrieving field information.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   The array of field definitions for the server, keyed by field name.
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function buildFieldDefinitionsFromShardServers(IndexInterface $index) {
    $fields = [];
    $shards = $this->getShardServers($index);
    $server = $index->getServerInstance();

    foreach ($shards as $shard) {
      try {
        $response = $this->httpClient->get($shard);
        $shardFields = json_decode((string)$response->getBody(), TRUE)['fields'];
        foreach ($shardFields as $name => $definition) {
          $fields[$name] = $this->setFieldDataType($name, $definition);
        }
      } catch (Exception $e) {
        $this->logger
          ->error('Could not recieve shard servers or their fields, %message', [
            '%server' => $server->id(),
            '%message' => $e->getMessage(),
          ]);
      }
    }

    return $fields;
  }

  /**
   * Maps the Solr field type to a Search API field type.
   *
   * @param $name
   * @param $definition
   *
   * @return \Drupal\search_api_solr\TypedData\SolrFieldDefinition
   */
  protected function setFieldDataType($name, $definition) {
    $field = new SolrFieldDefinition($definition);
    $label = Unicode::ucfirst(trim(str_replace('_', ' ', $name)));
    $field->setLabel($label);

    $type = $field->getDataType();
    if (strpos($type, 'text') !== FALSE) {
      $field->setDataType('search_api_text');
    } elseif (strpos($type, 'date_range') !== FALSE) {
      $field->setDataType('solr_date_range');
    } elseif (strpos($type, 'date') !== FALSE) {
      // The field config UI uses "date" but converts that to "timestamp"
      // internally. We handle this in the mapping.
      /** @see \Drupal\search_api_solr\EventSubscriber\SearchApiSubscriber::onMappingViewsFieldHandlers() */
      $field->setDataType('date');
    } elseif (strpos($type, 'int') !== FALSE) {
      $field->setDataType('integer');
    } elseif (strpos($type, 'long') !== FALSE) {
      $field->setDataType('integer');
    } elseif (strpos($type, 'float') !== FALSE) {
      $field->setDataType('float');
    } elseif (strpos($type, 'double') !== FALSE) {
      $field->setDataType('float');
    } elseif (strpos($type, 'bool') !== FALSE) {
      $field->setDataType('boolean');
    } else {
      $field->setDataType('string');
    }
    return $field;
  }

  /**
   * Extract from shards servers the list of servers to query.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *
   * @return array|mixed|string[]
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function getShardServers(IndexInterface $index) {
    $shards = [];

    if ($index->getServerInstance()) {
      /** @var \Drupal\search_api_solr_shards\Plugin\search_api\datasource\ShardDocument $shardDocument */
      try {
        $shardDocument = $index->getDatasource('solr_document');
        $solrconfig = empty($shardDocument->getConfiguration()['solrconfig'])
          ? 'solrconfig.xml' : $shardDocument->getConfiguration()['solrconfig'];
      } catch (SearchApiException $e) {
        $solrconfig = 'solrconfig.xml';
      }

      try {
        $server = $index->getServerInstance();
        $backend = $server->getBackend();
        $connector = $backend->getSolrConnector();
        $solrconfig = $connector->getFile($solrconfig)->getBody();

        $xml = simplexml_load_string($solrconfig);
        $shards = ((array)$xml->xpath('//*[@name="shards"]')[0])[0];
        if ($shards) {
          $shards = explode(',', $shards);
        }

        // Include the segment to retrieve field-schema information.
        foreach ($shards as &$shard) {
          $shard = "${shard}/admin/luke?wt=json";
        }
      } catch (SearchApiException $e) {
        $this->logger
          ->error('Could not read %file file from remote, %message', [
            '%message' => $e->getMessage(),
            '%file' => $solrconfig
          ]);
      } catch (\Exception $e) {
        $this->logger
          ->error('An error occurred retrieving shards servers, %message', [
            '%message' => $e->getMessage()
          ]);
      }
    }

    return $shards;
  }

}
