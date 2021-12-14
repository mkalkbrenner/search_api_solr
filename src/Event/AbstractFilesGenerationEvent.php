<?php

namespace Drupal\search_api_solr\Event;

use Drupal\Component\EventDispatcher\Event;

abstract class AbstractFilesGenerationEvent extends Event {

  /**
   * The files.
   *
   * @var array
   */
  protected $files;

  /**
   * @var string
   */
  protected $luceneMatchVersion;

  /**
   * @var string
   */
  protected $serverId;

  /**
   * Constructs a new class instance.
   *
   * @param array $files
   *   Reference to files array.
   * @param string $lucene_match_version
   * @param string $server_id
   */
  public function __construct(array &$files, string $lucene_match_version, string $server_id) {
    $this->files = &$files;
    $this->luceneMatchVersion = $lucene_match_version;
    $this->serverId = $server_id;
  }

  /**
   * Retrieves the files array.
   *
   * @return array
   *   The files array.
   */
  public function getFiles(): array {
    return $this->files;
  }

  /**
   * Set the files array.
   *
   * @param array $files
   */
  public function setConfigFiles(array $files) {
    $this->files = $files;
  }

  /**
   * Retrieves the lucene match version.
   */
  public function getLuceneMatchVersion(): string {
    return $this->luceneMatchVersion;
  }

  /**
   * Retrieves the server ID.
   */
  public function getServerId(): string {
    return $this->serverId;
  }
}
