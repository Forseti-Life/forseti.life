<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
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
   * Logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * Default repository for issue lookups.
   */
  private string $defaultRepo = 'keithaumiller/forseti.life';

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
   * Render tester documentation home page.
   */
  public function documentationHome(): array {
    $coreLinks = [
      Link::fromTextAndUrl($this->t('Getting Started'), Url::fromRoute('dungeoncrawler_tester.docs_getting_started')),
      Link::fromTextAndUrl($this->t('Test Execution Playbook'), Url::fromRoute('dungeoncrawler_tester.docs_execution_playbook')),
      Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
      Link::fromTextAndUrl($this->t('Automated Testing Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_process_flow')),
    ];

    $strategyLinks = [
      Link::fromTextAndUrl($this->t('Legacy: Module README page'), Url::fromRoute('dungeoncrawler_tester.docs_module_readme')),
      Link::fromTextAndUrl($this->t('Legacy: Testing Module README page'), Url::fromRoute('dungeoncrawler_tester.docs_testing_module_readme')),
      Link::fromTextAndUrl($this->t('Legacy: Tests README page'), Url::fromRoute('dungeoncrawler_tester.docs_tests_readme')),
    ];

    $liveLinks = [
      Link::fromTextAndUrl($this->t('Testing Dashboard'), Url::fromRoute('dungeoncrawler_tester.dashboard')),
      Link::fromTextAndUrl($this->t('Tester Queue Management'), Url::fromRoute('dungeoncrawler_tester.queue_management')),
      Link::fromTextAndUrl($this->t('Tester Settings'), Url::fromRoute('dungeoncrawler_tester.settings')),
      Link::fromTextAndUrl($this->t('Copilot Issue Automation page'), Url::fromRoute('dungeoncrawler_tester.docs_issue_automation')),
      Link::fromTextAndUrl($this->t('GitHub Issues (testing-related)'), Url::fromUri('https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting')),
    ];

    $coreItems = $this->renderLinkItems($coreLinks);
    $strategyItems = $this->renderLinkItems($strategyLinks);
    $liveItems = $this->renderLinkItems($liveLinks);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-home']],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'max-age' => self::GITHUB_CACHE_TTL,
      ],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'justify-content-center']],
        'col' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-10']],
          'header_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $this->t('Tester Documentation Home'),
            ],
            'intro' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light', 'mb-0']],
              '#value' => $this->t('Central entry point for all Dungeon Crawler tester documentation, testing strategy references, and live workflow links.'),
            ],
          ],
          'core_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'core_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Standard Testing Documentation'),
            ],
            'core_list' => [
              '#theme' => 'item_list',
              '#items' => $coreItems,
            ],
          ],
          'strategy_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'strategy_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Legacy Route Aliases (Compatibility)'),
            ],
            'strategy_list' => [
              '#theme' => 'item_list',
              '#items' => $strategyItems,
            ],
          ],
          'live_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
            'live_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Live Workflow Links'),
            ],
            'live_list' => [
              '#theme' => 'item_list',
              '#items' => $liveItems,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Render consolidated getting started documentation page.
   */
  public function docsGettingStarted(): array {
    return $this->buildDocPage(
      $this->t('Getting Started'),
      $this->t('Standard onboarding path for the Dungeon Crawler testing module.'),
      [
        $this->t('Scope: this module owns test harnesses, test suites, and testing dashboard integrations.'),
        $this->t('Entry points: start at /dungeoncrawler/testing for dashboard controls and linked documentation.'),
        $this->t('Prerequisites: tester settings configured with repository/token when failure issue automation is needed.'),
        $this->t('First run: execute focused tests first, then broader suites as confidence increases.'),
      ],
      [
        Link::fromTextAndUrl($this->t('Test Execution Playbook'), Url::fromRoute('dungeoncrawler_tester.docs_execution_playbook')),
        Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
      ]
    );
  }

  /**
   * Render consolidated test execution playbook.
   */
  public function docsExecutionPlaybook(): array {
    return $this->buildDocPage(
      $this->t('Test Execution Playbook'),
      $this->t('Standard structure for planning and running test stages.'),
      [
        $this->t('Plan by stage: pre-commit checks, focused functional coverage, then full confidence runs.'),
        $this->t('Use dashboard command snippets to keep local runs aligned with expected workflows.'),
        $this->t('On failure, capture output and stage context before rerunning to preserve root-cause evidence.'),
        $this->t('Use queue management to pause, resume, and verify stage progression intentionally.'),
      ],
      [
        Link::fromTextAndUrl($this->t('Testing Dashboard'), Url::fromRoute('dungeoncrawler_tester.dashboard')),
        Link::fromTextAndUrl($this->t('Tester Queue Management'), Url::fromRoute('dungeoncrawler_tester.queue_management')),
      ]
    );
  }

  /**
   * Render consolidated failure triage and issue workflow page.
   */
  public function docsFailureTriage(): array {
    return $this->buildDocPage(
      $this->t('Failure Triage and Issue Workflow'),
      $this->t('Standard response flow for failed stages and GitHub issue automation.'),
      [
        $this->t('Triage sequence: identify failing stage, inspect output, validate reproducibility, and scope impact.'),
        $this->t('Issue lifecycle: open issue on failure, attach stage context, and track remediation until stage pass.'),
        $this->t('Assignment behavior: Copilot assignment attempts API identifiers then CLI fallback for compatibility.'),
        $this->t('Operational controls: keep labels consistent for CI failures, testing defects, and program defects.'),
      ],
      [
        Link::fromTextAndUrl($this->t('Tester Settings'), Url::fromRoute('dungeoncrawler_tester.settings')),
        Link::fromTextAndUrl($this->t('GitHub Issues (testing-related)'), Url::fromUri('https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting')),
      ]
    );
  }

  /**
   * Render process flow documentation page.
   */
  public function docsProcessFlow(): array {
    return $this->buildDocPage(
      $this->t('Automated Testing Process Flow'),
      $this->t('Operational flow with synchronous/asynchronous subprocesses, timing windows, and blocking gates.'),
      [
        $this->t('Synchronous surfaces: dashboard queue actions and in-worker state transitions.'),
        $this->t('Asynchronous surfaces: stage auto-enqueue cadence and queue worker command execution.'),
        $this->t('Timing controls: 3600s enqueue interval gate, 1800s command timeout, 10s GitHub API timeout, 20s CLI fallback timeout.'),
        $this->t('Blocking gates: inactive stage, open issue lock, pending/running lock, failed-stage auto-pause.'),
        $this->t('Canonical source file: PROCESS_FLOW.md in this module; update it with code changes affecting flow behavior.'),
      ],
      [
        Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
        Link::fromTextAndUrl($this->t('Tester Queue Management'), Url::fromRoute('dungeoncrawler_tester.queue_management')),
      ]
    );
  }

  /**
   * Legacy route alias for module overview docs.
   */
  public function docsModuleReadme(): array {
    return $this->docsGettingStarted();
  }

  /**
   * Legacy route alias for testing module guide docs.
   */
  public function docsTestingModuleReadme(): array {
    return $this->docsGettingStarted();
  }

  /**
   * Legacy route alias for tests README docs.
   */
  public function docsTestsReadme(): array {
    return $this->docsExecutionPlaybook();
  }

  /**
   * Legacy route alias for strategy design docs.
   */
  public function docsStrategyDesign(): array {
    return $this->docsExecutionPlaybook();
  }

  /**
   * Legacy route alias for quick start docs.
   */
  public function docsQuickStart(): array {
    return $this->docsExecutionPlaybook();
  }

  /**
   * Legacy route alias for issues directory docs.
   */
  public function docsIssuesDirectory(): array {
    return $this->docsFailureTriage();
  }

  /**
   * Legacy route alias for issue automation docs.
   */
  public function docsIssueAutomation(): array {
    return $this->docsFailureTriage();
  }

  /**
   * Build a shared docs page layout.
   *
   * @param string $title
   *   Page title.
   * @param string $intro
   *   Intro text.
   * @param array $items
   *   Primary bullet items.
   * @param array $relatedLinks
   *   Related links as Link objects.
   */
  private function buildDocPage(string $title, string $intro, array $items, array $relatedLinks = []): array {
    $related = $this->renderLinkItems($relatedLinks);

    $backToHome = Link::fromTextAndUrl(
      $this->t('Back to Documentation Home'),
      Url::fromRoute('dungeoncrawler_tester.documentation_home')
    );
    $related = array_merge($related, $this->renderLinkItems([$backToHome]));

    $itemMarkup = [];
    foreach ($items as $item) {
      $itemMarkup[] = ['#markup' => $item];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-page']],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'max-age' => self::GITHUB_CACHE_TTL,
      ],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'justify-content-center']],
        'col' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-10']],
          'summary_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $title,
            ],
            'intro' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light', 'mb-0']],
              '#value' => $intro,
            ],
          ],
          'details_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'items_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Key Points'),
            ],
            'items' => [
              '#theme' => 'item_list',
              '#items' => $itemMarkup,
            ],
          ],
          'related_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
            'related_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Related Links'),
            ],
            'related' => [
              '#theme' => 'item_list',
              '#items' => $related,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Convert links to themed render arrays.
   *
   * @param array $links
   *   Array of Link objects.
   *
   * @return array
   *   Renderable link items.
   */
  private function renderLinkItems(array $links): array {
    $items = [];

    foreach ($links as $link) {
      $render = $link->toRenderable();
      $render['#attributes']['class'][] = 'link-cyan';
      $items[] = $render;
    }

    return $items;
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
    $links = [
      Link::fromTextAndUrl(
        $this->t('Documentation Home (all tester docs)'),
        Url::fromRoute('dungeoncrawler_tester.documentation_home')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Getting Started'),
        Url::fromRoute('dungeoncrawler_tester.docs_getting_started')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Test Execution Playbook'),
        Url::fromRoute('dungeoncrawler_tester.docs_execution_playbook')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Failure Triage and Issue Workflow'),
        Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Automated Testing Process Flow'),
        Url::fromRoute('dungeoncrawler_tester.docs_process_flow')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Tester Queue Management'),
        Url::fromRoute('dungeoncrawler_tester.queue_management')
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
