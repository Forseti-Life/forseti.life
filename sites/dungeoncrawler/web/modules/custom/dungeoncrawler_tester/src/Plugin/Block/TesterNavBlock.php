<?php

namespace Drupal\dungeoncrawler_tester\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a documentation & utilities block for the Dungeon Crawler tester module.
 *
 * @Block(
 *   id = "dungeoncrawler_tester_nav_block",
 *   admin_label = @Translation("Dungeon Crawler Tester Documentation")
 * )
 */
class TesterNavBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $items = [];

    $links = [
      [
        'title' => $this->t('Tester README (module overview)'),
        'route' => 'dungeoncrawler_tester.docs_module_readme',
      ],
      [
        'title' => $this->t('Test instructions (suite & commands)'),
        'route' => 'dungeoncrawler_tester.docs_testing_module_readme',
      ],
      [
        'title' => $this->t('Test suite structure (tests/README)'),
        'route' => 'dungeoncrawler_tester.docs_tests_readme',
      ],
      [
        'title' => $this->t('Issue queue'),
        'uri' => 'https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting',
      ],
      [
        'title' => $this->t('Copilot issue API (docs)'),
        'route' => 'dungeoncrawler_tester.docs_issue_automation',
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

}
