<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Manages PF2e character CRUD operations and JSON storage.
 */
class CharacterManager {

  protected Connection $database;
  protected AccountProxyInterface $currentUser;
  protected UuidInterface $uuid;

  /**
   * PF2e ancestries with base stats.
   */
  const ANCESTRIES = [
    'Human' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Free', 'Free'], 'languages' => ['Common'], 'traits' => ['Human', 'Humanoid'], 'vision' => 'normal'],
    'Elf' => ['hp' => 6, 'size' => 'Medium', 'speed' => 30, 'boosts' => ['Dexterity', 'Intelligence'], 'flaw' => 'Constitution', 'languages' => ['Common', 'Elven'], 'traits' => ['Elf', 'Humanoid'], 'vision' => 'low-light vision'],
    'Dwarf' => ['hp' => 10, 'size' => 'Medium', 'speed' => 20, 'boosts' => ['Constitution', 'Wisdom'], 'flaw' => 'Charisma', 'languages' => ['Common', 'Dwarven'], 'traits' => ['Dwarf', 'Humanoid'], 'vision' => 'darkvision'],
    'Gnome' => ['hp' => 8, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Constitution', 'Charisma'], 'flaw' => 'Strength', 'languages' => ['Common', 'Gnomish', 'Sylvan'], 'traits' => ['Gnome', 'Humanoid'], 'vision' => 'low-light vision'],
    'Goblin' => ['hp' => 6, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Dexterity', 'Charisma'], 'flaw' => 'Wisdom', 'languages' => ['Common', 'Goblin'], 'traits' => ['Goblin', 'Humanoid'], 'vision' => 'darkvision'],
    'Halfling' => ['hp' => 6, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Dexterity', 'Wisdom'], 'flaw' => 'Strength', 'languages' => ['Common', 'Halfling'], 'traits' => ['Halfling', 'Humanoid'], 'vision' => 'normal'],
    'Half-Elf' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Free', 'Free'], 'languages' => ['Common', 'Elven'], 'traits' => ['Human', 'Elf', 'Humanoid', 'Half-Elf'], 'vision' => 'low-light vision'],
    'Half-Orc' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Free', 'Free'], 'languages' => ['Common', 'Orcish'], 'traits' => ['Human', 'Orc', 'Humanoid', 'Half-Orc'], 'vision' => 'low-light vision'],
    'Leshy' => ['hp' => 8, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Constitution', 'Wisdom'], 'flaw' => 'Intelligence', 'languages' => ['Common', 'Sylvan'], 'traits' => ['Leshy', 'Plant', 'Humanoid'], 'vision' => 'low-light vision'],
    'Orc' => ['hp' => 10, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Strength', 'Free'], 'languages' => ['Common', 'Orcish'], 'traits' => ['Orc', 'Humanoid'], 'vision' => 'darkvision'],
    'Catfolk' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Dexterity', 'Charisma'], 'flaw' => 'Wisdom', 'languages' => ['Common', 'Amurrun'], 'traits' => ['Catfolk', 'Humanoid'], 'vision' => 'low-light vision'],
    'Kobold' => ['hp' => 6, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Dexterity', 'Charisma'], 'flaw' => 'Constitution', 'languages' => ['Common', 'Draconic'], 'traits' => ['Kobold', 'Humanoid'], 'vision' => 'darkvision'],
    'Ratfolk' => ['hp' => 6, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Dexterity', 'Intelligence'], 'flaw' => 'Strength', 'languages' => ['Common', 'Ysoki'], 'traits' => ['Ratfolk', 'Humanoid'], 'vision' => 'low-light vision'],
    'Tengu' => ['hp' => 6, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Dexterity', 'Free'], 'languages' => ['Common', 'Tengu'], 'traits' => ['Tengu', 'Humanoid'], 'vision' => 'low-light vision'],
  ];

  /**
   * PF2e classes with base stats.
   */
  const CLASSES = [
    'Fighter' => ['hp' => 10, 'key_ability' => 'Strength or Dexterity', 'perception' => 'Expert', 'fortitude' => 'Expert', 'reflex' => 'Trained', 'will' => 'Trained', 'trained_skills' => 3, 'attack_prof' => 'Expert in all weapons'],
    'Rogue' => ['hp' => 8, 'key_ability' => 'Dexterity', 'perception' => 'Expert', 'fortitude' => 'Trained', 'reflex' => 'Expert', 'will' => 'Expert', 'trained_skills' => 7, 'attack_prof' => 'Trained in simple, rogue weapons'],
    'Wizard' => ['hp' => 6, 'key_ability' => 'Intelligence', 'perception' => 'Trained', 'fortitude' => 'Trained', 'reflex' => 'Trained', 'will' => 'Expert', 'trained_skills' => 2, 'attack_prof' => 'Trained in simple weapons, staff'],
    'Cleric' => ['hp' => 8, 'key_ability' => 'Wisdom', 'perception' => 'Trained', 'fortitude' => 'Trained', 'reflex' => 'Trained', 'will' => 'Expert', 'trained_skills' => 2, 'attack_prof' => 'Trained in simple weapons, deity favored'],
    'Ranger' => ['hp' => 10, 'key_ability' => 'Strength or Dexterity', 'perception' => 'Expert', 'fortitude' => 'Expert', 'reflex' => 'Expert', 'will' => 'Trained', 'trained_skills' => 4, 'attack_prof' => 'Trained in simple and martial weapons'],
    'Bard' => ['hp' => 8, 'key_ability' => 'Charisma', 'perception' => 'Expert', 'fortitude' => 'Trained', 'reflex' => 'Trained', 'will' => 'Expert', 'trained_skills' => 4, 'attack_prof' => 'Trained in simple weapons, longsword, rapier, shortbow'],
    'Barbarian' => ['hp' => 12, 'key_ability' => 'Strength', 'perception' => 'Expert', 'fortitude' => 'Expert', 'reflex' => 'Trained', 'will' => 'Expert', 'trained_skills' => 3, 'attack_prof' => 'Trained in simple and martial weapons'],
    'Champion' => ['hp' => 10, 'key_ability' => 'Strength', 'perception' => 'Trained', 'fortitude' => 'Expert', 'reflex' => 'Trained', 'will' => 'Expert', 'trained_skills' => 2, 'attack_prof' => 'Trained in simple and martial weapons'],
    'Druid' => ['hp' => 8, 'key_ability' => 'Wisdom', 'perception' => 'Trained', 'fortitude' => 'Trained', 'reflex' => 'Trained', 'will' => 'Expert', 'trained_skills' => 2, 'attack_prof' => 'Trained in simple weapons'],
    'Monk' => ['hp' => 10, 'key_ability' => 'Strength or Dexterity', 'perception' => 'Trained', 'fortitude' => 'Expert', 'reflex' => 'Expert', 'will' => 'Expert', 'trained_skills' => 4, 'attack_prof' => 'Trained in simple weapons, unarmed'],
    'Sorcerer' => ['hp' => 6, 'key_ability' => 'Charisma', 'perception' => 'Trained', 'fortitude' => 'Trained', 'reflex' => 'Trained', 'will' => 'Expert', 'trained_skills' => 2, 'attack_prof' => 'Trained in simple weapons'],
    'Alchemist' => ['hp' => 8, 'key_ability' => 'Intelligence', 'perception' => 'Trained', 'fortitude' => 'Expert', 'reflex' => 'Expert', 'will' => 'Trained', 'trained_skills' => 3, 'attack_prof' => 'Trained in simple weapons, alchemical bombs'],
    'Investigator' => ['hp' => 8, 'key_ability' => 'Intelligence', 'perception' => 'Expert', 'fortitude' => 'Trained', 'reflex' => 'Expert', 'will' => 'Expert', 'trained_skills' => 4, 'attack_prof' => 'Trained in simple weapons, martial weapons'],
    'Oracle' => ['hp' => 8, 'key_ability' => 'Charisma', 'perception' => 'Trained', 'fortitude' => 'Trained', 'reflex' => 'Trained', 'will' => 'Expert', 'trained_skills' => 3, 'attack_prof' => 'Trained in simple weapons'],
    'Swashbuckler' => ['hp' => 10, 'key_ability' => 'Dexterity', 'perception' => 'Expert', 'fortitude' => 'Trained', 'reflex' => 'Expert', 'will' => 'Expert', 'trained_skills' => 5, 'attack_prof' => 'Trained in simple and martial weapons'],
    'Witch' => ['hp' => 6, 'key_ability' => 'Intelligence', 'perception' => 'Trained', 'fortitude' => 'Trained', 'reflex' => 'Trained', 'will' => 'Expert', 'trained_skills' => 3, 'attack_prof' => 'Trained in simple weapons'],
  ];

  public function __construct(Connection $database, AccountProxyInterface $current_user, UuidInterface $uuid) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->uuid = $uuid;
  }

  /**
   * Get all characters for the current user.
   */
  public function getUserCharacters(?int $uid = NULL): array {
    $uid = $uid ?? (int) $this->currentUser->id();
    return $this->database->select('dc_characters', 'c')
      ->fields('c')
      ->condition('c.uid', $uid)
      ->orderBy('c.changed', 'DESC')
      ->execute()
      ->fetchAll();
  }

  /**
   * Load a single character by ID.
   */
  public function loadCharacter(int $id): ?object {
    $record = $this->database->select('dc_characters', 'c')
      ->fields('c')
      ->condition('c.id', $id)
      ->execute()
      ->fetchObject();

    return $record ?: NULL;
  }

  /**
   * Load a character by UUID.
   */
  public function loadByUuid(string $uuid): ?object {
    $record = $this->database->select('dc_characters', 'c')
      ->fields('c')
      ->condition('c.uuid', $uuid)
      ->execute()
      ->fetchObject();

    return $record ?: NULL;
  }

  /**
   * Create a new character with full PF2e JSON.
   */
  public function createCharacter(string $name, string $ancestry, string $class, array $options = []): int {
    $character_data = $this->buildCharacterJson($name, $ancestry, $class, $options);

    $now = \Drupal::time()->getRequestTime();
    $id = $this->database->insert('dc_characters')
      ->fields([
        'uuid' => $this->uuid->generate(),
        'uid' => (int) $this->currentUser->id(),
        'name' => $name,
        'level' => 1,
        'ancestry' => $ancestry,
        'class' => $class,
        'character_data' => json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        'status' => 1,
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    return (int) $id;
  }

  /**
   * Update character data.
   */
  public function updateCharacter(int $id, array $fields): bool {
    $fields['changed'] = \Drupal::time()->getRequestTime();
    $updated = $this->database->update('dc_characters')
      ->fields($fields)
      ->condition('id', $id)
      ->execute();

    return (bool) $updated;
  }

  /**
   * Delete a character.
   */
  public function deleteCharacter(int $id): bool {
    $deleted = $this->database->delete('dc_characters')
      ->condition('id', $id)
      ->condition('uid', (int) $this->currentUser->id())
      ->execute();

    return (bool) $deleted;
  }

  /**
   * Build a full PF2e character JSON structure.
   */
  public function buildCharacterJson(string $name, string $ancestry_name, string $class_name, array $options = []): array {
    $ancestry = self::ANCESTRIES[$ancestry_name] ?? self::ANCESTRIES['Human'];
    $class = self::CLASSES[$class_name] ?? self::CLASSES['Fighter'];

    // Default ability scores (10 base + ancestry boosts).
    $abilities = [
      'strength' => 10,
      'dexterity' => 10,
      'constitution' => 10,
      'intelligence' => 10,
      'wisdom' => 10,
      'charisma' => 10,
    ];

    // Apply manual ability boosts if provided.
    if (!empty($options['ability_boosts'])) {
      foreach ($options['ability_boosts'] as $ability => $boost) {
        $key = strtolower($ability);
        if (isset($abilities[$key])) {
          $abilities[$key] += (int) $boost;
        }
      }
    }
    else {
      // Apply ancestry boosts automatically.
      foreach ($ancestry['boosts'] as $boost) {
        if ($boost !== 'Free') {
          $key = strtolower($boost);
          if (isset($abilities[$key])) {
            $abilities[$key] += 2;
          }
        }
      }
      // Apply ancestry flaw.
      if (!empty($ancestry['flaw'])) {
        $key = strtolower($ancestry['flaw']);
        if (isset($abilities[$key])) {
          $abilities[$key] -= 2;
        }
      }
    }

    // Calculate modifiers.
    $ability_block = [];
    foreach ($abilities as $key => $score) {
      $ability_block[$key] = [
        'score' => $score,
        'modifier' => intdiv($score - 10, 2),
      ];
    }

    $con_mod = $ability_block['constitution']['modifier'];
    $dex_mod = $ability_block['dexterity']['modifier'];
    $wis_mod = $ability_block['wisdom']['modifier'];

    $hp = $ancestry['hp'] + $class['hp'] + $con_mod;

    // Proficiency bonus at level 1 = 2 + level for trained, 4 + level for expert.
    $trained = 3; // 2 + level(1)
    $expert = 5;  // 4 + level(1)

    $prof_to_bonus = function (string $prof, int $ability_mod) use ($trained, $expert): int {
      return match($prof) {
        'Expert' => $expert + $ability_mod,
        'Trained' => $trained + $ability_mod,
        default => $ability_mod,
      };
    };

    return [
      'pf2e_version' => '2.0',
      'character' => [
        'name' => $name,
        'player' => 'Player',
        'level' => 1,
        'experience_points' => 0,
        'hero_points' => 1,
        'ancestry' => [
          'name' => $ancestry_name,
          'heritage' => $options['heritage'] ?? '',
          'size' => $ancestry['size'],
          'speed' => $ancestry['speed'],
          'languages' => $ancestry['languages'],
          'traits' => $ancestry['traits'],
          'ancestry_features' => [
            'darkvision' => $ancestry['vision'] === 'darkvision',
            'low_light_vision' => $ancestry['vision'] === 'low-light vision',
            'hp' => $ancestry['hp'],
          ],
          'ancestry_feat' => [
            'name' => '',
            'description' => '',
          ],
        ],
        'background' => [
          'name' => $options['background'] ?? '',
          'description' => '',
          'ability_boosts' => [],
          'skill_training' => [],
          'feat' => ['name' => '', 'description' => ''],
        ],
        'class' => [
          'name' => $class_name,
          'subclass' => $options['subclass'] ?? '',
          'key_ability' => $class['key_ability'],
          'hp_per_level' => $class['hp'],
          'proficiencies' => [
            'perception' => $class['perception'],
            'fortitude' => $class['fortitude'],
            'reflex' => $class['reflex'],
            'will' => $class['will'],
          ],
          'class_features' => [],
          'class_feats' => [],
          'skill_feats' => [],
        ],
        'ability_scores' => $ability_block,
        'hit_points' => [
          'max' => $hp,
          'current' => $hp,
          'temporary' => 0,
        ],
        'armor_class' => 10 + $dex_mod,
        'saving_throws' => [
          'fortitude' => [
            'modifier' => $prof_to_bonus($class['fortitude'], $con_mod),
            'proficiency' => $class['fortitude'],
          ],
          'reflex' => [
            'modifier' => $prof_to_bonus($class['reflex'], $dex_mod),
            'proficiency' => $class['reflex'],
          ],
          'will' => [
            'modifier' => $prof_to_bonus($class['will'], $wis_mod),
            'proficiency' => $class['will'],
          ],
        ],
        'perception' => [
          'modifier' => $prof_to_bonus($class['perception'], $wis_mod),
          'proficiency' => $class['perception'],
          'senses' => $ancestry['vision'] !== 'normal' ? [ucwords($ancestry['vision'])] : [],
        ],
        'skills' => new \stdClass(),
        'attacks' => ['melee' => [], 'ranged' => []],
        'equipment' => [
          'worn' => ['armor' => NULL, 'other' => []],
          'held' => [],
          'stowed' => [],
          'currency' => ['gold' => 15, 'silver' => 0, 'copper' => 0],
          'bulk' => ['current' => 0, 'encumbered' => 5 + $ability_block['strength']['modifier'], 'max' => 10 + $ability_block['strength']['modifier']],
        ],
        'personality' => [
          'alignment' => $options['alignment'] ?? 'Neutral',
          'deity' => $options['deity'] ?? '',
          'traits' => [],
          'backstory' => $options['backstory'] ?? '',
        ],
      ],
    ];
  }

  /**
   * Get decoded character data from a record.
   */
  public function getCharacterData(object $record): array {
    return json_decode($record->character_data, TRUE) ?? [];
  }

  /**
   * Check if a character belongs to the current user.
   */
  public function isOwner(object $record): bool {
    return (int) $record->uid === (int) $this->currentUser->id();
  }

}
