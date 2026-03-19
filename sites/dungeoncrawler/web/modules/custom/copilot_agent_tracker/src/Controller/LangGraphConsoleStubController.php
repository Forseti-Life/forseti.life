<?php

namespace Drupal\copilot_agent_tracker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Clean-slate LangGraph management console stubs.
 */
final class LangGraphConsoleStubController extends ControllerBase {

  private const DEFAULT_HQ_ROOT = '/home/keithaumiller/copilot-sessions-hq';

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

  /**
   * Console home.
   */
  public function home(): array {
    $sections = $this->sectionMap();
    $page = $sections['home'];
    $build = $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('home', (array) $page['subsections'])
    );

    $latest_tick = $this->readLatestTick();
    $parity = $this->readParity();
    $release_control = $this->readReleaseControl();

    $tick_ts = (string) ($latest_tick['ts'] ?? 'unavailable');
    $tick_age = $this->formatAgeFromTimestamp((string) ($latest_tick['ts'] ?? ''));
    $dry_run = !empty($latest_tick['dry_run']) ? 'yes' : 'no';
    $publish_enabled = !empty($latest_tick['publish_enabled']) ? 'yes' : 'no';
    $provider = (string) ($latest_tick['provider'] ?? 'unknown');
    $parity_ok = isset($parity['parity_ok']) ? ((bool) $parity['parity_ok'] ? 'PASS' : 'FAIL') : 'unknown';
    $release_enabled = isset($release_control['enabled']) ? ((bool) $release_control['enabled'] ? 'yes' : 'no') : 'unknown';

    $build['live_status'] = [
      '#type' => 'details',
      '#title' => $this->t('Live Runtime Status (read-only)'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Signal'), $this->t('Current Value'), $this->t('Source')],
        '#rows' => [
          [$this->t('Latest tick timestamp'), $tick_ts, $this->sourcePath('inbox/responses/langgraph-ticks.jsonl')],
          [$this->t('Latest tick age'), $tick_age, $this->t('derived from tick timestamp')],
          [$this->t('dry_run'), $dry_run, $this->sourcePath('inbox/responses/langgraph-ticks.jsonl')],
          [$this->t('publish_enabled'), $publish_enabled, $this->sourcePath('inbox/responses/langgraph-ticks.jsonl')],
          [$this->t('provider'), $provider, $this->sourcePath('inbox/responses/langgraph-ticks.jsonl')],
          [$this->t('parity_ok'), $parity_ok, $this->sourcePath('inbox/responses/langgraph-parity-latest.json')],
          [$this->t('release_cycle_enabled'), $release_enabled, $this->sourcePath('tmp/release-cycle-control.json or /var/tmp/...')],
        ],
      ],
    ];

    return $build;
  }

  /**
   * Build page.
   */
  public function build(): array {
    $sections = $this->sectionMap();
    $page = $sections['build'];
    $build = $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('build', (array) $page['subsections'])
    );

    $latest_tick = $this->readLatestTick();
    $step_results = is_array($latest_tick['step_results'] ?? NULL) ? $latest_tick['step_results'] : [];
    $graph_nodes = array_values(array_filter(array_keys($step_results), static fn($key) => $key !== 'summarize_tick'));
    sort($graph_nodes);

    $rows = [];
    foreach ($graph_nodes as $node) {
      $rows[] = [$node, $this->t('Observed in latest tick')];
    }
    if ($rows === []) {
      $rows[] = [$this->t('(none)'), $this->t('No step_results found in latest tick artifact.')];
    }

    $build['graph_shape'] = [
      '#type' => 'details',
      '#title' => $this->t('Observed Graph Shape (read-only)'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Node'), $this->t('Observation')],
        '#rows' => $rows,
      ],
      'source' => [
        '#markup' => '<p><strong>' . $this->t('Source') . ':</strong> ' . $this->sourcePath('inbox/responses/langgraph-ticks.jsonl') . '</p>',
      ],
    ];

    return $build;
  }

  /**
   * Test page.
   */
  public function test(): array {
    $sections = $this->sectionMap();
    $page = $sections['test'];
    $build = $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('test', (array) $page['subsections'])
    );

    $parity = $this->readParity();
    $errors = is_array($parity['errors'] ?? NULL) ? $parity['errors'] : [];
    $errors_text = $errors ? implode('; ', array_map('strval', $errors)) : '(none)';

    $build['parity_evidence'] = [
      '#type' => 'details',
      '#title' => $this->t('Current Validation Evidence (read-only)'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Field'), $this->t('Value')],
        '#rows' => [
          [$this->t('parity_ok'), isset($parity['parity_ok']) ? ((bool) $parity['parity_ok'] ? 'PASS' : 'FAIL') : 'unknown'],
          [$this->t('selected_agents.match'), isset($parity['selected_agents']['match']) ? ((bool) $parity['selected_agents']['match'] ? 'yes' : 'no') : 'unknown'],
          [$this->t('steps.match'), isset($parity['steps']['match']) ? ((bool) $parity['steps']['match'] ? 'yes' : 'no') : 'unknown'],
          [$this->t('generated_at'), (string) ($parity['generated_at'] ?? 'unknown')],
          [$this->t('errors'), $errors_text],
        ],
      ],
      'source' => [
        '#markup' => '<p><strong>' . $this->t('Source') . ':</strong> ' . $this->sourcePath('inbox/responses/langgraph-parity-latest.json') . '</p>',
      ],
    ];

    return $build;
  }

  /**
   * Run page.
   */
  public function run(): array {
    $sections = $this->sectionMap();
    $page = $sections['run'];
    $build = $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('run', (array) $page['subsections'])
    );

    $ticks = $this->readTicks();
    $ticks = array_slice($ticks, -25);
    $rows = [];
    foreach (array_reverse($ticks) as $tick) {
      $rows[] = [
        (string) ($tick['ts'] ?? ''),
        !empty($tick['dry_run']) ? 'yes' : 'no',
        !empty($tick['publish_enabled']) ? 'yes' : 'no',
        (string) ($tick['provider'] ?? ''),
        (string) ($tick['agent_cap'] ?? ''),
        (string) $this->countTickErrors($tick),
      ];
    }

    $build['run_timeline'] = [
      '#type' => 'details',
      '#title' => $this->t('Recent Runs Timeline (read-only)'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Timestamp'),
          $this->t('dry_run'),
          $this->t('publish_enabled'),
          $this->t('provider'),
          $this->t('agent_cap'),
          $this->t('error_count'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No runtime ticks available.'),
      ],
      'source' => [
        '#markup' => '<p><strong>' . $this->t('Source') . ':</strong> ' . $this->sourcePath('inbox/responses/langgraph-ticks.jsonl') . '</p>',
      ],
    ];

    return $build;
  }

  /**
   * Observe page.
   */
  public function observe(): array {
    $sections = $this->sectionMap();
    $page = $sections['observe'];
    $build = $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('observe', (array) $page['subsections'])
    );

    $latest_tick = $this->readLatestTick();
    $steps = is_array($latest_tick['step_results'] ?? NULL) ? $latest_tick['step_results'] : [];
    $rows = [];
    foreach ($steps as $node => $detail) {
      if ($node === 'summarize_tick' || !is_array($detail)) {
        continue;
      }
      $status = 'ok';
      if (isset($detail['error']) || !empty($detail['errors'])) {
        $status = 'error';
      }
      elseif (isset($detail['skipped'])) {
        $status = 'skipped';
      }

      $summary_parts = [];
      if (isset($detail['mode'])) {
        $summary_parts[] = 'mode=' . (string) $detail['mode'];
      }
      if (isset($detail['rc'])) {
        $summary_parts[] = 'rc=' . (string) $detail['rc'];
      }
      if (isset($detail['skipped'])) {
        $summary_parts[] = 'reason=' . (string) $detail['skipped'];
      }
      if (isset($detail['error'])) {
        $summary_parts[] = 'error=' . (string) $detail['error'];
      }

      $rows[] = [
        (string) $node,
        $status,
        $summary_parts ? implode('; ', $summary_parts) : 'ok',
      ];
    }

    $build['node_diagnostics'] = [
      '#type' => 'details',
      '#title' => $this->t('Latest Node Diagnostics (read-only)'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Node'), $this->t('Status'), $this->t('Details')],
        '#rows' => $rows,
        '#empty' => $this->t('No node-level diagnostics found in latest tick.'),
      ],
      'source' => [
        '#markup' => '<p><strong>' . $this->t('Source') . ':</strong> ' . $this->sourcePath('inbox/responses/langgraph-ticks.jsonl') . '</p>',
      ],
    ];

    return $build;
  }

  /**
   * Release page.
   */
  public function release(): array {
    $sections = $this->sectionMap();
    $page = $sections['release'];
    $build = $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('release', (array) $page['subsections'])
    );

    $release_rows = $this->readReleaseCycleRows();
    $build['release_state'] = [
      '#type' => 'details',
      '#title' => $this->t('Release Cycle State (read-only)'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Team'), $this->t('Current Release'), $this->t('Next Release'), $this->t('Source')],
        '#rows' => $release_rows,
        '#empty' => $this->t('No release-cycle state files detected.'),
      ],
    ];

    return $build;
  }

  /**
   * Admin page.
   */
  public function admin(): array {
    $sections = $this->sectionMap();
    $page = $sections['admin'];
    $build = $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('admin', (array) $page['subsections'])
    );

    $paths = $this->artifactPaths();
    $rows = [];
    foreach ($paths as $label => $path) {
      $exists = file_exists($path);
      $readable = is_readable($path);
      $size = ($exists && is_file($path)) ? (string) filesize($path) . ' bytes' : '-';
      $rows[] = [
        $label,
        $this->sourcePath($this->toRelativeHqPath($path)),
        $exists ? 'yes' : 'no',
        $readable ? 'yes' : 'no',
        $size,
      ];
    }

    $build['artifact_health'] = [
      '#type' => 'details',
      '#title' => $this->t('Artifact Health (read-only)'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Artifact'), $this->t('Path'), $this->t('Exists'), $this->t('Readable'), $this->t('Size')],
        '#rows' => $rows,
      ],
    ];

    return $build;
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
      // Unknown subsection: return a generic stub (route is valid, content is unmapped).
      $sub_title = ucwords(str_replace('-', ' ', $subsection));
      $sub_desc = 'Stub placeholder for this subsection. No content is mapped here yet.';
    }
    else {
      $sub_title = (string) $sub_info[0];
      $sub_desc = (string) $sub_info[1];
    }

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

  /**
   * Resolve HQ root path.
   */
  private function hqRoot(): string {
    return rtrim((string) (getenv('COPILOT_HQ_ROOT') ?: self::DEFAULT_HQ_ROOT), '/');
  }

  /**
   * Runtime artifact locations used by this console.
   *
   * @return array<string,string>
   */
  private function artifactPaths(): array {
    $hq_root = $this->hqRoot();
    return [
      'ticks' => $hq_root . '/inbox/responses/langgraph-ticks.jsonl',
      'parity' => $hq_root . '/inbox/responses/langgraph-parity-latest.json',
      'orchestrator_log' => $hq_root . '/inbox/responses/orchestrator-latest.log',
      'release_cycle_dir' => $hq_root . '/tmp/release-cycle-active',
      'release_control_legacy' => $hq_root . '/tmp/release-cycle-control.json',
      'release_control_default' => '/var/tmp/copilot-sessions-hq/release-cycle-control.json',
      'graph_definition' => $hq_root . '/orchestrator/langgraph/graph.py',
    ];
  }

  /**
   * Read full JSONL ticks array.
   *
   * @return array<int,array<string,mixed>>
   */
  private function readTicks(): array {
    $paths = $this->artifactPaths();
    $path = $paths['ticks'];
    if (!is_readable($path)) {
      return [];
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $rows = [];
    foreach ($lines as $line) {
      $decoded = json_decode(trim((string) $line), TRUE);
      if (is_array($decoded)) {
        $rows[] = $decoded;
      }
    }
    return $rows;
  }

  /**
   * Read latest tick object.
   *
   * @return array<string,mixed>
   */
  private function readLatestTick(): array {
    $ticks = $this->readTicks();
    if (!$ticks) {
      return [];
    }
    $latest = end($ticks);
    return is_array($latest) ? $latest : [];
  }

  /**
   * Read parity payload.
   *
   * @return array<string,mixed>
   */
  private function readParity(): array {
    $paths = $this->artifactPaths();
    $path = $paths['parity'];
    if (!is_readable($path)) {
      return [];
    }
    $raw = (string) @file_get_contents($path);
    $decoded = json_decode($raw, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Read release-cycle control state with fallback order.
   *
   * @return array<string,mixed>
   */
  private function readReleaseControl(): array {
    $paths = $this->artifactPaths();
    $default_path = (string) (getenv('RELEASE_CYCLE_CONTROL_FILE') ?: $paths['release_control_default']);
    $legacy_path = $paths['release_control_legacy'];

    foreach ([$default_path, $legacy_path] as $path) {
      if (!is_readable($path)) {
        continue;
      }
      $raw = (string) @file_get_contents($path);
      $decoded = json_decode($raw, TRUE);
      if (is_array($decoded)) {
        return $decoded;
      }
    }
    return [];
  }

  /**
   * Build release-cycle state rows.
   *
   * @return array<int,array<int,string>>
   */
  private function readReleaseCycleRows(): array {
    $paths = $this->artifactPaths();
    $dir = $paths['release_cycle_dir'];
    if (!is_dir($dir)) {
      return [];
    }

    $files = glob($dir . '/*.release_id') ?: [];
    sort($files);
    $rows = [];
    foreach ($files as $file) {
      $team = basename((string) $file, '.release_id');
      $current = trim((string) @file_get_contents((string) $file));
      $next_file = $dir . '/' . $team . '.next_release_id';
      $next = is_readable($next_file) ? trim((string) @file_get_contents($next_file)) : '';
      $rows[] = [
        (string) $team,
        $current !== '' ? $current : '-',
        $next !== '' ? $next : '-',
        $this->sourcePath('tmp/release-cycle-active/' . $team . '.release_id'),
      ];
    }

    return $rows;
  }

  /**
   * Count errors on a tick row.
   */
  private function countTickErrors(array $tick): int {
    $count = count((array) ($tick['errors'] ?? []));
    $steps = is_array($tick['step_results'] ?? NULL) ? $tick['step_results'] : [];
    foreach ($steps as $step) {
      if (!is_array($step)) {
        continue;
      }
      if (($step['status'] ?? '') === 'error' || !empty($step['errors']) || isset($step['error'])) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * Human-readable age from timestamp string.
   */
  private function formatAgeFromTimestamp(string $ts): string {
    if ($ts === '') {
      return 'unknown';
    }
    $value = strtotime($ts);
    if ($value === FALSE) {
      return 'unknown';
    }
    return (string) max(0, time() - $value) . 's';
  }

  /**
   * Convert absolute HQ path to relative label when possible.
   */
  private function toRelativeHqPath(string $path): string {
    $root = $this->hqRoot() . '/';
    if (str_starts_with($path, $root)) {
      return substr($path, strlen($root));
    }
    return $path;
  }

  /**
   * Build readable source path label.
   */
  private function sourcePath(string $relative): string {
    return $relative;
  }

}
