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
    return $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('home', (array) $page['subsections'])
    );
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
   * Run page.
   */
  public function run(): array {
    $sections = $this->sectionMap();
    $page = $sections['run'];
    return $this->buildPage(
      (string) $page['title'],
      (string) $page['description'],
      $this->buildSectionRows('run', (array) $page['subsections'])
    );
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
