<?php

namespace Drupal\search_api_solr\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Consolidation\AnnotatedCommand\OutputDataInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\search_api\ConsoleException;
use Drupal\search_api_solr\SearchApiSolrException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\SolrCloudConnectorInterface;
use Drupal\search_api_solr\Utility\SolrCommandHelper;
use Drush\Attributes\Argument;
use Drush\Attributes\Bootstrap;
use Drush\Attributes\Command;
use Drush\Attributes\Help;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Defines Drush commands for the Search API Solr.
 */
class SearchApiSolrCommands extends DrushCommands implements StdinAwareInterface, SiteAliasManagerAwareInterface {

  use StdinAwareTrait;
  use SiteAliasManagerAwareTrait;

  /**
   * Constructs a SearchApiSolrCommands object.
   *
   * @param \Drupal\search_api_solr\Utility\SolrCommandHelper $commandHelper
   *   The command helper.
   */
  public function __construct(protected SolrCommandHelper $commandHelper) {
    parent::__construct();
  }

  /**
   * Instantiates a new instance of this class.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   The service container this instance should use.
   *
   * @return static
   *   A new class instance.
   *
   * @throws \Psr\Container\ContainerExceptionInterface
   *   Thrown if some required services are not registered.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('search_api_solr.command_helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(LoggerInterface $logger): void {
    parent::setLogger($logger);
    $this->commandHelper->setLogger($logger);
  }

  /**
   * Re-install Solr Field Types from their yml files.
   *
   * @command search-api-solr:reinstall-fieldtypes
   *
   * @usage drush search-api-solr:reinstall-fieldtypes
   *   Deletes all Solr Field Type and re-installs them from their yml files.
   *
   * @aliases solr-reinstall-ft,sasm-reinstall-ft,search-api-solr-delete-and-reinstall-all-field-types,search-api-solr-multilingual-delete-and-reinstall-all-field-types
   */
  #[Command(name: 'earch-api-solr:reinstall-fieldtypes', aliases: ['solr-reinstall-ft', 'sasm-reinstall-ft', 'search-api-solr-delete-and-reinstall-all-field-types', 'search-api-solr-multilingual-delete-and-reinstall-all-field-types'])]
  #[Help(description: 'Re-install Solr Field Types from their yml files.')]
  #[Usage(name: 'drush search-api-solr:reinstall-fieldtypes', description: 'Deletes all Solr Field Type and re-installs them from their yml files.')]
  public function reinstallFieldtypes(): void {
    $this->commandHelper->reinstallFieldtypesCommand();
    $this->logger()->success('Solr field types re-installed.');
  }

  /**
   * Install missing Solr Field Types from their yml files.
   *
   * @command search-api-solr:install-missing-fieldtypes
   *
   * @usage drush search-api-solr:install-missing-fieldtypes
   *   Install missing Solr Field Types.
   */
  #[Command(name: 'earch-api-solr:install-missing-fieldtypes')]
  #[Help(description: 'Install missing Solr Field Types from their yml files.')]
  #[Usage(name: 'drush search-api-solr:install-missing-fieldtypes', description: 'Install missing Solr Field Types.')]
  public function installMissingFieldtypes(): void {
    search_api_solr_install_missing_field_types();
  }

  /**
   * Gets the config for a Solr search server.
   *
   * @param string $server_id
   *   The ID of the server.
   * @param string $file_name
   *   The file name of the config zip that should be created.
   * @param string $solr_version
   *   The targeted Solr version.
   * @param array $options
   *   The options array.
   *
   * @command search-api-solr:get-server-config
   *
   * @default $options []
   *
   * @usage drush search-api-solr:get-server-config server_id file_name
   *   Get the config files for a solr server and save it as zip file.
   *
   * @aliases solr-gsc,sasm-gsc,search-api-solr-get-server-config,search-api-solr-multilingual-get-server-config
   *
   * @throws \Drupal\search_api\ConsoleException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \ZipStream\Exception\FileNotFoundException
   * @throws \ZipStream\Exception\FileNotReadableException
   * @throws \ZipStream\Exception\OverflowException
   */
  #[Command(name: 'search-api-solr:get-server-config', aliases: ['solr-gsc', 'sasm-gsc', 'search-api-solr-get-server-config', 'search-api-solr-multilingual-get-server-config'])]
  #[Argument(name: 'server_id', description: 'The ID of the server.')]
  #[Argument(name: 'file_name', description: ' The file name of the config zip that should be created.')]
  #[Argument(name: 'solr_version', description: 'The targeted Solr version.')]
  #[Help(description: 'Gets the config for a Solr search server.')]
  #[Usage(name: 'drush search-api-solr:get-server-config server_id file_name', description: 'Get the config files for a solr server and save it as zip file.')]
  public function getServerConfig(string $server_id, ?string $file_name = NULL, ?string $solr_version = NULL): void {
    if ((!isset($options['pipe']) || !$options['pipe']) && ($file_name === NULL)) {
      throw new ConsoleException('Required argument missing ("file_name"), and no --pipe option specified.');
    }
    $this->commandHelper->getServerConfigCommand($server_id, $file_name, $solr_version);
  }

  /**
   * Finalizes one or all enabled search indexes.
   *
   * @param string $indexId
   *   (optional) A search index ID, or NULL to index items for all enabled
   *   indexes.
   * @param array $options
   *   The options array.
   *
   * @command search-api-solr:finalize-index
   *
   * @option force
   *   Force the finalization, even if the index isn't "dirty".
   *   Defaults to FALSE.
   *
   * @default $options []
   *
   * @usage drush search-api-solr:finalize-index
   *   Finalize all enabled indexes.
   * @usage drush search-api-solr:finalize-index node_index
   *   Finalize the index with the ID node_index.
   * @usage drush search-api-solr:finalize-index node_index --force
   *   Index a maximum number of 100 items for the index with the ID node_index.
   *
   * @aliases solr-finalize
   *
   * @throws \Exception
   *   If a batch process could not be created.
   */
  #[Command(name: 'search-api-solr:finalize-index', aliases: ['solr-finalize'])]
  #[Argument(name: 'indexId', description: 'The ID of the search index')]
  #[Option(name: 'force', description: 'Force the finalization, even if the index is not "dirty". Defaults to FALSE.')]
  #[Help(description: 'Finalizes one or all enabled search indexes.')]
  #[Usage(name: 'drush search-api-solr:finalize-index', description: 'Finalize all enabled indexes.')]
  #[Usage(name: 'drush search-api-solr:finalize-index node_index', description: 'Finalize the index with the ID node_index.')]
  #[Usage(name: 'drush search-api-solr:finalize-index node_index --force', description: 'Index a maximum number of 100 items for the index with the ID node_index.')]
  public function finalizeIndex(?string $indexId = NULL, bool $force = FALSE): void {
    $this->commandHelper->finalizeIndexCommand($indexId ? [$indexId] : $indexId, $force);
    $this->logger()->success('Solr %index_id finalized.', ['%index_id' => $indexId]);
  }

  /**
   * Executes a streaming expression from STDIN.
   *
   * @param string $indexId
   *   A search index ID.
   * @param mixed $expression
   *   The streaming expression. Use '-' to read from STDIN.
   *
   * @command search-api-solr:execute-raw-streaming-expression
   *
   * @usage drush search-api-solr:execute-streaming-expression node_index - < streaming_expression.txt
   *  Execute the raw streaming expression in streaming_expression.txt
   *
   * @aliases solr-erse
   *
   * @return string
   *   The JSON encoded raw streaming expression result
   *
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   * @throws \Drupal\search_api\SearchApiException
   */
  #[Command(name: 'search-api-solr:execute-raw-streaming-expression', aliases: ['solr-erse'])]
  #[Argument(name: 'indexId', description: 'The ID of the search index')]
  #[Argument(name: 'expression', description: 'The streaming expression. Use "-" to read from STDIN.')]
  #[Help(description: 'Executes a streaming expression from STDIN.')]
  #[Usage(name: 'drush search-api-solr:execute-streaming-expression node_index - < streaming_expression.txt', description: 'Execute the raw streaming expression in streaming_expression.txt.')]
  public function executeRawStreamingExpression(string $indexId, string $expression): string {
    // Special flag indicating that the value has been passed via STDIN.
    if ($expression === '-') {
      $expression = $this->stdin()->contents();
    }

    if (!$expression) {
      throw new SearchApiSolrException('No streaming expression provided.');
    }

    $indexes = $this->commandHelper->loadIndexes([$indexId]);
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = reset($indexes);

    if (!$index) {
      throw new SearchApiSolrException('Failed to load index.');
    }

    if (!$index->status()) {
      throw new SearchApiSolrException('Index is not enabled.');
    }

    if ($server = $index->getServerInstance()) {
      /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
      $backend = $server->getBackend();

      if (!($backend instanceof SolrBackendInterface) || !($backend->getSolrConnector() instanceof SolrCloudConnectorInterface)) {
        throw new SearchApiSolrException('The index must be located on Solr Cloud to execute streaming expressions.');
      }

      $queryHelper = \Drupal::service('search_api_solr.streaming_expression_query_helper');
      $query = $queryHelper->createQuery($index);
      $queryHelper->setStreamingExpression($query,
        $expression,
        basename(__FILE__) . ':' . __LINE__
      );
      $result = $backend->executeStreamingExpression($query);

      return $result->getBody();
    }

    throw new SearchApiSolrException('Server could not be loaded.');
  }

  /**
   * Indexes items for one or all enabled search indexes.
   *
   * @param string $indexId
   *   (optional) A search index ID, or NULL to index items for all enabled
   *   indexes.
   * @param array $options
   *   (optional) An array of options.
   *
   * @throws \Exception
   *   If a batch process could not be created.
   *
   * @command search-api-solr:index-parallel
   *
   * @option threads
   *   The number of parallel threads. Defaults to 2.
   * @option batch-size
   *   The maximum number of items to index per batch run. Defaults to the "Cron
   *   batch size" setting of the index if omitted or explicitly set to 0. Set
   *   to a negative value to index all items in a single batch (not
   *   recommended).
   */
  #[Command(name: 'search-api-solr:index-parallel')]
  #[Argument(name: 'indexId', description: 'The ID of the search index. All if not provided.')]
  #[Option(name: 'threads', description: 'The number of parallel threads.')]
  #[Option(name: 'batch-size', description: 'The maximum number of items to index per batch run. Defaults to the "Cron batch size" setting of the index if omitted or explicitly set to 0 or -999. Set to -1 to index all items in a single batch (not recommended).')]
  #[Help(description: 'Indexes items for one or all enabled search indexes in parallel.')]
  public function indexParallel(
    ?string $indexId = NULL,
    ?int $threads = 3,
    ?int $batchSize = -999, // @todo Real default is NULL.
  ): void {
    $ids = $this->commandHelper->indexParallelCommand([$indexId], $threads, -999 === $batchSize ? NULL : $batchSize);

    $processes = [];
    $siteAlias = $this->siteAliasManager()->getSelf();
    foreach($ids as $id) {
      $processes[$id] = Drush::drush($siteAlias, 'search-api-solr:process', [$id]);
      $processes[$id]->start();

      while (count($processes) >= $threads) {
        foreach ($processes as $pid => $process) {
          $this->output()->write($process->getIncrementalErrorOutput());
          $this->output()->write($process->getIncrementalOutput());

          if ($process->isTerminated()) {
            unset($processes[$pid]);
          }
        }
        sleep(2);
      }
    }

    while (count($processes)) {
      foreach ($processes as $pid => $process) {
        $this->output()->write($process->getIncrementalErrorOutput());
        $this->output()->write($process->getIncrementalOutput());

        if ($process->isTerminated()) {
          unset($processes[$pid]);
        }
      }
      sleep(2);
    }

    $this->commandHelper->resetEmptyIndexState([$indexId]);
  }

  /**
   * Process operations in the specified batch set with propper exit code.
   *
   * @see \Drush\Commands\core\BatchCommands
   */
  #[Command(name: 'search-api-solr:process')]
  #[Argument(name: 'batch_id', description: 'The batch id that will be processed.')]
  #[Help(hidden: true)]
  #[Bootstrap(level: DrupalBootLevels::FULL)]
  public function process($batch_id, $options = ['format' => 'json']): OutputDataInterface {
    $return = drush_batch_command($batch_id);
    $exit_code = 1;
    if (isset($return['drush_batch_process_finished']) && $return['drush_batch_process_finished'] === TRUE) {
      $exit_code = 0;
    }

    return CommandResult::exitCode($exit_code);
  }

  /**
   * Reset empty index state to FALSE.
   *
   * Important if drush search-api-solr:index-parallel crashed or has been
   * interrupted. That might cause to block deletes on an index for one hour
   * unless you run this command.
   *
   * @param string $indexId
   *   (optional) A search index ID, or NULL to index items for all enabled
   *   indexes.
   * @param array $options
   *   (optional) An array of options.
   *
   * @command search-api-solr:reset-empty-index-state
   *
   * @default $options []
   */
  public function resetEmptyIndexState($indexId = NULL, array $options = []) {
    $this->commandHelper->resetEmptyIndexState([$indexId]);
  }

}
