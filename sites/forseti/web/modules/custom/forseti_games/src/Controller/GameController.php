<?php

namespace Drupal\forseti_games\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for game pages.
 */
class GameController extends ControllerBase {

  /**
   * Display the games home page with list of available games.
   */
  public function home() {
    $games = [
      [
        'title' => 'Block Matcher',
        'description' => 'Match colored blocks to clear the board. A classic puzzle game!',
        'image' => '/modules/custom/forseti_games/images/block-matcher-thumb.png',
        'url' => '/games/block-matcher',
        'difficulty' => 'Easy',
        'plays' => 0,
      ],
    ];

    return [
      '#theme' => 'game_home',
      '#games' => $games,
      '#attached' => [
        'library' => [
          'forseti_games/game-home',
        ],
      ],
    ];
  }

  /**
   * Display the Block Matcher game.
   */
  public function blockMatcher() {
    $game_data = [
      'grid_size' => 8,
      'block_types' => 5,
      'min_match' => 3,
    ];

    return [
      '#theme' => 'game_block_matcher',
      '#game_data' => $game_data,
      '#attached' => [
        'library' => [
          'forseti_games/block-matcher',
        ],
      ],
    ];
  }

}
