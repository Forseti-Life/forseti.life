<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for hex map rendering and interaction.
 */
class HexMapController extends ControllerBase {

  /**
   * Hex map demo page.
   *
   * @return array
   *   Render array for the hex map demo.
   */
  public function demo() {
    return [
      '#theme' => 'hexmap_demo',
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/hexmap',
        ],
      ],
      '#cache' => [
        'max-age' => 0, // Disable cache for development
      ],
    ];
  }

}
