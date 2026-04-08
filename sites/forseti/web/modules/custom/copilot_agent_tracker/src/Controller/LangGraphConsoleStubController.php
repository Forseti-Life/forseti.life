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
   * Observe page — live observability panel.
   */
  public function observe(): array {
    ['tick' => $tick, 'parity' => $parity] = $this->loadTelemetry();

    $ts          = (string) ($tick['ts'] ?? '');
    $step_res    = (array) ($tick['step_results'] ?? []);
    $errors      = (array) ($tick['errors'] ?? []);
    $parity_ok   = isset($parity['parity_ok']) ? (bool) $parity['parity_ok'] : NULL;
    $par_errors  = (array) ($parity['errors'] ?? []);
    $exec_ran    = (array) ($step_res['exec_agents']['ran'] ?? []);

    // Node trace: iterate step_results in pipeline order.
    $trace_rows = [];
    foreach ($step_res as $step_name => $step_data) {
      $data_str = is_array($step_data)
        ? json_encode($step_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        : (string) $step_data;
      $rc = isset($step_data['rc']) ? (int) $step_data['rc'] : NULL;
      $rc_cell = $rc !== NULL
        ? ['data' => ['#markup' => $this->rcBadge($rc)]]
        : ['data' => ['#markup' => '<span style="color:#888">—</span>']];
      $trace_rows[] = [$step_name, $rc_cell, '<code>' . htmlspecialchars((string) $data_str) . '</code>'];
    }

    // Runtime metrics.
    $total_agents = count($exec_ran);
    $ok_agents    = count(array_filter($exec_ran, fn($e) => (int) ($e['rc'] ?? -1) === 0));
    $fail_agents  = $total_agents - $ok_agents;
    $metric_rows  = [
      [$this->t('Last tick'), $this->fmtTs($ts)],
      [$this->t('Pipeline steps'), (string) count($step_res)],
      [$this->t('Agents executed'), (string) $total_agents],
      [$this->t('Agents ok / failed'), "$ok_agents / $fail_agents"],
      [$this->t('Tick errors'), (string) count($errors)],
      [$this->t('Parity'), ['data' => ['#markup' => $this->badge($parity_ok)]]],
    ];

    // Parity diff: expected vs actual steps.
    $exp_steps = (array) ($parity['steps']['expected'] ?? []);
    $act_steps = (array) ($parity['steps']['actual'] ?? []);
    $all_steps = array_unique(array_merge($exp_steps, $act_steps));
    $diff_rows = [];
    foreach ($all_steps as $step) {
      $in_exp   = in_array($step, $exp_steps, TRUE);
      $in_act   = in_array($step, $act_steps, TRUE);
      $status   = $in_exp && $in_act ? '<span style="color:#2e7d32">✓ match</span>' : ($in_exp ? '<span style="color:#b71c1c">missing in actual</span>' : '<span style="color:#e65100">extra in actual</span>');
      $diff_rows[] = [(string) $step, ['data' => ['#markup' => $status]]];
    }

    // Alerts: combine tick errors + parity errors.
    $alert_rows = [];
    foreach ($errors as $err) {
      $alert_rows[] = ['tick', (string) $err];
    }
    foreach ($par_errors as $err) {
      $alert_rows[] = ['parity', (string) $err];
    }

    $sections = $this->sectionMap();
    $nav = $this->buildSectionRows('observe', (array) $sections['observe']['subsections']);

    return [
      '#type' => 'container',
      '#cache' => ['max-age' => 0],
      'title' => ['#markup' => '<h2>' . $this->t('Observe') . '</h2>'],
      'ts_note' => ['#markup' => '<p><em>' . $this->t('Last tick: @ts', ['@ts' => $this->fmtTs($ts)]) . '</em></p>'],

      'metrics_header' => ['#markup' => '<h3>' . $this->t('Runtime Metrics') . '</h3>'],
      'metrics_table' => [
        '#type' => 'table',
        '#header' => [$this->t('Metric'), $this->t('Value')],
        '#rows' => $metric_rows,
      ],

      'trace_header' => ['#markup' => '<h3>' . $this->t('Node Trace — Pipeline Step Results') . '</h3>'],
      'trace_table' => [
        '#type' => 'table',
        '#header' => [$this->t('Step'), $this->t('RC'), $this->t('Data')],
        '#rows' => $trace_rows,
        '#empty' => $this->t('No step data.'),
      ],

      'parity_header' => ['#markup' => '<h3>' . $this->t('Drift & Parity — Pipeline Steps') . '</h3>'],
      'parity_table' => [
        '#type' => 'table',
        '#header' => [$this->t('Step'), $this->t('Status')],
        '#rows' => $diff_rows,
        '#empty' => $this->t('No parity data.'),
      ],

      'alerts_header' => ['#markup' => '<h3>' . $this->t('Alerts & Errors') . '</h3>'],
      'alerts_table' => [
        '#type' => 'table',
        '#header' => [$this->t('Source'), $this->t('Error')],
        '#rows' => $alert_rows,
        '#empty' => $this->t('No errors.'),
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
   * Generic subsection page — routes to live data for wired panels, stubs otherwise.
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

    $back = [
      '#markup' => '<p>' . Link::fromTextAndUrl(
        $this->t('← Back to @section', ['@section' => (string) ($section_info['title'] ?? '')]),
        Url::fromRoute('copilot_agent_tracker.langgraph_console_' . $section)
      )->toString() . '</p>',
    ];

    // Route to live implementations.
    $key = $section . '/' . $subsection;
    return match ($key) {
      'home/graph-contract'   => $this->subHomeGraphContract($sub_info, $back),
      'home/runtime-objects'  => $this->subHomeRuntimeObjects($sub_info, $back),
      'home/durability-model' => $this->subHomeDurabilityModel($sub_info, $back),
      'home/control-gates'    => $this->subHomeControlGates($sub_info, $back),
      'run/threads-runs'      => $this->subRunThreadsRuns($sub_info, $back),
      'run/stream-events'     => $this->subRunStreamEvents($sub_info, $back),
      'run/resume-retry'      => $this->subRunResumeRetry($sub_info, $back),
      'run/concurrency'       => $this->subRunConcurrency($sub_info, $back),
      'observe/node-traces'   => $this->subObserveNodeTraces($sub_info, $back),
      'observe/runtime-metrics' => $this->subObserveRuntimeMetrics($sub_info, $back),
      'observe/drift-anomalies' => $this->subObserveDriftAnomalies($sub_info, $back),
      'observe/alerts-incidents' => $this->subObserveAlertsIncidents($sub_info, $back),
      default                 => $this->buildStubSubsection($section_info, $sub_info, $back),
    };
  }

  // -------------------------------------------------------------------------
  // Home subsections (live)
  // -------------------------------------------------------------------------

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subHomeGraphContract(array $sub, array $back): array {
    ['parity' => $parity] = $this->loadTelemetry();
    $exp = (array) ($parity['steps']['expected'] ?? []);
    $act = (array) ($parity['steps']['actual'] ?? []);
    $rows = [];
    foreach (array_unique(array_merge($exp, $act)) as $i => $step) {
      $in_e = in_array($step, $exp, TRUE);
      $in_a = in_array($step, $act, TRUE);
      $rows[] = [
        (string) ($i + 1),
        (string) $step,
        ['data' => ['#markup' => $in_e ? '✓' : '—']],
        ['data' => ['#markup' => $in_a ? '✓' : '<span style="color:#e65100">missing</span>']],
      ];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'table' => [
        '#type' => 'table',
        '#caption' => $this->t('LangGraph pipeline step contract (from parity report)'),
        '#header' => [$this->t('#'), $this->t('Step'), $this->t('Expected'), $this->t('Actual')],
        '#rows' => $rows,
        '#empty' => $this->t('No parity data.'),
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subHomeRuntimeObjects(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $teams = (array) ($tick['step_results']['release_cycle']['teams'] ?? []);
    $exec  = (array) ($tick['step_results']['exec_agents']['ran'] ?? []);
    $rows  = [];
    foreach ($teams as $t) {
      $rows[] = [
        'Thread',
        (string) ($t['team'] ?? '?'),
        (string) ($t['current'] ?? '—'),
        (string) ($t['action'] ?? '—'),
      ];
    }
    foreach ($exec as $e) {
      $rows[] = [
        'Run',
        (string) ($e['agent'] ?? '?'),
        '—',
        ['data' => ['#markup' => $this->rcBadge(isset($e['rc']) ? (int) $e['rc'] : -1)]],
      ];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'table' => [
        '#type' => 'table',
        '#caption' => $this->t('Active threads (release teams) and runs (agent executions) from last tick'),
        '#header' => [$this->t('Type'), $this->t('ID'), $this->t('State'), $this->t('Status')],
        '#rows' => $rows,
        '#empty' => $this->t('No data.'),
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subHomeDurabilityModel(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $ts    = (string) ($tick['ts'] ?? '');
    $push  = (array) ($tick['step_results']['coordinated_push'] ?? []);
    $hc    = (array) ($tick['step_results']['health_check'] ?? []);
    $rows  = [
      [$this->t('Last successful tick'), $this->fmtTs($ts)],
      [$this->t('Coordinated push status'), (string) ($push['status'] ?? '—')],
      [$this->t('Idle agents with work'), (string) ($hc['idle_with_inbox'] ?? '—')],
      [$this->t('Blocked agents'), (string) ($hc['blocked_count'] ?? '—')],
      [$this->t('Remediated this tick'), (string) count((array) ($hc['remediated'] ?? []))],
    ];
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Property'), $this->t('Value')],
        '#rows' => $rows,
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subHomeControlGates(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $dry_run = isset($tick['dry_run']) ? (bool) $tick['dry_run'] : NULL;
    $pub_en  = isset($tick['publish_enabled']) ? (bool) $tick['publish_enabled'] : NULL;
    $cap     = isset($tick['agent_cap']) ? (int) $tick['agent_cap'] : NULL;
    $rows    = [
      [$this->t('Mode'), $dry_run === NULL ? '—' : ($dry_run ? 'dry-run (no writes)' : 'live')],
      [$this->t('Publish enabled'), $pub_en === NULL ? '—' : ($pub_en ? 'yes' : 'no')],
      [$this->t('Agent cap (max agents/tick)'), $cap !== NULL ? (string) $cap : '—'],
    ];
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'table' => [
        '#type' => 'table',
        '#caption' => $this->t('Orchestrator control gate values from last tick'),
        '#header' => [$this->t('Gate'), $this->t('Value')],
        '#rows' => $rows,
      ],
    ]);
  }

  // -------------------------------------------------------------------------
  // Run subsections (live)
  // -------------------------------------------------------------------------

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subRunThreadsRuns(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $exec  = (array) ($tick['step_results']['exec_agents']['ran'] ?? []);
    $teams = (array) ($tick['step_results']['release_cycle']['teams'] ?? []);
    $rows  = [];
    foreach ($teams as $t) {
      $rows[] = ['Team', (string) ($t['team'] ?? '?'), (string) ($t['current'] ?? '—'), (string) ($t['action'] ?? '—')];
    }
    foreach ($exec as $e) {
      $rc = isset($e['rc']) ? (int) $e['rc'] : -1;
      $rows[] = ['Agent', (string) ($e['agent'] ?? '?'), '—', ['data' => ['#markup' => $this->rcBadge($rc)]]];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Type'), $this->t('ID'), $this->t('Release / State'), $this->t('Status')],
        '#rows' => $rows,
        '#empty' => $this->t('No data.'),
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subRunStreamEvents(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $steps = (array) ($tick['step_results'] ?? []);
    $ts    = (string) ($tick['ts'] ?? '');
    $rows  = [];
    $i     = 1;
    foreach ($steps as $name => $data) {
      $rc = isset($data['rc']) ? $this->rcBadge((int) $data['rc']) : '—';
      $rows[] = [
        (string) $i++,
        (string) $name,
        ['data' => ['#markup' => $rc]],
        $this->fmtTs($ts),
      ];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'table' => [
        '#type' => 'table',
        '#caption' => $this->t('Pipeline step execution events for the last tick'),
        '#header' => [$this->t('Seq'), $this->t('Step'), $this->t('RC'), $this->t('Tick timestamp')],
        '#rows' => $rows,
        '#empty' => $this->t('No events.'),
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subRunResumeRetry(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $hc         = (array) ($tick['step_results']['health_check'] ?? []);
    $remediated = (array) ($hc['remediated'] ?? []);
    $rem_rows   = [];
    foreach ($remediated as $r) {
      $rc = isset($r['rc']) ? (int) $r['rc'] : -1;
      $rem_rows[] = [(string) ($r['agent'] ?? '?'), ['data' => ['#markup' => $this->rcBadge($rc)]]];
    }
    $summary = [
      [$this->t('Idle agents with inbox items'), (string) ($hc['idle_with_inbox'] ?? '—')],
      [$this->t('Blocked agents'), (string) ($hc['blocked_count'] ?? '—')],
      [$this->t('Remediated this tick'), (string) count($remediated)],
    ];
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'summary' => [
        '#type' => 'table',
        '#header' => [$this->t('Metric'), $this->t('Value')],
        '#rows' => $summary,
      ],
      'rem_header' => ['#markup' => '<h4>' . $this->t('Remediated Agents') . '</h4>'],
      'rem_table' => [
        '#type' => 'table',
        '#header' => [$this->t('Agent'), $this->t('RC')],
        '#rows' => $rem_rows,
        '#empty' => $this->t('None remediated this tick.'),
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subRunConcurrency(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $pick = (array) ($tick['step_results']['pick_agents'] ?? []);
    $cap  = isset($tick['agent_cap']) ? (int) $tick['agent_cap'] : NULL;
    $sel  = (array) ($pick['selected'] ?? []);
    $pri  = (array) ($pick['release_priority'] ?? []);
    $rows = [
      [$this->t('Agent cap (max/tick)'), $cap !== NULL ? (string) $cap : '—'],
      [$this->t('Agents selected this tick'), (string) count($sel)],
      [$this->t('Utilisation'), $cap ? round(count($sel) / $cap * 100) . '%' : '—'],
      [$this->t('Release priority agents'), implode(', ', array_map('strval', $pri)) ?: '—'],
      [$this->t('Selected agents'), implode(', ', array_map('strval', $sel)) ?: '—'],
    ];
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Metric'), $this->t('Value')],
        '#rows' => $rows,
      ],
    ]);
  }

  // -------------------------------------------------------------------------
  // Observe subsections (live)
  // -------------------------------------------------------------------------

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subObserveNodeTraces(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $steps = (array) ($tick['step_results'] ?? []);
    $rows  = [];
    foreach ($steps as $name => $data) {
      $rc_cell = isset($data['rc'])
        ? ['data' => ['#markup' => $this->rcBadge((int) $data['rc'])]]
        : '—';
      $payload = is_array($data)
        ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        : (string) $data;
      $rows[] = [
        (string) $name,
        $rc_cell,
        ['data' => ['#markup' => '<pre style="margin:0;font-size:0.8em;max-width:600px;white-space:pre-wrap">' . htmlspecialchars((string) $payload) . '</pre>']],
      ];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Step / Node'), $this->t('RC'), $this->t('Output')],
        '#rows' => $rows,
        '#empty' => $this->t('No trace data.'),
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subObserveRuntimeMetrics(array $sub, array $back): array {
    ['tick' => $tick, 'parity' => $parity] = $this->loadTelemetry();
    $exec  = (array) ($tick['step_results']['exec_agents']['ran'] ?? []);
    $errs  = (array) ($tick['errors'] ?? []);
    $ok    = count(array_filter($exec, fn($e) => (int) ($e['rc'] ?? -1) === 0));
    $fail  = count($exec) - $ok;
    $par   = isset($parity['parity_ok']) ? (bool) $parity['parity_ok'] : NULL;
    $rows  = [
      [$this->t('Last tick timestamp'), $this->fmtTs((string) ($tick['ts'] ?? ''))],
      [$this->t('Pipeline steps executed'), (string) count((array) ($tick['step_results'] ?? []))],
      [$this->t('Agents executed'), (string) count($exec)],
      [$this->t('Agents succeeded'), (string) $ok],
      [$this->t('Agents failed'), (string) $fail],
      [$this->t('Tick errors'), (string) count($errs)],
      [$this->t('Parity health'), ['data' => ['#markup' => $this->badge($par)]]],
    ];
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Metric'), $this->t('Value')],
        '#rows' => $rows,
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subObserveDriftAnomalies(array $sub, array $back): array {
    ['parity' => $parity] = $this->loadTelemetry();
    $par_ok     = isset($parity['parity_ok']) ? (bool) $parity['parity_ok'] : NULL;
    $exp_steps  = (array) ($parity['steps']['expected'] ?? []);
    $act_steps  = (array) ($parity['steps']['actual'] ?? []);
    $exp_agents = (array) ($parity['selected_agents']['actual'] ?? []);
    $step_match = isset($parity['steps']['match']) ? (bool) $parity['steps']['match'] : NULL;
    $ag_match   = isset($parity['selected_agents']['match']) ? (bool) $parity['selected_agents']['match'] : NULL;

    $overview = [
      [$this->t('Overall parity'), ['data' => ['#markup' => $this->badge($par_ok)]]],
      [$this->t('Pipeline steps match'), ['data' => ['#markup' => $this->badge($step_match)]]],
      [$this->t('Agent selection match'), ['data' => ['#markup' => $this->badge($ag_match)]]],
    ];

    $missing = array_diff($exp_steps, $act_steps);
    $extra   = array_diff($act_steps, $exp_steps);
    $drift_rows = [];
    foreach ($missing as $s) {
      $drift_rows[] = ['step', (string) $s, ['data' => ['#markup' => '<span style="color:#b71c1c">missing in actual</span>']]];
    }
    foreach ($extra as $s) {
      $drift_rows[] = ['step', (string) $s, ['data' => ['#markup' => '<span style="color:#e65100">extra in actual</span>']]];
    }

    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'overview' => [
        '#type' => 'table',
        '#header' => [$this->t('Check'), $this->t('Result')],
        '#rows' => $overview,
      ],
      'drift_header' => ['#markup' => '<h4>' . $this->t('Pipeline Drift') . '</h4>'],
      'drift_table' => [
        '#type' => 'table',
        '#header' => [$this->t('Type'), $this->t('Name'), $this->t('Status')],
        '#rows' => $drift_rows,
        '#empty' => $this->t('No drift detected.'),
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subObserveAlertsIncidents(array $sub, array $back): array {
    ['tick' => $tick, 'parity' => $parity] = $this->loadTelemetry();
    $tick_errs  = (array) ($tick['errors'] ?? []);
    $par_errs   = (array) ($parity['errors'] ?? []);
    $rows       = [];
    foreach ($tick_errs as $e) {
      $rows[] = ['tick', (string) $e, $this->fmtTs((string) ($tick['ts'] ?? ''))];
    }
    foreach ($par_errs as $e) {
      $rows[] = ['parity', (string) $e, $this->fmtTs((string) ($parity['generated_at'] ?? ''))];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Source'), $this->t('Error'), $this->t('Timestamp')],
        '#rows' => $rows,
        '#empty' => $this->t('No errors recorded.'),
      ],
    ]);
  }

  // -------------------------------------------------------------------------
  // Stub subsection fallback
  // -------------------------------------------------------------------------

  /**
   * Generic stub subsection.
   *
   * @param array<mixed> $section_info
   * @param array<mixed> $sub_info
   * @param array<mixed> $back
   */
  private function buildStubSubsection(array $section_info, array $sub_info, array $back): array {
    return [
      '#type' => 'container',
      '#cache' => ['max-age' => 0],
      'title' => [
        '#markup' => '<h2>' . $this->t('@section: @subsection', [
          '@section'    => (string) ($section_info['title'] ?? ''),
          '@subsection' => (string) ($sub_info[0] ?? ''),
        ]) . '</h2>',
      ],
      'desc' => ['#markup' => '<p>' . $this->t((string) ($sub_info[1] ?? '')) . '</p>'],
      'notice' => [
        '#markup' => '<div class="messages messages--status"><strong>' . $this->t('Stub') . ':</strong> ' . $this->t('Data integration not yet wired for this subsection.') . '</div>',
      ],
      'back' => $back,
    ];
  }

  /**
   * Helper to wrap subsection content with a title + back link.
   *
   * @param array<mixed> $back
   * @param array<mixed> $content
   */
  private function buildSubPage(string $title, string $description, array $back, array $content): array {
    return array_merge([
      '#type' => 'container',
      '#cache' => ['max-age' => 0],
      'title' => ['#markup' => '<h2>' . htmlspecialchars($title) . '</h2>'],
      'desc'  => ['#markup' => '<p>' . htmlspecialchars($description) . '</p>'],
      'back'  => $back,
    ], $content);
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
