<?php

namespace Drupal\search_api_solr\Solarium\EventDispatcher;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Symfony\Component\EventDispatcher\Event;

/**
 * A helper to decorate the legacy EventDispatcherInterface::dispatch().
 */
final class DrupalPsr14Bridge extends ContainerAwareEventDispatcher {

  /**
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $dispatcher;

  public function __construct() {
    $this->dispatcher = \Drupal::service('event_dispatcher');
  }

  public function dispatch($event, Event $null = NULL) {
    if (\is_object($event)) {
      return $this->dispatcher->dispatch(\get_class($event), $event);
    }
    return $this->dispatcher->dispatch($event, $null);
  }
}
