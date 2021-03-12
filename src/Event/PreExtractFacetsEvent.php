<?php

namespace Drupal\search_api_solr\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api\Query\QueryInterface;
use Solarium\QueryType\Select\Result\Result;

// Drupal >= 9.1.
if (class_exists('\Drupal\Component\EventDispatcher\Event')) {
  /**
   * Event to be fired before facets are extracted from the solarium result.
   */
  final class PreExtractFacetsEvent extends Event {

    /**
     * The search_api query.
     *
     * @var \Drupal\search_api\Query\QueryInterface
     */
    protected $query;

    /**
     * The solarium result.
     *
     * @var \Solarium\QueryType\Select\Result\Result
     */
    protected $result;

    /**
     * Constructs a new class instance.
     *
     * @param \Drupal\search_api\Query\QueryInterface $query
     *   The search_api query.
     * @param \Solarium\QueryType\Select\Result\Result $result
     *   The solarium result.
     */
    public function __construct(QueryInterface $query, Result $result) {
      $this->query = $query;
      $this->result = $result;
    }

    /**
     * Retrieves the search_api query.
     *
     * @return \Drupal\search_api\Query\QueryInterface
     *   The created query.
     */
    public function getQuery() {
      return $this->query;
    }

    /**
     * Retrieves the solarium result.
     *
     * @return \Solarium\QueryType\Select\Result\Result
     *   The solarium result.
     */
    public function getResult() {
      return $this->result;
    }

  }
}
else {
  /**
   * Event to be fired before facets are extracted from the solarium result.
   */
  final class PreExtractFacetsEvent extends \Symfony\Component\EventDispatcher\Event {

    /**
     * The search_api query.
     *
     * @var \Drupal\search_api\Query\QueryInterface
     */
    protected $query;

    /**
     * The solarium result.
     *
     * @var \Solarium\QueryType\Select\Result\Result
     */
    protected $result;

    /**
     * Constructs a new class instance.
     *
     * @param \Drupal\search_api\Query\QueryInterface $query
     *   The search_api query.
     * @param \Solarium\QueryType\Select\Result\Result $result
     *   The solarium result.
     */
    public function __construct(QueryInterface $query, Result $result) {
      $this->query = $query;
      $this->result = $result;
    }

    /**
     * Retrieves the search_api query.
     *
     * @return \Drupal\search_api\Query\QueryInterface
     *   The created query.
     */
    public function getQuery() {
      return $this->query;
    }

    /**
     * Retrieves the solarium result.
     *
     * @return \Solarium\QueryType\Select\Result\Result
     *   The solarium result.
     */
    public function getResult() {
      return $this->result;
    }

  }
}