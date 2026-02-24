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
use Drupal\copilot_agent_tracker\Form\OrgAutomationToggleForm;
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
      'role' => (string) ($request?->query->get('role') ?? ''),
    ];

    $rows = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a', ['agent_id', 'role', 'website', 'module', 'status', 'current_action', 'last_seen', 'metadata'])
      ->orderBy('website', 'ASC')
      ->orderBy('module', 'ASC')
      ->orderBy('role', 'ASC')
      ->orderBy('last_seen', 'DESC')
      ->execute()
      ->fetchAllAssoc('agent_id');

    $products = [];
    $roles = [];
    foreach ($rows as $row) {
      $website = (string) ($row->website ?? '');
      $module = (string) ($row->module ?? '');
      $product_key = $website . '::' . $module;
      $products[$product_key] = ($website ?: '-') . ' / ' . ($module ?: '-');

      $role = trim((string) ($row->role ?? ''));
      if ($role !== '') {
        $roles[$role] = $role;
      }
    }
    asort($products);
    ksort($roles);

    $filter_form = $this->dashboardFormBuilder->getForm(AgentDashboardFilterForm::class, [
      'products' => $products,
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
      if ($selected['role'] !== '' && $role !== $selected['role']) {
        continue;
      }

      $meta = [];
      if (!empty($row->metadata)) {
        try {
          $meta = Json::decode((string) $row->metadata) ?? [];
        }
        catch (\Throwable) {
          $meta = [];
        }
      }
      $inbox_count = 0;
      if (is_array($meta) && isset($meta['inbox_count'])) {
        $inbox_count = (int) $meta['inbox_count'];
      }

      $table_rows[] = [
        Link::fromTextAndUrl($agent_id, Url::fromRoute('copilot_agent_tracker.agent', ['agent_id' => $agent_id]))->toString(),
        $website,
        $module,
        $role,
        $status,
        $row->current_action ?? '',
        (string) $inbox_count,
        $row->last_seen ? $this->dateFormatter->format((int) $row->last_seen, 'short') : '',
      ];
    }

    return [
      '#type' => 'container',
      'help' => [
        '#markup' => '<p>Tracks high-level agent status updates and work item progress. Do not post raw conversation logs.</p>'
          . ($token ? '<p><strong>Telemetry token</strong> (send as <code>X-Copilot-Agent-Tracker-Token</code>): <code>' . $token . '</code></p>' : ''),
      ],
      'todo_separator' => [
        '#markup' => '<hr>',
      ],
      'todo' => $this->buildWaitingOnKeithView(),
      'agents_separator' => [
        '#markup' => '<hr>',
      ],
      'filters' => $filter_form,
      'agents' => [
        '#type' => 'table',
        '#header' => ['Agent', 'Website', 'Module', 'Role', 'Status', 'Current action', 'Inbox', 'Last seen'],
        '#rows' => $table_rows,
        '#empty' => $this->t('No agent updates yet.'),
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Consolidated entry point for Keith/CEO pending decisions.
   *
   * This report is now rendered within the main dashboard page to avoid
   * splitting the workflow across two separate admin reports.
   */
  public function waitingOnKeithRedirect(): RedirectResponse {
    $url = Url::fromRoute('copilot_agent_tracker.dashboard', [], [
      'fragment' => 'todo-for-keith',
    ]);
    return new RedirectResponse($url->toString(), 301);
  }

  /**
   * Backward-compatible controller method.
   *
   * Some environments may temporarily have stale route caches that still
   * reference `::waitingOnKeith`. Keep this method callable and delegate to the
   * canonical redirect.
   */
  public function waitingOnKeith(): RedirectResponse {
    return $this->waitingOnKeithRedirect();
  }

  /**
   * Inbox-style view for Keith/CEO pending decisions.
   */
  private function buildWaitingOnKeithView(): array {
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
    $agent_meta = [];

    $is_legacy_agent_id = static function (string $agent_id): bool {
      // Legacy bug: HQ briefly published per-inbox-item "agent ids" into the tracker.
      // These contain dated/task suffixes like:
      //   pm-foo-20260220-product-...
      //   ...-reply-keith-...
      //   ...-needs-...
      //   ...-clarify-escalation-...
      if ($agent_id === '') {
        return TRUE;
      }
      if (preg_match('/-\\d{8}(-|$)/', $agent_id)) {
        return TRUE;
      }
      if (str_contains($agent_id, '-reply-keith-') || str_contains($agent_id, '-needs-') || str_contains($agent_id, '-clarify-escalation-')) {
        return TRUE;
      }
      return FALSE;
    };

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

      // Include CEO agents in the pending-agent list so the report reflects the full set of tracked seats.

      // Hide legacy per-item IDs so the report shows only real agent seats.
      if ($is_legacy_agent_id($agent_id)) {
        continue;
      }

      $status = trim((string) ($row->status ?? ''));
      $is_paused = strtolower($status) === 'paused';

      $inbox_count = (int) ($meta['inbox_count'] ?? 0);

      // Prefer effective ROI (includes small time-based aging bonus from HQ).
      // Fall back to base ROI for older payloads.
      $next_inbox_roi = (int) ($meta['next_inbox_effective_roi'] ?? ($meta['next_inbox_roi'] ?? 1));
      if ($next_inbox_roi < 1) {
        $next_inbox_roi = 1;
      }

      // Sort key: prioritize agents with pending inbox items, then highest ROI.
      // (ROI is published from HQ as metadata.next_inbox_roi.)
      // Paused seats should still be visible on this page, but not prioritized.
      $sort_has_inbox = (!$is_paused && $inbox_count > 0) ? 1 : 0;
      $sort_roi = (!$is_paused && $sort_has_inbox) ? $next_inbox_roi : 0;
      $sort_last_seen = (int) ($row->last_seen ?? 0);

      $website_cell = trim((string) ($row->website ?? ''));
      $module_cell = trim((string) ($row->module ?? ''));
      $role_cell = trim((string) ($row->role ?? ''));
      $status_cell = trim((string) ($row->status ?? ''));
      $action_cell = trim((string) ($row->current_action ?? ''));
      $last_seen_cell = $row->last_seen ? $this->dateFormatter->format((int) $row->last_seen, 'short') : '-';

      $pending_items[] = [
        'sort_has_inbox' => $sort_has_inbox,
        'sort_roi' => $sort_roi,
        'sort_last_seen' => $sort_last_seen,
        'agent_id' => $agent_id,
        'row' => [
        Link::fromTextAndUrl($agent_id, Url::fromRoute('copilot_agent_tracker.agent', ['agent_id' => $agent_id]))->toString(),
        $website_cell !== '' ? $website_cell : '-',
        $module_cell !== '' ? $module_cell : '-',
        $role_cell !== '' ? $role_cell : '-',
        $status_cell !== '' ? $status_cell : '-',
        $action_cell !== '' ? $action_cell : '-',
        (string) $inbox_count,
        $last_seen_cell,
        ],
      ];

      $pending_agent_ids[$agent_id] = TRUE;
    }

    // Ensure *all* configured seats are represented, even if a seat hasn't
    // published telemetry yet (or was recently added).
    $configured = $ceo_meta['configured_seats'] ?? [];
    if (is_array($configured)) {
      foreach ($configured as $maybe_id) {
        $id = trim((string) $maybe_id);
        if ($id === '' || !is_string($maybe_id) && !is_numeric($maybe_id)) {
          continue;
        }
        if (!empty($pending_agent_ids[$id])) {
          continue;
        }
        // Keep legacy noise out of the report.
        if ($is_legacy_agent_id($id)) {
          continue;
        }

        // If the seat exists in the agents table but was excluded earlier for
        // some reason, let it show up normally (link works).
        // Otherwise, render a placeholder row without a broken link.
        $pending_items[] = [
          'sort_has_inbox' => 0,
          'sort_roi' => 0,
          'sort_last_seen' => 0,
          'agent_id' => $id,
          'row' => [
            $id,
            '-',
            '-',
            '-',
            'missing',
            'no telemetry yet',
            '0',
            '-',
          ],
        ];
        $pending_agent_ids[$id] = TRUE;
      }
    }

    // Apply org-level ordering: highest ROI first, while keeping agents with no inbox items at the bottom.
    usort($pending_items, static function (array $a, array $b): int {
      // Has inbox first.
      $c = ($b['sort_has_inbox'] ?? 0) <=> ($a['sort_has_inbox'] ?? 0);
      if ($c !== 0) {
        return $c;
      }
      // Highest ROI first.
      $c = ($b['sort_roi'] ?? 0) <=> ($a['sort_roi'] ?? 0);
      if ($c !== 0) {
        return $c;
      }
      // Most recently seen first.
      $c = ($b['sort_last_seen'] ?? 0) <=> ($a['sort_last_seen'] ?? 0);
      if ($c !== 0) {
        return $c;
      }
      // Stable-ish tie-breaker.
      return strcmp((string) ($a['agent_id'] ?? ''), (string) ($b['agent_id'] ?? ''));
    });

    foreach ($pending_items as $it) {
      if (!empty($it['row']) && is_array($it['row'])) {
        $pending_rows[] = $it['row'];
      }
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
    // Prefer configured seat ordering if HQ published it.
    $configured_ids = [];
    if (is_array($ceo_meta['configured_seats'] ?? NULL)) {
      foreach (($ceo_meta['configured_seats'] ?? []) as $maybe_id) {
        $id = trim((string) $maybe_id);
        if ($id !== '' && !$is_legacy_agent_id($id)) {
          $configured_ids[] = $id;
        }
      }
      $configured_ids = array_values(array_unique($configured_ids));
      sort($configured_ids);
    }

    $ordered_ids = array_values(array_unique(array_merge($ceo_ids, $configured_ids, $all_ids)));

    $by_id = [];
    foreach ($rows as $row) {
      $agent_id = trim((string) ($row->agent_id ?? ''));
      if ($agent_id !== '') {
        $by_id[$agent_id] = $row;
      }
    }

    foreach ($ordered_ids as $agent_id) {
      // Keep CEO threads, but hide legacy per-item IDs from the compose dropdown.
      if ($agent_id !== $self_agent_prefix && !str_starts_with($agent_id, $self_agent_prefix . '-') && $is_legacy_agent_id($agent_id)) {
        continue;
      }
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

    // Organizational priorities (published from HQ into CEO metadata).
    $priority_items = [];
    $org_priorities = $ceo_meta['org_priorities'] ?? [];
    if (is_array($org_priorities)) {
      foreach ($org_priorities as $p) {
        if (!is_array($p)) {
          continue;
        }
        $k = trim((string) ($p['key'] ?? ''));
        $score = $p['score'] ?? NULL;
        if ($k === '' || $score === NULL) {
          continue;
        }
        $priority_items[] = $this->t('@k: @v', ['@k' => $k, '@v' => (string) $score]);
      }
    }

    // Org-wide automation controls (status published from HQ; toggle delegates back to HQ).
    $org_control = $ceo_meta['org_control'] ?? [];
    if (!is_array($org_control)) {
      $org_control = [];
    }
    $org_control += [
      'enabled' => TRUE,
      'updated_at' => NULL,
      'updated_by' => NULL,
      'reason' => NULL,
    ];

    return [
      '#type' => 'container',
      'priorities' => [
        '#type' => 'container',
        'title' => [
          '#markup' => '<h2>Organizational priorities</h2>',
        ],
        'list' => $priority_items ? [
          '#theme' => 'item_list',
          '#items' => $priority_items,
        ] : [
          '#markup' => '<em>No priorities published yet.</em>',
        ],
      ],
      'org_controls' => [
        '#type' => 'details',
        '#title' => $this->t('Org automation'),
        '#open' => FALSE,
        'form' => $this->formBuilder()->getForm(OrgAutomationToggleForm::class, $org_control),
      ],
      'help' => [
        '#type' => 'details',
        '#title' => $this->t('Process flow (authority)'),
        '#open' => FALSE,
        '#markup' => '<p><strong>Purpose:</strong> keep work progressing with a single inbox/outbox per configured agent seat, with a clean audit trail.</p>'
          . '<p><strong>Authority for non-CEO agents:</strong> HQ org-wide + role instructions (copilot-sessions-hq: <code>org-chart/org-wide.instructions.md</code> and <code>org-chart/roles/*.instructions.md</code>). Agents select the next work item from their seat inbox; if idle, they generate role-appropriate work.</p>'
          . '<hr>'
          . '<h3>Keith (Human owner) — what to do next</h3>'
          . '<ol>'
          . '<li>Open this page and review the <strong>Messages</strong> table (CEO inbox items needing decision).</li>'
          . '<li>For each message: read <em>Decision needed</em> + <em>Recommendation</em>, then decide: approve / request clarification / deprioritize.</li>'
          . '<li>If you approve: click <strong>Approve</strong> (it sends an “approved” reply back to the originating agent and resolves the item).</li>'
          . '<li>If you need changes: reply with specific direction (scope, stage breaks, acceptance criteria, constraints).</li>'
          . '<li>Check <strong>Sent messages</strong> to ensure replies were delivered and are pending in the intended agent inbox.</li>'
          . '<li>Daily/periodic: confirm top OKR priorities and adjust only the smallest number of constraints needed to unblock execution.</li>'
          . '</ol>'
          . '<h3>CEO (ceo-copilot) — operating loop</h3>'
          . '<ol>'
          . '<li>Run HQ status + blocker review (HQ scripts): <code>scripts/hq-status.sh</code> and <code>scripts/hq-blockers.sh</code>.</li>'
          . '<li>Ensure every PM seat has an active queue for BA/Dev/QA (one inbox per seat; no new agent IDs).</li>'
          . '<li>When blocked: either provide missing inputs (files/paths/URLs) in a single inbox item, or escalate to Keith with options.</li>'
          . '<li>When idle: seed continuous improvement work for HQ processes and tooling (delegated as inbox items).</li>'
          . '<li>Publish status telemetry from HQ to this tracker (cron) and keep the agent list clean (configured seats only).</li>'
          . '</ol>',
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
      'todo_title' => [
        '#type' => 'container',
        '#markup' => '<h2 id="todo-for-keith">Todo for Keith</h2>',
      ],
      'messages' => [
        '#type' => 'table',
        '#header' => ['Subject', 'From', 'Product', 'Role', 'Decision needed', 'Recommendation', 'Preview'],
        '#rows' => $message_rows,
        '#empty' => $this->t('No inbox items detected.'),
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
      return new RedirectResponse(Url::fromRoute('copilot_agent_tracker.dashboard', [], ['fragment' => 'todo-for-keith'])->toString());
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
    return new RedirectResponse(Url::fromRoute('copilot_agent_tracker.dashboard', [], ['fragment' => 'todo-for-keith'])->toString());
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
    return new RedirectResponse(Url::fromRoute('copilot_agent_tracker.dashboard', [], ['fragment' => 'todo-for-keith'])->toString());
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
   * Release Notes admin report (HQ-driven).
   *
   * Data source: CEO metadata published from HQ (scripts/publish-forseti-agent-tracker.sh)
   * under metadata.release_notes.
   */
  public function releaseNotes(): array {
    // Resolve CEO metadata (same approach as Waiting on Keith).
    $row = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a', ['metadata', 'last_seen'])
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

    $entries = $meta['release_notes'] ?? [];
    if (!is_array($entries)) {
      $entries = [];
    }

    $items = [];
    foreach ($entries as $e) {
      if (!is_array($e)) {
        continue;
      }
      $rid = trim((string) ($e['release_id'] ?? ''));
      if ($rid === '') {
        continue;
      }
      $state = trim((string) ($e['state'] ?? '')) ?: 'unknown';

      $details = [];
      $fields = [
        'plan' => 'Release plan',
        'change_list' => 'Change list',
        'test_evidence' => 'Test evidence',
        'risk_security' => 'Risk + security',
        'rollback' => 'Rollback',
        'human_approval' => 'Human approval',
        'release_notes' => 'Release notes',
      ];
      foreach ($fields as $k => $title) {
        $txt = (string) ($e[$k] ?? '');
        $txt = trim($txt);
        if ($txt === '') {
          continue;
        }
        $details[] = [
          '#type' => 'details',
          '#title' => $this->t('@t', ['@t' => $title]),
          '#open' => FALSE,
          '#markup' => '<pre style="white-space:pre-wrap;max-height:260px;overflow:auto;">' . htmlspecialchars($txt) . '</pre>',
        ];
      }

      // Link to Waiting on Keith message view if it's a pending needs-* item.
      $rid_link = $rid;
      if (preg_match('/^\d{8}-needs-/', $rid)) {
        $rid_link = Link::fromTextAndUrl($rid, Url::fromRoute('copilot_agent_tracker.waiting_on_keith_message', ['item_id' => $rid]))->toString();
      }

      $items[] = [
        '#type' => 'details',
        '#title' => Markup::create($rid_link . ' — ' . htmlspecialchars($state)),
        '#open' => FALSE,
        'body' => $details ?: ['#markup' => '<em>No details published.</em>'],
      ];
    }

    return [
      '#type' => 'container',
      'help' => [
        '#markup' => '<p>This page is driven by HQ release candidate artifacts and is coordinated by the CEO. Pending release candidates should appear here and in <a href="/admin/reports/waitingonkeith">Waiting on Keith</a> for human approval.</p>',
      ],
      'items' => $items ?: [
        '#markup' => '<em>No release candidates or shipped releases published yet.</em>',
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

    $meta = $this->decodeAgentMetadata($agent);
    $inbox_items = $this->extractInboxItems($meta);

    $active_item_id = trim((string) ($meta['active_inbox'] ?? ''));
    $outbox_results = (!empty($meta['outbox_results']) && is_array($meta['outbox_results'])) ? $meta['outbox_results'] : [];
    $outbox_recent = (!empty($outbox_results['recent']) && is_array($outbox_results['recent'])) ? $outbox_results['recent'] : [];
    $outbox_status_by_id = [];
    foreach ($outbox_recent as $r) {
      if (!is_array($r)) {
        continue;
      }
      $rid = trim((string) ($r['item_id'] ?? ''));
      if ($rid === '') {
        continue;
      }
      $rst = trim((string) ($r['status'] ?? ''));
      if ($rst !== '') {
        $outbox_status_by_id[$rid] = $rst;
      }
    }

    $queue_rows = $this->buildQueueRows($agent_id, $inbox_items, $active_item_id, $outbox_status_by_id);
    $event_rows = $this->buildEventRows($events);
    $metrics_items = $this->buildAgentMetricsItems($meta, $inbox_items);
    $collapse_details = ($agent_id === 'dev-forseti');
    $results = $this->buildAgentResultsSections($meta, !$collapse_details);

    $qa_counts = (!empty($meta['qa_test_counts']) && is_array($meta['qa_test_counts'])) ? $meta['qa_test_counts'] : [];
    $qa_last = (!empty($meta['qa_last_audit']) && is_array($meta['qa_last_audit'])) ? $meta['qa_last_audit'] : [];
    $is_qa = (($agent['role'] ?? '') === 'tester') || str_starts_with($agent_id, 'qa-');
    $qa_counts_items = [];
    if ($is_qa) {
      $qa_counts_items[] = 'Unit tests: ' . (string) ((int) ($qa_counts['unit'] ?? 0));
      $qa_counts_items[] = 'Functional tests: ' . (string) ((int) ($qa_counts['functional'] ?? 0));
      $qa_counts_items[] = 'Integration tests: ' . (string) ((int) ($qa_counts['integration'] ?? 0));
      $qa_counts_items[] = 'Total: ' . (string) ((int) ($qa_counts['total'] ?? 0));
    }

    $qa_last_items = [];
    if ($is_qa && $qa_last) {
      $qa_last_items[] = 'Status: ' . (string) ($qa_last['status'] ?? '-');
      if (!empty($qa_last['run_id'])) {
        $qa_last_items[] = 'Last run: ' . (string) $qa_last['run_id'];
      }
      if (!empty($qa_last['base_url'])) {
        $qa_last_items[] = 'Base URL: ' . (string) $qa_last['base_url'];
      }
      $qa_last_items[] = 'URL checks: ' . (string) ((int) ($qa_last['url_checks_total'] ?? 0))
        . ' (failed ' . (string) ((int) ($qa_last['url_checks_failed'] ?? 0)) . ')';
      $qa_last_items[] = 'Route checks: ' . (string) ((int) ($qa_last['route_checks_total'] ?? 0))
        . ' (failed ' . (string) ((int) ($qa_last['route_checks_failed'] ?? 0)) . ')';
      $qa_last_items[] = 'Permission violations: ' . (string) ((int) ($qa_last['permission_violation_count'] ?? 0));
      $roles = (!empty($qa_last['roles_covered']) && is_array($qa_last['roles_covered'])) ? $qa_last['roles_covered'] : [];
      if ($roles) {
        $qa_last_items[] = 'Roles covered: ' . implode(', ', array_slice(array_map('strval', $roles), 0, 8));
      }
    }

    $active_summary_items = [];
    if ($active_item_id !== '' && !empty($inbox_items[$active_item_id]) && is_array($inbox_items[$active_item_id])) {
      $it = $inbox_items[$active_item_id];
      $active_summary_items[] = Markup::create('<strong>' . htmlspecialchars($active_item_id) . '</strong>');
      $roi = (int) ($it['roi'] ?? 0);
      $eff = (int) ($it['effective_roi'] ?? 0);
      $mtime = (int) ($it['mtime'] ?? 0);
      $preview = trim((string) ($it['preview'] ?? ''));
      if ($roi > 0) {
        $active_summary_items[] = 'ROI: ' . (string) $roi;
      }
      if ($eff > 0) {
        $active_summary_items[] = 'Effective ROI: ' . (string) $eff;
      }
      if ($mtime > 0) {
        $active_summary_items[] = 'Updated: ' . $this->dateFormatter->format($mtime, 'short');
      }
      if ($preview !== '') {
        $active_summary_items[] = 'Preview: ' . $preview;
      }

      $active_link = Link::fromTextAndUrl($this->t('Open active item'), Url::fromRoute('copilot_agent_tracker.agent_inbox_item', ['agent_id' => $agent_id, 'item_id' => $active_item_id]))->toString();
      $active_summary_items[] = Markup::create($active_link);
    }

    return [
      '#type' => 'container',
      'summary' => [
        '#markup' => '<h2>' . $this->t('Agent: @id', ['@id' => $agent_id]) . '</h2>',
      ],
      'active_item' => $active_summary_items ? [
        '#type' => 'details',
        '#title' => $this->t('Active work item'),
        '#open' => TRUE,
        'items' => [
          '#theme' => 'item_list',
          '#items' => $active_summary_items,
        ],
      ] : [],
      'qa_roster' => ($is_qa && $qa_counts_items) ? [
        '#type' => 'container',
        'title' => [
          '#markup' => '<p><strong>QA test roster</strong></p>',
        ],
        'items' => [
          '#theme' => 'item_list',
          '#items' => $qa_counts_items,
        ],
      ] : [],
      'qa_last_run' => ($is_qa && $qa_last_items) ? [
        '#type' => 'container',
        'title' => [
          '#markup' => '<p><strong>QA last run (scripted)</strong></p>',
        ],
        'items' => [
          '#theme' => 'item_list',
          '#items' => $qa_last_items,
        ],
      ] : [],
      'metrics' => [
        '#type' => 'details',
        '#title' => $this->t('Metrics'),
        '#open' => !$collapse_details,
        'items' => [
          '#theme' => 'item_list',
          '#items' => $metrics_items,
        ],
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
      'results' => $results,
      'queue' => [
        '#type' => 'details',
        '#title' => $this->t('Inbox queue'),
        '#open' => !$collapse_details,
        'table' => [
          '#type' => 'table',
          '#header' => ['Item', 'ROI', 'Effective ROI', 'Updated', 'Preview'],
          '#rows' => $queue_rows,
          '#empty' => $this->t('No inbox items published for this agent.'),
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

  private function decodeAgentMetadata(array $agent): array {
    if (empty($agent['metadata'])) {
      return [];
    }
    try {
      $decoded = Json::decode((string) $agent['metadata']);
      return is_array($decoded) ? $decoded : [];
      }
      $metrics_items[] = Markup::create('<strong>Cost KPIs</strong>:<br/>' . htmlspecialchars(implode(' | ', array_slice(array_map('strval', $kpi_cost), 0, 6))));
    }
    if ($kpi_quality) {
      $metrics_items[] = Markup::create('<strong>Quality KPIs</strong>:<br/>' . htmlspecialchars(implode(' | ', array_slice(array_map('strval', $kpi_quality), 0, 6))));
    }
    if ($kpi_speed) {
      $metrics_items[] = Markup::create('<strong>Speed KPIs</strong>:<br/>' . htmlspecialchars(implode(' | ', array_slice(array_map('strval', $kpi_speed), 0, 6))));
    }

    return $metrics_items;
  }

  private function buildAgentResultsSections(array $meta, bool $open = TRUE): array {
    $outbox_results = (!empty($meta['outbox_results']) && is_array($meta['outbox_results'])) ? $meta['outbox_results'] : [];

    $results_completed = [];
    $results_forwarded = [];
    $results_in_progress = [];
    $results_other = [];

    if (!empty($outbox_results['recent']) && is_array($outbox_results['recent'])) {
      foreach ($outbox_results['recent'] as $r) {
        if (!is_array($r)) {
          continue;
        }
        $rid = trim((string) ($r['item_id'] ?? ''));
        if ($rid === '') {
          continue;
        }
        $rstatus = trim((string) ($r['status'] ?? ''));
        $rsummary = trim((string) ($r['summary'] ?? ''));
        $rroi = (int) ($r['roi'] ?? 0);
        $rmtime = (int) ($r['mtime'] ?? 0);
        $rexcerpt = (string) ($r['excerpt'] ?? '');

        $title_bits = [];
        $title_bits[] = htmlspecialchars($rid);
        if ($rstatus !== '') {
          $title_bits[] = htmlspecialchars($rstatus);
        }
        if ($rroi > 0) {
          $title_bits[] = 'ROI ' . $rroi;
        }
        $title = implode(' — ', $title_bits);

        $meta_bits = [];
        if ($rmtime > 0) {
          $meta_bits[] = '<strong>' . $this->t('Updated') . ':</strong> ' . $this->dateFormatter->format($rmtime, 'short');
        }
        if ($rsummary !== '') {
          $meta_bits[] = '<strong>' . $this->t('Summary') . ':</strong> ' . htmlspecialchars($rsummary);
        }

        $body = $rexcerpt !== ''
          ? '<pre style="white-space: pre-wrap;">' . htmlspecialchars($rexcerpt) . '</pre>'
          : '<em>No excerpt published.</em>';

        $normalized = strtolower(str_replace(' ', '_', $rstatus));
        if ($normalized === 'needsinfo') {
          $normalized = 'needs-info';
        }

        $item_render = [
          '#type' => 'details',
          '#title' => Markup::create($title),
          '#open' => FALSE,
          'meta' => [
            '#markup' => $meta_bits ? '<p>' . implode('<br/>', $meta_bits) . '</p>' : '',
          ],
          'body' => [
            '#markup' => $body,
          ],
        ];

        if ($normalized === 'done') {
          $results_completed[] = $item_render;
        }
        elseif ($normalized === 'needs-info' || $normalized === 'blocked') {
          $results_forwarded[] = $item_render;
        }
        elseif ($normalized === 'in_progress') {
          $results_in_progress[] = $item_render;
        }
        else {
          $results_other[] = $item_render;
        }
      }
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('Results'),
      '#open' => $open,
      'help' => [
        '#markup' => '<p><strong>Completed</strong> = Status done. <strong>Forwarded</strong> = Status needs-info/blocked (requires a decision or missing input). This is derived from HQ outbox updates.</p>',
      ],
      'completed' => [
        '#type' => 'details',
        '#title' => $this->t('Completed (recent)'),
        '#open' => $open,
        'items' => $results_completed ?: ['#markup' => '<em>No completed results published yet.</em>'],
      ],
      'forwarded' => [
        '#type' => 'details',
        '#title' => $this->t('Forwarded / needs decision (recent)'),
        '#open' => $open,
        'items' => $results_forwarded ?: ['#markup' => '<em>No forwarded/escalated results published yet.</em>'],
      ],
      'in_progress' => [
        '#type' => 'details',
        '#title' => $this->t('In progress (recent)'),
        '#open' => FALSE,
        'items' => $results_in_progress ?: ['#markup' => '<em>No in-progress results published yet.</em>'],
      ],
      'other' => [
        '#type' => 'details',
        '#title' => $this->t('Other (recent)'),
        '#open' => FALSE,
        'items' => $results_other ?: ['#markup' => '<em>No other results published yet.</em>'],
      ],
    ];
  }

  /**
   * Agent inbox item detail view (from HQ-published metadata).
   */
  public function agentInboxItem(string $agent_id, string $item_id): array {
    $agent = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a', ['agent_id', 'role', 'website', 'module', 'status', 'current_action', 'last_seen', 'metadata'])
      ->condition('agent_id', $agent_id)
      ->execute()
      ->fetchAssoc();

    if (!$agent) {
      throw new NotFoundHttpException();
    }

    $meta = [];
    if (!empty($agent['metadata'])) {
      try {
        $decoded = Json::decode((string) $agent['metadata']);
        $meta = is_array($decoded) ? $decoded : [];
      }
      catch (\Throwable) {
        $meta = [];
      }
    }

    $detail = NULL;
    if (!empty($meta['inbox_items_detail']) && is_array($meta['inbox_items_detail'])) {
      foreach ($meta['inbox_items_detail'] as $it) {
        if (is_array($it) && (string) ($it['item_id'] ?? '') === $item_id) {
          $detail = $it;
          break;
        }
      }
    }

    $agent_status = trim((string) ($agent['status'] ?? ''));
    $agent_action = trim((string) ($agent['current_action'] ?? ''));
    $agent_last_seen = (int) ($agent['last_seen'] ?? 0);
    $agent_active_inbox = trim((string) ($meta['active_inbox'] ?? ''));

    $active_on_this = FALSE;
    if (strtolower($agent_status) === 'in_progress') {
      if ($agent_active_inbox !== '' && $agent_active_inbox === $item_id) {
        $active_on_this = TRUE;
      }
      elseif ($agent_action !== '' && str_contains($agent_action, $item_id)) {
        $active_on_this = TRUE;
      }
    }

    $activity_items = [];
    $activity_items[] = 'Agent status: ' . ($agent_status !== '' ? $agent_status : '-');
    $activity_items[] = 'Last seen: ' . ($agent_last_seen ? $this->dateFormatter->format($agent_last_seen, 'short') : '-');
    $activity_items[] = 'Current action: ' . ($agent_action !== '' ? $agent_action : '-');
    if ($active_on_this) {
      $activity_items[] = Markup::create('<strong>Actively executing this item.</strong>');
    }
    elseif (strtolower($agent_status) === 'in_progress' && $agent_active_inbox !== '' && $agent_active_inbox !== $item_id) {
      $other_link = Link::fromTextAndUrl($agent_active_inbox, Url::fromRoute('copilot_agent_tracker.agent_inbox_item', ['agent_id' => $agent_id, 'item_id' => $agent_active_inbox]))->toString();
      $activity_items[] = Markup::create('Active item: ' . $other_link);
    }

    if (!$detail) {
      return [
        '#type' => 'container',
        'header' => [
          '#markup' => '<h2>' . $this->t('Inbox item: @item', ['@item' => $item_id]) . '</h2>'
            . '<p><strong>' . $this->t('Agent') . ':</strong> ' . $this->t('@a', ['@a' => $agent_id]) . '</p>',
        ],
        'activity' => [
          '#theme' => 'item_list',
          '#items' => $activity_items,
        ],
        'missing' => [
          '#markup' => '<p><em>No detail published for this item yet.</em> This usually means HQ has not published the newer inbox detail payload. Re-run the HQ publish job and refresh.</p>',
        ],
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }

    $roi = (int) ($detail['roi'] ?? 0);
    $eff = (int) ($detail['effective_roi'] ?? 0);
    $mtime = (int) ($detail['mtime'] ?? 0);
    $files = $detail['files'] ?? [];
    $files = is_array($files) ? $files : [];
    $body_source = (string) ($detail['body_source'] ?? '');
    $body = (string) ($detail['body'] ?? '');

    $file_markup = '<em>None</em>';
    if ($files) {
      $safe = array_map(static fn($v) => htmlspecialchars((string) $v), $files);
      $file_markup = '<ul><li>' . implode('</li><li>', $safe) . '</li></ul>';
    }

    return [
      '#type' => 'container',
      'header' => [
        '#markup' => '<h2>' . $this->t('Inbox item: @item', ['@item' => $item_id]) . '</h2>'
          . '<p><strong>' . $this->t('Agent') . ':</strong> ' . $this->t('@a', ['@a' => $agent_id]) . '</p>'
          . '<p><strong>' . $this->t('Product') . ':</strong> ' . $this->t('@p', ['@p' => (($agent['website'] ?? '') ?: '-') . ' / ' . (($agent['module'] ?? '') ?: '-')]) . '</p>'
          . '<p><strong>' . $this->t('Role') . ':</strong> ' . $this->t('@r', ['@r' => ($agent['role'] ?? '') ?: '-']) . '</p>',
      ],
      'activity' => [
        '#type' => 'details',
        '#title' => $this->t('Agent activity (latest published)'),
        '#open' => TRUE,
        'items' => [
          '#theme' => 'item_list',
          '#items' => $activity_items,
        ],
      ],
      'meta' => [
        '#theme' => 'item_list',
        '#items' => [
          'ROI: ' . ($roi > 0 ? (string) $roi : '-'),
          'Effective ROI: ' . ($eff > 0 ? (string) $eff : '-'),
          'Updated: ' . ($mtime ? $this->dateFormatter->format($mtime, 'short') : '-'),
          'Source file: ' . ($body_source !== '' ? $body_source : '-'),
        ],
      ],
      'files' => [
        '#type' => 'details',
        '#title' => $this->t('Files'),
        '#open' => FALSE,
        '#markup' => $file_markup,
      ],
      'body' => [
        '#type' => 'details',
        '#title' => $this->t('Content'),
        '#open' => TRUE,
        'content' => [
          '#type' => 'textarea',
          '#title' => $this->t('Body'),
          '#value' => $body,
          '#rows' => 22,
          '#attributes' => ['readonly' => 'readonly'],
        ],
      ],
      'back' => [
        '#markup' => '<p>' . Link::fromTextAndUrl($this->t('Back to agent'), Url::fromRoute('copilot_agent_tracker.agent', ['agent_id' => $agent_id]))->toString() . '</p>',
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
