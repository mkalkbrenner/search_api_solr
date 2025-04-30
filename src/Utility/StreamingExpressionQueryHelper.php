<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\search_api\Query\QueryInterface;
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

}
