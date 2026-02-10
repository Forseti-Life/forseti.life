<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the homepage.
 */
class HomeController extends ControllerBase {

  /**
   * Display the homepage.
   *
   * @return array
   *   A render array for the homepage.
   */
  public function index() {
    // Return minimal render array - page--front.html.twig handles the display
    return [
      '#theme' => 'page__front',
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
  }

}
