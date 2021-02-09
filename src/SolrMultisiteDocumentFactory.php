<?php

namespace Drupal\search_api_solr;

/**
 * Defines a class for a Solr Document factory.
 */
class SolrMultisiteDocumentFactory extends SolrDocumentFactory {

  /**
   * {@inheritdoc}
   */
  protected static $solrDocument = 'solr_multisite_document';

}
