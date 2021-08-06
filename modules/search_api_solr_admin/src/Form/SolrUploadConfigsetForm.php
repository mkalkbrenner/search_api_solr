<?php

namespace Drupal\search_api_solr_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\ServerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The upload configset form.
 *
 * @package Drupal\search_api_solr_admin\Form
 */
class SolrUploadConfigsetForm extends FormBase {

  /**
   * The Search API server entity.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  private $search_api_server;

  /**
   * SolrUploadConfigsetForm constructor.
   *
   * @param \Drupal\search_api_solr\Form\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'solr_upload_configset_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ServerInterface $search_api_server = NULL) {
    $this->search_api_server = $search_api_server;

    $core = $search_api_server->getBackendConfig()['connector_config']['core'];
    $form['#title'] = $this->t('Reload core %core', ['%core' => $core]);

    $form['actions'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Reload'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

}
