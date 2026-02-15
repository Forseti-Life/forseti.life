<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Queue management UI for the testing module.
 */
class QueueManagementController extends ControllerBase {

  /**
   * Single queue definition for tester runs.
   */
  private const QUEUE_DEFINITIONS = [
    'dungeoncrawler_tester_runs' => [
      'name' => 'Testing Runs',
      'description' => 'Background execution of dashboard run jobs.',
      'icon' => '🧪',
    ],
  ];

  public function __construct(
    private QueueFactory $queueFactory,
    private QueueWorkerManagerInterface $queueManager,
    private StateInterface $state,
    private Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('state'),
      $container->get('database'),
    );
  }

  /**
   * Render the queue management page.
   */
  public function queueManagement(): array {
    $queue_items = $this->loadQueueItems();
    $queue_status = $this->getQueueStatus();

    return [
      '#theme' => 'dungeoncrawler_tester_queue_management',
      '#queue_items' => $queue_items,
      '#queue_status' => $queue_status,
      '#attached' => [
        'library' => [
          'dungeoncrawler_tester/queue-management',
        ],
        'drupalSettings' => [
          'dungeoncrawlerTester' => [
            'csrfToken' => \Drupal::csrfToken()->get('rest'),
            'routes' => [
              'run' => Url::fromRoute('dungeoncrawler_tester.queue_run')->toString(),
              'status' => Url::fromRoute('dungeoncrawler_tester.queue_status')->toString(),
              'logs' => Url::fromRoute('dungeoncrawler_tester.queue_logs')->toString(),
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * AJAX: run the tester queue.
   */
  public function runQueueAjax(Request $request): JsonResponse {
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
    }

    $limit = (int) ($request->request->get('limit') ?? 5);
    if ($limit < 1) {
      $limit = 1;
    }

    $queue_id = 'dungeoncrawler_tester_runs';
    try {
      $processed = $this->processQueue($queue_id, $limit, 60);
      $remaining = $this->queueFactory->get($queue_id)->numberOfItems();
      return new JsonResponse([
        'success' => TRUE,
        'processed' => $processed,
        'remaining' => $remaining,
        'message' => "Processed {$processed} item(s); {$remaining} remaining",
      ]);
    }
    catch (\Throwable $e) {
      $this->getLogger('dungeoncrawler_tester')->error('Queue run failed: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Error: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * AJAX: queue status.
   */
  public function getQueueStatusAjax(): JsonResponse {
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
    }

    return new JsonResponse([
      'success' => TRUE,
      'queues' => $this->getQueueStatus(),
    ]);
  }

  /**
   * AJAX: queue logs (recent watchdog entries).
   */
  public function getQueueLogsAjax(): JsonResponse {
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
    }

    $query = $this->database->select('watchdog', 'w')
      ->fields('w', ['wid', 'timestamp', 'type', 'severity', 'message', 'variables'])
      ->condition('type', 'dungeoncrawler_tester')
      ->orderBy('timestamp', 'DESC')
      ->range(0, 20);

    $rows = $query->execute()->fetchAll();
    $logs = [];
    foreach ($rows as $row) {
      $vars = @unserialize($row->variables) ?: [];
      $message = strtr($row->message, $vars);
      $logs[] = [
        'timestamp' => $row->timestamp,
        'message' => $message,
        'severity' => (int) $row->severity,
      ];
    }

    return new JsonResponse([
      'success' => TRUE,
      'logs' => $logs,
    ]);
  }

  /**
   * Process queue items with basic timeout.
   */
  private function processQueue(string $queue_id, int $max_items, int $timeout): int {
    $start = microtime(TRUE);
    $queue = $this->queueFactory->get($queue_id);
    $worker = $this->queueManager->createInstance($queue_id);
    $processed = 0;

    while ($processed < $max_items && ($item = $queue->claimItem())) {
      $elapsed = microtime(TRUE) - $start;
      if ($elapsed > $timeout) {
        $this->getLogger('dungeoncrawler_tester')->warning('Queue processing timed out after @s seconds', ['@s' => round($elapsed, 2)]);
        break;
      }
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
      }
      catch (\Throwable $e) {
        $this->getLogger('dungeoncrawler_tester')->error('Queue item failed: @msg', ['@msg' => $e->getMessage()]);
        $queue->releaseItem($item);
        break;
      }
    }

    return $processed;
  }

  /**
   * Load active queue items for display.
   */
  private function loadQueueItems(): array {
    $connection = $this->database;
    $queue_items = [];

    $query = $connection->select('queue', 'q')
      ->fields('q', ['item_id', 'data', 'expire', 'created'])
      ->condition('name', 'dungeoncrawler_tester_runs');
    $results = $query->execute()->fetchAll();

    foreach ($results as $row) {
      $data = unserialize($row->data);
      $preview = $this->getQueueItemPreview($data);
      $queue_items[] = [
        'item_id' => $row->item_id,
        'queue_name' => 'dungeoncrawler_tester_runs',
        'queue_label' => self::QUEUE_DEFINITIONS['dungeoncrawler_tester_runs']['name'],
        'created' => $row->created,
        'expire' => $row->expire,
        'data' => $data,
        'data_preview' => $preview,
      ];
    }

    usort($queue_items, fn($a, $b) => $b['created'] <=> $a['created']);
    return $queue_items;
  }

  private function getQueueItemPreview($data): array {
    $preview = [];
    if (is_array($data)) {
      if (!empty($data['stage_id'])) {
        $preview['stage'] = $data['stage_id'];
      }
      if (!empty($data['display'])) {
        $preview['command'] = $data['display'];
      }
      if (!empty($data['job_id'])) {
        $preview['job_id'] = $data['job_id'];
      }
    }
    return $preview;
  }

  /**
   * Build queue status for UI.
   */
  private function getQueueStatus(): array {
    $status = [];
    foreach (self::QUEUE_DEFINITIONS as $queue_id => $info) {
      $queue = $this->queueFactory->get($queue_id);
      $status[$queue_id] = [
        'id' => $queue_id,
        'name' => $info['name'],
        'description' => $info['description'],
        'icon' => $info['icon'],
        'items' => $queue->numberOfItems(),
      ];
    }
    return $status;
  }

}
