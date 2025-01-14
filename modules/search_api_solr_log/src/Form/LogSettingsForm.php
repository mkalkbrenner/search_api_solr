<?php

namespace Drupal\search_api_solr_log\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Solr log settings form.
 */
class LogSettingsForm extends ConfigFormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_solr_log_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['search_api_solr_log.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('search_api_solr_log.settings');
    $form['days_to_keep'] = [
      '#type' => 'number',
      '#title' => $this->t('Days to keep logs'),
      '#description' => $this->t('Logged events older than the given amount of days will be deleted from Solr by cron.'),
      '#required' => TRUE,
      '#min' => 1,
      '#step' => 1,
      '#default_value' => $config->get('days_to_keep') ?? 14,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('search_api_solr_log.settings');
    $config->set('days_to_keep', (int) $form_state->getValue('days_to_keep'))->save();
    parent::submitForm($form, $form_state);
  }

}
