<?php

namespace Drupal\copilot_agent_tracker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * LangGraph management console — live data wired for Home, Run, Observe.
 *
 * Build, Test, Release, Admin sections remain structural stubs pending
 * their respective feature implementations.
 */
final class LangGraphConsoleStubController extends ControllerBase {

  // Paths relative to COPILOT_HQ_ROOT.
  const TICKS_RELATIVE   = 'inbox/responses/langgraph-ticks.jsonl';
  const PARITY_RELATIVE  = 'inbox/responses/langgraph-parity-latest.json';
  const FEATURE_PROGRESS = 'dashboards/FEATURE_PROGRESS.md';

  // -------------------------------------------------------------------------
  // Data helpers
  // -------------------------------------------------------------------------

  /**
   * Resolve a path under COPILOT_HQ_ROOT.
   */
  private function hqPath(string $relative): string {
    $root = rtrim((string) (getenv('COPILOT_HQ_ROOT') ?: '/home/ubuntu/forseti.life/copilot-hq'), '/');
    return $root . '/' . ltrim($relative, '/');
  }

  /**
   * Read a JSON file safely, returning [] on any failure.
   *
   * @return array<mixed>
   */
  private function readJson(string $path): array {
    if (!is_readable($path)) {
      return [];
    }
    try {
      $raw = (string) file_get_contents($path);
      $decoded = json_decode($raw, TRUE);
      return is_array($decoded) ? $decoded : [];
    }
    catch (\Throwable) {
      return [];
    }
  }

  /**
   * Read the last JSON object from a JSONL file safely.
   *
   * @return array<mixed>
   */
  private function readLastJsonl(string $path): array {
    if (!is_readable($path)) {
      return [];
    }
    try {
      $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
      if (!$lines) {
        return [];
      }
      $decoded = json_decode(trim((string) end($lines)), TRUE);
      return is_array($decoded) ? $decoded : [];
    }
    catch (\Throwable) {
      return [];
    }
  }

  /**
   * Load the last tick and parity data in one call.
   *
   * @return array{tick: array<mixed>, parity: array<mixed>}
   */
  private function loadTelemetry(): array {
    return [
      'tick'   => $this->readLastJsonl($this->hqPath(self::TICKS_RELATIVE)),
      'parity' => $this->readJson($this->hqPath(self::PARITY_RELATIVE)),
    ];
  }

  /**
   * Return a status badge markup string.
   */
  private function badge(bool|null $ok, string $pass = 'PASS', string $fail = 'FAIL'): string {
    if ($ok === NULL) {
      return '<span style="color:#888">UNKNOWN</span>';
    }
    $color = $ok ? '#2e7d32' : '#b71c1c';
    $label = $ok ? $pass : $fail;
    return '<strong style="color:' . $color . '">' . $label . '</strong>';
  }

  /**
   * Format an ISO-8601 timestamp for display.
   */
  private function fmtTs(string $ts): string {
    if ($ts === '') {
      return '—';
    }
    try {
      $dt = new \DateTimeImmutable($ts);
      return $dt->format('Y-m-d H:i:s') . ' UTC';
    }
    catch (\Throwable) {
      return $ts;
    }
  }

  /**
   * Render an rc value as a coloured badge.
   */
  private function rcBadge(int $rc): string {
    return $rc === 0
      ? '<span style="color:#2e7d32">✓ ok</span>'
      : '<span style="color:#b71c1c">✗ rc=' . $rc . '</span>';
  }

  /**
   * Console section definitions and subsection frames.
   *
   * @return array<string,array<string,mixed>>
   *   Section map keyed by section slug.
   */
  private function sectionMap(): array {
    return [
      'home' => [
        'title' => 'LangGraph Console Home',
        'description' => 'Control-plane frame grounded in LangGraph architecture primitives. No live systems are connected.',
        'subsections' => [
          'graph-contract' => ['Graph Contract', 'StateGraph contract frame: state schema, START/END flow, and transition policy placeholders.'],
          'runtime-objects' => ['Runtime Objects', 'Thread, run, checkpoint, and persisted state lifecycle placeholders.'],
          'durability-model' => ['Durability Model', 'Checkpointer/store durability, recovery, and replay boundary placeholders.'],
          'control-gates' => ['Control Gates', 'Human-in-the-loop interrupt/resume and approval gate placeholders.'],
        ],
      ],
      'build' => [
        'title' => 'Build',
        'description' => 'Design-time frame for authoring LangGraph graph topology and node behavior.',
        'subsections' => [
          'state-schema' => ['State Schema', 'Typed state keys/channels and reducer/merge policy placeholders.'],
          'nodes-routing' => ['Nodes & Routing', 'Node definitions, conditional edge routing, and branch policy placeholders.'],
          'subgraphs' => ['Subgraphs', 'Subgraph composition, boundaries, and parent-child state handoff placeholders.'],
          'tool-calling' => ['Tool Calling', 'Tool invocation contracts and structured tool-result handling placeholders.'],
          'prompts-policies' => ['Prompts & Policies', 'Prompt templates, guardrails, and policy attachment placeholders.'],
        ],
      ],
      'test' => [
        'title' => 'Test',
        'description' => 'Validation frame for correctness, determinism, and safety before promotion.',
        'subsections' => [
          'path-scenarios' => ['Path Scenarios', 'Golden-path and branch/edge-path scenario placeholders across graph routes.'],
          'checkpoint-replay' => ['Checkpoint Replay', 'Replay/time-travel and deterministic resume behavior placeholders.'],
          'eval-scorecards' => ['Eval Scorecards', 'Task success, hallucination, and tool-accuracy score placeholders.'],
          'safety-gates' => ['Safety Gates', 'Policy gate outcomes and block reason placeholders pre-release.'],
        ],
      ],
      'run' => [
        'title' => 'Run',
        'description' => 'Execution-plane frame for live LangGraph runtime operations.',
        'subsections' => [
          'threads-runs' => ['Threads & Runs', 'Thread/run registry placeholders with state and terminal status markers.'],
          'stream-events' => ['Stream Events', 'Streaming token/event timeline placeholders for active runs.'],
          'resume-retry' => ['Resume & Retry', 'Interrupt resume and failed-run retry control placeholders.'],
          'concurrency' => ['Concurrency', 'Run parallelism, queue depth, and worker-capacity placeholders.'],
        ],
      ],
      'observe' => [
        'title' => 'Observe',
        'description' => 'Observability frame for graph execution internals and runtime health.',
        'subsections' => [
          'node-traces' => ['Node Traces', 'Node-level path, state diff, and edge decision trace placeholders.'],
          'runtime-metrics' => ['Runtime Metrics', 'Latency, failure, token, and cost metric placeholders by graph/node.'],
          'drift-anomalies' => ['Drift & Anomalies', 'Behavior drift and anomalous route frequency placeholders.'],
          'alerts-incidents' => ['Alerts & Incidents', 'Threshold alert and incident timeline placeholders.'],
        ],
      ],
      'release' => [
        'title' => 'Release',
        'description' => 'Promotion-plane frame for graph version rollout and rollback.',
        'subsections' => [
          'graph-versions' => ['Graph Versions', 'Graph artifact/version inventory and compatibility placeholders.'],
          'promotion-flow' => ['Promotion Flow', 'Dev→staging→prod promotion gate placeholders.'],
          'canary-controls' => ['Canary Controls', 'Traffic-split/canary rollout placeholder controls.'],
          'rollback-recovery' => ['Rollback & Recovery', 'Fast rollback and checkpoint recovery placeholders.'],
        ],
      ],
      'admin' => [
        'title' => 'Admin',
        'description' => 'Governance frame for runtime policy, security, and platform controls.',
        'subsections' => [
          'identity-rbac' => ['Identity & RBAC', 'Role policy and environment scope placeholders.'],
          'secrets-connectors' => ['Secrets & Connectors', 'Provider secrets and connector lifecycle placeholders.'],
          'retention-compliance' => ['Retention & Compliance', 'State retention, redaction, and compliance control placeholders.'],
          'budgets-quotas' => ['Budgets & Quotas', 'Token/cost budget and quota policy placeholders.'],
          'audit-change-log' => ['Audit Change Log', 'Immutable change/audit event placeholders.'],
        ],
      ],
    ];
  }

  // -------------------------------------------------------------------------
  // Section pages
  // -------------------------------------------------------------------------

  /**
   * Console home — live orchestrator health summary.
   */
  public function home(): array {
    ['tick' => $tick, 'parity' => $parity] = $this->loadTelemetry();

    $ts           = (string) ($tick['ts'] ?? '');
    $dry_run      = isset($tick['dry_run']) ? (bool) $tick['dry_run'] : NULL;
    $provider     = (string) ($tick['provider'] ?? '—');
    $agent_cap    = isset($tick['agent_cap']) ? (int) $tick['agent_cap'] : '—';
    $parity_ok    = isset($parity['parity_ok']) ? (bool) $parity['parity_ok'] : NULL;
    $steps_match  = isset($parity['steps']['match']) ? (bool) $parity['steps']['match'] : NULL;
    $agents_match = isset($parity['selected_agents']['match']) ? (bool) $parity['selected_agents']['match'] : NULL;
    $errors       = array_merge((array) ($tick['errors'] ?? []), (array) ($parity['errors'] ?? []));
    $exec_ran     = (array) ($tick['step_results']['exec_agents']['ran'] ?? []);
    $selected     = (array) ($tick['step_results']['pick_agents']['selected'] ?? $tick['selected_agents'] ?? []);

    $summary_rows = [
      [$this->t('Last tick'), $this->fmtTs($ts), ''],
      [$this->t('Provider'), $provider, ''],
      [$this->t('Mode'), $dry_run === NULL ? '—' : ($dry_run ? 'dry-run' : 'live'), ''],
      [$this->t('Agent cap'), (string) $agent_cap, ''],
      [$this->t('Agents executed'), (string) count($exec_ran), ''],
      [$this->t('Parity'), ['data' => ['#markup' => $this->badge($parity_ok)]], ''],
      [$this->t('Pipeline steps match'), ['data' => ['#markup' => $this->badge($steps_match)]], ''],
      [$this->t('Agent selection match'), ['data' => ['#markup' => $this->badge($agents_match)]], ''],
      [$this->t('Errors'), (string) count($errors), count($errors) > 0 ? implode('; ', array_map('strval', $errors)) : 'none'],
    ];

    $agent_rows = [];
    foreach ($exec_ran as $entry) {
      $agent = (string) ($entry['agent'] ?? '?');
      $rc    = isset($entry['rc']) ? (int) $entry['rc'] : -1;
      $agent_rows[] = [$agent, ['data' => ['#markup' => $this->rcBadge($rc)]]];
    }

    $sections = $this->sectionMap();
    $section_nav = $this->buildSectionRows('home', (array) $sections['home']['subsections']);

    return [
      '#type' => 'container',
      '#cache' => ['max-age' => 0],
      'title' => ['#markup' => '<h2>' . $this->t('LangGraph Console') . '</h2>'],
      'summary_header' => ['#markup' => '<h3>' . $this->t('Orchestrator Health') . '</h3>'],
      'summary' => [
        '#type' => 'table',
        '#header' => [$this->t('Metric'), $this->t('Value'), $this->t('Notes')],
        '#rows' => $summary_rows,
        '#empty' => $this->t('No tick data available.'),
      ],
      'agents_header' => ['#markup' => '<h3>' . $this->t('Last Tick: Agent Execution') . '</h3>'],
      'agents_table' => [
        '#type' => 'table',
        '#header' => [$this->t('Agent'), $this->t('Result')],
        '#rows' => $agent_rows,
        '#empty' => $this->t('No exec data.'),
      ],
      'nav' => [
        '#type' => 'details',
        '#title' => $this->t('Subsections'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Subsection'), $this->t('Frame'), $this->t('Status')],
          '#rows' => $section_nav,
        ],
      ],
    ];
  }

  /**
   * Build page.
   */
  public function build(): array {
    $sections = $this->sectionMap();
    $page = $sections['build'];
    return $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('build', (array) $page['subsections'])
    );
  }

  /**
   * Test page.
   */
  public function test(): array {
    $sections = $this->sectionMap();
    $page = $sections['test'];
    return $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('test', (array) $page['subsections'])
    );
  }

  /**
   * Run page — live runtime operations panel.
   */
  public function run(): array {
    ['tick' => $tick, 'parity' => $parity] = $this->loadTelemetry();

    $ts        = (string) ($tick['ts'] ?? '');
    $exec_ran  = (array) ($tick['step_results']['exec_agents']['ran'] ?? []);
    $teams     = (array) ($tick['step_results']['release_cycle']['teams'] ?? []);
    $push      = (array) ($tick['step_results']['coordinated_push'] ?? []);
    $pick      = (array) ($tick['step_results']['pick_agents'] ?? []);
    $selected  = (array) ($pick['selected'] ?? []);
    $agent_cap = isset($tick['agent_cap']) ? (int) $tick['agent_cap'] : 0;
    $health    = (array) ($tick['step_results']['health_check'] ?? []);

    // Agents table.
    $agent_rows = [];
    foreach ($exec_ran as $entry) {
      $agent = (string) ($entry['agent'] ?? '?');
      $rc    = isset($entry['rc']) ? (int) $entry['rc'] : -1;
      $agent_rows[] = [$agent, ['data' => ['#markup' => $this->rcBadge($rc)]]];
    }

    // Release teams table.
    $team_rows = [];
    foreach ($teams as $team_entry) {
      $team_rows[] = [
        (string) ($team_entry['team'] ?? '?'),
        (string) ($team_entry['action'] ?? '—'),
        (string) ($team_entry['current'] ?? '—'),
        (string) ($team_entry['next'] ?? '—'),
        ['data' => ['#markup' => $this->rcBadge(isset($team_entry['rc']) ? (int) $team_entry['rc'] : 0)]],
      ];
    }

    // Concurrency row.
    $push_status     = (string) ($push['status'] ?? '—');
    $not_ready       = implode(', ', array_map('strval', (array) ($push['not_ready'] ?? [])));
    $release_pri     = implode(', ', array_map('strval', (array) ($pick['release_priority'] ?? [])));
    $idle_with_inbox = isset($health['idle_with_inbox']) ? (int) $health['idle_with_inbox'] : '—';
    $blocked         = isset($health['blocked_count']) ? (int) $health['blocked_count'] : '—';
    $remediated      = (array) ($health['remediated'] ?? []);

    $sections = $this->sectionMap();
    $nav = $this->buildSectionRows('run', (array) $sections['run']['subsections']);

    return [
      '#type' => 'container',
      '#cache' => ['max-age' => 0],
      'title' => ['#markup' => '<h2>' . $this->t('Run') . '</h2>'],
      'ts_note' => ['#markup' => '<p><em>' . $this->t('Last tick: @ts', ['@ts' => $this->fmtTs($ts)]) . '</em></p>'],

      'agents_header' => ['#markup' => '<h3>' . $this->t('Threads & Runs — Agent Execution') . '</h3>'],
      'agents_table' => [
        '#type' => 'table',
        '#header' => [$this->t('Agent'), $this->t('Exit')],
        '#rows' => $agent_rows,
        '#empty' => $this->t('No execution data.'),
      ],

      'teams_header' => ['#markup' => '<h3>' . $this->t('Release Cycle — Active Teams') . '</h3>'],
      'teams_table' => [
        '#type' => 'table',
        '#header' => [$this->t('Team'), $this->t('Action'), $this->t('Current Release'), $this->t('Next Release'), $this->t('RC')],
        '#rows' => $team_rows,
        '#empty' => $this->t('No release cycle data.'),
      ],

      'push_header' => ['#markup' => '<h3>' . $this->t('Coordinated Push') . '</h3>'],
      'push_table' => [
        '#type' => 'table',
        '#header' => [$this->t('Metric'), $this->t('Value')],
        '#rows' => [
          [$this->t('Push status'), $push_status],
          [$this->t('Teams not ready'), $not_ready ?: '—'],
          [$this->t('Release priority agents'), $release_pri ?: '—'],
        ],
      ],

      'health_header' => ['#markup' => '<h3>' . $this->t('Health & Resume') . '</h3>'],
      'health_table' => [
        '#type' => 'table',
        '#header' => [$this->t('Metric'), $this->t('Value')],
        '#rows' => [
          [$this->t('Idle agents with inbox'), (string) $idle_with_inbox],
          [$this->t('Blocked agents'), (string) $blocked],
          [$this->t('Remediated this tick'), (string) count($remediated)],
          [$this->t('Agent cap'), (string) $agent_cap],
          [$this->t('Agents selected'), (string) count($selected) . ' (' . implode(', ', array_map('strval', $selected)) . ')'],
        ],
      ],

      'nav' => [
        '#type' => 'details',
        '#title' => $this->t('Subsections'),
        '#open' => FALSE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Subsection'), $this->t('Frame'), $this->t('Status')],
          '#rows' => $nav,
        ],
      ],
    ];
  }

  /**
   * Observe page.
   */
  public function observe(): array {
    $sections = $this->sectionMap();
    $page = $sections['observe'];
    return $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('observe', (array) $page['subsections'])
    );
  }

  /**
   * Release page.
   */
  public function release(): array {
    $sections = $this->sectionMap();
    $page = $sections['release'];
    return $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('release', (array) $page['subsections'])
    );
  }

  /**
   * Admin page.
   */
  public function admin(): array {
    $sections = $this->sectionMap();
    $page = $sections['admin'];
    return $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('admin', (array) $page['subsections'])
    );
  }

  /**
   * Generic subsection page.
   */
  public function subsection(string $section, string $subsection): array {
    $map = $this->sectionMap();
    $section_info = $map[$section] ?? NULL;
    if (!is_array($section_info)) {
      throw new NotFoundHttpException();
    }

    $subsections = (array) ($section_info['subsections'] ?? []);
    $sub_info = $subsections[$subsection] ?? NULL;
    if (!is_array($sub_info) || count($sub_info) < 2) {
      throw new NotFoundHttpException();
    }

    $sub_title = (string) $sub_info[0];
    $sub_desc = (string) $sub_info[1];

    return [
      '#type' => 'container',
      '#cache' => ['max-age' => 0],
      'title' => [
        '#markup' => '<h2>' . $this->t('@section: @subsection', [
          '@section' => (string) ($section_info['title'] ?? ''),
          '@subsection' => $sub_title,
        ]) . '</h2>',
      ],
      'description' => [
        '#markup' => '<p>' . $this->t($sub_desc) . '</p>',
      ],
      'notice' => [
        '#markup' => '<div class="messages messages--status"><strong>' . $this->t('Stub Subsection') . ':</strong> ' . $this->t('This is a structural frame only. No workflows or data are connected.') . '</div>',
      ],
      'back' => [
        '#markup' => '<p>' . Link::fromTextAndUrl(
          $this->t('Back to @section', ['@section' => (string) ($section_info['title'] ?? '')]),
          Url::fromRoute('copilot_agent_tracker.langgraph_console_' . $section)
        )->toString() . '</p>',
      ],
      'layout_frames' => [
        '#type' => 'details',
        '#title' => $this->t('Subsection Frame'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Frame Area'), $this->t('Placeholder')],
          '#rows' => [
            [$this->t('Scope controls'), $this->t('Graph, thread, run, and environment selectors placeholder.')],
            [$this->t('Execution panel'), $this->t('Node path, state snapshot, or lifecycle timeline placeholder.')],
            [$this->t('Checkpoint/context panel'), $this->t('Checkpoint state, resume context, and metadata placeholder.')],
            [$this->t('Control rail'), $this->t('Interrupt/resume/retry/promote action placeholders.')],
          ],
        ],
      ],
    ];
  }

  /**
   * Convert subsection map into row definitions with deep links.
   *
   * @param array<string,array<int,string>> $subsections
   *   Subsection map keyed by slug.
   *
   * @return array<int,array<int|string,mixed>>
   *   Table rows.
   */
  private function buildSectionRows(string $section, array $subsections): array {
    $rows = [];
    foreach ($subsections as $slug => $info) {
      $title = (string) ($info[0] ?? '');
      $desc = (string) ($info[1] ?? '');
      $rows[] = [
        Link::fromTextAndUrl(
          $this->t($title),
          Url::fromRoute('copilot_agent_tracker.langgraph_console_subsection', [
            'section' => $section,
            'subsection' => (string) $slug,
          ])
        )->toString(),
        $desc,
        $this->t('Stub'),
      ];
    }
    return $rows;
  }

  /**
   * Build a static stub page with consistent navigation and subsection frames.
   */
  private function buildPage(string $title, string $description, array $sections): array {
    return [
      '#type' => 'container',
      '#cache' => ['max-age' => 0],
      'title' => [
        '#markup' => '<h2>' . $this->t($title) . '</h2>',
      ],
      'description' => [
        '#markup' => '<p>' . $this->t($description) . '</p>',
      ],
      'notice' => [
        '#markup' => '<div class="messages messages--status"><strong>' . $this->t('Stub Console') . ':</strong> ' . $this->t('Navigation and layout only. Data integrations are intentionally not connected.') . '</div>',
      ],
      'sections' => [
        '#type' => 'details',
        '#title' => $this->t('Subsections'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Subsection'), $this->t('Frame'), $this->t('Status')],
          '#rows' => $sections,
        ],
      ],
      'wireframe' => [
        '#type' => 'details',
        '#title' => $this->t('Page Frame'),
        '#open' => FALSE,
        'content' => [
          '#markup' => '<p>' . $this->t('Reserved LangGraph frame areas: graph/thread scope controls, run-state summary strip, node/state panel, and control actions rail.') . '</p>',
        ],
      ],
    ];
  }

}
