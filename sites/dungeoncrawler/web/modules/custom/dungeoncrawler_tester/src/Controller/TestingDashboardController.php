<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_tester\Form\DashboardRunsForm;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Process;

/**
 * Testing dashboard with stagegates and GitHub failure surfacing.
 */
class TestingDashboardController extends ControllerBase {

  /**
   * HTTP client for GitHub API calls.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * State service for persisting last run metadata.
   */
  protected StateInterface $state;

  /**
   * Queue factory for reading queue status/items.
   */
  protected QueueFactory $queueFactory;

  /**
   * Database connection for watchdog reads.
   */
  protected Connection $database;

  /**
   * Default repository for issue lookups.
   */
  private string $defaultRepo = 'keithaumiller/forseti.life';

  /**
   * Base path for documentation relative to module path.
   */
  private string $docsRelativePath = '../../../../docs/dungeoncrawler';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->httpClient = $container->get('http_client');
    $instance->configFactory = $container->get('config.factory');
    $instance->state = $container->get('state');
    $instance->queueFactory = $container->get('queue');
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * Render the testing dashboard.
   */
  public function dashboard(): array {
    $settings = $this->configFactory->get('ai_conversation.settings');
    $repo = $settings->get('copilot_default_repo') ?: $this->defaultRepo;
    $token = $settings->get('copilot_token') ?: (getenv('GITHUB_TOKEN_COPILOT') ?: getenv('GITHUB_TOKEN'));

    $issueSections = [
      'ci_failures' => $this->t('Recent CI / test failures (ci-failure)'),
      'testing_defects' => $this->t('Testing defects (testing-defect)'),
      'program_defects' => $this->t('Program defects (program-defect)'),
    ];

    $issues = [];
    foreach ($issueSections as $key => $label) {
      $issues[$key] = $this->fetchIssues($repo, $token, $key);
      $issues[$key]['label'] = $label;
    }

    $queue_items = $this->loadQueueItems();
    $queue_status = $this->getQueueStatus();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dungeoncrawler-testing-dashboard']],
      'queue' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['dungeoncrawler-queue-embedded']],
        'ui' => [
          '#theme' => 'dungeoncrawler_tester_queue_management',
          '#queue_items' => $queue_items,
          '#queue_status' => $queue_status,
        ],
      ],
      'thetest_callout' => $this->buildTheTestCallout(),
      'flow' => $this->buildProcessFlowSection(),
      'stages' => $this->formBuilder()->getForm(DashboardRunsForm::class),
      'overview' => $this->buildCapabilitiesSection(),
      'documentation' => $this->buildDocumentationSection(),
      'roadmap' => $this->buildRoadmapSection(),
      'issues' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['dungeoncrawler-testing-issues']],
        'ci' => $this->renderIssueList($issues['ci_failures']),
        'testing_defects' => $this->renderIssueList($issues['testing_defects']),
        'program_defects' => $this->renderIssueList($issues['program_defects']),
      ],
      '#attached' => [
        'library' => [
          'dungeoncrawler_tester/dashboard',
          'dungeoncrawler_tester/queue-management',
        ],
        'drupalSettings' => [
          'dungeoncrawlerTester' => [
            'csrfToken' => \Drupal::csrfToken()->get('rest'),
            'routes' => [
              'run' => Url::fromRoute('dungeoncrawler_tester.queue_run')->toString(),
              'status' => Url::fromRoute('dungeoncrawler_tester.queue_status')->toString(),
              'logs' => Url::fromRoute('dungeoncrawler_tester.queue_logs')->toString(),
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Build the top-level process flow that anchors the page layout.
   */
  private function buildProcessFlowSection(): array {
    $process = $this->buildProcessFlow();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-flow']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Release Testing Stagegates'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $process,
      ],
      'note' => [
        '#markup' => '<p>' . $this->t('Each stage below includes run buttons (commands) and a reports placeholder.') . '</p>',
      ],
    ];
  }

  /**
   * Build concise capabilities overview for the dashboard.
   */
  private function buildCapabilitiesSection(): array {
    $items = [
      $this->t('Dashboard lives at /dungeoncrawler/testing (administer site configuration).'),
      $this->t('Sections: stagegates, documentation links, quick test commands, GitHub issue surfacing.'),
      $this->t('Issue surfacing pulls ci-failure, testing-defect, and program-defect labels using ai_conversation token (or env token fallback).'),
      $this->t('Tester navigation block links to tester README, testing guides, and issue queue.'),
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-capabilities']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Current Capabilities'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Highlight the /thetest flip hook for automation verification.
   */
  private function buildTheTestCallout(): array {
    $link = Link::fromTextAndUrl(
      $this->t('Open /thetest page'),
      Url::fromRoute('dungeoncrawler_tester.thetest')
    )->toRenderable();
    $link['#attributes']['class'][] = 'queue-link';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['thetest-callout']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Automation flip test (/thetest)'),
      ],
      'body' => [
        '#markup' => '<p>' . $this->t('This page drives the pre-commit stage “Pre-commit: thetest toggle”. The code currently emits TEST:FAIL until the constant in TheTestController is flipped to pass. Use this to validate auto-pause, issue linking, and resume flows.') . '</p>',
      ],
      'cta' => $link,
    ];
  }

  /**
   * Build documentation links section.
   */
  private function buildDocumentationSection(): array {
    $moduleBasePath = \Drupal::service('extension.list.module')->getPath('dungeoncrawler_tester');
    $docsBasePath = $moduleBasePath . '/' . $this->docsRelativePath;
    
    $links = [
      Link::fromTextAndUrl(
        $this->t('Module README'),
        Url::fromUri('base:' . $moduleBasePath . '/README.md')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Testing Module README'),
        Url::fromUri('base:' . $moduleBasePath . '/tests/TESTING_MODULE_README.md')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Tests README'),
        Url::fromUri('base:' . $moduleBasePath . '/tests/README.md')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Testing Strategy Design'),
        Url::fromUri('base:' . $docsBasePath . '/issues/issue-testing-strategy-design.md')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Testing Quick Start Guide'),
        Url::fromUri('base:' . $docsBasePath . '/testing/README.md')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Testing Issues Directory'),
        Url::fromUri('base:' . $docsBasePath . '/issues/')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('GitHub Issues (testing-related)'),
        Url::fromUri('https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting')
      )->toRenderable(),
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['documentation-links']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Test Documentation'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $links,
      ],
    ];
  }

  /**
   * Build test commands section.
   */
  private function buildTestCommandsSection(): array {
    $commands = [
      [
        'title' => $this->t('Run All Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml',
      ],
      [
        'title' => $this->t('Unit Tests Only'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --testsuite=unit',
      ],
      [
        'title' => $this->t('Functional Tests Only'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --testsuite=functional',
      ],
      [
        'title' => $this->t('Route Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/Routes/',
      ],
      [
        'title' => $this->t('Controller Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/Controller/',
      ],
      [
        'title' => $this->t('API Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --group=api',
      ],
      [
        'title' => $this->t('Campaign/Entity Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/CampaignStateAccessTest.php tests/src/Functional/CampaignStateValidationTest.php tests/src/Functional/EntityLifecycleTest.php',
      ],
      [
        'title' => $this->t('Character Creation Workflow'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --group=character-creation',
      ],
      [
        'title' => $this->t('PF2e Rules Validation'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --group=pf2e-rules',
      ],
      [
        'title' => $this->t('With Coverage Report'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --coverage-html tests/coverage',
      ],
    ];

    $items = [];
    foreach ($commands as $cmd) {
      $items[] = [
        '#type' => 'container',
        'title' => [
          '#markup' => '<strong>' . $cmd['title'] . '</strong>',
        ],
        'command' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => $cmd['command'],
          '#attributes' => ['class' => ['command-snippet']],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['test-commands']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Quick Test Commands'),
      ],
      'description' => [
        '#markup' => '<p>' . $this->t('Copy and paste these commands to run different test suites:') . '</p>',
      ],
      'commands' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['command-list']],
      ],
    ];
  }

  /**
   * Build roadmap list anchored to the dashboard.
   */
  private function buildRoadmapSection(): array {
    $items = [
      $this->t('Add kernel and unit suites for service/business logic.'),
      $this->t('Add integration flows (character + campaign + combat) with real entity fixtures.'),
      $this->t('Surface CI status and last test run results directly on the dashboard.'),
      $this->t('Add quick actions (drush/phpunit presets) and environment checks (config status, tokens present).'),
      $this->t('Add performance and load probes for API endpoints.'),
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-roadmap']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Roadmap (Dashboard as Anchor)'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Build stagegate process flow top-to-bottom.
   */
  private function buildProcessFlow(): array {
    $definitions = $this->getStageDefinitions();
    $items = [];
    foreach ($definitions as $stage) {
      $items[] = $stage['flow'];
    }
    return $items;
  }

  /**
   * Fetch issues with specific label filters.
   */
  private function fetchIssues(string $repo, ?string $token, string $kind): array {
    $labelMap = [
      'ci_failures' => 'ci-failure',
      'testing_defects' => 'testing-defect',
      'program_defects' => 'program-defect',
    ];

    $label = $labelMap[$kind] ?? 'ci-failure';
    $url = "https://api.github.com/repos/{$repo}/issues?state=open&labels=" . urlencode($label) . "&per_page=10";

    if (!$token) {
      return ['items' => [], 'error' => $this->t('No GitHub token configured.')];
    }

    try {
      $response = $this->httpClient->request('GET', $url, [
        'headers' => [
          'Authorization' => "Bearer {$token}",
          'Accept' => 'application/vnd.github+json',
          'User-Agent' => 'dungeoncrawler-tester-dashboard',
        ],
        'timeout' => 10,
      ]);
      $status = $response->getStatusCode();
      if ($status >= 200 && $status < 300) {
        $data = json_decode((string) $response->getBody(), TRUE) ?: [];
        $items = [];
        foreach ($data as $issue) {
          $items[] = Link::fromTextAndUrl($issue['title'], Url::fromUri($issue['html_url']))->toRenderable();
        }
        return ['items' => $items, 'error' => NULL];
      }

      return ['items' => [], 'error' => $this->t('GitHub responded with status @s', ['@s' => $status])];
    }
    catch (GuzzleException $e) {
      return ['items' => [], 'error' => $this->t('GitHub request failed: @m', ['@m' => $e->getMessage()])];
    }
  }

  /**
   * Load active queue items for display.
   */
  private function loadQueueItems(): array {
    $queue_items = [];

    $query = $this->database->select('queue', 'q')
      ->fields('q', ['item_id', 'data', 'expire', 'created'])
      ->condition('name', 'dungeoncrawler_tester_runs');
    $results = $query->execute()->fetchAll();

    foreach ($results as $row) {
      $data = unserialize($row->data);
      $preview = $this->getQueueItemPreview($data);
      $queue_items[] = [
        'item_id' => $row->item_id,
        'queue_name' => 'dungeoncrawler_tester_runs',
        'queue_label' => $this->t('Testing Runs'),
        'created' => $row->created,
        'expire' => $row->expire,
        'data' => $data,
        'data_preview' => $preview,
      ];
    }

    usort($queue_items, fn($a, $b) => $b['created'] <=> $a['created']);
    return $queue_items;
  }

  private function getQueueItemPreview($data): array {
    $preview = [];
    if (is_array($data)) {
      if (!empty($data['stage_id'])) {
        $preview['stage'] = $data['stage_id'];
      }
      if (!empty($data['display'])) {
        $preview['command'] = $data['display'];
      }
      if (!empty($data['job_id'])) {
        $preview['job_id'] = $data['job_id'];
      }
    }
    return $preview;
  }

  /**
   * Build queue status for UI.
   */
  private function getQueueStatus(): array {
    $queue_id = 'dungeoncrawler_tester_runs';
    $queue = $this->queueFactory->get($queue_id);

    return [
      $queue_id => [
        'id' => $queue_id,
        'name' => $this->t('Testing Runs'),
        'description' => $this->t('Background execution of dashboard run jobs.'),
        'icon' => '🧪',
        'items' => $queue->numberOfItems(),
      ],
    ];
  }

  /**
   * Shared stage definitions with flow labels and command metadata.
   */
  public function getStageDefinitions(): array {
    $root = $this->getProjectRoot();

    return [
      [
        'id' => 'precommit',
        'flow' => $this->t('Pre-commit: lint/format + unit (CharacterCalculator, CombatCalculator)'),
        'title' => $this->t('Pre-commit: lint/format + unit'),
        'description' => $this->t('Keep fast checks green before pushing.'),
        'commands' => [
          [
            'label' => $this->t('Unit suite'),
            'args' => ['./vendor/bin/phpunit', '--testsuite=unit'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --testsuite=unit',
          ],
        ],
      ],
      [
        'id' => 'functional-routes',
        'flow' => $this->t('Functional routes/controllers: public, admin, character, campaign, API endpoints'),
        'title' => $this->t('Functional routes/controllers'),
        'description' => $this->t('Public, admin, character, campaign, API endpoints.'),
        'commands' => [
          [
            'label' => $this->t('Routes'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'tests/src/Functional/Routes/'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/Routes/',
          ],
          [
            'label' => $this->t('Controllers'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'tests/src/Functional/Controller/'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/Controller/',
          ],
          [
            'label' => $this->t('API group'),
            'args' => ['./vendor/bin/phpunit', '--group=api'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --group=api',
          ],
        ],
      ],
      [
        'id' => 'character-workflow',
        'flow' => $this->t('Character creation workflow: 8-step wizard, validation, persistence (see workflow tests)'),
        'title' => $this->t('Character creation workflow'),
        'description' => $this->t('8-step wizard, validation, persistence.'),
        'commands' => [
          [
            'label' => $this->t('Workflow group'),
            'args' => ['./vendor/bin/phpunit', '--group=character-creation'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --group=character-creation',
          ],
        ],
      ],
      [
        'id' => 'entity-campaign',
        'flow' => $this->t('Entity/campaign APIs: state validation/access, entity lifecycle'),
        'title' => $this->t('Entity/campaign APIs'),
        'description' => $this->t('State validation, access, lifecycle.'),
        'commands' => [
          [
            'label' => $this->t('Entity lifecycle trio'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'tests/src/Functional/CampaignStateAccessTest.php', 'tests/src/Functional/CampaignStateValidationTest.php', 'tests/src/Functional/EntityLifecycleTest.php'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/CampaignStateAccessTest.php tests/src/Functional/CampaignStateValidationTest.php tests/src/Functional/EntityLifecycleTest.php',
          ],
        ],
      ],
      [
        'id' => 'fixtures',
        'flow' => $this->t('Cross-check fixtures: PF2e reference + character fixtures up to date'),
        'title' => $this->t('Cross-check fixtures'),
        'description' => $this->t('PF2e reference + character fixtures up to date.'),
        'commands' => [
          [
            'label' => $this->t('PF2e rules group'),
            'args' => ['./vendor/bin/phpunit', '--group=pf2e-rules'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --group=pf2e-rules',
          ],
        ],
      ],
      [
        'id' => 'ci-gate',
        'flow' => $this->t('CI gate: all suites green; failures auto-filed to GitHub (ci-failure label)'),
        'title' => $this->t('CI gate'),
        'description' => $this->t('All suites green; failures auto-filed.'),
        'commands' => [
          [
            'label' => $this->t('Full suite with coverage'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--coverage-html', 'tests/coverage'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --coverage-html tests/coverage',
          ],
        ],
      ],
      [
        'id' => 'signoff',
        'flow' => $this->t('Release sign-off: no open ci-failure/testing-defect blocking issues'),
        'title' => $this->t('Release sign-off'),
        'description' => $this->t('No open ci-failure/testing-defect blocking issues.'),
        'commands' => [
          [
            'label' => $this->t('Review open defects'),
            'args' => [],
            'cwd' => $root,
            'display' => 'Open GitHub issues (ci-failure, testing-defect)',
            'link' => 'https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Aci-failure+label%3Atesting-defect',
          ],
        ],
      ],
    ];
  }

  /**
   * Get project root (Drupal web root parent).
   */
  private function getProjectRoot(): string {
    return dirname(\Drupal::root());
  }

  /**
   * Fetch last run metadata for a stage.
   */
  private function getLastRun(string $stage_id): ?array {
    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    return $runs[$stage_id] ?? NULL;
  }

  /**
   * Render last run status block.
   */
  private function buildRunStatus(?array $run): array {
    if (!$run) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['stage-run-status']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $this->t('Last run'),
        ],
        'content' => [
          '#markup' => '<p>' . $this->t('No runs yet.') . '</p>',
        ],
      ];
    }

    $status = $run['exit_code'] === 0 ? $this->t('Passed') : $this->t('Failed');
    $time = $this->dateFormatter()->format($run['ended'], 'short');
    $duration = isset($run['duration']) ? sprintf('%.1fs', $run['duration']) : '';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['stage-run-status']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Last run'),
      ],
      'content' => [
        '#markup' => '<p><strong>' . $status . '</strong> · ' . $time . ' · ' . $duration . '</p>',
      ],
      'log' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $run['output'] ?? '',
        '#attributes' => ['class' => ['command-snippet', 'command-log']],
      ],
    ];
  }

  /**
   * Render issue list container.
   */
  private function renderIssueList(array $data): array {
    if (!empty($data['error'])) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['issue-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $data['label'] ?? $this->t('Issues'),
        ],
        'error' => [
          '#markup' => '<div class="messages messages--error">' . $data['error'] . '</div>',
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['issue-card']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $data['label'] ?? $this->t('Issues'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $data['items'] ?? [],
        '#empty' => $this->t('No open issues for this category.'),
      ],
    ];
  }

}
