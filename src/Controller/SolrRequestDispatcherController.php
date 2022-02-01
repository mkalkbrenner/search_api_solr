<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrConfigInterface;

/**
 * Provides different listings of SolrRequestDispatcher.
 */
class SolrRequestDispatcherController extends AbstractSolrEntityController {

  /**
   * Entity type id.
   *
   * @var string
   */
  protected $entityTypeId = 'solr_request_dispatcher';

}
