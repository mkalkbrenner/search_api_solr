<?php

namespace Drupal\search_api_solr\Solarium\EventDispatcher;

use Symfony\Component\EventDispatcher\Event as LegacyEvent;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A proxy for events defined by symfony contracts.
 */
class EventProxy extends LegacyEvent {

  /**
   * The Symfony contracts event.
   *
   * @var \Symfony\Contracts\EventDispatcher\Event
   */
  protected $event;

  /**
   * EventProxy constructor.
   *
   * @param \Symfony\Contracts\EventDispatcher\Event $event
   *   The Symfony contracts event.
   */
  public function __construct(Event $event) {
    $this->event = $event;
  }

  /**
   * {@inheritdoc}
   */
  public function isPropagationStopped() {
    return $this->event->isPropagationStopped();
  }

  /**
   * {@inheritdoc}
   */
  public function stopPropagation() {
    $this->event->stopPropagation();
  }

  /**
   * Proxies all method calls to the original event.
   */
  public function __call($method, $arguments) {
    return $this->event->{$method}(...$arguments);
  }

}
