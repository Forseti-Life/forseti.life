<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Link;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_tester\Form\DashboardRunsForm;
use Drupal\dungeoncrawler_tester\Service\StageDefinitionService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
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
   * Date formatter service.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Stage definitions service.
   */
  protected StageDefinitionService $stageDefinitions;

  /**
   * Module extension list service.
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * Logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * Default repository for issue lookups.
   */
  private string $defaultRepo = 'keithaumiller/forseti.life';

  /**
   * Base path for documentation relative to module path.
   */
  private string $docsRelativePath = '../../../../docs/dungeoncrawler';

  /**
   * GitHub API timeout in seconds.
   */
  private const GITHUB_API_TIMEOUT = 10;

  /**
   * Maximum issues to fetch per request.
   */
  private const GITHUB_MAX_ISSUES = 10;

  /**
   * Maximum queue items to display.
   */
  private const MAX_QUEUE_ITEMS = 50;

  /**
   * GitHub API cache TTL in seconds (10 minutes).
   */
  private const GITHUB_CACHE_TTL = 600;

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
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->stageDefinitions = $container->get('dungeoncrawler_tester.stage_definitions');
    $instance->moduleExtensionList = $container->get('extension.list.module');
    $instance->logger = $container->get('logger.factory')->get('dungeoncrawler_tester');
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
      '#cache' => [
        'tags' => ['dungeoncrawler_tester.dashboard', 'dungeoncrawler_tester.queue'],
        'contexts' => ['user.permissions'],
        'max-age' => self::GITHUB_CACHE_TTL,
      ],
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
   * Build concise process flow for stagegate overview.
   */
  private function buildProcessFlowSection(): array {
    $definitions = $this->stageDefinitions->getDefinitions();
    $items = [];
    foreach ($definitions as $stage) {
      $items[] = $stage['title'] ?? $stage['id'];
    }

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
        '#items' => $items,
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
    $moduleBasePath = $this->moduleExtensionList->getPath('dungeoncrawler_tester');
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
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Routes/',
      ],
      [
        'title' => $this->t('Controller Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Controller/',
      ],
      [
        'title' => $this->t('API Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --group=api',
      ],
      [
        'title' => $this->t('Campaign/Entity Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/CampaignStateAccessTest.php web/modules/custom/dungeoncrawler_tester/tests/src/Functional/CampaignStateValidationTest.php web/modules/custom/dungeoncrawler_tester/tests/src/Functional/EntityLifecycleTest.php',
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
   * Fetch issues with specific label filters (with caching).
   */
  private function fetchIssues(string $repo, ?string $token, string $kind): array {
    $labelMap = [
      'ci_failures' => 'ci-failure',
      'testing_defects' => 'testing-defect',
      'program_defects' => 'program-defect',
    ];

    $label = $labelMap[$kind] ?? 'ci-failure';
    
    if (!$token) {
      $this->logger->warning('No GitHub token configured for issue fetching.');
      return ['items' => [], 'error' => $this->t('No GitHub token configured.')];
    }

    // Check cache first
    $cache_key = 'dungeoncrawler_tester.github_issues.' . $repo . '.' . $label;
    $cache = \Drupal::cache()->get($cache_key);
    if ($cache && !empty($cache->data)) {
      return $cache->data;
    }

    $url = "https://api.github.com/repos/{$repo}/issues?state=open&labels=" . urlencode($label) . "&per_page=" . self::GITHUB_MAX_ISSUES;

    try {
      $response = $this->httpClient->request('GET', $url, [
        'headers' => [
          'Authorization' => "Bearer {$token}",
          'Accept' => 'application/vnd.github+json',
          'User-Agent' => 'dungeoncrawler-tester-dashboard',
        ],
        'timeout' => self::GITHUB_API_TIMEOUT,
      ]);
      $status = $response->getStatusCode();
      if ($status >= 200 && $status < 300) {
        $data = json_decode((string) $response->getBody(), TRUE) ?: [];
        $items = [];
        foreach ($data as $issue) {
          $items[] = Link::fromTextAndUrl($issue['title'], Url::fromUri($issue['html_url']))->toRenderable();
        }
        $result = ['items' => $items, 'error' => NULL];
        
        // Cache successful response
        \Drupal::cache()->set($cache_key, $result, time() + self::GITHUB_CACHE_TTL);
        
        return $result;
      }

      $error_msg = $this->t('GitHub responded with status @s', ['@s' => $status]);
      $this->logger->error('GitHub API returned non-200 status: @status for @url', [
        '@status' => $status,
        '@url' => $url,
      ]);
      return ['items' => [], 'error' => $error_msg];
    }
    catch (GuzzleException $e) {
      $error_msg = $this->t('GitHub request failed: @m', ['@m' => $e->getMessage()]);
      $this->logger->error('GitHub API request failed: @message for @url', [
        '@message' => $e->getMessage(),
        '@url' => $url,
      ]);
      return ['items' => [], 'error' => $error_msg];
    }
  }

  /**
   * Load active queue items for display (limited).
   */
  private function loadQueueItems(): array {
    $queue_items = [];

    $query = $this->database->select('queue', 'q')
      ->fields('q', ['item_id', 'data', 'expire', 'created'])
      ->condition('name', 'dungeoncrawler_tester_runs')
      ->orderBy('created', 'DESC')
      ->range(0, self::MAX_QUEUE_ITEMS);
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
    $time = $this->dateFormatter->format($run['ended'], 'short');
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
