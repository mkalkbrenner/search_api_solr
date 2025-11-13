<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type\value;

/**
 * Represents a single dense vector value.
 */
class DenseVectorValue implements DenseVectorValueInterface {

  /**
   * Constructs a DenseVectorValue object.
   *
   * @param float[] $vectors
   *   The vectors.
   */
  public function __construct(protected array $vectors) {
  }

  /**
   * {@inheritdoc}
   */
  public function getVectors(): array {
    return $this->vectors;
  }

}
