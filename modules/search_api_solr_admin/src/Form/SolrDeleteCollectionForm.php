<?php

namespace Drupal\search_api_solr_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\ServerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The core reload form.
 *
 * @package Drupal\search_api_solr_admin\Form
 */
class SolrDeleteCollectionForm extends FormBase {

  use LoggerTrait {
    getLogger as getSearchApiLogger;
  }

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Search API server entity.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $search_api_server;

  /**
   * SolrReloadCoreForm constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'solr_delete_collection_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ServerInterface $search_api_server = NULL) {
    $this->search_api_server = $search_api_server;

    /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $backend */
    $backend = $this->search_api_server->getBackend();

    $core = $search_api_server->getBackendConfig()['connector_config']['core'];
    $form['#title'] = $this->t('Delete collection %core?', ['%core' => $core]);

    $form['actions'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      /** @var \Drupal\search_api_solr\SolrCloudConnectorInterface $connector */
      $connector = $this->search_api_server->getBackend()->getSolrConnector();
      $result = $connector->deleteCollection();

      if ($result) {
        $core = $this->search_api_server->getBackendConfig()['connector_config']['core'];
        $this->messenger->addMessage($this->t('Successfully deleted collection %core.', ['%core' => $core]));
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($e->getMessage());
      $this->logException($e);
    }

    $form_state->setRedirect('entity.search_api_server.canonical', ['search_api_server' => $this->search_api_server->id()]);
  }

  protected function getLogger($channel = '') {
    return $this->getSearchApiLogger();
  }

}
