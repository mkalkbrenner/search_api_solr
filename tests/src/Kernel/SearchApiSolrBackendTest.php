<?php

/**
 * @file
 * Contains \Drupal\Tests\search_api_solr\Kernel\SearchApiSolrBackendTest.
 */

namespace Drupal\Tests\search_api_solr\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Solarium\QueryType\Update\Query\Document\Document;

/**
 * @group search_api_solr
 *
 * @coversDefaultClass \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend
 */
class SearchApiSolrBackendTest extends KernelTestBase {

  use SearchApiSolrTestTrait;

  /**
   * @covers ::addIndexField
   */
  public function testAddIndexField() {
    $backend = SearchApiSolrBackend::create($this->container, [], 'test', []);
    $doc = new Document();

    $date = [
      '1465819200',
      '2016-06-13T12:00:00',
    ];

    $this->invokeMethod($backend, 'addIndexField', [$doc, 'key', $date, 'date']);

    $this->assertSame([
      '2016-06-13T12:00:00Z',
      '2016-06-13T12:00:00Z',
    ], $doc->getFields()['key']);
  }


}
