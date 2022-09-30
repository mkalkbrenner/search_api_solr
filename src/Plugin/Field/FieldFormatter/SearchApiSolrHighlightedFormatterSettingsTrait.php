<?php

namespace Drupal\search_api_solr\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Common formatter settings for SearchApiSolrHighlighted* formatters
 */
trait SearchApiSolrHighlightedFormatterSettingsTrait {

  public static function defaultSettings() {
    return [
        'prefix' => '<strong>',
        'suffix' => '</strong>',
      ] + parent::defaultSettings();
  }

  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['prefix'] = [
      '#title' => t('Prefix'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('prefix'),
      '#description' => t('The prefix for a highlighted snippet, usually a HTML tag. Ensure that the selected text format for this field allows this prefix.'),
    ];

    $form['suffix'] = [
      '#title' => t('Suffix'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('suffix'),
      '#description' => t('The prefix for a highlighted snippet, usually a HTML tag. Ensure that the selected text format for this field allows this suffix.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = t('Highlighting: @prefixtext snippet@suffix', ['@prefix' => $this->getSetting('prefix'), '@suffix' => $this->getSetting('suffix')]);
    return $summary;
  }

}
