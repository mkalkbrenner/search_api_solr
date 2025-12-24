<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Drupal\search_api_solr\Utility\Utility;
use Drupal\Tests\search_api_solr\Traits\InvokeMethodTrait;

/**
 * Tests the "map" data type support in the Solr backend.
 *
 * This test verifies that map fields are correctly mapped to Solr field names
 * using the 's' (string) prefix.
 *
 * @group search_api_solr
 *
 * @see \Drupal\search_api_solr\Utility\Utility::getDataTypeInfo()
 * @see \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend::getSolrFieldNames()
 */
class MapDataTypeTest extends KernelTestBase {

  use InvokeMethodTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'user',
    'language',
    'search_api',
    'search_api_solr',
    'search_api_solr_test',
    'system',
  ];

  /**
   * Tests that the map type has the correct Solr data type info.
   */
  public function testMapDataTypeInfo(): void {
    $map_info = Utility::getDataTypeInfo('map');

    $this->assertNotNull($map_info, 'Map data type info exists.');
    $this->assertArrayHasKey('prefix', $map_info, 'Map data type has a prefix defined.');
    $this->assertEquals('s', $map_info['prefix'], 'Map data type uses "s" (string) prefix.');
  }

  /**
   * Tests that map fields get the correct Solr field name prefix.
   */
  public function testMapSolrFieldNames(): void {
    $field = FieldStorageConfig::create([
      'field_name' => 'field_data',
      'entity_type' => 'user',
      'type' => 'map',
      'cardinality' => 1,
    ]);
    $field->save();
    FieldConfig::create([
      'field_storage' => $field,
      'bundle' => 'user',
    ])->save();

    // Create an index with a map field.
    $index = Index::create([
      'id' => 'test_map_index',
      'datasource_settings' => [
        'entity:node' => [
          'plugin_id' => 'entity:node',
          'settings' => [],
        ],
      ],
      'field_settings' => [
        'map_field' => [
          'label' => 'Map field',
          'type' => 'map',
          'datasource_id' => 'entity:node',
          'property_path' => 'uid:entity:field_data',
        ],
      ],
    ]);

    $backend = SearchApiSolrBackend::create($this->container, [], 'test', []);
    $fields = $backend->getSolrFieldNames($index);

    // A single-valued map field should get 'ss_' prefix (string, single-value).
    $this->assertArrayHasKey('map_field', $fields, 'Map field exists in Solr field names.');
    $this->assertEquals('ss_map_field', $fields['map_field'], 'Single-valued map field gets "ss_" Solr field name.');
  }

  /**
   * Tests that multi-valued map fields get the correct Solr field name prefix.
   */
  public function testMultiValuedMapSolrFieldNames(): void {
    // Create a multi-value string field.
    $field = FieldStorageConfig::create([
      'field_name' => 'field_multi_data',
      'entity_type' => 'user',
      'type' => 'map',
      'cardinality' => FieldStorageConfigInterface::CARDINALITY_UNLIMITED,
    ]);
    $field->save();
    FieldConfig::create([
      'field_storage' => $field,
      'bundle' => 'user',
    ])->save();

    // Create an index with a multi-valued map field.
    $index = Index::create([
      'id' => 'test_multi_map_index',
      'datasource_settings' => [
        'entity:node' => [
          'plugin_id' => 'entity:node',
          'settings' => [],
        ],
      ],
      'field_settings' => [
        'multi_map_field' => [
          'label' => 'Multi-valued map field',
          'type' => 'map',
          'datasource_id' => 'entity:node',
          // Use a property path through user to the multi-value field.
          'property_path' => 'uid:entity:field_multi_data',
        ],
      ],
    ]);

    $backend = SearchApiSolrBackend::create($this->container, [], 'test', []);
    $fields = $backend->getSolrFieldNames($index);

    // A multi-valued map field should get 'sm_' prefix (string, multi-value).
    $this->assertArrayHasKey('multi_map_field', $fields, 'Multi-valued map field exists in Solr field names.');
    $this->assertEquals('sm_multi_map_field', $fields['multi_map_field'], 'Multi-valued map field gets "sm_" Solr field name.');
  }

  /**
   * Tests that the map type is included in all data types with proper prefix.
   */
  public function testMapTypeInAllDataTypes(): void {
    $all_types = Utility::getDataTypeInfo();

    $this->assertArrayHasKey('map', $all_types, 'Map type is included in all data types.');
    $this->assertArrayHasKey('prefix', $all_types['map'], 'Map type has prefix in all data types.');
    $this->assertEquals('s', $all_types['map']['prefix'], 'Map type prefix is "s" in all data types.');
  }

  /**
   * Tests that map and string types use the same Solr prefix.
   */
  public function testMapAndStringUseSamePrefix(): void {
    $map_info = Utility::getDataTypeInfo('map');
    $string_info = Utility::getDataTypeInfo('string');

    $this->assertEquals(
      $string_info['prefix'],
      $map_info['prefix'],
      'Map type uses the same Solr prefix as string type.'
    );
  }

}
