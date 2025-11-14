<?php

namespace Drupal\search_api_solr\Utility;

use Drupal\Core\Batch\BatchStorageInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\IndexingBatchHelper;
use Drupal\search_api_solr\Plugin\search_api\tracker\IndexParallel;

/**
 * Provides helper methods for indexing items using Drupal's Batch API.
 */
class IndexParallelBatchHelper extends IndexingBatchHelper {

  protected array $batchIds = [];

  /**
   * Creates an indexing batch for a given search index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index for which items should be indexed.
   * @param int|null $batch_size
   *   (optional) Number of items to index per batch. Defaults to the cron limit
   *   set for the index.
   * @param int $limit
   *   (optional) Maximum number of items to index. Defaults to indexing all
   *   remaining items.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if the batch could not be created.
   */
  public function createBatch(
    IndexInterface $index,
    ?int $batch_size = NULL,
    int $limit = -1,
    int $time_limit = -1,
  ): void {
    // Make sure that the indexing lock is available.
    if (!$this->lockBackend->lockMayBeAvailable($index->getLockId())) {
      throw new SearchApiException('Items are being indexed in a different process.');
    }

    $ids = [];

    // Check if indexing items is allowed.
    if (($batch_size  ?? 0) > 0 && $index->status() && !$index->isReadOnly()) {
      /** @var BatchStorageInterface $batchStorage */
      $batchStorage = \Drupal::service('batch.storage');

      for ($thread = 1; $thread <= $limit; $thread++) {
        // Define the search index batch definition.
        $batch_definition = [
          'operations' => [
            [
              [$this, 'process'],
              [
                $index,
                $batch_size,
                $thread,
                -1,
              ]
            ],
          ],
          'finished' => [$this, 'finish'],
          'progress_message' => $this->t('Completed about @percentage% of the indexing operation (@current of @total).'),
        ];

        batch_set($batch_definition);

        $batch = &batch_get();

        if (isset($batch)) {
          $process_info = [
            'current_set' => 0,
          ];
          $batch += $process_info;

          $ids[] = $batch['id'] = $batchStorage->getId();

          $batch['progressive'] = TRUE;

          // Move operations to a job queue. Non-progressive batches will use a
          // memory-based queue.
          foreach ($batch['sets'] as $key => $batch_set) {
            _batch_populate_queue($batch, $key);
          }

          $batchStorage->create($batch);
          $batch = [];
        }
      }
    }
    else {
      $index_label = $index->label();
      throw new SearchApiException("Failed to create a batch with batch size '$batch_size' and threads '$limit' for index '$index_label'.");
    }

    $this->batchIds = array_reverse($ids);
  }

  /**
   * Get batch IDs.
   *
   * @return int[]
   *   The batch IDs.
   */
  public function getBatchIds(): array {
    return $this->batchIds;
  }

  /**
   * Processes an index batch operation.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index on which items should be indexed.
   * @param int $batch_size
   *   The maximum number of items to index per batch pass.
   * @param int $limit
   *   The maximum number of items to index in total, or -1 to index all items.
   * @param int $time_limit
   *   (optional) The maximum number of seconds allowed to run indexing, or -1
   *   to not have any limit. Defaults to -1 (no limit).
   * @param array|\ArrayAccess $context
   *   The context of the current batch, as defined in the @link batch Batch
   *   operations @endlink documentation.
   */
  public function process(
    IndexInterface $index,
    int $batch_size,
    int $limit,
    int $time_limit,
    array|\ArrayAccess &$context,
  ): void {
    // Check if the sandbox should be initialized.
    if (!isset($context['sandbox']['limit'])) {
      $context['sandbox']['limit'] = -1;
      $context['sandbox']['thread'] = $limit;
      $context['sandbox']['batch_size'] = $batch_size;
    }

    if ($index->hasValidTracker() && !$index->isReadOnly() && $index->getTrackerId() === 'index_parallel') {
      /** @var \Drupal\search_api_solr\Plugin\search_api\tracker\IndexParallel $tracker */
      $tracker = $index->getTrackerInstance();
      $tracker->setThread($context['sandbox']['thread']);
      if ($context['sandbox']['thread'] > 1) {
        $tracker->setOffset($context['sandbox']['batch_size'] * IndexParallel::SAFETY_DISTANCE_FACTOR * ($context['sandbox']['thread'] - 1));
      }
    }

    parent::process($index, $batch_size, -1, $time_limit, $context);
  }

  /**
   * Finishes an index batch.
   */
  public function finish($success, $results, $operations): void {
    // Check if the batch job was successful.
    if ($success) {
      // Display the number of items indexed.
      if (!empty($results['indexed'])) {
        // Build the indexed message.
        $indexed_message = $this->formatPlural($results['indexed'], 'Thread successfully indexed 1 item.', 'Thread successfully indexed @count items.');
        // Notify user about indexed items.
        $this->messenger->addStatus($indexed_message);
      }
      else {
        // Notify user about failure to index items.
        $this->messenger->addError($this->t("Couldn't index items. Check the logs for details."));
      }
    }
    else {
      // Notify user about batch job failure.
      $this->messenger->addError($this->t('An error occurred while trying to index items. Check the logs for details.'));
    }
  }

}
