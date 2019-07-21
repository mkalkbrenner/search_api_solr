<?php

namespace Drupal\search_api_solr\Plugin\DataType\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Provides data type plugins for each index.
 */
class SolrDocumentDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * List of derivative definitions.
   *
   * @var array
   */
  protected $derivatives = [];

  /**
   * The base plugin ID this derivative is for.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')
    );
  }

  /**
   * SolrDocumentDeriver constructor.
   *
   * @param string $base_plugin_id
   *    Base plugin ID.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *    Entity type manager.
   */
  public function __construct($base_plugin_id, EntityTypeManager $entity_type_manager) {
    $this->basePluginId = $base_plugin_id;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Also keep the 'solr_document' defined without any index.
    $this->derivatives[''] = $base_plugin_definition;
    // Load all indexes and filter out solr-based.
    $indexes = $this->entityTypeManager->getStorage('search_api_index')->loadMultiple();
    /* @var \Drupal\search_api\Entity\Index $entity */
    foreach ($indexes as $index_id => $entity) {
      if ($entity->getServerInstance()
            && $entity->getServerInstance()->getBackend() instanceof SolrBackendInterface
            && Utility::hasIndexSolrDatasources($entity)) {
        // Does it make sense to get constraints from index entity?
        $this->derivatives[$index_id] = [
          'label' => $base_plugin_definition['label'] . ':' . $entity->label(),
        ]
        + $base_plugin_definition;
      }
    }
    return $this->derivatives;
  }

}
