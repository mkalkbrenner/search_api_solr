<?php

namespace Drupal\search_api_solr;

use Solarium\Core\Client\Endpoint;
use Solarium\QueryType\Graph\Query as GraphQuery;
use Solarium\QueryType\Stream\Query as StreamQuery;

/**
 * The Solr Cloud connector interface.
 */
interface SolrCloudConnectorInterface extends SolrConnectorInterface {

  /**
   * Returns the Solr collection name.
   *
   * @return string
   *   The Solr collection name.
   */
  public function getCollectionName();

  /**
   * Returns a link to the Solr collection, if the necessary options are set.
   *
   * @return \Drupal\Core\Link
   *   The link to the Solr collection.
   */
  public function getCollectionLink();

  /**
   * Gets information about the Solr Collection.
   *
   * @param bool $reset
   *   If TRUE the server will be asked regardless if a previous call is cached.
   *
   * @return object
   *   A response object with system information.
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getCollectionInfo($reset = FALSE);

  /**
   * Pings the Solr collection to tell whether it can be accessed.
   *
   * @return mixed
   *   The latency in milliseconds if the core can be accessed,
   *   otherwise FALSE.
   */
  public function pingCollection();

  /**
   * Creates a new Solarium stream query.
   *
   * @return \Solarium\QueryType\Stream\Query
   *   The Stream query.
   */
  public function getStreamQuery();

  /**
   * Creates a new Solarium graph query.
   *
   * @return \Solarium\QueryType\Graph\Query
   *   The Graph query.
   */
  public function getGraphQuery();

  /**
   * Executes a stream query.
   *
   * @param \Solarium\QueryType\Stream\Query $query
   *   The Solarium stream query.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint.
   *
   * @return \Solarium\QueryType\Stream\Result
   *   The Solarium stream result.
   */
  public function stream(StreamQuery $query, Endpoint $endpoint = NULL);

  /**
   * Executes a graph query.
   *
   * @param \Solarium\QueryType\Graph\Query $query
   *   The Solarium graph query.
   * @param \Solarium\Core\Client\Endpoint|null $endpoint
   *   (optional) The Solarium endpoint.
   *
   * @return \Solarium\QueryType\Graph\Result
   *   The Solarium graph result.
   */
  public function graph(GraphQuery $query, Endpoint $endpoint = NULL);

}
