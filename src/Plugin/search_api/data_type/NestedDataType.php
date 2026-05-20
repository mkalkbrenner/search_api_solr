<?php

namespace Drupal\search_api_solr\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a string data type.
 *
 * @SearchApiDataType(
 *   id = "nested",
 *   label = @Translation("Nested data"),
 *   description = @Translation("Nested object"),
 *   prefix = "nes"
 * )
 */
class NestedDataType extends DataTypePluginBase {

}
