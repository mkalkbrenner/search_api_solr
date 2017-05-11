<?php

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\search_api\Kernel\BackendTestBase;

/**
 * Tests index and search capabilities using the Solr search backend.
 *
 * @group search_api_solr
 */
class SearchApiSolrMultilingualLocationTest extends SearchApiSolrLocationTest {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = array(
    'language',
    'search_api_solr_multilingual',
    'search_api_solr_multilingual_test',
  );

  /**
   * A Search API server ID.
   *
   * @var string
   */
  protected $serverId = 'solr_multilingual_search_server';

  /**
   * A Search API index ID.
   *
   * @var string
   */
  protected $indexId = 'solr_multilingual_search_index';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    BackendTestBase::setUp();

    $this->installConfig([
      'search_api_solr',
      'search_api_solr_multilingual',
      'search_api_solr_multilingual_test',
    ]);

    $this->commonSolrBackendSetUp();
  }

}
