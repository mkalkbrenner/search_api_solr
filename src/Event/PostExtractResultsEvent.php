<?php

namespace Drupal\search_api_solr\Event;

use Drupal\search_api\Query\QueryInterface;
use Solarium\QueryType\Select\Result\Result;

/**
 * Event to be fired after the search result is extracted from the Solr response.
 */
final class PostExtractResultsEvent extends AbstractSearchApiQuerySolariumResultEvent {}
