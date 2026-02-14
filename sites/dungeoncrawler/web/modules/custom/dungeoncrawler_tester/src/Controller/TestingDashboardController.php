<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Default repository for issue lookups.
   */
  private string $defaultRepo = 'keithaumiller/forseti.life';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->httpClient = $container->get('http_client');
    $instance->configFactory = $container->get('config.factory');
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

    $process = $this->buildProcessFlow();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dungeoncrawler-testing-dashboard']],
      'documentation' => $this->buildDocumentationSection(),
      'commands' => $this->buildTestCommandsSection(),
      'stagegates' => [
        '#theme' => 'item_list',
        '#title' => $this->t('Release Testing Stagegates'),
        '#items' => $process,
      ],
      'issues' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['dungeoncrawler-testing-issues']],
        'ci' => $this->renderIssueList($issues['ci_failures']),
        'testing_defects' => $this->renderIssueList($issues['testing_defects']),
        'program_defects' => $this->renderIssueList($issues['program_defects']),
      ],
      '#attached' => [
        'library' => [],
      ],
    ];
  }

  /**
   * Build documentation links section.
   */
  private function buildDocumentationSection(): array {
    $moduleBasePath = \Drupal::service('extension.list.module')->getPath('dungeoncrawler_tester');
    $docsBasePath = dirname(dirname(dirname(dirname($moduleBasePath)))) . '/docs/dungeoncrawler';
    
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
      '#theme' => 'item_list',
      '#title' => $this->t('📚 Test Documentation'),
      '#items' => $links,
      '#attributes' => ['class' => ['documentation-links']],
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
        '#markup' => '<h2>' . $this->t('🚀 Quick Test Commands') . '</h2>',
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
   * Build stagegate process flow top-to-bottom.
   */
  private function buildProcessFlow(): array {
    return [
      $this->t('Pre-commit: lint/format + unit (CharacterCalculator, CombatCalculator)'),
      $this->t('Functional routes/controllers: public, admin, character, campaign, API endpoints'),
      $this->t('Character creation workflow: 8-step wizard, validation, persistence (see workflow tests)'),
      $this->t('Entity/campaign APIs: state validation/access, entity lifecycle'),
      $this->t('Cross-check fixtures: PF2e reference + character fixtures up to date'),
      $this->t('CI gate: all suites green; failures auto-filed to GitHub (ci-failure label)'),
      $this->t('Release sign-off: no open ci-failure/testing-defect blocking issues'),
    ];
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
   * Render issue list container.
   */
  private function renderIssueList(array $data): array {
    if (!empty($data['error'])) {
      return [
        '#type' => 'item',
        '#title' => $data['label'] ?? $this->t('Issues'),
        '#markup' => '<div class="messages messages--error">' . $data['error'] . '</div>',
      ];
    }

    return [
      '#theme' => 'item_list',
      '#title' => $data['label'] ?? $this->t('Issues'),
      '#items' => $data['items'] ?? [],
      '#empty' => $this->t('No open issues for this category.'),
    ];
  }

}
