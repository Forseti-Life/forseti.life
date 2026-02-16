<?php

namespace Drupal\dungeoncrawler_tester\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a documentation & utilities block for the Dungeon Crawler tester module.
 *
 * @Block(
 *   id = "dungeoncrawler_tester_nav_block",
 *   admin_label = @Translation("Dungeon Crawler Tester Documentation")
 * )
 */
class TesterNavBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $items = [];

    $links = [
      [
        'title' => $this->t('Getting Started'),
        'route' => 'dungeoncrawler_tester.docs_getting_started',
      ],
      [
        'title' => $this->t('Test Execution Playbook'),
        'route' => 'dungeoncrawler_tester.docs_execution_playbook',
      ],
      [
        'title' => $this->t('Failure Triage and Issue Workflow'),
        'route' => 'dungeoncrawler_tester.docs_failure_triage',
      ],
      [
        'title' => $this->t('Automated Testing Process Flow'),
        'route' => 'dungeoncrawler_tester.docs_process_flow',
      ],
      [
        'title' => $this->t('SDLC Process Flow'),
        'route' => 'dungeoncrawler_tester.docs_sdlc_process_flow',
      ],
      [
        'title' => $this->t('Release Process Flow'),
        'route' => 'dungeoncrawler_tester.docs_release_process_flow',
      ],
      [
        'title' => $this->t('Issue queue'),
        'uri' => $this->buildIssueQueueUri(),
      ],
      [
        'title' => $this->t('Documentation Home'),
        'route' => 'dungeoncrawler_tester.documentation_home',
      ],
    ];

    foreach ($links as $link) {
      if (isset($link['route'])) {
        $items[] = Link::fromTextAndUrl($link['title'], Url::fromRoute($link['route']))->toRenderable();
      }
      else {
        $items[] = Link::fromTextAndUrl($link['title'], Url::fromUri($link['uri']))->toRenderable();
      }
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Testing Documentation & Utilities'),
      '#items' => $items,
      '#attributes' => ['class' => ['dungeoncrawler-tester-nav-block']],
    ];
  }

  /**
   * Build repository-aware issue queue URI.
   */
  private function buildIssueQueueUri(): string {
    $testerRepo = trim((string) $this->configFactory->get('dungeoncrawler_tester.settings')->get('github_repo'));
    $aiRepo = trim((string) $this->configFactory->get('ai_conversation.settings')->get('github_repo'));
    $envRepo = trim((string) getenv('TESTER_GITHUB_REPO'));

    $repo = $testerRepo !== ''
      ? $testerRepo
      : ($aiRepo !== ''
        ? $aiRepo
        : ($envRepo !== '' ? $envRepo : 'keithaumiller/forseti.life'));

    $query = rawurlencode("repo:{$repo} is:issue is:open label:testing");
    return "https://github.com/issues?q={$query}";
  }

}
