<?php

namespace Drupal\professional_website_content\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides a Professional Website Navigation block.
 */
#[Block(
  id: 'professional_navigation_block',
  admin_label: new TranslatableMarkup('Professional Website Navigation'),
  category: new TranslatableMarkup('Professional Website')
)]
class ProfessionalNavigationBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Define the navigation menu structure
    $menu_items = [
      [
        'title' => $this->t('Home'),
        'url' => Url::fromRoute('<front>'),
        'class' => 'nav-item',
        'children' => [],
      ],
      [
        'title' => $this->t('About Us'),
        'url' => Url::fromUserInput('/about-us'),
        'class' => 'nav-item',
        'children' => [],
      ],
      [
        'title' => $this->t('Services'),
        'url' => Url::fromRoute('professional_website_content.services'),
        'class' => 'nav-item',
        'children' => [],
      ],
      [
        'title' => $this->t('Industries'),
        'url' => Url::fromRoute('<none>'),
        'class' => 'nav-item dropdown',
        'children' => [
          [
            'title' => $this->t('FinTech'),
            'url' => Url::fromRoute('professional_website_content.fintech'),
            'class' => 'dropdown-item',
          ],
          [
            'title' => $this->t('Healthcare'),
            'url' => Url::fromRoute('professional_website_content.healthcare'),
            'class' => 'dropdown-item',
          ],
          [
            'title' => $this->t('Energy'),
            'url' => Url::fromRoute('professional_website_content.energy'),
            'class' => 'dropdown-item',
          ],
        ],
      ],
      [
        'title' => $this->t('Case Studies'),
        'url' => Url::fromUserInput('/case-studies'),
        'class' => 'nav-item',
        'children' => [],
      ],
      [
        'title' => $this->t('Leadership'),
        'url' => Url::fromUserInput('/leadership'),
        'class' => 'nav-item',
        'children' => [],
      ],
      [
        'title' => $this->t('Contact'),
        'url' => Url::fromUserInput('/contact'),
        'class' => 'nav-item',
        'children' => [],
      ],
    ];

    $build['professional_navigation'] = [
      '#theme' => 'professional_navigation_menu',
      '#menu_items' => $menu_items,
      '#cache' => [
        'contexts' => ['route'],
      ],
    ];

    return $build;
  }

  /**
   * Helper function to get node URL by title.
   *
   * @param string $title
   *   The node title to search for.
   *
   * @return \Drupal\Core\Url
   *   The URL object for the node, or front page if not found.
   */
  protected function getNodeUrlByTitle($title) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'page')
      ->condition('title', $title)
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->range(0, 1);

    $nids = $query->execute();
    if (!empty($nids)) {
      $nid = reset($nids);
      return Url::fromRoute('entity.node.canonical', ['node' => $nid]);
    }

    return Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Cache for 1 hour
    return 3600;
  }

}