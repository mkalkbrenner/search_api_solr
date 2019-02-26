<?php

namespace Drupal\search_api_solr\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Language\LanguageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a ConfigSubscriber that adds language-specific Solr Field Types.
 *
 * Whenever a new language is enabled this EventSubscriber installs all
 * available Solr Field Types for that language.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The Config Installer.
   *
   * @var \Drupal\Core\Config\ConfigInstallerInterface
   */
  protected $configInstaller;

  /**
   * Constructs a ConfigSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigInstallerInterface $configInstaller
   *   The Config Installer.
   */
  public function __construct(ConfigInstallerInterface $configInstaller) {
    $this->configInstaller = $configInstaller;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onConfigSave'];
    return $events;
  }

  /**
   * Installs all available Solr Field Types for a new language.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $saved_config = $event->getConfig();

    if (preg_match('@^language\.entity\.(.+)@', $saved_config->getName(), $matches) &&
        $matches[1] != LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      $restrict_by_dependency = [
        'module' => 'search_api_solr',
      ];
      // installOptionalConfig will not replace existing configs and it contains
      // a dependency check so we need not perform any checks ourselves.
      $this->configInstaller->installOptionalConfig(NULL, $restrict_by_dependency);
    }

    // @todo alert to trigger new config when an index is added => context
  }

}
