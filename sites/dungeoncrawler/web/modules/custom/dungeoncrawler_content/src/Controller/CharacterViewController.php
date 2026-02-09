<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for viewing a single character's full PF2e sheet.
 */
class CharacterViewController extends ControllerBase {

  protected CharacterManager $characterManager;

  public function __construct(CharacterManager $character_manager) {
    $this->characterManager = $character_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
    );
  }

  /**
   * Renders a full character sheet.
   */
  public function viewCharacter(int $character_id) {
    $record = $this->characterManager->loadCharacter($character_id);

    if (!$record) {
      throw new NotFoundHttpException();
    }

    if (!$this->characterManager->isOwner($record) && !$this->currentUser()->hasPermission('administer site configuration')) {
      throw new AccessDeniedHttpException();
    }

    $data = $this->characterManager->getCharacterData($record);
    $char = $data['character'] ?? [];

    // Flatten skills for template.
    $skills = [];
    if (!empty($char['skills'])) {
      foreach ($char['skills'] as $skill_key => $skill_data) {
        if (is_array($skill_data)) {
          $skills[] = [
            'name' => ucwords(str_replace('_', ' ', $skill_key)),
            'modifier' => $skill_data['modifier'] ?? 0,
            'proficiency' => $skill_data['proficiency'] ?? 'Untrained',
          ];
        }
      }
    }

    // Flatten attacks.
    $melee_attacks = [];
    if (!empty($char['attacks']['melee'])) {
      foreach ($char['attacks']['melee'] as $attack) {
        $melee_attacks[] = [
          'name' => $attack['name'] ?? '',
          'bonus' => $attack['attack_bonus'] ?? 0,
          'damage' => $attack['damage'] ?? '',
          'damage_type' => $attack['damage_type'] ?? '',
          'traits' => $attack['traits'] ?? [],
        ];
      }
    }

    $ranged_attacks = [];
    if (!empty($char['attacks']['ranged'])) {
      foreach ($char['attacks']['ranged'] as $attack) {
        $ranged_attacks[] = [
          'name' => $attack['name'] ?? '',
          'bonus' => $attack['attack_bonus'] ?? 0,
          'damage' => $attack['damage'] ?? '',
          'damage_type' => $attack['damage_type'] ?? '',
          'range' => $attack['range'] ?? '',
          'traits' => $attack['traits'] ?? [],
        ];
      }
    }

    // Equipment.
    $equipment = $char['equipment'] ?? [];

    $build = [
      '#theme' => 'character_sheet',
      '#character' => [
        'id' => $record->id,
        'uuid' => $record->uuid,
        'name' => $char['name'] ?? $record->name,
        'player' => $char['player'] ?? 'Player',
        'level' => $char['level'] ?? $record->level,
        'xp' => $char['experience_points'] ?? 0,
        'hero_points' => $char['hero_points'] ?? 1,
        'status' => $record->status ? 'active' : 'dead',
        'portrait' => $record->portrait,
      ],
      '#ancestry' => $char['ancestry'] ?? [],
      '#background' => $char['background'] ?? [],
      '#class_data' => $char['class'] ?? [],
      '#abilities' => $char['ability_scores'] ?? [],
      '#hp' => $char['hit_points'] ?? ['max' => 0, 'current' => 0, 'temporary' => 0],
      '#ac' => $char['armor_class'] ?? 10,
      '#saves' => $char['saving_throws'] ?? [],
      '#perception' => $char['perception'] ?? [],
      '#skills' => $skills,
      '#melee_attacks' => $melee_attacks,
      '#ranged_attacks' => $ranged_attacks,
      '#equipment' => $equipment,
      '#personality' => $char['personality'] ?? [],
      '#npc_data' => $char['npc_data'] ?? NULL,
      '#raw_json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
      '#edit_url' => Url::fromRoute('dungeoncrawler_content.character_edit', ['character_id' => $record->id])->toString(),
      '#delete_url' => Url::fromRoute('dungeoncrawler_content.character_delete', ['character_id' => $record->id])->toString(),
      '#back_url' => Url::fromRoute('dungeoncrawler_content.characters')->toString(),
      '#attached' => [
        'library' => ['dungeoncrawler_content/character-sheet'],
      ],
    ];

    return $build;
  }

  /**
   * Title callback for character view page.
   */
  public function viewTitle(int $character_id): string {
    $record = $this->characterManager->loadCharacter($character_id);
    return $record ? $record->name : 'Character Not Found';
  }

}
