<?php

namespace Drupal\search_api_solr_admin\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\ServerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\Utility\SolrCommandHelper;
use Drupal\search_api_solr\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The upload configset form.
 *
 * @package Drupal\search_api_solr_admin\Form
 */
class SolrUploadConfigsetForm extends FormBase {

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
   * The Search API server entity.
   *
   * @var \Drupal\search_api_solr\Utility\SolrCommandHelper
   */
  protected $commandHelper;

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * SolrUploadConfigsetForm constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger, SolrCommandHelper $commandHelper, FileSystemInterface $fileSystem) {
    $this->messenger = $messenger;
    $this->commandHelper = $commandHelper;
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('search_api_solr.command_helper'),
      $container->get('file_system')
    );
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

    /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $backend */
    $backend = $this->search_api_server->getBackend();
    /** @var \Drupal\search_api_solr\SolrCloudConnectorInterface $connector */
    $connector = $backend->getSolrConnector();
    $configset = $connector->getConfigSetName();
    if (!$configset) {
      $this->messenger->addWarning($this->t('No existing configset name could be detected on the Solr server for this collection. That\'s fine if you just create a new collection. Otherwise you should check the logs.'));
    }

    $core = $this->search_api_server->getBackendConfig()['connector_config']['core'];
    $form['#title'] = $this->t('Upload Configset for %collection?', ['%collection' => $core]);

    $form['accept'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Upload (and overwrite) configset %configset to Solr Server.', ['%configset' => $configset]),
      '#default_value' => FALSE,
    ];

    if ($configset) {
      $form['reload_collection'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Reload the collection using the new configset.'),
        '#default_value' => FALSE,
      ];
    }
    else {
      $form['create_collection'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Create the collection using the new configset.'),
        '#default_value' => FALSE,
      ];

      $form['num_shards'] = [
        '#type' => 'number',
        '#title' => $this->t('Number of shards'),
        '#description' => $this->t('The number of shards to be created as part of the collection.'),
        '#default_value' => 3,
        '#states' => [
          'invisible' => [':input[name="create_collection"]' => ['checked' => FALSE]],
        ],
      ];

    }

    $form['configset'] = [
      '#type' => 'value',
      '#default_value' => $configset ?: Utility::generateConfigsetName($this->search_api_server),
    ];

    $form['actions'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Upload'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('accept')) {
      $form_state->setError($form['accept'], $this->t('You must accept the action that will be taken after the configset is uploaded.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $configset = $form_state->getValue('configset');

    $filename = $this->fileSystem->tempnam($this->fileSystem->getTempDirectory(), 'configset_') . '.zip';

    $this->commandHelper->getServerConfigCommand($this->search_api_server->id(), $filename);

    /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $backend */
    $backend = $this->search_api_server->getBackend();
    /** @var \Drupal\search_api_solr\SolrCloudConnectorInterface $connector */
    $connector = $backend->getSolrConnector();

    try {
      if ($connector->uploadConfigset($configset, $filename)) {
        $this->messenger->addStatus($this->t('Successfully uploaded configset %configset.', ['%configset' => $configset]));
        if ($form_state->getValue('reload_collection')) {
          try {
            if ($connector->reloadCollection()) {
              $this->messenger->addStatus($this->t('Successfully reloaded collection %collection.', ['%collection' => $connector->getCollectionName()]));
              foreach($this->search_api_server->getIndexes() as $index) {
                if ($index->status() && !$index->isReadOnly()) {
                  $index->reindex();
                }
              }
            }
            else {
              $this->messenger->addError($this->t('Reloading collection %collection failed.', ['%collection' => $connector->getCollectionName()]));
            }
          } catch (SearchApiSolrException $e) {
            $this->logException($e);
            $this->messenger->addError($this->t('Reloading collection %collection failed.', ['%collection' => $connector->getCollectionName()]));
          }
        }
        elseif ($form_state->getValue('create_collection')) {
          try {
            if ($connector->createCollection(['collection.configName' => $configset, 'numShards' => (int) $form_state->getValue('num_shards')])) {
              $this->messenger->addStatus($this->t('Successfully created collection %collection.', ['%collection' => $connector->getCollectionName()]));
              foreach($this->search_api_server->getIndexes() as $index) {
                if ($index->status() && !$index->isReadOnly()) {
                  $index->reindex();
                }
              }
            }
            else {
              $this->messenger->addError($this->t('Creating collection %collection failed.', ['%collection' => $connector->getCollectionName()]));
            }
          } catch (SearchApiSolrException $e) {
            $this->logException($e);
            $this->messenger->addError($this->t('Creating collection %collection failed.', ['%collection' => $connector->getCollectionName()]));
          }
        }
      }
      else {
        $this->messenger->addError($this->t('uploading configset %configset failed.', ['%configset' => $configset]));
      }
    } catch (SearchApiSolrException $e) {
      $this->logException($e);
      $this->messenger->addError($this->t('uploading configset %configset failed.', ['%configset' => $configset]));
    }

    $form_state->setRedirect('entity.search_api_server.canonical', ['search_api_server' => $this->search_api_server->id()]);
  }

  protected function getLogger($channel = '') {
    return $this->getSearchApiLogger();
  }

}
