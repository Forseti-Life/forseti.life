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

  /** @var list<string> Subsection keys that have live data implementations. */
  private const LIVE_SUBSECTIONS = [
    'home/graph-contract',
    'home/runtime-objects',
    'home/durability-model',
    'home/control-gates',
    'run/threads-runs',
    'run/stream-events',
    'run/resume-retry',
    'run/concurrency',
    'observe/node-traces',
    'observe/runtime-metrics',
    'observe/drift-anomalies',
    'observe/alerts-incidents',
    'build/state-schema',
    'build/nodes-routing',
    'build/subgraphs',
    'build/tool-calling',
    'test/path-scenarios',
    'test/checkpoint-replay',
    'test/eval-scorecards',
    'test/safety-gates',
    'release/graph-versions',
    'release/promotion-flow',
    'admin/identity-rbac',
    'admin/audit-change-log',
  ];

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
   * Build page — graph definition and topology.
   */
  public function build(): array {
    $sections = $this->sectionMap();
    $page = $sections['build'];
    $base = $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('build', (array) $page['subsections'])
    );
    $base['context'] = $this->renderConsoleContext(
      'Graph topology and schema for the Release Cycle Orchestrator — the nodes (pipeline steps) registered in engine.py, their edges (execution order), and the LangGraphDeps state schema that flows between them.',
      'Use when extending or debugging the orchestrator graph: verifying a node was registered, confirming edge order, or understanding what fields are available in the state dict at each step.',
      'orchestrator/runtime_graph/engine.py — parsed live at page load from graph.add_node() and graph.add_edge() calls.'
    );
    $base['terms'] = $this->renderKeyTerms([
      'Node'         => 'A LangGraph node = one pipeline step function registered via graph.add_node("name", fn). Each node receives the state dict and returns an update.',
      'Edge'         => 'A directed connection between two nodes — graph.add_edge("from", "to"). This orchestrator uses a linear chain with no conditional branching.',
      'State schema' => 'The shared state object (LangGraphDeps dataclass) passed between nodes. Each node can read from it and return partial updates.',
      'Pipeline'     => 'The full ordered sequence of nodes: consume_replies → dispatch_commands → release_cycle → coordinated_push → pick_agents → exec_agents → health_check → kpi_monitor → publish.',
    ]);
    return $base;
  }

  /**
   * Test page — validation and quality signals.
   */
  public function test(): array {
    $sections = $this->sectionMap();
    $page = $sections['test'];
    $base = $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('test', (array) $page['subsections'])
    );
    $base['context'] = $this->renderConsoleContext(
      'Feature progress and quality signals across all org workstreams — how many features are in-progress vs ready vs done, broken down by site and priority.',
      'Use during release planning to assess open work volume, confirm P0/P1 items are moving, or identify sites with a high deferred backlog.',
      'dashboards/FEATURE_PROGRESS.md — auto-regenerated by the orchestrator on every tick from features/*/feature.md status fields.'
    );
    $base['terms'] = $this->renderKeyTerms([
      'FEATURE_PROGRESS.md' => 'A markdown table auto-generated by the orchestrator each tick. Columns: Work item | Website | Module | Status | Priority | PM | Dev | QA.',
      'Status values'       => 'ready = groomed and waiting for dev start. in_progress = currently being worked. done = code complete. deferred = postponed. shipped = deployed to production.',
      'Priority'            => 'P0 = critical/blocking. P1 = high-value next. P2 = normal. P3 = low/backlog.',
      'Path scenario'       => 'A feature or scenario exercising a specific path through the orchestrator graph — used to validate end-to-end behavior for that work item.',
      'Eval scorecard'      => 'Planned future: quantitative success metrics per feature (task completion rate, tool accuracy, etc.). Currently structural stub.',
    ]);
    return $base;
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
   * Release page — graph version promotion and rollout.
   */
  public function release(): array {
    $sections = $this->sectionMap();
    $page = $sections['release'];
    $base = $this->buildPage(
      'Release Control Panel',
      (string) $page['description'],
      $this->buildSectionRows('release', (array) $page['subsections'])
    );
    $base['context'] = $this->renderConsoleContext(
      'Release cycle state across all product teams — current release IDs, PM signoff status, features in scope, and elapsed time since release start.',
      'Use when a release appears stalled, to confirm PM has signed off, or to see how many features remain in-progress for a team.',
      'tmp/release-cycle-active/{team}.release_id, {team}.started_at + sessions/pm-{team}/artifacts/release-signoffs/{release_id}.md + features/*/feature.md'
    );
    $base['panel_header'] = ['#markup' => '<h3>' . $this->t('Active Releases') . '</h3>'];
    $base['panel_table'] = $this->buildReleasePanelTable();
    $base['terms'] = $this->renderKeyTerms([
      'release_id'         => 'The identifier of the release currently in-flight for a team, e.g. "20260408-forseti-release-d". Format: YYYYMMDD-{team}-release-{letter}.',
      'PM signoff'         => 'SIGNED = a signoff artifact exists in sessions/pm-{team}/artifacts/release-signoffs/{release_id}.md. PENDING = file absent.',
      'Features in scope'  => 'Count of features with Status: in_progress matching the active site name in features/*/feature.md.',
      'Hours elapsed'      => 'Time since {team}.started_at was written. Releases auto-close after 24h.',
      'coordinated_push'   => 'A cross-team gate: the orchestrator only executes a push when ALL teams simultaneously signal ready.',
      'Canary controls'    => 'Planned future: traffic-split rollout of graph changes. Currently structural stub — requires Board approval before scoping.',
    ]);
    return $base;
  }

  /**
   * Build the live Release Control Panel table for all teams.
   *
   * @return array<string, mixed>
   */
  private function buildReleasePanelTable(): array {
    $hq = $this->hqPath('tmp/release-cycle-active');
    $features_dir = $this->hqPath('features');

    // Map team slug → site name used in feature.md Website field.
    $teams = [
      'forseti'       => 'forseti.life',
      'dungeoncrawler' => 'dungeoncrawler',
    ];

    $rows = [];
    foreach ($teams as $team => $site_name) {
      $r_id_path  = "$hq/$team.release_id";
      $started_path = "$hq/$team.started_at";

      $r_id = is_readable($r_id_path) ? trim((string) file_get_contents($r_id_path)) : '';
      if ($r_id === '') {
        $rows[] = [
          $team,
          $this->t('No active release'),
          '—',
          '—',
          '—',
        ];
        continue;
      }

      // PM signoff: look for the signoff markdown artifact.
      $signoff_path = $this->hqPath("sessions/pm-$team/artifacts/release-signoffs/$r_id.md");
      $signed = is_readable($signoff_path);
      $signoff_badge = $signed
        ? '<strong style="color:#2e7d32">SIGNED</strong>'
        : '<span style="color:#b71c1c">PENDING</span>';

      // Feature count in scope: count in_progress features for this site.
      $feature_count = 0;
      foreach (glob("$features_dir/*/feature.md") ?: [] as $fpath) {
        $fcontent = (string) @file_get_contents($fpath);
        if (preg_match('/^- Status:\s*in_progress/m', $fcontent) &&
            preg_match('/^- Website:\s*' . preg_quote($site_name, '/') . '/m', $fcontent)) {
          $feature_count++;
        }
      }

      // Hours elapsed since release start.
      $hours_str = '—';
      if (is_readable($started_path)) {
        $started_raw = trim((string) file_get_contents($started_path));
        $ts = strtotime($started_raw);
        if ($ts !== FALSE) {
          $elapsed_h = (time() - $ts) / 3600;
          $hours_str = number_format($elapsed_h, 1) . 'h';
          if ($elapsed_h > 20) {
            $hours_str = '<strong style="color:#b71c1c">' . htmlspecialchars($hours_str) . ' ⚠</strong>';
          }
        }
      }

      $rows[] = [
        htmlspecialchars($team),
        htmlspecialchars($r_id),
        ['data' => ['#markup' => $signoff_badge]],
        (string) $feature_count,
        ['data' => ['#markup' => $hours_str]],
      ];
    }

    return [
      '#type'   => 'table',
      '#cache'  => ['max-age' => 60],
      '#header' => [
        $this->t('Team'),
        $this->t('Release ID'),
        $this->t('PM Signoff'),
        $this->t('Features in scope'),
        $this->t('Hours elapsed'),
      ],
      '#rows'   => $rows,
      '#empty'  => $this->t('No release state found.'),
    ];
  }

  /**
   * Admin page — configuration and platform controls.
   */
  public function admin(): array {
    ['tick' => $tick, 'parity' => $parity] = $this->loadTelemetry();

    $hq_root    = rtrim((string) (getenv('COPILOT_HQ_ROOT') ?: '/home/ubuntu/forseti.life/copilot-hq'), '/');
    $ticks_path = $this->hqPath(self::TICKS_RELATIVE);
    $par_path   = $this->hqPath(self::PARITY_RELATIVE);
    $dry_run    = isset($tick['dry_run']) ? (bool) $tick['dry_run'] : NULL;
    $pub_en     = isset($tick['publish_enabled']) ? (bool) $tick['publish_enabled'] : NULL;
    $agent_cap  = isset($tick['agent_cap']) ? (int) $tick['agent_cap'] : NULL;
    $provider   = (string) ($tick['provider'] ?? '—');
    $ts         = (string) ($tick['ts'] ?? '');
    $par_ts     = (string) ($parity['generated_at'] ?? '');

    // Tick file stats.
    $tick_lines = 0;
    if (is_readable($ticks_path)) {
      $lines = @file($ticks_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      $tick_lines = is_array($lines) ? count($lines) : 0;
    }

    $config_rows = [
      [$this->t('COPILOT_HQ_ROOT'), $hq_root, $this->t('Runtime env var (falls back to /home/ubuntu/forseti.life/copilot-hq)')],
      [$this->t('Provider'), $provider, $this->t('Agent execution provider')],
      [$this->t('Agent cap'), $agent_cap !== NULL ? (string) $agent_cap : '—', $this->t('Max agents executed per tick')],
      [$this->t('Mode'), $dry_run === NULL ? '—' : ($dry_run ? 'dry-run' : 'live'), $this->t('dry_run=true means no writes')],
      [$this->t('Publish enabled'), $pub_en === NULL ? '—' : ($pub_en ? 'yes' : 'no'), $this->t('Controls bash scripts/publish-forseti-agent-tracker.sh')],
    ];

    $file_rows = [
      [$this->t('Ticks JSONL'), $ticks_path, is_readable($ticks_path) ? $this->t('@n lines', ['@n' => $tick_lines]) : $this->t('not readable')],
      [$this->t('Parity JSON'), $par_path, is_readable($par_path) ? $this->t('readable') : $this->t('not readable')],
      [$this->t('Feature Progress'), $this->hqPath(self::FEATURE_PROGRESS), is_readable($this->hqPath(self::FEATURE_PROGRESS)) ? $this->t('readable') : $this->t('not readable')],
      [$this->t('Last tick timestamp'), $this->fmtTs($ts), ''],
      [$this->t('Last parity check'), $this->fmtTs($par_ts), ''],
    ];

    $sections = $this->sectionMap();
    $nav = $this->buildSectionRows('admin', (array) $sections['admin']['subsections']);

    return [
      '#type'  => 'container',
      '#cache' => ['max-age' => 0],
      'title'  => ['#markup' => '<h2>' . $this->t('Admin') . '</h2>'],
      'context' => $this->renderConsoleContext(
        'Runtime configuration snapshot and data file health for the Release Cycle Orchestrator — where it reads/writes state, what mode it is running in, and whether key telemetry files are accessible.',
        'Use when other console pages show missing data (check file readable status), confirming the orchestrator is in live vs dry-run mode, or checking the publish pipeline is connected to Drupal.',
        'Environment variable COPILOT_HQ_ROOT + filesystem stat of langgraph-ticks.jsonl, langgraph-parity-latest.json, FEATURE_PROGRESS.md.'
      ),
      'terms' => $this->renderKeyTerms([
        'COPILOT_HQ_ROOT'  => 'Environment variable pointing to the copilot-hq working directory. All telemetry file paths resolve relative to this root. Falls back to /home/ubuntu/forseti.life/copilot-hq.',
        'dry_run'          => 'When true, the orchestrator runs all logic but skips external writes. No GitHub pushes, no file writes outside the HQ repo.',
        'publish_enabled'  => 'Controls whether the orchestrator runs the publish step, which writes agent tracker telemetry to the Drupal database. When false, Drupal UI shows stale agent data.',
        'agent_cap'        => 'Max agents dispatched per tick. Raised temporarily for faster throughput during a release; lowered to reduce API usage.',
        'Ticks JSONL'      => 'Append-only log of every orchestrator tick. Each line is a JSON object. Console pages read only the last line for live data.',
        'Parity JSON'      => 'Latest parity snapshot — overwritten each tick. Contains parity_ok, expected/actual step lists, and agent selection match result.',
        'FEATURE_PROGRESS' => 'Auto-generated markdown table of all features across all sites. Re-written every tick from features/*/feature.md status fields.',
      ]),

      'config_header' => ['#markup' => '<h3>' . $this->t('Runtime Configuration') . '</h3>'],
      'config_table'  => [
        '#type'   => 'table',
        '#header' => [$this->t('Setting'), $this->t('Value'), $this->t('Notes')],
        '#rows'   => $config_rows,
      ],

      'files_header' => ['#markup' => '<h3>' . $this->t('Data Files') . '</h3>'],
      'files_table'  => [
        '#type'   => 'table',
        '#header' => [$this->t('File'), $this->t('Path'), $this->t('Status')],
        '#rows'   => $file_rows,
      ],

      'nav' => [
        '#type'  => 'details',
        '#title' => $this->t('Subsections'),
        '#open'  => FALSE,
        'table'  => [
          '#type'   => 'table',
          '#header' => [$this->t('Subsection'), $this->t('Frame'), $this->t('Status')],
          '#rows'   => $nav,
        ],
      ],
    ];
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
      'build/state-schema'    => $this->subBuildStateSchema($sub_info, $back),
      'build/nodes-routing'   => $this->subBuildNodesRouting($sub_info, $back),
      'build/subgraphs'       => $this->subBuildSubgraphs($sub_info, $back),
      'build/tool-calling'    => $this->subBuildToolCalling($sub_info, $back),
      'test/path-scenarios'   => $this->subTestPathScenarios($sub_info, $back),
      'test/checkpoint-replay' => $this->subTestCheckpointReplay($sub_info, $back),
      'test/eval-scorecards'  => $this->subTestEvalScorecards($sub_info, $back),
      'test/safety-gates'      => $this->subTestSafetyGates($sub_info, $back),
      'release/graph-versions' => $this->subReleaseGraphVersions($sub_info, $back),
      'release/promotion-flow' => $this->subReleasePromotionFlow($sub_info, $back),
      'admin/identity-rbac'   => $this->subAdminConfig($sub_info, $back),
      'admin/audit-change-log' => $this->subAdminAuditLog($sub_info, $back),
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

  // -------------------------------------------------------------------------
  // Build subsections (live from engine.py)
  // -------------------------------------------------------------------------

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subBuildStateSchema(array $sub, array $back): array {
    // LangGraphDeps fields — static from dataclass definition in engine.py.
    $fields = [
      ['run_cmd',                'RunFn',               'Callable — execute a shell command and return (rc, output)'],
      ['dispatch_commands_step', 'DispatchFn',          'Callable — process and dispatch pending inbox commands'],
      ['release_cycle_step',     'ReleaseCycleFn',      'Callable — advance release cycle state for each team'],
      ['coordinated_push_step',  'CoordinatedPushFn',   'Callable — coordinate multi-team git push when all ready'],
      ['prioritized_agents',     'PrioritizedAgentsFn', 'Callable — return ordered list of agents to run this tick'],
      ['health_check_step',      'HealthCheckFn',       'Callable — remediate idle/blocked agents'],
      ['now_ts',                 'NowTsFn',             'Callable — return current Unix timestamp (int)'],
      ['kpi_monitor_cmd',        'List[str]',           'Shell command to run KPI monitoring script'],
    ];
    $rows = array_map(fn($f) => $f, $fields);
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'note' => ['#markup' => '<p><em>' . $this->t('Source: orchestrator/runtime_graph/engine.py — @dataclass(frozen=True) LangGraphDeps') . '</em></p>'],
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('Field'), $this->t('Type'), $this->t('Description')],
        '#rows'   => $rows,
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subBuildNodesRouting(array $sub, array $back): array {
    $nodes = $this->parseEngineNodes();
    $edges = $this->parseEngineEdges();

    $node_rows = array_map(fn($n, $i) => [(string) ($i + 1), $n], $nodes, array_keys($nodes));
    $edge_rows = array_map(fn($e) => [$e['from'], '→', $e['to']], $edges);

    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'note'   => ['#markup' => '<p><em>' . $this->t('Parsed from orchestrator/runtime_graph/engine.py at request time.') . '</em></p>'],
      'n_head' => ['#markup' => '<h4>' . $this->t('Nodes') . '</h4>'],
      'n_tbl'  => [
        '#type'   => 'table',
        '#header' => [$this->t('#'), $this->t('Node name')],
        '#rows'   => $node_rows,
        '#empty'  => $this->t('Could not parse.'),
      ],
      'e_head' => ['#markup' => '<h4>' . $this->t('Edges (sequential pipeline)') . '</h4>'],
      'e_tbl'  => [
        '#type'   => 'table',
        '#header' => [$this->t('From'), $this->t(''), $this->t('To')],
        '#rows'   => $edge_rows,
        '#empty'  => $this->t('Could not parse.'),
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subBuildSubgraphs(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    // Recursively search step_results for any 'subgraph' key.
    $found = $this->findSubgraphEntries((array) ($tick['step_results'] ?? []));
    if (empty($found)) {
      return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
        'note' => ['#markup' => '<p><em>' . $this->t('No subgraphs detected in current telemetry. Subgraph entries appear when a step_results key contains a "subgraph" field.') . '</em></p>'],
      ]);
    }
    $rows = [];
    foreach ($found as $entry) {
      $rows[] = [(string) ($entry['path'] ?? ''), (string) ($entry['value'] ?? '')];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'note' => ['#markup' => '<p><em>' . $this->t('Subgraph entries found in last telemetry tick.') . '</em></p>'],
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('Path'), $this->t('Value')],
        '#rows'   => $rows,
        '#empty'  => $this->t('No subgraph entries.'),
      ],
    ]);
  }

  /**
   * Recursively find all 'subgraph' keys in a nested array.
   *
   * @param array<mixed> $data
   * @param string $path
   * @return list<array{path: string, value: string}>
   */
  private function findSubgraphEntries(array $data, string $path = ''): array {
    $results = [];
    foreach ($data as $key => $value) {
      $current_path = $path !== '' ? $path . '.' . $key : (string) $key;
      if ($key === 'subgraph') {
        $results[] = ['path' => $current_path, 'value' => is_scalar($value) ? (string) $value : json_encode($value)];
      }
      if (is_array($value)) {
        $results = array_merge($results, $this->findSubgraphEntries($value, $current_path));
      }
    }
    return $results;
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subBuildToolCalling(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $manifest = $tick['tool_manifest'] ?? ($tick['step_results']['tool_manifest'] ?? NULL);
    if ($manifest === NULL || (is_array($manifest) && empty($manifest))) {
      return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
        'note' => ['#markup' => '<p><em>' . $this->t('Tool manifest not yet available. The orchestrator will emit a "tool_manifest" field in the tick payload once tool definitions are registered.') . '</em></p>'],
      ]);
    }
    $rows = [];
    if (is_array($manifest)) {
      foreach ($manifest as $tool_name => $tool_def) {
        $desc = is_array($tool_def) ? (string) ($tool_def['description'] ?? json_encode($tool_def)) : (string) $tool_def;
        $rows[] = [(string) $tool_name, $desc];
      }
    }
    else {
      $rows[] = ['manifest', (string) $manifest];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'note' => ['#markup' => '<p><em>' . $this->t('Tool manifest from last telemetry tick.') . '</em></p>'],
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('Tool'), $this->t('Description')],
        '#rows'   => $rows,
        '#empty'  => $this->t('No tools registered.'),
      ],
    ]);
  }

  // -------------------------------------------------------------------------
  // Test subsections (feature progress data)
  // -------------------------------------------------------------------------

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subTestPathScenarios(array $sub, array $back): array {
    $suite_path = $this->hqPath('qa-suites/products/forseti.life/suite.json');
    if (!is_readable($suite_path)) {
      return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
        'note' => ['#markup' => '<div class="messages messages--warning">' . $this->t('Test suite not yet configured. Create <code>qa-suites/products/forseti.life/suite.json</code> to populate this view.') . '</div>'],
      ]);
    }
    $suite = $this->readJson($suite_path);
    $scenarios = (array) ($suite['scenarios'] ?? $suite['tests'] ?? $suite);
    $rows = [];
    foreach ($scenarios as $id => $scenario) {
      if (is_array($scenario)) {
        $name   = (string) ($scenario['name'] ?? $scenario['id'] ?? (string) $id);
        $path   = (string) ($scenario['path'] ?? $scenario['description'] ?? '');
        $status = (string) ($scenario['status'] ?? $scenario['result'] ?? '—');
      }
      else {
        $name   = (string) $id;
        $path   = (string) $scenario;
        $status = '—';
      }
      $rows[] = [$name, $path, $status];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'note' => ['#markup' => '<p><em>' . $this->t('Loaded from qa-suites/products/forseti.life/suite.json.') . '</em></p>'],
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('Scenario'), $this->t('Path / Description'), $this->t('Status')],
        '#rows'   => $rows,
        '#empty'  => $this->t('No scenarios defined in suite.json.'),
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subTestCheckpointReplay(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    if (empty($tick)) {
      return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
        'note' => ['#markup' => '<p><em>' . $this->t('No checkpoint data available. No tick file found.') . '</em></p>'],
      ]);
    }
    $ts     = (string) ($tick['ts'] ?? '');
    $teams  = (array) ($tick['step_results']['release_cycle']['teams'] ?? []);
    $exec   = (array) ($tick['step_results']['exec_agents']['ran'] ?? []);
    $rows   = [];
    $rows[] = [$this->t('Last tick timestamp'), $this->fmtTs($ts), '—'];
    foreach ($teams as $t) {
      $rows[] = [
        $this->t('Team: @team', ['@team' => (string) ($t['team'] ?? '?')]),
        (string) ($t['current'] ?? '—'),
        (string) ($t['action'] ?? '—'),
      ];
    }
    foreach ($exec as $e) {
      $rows[] = [
        $this->t('Agent: @agent', ['@agent' => (string) ($e['agent'] ?? '?')]),
        '—',
        ['data' => ['#markup' => $this->rcBadge(isset($e['rc']) ? (int) $e['rc'] : -1)]],
      ];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'note' => ['#markup' => '<p><em>' . $this->t('Checkpoint state from last telemetry tick. Full checkpoint replay (time-travel) is planned for release-h.') . '</em></p>'],
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('Thread / Agent'), $this->t('State'), $this->t('Action / RC')],
        '#rows'   => $rows,
        '#empty'  => $this->t('No checkpoint data.'),
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subTestEvalScorecards(array $sub, array $back): array {
    $outbox_dir = $this->hqPath('sessions/qa-forseti/outbox');
    $files = glob("$outbox_dir/*.md") ?: [];
    if (empty($files)) {
      return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
        'note' => ['#markup' => '<p><em>' . $this->t('No QA scorecard files found in sessions/qa-forseti/outbox/. Files appear after qa-forseti completes a verification cycle.') . '</em></p>'],
        'table' => [
          '#type'   => 'table',
          '#header' => [$this->t('File'), $this->t('Status'), $this->t('Summary')],
          '#rows'   => [],
          '#empty'  => $this->t('No eval scorecard data available.'),
        ],
      ]);
    }
    // Sort by mtime descending (most recent first).
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    $files = array_slice($files, 0, 20);
    $rows = [];
    foreach ($files as $file) {
      $content = (string) @file_get_contents($file);
      preg_match('/^- Status:\s*(.+)$/m', $content, $m_status);
      preg_match('/^- Summary:\s*(.+)$/m', $content, $m_summary);
      $status  = trim($m_status[1] ?? '—');
      $summary = trim($m_summary[1] ?? '—');
      if (strlen($summary) > 120) {
        $summary = substr($summary, 0, 117) . '…';
      }
      $filename = basename($file, '.md');
      $badge = match (strtolower($status)) {
        'done'        => '<span style="color:#2e7d32">✓ done</span>',
        'in_progress' => '<span style="color:#e65100">⏳ in_progress</span>',
        'blocked'     => '<span style="color:#b71c1c">✗ blocked</span>',
        default       => htmlspecialchars($status),
      };
      $rows[] = [
        $filename,
        ['data' => ['#markup' => $badge]],
        htmlspecialchars($summary),
      ];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'note' => ['#markup' => '<p><em>' . $this->t('Latest QA outbox files from sessions/qa-forseti/outbox/ (most recent 20).') . '</em></p>'],
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('File'), $this->t('Status'), $this->t('Summary')],
        '#rows'   => $rows,
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subTestSafetyGates(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $hq = $this->hqPath('tmp/release-cycle-active');

    // Gate 1: Active release.
    $release_id = is_readable("$hq/forseti.release_id")
      ? trim((string) file_get_contents("$hq/forseti.release_id")) : '';
    $gate1_ok  = $release_id !== '';
    $gate1_val = $gate1_ok ? $release_id : '—';

    // Gate 2: QA verified — check qa-forseti outbox for a recent file with Status: done.
    $outbox_dir = $this->hqPath('sessions/qa-forseti/outbox');
    $gate2_ok   = FALSE;
    $gate2_val  = 'no QA outbox files';
    foreach (glob("$outbox_dir/*.md") ?: [] as $f) {
      $c = (string) @file_get_contents($f);
      if (preg_match('/^- Status:\s*(done|approved|pass)/mi', $c)) {
        $gate2_ok  = TRUE;
        $gate2_val = basename($f, '.md');
        break;
      }
    }

    // Gate 3: PM signoff artifact.
    $gate3_ok  = FALSE;
    $gate3_val = '—';
    if ($release_id !== '') {
      $signoff = $this->hqPath("sessions/pm-forseti/artifacts/release-signoffs/$release_id.md");
      $gate3_ok  = is_readable($signoff);
      $gate3_val = $gate3_ok ? $release_id : 'no signoff file';
    }

    // Gate 4: Coordinated push done.
    $push_status = (string) ($tick['step_results']['coordinated_push']['status'] ?? '');
    $gate4_ok    = str_contains(strtolower($push_status), 'pushed') || str_contains(strtolower($push_status), 'done') || str_contains(strtolower($push_status), 'complete');
    $gate4_val   = $push_status !== '' ? $push_status : '—';

    $ok   = fn(bool $v): array => ['data' => ['#markup' => $v ? '<span style="color:#2e7d32">✓ PASS</span>' : '<span style="color:#b71c1c">✗ FAIL</span>']];
    $rows = [
      [$this->t('Gate 1: Active release ID'), $ok($gate1_ok), $gate1_val],
      [$this->t('Gate 2: QA verified (recent done status)'), $ok($gate2_ok), $gate2_val],
      [$this->t('Gate 3: PM signoff artifact present'), $ok($gate3_ok), $gate3_val],
      [$this->t('Gate 4: Coordinated push completed'), $ok($gate4_ok), $gate4_val],
    ];

    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'note' => ['#markup' => '<p><em>' . $this->t('Pre-release safety gates — live status from release-cycle-active/ and telemetry.') . '</em></p>'],
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('Gate'), $this->t('Result'), $this->t('Detail')],
        '#rows'   => $rows,
      ],
    ]);
  }

  // -------------------------------------------------------------------------
  // Release subsections (live release state)
  // -------------------------------------------------------------------------

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subReleaseGraphVersions(array $sub, array $back): array {
    $hq = $this->hqPath('tmp/release-cycle-active');
    $rows = [];
    foreach (['forseti', 'dungeoncrawler'] as $team) {
      $r_id  = is_readable("$hq/$team.release_id") ? trim((string) file_get_contents("$hq/$team.release_id")) : '—';
      $n_id  = is_readable("$hq/$team.next_release_id") ? trim((string) file_get_contents("$hq/$team.next_release_id")) : '—';
      $start = is_readable("$hq/$team.started_at") ? trim((string) file_get_contents("$hq/$team.started_at")) : '';
      $rows[] = [$team, $r_id, $n_id, $this->fmtTs($start)];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'note' => ['#markup' => '<p><em>' . $this->t('Active release IDs from tmp/release-cycle-active/ (runtime state, gitignored).') . '</em></p>'],
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('Team'), $this->t('Current Release ID'), $this->t('Next Release ID'), $this->t('Started')],
        '#rows'   => $rows,
        '#empty'  => $this->t('No release state.'),
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subReleasePromotionFlow(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $push  = (array) ($tick['step_results']['coordinated_push'] ?? []);
    $teams = (array) ($tick['step_results']['release_cycle']['teams'] ?? []);
    $rows  = [];
    foreach ($teams as $t) {
      $rc    = isset($t['rc']) ? (int) $t['rc'] : 0;
      $rows[] = [
        (string) ($t['team'] ?? '?'),
        (string) ($t['action'] ?? '—'),
        (string) ($t['current'] ?? '—'),
        ['data' => ['#markup' => $this->rcBadge($rc)]],
      ];
    }
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'push_status' => ['#markup' => '<p><strong>' . $this->t('Coordinated push: @s', ['@s' => (string) ($push['status'] ?? '—')]) . '</strong></p>'],
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('Team'), $this->t('Action'), $this->t('Release'), $this->t('RC')],
        '#rows'   => $rows,
        '#empty'  => $this->t('No release cycle data.'),
      ],
      'notice' => ['#markup' => '<div class="messages messages--warning">' . $this->t('Manual promotion, canary controls, and rollback tooling require Board approval. See roadmap release-h.') . '</div>'],
    ]);
  }

  // -------------------------------------------------------------------------
  // Admin subsections (config data)
  // -------------------------------------------------------------------------

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subAdminConfig(array $sub, array $back): array {
    ['tick' => $tick] = $this->loadTelemetry();
    $rows = [
      [$this->t('COPILOT_HQ_ROOT'), rtrim((string) (getenv('COPILOT_HQ_ROOT') ?: '/home/ubuntu/forseti.life/copilot-hq'), '/')],
      [$this->t('Provider'), (string) ($tick['provider'] ?? '—')],
      [$this->t('Agent cap'), isset($tick['agent_cap']) ? (string) $tick['agent_cap'] : '—'],
      [$this->t('Mode'), isset($tick['dry_run']) ? ((bool) $tick['dry_run'] ? 'dry-run' : 'live') : '—'],
      [$this->t('Publish enabled'), isset($tick['publish_enabled']) ? ((bool) $tick['publish_enabled'] ? 'yes' : 'no') : '—'],
    ];
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('Setting'), $this->t('Value')],
        '#rows'   => $rows,
      ],
    ]);
  }

  /** @param array<mixed> $sub @param array<mixed> $back */
  private function subAdminAuditLog(array $sub, array $back): array {
    ['tick' => $tick, 'parity' => $parity] = $this->loadTelemetry();
    $rows = [
      [$this->t('Last tick'), $this->fmtTs((string) ($tick['ts'] ?? '')), 'orchestrator', 'auto'],
      [$this->t('Last parity check'), $this->fmtTs((string) ($parity['generated_at'] ?? '')), 'parity', 'auto'],
    ];
    return $this->buildSubPage((string) $sub[0], (string) $sub[1], $back, [
      'note' => ['#markup' => '<p><em>' . $this->t('Full immutable audit log (Release-h scope) not yet implemented. Showing last automatic events.') . '</em></p>'],
      'table' => [
        '#type'   => 'table',
        '#header' => [$this->t('Event'), $this->t('Timestamp'), $this->t('Source'), $this->t('Actor')],
        '#rows'   => $rows,
      ],
    ]);
  }

  // -------------------------------------------------------------------------
  // Engine.py parsing helpers
  // -------------------------------------------------------------------------

  /**
   * Parse add_node() calls from engine.py.
   *
   * @return list<string>
   */
  private function parseEngineNodes(): array {
    $path = $this->hqPath('orchestrator/runtime_graph/engine.py');
    if (!is_readable($path)) {
      return [];
    }
    $content = (string) file_get_contents($path);
    preg_match_all('/graph\.add_node\(\s*["\']([^"\']+)["\']/', $content, $m);
    return $m[1] ?? [];
  }

  /**
   * Parse add_edge() calls from engine.py.
   *
   * @return list<array{from: string, to: string}>
   */
  private function parseEngineEdges(): array {
    $path = $this->hqPath('orchestrator/runtime_graph/engine.py');
    if (!is_readable($path)) {
      return [];
    }
    $content = (string) file_get_contents($path);
    preg_match_all('/graph\.add_edge\(\s*["\']([^"\']+)["\'],\s*["\']([^"\']+)["\']/', $content, $m);
    $edges = [];
    for ($i = 0; $i < count($m[1]); $i++) {
      $edges[] = ['from' => $m[1][$i], 'to' => $m[2][$i]];
    }
    return $edges;
  }

  // -------------------------------------------------------------------------
  // FEATURE_PROGRESS.md parsing helper
  // -------------------------------------------------------------------------

  /**
   * Parse FEATURE_PROGRESS.md and return aggregated stats.
   *
   * @return array{by_status: array<string,int>, by_site: array<string,int>, by_priority: array<string,int>}
   */
  private function featureProgressStats(): array {
    $path = $this->hqPath(self::FEATURE_PROGRESS);
    if (!is_readable($path)) {
      return ['by_status' => [], 'by_site' => [], 'by_priority' => []];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    $by_status   = [];
    $by_site     = [];
    $by_priority = [];
    $in_table    = FALSE;

    foreach ($lines as $line) {
      $line = trim($line);
      // Detect markdown table header with expected columns.
      if (str_contains($line, '| Work item |') || str_contains($line, '|-----------|')) {
        $in_table = TRUE;
        continue;
      }
      if (!$in_table || !str_starts_with($line, '|')) {
        if ($in_table && $line === '') {
          $in_table = FALSE;
        }
        continue;
      }
      $cols = array_map('trim', explode('|', $line));
      // Remove empty leading/trailing from pipe split.
      $cols = array_values(array_filter($cols, fn($c) => $c !== ''));
      // | Work item | Website | Module | Status | Priority | PM | Dev | QA |
      //      0           1        2        3        4        5    6    7
      if (count($cols) < 5) {
        continue;
      }
      $status   = strtolower((string) $cols[3]);
      $site     = (string) $cols[1];
      $priority = (string) $cols[4];
      // Normalise priority to just first token (P0, P1, etc.).
      if (preg_match('/^(P\d|high|medium|low|unset)/i', $priority, $pm)) {
        $priority = strtolower($pm[1]);
      }
      else {
        $priority = 'other';
      }
      $by_status[$status]   = ($by_status[$status] ?? 0) + 1;
      $by_site[$site]       = ($by_site[$site] ?? 0) + 1;
      $by_priority[$priority] = ($by_priority[$priority] ?? 0) + 1;
    }

    // Sort by count desc.
    arsort($by_status);
    arsort($by_site);
    arsort($by_priority);

    return ['by_status' => $by_status, 'by_site' => $by_site, 'by_priority' => $by_priority];
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
      $key = $section . '/' . $slug;
      $is_live = in_array($key, self::LIVE_SUBSECTIONS, TRUE);
      $rows[] = [
        Link::fromTextAndUrl(
          $this->t($title),
          Url::fromRoute('copilot_agent_tracker.langgraph_console_subsection', [
            'section' => $section,
            'subsection' => (string) $slug,
          ])
        )->toString(),
        $desc,
        ['data' => ['#markup' => $is_live ? '<span style="color:#2e7d32">🟢 Live</span>' : '<span style="color:#b71c1c">🔴 Stub</span>']],
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
