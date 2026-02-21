<?php

namespace Drupal\copilot_agent_tracker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Render\Markup;
use Drupal\copilot_agent_tracker\Form\AgentDashboardFilterForm;
use Drupal\copilot_agent_tracker\Form\ComposeAgentMessageForm;
use Drupal\copilot_agent_tracker\Form\InboxReplyForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Admin dashboard for agent/session tracking.
 */
final class DashboardController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly StateInterface $state,
    private readonly FormBuilderInterface $dashboardFormBuilder,
    private readonly RequestStack $dashboardRequestStack,
    private readonly CsrfTokenGenerator $csrfToken,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('state'),
      $container->get('form_builder'),
      $container->get('request_stack'),
      $container->get('csrf_token'),
    );
  }

  /**
   * Dashboard listing all agents.
   */
  public function dashboard(): array {
    $token = (string) $this->state->get('copilot_agent_tracker.telemetry_token', '');

    $request = $this->dashboardRequestStack->getCurrentRequest();
    $selected = [
      'product' => (string) ($request?->query->get('product') ?? ''),
      'status' => (string) ($request?->query->get('status') ?? ''),
      'role' => (string) ($request?->query->get('role') ?? ''),
    ];

    $rows = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a', ['agent_id', 'role', 'website', 'module', 'status', 'current_action', 'last_seen'])
      ->orderBy('website', 'ASC')
      ->orderBy('module', 'ASC')
      ->orderBy('role', 'ASC')
      ->orderBy('last_seen', 'DESC')
      ->execute()
      ->fetchAllAssoc('agent_id');

    $products = [];
    $statuses = [];
    $roles = [];
    foreach ($rows as $row) {
      $website = (string) ($row->website ?? '');
      $module = (string) ($row->module ?? '');
      $product_key = $website . '::' . $module;
      $products[$product_key] = ($website ?: '-') . ' / ' . ($module ?: '-');

      $status = trim((string) ($row->status ?? ''));
      if ($status !== '') {
        $statuses[$status] = $status;
      }

      $role = trim((string) ($row->role ?? ''));
      if ($role !== '') {
        $roles[$role] = $role;
      }
    }
    asort($products);
    ksort($statuses);
    ksort($roles);

    $filter_form = $this->dashboardFormBuilder->getForm(AgentDashboardFilterForm::class, [
      'products' => $products,
      'statuses' => $statuses,
      'roles' => $roles,
    ], $selected);

    $table_rows = [];
    foreach ($rows as $agent_id => $row) {
      $website = (string) ($row->website ?? '');
      $module = (string) ($row->module ?? '');
      $status = (string) ($row->status ?? '');
      $role = (string) ($row->role ?? '');

      if ($selected['product'] !== '' && ($website . '::' . $module) !== $selected['product']) {
        continue;
      }
      if ($selected['status'] !== '' && $status !== $selected['status']) {
        continue;
      }
      if ($selected['role'] !== '' && $role !== $selected['role']) {
        continue;
      }

      $table_rows[] = [
        Link::fromTextAndUrl($agent_id, Url::fromRoute('copilot_agent_tracker.agent', ['agent_id' => $agent_id]))->toString(),
        $role,
        $website,
        $module,
        $status,
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
      'filters' => $filter_form,
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
    $self_agent_prefix = 'ceo-copilot';
    $resolved = $this->database->select('copilot_agent_tracker_inbox_resolutions', 'r')
      ->fields('r', ['item_id'])
      ->condition('resolved', 1)
      ->execute()
      ->fetchCol();
    $resolved = array_fill_keys($resolved ?: [], TRUE);

    $rows = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a', ['agent_id', 'role', 'website', 'module', 'status', 'current_action', 'last_seen', 'metadata'])
      ->orderBy('website', 'ASC')
      ->orderBy('module', 'ASC')
      ->orderBy('role', 'ASC')
      ->orderBy('last_seen', 'DESC')
      ->execute()
      ->fetchAll();

    $ceo_meta = [];
    $ceo_last_seen = 0;
    $pending_rows = [];
    $agent_meta = [];

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

      $agent_id_for_meta = (string) ($row->agent_id ?? '');
      if ($agent_id_for_meta === $self_agent_prefix || str_starts_with($agent_id_for_meta, $self_agent_prefix . '-')) {
        $seen = (int) ($row->last_seen ?? 0);
        if ($seen >= $ceo_last_seen) {
          $ceo_last_seen = $seen;
          $ceo_meta = is_array($meta) ? $meta : [];
        }
      }
      $agent_meta[$agent_id_for_meta] = is_array($meta) ? $meta : [];

      $agent_id = trim((string) ($row->agent_id ?? ''));
      if ($agent_id === '') {
        continue;
      }
      // Prevent showing the CEO inbox / internal CEO sub-sessions on the waiting-on-keith agent list.
      if ($agent_id === $self_agent_prefix || str_starts_with($agent_id, $self_agent_prefix . '-')) {
        continue;
      }

      $inbox_count = (int) ($meta['inbox_count'] ?? 0);
      $pending_rows[] = [
        Link::fromTextAndUrl($agent_id, Url::fromRoute('copilot_agent_tracker.agent', ['agent_id' => $agent_id]))->toString(),
        $row->website ?? '',
        $row->module ?? '',
        $row->role ?? '',
        $row->status ?? '',
        $row->current_action ?? '',
        (string) $inbox_count,
        $row->last_seen ? $this->dateFormatter->format((int) $row->last_seen, 'short') : '',
      ];
    }

    // Compose dropdown includes ALL agents, including CEO threads.
    // CEO threads are ordered first for convenience.
    $agent_options = [];
    $ceo_ids = [];
    foreach ($rows as $row) {
      $agent_id = trim((string) ($row->agent_id ?? ''));
      if ($agent_id === '' || !str_starts_with($agent_id, $self_agent_prefix)) {
        continue;
      }
      $ceo_ids[] = $agent_id;
    }
    sort($ceo_ids);
    // Force ceo-copilot first if present.
    if (in_array($self_agent_prefix, $ceo_ids, TRUE)) {
      $ceo_ids = array_values(array_unique(array_merge([$self_agent_prefix], array_diff($ceo_ids, [$self_agent_prefix]))));
    }

    $all_ids = [];
    foreach ($rows as $row) {
      $agent_id = trim((string) ($row->agent_id ?? ''));
      if ($agent_id !== '') {
        $all_ids[] = $agent_id;
      }
    }
    $all_ids = array_values(array_unique($all_ids));
    sort($all_ids);
    $ordered_ids = array_values(array_unique(array_merge($ceo_ids, $all_ids)));

    $by_id = [];
    foreach ($rows as $row) {
      $agent_id = trim((string) ($row->agent_id ?? ''));
      if ($agent_id !== '') {
        $by_id[$agent_id] = $row;
      }
    }

    foreach ($ordered_ids as $agent_id) {
      $row = $by_id[$agent_id] ?? NULL;
      $website = trim((string) ($row?->website ?? ''));
      $module = trim((string) ($row?->module ?? ''));
      $role = trim((string) ($row?->role ?? ''));
      $label = $agent_id;
      if ($website !== '' || $module !== '' || $role !== '') {
        $label .= ' (' . ($website ?: '-') . '/' . ($module ?: '-') . ($role ? (' - ' . $role) : '') . ')';
      }
      $agent_options[$agent_id] = $label;
    }

    $sent = $this->database->select('copilot_agent_tracker_replies', 'r')
      ->fields('r', ['id', 'to_agent_id', 'in_reply_to', 'message', 'created', 'consumed', 'consumed_at', 'hq_item_id'])
      ->condition('dismissed', 0)
      ->orderBy('created', 'DESC')
      ->range(0, 50)
      ->execute()
      ->fetchAll();

    $messages = [];
    foreach (($ceo_meta['inbox_messages'] ?? []) as $m) {
      if (!is_array($m)) {
        continue;
      }
      $item_id = (string) ($m['item_id'] ?? '');
      if ($item_id === '') {
        continue;
      }
      if (!empty($resolved[$item_id])) {
        continue;
      }
      $messages[] = $m;
    }

    $ceo_by_agent = [];
    foreach ($messages as $m) {
      $from = trim((string) ($m['from_agent'] ?? ''));
      if ($from !== '') {
        $ceo_by_agent[$from][] = $m;
      }
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

      $subject_link = Link::fromTextAndUrl($subject, Url::fromRoute('copilot_agent_tracker.waiting_on_keith_message', ['item_id' => $item_id]))->toString();
      $approve_link = '';
      if ($from !== '' && strlen($from) <= 128) {
        $token = $this->csrfToken->get('approve-inbox:' . $item_id);
        $approve_link = ' ' . Link::fromTextAndUrl(
          $this->t('Approve'),
          Url::fromRoute('copilot_agent_tracker.waiting_on_keith_approve', ['item_id' => $item_id], ['query' => ['token' => $token]])
        )->toString();
      }

      $message_rows[] = [
        Markup::create($subject_link . $approve_link),
        $from,
        ($website ?: '-') . ' / ' . ($module ?: '-'),
        $role ?: '-',
        mb_substr(trim($decision), 0, 80),
        mb_substr(trim($recommendation), 0, 80),
        $preview,
      ];
    }

    $sent_thread_items = [];
    foreach ($sent as $s) {
      $to_agent_id = (string) ($s->to_agent_id ?? '');
      $created = (int) ($s->created ?? 0);
      $title = ($created ? $this->dateFormatter->format($created, 'short') : '-') . ' -> ' . $to_agent_id;
      $dismiss_token = $this->csrfToken->get('dismiss-sent:' . (int) $s->id);
      $dismiss_link = Link::fromTextAndUrl(
        $this->t('Dismiss'),
        Url::fromRoute('copilot_agent_tracker.dismiss_sent_message', ['reply_id' => (int) $s->id], ['query' => ['token' => $dismiss_token]])
      )->toString();

      $hq_item_id = trim((string) ($s->hq_item_id ?? ''));
      $state = 'Queued';
      if (!empty($s->consumed)) {
        $state = 'Delivered';
        $items = $agent_meta[$to_agent_id]['inbox_items'] ?? [];
        if ($hq_item_id !== '' && is_array($items) && in_array($hq_item_id, $items, TRUE)) {
          $state = 'Pending (in agent inbox)';
        }
      }

      $sub_links = [];
      $sent_ymd = $created ? gmdate('Ymd', $created) : '';
      foreach (($ceo_by_agent[$to_agent_id] ?? []) as $m) {
        $item_id = (string) ($m['item_id'] ?? '');
        if ($item_id === '') {
          continue;
        }
        if ($sent_ymd !== '' && strcmp(substr($item_id, 0, 8), $sent_ymd) < 0) {
          continue;
        }
        $sub_links[] = Link::fromTextAndUrl($item_id, Url::fromRoute('copilot_agent_tracker.waiting_on_keith_message', ['item_id' => $item_id]))->toString();
      }

      $sent_thread_items[] = [
        '#type' => 'details',
        '#title' => $title,
        '#open' => FALSE,
        'meta' => [
          '#markup' => '<p><strong>Status:</strong> ' . $this->t('@s', ['@s' => $state]) . ' &nbsp; ' . $dismiss_link . '<br>'
            . '<strong>HQ item:</strong> ' . $this->t('@h', ['@h' => ($hq_item_id ?: '-')]) . '</p>',
        ],
        'message' => [
          '#type' => 'item',
          '#title' => $this->t('Message'),
          '#markup' => '<pre style="white-space:pre-wrap;max-height:240px;overflow:auto;">' . htmlspecialchars((string) ($s->message ?? '')) . '</pre>',
        ],
        'sub' => [
          '#type' => 'item',
          '#title' => $this->t('Sub-items'),
          '#markup' => $sub_links ? ('<ul><li>' . implode('</li><li>', $sub_links) . '</li></ul>') : '<em>None detected.</em>',
        ],
      ];
    }

    return [
      '#type' => 'container',
      'help' => [
        '#markup' => '<p>This page shows a CEO-style inbox view derived from HQ sync metadata (not raw chat logs).</p>',
      ],
      'compose' => [
        '#type' => 'details',
        '#title' => $this->t('Compose message'),
        '#open' => FALSE,
        'form' => $this->formBuilder()->getForm(ComposeAgentMessageForm::class, $agent_options),
      ],
      'sent_threads' => [
        '#type' => 'details',
        '#title' => $this->t('Sent messages'),
        '#open' => FALSE,
        'items' => $sent_thread_items ?: [
          '#markup' => '<em>No sent messages yet.</em>',
        ],
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
        '#empty' => $this->t('No agents found.'),
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Quickly approve a Waiting on Keith inbox item (send "approved" + resolve).
   */
  public function approveWaitingOnKeithItem(string $item_id): RedirectResponse {
    $request = $this->dashboardRequestStack->getCurrentRequest();
    $token = (string) ($request?->query->get('token') ?? '');
    if (!$this->csrfToken->validate($token, 'approve-inbox:' . $item_id)) {
      throw new AccessDeniedHttpException();
    }

    $row = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a', ['metadata'])
      ->condition('agent_id', 'ceo-copilot%', 'LIKE')
      ->orderBy('last_seen', 'DESC')
      ->range(0, 1)
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

    $to_agent_id = trim((string) ($message['from_agent'] ?? ''));
    if ($to_agent_id === '' || strlen($to_agent_id) > 128) {
      $this->messenger()->addError($this->t('Cannot approve: missing or invalid destination agent.'));
      return new RedirectResponse(Url::fromRoute('copilot_agent_tracker.waiting_on_keith')->toString());
    }

    $now = (int) \Drupal::time()->getRequestTime();
    $this->database->insert('copilot_agent_tracker_replies')
      ->fields([
        'to_agent_id' => $to_agent_id,
        'in_reply_to' => $item_id,
        'message' => 'approved',
        'created' => $now,
        'consumed' => 0,
        'consumed_at' => 0,
      ])
      ->execute();

    $this->database->merge('copilot_agent_tracker_inbox_resolutions')
      ->key('item_id', $item_id)
      ->fields([
        'resolved' => 1,
        'resolved_at' => $now,
        'resolved_by_uid' => (int) $this->currentUser()->id(),
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Approved and removed from inbox.'));
    return new RedirectResponse(Url::fromRoute('copilot_agent_tracker.waiting_on_keith')->toString());
  }

  /**
   * Dismiss a sent message thread from the Waiting on Keith page.
   */
  public function dismissSentMessage(int $reply_id): RedirectResponse {
    $request = $this->dashboardRequestStack->getCurrentRequest();
    $token = (string) ($request?->query->get('token') ?? '');
    if (!$this->csrfToken->validate($token, 'dismiss-sent:' . $reply_id)) {
      throw new AccessDeniedHttpException();
    }

    $this->database->update('copilot_agent_tracker_replies')
      ->fields([
        'dismissed' => 1,
        'dismissed_at' => (int) \Drupal::time()->getRequestTime(),
        'dismissed_by_uid' => (int) $this->currentUser()->id(),
      ])
      ->condition('id', $reply_id)
      ->execute();

    $this->messenger()->addStatus($this->t('Sent message dismissed.'));
    return new RedirectResponse(Url::fromRoute('copilot_agent_tracker.waiting_on_keith')->toString());
  }

  /**
   * Message detail view with reply form.
   */
  public function waitingOnKeithMessage(string $item_id): array {
    $row = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a', ['metadata'])
      ->condition('agent_id', 'ceo-copilot%', 'LIKE')
      ->orderBy('last_seen', 'DESC')
      ->range(0, 1)
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
