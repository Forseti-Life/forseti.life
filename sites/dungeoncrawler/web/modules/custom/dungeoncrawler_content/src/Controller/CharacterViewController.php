<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for viewing a single character's full PF2e sheet.
 */
class CharacterViewController extends ControllerBase {

  protected CharacterManager $characterManager;
  protected GeneratedImageRepository $imageRepository;

  public function __construct(CharacterManager $character_manager, GeneratedImageRepository $image_repository) {
    $this->characterManager = $character_manager;
    $this->imageRepository = $image_repository;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.generated_image_repository'),
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

    // Load portrait from generated images
    $portraits = $this->imageRepository->loadImagesForObject(
      'dc_campaign_characters',
      (string) $record->id,
      $campaign_id > 0 ? $campaign_id : NULL,
      'portrait',
      'original'
    );
    $portrait_url = NULL;
    if (!empty($portraits)) {
      $file_uri = $portraits[0]['file_uri'] ?? $portraits[0]['public_url'] ?? NULL;
      if ($file_uri) {
        // Convert Drupal stream wrapper to web-accessible URL
        $portrait_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file_uri);
      }
    }

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

    // Extract new character creation data
    $ancestry_feat = $char_data['ancestry_feat'] ?? NULL;
    $ancestry_feat_data = NULL;
    if ($ancestry_feat && !empty($ancestry_name)) {
      $ancestry_feats = CharacterManager::ANCESTRY_FEATS[$ancestry_name] ?? [];
      foreach ($ancestry_feats as $feat) {
        if ($feat['id'] === $ancestry_feat) {
          $ancestry_feat_data = $feat;
          break;
        }
      }
    }

    $class_feat = $char_data['class_feat'] ?? NULL;
    $class_feat_data = NULL;
    if ($class_feat && !empty($class_name)) {
      $class_feats = CharacterManager::CLASS_FEATS[$class_name] ?? [];
      foreach ($class_feats as $feat) {
        if ($feat['id'] === $class_feat) {
          $class_feat_data = $feat;
          break;
        }
      }
    }

    $trained_skills_list = $char_data['trained_skills'] ?? [];
    $background_skill = NULL;
    $background_lore = NULL;
    $background_feat = NULL;
    if (!empty($char_data['background'])) {
      $background_data = CharacterManager::BACKGROUNDS[$char_data['background']] ?? NULL;
      if ($background_data) {
        $background_skill = $background_data['skill'] ?? NULL;
        $background_lore = $background_data['lore'] ?? NULL;
        $background_feat = $background_data['feat'] ?? NULL;
      }
    }

    // Enhance skills array with training status
    $skills = [];
    foreach ($skill_list as $skill_name => $ability_key) {
      $is_trained = in_array($skill_name, $trained_skills_list, TRUE) || ($skill_name === $background_skill);
      $proficiency = $is_trained ? 'Trained' : 'Untrained';
      $bonus = $is_trained ? $prof_bonus : 0;
      
      $skills[] = [
        'name' => $skill_name,
        'modifier' => $abilities[$ability_key]['modifier'] + $bonus,
        'proficiency' => $proficiency,
        'trained' => $is_trained,
      ];
    }

    // Combat calculations: Melee and Ranged attacks
    $melee_attacks = [];
    $ranged_attacks = [];
    
    // Determine weapon proficiencies based on class
    $simple_weapon_prof = 'trained'; // Most classes get simple weapon proficiency
    $martial_weapon_prof = 'untrained';
    $unarmed_prof = 'trained'; // Everyone is trained in unarmed
    
    if (!empty($class_name)) {
      // Fighters, Rangers, Barbarians, Champions get martial proficiency
      if (in_array($class_name, ['fighter', 'ranger', 'barbarian', 'champion'], TRUE)) {
        $martial_weapon_prof = 'trained';
      }
    }
    
    // Get starting weapons from equipment or use defaults
    $weapon_ids = [];
    if (!empty($char_data['equipment'])) {
      foreach ($char_data['equipment'] as $item) {
        if (is_array($item) && isset($item['type']) && $item['type'] === 'weapon') {
          $weapon_ids[] = $item['id'] ?? NULL;
        }
      }
    }
    
    // If no weapons, provide class-appropriate defaults
    if (empty($weapon_ids)) {
      if (!empty($class_name)) {
        $weapon_ids = match ($class_name) {
          'fighter', 'champion' => ['longsword', 'shortbow'],
          'ranger' => ['shortsword', 'longbow'],
          'barbarian' => ['greataxe'],
          'rogue' => ['rapier', 'shortbow'],
          'wizard', 'sorcerer' => ['staff', 'crossbow'],
          'cleric' => ['mace', 'dagger'],
          'bard' => ['rapier', 'crossbow'],
          default => ['dagger', 'sling'],
        };
      } else {
        $weapon_ids = ['fist']; // Always have unarmed strike
      }
    }
    
    // Always include unarmed strike
    if (!in_array('fist', $weapon_ids, TRUE)) {
      $weapon_ids[] = 'fist';
    }
    
    // Calculate attacks for each weapon
    foreach ($weapon_ids as $weapon_id) {
      if (empty($weapon_id) || !isset(CharacterManager::WEAPONS[$weapon_id])) {
        continue;
      }
      
      $weapon = CharacterManager::WEAPONS[$weapon_id];
      $category = $weapon['category'];
      $is_finesse = in_array('Finesse', $weapon['traits'], TRUE);
      $is_ranged = !empty($weapon['range']);
      
      // Determine proficiency bonus for this weapon
      $weapon_prof_bonus = 0;
      if ($category === 'simple' && $simple_weapon_prof === 'trained') {
        $weapon_prof_bonus = $prof_bonus;
      } elseif ($category === 'martial' && $martial_weapon_prof === 'trained') {
        $weapon_prof_bonus = $prof_bonus;
      } elseif ($category === 'unarmed' && $unarmed_prof === 'trained') {
        $weapon_prof_bonus = $prof_bonus;
      }
      
      // Calculate attack bonus
      if ($is_ranged) {
        // Ranged: DEX mod + weapon prof + level + potency
        $attack_bonus = $abilities['dexterity']['modifier'] + $weapon_prof_bonus;
      } elseif ($is_finesse) {
        // Finesse: Use higher of STR or DEX
        $attack_bonus = max($abilities['strength']['modifier'], $abilities['dexterity']['modifier']) + $weapon_prof_bonus;
      } else {
        // Melee: STR mod + weapon prof + level + potency
        $attack_bonus = $abilities['strength']['modifier'] + $weapon_prof_bonus;
      }
      
      // Calculate damage (damage die + STR for melee, just die for ranged unless Propulsive)
      $damage_mod = 0;
      if (!$is_ranged) {
        $damage_mod = $abilities['strength']['modifier'];
      } elseif (in_array('Propulsive', $weapon['traits'], TRUE)) {
        // Propulsive: add half STR (minimum 0)
        $damage_mod = max(0, (int) floor($abilities['strength']['modifier'] / 2));
      }
      
      $damage_string = $weapon['damage'];
      if ($damage_mod > 0) {
        $damage_string .= '+' . $damage_mod;
      } elseif ($damage_mod < 0) {
        $damage_string .= $damage_mod;
      }
      
      $attack = [
        'name' => $weapon['name'],
        'bonus' => $attack_bonus,
        'damage' => $damage_string,
        'damage_type' => $weapon['damage_type'],
        'traits' => $weapon['traits'],
      ];
      
      if ($is_ranged) {
        $attack['range'] = $weapon['range'];
        $ranged_attacks[] = $attack;
      } else {
        $melee_attacks[] = $attack;
      }
    }

    // Spell data preparation for spellcasting classes
    $spell_data = NULL;
    if (!empty($class_name) && $class_name === 'wizard') {
      // Calculate spell DC and spell attack for Wizard (Arcane tradition)
      $int_mod = $abilities['intelligence']['modifier'];
      $spell_proficiency = $prof_bonus; // Trained = level + 2
      $spell_attack = $int_mod + $spell_proficiency;
      $spell_dc = 10 + $int_mod + $spell_proficiency;

      // Prepare cantrips
      $cantrips = [];
      if (!empty($char_data['cantrips'])) {
        $cantrip_ids = array_filter((array) $char_data['cantrips']);
        $available_cantrips = CharacterManager::SPELLS['arcane']['cantrips'] ?? [];
        foreach ($available_cantrips as $cantrip) {
          if (in_array($cantrip['id'], $cantrip_ids, TRUE)) {
            $cantrips[] = [
              'name' => $cantrip['name'],
              'rank' => 0,
              'school' => $cantrip['school'],
              'actions' => $cantrip['cast'],
              'traits' => $cantrip['traits'],
              'description' => $cantrip['description'],
            ];
          }
        }
      }

      // Prepare 1st level spells
      $first_level_spells = [];
      if (!empty($char_data['spells_first'])) {
        $spell_ids = array_filter((array) $char_data['spells_first']);
        $available_spells = CharacterManager::SPELLS['arcane']['1st'] ?? [];
        foreach ($available_spells as $spell) {
          if (in_array($spell['id'], $spell_ids, TRUE)) {
            $first_level_spells[] = [
              'name' => $spell['name'],
              'rank' => 1,
              'school' => $spell['school'],
              'actions' => $spell['cast'],
              'traits' => $spell['traits'],
              'description' => $spell['description'],
            ];
          }
        }
      }

      // Combine all spells
      $all_spells = array_merge($cantrips, $first_level_spells);

      // Calculate spell slots (Wizard at level 1 has 2 × 1st level slots)
      // TODO: Add spells per day from INT bonus
      $spell_slots = [
        1 => ['max' => 2, 'remaining' => 2],
      ];

      if (!empty($all_spells)) {
        $spell_data = [
          'tradition' => 'arcane',
          'spell_attack' => $spell_attack,
          'spell_dc' => $spell_dc,
          'spells_known' => $all_spells,
          'spell_slots' => $spell_slots,
          'focus_points' => NULL,
        ];
      }
    }

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
        'portrait' => $portrait_url,
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
        'skill' => $background_skill,
        'lore' => $background_lore,
        'feat' => $background_feat,
      ],
      '#class_data' => [
        'name' => $class_name,
        'subclass' => $class_subclass,
        'key_ability' => $class_key_ability,
        'hp_per_level' => $class_hp_per_level,
        'class_features' => [],
        'class_feats' => [],
      ],
      '#feats' => [
        'ancestry' => $ancestry_feat_data,
        'class' => $class_feat_data,
        'general' => $char_data['feats'] ?? [],
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
      '#melee_attacks' => $melee_attacks,
      '#ranged_attacks' => $ranged_attacks,
      '#equipment' => [
        'gold' => $equipment_gold,
        'items' => $equipment_items,
      ],
      '#spells' => $spell_data,
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
