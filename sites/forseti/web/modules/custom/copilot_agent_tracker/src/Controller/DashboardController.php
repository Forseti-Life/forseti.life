<?php

namespace Drupal\copilot_agent_tracker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Serialization\Json;
use Drupal\copilot_agent_tracker\Form\InboxReplyForm;
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

    $messages = [];
    foreach (($ceo_meta['inbox_messages'] ?? []) as $m) {
      if (!is_array($m)) {
        continue;
      }
      $item_id = (string) ($m['item_id'] ?? '');
      if ($item_id === '') {
        continue;
      }
      $messages[] = $m;
    }

    $message_rows = [];
    foreach ($messages as $m) {
      $item_id = (string) ($m['item_id'] ?? '');
      $from = (string) ($m['from_agent'] ?? '');
      $subject = (string) ($m['subject'] ?? $item_id);
      $body = (string) ($m['body'] ?? '');
      $website = (string) ($m['website'] ?? '');
      $module = (string) ($m['module'] ?? '');
      $role = (string) ($m['role'] ?? '');
      $decision = (string) ($m['decision_needed'] ?? '');
      $recommendation = (string) ($m['recommendation'] ?? '');
      $preview = mb_substr(trim($body), 0, 160);
      $message_rows[] = [
        Link::fromTextAndUrl($subject, Url::fromRoute('copilot_agent_tracker.waiting_on_keith_message', ['item_id' => $item_id]))->toString(),
        $from,
        ($website ?: '-') . ' / ' . ($module ?: '-'),
        $role ?: '-',
        mb_substr(trim($decision), 0, 80),
        mb_substr(trim($recommendation), 0, 80),
        $preview,
      ];
    }

    return [
      '#type' => 'container',
      'help' => [
        '#markup' => '<p>This page shows a CEO-style inbox view derived from HQ sync metadata (not raw chat logs).</p>',
      ],
      'messages' => [
        '#type' => 'table',
        '#header' => ['Subject', 'From', 'Product', 'Role', 'Decision needed', 'Recommendation', 'Preview'],
        '#rows' => $message_rows,
        '#empty' => $this->t('No inbox items detected.'),
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
   * Message detail view with reply form.
   */
  public function waitingOnKeithMessage(string $item_id): array {
    $row = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a', ['metadata'])
      ->condition('agent_id', 'ceo-copilot')
      ->execute()
      ->fetchAssoc();

    $meta = [];
    if (!empty($row['metadata'])) {
      try {
        $meta = Json::decode((string) $row['metadata']) ?? [];
      }
      catch (\Throwable) {
        $meta = [];
      }
    }

    $message = NULL;
    foreach (($meta['inbox_messages'] ?? []) as $m) {
      if (is_array($m) && (string) ($m['item_id'] ?? '') === $item_id) {
        $message = $m;
        break;
      }
    }
    if (!$message) {
      throw new NotFoundHttpException();
    }

    $from_agent = (string) ($message['from_agent'] ?? '');
    $subject = (string) ($message['subject'] ?? $item_id);
    $body = (string) ($message['body'] ?? '');
    $website = (string) ($message['website'] ?? '');
    $module = (string) ($message['module'] ?? '');
    $role = (string) ($message['role'] ?? '');
    $decision = (string) ($message['decision_needed'] ?? '');
    $recommendation = (string) ($message['recommendation'] ?? '');

    return [
      '#type' => 'container',
      'header' => [
        '#markup' => '<h2>' . $this->t('Message: @subject', ['@subject' => $subject]) . '</h2>'
          . '<p><strong>' . $this->t('From') . ':</strong> ' . $this->t('@from', ['@from' => $from_agent ?: '-']) . '</p>'
          . '<p><strong>' . $this->t('Product') . ':</strong> ' . $this->t('@p', ['@p' => ($website ?: '-') . ' / ' . ($module ?: '-')]) . '</p>'
          . '<p><strong>' . $this->t('Role') . ':</strong> ' . $this->t('@r', ['@r' => $role ?: '-']) . '</p>',
      ],
      'decision' => [
        '#type' => 'details',
        '#title' => $this->t('Decision needed'),
        '#open' => TRUE,
        'content' => [
          '#type' => 'textarea',
          '#title' => $this->t('Decision'),
          '#value' => $decision,
          '#rows' => 6,
          '#attributes' => ['readonly' => 'readonly'],
        ],
      ],
      'recommendation' => [
        '#type' => 'details',
        '#title' => $this->t('Recommendation'),
        '#open' => TRUE,
        'content' => [
          '#type' => 'textarea',
          '#title' => $this->t('Recommendation'),
          '#value' => $recommendation,
          '#rows' => 6,
          '#attributes' => ['readonly' => 'readonly'],
        ],
      ],
      'body' => [
        '#type' => 'details',
        '#title' => $this->t('Message body'),
        '#open' => TRUE,
        'content' => [
          '#type' => 'textarea',
          '#title' => $this->t('Ask'),
          '#value' => $body,
          '#rows' => 18,
          '#attributes' => ['readonly' => 'readonly'],
        ],
      ],
      'reply' => $this->formBuilder()->getForm(InboxReplyForm::class, $item_id, $from_agent),
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
