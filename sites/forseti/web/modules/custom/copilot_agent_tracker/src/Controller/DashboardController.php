<?php

namespace Drupal\copilot_agent_tracker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Serialization\Json;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin dashboard for agent/session tracking.
 */
final class DashboardController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly StateInterface $state,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('state'),
    );
  }

  /**
   * Dashboard listing all agents.
   */
  public function dashboard(): array {
    $token = (string) $this->state->get('copilot_agent_tracker.telemetry_token', '');

    $rows = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a', ['agent_id', 'role', 'website', 'module', 'status', 'current_action', 'last_seen'])
      ->orderBy('website', 'ASC')
      ->orderBy('module', 'ASC')
      ->orderBy('role', 'ASC')
      ->orderBy('last_seen', 'DESC')
      ->execute()
      ->fetchAllAssoc('agent_id');

    $table_rows = [];
    foreach ($rows as $agent_id => $row) {
      $table_rows[] = [
        Link::fromTextAndUrl($agent_id, Url::fromRoute('copilot_agent_tracker.agent', ['agent_id' => $agent_id]))->toString(),
        $row->role ?? '',
        $row->website ?? '',
        $row->module ?? '',
        $row->status ?? '',
        $row->current_action ?? '',
        $row->last_seen ? $this->dateFormatter->format((int) $row->last_seen, 'short') : '',
      ];
    }

    return [
      '#type' => 'container',
      'help' => [
        '#markup' => '<p>Tracks high-level agent status updates and work item progress. Do not post raw conversation logs.</p>'
          . ($token ? '<p><strong>Telemetry token</strong> (send as <code>X-Copilot-Agent-Tracker-Token</code>): <code>' . $token . '</code></p>' : ''),
      ],
      'agents' => [
        '#type' => 'table',
        '#header' => ['Agent', 'Role', 'Website', 'Module', 'Status', 'Current action', 'Last seen'],
        '#rows' => $table_rows,
        '#empty' => $this->t('No agent updates yet.'),
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Inbox-style view for Keith/CEO pending decisions.
   */
  public function waitingOnKeith(): array {
    $rows = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a', ['agent_id', 'role', 'website', 'module', 'status', 'current_action', 'last_seen', 'metadata'])
      ->orderBy('website', 'ASC')
      ->orderBy('module', 'ASC')
      ->orderBy('role', 'ASC')
      ->orderBy('last_seen', 'DESC')
      ->execute()
      ->fetchAll();

    $ceo_meta = [];
    $pending_rows = [];

    foreach ($rows as $row) {
      $meta = [];
      if (!empty($row->metadata)) {
        try {
          $meta = Json::decode((string) $row->metadata) ?? [];
        }
        catch (\Throwable) {
          $meta = [];
        }
      }

      if (($row->agent_id ?? '') === 'ceo-copilot') {
        $ceo_meta = is_array($meta) ? $meta : [];
      }

      $inbox_count = (int) ($meta['inbox_count'] ?? 0);
      if ($inbox_count > 0 || in_array((string) ($row->status ?? ''), ['blocked', 'needs-info'], TRUE)) {
        $pending_rows[] = [
          Link::fromTextAndUrl((string) $row->agent_id, Url::fromRoute('copilot_agent_tracker.agent', ['agent_id' => (string) $row->agent_id]))->toString(),
          $row->website ?? '',
          $row->module ?? '',
          $row->role ?? '',
          $row->status ?? '',
          $row->current_action ?? '',
          (string) $inbox_count,
          $row->last_seen ? $this->dateFormatter->format((int) $row->last_seen, 'short') : '',
        ];
      }
    }

    $items = [];
    foreach (($ceo_meta['inbox_items'] ?? []) as $i) {
      $items[] = (string) $i;
    }

    return [
      '#type' => 'container',
      'help' => [
        '#markup' => '<p>This page shows a CEO-style inbox view derived from HQ sync metadata (not raw chat logs).</p>',
      ],
      'ceo' => [
        '#theme' => 'item_list',
        '#title' => $this->t('CEO inbox items (from HQ sync)'),
        '#items' => $items ?: [$this->t('No inbox items detected.')],
      ],
      'pending' => [
        '#type' => 'table',
        '#header' => ['Agent', 'Website', 'Module', 'Role', 'Status', 'Current action', 'Inbox', 'Last seen'],
        '#rows' => $pending_rows,
        '#empty' => $this->t('No pending items.'),
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Agent detail page.
   */
  public function agent(string $agent_id): array {
    $agent = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a')
      ->condition('agent_id', $agent_id)
      ->execute()
      ->fetchAssoc();

    if (!$agent) {
      throw new NotFoundHttpException();
    }

    $events = $this->database->select('copilot_agent_tracker_events', 'e')
      ->fields('e', ['created', 'action', 'status', 'summary', 'session_id', 'work_item_id'])
      ->condition('agent_id', $agent_id)
      ->orderBy('created', 'DESC')
      ->range(0, 50)
      ->execute()
      ->fetchAll();

    $event_rows = [];
    foreach ($events as $e) {
      $event_rows[] = [
        $e->created ? $this->dateFormatter->format((int) $e->created, 'short') : '',
        $e->action ?? '',
        $e->status ?? '',
        $e->summary ?? '',
        $e->session_id ?? '',
        $e->work_item_id ?? '',
      ];
    }

    return [
      '#type' => 'container',
      'summary' => [
        '#markup' => '<h2>' . $this->t('Agent: @id', ['@id' => $agent_id]) . '</h2>',
      ],
      'meta' => [
        '#theme' => 'item_list',
        '#items' => [
          'Role: ' . ($agent['role'] ?? ''),
          'Website: ' . ($agent['website'] ?? ''),
          'Module: ' . ($agent['module'] ?? ''),
          'Status: ' . ($agent['status'] ?? ''),
          'Current action: ' . ($agent['current_action'] ?? ''),
        ],
      ],
      'events' => [
        '#type' => 'table',
        '#header' => ['When', 'Action', 'Status', 'Summary', 'Session', 'Work item'],
        '#rows' => $event_rows,
        '#empty' => $this->t('No events yet.'),
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
