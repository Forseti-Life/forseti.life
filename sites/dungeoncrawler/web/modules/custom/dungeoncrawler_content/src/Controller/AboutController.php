<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the About page.
 */
class AboutController extends ControllerBase {

  /**
   * Display the about page.
   *
   * @return array
   *   A render array for the about page.
   */
  public function index() {
    $build = [];

    $build['hero'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hero-section', 'mb-5', 'text-center']],
      'content' => [
        '#markup' => '<div class="card bg-gradient-dark text-light border-0">
          <div class="card-body p-5">
            <h1 class="display-3 mb-4">Dungeon Crawler Life</h1>
            <p class="lead fs-3">Where Forseti Guides Every Adventure</p>
            <p class="fs-5 text-muted">A living dungeon shaped by your choices, your party, and your next risk</p>
          </div>
        </div>',
      ],
    ];

    $build['story'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['about-story', 'mb-5']],
      'content' => [
        '#markup' => '<div class="card bg-dark text-light">
          <div class="card-body p-4">
            <h2 class="card-title mb-4">The Vision</h2>
            <p class="lead">Dungeon Crawler Life is a living campaign world where Forseti, your Game Master, keeps every expedition responsive, dangerous, and story-rich.</p>
            <p>Traditional dungeon crawlers can feel static after enough runs. Our goal is a world that reacts: encounters shift, rooms evolve, and narrative hooks adapt to your decisions in real time.</p>
            <p>Using advanced AI technology, Dungeon Crawler Life generates:</p>
            <ul>
              <li><strong>Unique Creatures:</strong> Each enemy you encounter is procedurally generated with its own abilities, behaviors, and appearance</li>
              <li><strong>Dynamic Rooms:</strong> Dungeon layouts adapt to your playstyle and challenge level</li>
              <li><strong>Living Items:</strong> Weapons and artifacts with procedurally generated lore and powers</li>
              <li><strong>Emergent Quests:</strong> Storylines that branch and evolve based on your choices</li>
              <li><strong>Adaptive Challenges:</strong> The dungeon learns from your tactics and responds accordingly</li>
            </ul>
          </div>
        </div>',
      ],
    ];

    $build['features'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['features', 'row', 'g-4', 'mb-5']],
    ];

    $features = [
      [
        'icon' => '🤖',
        'title' => 'Forseti-Guided Generation',
        'description' => 'Forseti orchestrates dynamic content so every crawl feels authored for your current campaign.',
      ],
      [
        'icon' => '🎲',
        'title' => 'Infinite Replayability',
        'description' => 'No two adventures are the same. Each playthrough offers new challenges, creatures, and treasures.',
      ],
      [
        'icon' => '🧠',
        'title' => 'Adaptive Difficulty',
        'description' => 'The dungeon analyzes your performance and adjusts to provide the perfect level of challenge.',
      ],
      [
        'icon' => '🌍',
        'title' => 'Hex-Based World',
        'description' => 'Explore a vast world divided into hexagonal regions, each with unique biomes and dangers.',
      ],
      [
        'icon' => '⚔️',
        'title' => 'Classic RPG Mechanics',
        'description' => 'Built on time-tested D&D-inspired rules that tabletop gamers will recognize and love.',
      ],
      [
        'icon' => '📱',
        'title' => 'Play Anywhere',
        'description' => 'Continue your campaign from web or mobile without losing momentum.',
      ],
    ];

    foreach ($features as $feature) {
      $build['features'][] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-md-6', 'col-lg-4']],
        'card' => [
          '#markup' => '<div class="card h-100 bg-dark text-light border-primary">
            <div class="card-body text-center">
              <div class="display-1 mb-3">' . $feature['icon'] . '</div>
              <h3 class="card-title">' . $feature['title'] . '</h3>
              <p class="card-text">' . $feature['description'] . '</p>
            </div>
          </div>',
        ],
      ];
    }

    $build['technology'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['technology', 'mb-5']],
      'content' => [
        '#markup' => '<div class="card bg-dark text-light border-info">
          <div class="card-body p-4">
            <h2 class="card-title mb-4">🔧 The Technology</h2>
            <div class="row">
              <div class="col-md-6">
                <h4>Built With:</h4>
                <ul>
                  <li>Drupal CMS for robust content management</li>
                  <li>React Native for mobile experiences</li>
                  <li>H3 geospatial indexing for hex-based world</li>
                  <li>Advanced AI models for content generation</li>
                  <li>Real-time game state management</li>
                </ul>
              </div>
              <div class="col-md-6">
                <h4>Powered By:</h4>
                <ul>
                  <li>Natural Language Processing for dynamic dialogue</li>
                  <li>Procedural generation algorithms</li>
                  <li>Machine learning for adaptive gameplay</li>
                  <li>RESTful API architecture</li>
                  <li>Modern web technologies (HTML5, CSS3, JavaScript)</li>
                </ul>
              </div>
            </div>
          </div>
        </div>',
      ],
    ];

    $build['team'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['team', 'mb-5']],
      'content' => [
        '#markup' => '<div class="card bg-dark text-light">
          <div class="card-body p-4 text-center">
            <h2 class="card-title mb-4">The Team</h2>
            <p class="lead">Dungeon Crawler Life is built by a small studio team focused on living-world RPG systems, with Forseti serving as the Game Master voice guiding every adventure.</p>
            <p>We believe that games should be: <strong>innovative</strong>, <strong>accessible</strong>, and <strong>infinitely replayable</strong>.</p>
          </div>
        </div>',
      ],
    ];

    $build['cta'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['cta', 'mt-5', 'text-center']],
      'content' => [
        '#markup' => '<div class="card bg-primary text-light border-0">
          <div class="card-body p-5">
            <h2 class="card-title mb-4">Ready for Your Next Expedition?</h2>
            <p class="lead mb-4">Gather your party and let Forseti open the first chamber.</p>
            <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
              <a href="/characters/create" class="btn btn-light btn-lg px-5">Create Character</a>
              <a href="/how-to-play" class="btn btn-outline-light btn-lg px-5">Learn More</a>
            </div>
          </div>
        </div>',
      ],
    ];

    $build['#attached']['library'][] = 'dungeoncrawler_content/game-cards';

    return $build;
  }

}
