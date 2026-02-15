<?php

namespace Drupal\dungeoncrawler_tester\Commands;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drush\Commands\DrushCommands;

class TestingQueueCommands extends DrushCommands {

  /**
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected QueueWorkerManagerInterface $queueManager;

  /**
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  public function __construct(QueueFactory $queueFactory, QueueWorkerManagerInterface $queueManager, LockBackendInterface $lock, LoggerChannelFactoryInterface $loggerFactory) {
    parent::__construct();
    $this->queueFactory = $queueFactory;
    $this->queueManager = $queueManager;
    $this->lock = $lock;
    $this->logger = $loggerFactory->get('dungeoncrawler_tester');
  }

  /**
   * Run queued testing jobs.
   *
   * @command dungeoncrawler_tester:run-queue
   * @aliases dctr:queue
   * @option limit Number of items to process (default 10)
   * @usage drush dungeoncrawler_tester:run-queue --limit=5
   */
  public function runQueue(array $options = ['limit' => 10]): void {
    $limit = (int) ($options['limit'] ?? 10);
    if ($limit <= 0) {
      $limit = 1;
    }

    if (!$this->lock->acquire('dungeoncrawler_tester.queue_runner', 30)) {
      $this->logger->warning('Queue runner already active; skipping.');
      $this->output()->writeln('Queue runner already active; skipping.');
      return;
    }

    $queue = $this->queueFactory->get('dungeoncrawler_tester_runs');
    $worker = $this->queueManager->createInstance('dungeoncrawler_tester_runs');

    $processed = 0;
    try {
      while ($processed < $limit && ($item = $queue->claimItem())) {
        try {
          $worker->processItem($item->data);
          $queue->deleteItem($item);
          $processed++;
        }
        catch (\Throwable $e) {
          $this->logger->error('Queue item failed: @msg', ['@msg' => $e->getMessage()]);
          $queue->releaseItem($item);
          break;
        }
      }
      $this->logger->notice('Queue runner processed @count item(s).', ['@count' => $processed]);
      $this->output()->writeln(sprintf('Processed %d item(s).', $processed));
    }
    finally {
      $this->lock->release('dungeoncrawler_tester.queue_runner');
    }
  }

}
