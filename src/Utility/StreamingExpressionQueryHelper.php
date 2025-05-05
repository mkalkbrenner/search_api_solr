<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\search_api\Query\Query;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\QueryHelper;

/**
 * Provides methods for creating streaming expressions.
 */
class StreamingExpressionQueryHelper extends QueryHelper {
  /** @var StreamingExpressionBuilder[] $instances */
  protected static array $instances = [];

  /**
   * Builds a streaming expression for the given Search API query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query.
   *
   * @return \Drupal\search_api_solr\Utility\StreamingExpressionBuilder
   *   The StreamingExpressionBuilder object.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public function getStreamingExpressionBuilder(QueryInterface $query): StreamingExpressionBuilder {
    $index_id = $query->getIndex()->id();

    if (!isset(self::$instances[$index_id])) {
      // Getting all required data of the index is expensive. So we use a
      // singleton pattern for the streaming expression builder.
      self::$instances[$index_id] = new StreamingExpressionBuilder($query->getIndex());
    }

    if ($query instanceof Query) {
      $query->setQueryHelper($this);
    }

    return self::$instances[$index_id];
  }

  /**
   * Applies a streaming expression for a given Search API query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query.
   * @param \Drupal\search_api_solr\Utility\string $streaming_expression
   *   The streaming expression to set for this query.
   * @param \Drupal\search_api_solr\Utility\string $comment
   *   A comment of the streaming expression.
   */
  public function setStreamingExpression(QueryInterface $query, string $streaming_expression, string $comment = ''): void {
    if ($comment) {
      $query->setOption('solr_streaming_expression_comment', $comment);
    }
    $query->setOption('solr_streaming_expression', $streaming_expression);
  }

  /**
   * {@inheritdoc}
   *
   * The original implementation becomes slow if you run a lot of streaming
   * expressions in a script. Usually, nobody needs the result cache in
   * combination with streaming expressions. But the edge case of replacing a
   * view's query with a streaming expression is covered by "caching" the last
   * result only.
   */
  public function addResults(ResultSetInterface $results): void {
    $this->results[$this] = $results;
  }

  /**
   * {@inheritdoc}
   */
  public function getResults($search_id): ?ResultSetInterface {
    if (isset($this->results[$this]) && $this->results[$this]->getQuery()->getSearchId(FALSE) !== $search_id) {
      throw new \LogicException('The streaming expression query results are not cached.');
    }
    return $this->results[$this]?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllResults(): array {
    return [$this->results[$this]];
  }

  /**
   * {@inheritdoc}
   */
  public function removeResults($search_id): void {
    if (isset($this->results[$this]) && $this->results[$this]->getQuery()->getSearchId(FALSE) === $search_id) {
      $this->results[$this] = NULL;
    }
  }

}
