<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a dense vector data type.
 *
 * @SearchApiDataType(
 *   id = "solr_dense_vector",
 *   label = @Translation("Dense Vector"),
 *   description = @Translation("Vector representation of a text."),
 *   prefix = "knn"
 * )
 */
class DenseVectorDataType extends DataTypePluginBase {}
