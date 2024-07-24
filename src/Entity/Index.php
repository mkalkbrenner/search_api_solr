<?php

namespace Drupal\search_api_solr\Entity;

use Drupal\search_api\Entity\Index as SearchApiIndex;

class Index extends SearchApiIndex {

  /**
   * {@inheritdoc}
   */
  public function getLockId(): string {
    if ($this->hasValidTracker() && $this->getTrackerId() === 'index_parallel') {
      /** @var \Drupal\search_api_solr\Plugin\search_api\tracker\IndexParallel $tracker */
      $tracker = $this->getTrackerInstance();
      return "search_api:index:{$this->id}:thread:{$tracker->getThread()}";
    }

    return parent::getLockId();
  }

}
