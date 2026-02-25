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
    $visible_agents = [];
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

      $active_item_id = '';
      if (is_array($meta) && isset($meta['active_inbox'])) {
        $active_item_id = trim((string) ($meta['active_inbox'] ?? ''));
      }

      $inbox_count = (int) ($meta['inbox_count'] ?? 0);
      $next_item_id = '';
      if (is_array($meta) && isset($meta['next_inbox'])) {
        $next_item_id = trim((string) ($meta['next_inbox'] ?? ''));
      }
      $next_inbox_roi = (int) ($meta['next_inbox_effective_roi'] ?? ($meta['next_inbox_roi'] ?? 0));

      $visible_agents[] = [
        'agent_id' => (string) $agent_id,
        'website' => $website,
        'module' => $module,
        'role' => $role,
        'status' => $status,
        'current_action' => (string) ($row->current_action ?? ''),
        'last_seen' => (int) ($row->last_seen ?? 0),
        'active_item_id' => $active_item_id,
        'inbox_count' => $inbox_count,
        'next_item_id' => $next_item_id,
        'next_inbox_roi' => $next_inbox_roi,
        'meta' => is_array($meta) ? $meta : [],
      ];

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

    $current_release = $this->buildCurrentReleaseSummary($visible_agents);
    $release_stages = $this->buildReleaseStageAccordion($visible_agents);

    return [
      '#type' => 'container',
      'help' => [
        '#markup' => '<p>Tracks high-level agent status updates and work item progress. Do not post raw conversation logs.</p>'
          . '<p><strong>Release stage view</strong> (below) is a best-effort inference based on agent role + tracker metadata. It includes in-progress, queued (inbox), and blocked work when available.</p>'
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
      'current_release' => $current_release,
      'release_stages' => $release_stages,
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
   * Builds a "current release" summary block.
   *
   * Uses CEO-published metadata.release_notes to infer the current release id
   * and uses QA agent metadata (qa_last_audit) to show per-product PASS/FAIL.
   */
  private function buildCurrentReleaseSummary(array $agents): array {
    $current_release_id = '';

    // Pull most-recent CEO metadata and infer current release id.
    $row = $this->database->select('copilot_agent_tracker_agents', 'a')
      ->fields('a', ['metadata', 'last_seen'])
      ->condition('agent_id', 'ceo-copilot%', 'LIKE')
      ->orderBy('last_seen', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $ceo_meta = [];
    if (!empty($row['metadata'])) {
      try {
        $ceo_meta = Json::decode((string) $row['metadata']) ?? [];
      }
      catch (\Throwable) {
        $ceo_meta = [];
      }
    }

    $entries = (is_array($ceo_meta) && !empty($ceo_meta['release_notes']) && is_array($ceo_meta['release_notes'])) ? $ceo_meta['release_notes'] : [];
    if ($entries) {
      $candidates = [];
      $fallback = [];
      foreach ($entries as $e) {
        if (!is_array($e)) {
          continue;
        }
        $rid = trim((string) ($e['release_id'] ?? ''));
        if ($rid === '') {
          continue;
        }
        if (!preg_match('/^\d{8}-[A-Za-z0-9._-]+$/', $rid)) {
          continue;
        }
        $fallback[] = $rid;

        $state = strtolower(trim((string) ($e['state'] ?? '')));
        // Treat anything not explicitly shipped/released as "current" candidate.
        if ($state === '' || $state === 'pending' || $state === 'candidate' || $state === 'needs_approval' || $state === 'needs-approval') {
          $candidates[] = $rid;
          continue;
        }
        if (!in_array($state, ['shipped', 'released', 'done', 'closed'], TRUE)) {
          $candidates[] = $rid;
        }
      }

      $pick_from = $candidates ?: $fallback;
      if ($pick_from) {
        // Release ids are typically YYYYMMDD-*; lexicographic max approximates newest.
        sort($pick_from);
        $current_release_id = (string) end($pick_from);
      }
    }

    if ($current_release_id === '') {
      $current_release_id = $this->inferReleaseIdFromInFlightAgents($agents);
    }

    if ($current_release_id === '') {
      $current_release_id = $this->inferReleaseIdFromQaPreflightArtifacts();
    }

    if ($current_release_id === '') {
      $current_release_id = $this->inferReleaseIdFromSignoffs();
    }

    $release_notes_url = Url::fromRoute('copilot_agent_tracker.release_notes');
    $release_notes_link = Link::fromTextAndUrl('Release notes / features / evidence', $release_notes_url)->toString();
    $release_id_link = $current_release_id !== ''
      ? Link::fromTextAndUrl($current_release_id, $release_notes_url)->toString()
      : '-';

    // Build per-product QA status table.
    $by_product = [];
    foreach ($agents as $a) {
      if (!is_array($a)) {
        continue;
      }
      $website = trim((string) ($a['website'] ?? ''));
      $module = trim((string) ($a['module'] ?? ''));

      // Internal/unscoped seats (e.g. agent-code-review) publish empty website+module.
      // They should not appear as a "- / -" product row.
      if ($website === '' && $module === '') {
        continue;
      }

      $product_key = $website . '::' . $module;
      if (!isset($by_product[$product_key])) {
        $by_product[$product_key] = [
          'website' => $website,
          'module' => $module,
          'agents' => [],
        ];
      }
      $by_product[$product_key]['agents'][] = $a;
    }
    ksort($by_product);

    // Website-level fallback QA seats, used when a module row has no direct QA.
    // Prefer seats scoped only to the website (module empty) when available.
    $qa_fallback_by_website = [];
    foreach ($agents as $a) {
      if (!is_array($a)) {
        continue;
      }
      $agent_id = (string) ($a['agent_id'] ?? '');
      $role = (string) ($a['role'] ?? '');
      $website = trim((string) ($a['website'] ?? ''));
      if ($website === '') {
        continue;
      }
      if (!($role === 'tester' || str_starts_with($agent_id, 'qa-'))) {
        continue;
      }

      $candidate_module = trim((string) ($a['module'] ?? ''));
      $meta = (!empty($a['meta']) && is_array($a['meta'])) ? $a['meta'] : [];
      $has_audit = !empty($meta['qa_last_audit']) && is_array($meta['qa_last_audit']);
      $score = ($has_audit ? 2 : 0) + ($candidate_module === '' ? 1 : 0);

      if (!isset($qa_fallback_by_website[$website]) || $score > (int) ($qa_fallback_by_website[$website]['score'] ?? -1)) {
        $qa_fallback_by_website[$website] = [
          'agent' => $a,
          'has_audit' => $has_audit,
          'score' => $score,
        ];
      }
    }

    $qa_rows = [];
    foreach ($by_product as $product_key => $p) {
      $website = (string) ($p['website'] ?? '');
      $module = (string) ($p['module'] ?? '');
      $product_label = ($website ?: '-') . ' / ' . ($module ?: '-');

      $pm_agent = NULL;
      $qa_agent = NULL;
      $qa_agent_has_audit = FALSE;
      $qa_is_website_fallback = FALSE;
      $saw_paused = FALSE;
      $saw_non_paused = FALSE;
      foreach (($p['agents'] ?? []) as $a) {
        if (!is_array($a)) {
          continue;
        }
        $agent_id = (string) ($a['agent_id'] ?? '');
        $role = (string) ($a['role'] ?? '');
        $agent_status = strtolower(trim((string) ($a['status'] ?? '')));
        if ($agent_status === 'paused') {
          $saw_paused = TRUE;
        }
        elseif ($agent_status !== '') {
          $saw_non_paused = TRUE;
        }
        if ($pm_agent === NULL && ($role === 'product-manager' || str_starts_with($agent_id, 'pm-'))) {
          $pm_agent = $a;
        }
        if ($role === 'tester' || str_starts_with($agent_id, 'qa-')) {
          $meta = (!empty($a['meta']) && is_array($a['meta'])) ? $a['meta'] : [];
          $has_audit = !empty($meta['qa_last_audit']) && is_array($meta['qa_last_audit']);
          if (!is_array($qa_agent) || (!$qa_agent_has_audit && $has_audit)) {
            $qa_agent = $a;
            $qa_agent_has_audit = $has_audit;
          }
        }
      }

      if ($website !== '' && isset($qa_fallback_by_website[$website])) {
        $fallback = $qa_fallback_by_website[$website];
        $fallback_agent = $fallback['agent'] ?? NULL;
        $fallback_has_audit = (bool) ($fallback['has_audit'] ?? FALSE);
        if (
          is_array($fallback_agent)
          && (
            !is_array($qa_agent)
            || (!$qa_agent_has_audit && $fallback_has_audit)
          )
        ) {
          $qa_agent = $fallback_agent;
          $qa_agent_has_audit = $fallback_has_audit;
          $qa_is_website_fallback = TRUE;
        }
      }

      $product_paused = ($saw_paused && !$saw_non_paused);

      $qa_status = $product_paused ? 'PAUSED' : 'NOT RUN';
      $qa_details = $product_paused ? 'Paused' : '-';
      $qa_link = '-';
      $features_link = '-';

      if (is_array($pm_agent)) {
        $pm_agent_id = (string) ($pm_agent['agent_id'] ?? '');
        if ($pm_agent_id !== '') {
          $features_link = Link::fromTextAndUrl('Features', Url::fromRoute('copilot_agent_tracker.agent', ['agent_id' => $pm_agent_id]))->toString();
        }
      }

      if ($product_paused) {
        // Keep the explicit PAUSED marker; do not attempt to interpret QA data.
      }
      elseif (!is_array($qa_agent)) {
        $qa_status = 'NO QA';
        $qa_details = 'No QA seat for this product';
      }
      else {
        $qa_agent_id = (string) ($qa_agent['agent_id'] ?? '');
        $qa_link = Link::fromTextAndUrl($qa_agent_id, Url::fromRoute('copilot_agent_tracker.agent', ['agent_id' => $qa_agent_id]))->toString();

        $meta = (!empty($qa_agent['meta']) && is_array($qa_agent['meta'])) ? $qa_agent['meta'] : [];
        $qa_last = (!empty($meta['qa_last_audit']) && is_array($meta['qa_last_audit'])) ? $meta['qa_last_audit'] : [];

        if (!$qa_last) {
          $qa_status = 'NOT RUN';
          $qa_details = 'No QA audit published yet';
        }
        else {
          $failed = (int) ($qa_last['url_checks_failed'] ?? 0) + (int) ($qa_last['route_checks_failed'] ?? 0) + (int) ($qa_last['permission_violation_count'] ?? 0);
          $run_id = trim((string) ($qa_last['run_id'] ?? ''));
          $status = strtolower(trim((string) ($qa_last['status'] ?? '')));
          $base_url = trim((string) ($qa_last['base_url'] ?? ''));

          if ($failed > 0 || in_array($status, ['issues', 'fail', 'failed'], TRUE)) {
            $qa_status = 'FAIL';
          }
          elseif (in_array($status, ['clean', 'pass', 'passed'], TRUE)) {
            $qa_status = 'PASS';
          }
          elseif ($failed === 0 && ($run_id !== '' || $base_url !== '')) {
            // If we have a concrete run published and zero failures, treat it as PASS.
            $qa_status = 'PASS';
          }
          else {
            $qa_status = 'NOT RUN';
          }

          $qa_details_parts = [];
          if ($run_id !== '') {
            $qa_details_parts[] = 'Run: ' . htmlspecialchars($run_id);
          }
          if ($base_url !== '') {
            $qa_details_parts[] = 'Base: ' . htmlspecialchars($base_url);
          }
          if ($qa_is_website_fallback) {
            $qa_details_parts[] = 'Scope: website QA seat';
          }
          $qa_details_parts[] = 'Failed checks: ' . (string) max(0, $failed);
          $qa_details = $qa_details_parts ? implode(' — ', $qa_details_parts) : '-';
        }
      }

      $qa_rows[] = [
        htmlspecialchars($product_label),
        Markup::create('<strong>' . htmlspecialchars($qa_status) . '</strong>'),
        Markup::create($qa_details),
        Markup::create($features_link),
        Markup::create($qa_link),
      ];
    }

    return [
      '#type' => 'container',
      'title' => [
        '#markup' => '<h3>Current release</h3>',
      ],
      'summary' => [
        '#markup' => '<p><strong>Release id:</strong> ' . $release_id_link . '</p>'
          . '<p><strong>Links:</strong> ' . $release_notes_link . '</p>'
          . '<p><em>The release stage section below marks the inferred current stage as “CURRENT”.</em></p>',
      ],
      'qa_table' => [
        '#type' => 'table',
        '#header' => ['Product', 'QA status', 'Last QA run (summary)', 'Features', 'QA page'],
        '#rows' => $qa_rows,
        '#empty' => $this->t('No products visible.'),
      ],
    ];
  }

  /**
   * Infers in-flight release id from active/queued release-cycle item ids.
   *
   * This is intended to resolve "Current release" before the first QA cycle
   * has completed and before release notes/signoff artifacts are available.
   */
  private function inferReleaseIdFromInFlightAgents(array $agents): string {
    $candidates = [];

    foreach ($agents as $a) {
      if (!is_array($a)) {
        continue;
      }

      $tokens = [
        trim((string) ($a['active_item_id'] ?? '')),
        trim((string) ($a['next_item_id'] ?? '')),
      ];

      $action = trim((string) ($a['current_action'] ?? ''));
      if ($action !== '') {
        if (preg_match_all('/\b\d{8}-(?:release-preflight-test-suite|release-ready)-[A-Za-z0-9._-]+\b/', $action, $m)) {
          foreach (($m[0] ?? []) as $tok) {
            $tokens[] = trim((string) $tok);
          }
        }
      }

      foreach ($tokens as $tok) {
        if ($tok === '') {
          continue;
        }
        // Examples:
        // 20260224-release-preflight-test-suite-20260224-coordinated-release
        // 20260224-release-ready-20260224-coordinated-release
        if (preg_match('/^\d{8}-(?:release-preflight-test-suite|release-ready)-(.+)$/', $tok, $m)) {
          $rid = trim((string) ($m[1] ?? ''));
          if ($rid !== '' && preg_match('/^\d{8}-[A-Za-z0-9._-]+$/', $rid)) {
            $candidates[] = $rid;
          }
        }
      }
    }

    if (!$candidates) {
      return '';
    }

    sort($candidates);
    return (string) end($candidates);
  }

  /**
   * Infers release id from QA preflight inbox/outbox artifact naming.
   *
   * Pattern:
   *   <date>-release-preflight-test-suite-<release-id>
   */
  private function inferReleaseIdFromQaPreflightArtifacts(): string {
    $paths = [];

    $inbox = glob('/home/keithaumiller/copilot-sessions-hq/sessions/qa-*/inbox/*release-preflight-test-suite-*') ?: [];
    foreach ($inbox as $p) {
      $paths[] = $p;
    }

    $outbox = glob('/home/keithaumiller/copilot-sessions-hq/sessions/qa-*/outbox/*release-preflight-test-suite-*.md') ?: [];
    foreach ($outbox as $p) {
      $paths[] = $p;
    }

    if (!$paths) {
      return '';
    }

    $best_id = '';
    $best_mtime = 0;
    foreach ($paths as $path) {
      $name = basename($path);
      $name = preg_replace('/\.md$/', '', $name) ?? $name;
      if (!preg_match('/^\d{8}-release-preflight-test-suite-(.+)$/', $name, $m)) {
        continue;
      }
      $rid = trim((string) ($m[1] ?? ''));
      if ($rid === '' || !preg_match('/^\d{8}-[A-Za-z0-9._-]+$/', $rid)) {
        continue;
      }

      $mtime = @filemtime($path);
      if (!is_int($mtime)) {
        $mtime = 0;
      }
      if ($mtime > $best_mtime || ($mtime === $best_mtime && strcmp($rid, $best_id) > 0)) {
        $best_mtime = $mtime;
        $best_id = $rid;
      }
    }

    return $best_id;
  }

  /**
   * Fallback release-id inference from PM release-signoff artifacts in HQ.
   */
  private function inferReleaseIdFromSignoffs(): string {
    $pattern = '/home/keithaumiller/copilot-sessions-hq/sessions/pm-*/artifacts/release-signoffs/*.md';
    $files = glob($pattern) ?: [];
    if (!$files) {
      return '';
    }

    $best_id = '';
    $best_mtime = 0;
    foreach ($files as $path) {
      $mtime = @filemtime($path);
      if (!is_int($mtime)) {
        $mtime = 0;
      }
      $rid = pathinfo($path, PATHINFO_FILENAME);
      $rid = trim((string) $rid);
      if ($rid === '') {
        continue;
      }
      if (!preg_match('/^\d{8}-[A-Za-z0-9._-]+$/', $rid)) {
        continue;
      }
      // Release ids are generally YYYYMMDD-*; accept best-effort fallback too.
      if ($mtime > $best_mtime || ($mtime === $best_mtime && strcmp($rid, $best_id) > 0)) {
        $best_mtime = $mtime;
        $best_id = $rid;
      }
    }

    return $best_id;
  }

  /**
   * Builds a nested accordion view of active work by release stage and product.
   *
   * Render placement requirement (per request): above the agent table, below the
   * rest of the dashboard content.
   */
  private function buildReleaseStageAccordion(array $agents): array {
    $relevant_agents = [];
    foreach ($agents as $a) {
      if (!is_array($a)) {
        continue;
      }
      $status = strtolower(trim((string) ($a['status'] ?? '')));
      if ($status === 'paused') {
        continue;
      }
      $inbox_count = (int) ($a['inbox_count'] ?? 0);
      $role = trim((string) ($a['role'] ?? ''));
      $agent_id = trim((string) ($a['agent_id'] ?? ''));
      $meta = (!empty($a['meta']) && is_array($a['meta'])) ? $a['meta'] : [];
      $stage3_velocity = (!empty($meta['stage3_velocity']) && is_array($meta['stage3_velocity'])) ? $meta['stage3_velocity'] : [];
      $latest_open_issues = (int) ($stage3_velocity['latest_open_issues'] ?? 0);
      $is_dev_seat = ($role === 'software-developer' || str_starts_with($agent_id, 'dev-'));
      $has_open_issues = ($is_dev_seat && $latest_open_issues > 0);

      if ($status === 'in_progress' || $inbox_count > 0 || $status === 'blocked' || $status === 'needs-info' || $has_open_issues) {
        $a['stage3_latest_open_issues'] = $latest_open_issues;
        $a['stage3_resolved_per_15'] = (float) ($stage3_velocity['resolved_per_15_minutes'] ?? 0);
        $a['stage3_handoff_signal'] = trim((string) ($stage3_velocity['workflow']['handoff_signal'] ?? ''));
        $relevant_agents[] = $a;
      }
    }

    $stages = [
      0 => 'Stage 0 — Start of cycle (scope freeze + suite readiness)',
      1 => 'Stage 1 — Intake (backlog; next cycle once frozen)',
      2 => 'Stage 2 — Triage / routing / dedupe',
      3 => 'Stage 3 — Execution (implementation)',
      4 => 'Stage 4 — Verification (QA regression loop)',
      5 => 'Stage 5 — Release candidate assembly',
      6 => 'Stage 6 — Signoff (coordinated release)',
      7 => 'Stage 7 — Ship',
      8 => 'Stage 8 — Post-release QA (production)',
      9 => 'Stage 9 — Continuous improvement',
    ];

    $by_stage_product = [];
    foreach ($relevant_agents as $a) {
      $stage = $this->inferReleaseStage($a);
      $website = trim((string) ($a['website'] ?? ''));
      $module = trim((string) ($a['module'] ?? ''));
      $product_key = $website . '::' . $module;
      if (!isset($by_stage_product[$stage])) {
        $by_stage_product[$stage] = [];
      }
      if (!isset($by_stage_product[$stage][$product_key])) {
        $by_stage_product[$stage][$product_key] = [
          'website' => $website,
          'module' => $module,
          'agents' => [],
        ];
      }
      $by_stage_product[$stage][$product_key]['agents'][] = $a;
    }

    // Infer "current" stage from the work distribution.
    // Priority: most active agents, then blocked, then queued.
    $current_stage_id = 0;
    $best_score = -1;
    foreach (array_keys($stages) as $sid) {
      $products = $by_stage_product[$sid] ?? [];
      $active = 0;
      $queued = 0;
      $blocked = 0;
      $open_issue_idle = 0;
      foreach ($products as $p) {
        $agents_in_product = (is_array($p['agents'] ?? NULL)) ? $p['agents'] : [];
        foreach ($agents_in_product as $a) {
          if (!is_array($a)) {
            continue;
          }
          $s = strtolower(trim((string) ($a['status'] ?? '')));
          $c = (int) ($a['inbox_count'] ?? 0);
          if ($s === 'in_progress') {
            $active++;
          }
          elseif ($s === 'blocked' || $s === 'needs-info') {
            $blocked++;
          }
          elseif ($c > 0) {
            $queued++;
          }
          elseif ($s === 'idle' && (int) ($a['stage3_latest_open_issues'] ?? 0) > 0) {
            $open_issue_idle++;
          }
        }
      }
      $score = ($active * 1000000) + ($blocked * 10000) + ($open_issue_idle * 100) + $queued;
      if ($score > $best_score) {
        $best_score = $score;
        $current_stage_id = (int) $sid;
      }
    }

    $build = [
      '#type' => 'container',
      'title' => [
        '#markup' => '<h3>Release stage (active work, grouped by product)</h3>',
      ],
    ];

    if (!$relevant_agents) {
      $build['empty'] = [
        '#markup' => '<em>No active, queued, or blocked work is currently visible.</em>',
      ];
      return $build;
    }

    foreach ($stages as $stage_id => $stage_title) {
      $products = $by_stage_product[$stage_id] ?? [];
      $agent_count = 0;
      $active_count = 0;
      $queued_count = 0;
      $blocked_count = 0;
      $open_issue_idle_count = 0;
      foreach ($products as $p) {
        $agents_in_product = (is_array($p['agents'] ?? NULL)) ? $p['agents'] : [];
        $agent_count += count($agents_in_product);
        foreach ($agents_in_product as $a) {
          if (!is_array($a)) {
            continue;
          }
          $s = strtolower(trim((string) ($a['status'] ?? '')));
          $c = (int) ($a['inbox_count'] ?? 0);
          if ($s === 'in_progress') {
            $active_count++;
          }
          elseif ($s === 'blocked' || $s === 'needs-info') {
            $blocked_count++;
          }
          elseif ($c > 0) {
            $queued_count++;
          }
          elseif ($s === 'idle' && (int) ($a['stage3_latest_open_issues'] ?? 0) > 0) {
            $open_issue_idle_count++;
          }
        }
      }
      $product_count = count($products);
      $title = $stage_title
        . ' (' . (string) $product_count . ' product' . ($product_count === 1 ? '' : 's')
        . ' — ' . (string) $active_count . ' active, ' . (string) $queued_count . ' queued, ' . (string) $blocked_count . ' blocked, ' . (string) $open_issue_idle_count . ' idle-open-issues)';

      if ((int) $stage_id === (int) $current_stage_id) {
        $title = 'CURRENT → ' . $title;
      }

      $stage_build = [
        '#type' => 'details',
        '#title' => $this->t('@t', ['@t' => $title]),
        '#open' => ((int) $stage_id === (int) $current_stage_id),
      ];

      if (!$products) {
        $stage_build['empty'] = [
          '#markup' => '<em>No active, queued, or blocked work inferred for this stage.</em>',
        ];
        $build['stage_' . (string) $stage_id] = $stage_build;
        continue;
      }

      // Stable ordering.
      ksort($products);
      foreach ($products as $product_key => $p) {
        $website = (string) ($p['website'] ?? '');
        $module = (string) ($p['module'] ?? '');
        $label = ($website ?: '-') . ' / ' . ($module ?: '-') . ' (' . (string) count($p['agents']) . ')';

        $items = [];
        foreach (($p['agents'] ?? []) as $a) {
          if (!is_array($a)) {
            continue;
          }
          $agent_id = (string) ($a['agent_id'] ?? '');
          $active_item_id = trim((string) ($a['active_item_id'] ?? ''));
          $next_item_id = trim((string) ($a['next_item_id'] ?? ''));
          $next_inbox_roi = (int) ($a['next_inbox_roi'] ?? 0);
          $inbox_count = (int) ($a['inbox_count'] ?? 0);
          $status = strtolower(trim((string) ($a['status'] ?? '')));
          $current_action = trim((string) ($a['current_action'] ?? ''));
          $role = trim((string) ($a['role'] ?? ''));
          $is_dev_row = ($role === 'software-developer' || str_starts_with($agent_id, 'dev-'));

          $agent_link = Link::fromTextAndUrl($agent_id, Url::fromRoute('copilot_agent_tracker.agent', ['agent_id' => $agent_id]))->toString();
          $parts = [$agent_link];

          if ($status !== '') {
            $parts[] = 'Status: ' . htmlspecialchars($status);
          }

          if ($status === 'in_progress' && $active_item_id !== '') {
            $item_link = Link::fromTextAndUrl(
              $active_item_id,
              Url::fromRoute('copilot_agent_tracker.agent_inbox_item', ['agent_id' => $agent_id, 'item_id' => $active_item_id])
            )->toString();
            $parts[] = 'Active: ' . $item_link;
          }
          elseif ($next_item_id !== '') {
            $item_link = Link::fromTextAndUrl(
              $next_item_id,
              Url::fromRoute('copilot_agent_tracker.agent_inbox_item', ['agent_id' => $agent_id, 'item_id' => $next_item_id])
            )->toString();
            $parts[] = 'Next: ' . $item_link;
            if ($next_inbox_roi > 0) {
              $parts[] = 'ROI: ' . (string) $next_inbox_roi;
            }
          }

          if ($inbox_count > 0) {
            $parts[] = 'Inbox: ' . (string) $inbox_count;
          }
          $latest_open_issues = (int) ($a['stage3_latest_open_issues'] ?? 0);
          $resolved_per_15 = (float) ($a['stage3_resolved_per_15'] ?? 0);
          $handoff_signal = trim((string) ($a['stage3_handoff_signal'] ?? ''));
          if ($is_dev_row || $latest_open_issues > 0) {
            $parts[] = 'Open issues: ' . (string) $latest_open_issues;
          }
          if ($is_dev_row || $resolved_per_15 > 0 || $latest_open_issues > 0) {
            $parts[] = 'Resolved/15m: ' . htmlspecialchars((string) $resolved_per_15);
          }
          if ($handoff_signal !== '') {
            $parts[] = 'Handoff: ' . htmlspecialchars($handoff_signal);
          }
          if ($current_action !== '') {
            $parts[] = 'Action: ' . htmlspecialchars($current_action);
          }
          $items[] = Markup::create(implode(' — ', $parts));
        }

        $stage_build['product_' . md5($product_key)] = [
          '#type' => 'details',
          '#title' => $this->t('@t', ['@t' => $label]),
          '#open' => FALSE,
          'items' => [
            '#theme' => 'item_list',
            '#items' => $items ?: [Markup::create('<em>No visible work.</em>')],
          ],
        ];
      }

      $build['stage_' . (string) $stage_id] = $stage_build;
    }

    return $build;
  }

  /**
   * Best-effort inference of release stage for an active agent.
   *
   * Uses role + active inbox item id patterns. This is intentionally simple and
   * uses only already-published tracker fields.
   */
  private function inferReleaseStage(array $a): int {
    $role = trim((string) ($a['role'] ?? ''));
    $agent_id = trim((string) ($a['agent_id'] ?? ''));
    $active_item_id = trim((string) ($a['active_item_id'] ?? ''));
    $current_action = strtolower(trim((string) ($a['current_action'] ?? '')));
    $meta = (!empty($a['meta']) && is_array($a['meta'])) ? $a['meta'] : [];

    if ($active_item_id !== '' && str_contains($active_item_id, 'release-preflight-test-suite')) {
      return 0;
    }

    // If QA is actively auditing production, treat it as post-release QA.
    if (($role === 'tester' || str_starts_with($agent_id, 'qa-')) && !empty($meta['qa_last_audit']) && is_array($meta['qa_last_audit'])) {
      $base = strtolower((string) ($meta['qa_last_audit']['base_url'] ?? ''));
      if (str_starts_with($base, 'https://forseti.life') || str_starts_with($base, 'https://dungeoncrawler.forseti.life')) {
        if (str_contains($current_action, 'audit') || str_contains($current_action, 'qa')) {
          return 8;
        }
      }
    }

    if ($role === 'tester' || str_starts_with($agent_id, 'qa-')) {
      return 4;
    }
    if ($role === 'software-developer' || str_starts_with($agent_id, 'dev-')) {
      return 3;
    }
    if ($role === 'business-analyst' || str_starts_with($agent_id, 'ba-')) {
      return 2;
    }
    if ($role === 'product-manager' || str_starts_with($agent_id, 'pm-')) {
      if (str_contains($current_action, 'signoff') || str_contains($active_item_id, 'signoff')) {
        return 6;
      }
      if (str_contains($current_action, 'ship') || str_contains($current_action, 'push')) {
        return 7;
      }
      return 5;
    }

    return 3;
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
          '#markup' => '<p>This page is driven by HQ release candidate artifacts and is coordinated by the CEO. Pending release candidates should appear here and in <a href="/admin/reports/copilot-agent-tracker#todo-for-keith">the approval queue</a> for human approval.</p>',
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
    catch (\Throwable) {
      return [];
    }
  }

  private function extractInboxItems(array $meta): array {
    $inbox_items = [];

    if (!empty($meta['inbox_items_detail']) && is_array($meta['inbox_items_detail'])) {
      foreach ($meta['inbox_items_detail'] as $it) {
        if (!is_array($it)) {
          continue;
        }
        $iid = trim((string) ($it['item_id'] ?? ''));
        if ($iid === '') {
          continue;
        }
        $inbox_items[$iid] = $it;
      }
      return $inbox_items;
    }

    if (!empty($meta['inbox_items']) && is_array($meta['inbox_items'])) {
      foreach ($meta['inbox_items'] as $iid) {
        $iid = trim((string) $iid);
        if ($iid !== '') {
          $inbox_items[$iid] = ['item_id' => $iid];
        }
      }
    }

    return $inbox_items;
  }

  private function buildQueueRows(string $agent_id, array $inbox_items, string $active_item_id = '', array $outbox_status_by_id = []): array {
    $queue_rows = [];
    foreach ($inbox_items as $iid => $it) {
      $roi = (int) ($it['roi'] ?? 0);
      $eff = (int) ($it['effective_roi'] ?? 0);
      $mtime = (int) ($it['mtime'] ?? 0);
      $preview = (string) ($it['preview'] ?? '');

      $link_html = Link::fromTextAndUrl($iid, Url::fromRoute('copilot_agent_tracker.agent_inbox_item', ['agent_id' => $agent_id, 'item_id' => $iid]))->toString();
      $is_active = ($active_item_id !== '' && $iid === $active_item_id);
      if ($is_active) {
        $link_html = '<strong>' . $link_html . '</strong>';
      }

      $preview_bits = [];
      if ($is_active) {
        $preview_bits[] = 'ACTIVE';
      }
      $known_status = trim((string) ($outbox_status_by_id[$iid] ?? ''));
      if ($known_status !== '') {
        $preview_bits[] = 'Status: ' . $known_status;
      }
      $preview = trim((string) $preview);
      if ($preview !== '') {
        $preview_bits[] = $preview;
      }

      $queue_rows[] = [
        Markup::create($link_html),
        $roi > 0 ? (string) $roi : '-',
        $eff > 0 ? (string) $eff : '-',
        $mtime ? $this->dateFormatter->format($mtime, 'short') : '-',
        $preview_bits ? htmlspecialchars(implode(' — ', $preview_bits)) : '',
      ];
    }
    return $queue_rows;
  }

  private function buildEventRows(array $events): array {
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
    return $event_rows;
  }

  private function buildAgentMetricsItems(array $meta, array $inbox_items): array {
    $metrics_items = [];
    $metrics_items[] = 'Inbox items: ' . (string) ((int) ($meta['inbox_count'] ?? count($inbox_items)));
    $metrics_items[] = 'Next inbox ROI: ' . (string) ((int) ($meta['next_inbox_effective_roi'] ?? ($meta['next_inbox_roi'] ?? 0)));

    $outbox_results = (!empty($meta['outbox_results']) && is_array($meta['outbox_results'])) ? $meta['outbox_results'] : [];
    $counts_7d = (!empty($outbox_results['counts_7d']) && is_array($outbox_results['counts_7d'])) ? $outbox_results['counts_7d'] : [];

    $count_done_7d = (int) ($counts_7d['done'] ?? 0);
    $count_in_progress_7d = (int) ($counts_7d['in_progress'] ?? 0);
    $count_needs_info_7d = (int) ($counts_7d['needs-info'] ?? 0);
    $count_blocked_7d = (int) ($counts_7d['blocked'] ?? 0);
    $count_total_7d = (int) ($counts_7d['total'] ?? 0);
    $count_forwarded_7d = $count_needs_info_7d + $count_blocked_7d;

    $metrics_items[] = 'Results (7d) — completed: ' . (string) $count_done_7d
      . ', forwarded (needs-info+blocked): ' . (string) $count_forwarded_7d
      . ', in_progress: ' . (string) $count_in_progress_7d
      . ', total: ' . (string) $count_total_7d;

    $last_outbox_mtime = (int) ($outbox_results['last_mtime'] ?? 0);
    if ($last_outbox_mtime > 0) {
      $metrics_items[] = 'Last outbox update: ' . $this->dateFormatter->format($last_outbox_mtime, 'short');
    }

    $role_kpis = (!empty($meta['role_kpis']) && is_array($meta['role_kpis'])) ? $meta['role_kpis'] : [];

    $kpi_value = trim((string) ($role_kpis['value'] ?? ''));
    if ($kpi_value !== '') {
      $metrics_items[] = 'Value I add: ' . $kpi_value;
    }

    $kpi_cost = (!empty($role_kpis['cost']) && is_array($role_kpis['cost'])) ? $role_kpis['cost'] : [];
    $kpi_quality = (!empty($role_kpis['quality']) && is_array($role_kpis['quality'])) ? $role_kpis['quality'] : [];
    $kpi_speed = (!empty($role_kpis['speed']) && is_array($role_kpis['speed'])) ? $role_kpis['speed'] : [];

    if ($kpi_cost) {
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
