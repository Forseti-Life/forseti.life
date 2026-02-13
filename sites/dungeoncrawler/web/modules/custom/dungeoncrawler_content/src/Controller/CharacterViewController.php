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
    $campaign_id = (int) (\Drupal::request()->query->get('campaign_id') ?? 0);

    $record = $this->characterManager->loadCharacter($character_id);

    if (!$record) {
      throw new NotFoundHttpException();
    }

    if (!$this->characterManager->isOwner($record) && !$this->currentUser()->hasPermission('administer site configuration')) {
      throw new AccessDeniedHttpException();
    }

    // Decode character_data JSON
    $char_data = json_decode($record->character_data, TRUE) ?? [];

    // Support both old flat structure and new nested abilities structure
    $abilities = [];
    if (!empty($char_data['abilities'])) {
      // New schema format
      foreach (['str' => 'strength', 'dex' => 'dexterity', 'con' => 'constitution', 'int' => 'intelligence', 'wis' => 'wisdom', 'cha' => 'charisma'] as $short => $long) {
        $score = $char_data['abilities'][$short] ?? 10;
        $modifier = floor(($score - 10) / 2);
        $abilities[$long] = [
          'score' => $score,
          'modifier' => $modifier,
        ];
      }
    }
    else {
      // Old flat format - fallback
      foreach (['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'] as $ability) {
        $score = $char_data[$ability] ?? 10;
        $modifier = floor(($score - 10) / 2);
        $abilities[$ability] = [
          'score' => $score,
          'modifier' => $modifier,
        ];
      }
    }

    // Calculate derived stats
    $level = $char_data['level'] ?? $record->level ?? 1;
    $con_mod = $abilities['constitution']['modifier'];
    
    // AC calculation (10 + DEX modifier for unarmored)
    $ac = 10 + $abilities['dexterity']['modifier'];
    
    // Max HP from schema or calculate
    $max_hp = $char_data['hit_points']['max'] ?? 20;
    
    // Saving throws (proficiency bonus = level + 2 for trained)
    $prof_bonus = $level + 2;
    $saves = [
      'Fortitude' => [
        'modifier' => $con_mod + $prof_bonus,
        'proficiency' => 'Trained',
      ],
      'Reflex' => [
        'modifier' => $abilities['dexterity']['modifier'] + $prof_bonus,
        'proficiency' => 'Trained',
      ],
      'Will' => [
        'modifier' => $abilities['wisdom']['modifier'] + $prof_bonus,
        'proficiency' => 'Trained',
      ],
    ];

    // Perception
    $perception = [
      'modifier' => $abilities['wisdom']['modifier'] + $prof_bonus,
      'proficiency' => 'Trained',
      'senses' => [],
    ];

    // Basic skills (all untrained unless specified)
    $skill_list = [
      'Acrobatics' => 'dexterity',
      'Arcana' => 'intelligence',
      'Athletics' => 'strength',
      'Crafting' => 'intelligence',
      'Deception' => 'charisma',
      'Diplomacy' => 'charisma',
      'Intimidation' => 'charisma',
      'Lore' => 'intelligence',
      'Medicine' => 'wisdom',
      'Nature' => 'wisdom',
      'Occultism' => 'intelligence',
      'Performance' => 'charisma',
      'Religion' => 'wisdom',
      'Society' => 'intelligence',
      'Stealth' => 'dexterity',
      'Survival' => 'wisdom',
      'Thievery' => 'dexterity',
    ];

    $skills = [];
    foreach ($skill_list as $skill_name => $ability_key) {
      $skills[] = [
        'name' => $skill_name,
        'modifier' => $abilities[$ability_key]['modifier'],
        'proficiency' => 'Untrained',
      ];
    }

    $launch_url = Url::fromRoute('dungeoncrawler_content.hexmap_demo')
      ->setOption('query', ['character_id' => $record->id]);
    $tavern_url = NULL;
    if ($campaign_id > 0) {
      $launch_url->setOption('query', [
        'campaign_id' => $campaign_id,
        'character_id' => $record->id,
      ]);
      $tavern_url = Url::fromRoute('dungeoncrawler_content.campaign_tavernentrance', [
        'campaign_id' => $campaign_id,
      ])->toString();
    }

    $back_url = Url::fromRoute('dungeoncrawler_content.characters');
    if ($campaign_id > 0) {
      $back_url->setOption('query', ['campaign_id' => $campaign_id]);
    }

    $build = [
      '#theme' => 'character_sheet',
      '#character' => [
        'id' => $record->id,
        'uuid' => $record->uuid,
        'name' => $char_data['name'] ?? $record->name,
        'level' => $level,
        'xp' => $char_data['experience_points'] ?? 0,
        'hero_points' => $char_data['hero_points'] ?? 1,
        'status' => $record->status ? 'active' : 'incomplete',
        'portrait' => $record->portrait ?? NULL,
        'step' => $char_data['step'] ?? 1,
      ],
      '#char_data' => $char_data,
      '#ancestry' => [
        'name' => $char_data['ancestry'] ?? 'Unknown',
        'heritage' => $char_data['heritage'] ?? NULL,
        'size' => $char_data['size'] ?? 'Medium',
        'speed' => $char_data['speed'] ?? 25,
        'languages' => $char_data['languages'] ?? [],
        'traits' => [],
      ],
      '#background' => [
        'name' => $char_data['background'] ?? 'Unknown',
      ],
      '#class_data' => [
        'name' => $char_data['class'] ?? 'Unknown',
        'subclass' => $char_data['subclass'] ?? NULL,
        'key_ability' => 'STR',
        'hp_per_level' => 8,
        'class_features' => [],
        'class_feats' => [],
      ],
      '#abilities' => $abilities,
      '#hp' => [
        'max' => $char_data['hit_points']['max'] ?? $max_hp,
        'current' => $char_data['hit_points']['current'] ?? $max_hp,
        'temporary' => $char_data['hit_points']['temp'] ?? 0,
      ],
      '#ac' => $ac,
      '#saves' => $saves,
      '#perception' => $perception,
      '#skills' => $skills,
      '#melee_attacks' => [],
      '#ranged_attacks' => [],
      '#equipment' => [
        'gold' => $char_data['gold'] ?? 15,
        'items' => $char_data['equipment'] ?? [],
      ],
      '#feats' => $char_data['feats'] ?? [],
      '#spells' => $char_data['spells'] ?? NULL,
      '#conditions' => $char_data['conditions'] ?? [],
      '#personality' => [
        'alignment' => $char_data['alignment'] ?? NULL,
        'deity' => $char_data['deity'] ?? NULL,
        'age' => $char_data['age'] ?? NULL,
        'gender' => $char_data['gender'] ?? NULL,
        'appearance' => $char_data['appearance'] ?? NULL,
        'personality' => $char_data['personality'] ?? NULL,
        'backstory' => $char_data['backstory'] ?? NULL,
      ],
      '#npc_data' => NULL,
      '#raw_json' => json_encode($char_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
      '#edit_url' => Url::fromRoute('dungeoncrawler_content.character_edit', ['character_id' => $record->id])->toString(),
      '#delete_url' => Url::fromRoute('dungeoncrawler_content.character_delete', ['character_id' => $record->id])->toString(),
      '#launch_url' => $launch_url->toString(),
      '#tavern_url' => $tavern_url,
      '#campaign_id' => $campaign_id,
      '#back_url' => $back_url->toString(),
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
