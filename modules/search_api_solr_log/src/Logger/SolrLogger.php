<?php

namespace Drupal\search_api_solr_log\Logger;

use Drupal\Component\Utility\Xss;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_solr\SolrBackendInterface;
use Drupal\search_api_solr\SolrConnectorInterface;
use Psr\Log\LoggerInterface;
use Solarium\Core\Query\Helper;

/**
 * Logs events in Search API.
 */
class SolrLogger implements LoggerInterface {
  use RfcLoggerTrait;
  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * Array of Solr field names, keyed by Drupal field names.
   *
   * @var string[]
   */
  protected static $logFieldMappings = [
    'type' => 'ss_type',
    'uid' => 'its_uid',
    'message' => 'tus_message',
    'variables' => 'zs_variables',
    'message_en' => 'ts_X3b_en_message',
    'severity' => 'its_severity',
    'link' => 'ss_link',
    'location' => 'ss_location',
    'referer' => 'ss_referer',
    'hostname' => 'ss_hostname',
    'timestamp' => 'dt_timestamp',
  ];

  /**
   * Internal flag of successful connection to Solr.
   *
   * @var bool
   */
  protected bool $serviceFunctional = TRUE;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LogMessageParserInterface $parser,
    protected Helper $helper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    if (!$this->serviceFunctional) {
      return;
    }
    // Remove backtrace and exception since they may contain
    // an unserializable variable.
    unset($context['exception']);
    try {
      $connector = self::getConnector();
    } catch (SearchApiException $e) {
      return;
    }
    if (!$connector) {
      return;
    }
    // Convert PSR3-style messages to \Drupal\Component\Render\FormattableMarkup
    // style, so they can be translated too in runtime.
    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
    $channel = mb_substr($context['channel'], 0, 64);
    $values = [
      'id' => 'search_api_solr_log:' . $channel . ':' . uniqid(),
      // This helps to clear/filter the documents.
      'index_id' => 'search_api_solr_log',
      static::$logFieldMappings['uid'] => $context['uid'],
      static::$logFieldMappings['type'] => $channel,
      static::$logFieldMappings['message'] => (string) $message,
      static::$logFieldMappings['variables'] => json_encode($message_placeholders, JSON_PRETTY_PRINT),
      static::$logFieldMappings['message_en'] => $this->t(Xss::filterAdmin((string) $message), (array) $message_placeholders)->render(),
      static::$logFieldMappings['severity'] => $level,
      static::$logFieldMappings['link'] => $context['link'],
      static::$logFieldMappings['location'] => $context['request_uri'],
      static::$logFieldMappings['referer'] => $context['referer'],
      static::$logFieldMappings['hostname'] => mb_substr($context['ip'], 0, 128),
      static::$logFieldMappings['timestamp'] => $this->helper->formatDate($context['timestamp']),
    ];
    $query = $connector->getUpdateQuery();
    $query->addDocument($query->createDocument($values))->addCommit();
    try {
      $connector->update($query);
    }
    catch (\Exception $e) {
      // Prevent infinite loop trying to log the message.
      $this->serviceFunctional = FALSE;
    }
  }

  /**
   * Get solr connector.
   *
   * @return \Drupal\search_api_solr\SolrConnectorInterface|null
   *   Solr connector.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public static function getConnector() : ?SolrConnectorInterface {
    try {
      $index = Index::load('search_api_solr_log');
      if ($index && $index->isActive() && $index->hasValidServer()) {
        if ($server = $index->getServerInstance()) {
          $backend = $server->getBackend();
          if ($backend instanceof SolrBackendInterface) {
            return $backend->getSolrConnector();
          }
        }
      }
    }
    catch (\Throwable $e) {
      return NULL;
    }

    return NULL;
  }

  /**
   * Delete old log events.
   *
   * @param int|null $days Days to keep log entries.
   *
   * @throws \DateMalformedStringException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_solr\SearchApiSolrException
   */
  public static function delete(?int $days = 0): void {
    if ($connector = self::getConnector()) {
      $query = $connector->getUpdateQuery();

      $date = new \DateTime();
      $date->modify('-' . $days . ' days');
      $solrDate = $date->format('Y-m-d\TH:i:s\Z');
      $deleteCondition = sprintf("timestamp:[* TO %s] AND index_id:search_api_solr_log", $solrDate);
      $query->addDeleteQuery($deleteCondition)->addCommit();

      $connector->update($query);
    }
  }
}
