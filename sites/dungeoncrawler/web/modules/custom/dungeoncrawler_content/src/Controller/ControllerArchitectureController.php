<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Documentation page describing controller responsibilities and touchpoints.
 */
class ControllerArchitectureController extends ControllerBase {

  /**
   * Render a concise map of controllers and how the system uses them.
   */
  public function overview() {
    $sections = [
      'Combat runtime (server authoritative)' => [
        'CombatEncounterApiController: Hexmap client uses /api/combat/* for start/end-turn/end/attack; stores turn index and runs server NPC auto-play.',
        'CombatActionController: Planned turn/action endpoints (start/end/delay) backed by CombatEngine/ActionProcessor.',
        'CombatApiController: Planned HP and conditions API (damage/heal/temp HP/conditions/initiative); currently stubs.',
        'CombatController: Page/API scaffolding for full encounter tracker UI (list/show/create/start/pause/resume/end).'
      ],
      'Exploration and dungeon' => [
        'HexMapController: Renders hexmap demo, injects dungeon payload from DB or example JSON, attaches JS runtime settings.',
        'DungeonController: Procedural dungeon REST (generate/get level/update state); most endpoints are TODO stubs.'
      ],
      'Character lifecycle' => [
        'CharacterCreationController: Legacy multi-step wizard render (static data).',
        'CharacterCreationStepController: Schema-driven wizard flow (start/step/saveStep) with CSRF and draft persistence.',
        'CharacterApiController: Authenticated JSON save/load/delete draft endpoints (wizard autosave).',
        'CharacterStateController: Character sheet APIs (get state/summary, cast spell, update state) with ownership checks; many updates are TODO.',
        'CharacterListController: Lists user characters; supports campaign selection.',
        'CharacterViewController: Renders PF2e-style sheet with launch links.',
      ],
      'Campaign flow' => [
        'CampaignController: Campaign list/create/select-character and tavern entrance launch.',
        'HomeController: Front page CTA routing to campaigns/login/how-to.',
        'DashboardController: Admin content dashboard counts.',
      ],
      'Static info pages' => [
        'HowToPlayController: How-to guide.',
        'WorldController: Lore/world page.',
        'AboutController: About page.',
        'CreditsController: Credits page.'
      ],
    ];

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['controller-architecture-doc']],
      'header' => [
        '#markup' => '<h2>Controller Architecture & Usage</h2><p>How frontend flows (hexmap, character, campaigns) hit backend controllers.</p>',
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    foreach ($sections as $title => $items) {
      $build[strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title))] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $title,
        'list' => [
          '#theme' => 'item_list',
          '#items' => $items,
        ],
      ];
    }

    $build['notes'] = [
      '#markup' => '<p><strong>Hexmap flow:</strong> UI talks to /api/combat/* for encounter lifecycle; server manages turn order, NPC auto-play, HP/conditions (when implemented).<br><strong>Character flow:</strong> UI wizard calls /api/character/* for draft save/load; sheet reads CharacterStateController.<br><strong>Campaign flow:</strong> Pages under /campaigns/* orchestrate character selection then launch hexmap.</p>',
    ];

    return $build;
  }

}
