<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type\value;

/**
 * Provides an interface for vector field values.
 */
interface DenseVectorValueInterface {

  /**
   * Return the vector values.
   *
   * @return float[]
   *   An array of vectors for the field.
   */
  public function getVectors(): array;

}
