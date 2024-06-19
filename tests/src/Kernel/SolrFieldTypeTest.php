<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\config_test\TestInstallStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\TypedConfigManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Provides tests for Solr field type configs.
 *
 * @group search_api_solr
 */
class SolrFieldTypeTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'search_api',
    'search_api_solr',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $languageIds = [];
    $configNames = array_keys(\Drupal::service('file_system')->scanDirectory(__DIR__ . '/../../../config', '/search_api_solr.solr_field_type.text_/', ['key' => 'name']));
    foreach ($configNames as $config_name) {
      preg_match('/search_api_solr.solr_field_type.text_(.*)_\d+_\d+_\d+/', $config_name, $matches);
      $languageIds[] = $matches[1];
    }
    $languageIds = array_unique($languageIds);

    foreach ($languageIds as $language_id) {
      if ('und' != $language_id) {
        ConfigurableLanguage::createFromLangcode($language_id)->save();
      }
    }
  }

  /**
   * Tests all available Solr field type configs.
   */
  public function testDefaultConfig() {
    // Create a typed config manager with access to configuration schema in
    // every module, profile and theme.
    $typed_config = new TypedConfigManager(
      \Drupal::service('config.storage'),
      new TestInstallStorage(InstallStorage::CONFIG_SCHEMA_DIRECTORY),
      \Drupal::service('cache.discovery'),
      \Drupal::service('module_handler'),
      \Drupal::service('class_resolver')
    );
    $typed_config->setValidationConstraintManager(\Drupal::service('validation.constraint'));
    // Avoid restricting to the config schemas discovered.
    $this->container->get('cache.discovery')->delete('typed_config_definitions');

    // Create a configuration storage with access to default configuration in
    // every module, profile and theme.
    $default_config_storage = new TestInstallStorage('test_search_api_solr');

    foreach ($default_config_storage->listAll() as $config_name) {
      if (str_starts_with($config_name, 'search_api_solr.')) {
        $data = $default_config_storage->read($config_name);
        $this->assertConfigSchema($typed_config, $config_name, $data);
      }
    }
  }

}
