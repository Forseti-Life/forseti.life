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

    // Decode character data via manager and normalize nested/flat shape.
    $decoded = $this->characterManager->getCharacterData($record);
    $char_data = is_array($decoded['character'] ?? NULL) ? $decoded['character'] : $decoded;
    $hot = $this->characterManager->resolveHotColumnsForRecord($record, $decoded);

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
    elseif (!empty($char_data['ability_scores']) && is_array($char_data['ability_scores'])) {
      // Nested schema format.
      foreach (['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'] as $ability) {
        $score = (int) ($char_data['ability_scores'][$ability]['score'] ?? 10);
        $modifier = floor(($score - 10) / 2);
        $abilities[$ability] = [
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
    $ac = (int) $hot['armor_class'];
    
    // Max HP from schema or calculate
    $max_hp = (int) $hot['hp_max'];
    
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

    $ancestry_name = is_array($char_data['ancestry'] ?? NULL)
      ? ($char_data['ancestry']['name'] ?? 'Unknown')
      : ($char_data['ancestry'] ?? 'Unknown');
    $heritage = is_array($char_data['ancestry'] ?? NULL)
      ? ($char_data['ancestry']['heritage'] ?? NULL)
      : ($char_data['heritage'] ?? NULL);
    $size = is_array($char_data['ancestry'] ?? NULL)
      ? ($char_data['ancestry']['size'] ?? 'Medium')
      : ($char_data['size'] ?? 'Medium');
    $speed = is_array($char_data['ancestry'] ?? NULL)
      ? ($char_data['ancestry']['speed'] ?? 25)
      : ($char_data['speed'] ?? 25);
    $languages = is_array($char_data['ancestry'] ?? NULL)
      ? ($char_data['ancestry']['languages'] ?? [])
      : ($char_data['languages'] ?? []);

    $class_name = is_array($char_data['class'] ?? NULL)
      ? ($char_data['class']['name'] ?? 'Unknown')
      : ($char_data['class'] ?? 'Unknown');
    $class_subclass = is_array($char_data['class'] ?? NULL)
      ? ($char_data['class']['subclass'] ?? NULL)
      : ($char_data['subclass'] ?? NULL);
    $class_key_ability = is_array($char_data['class'] ?? NULL)
      ? ($char_data['class']['key_ability'] ?? 'STR')
      : 'STR';
    $class_hp_per_level = is_array($char_data['class'] ?? NULL)
      ? ((int) ($char_data['class']['hp_per_level'] ?? 8))
      : 8;

    $equipment_items = is_array($char_data['equipment'] ?? NULL)
      ? ($char_data['equipment']['stowed'] ?? $char_data['equipment'])
      : [];
    $equipment_gold = is_array($char_data['equipment'] ?? NULL)
      ? ((float) ($char_data['equipment']['currency']['gold'] ?? 15))
      : ((float) ($char_data['gold'] ?? 15));

    $alignment = is_array($char_data['personality'] ?? NULL)
      ? ($char_data['personality']['alignment'] ?? NULL)
      : ($char_data['alignment'] ?? NULL);
    $deity = is_array($char_data['personality'] ?? NULL)
      ? ($char_data['personality']['deity'] ?? NULL)
      : ($char_data['deity'] ?? NULL);
    $appearance = is_array($char_data['personality'] ?? NULL)
      ? ($char_data['personality']['appearance'] ?? NULL)
      : ($char_data['appearance'] ?? NULL);
    $personality = is_array($char_data['personality'] ?? NULL)
      ? ($char_data['personality']['traits'][0] ?? NULL)
      : ($char_data['personality'] ?? NULL);
    $backstory = is_array($char_data['personality'] ?? NULL)
      ? ($char_data['personality']['backstory'] ?? NULL)
      : ($char_data['backstory'] ?? NULL);

    $build = [
      '#theme' => 'character_sheet',
      '#character' => [
        'id' => $record->id,
        'uuid' => $record->uuid,
        'name' => $char_data['name'] ?? $record->name,
        'level' => $level,
        'xp' => (int) ($record->experience_points ?? $char_data['experience_points'] ?? 0),
        'hero_points' => $char_data['hero_points'] ?? 1,
        'status' => $record->status ? 'active' : 'incomplete',
        'portrait' => $record->portrait ?? NULL,
        'step' => $char_data['step'] ?? 1,
      ],
      '#char_data' => $char_data,
      '#ancestry' => [
        'name' => $ancestry_name,
        'heritage' => $heritage,
        'size' => $size,
        'speed' => $speed,
        'languages' => $languages,
        'traits' => [],
      ],
      '#background' => [
        'name' => $char_data['background'] ?? 'Unknown',
      ],
      '#class_data' => [
        'name' => $class_name,
        'subclass' => $class_subclass,
        'key_ability' => $class_key_ability,
        'hp_per_level' => $class_hp_per_level,
        'class_features' => [],
        'class_feats' => [],
      ],
      '#abilities' => $abilities,
      '#hp' => [
        'max' => $max_hp,
        'current' => (int) $hot['hp_current'],
        'temporary' => $char_data['hit_points']['temp'] ?? 0,
      ],
      '#ac' => $ac,
      '#saves' => $saves,
      '#perception' => $perception,
      '#skills' => $skills,
      '#melee_attacks' => [],
      '#ranged_attacks' => [],
      '#equipment' => [
        'gold' => $equipment_gold,
        'items' => $equipment_items,
      ],
      '#feats' => $char_data['feats'] ?? [],
      '#spells' => $char_data['spells'] ?? NULL,
      '#conditions' => $char_data['conditions'] ?? [],
      '#personality' => [
        'alignment' => $alignment,
        'deity' => $deity,
        'age' => $char_data['age'] ?? NULL,
        'gender' => $char_data['gender'] ?? NULL,
        'appearance' => $appearance,
        'personality' => $personality,
        'backstory' => $backstory,
      ],
      '#npc_data' => NULL,
      '#raw_json' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
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
