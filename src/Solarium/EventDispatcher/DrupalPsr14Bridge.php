<?php

namespace Drupal\search_api_solr\Solarium\EventDispatcher;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A helper to decorate the legacy EventDispatcherInterface::dispatch().
 *
 * @method dispatch(...$args)
 * @method addListener(...$args)
 * @method addSubscriber(...$args)
 * @method removeListener(...$args)
 * @method removeSubscriber(...$args)
 * @method getListeners(...$args)
 * @method getListenerPriority(...$args)
 * @method hasListeners(...$args)
 */
final class DrupalPsr14Bridge implements EventDispatcherInterface {

  /**
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $dispatcher;

  public function __construct() {
    $this->dispatcher = \Drupal::service('event_dispatcher');
  }

  /**
   * Proxies all method calls to the original event dispatcher.
   */
  public function __call($method, $args) {
    if ('dispatch' === $method && \is_object($args[0])) {
      return $this->dispatcher->dispatch(\get_class($args[0]), $args[0]);
    }

    return $this->dispatcher->{$method}(...$args);
  }
}
