<?php

namespace Drupal\search_api_solr\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event to be fired before a value gets indexed as language fallback.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\LanguageWithFallback
 */
final class PreAddLanguageFallbackFieldEvent extends Event {

  /**
   * The language ID.
   *
   * @var string
   */
  protected $langcode;

  /**
   * The field value.
   *
   * @var mixed
   */
  protected $value;

  /**
   * The field type.
   *
   * @var string
   */
  protected $type;

  /**
   * Constructs a new class instance.
   *
   * @param string $langcode
   *   The language ID.
   * @param mixed $value
   *   The filed value.
   * @param string $type
   *   The field type.
   */
  public function __construct(string $langcode, mixed $value, string $type) {
    $this->langcode = $langcode;
    $this->value = $value;
    $this->type = $type;
  }

  /**
   * Retrieves the language ID.
   *
   * @return string
   *   The language ID.
   */
  public function getLangcode(): string {
    return $this->langcode;
  }

  /**
   * Retrieves the field values.
   *
   * @return mixed
   *   The field values.
   */
  public function getValue(): mixed {
    return $this->value;
  }

  /**
   * Set the field value.
   *
   * @param array $value
   *   The field value. If you supply NULL as the value and no modifier the
   *   field will be removed.
   */
  public function setValue(mixed $value): void {
    $this->value = $value;
  }

  /**
   * Retrieves the field type.
   *
   * @return string
   *   The field type.
   */
  public function getType(): string {
    return $this->type;
  }

}
