<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Manages character state for real-time gameplay.
 * 
 * This service implements the CharacterState management system as designed in:
 * docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md
 * 
 * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#characterstate-service-pseudocode
 */
class CharacterStateService {

  protected Connection $database;
  protected AccountProxyInterface $currentUser;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, AccountProxyInterface $current_user) {
    $this->database = $database;
    $this->currentUser = $current_user;
  }

  /**
   * Get current character state.
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return array
   *   Character state array matching CharacterState interface.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#characterstate-object
   */
  public function getState(string $character_id): array {
    $record = $this->database->select('dc_characters', 'c')
      ->fields('c')
      ->condition('id', $character_id)
      ->execute()
      ->fetchObject();

    if (!$record) {
      throw new \InvalidArgumentException("Character not found: {$character_id}");
    }

    // Parse character_data JSON
    $character_data = json_decode($record->character_data, TRUE) ?? [];
    
    // Build CharacterState structure
    $state = [
      'characterId' => (string) $record->id,
      'userId' => (string) $record->uid,
      'campaignId' => $character_data['campaignId'] ?? NULL,
      
      'basicInfo' => [
        'name' => $record->name,
        'level' => (int) $record->level,
        'experiencePoints' => $character_data['experiencePoints'] ?? 0,
        'ancestry' => $record->ancestry,
        'heritage' => $character_data['heritage'] ?? '',
        'background' => $character_data['background'] ?? '',
        'class' => $record->class,
        'alignment' => $character_data['alignment'] ?? '',
        'deity' => $character_data['deity'] ?? NULL,
        'age' => $character_data['age'] ?? NULL,
        'appearance' => $character_data['appearance'] ?? NULL,
        'personality' => $character_data['personality'] ?? NULL,
      ],
      
      'abilities' => $character_data['abilities'] ?? [
        'strength' => 10,
        'dexterity' => 10,
        'constitution' => 10,
        'intelligence' => 10,
        'wisdom' => 10,
        'charisma' => 10,
      ],
      
      'resources' => $character_data['resources'] ?? [
        'hitPoints' => [
          'current' => $character_data['hitPoints']['current'] ?? 0,
          'max' => $character_data['hitPoints']['max'] ?? 0,
          'temporary' => 0,
        ],
        'heroPoints' => ['current' => 1, 'max' => 3],
      ],
      
      'defenses' => $character_data['defenses'] ?? [],
      'conditions' => $character_data['conditions'] ?? [],
      'actions' => $character_data['actions'] ?? [
        'threeActionEconomy' => [
          'actionsRemaining' => 3,
          'reactionAvailable' => TRUE,
        ],
        'availableActions' => [],
      ],
      'spells' => $character_data['spells'] ?? [],
      'skills' => $character_data['skills'] ?? [],
      'inventory' => $character_data['inventory'] ?? [
        'worn' => ['weapons' => [], 'accessories' => []],
        'carried' => [],
        'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
        'totalBulk' => 0,
        'encumbrance' => 'unencumbered',
      ],
      'features' => $character_data['features'] ?? [
        'ancestryFeatures' => [],
        'classFeatures' => [],
        'feats' => [],
      ],
      
      'metadata' => [
        'createdAt' => date('c', $record->created),
        'updatedAt' => date('c', $record->changed),
        'lastSyncedAt' => date('c'),
        'version' => $character_data['version'] ?? 0,
      ],
    ];

    return $state;
  }

  /**
   * Update hit points.
   * 
   * @param string $character_id
   *   The character ID.
   * @param int $delta
   *   HP change (positive for healing, negative for damage).
   * @param bool $temporary
   *   Whether this affects temporary HP.
   * 
   * @return array
   *   Updated HP values.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#update-hit-points
   */
  public function updateHitPoints(string $character_id, int $delta, bool $temporary = FALSE): array {
    $state = $this->getState($character_id);
    
    if ($temporary) {
      // Temporary HP doesn't stack - take the higher value
      $new_temp_hp = max($state['resources']['hitPoints']['temporary'] ?? 0, $delta);
      $state['resources']['hitPoints']['temporary'] = $new_temp_hp;
    }
    else {
      // Update current HP with bounds checking
      $current = $state['resources']['hitPoints']['current'];
      $max = $state['resources']['hitPoints']['max'];
      
      $new_current = $current + $delta;
      // Cap between 0 and max HP
      $new_current = max(0, min($max, $new_current));
      
      $state['resources']['hitPoints']['current'] = $new_current;
    }
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['resources']['hitPoints'];
  }

  /**
   * Add condition to character.
   * 
   * @param string $character_id
   *   The character ID.
   * @param array $condition
   *   Condition data matching Condition interface.
   * 
   * @return array
   *   All active conditions.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#add-condition-to-character
   */
  public function addCondition(string $character_id, array $condition): array {
    $state = $this->getState($character_id);
    
    // Add required fields if not present
    if (empty($condition['id'])) {
      $condition['id'] = uniqid('cond_', TRUE);
    }
    if (empty($condition['appliedAt'])) {
      $condition['appliedAt'] = date('c');
    }
    
    // Add condition to state
    $state['conditions'][] = $condition;
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['conditions'];
  }

  /**
   * Remove condition from character.
   * 
   * @param string $character_id
   *   The character ID.
   * @param string $condition_id
   *   The condition ID to remove.
   * 
   * @return array
   *   Remaining active conditions.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#remove-condition-from-character
   */
  public function removeCondition(string $character_id, string $condition_id): array {
    $state = $this->getState($character_id);
    
    // Filter out the condition with matching ID
    $state['conditions'] = array_values(array_filter(
      $state['conditions'],
      function ($condition) use ($condition_id) {
        return $condition['id'] !== $condition_id;
      }
    ));
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['conditions'];
  }

  /**
   * Cast a spell (consume slot or focus point).
   * 
   * @param string $character_id
   *   The character ID.
   * @param string $spell_id
   *   The spell ID.
   * @param int $level
   *   Spell level.
   * @param bool $is_focus_spell
   *   Whether this is a focus spell.
   * 
   * @return array
   *   Updated spell slot/focus point data.
   * 
   * @throws \InvalidArgumentException
   *   If no spell slots/focus points available.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#cast-a-spell-consume-slot-or-focus-point
   */
  public function castSpell(string $character_id, string $spell_id, int $level, bool $is_focus_spell = FALSE): array {
    $state = $this->getState($character_id);
    
    if ($is_focus_spell) {
      // Check and consume focus point
      $current = $state['resources']['focusPoints']['current'] ?? 0;
      if ($current <= 0) {
        throw new \InvalidArgumentException('No focus points remaining');
      }
      $state['resources']['focusPoints']['current'] = $current - 1;
      
      $result = [
        'level' => 'focus',
        'remaining' => $state['resources']['focusPoints']['current'],
      ];
    }
    else {
      // Check and consume spell slot
      $slot_key = (string) $level;
      $current = $state['resources']['spellSlots'][$slot_key]['current'] ?? 0;
      if ($current <= 0) {
        throw new \InvalidArgumentException("No level {$level} spell slots remaining");
      }
      $state['resources']['spellSlots'][$slot_key]['current'] = $current - 1;
      
      $result = [
        'level' => $level,
        'remaining' => $state['resources']['spellSlots'][$slot_key]['current'],
      ];
    }
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $result;
  }

  /**
   * Use an action (track three-action economy).
   * 
   * @param string $character_id
   *   The character ID.
   * @param int $action_cost
   *   Number of actions to consume (1-3).
   * 
   * @return array
   *   Updated action economy state.
   * 
   * @throws \InvalidArgumentException
   *   If not enough actions remaining.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#use-an-action-track-three-action-economy
   */
  public function useAction(string $character_id, int $action_cost = 1): array {
    $state = $this->getState($character_id);
    
    $actions_remaining = $state['actions']['threeActionEconomy']['actionsRemaining'] ?? 0;
    if ($actions_remaining < $action_cost) {
      throw new \InvalidArgumentException("Not enough actions remaining (need {$action_cost}, have {$actions_remaining})");
    }
    
    $state['actions']['threeActionEconomy']['actionsRemaining'] = $actions_remaining - $action_cost;
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['actions']['threeActionEconomy'];
  }

  /**
   * Use reaction.
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return array
   *   Updated reaction state.
   * 
   * @throws \InvalidArgumentException
   *   If reaction already used.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#use-reaction
   */
  public function useReaction(string $character_id): array {
    $state = $this->getState($character_id);
    
    if (empty($state['actions']['threeActionEconomy']['reactionAvailable'])) {
      throw new \InvalidArgumentException('Reaction already used');
    }
    
    $state['actions']['threeActionEconomy']['reactionAvailable'] = FALSE;
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['actions']['threeActionEconomy'];
  }

  /**
   * Start new turn (reset actions and reaction).
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return array
   *   Reset action economy state.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#start-new-turn-reset-actions-and-reaction
   */
  public function startNewTurn(string $character_id): array {
    $state = $this->getState($character_id);
    
    // Reset action economy
    $state['actions']['threeActionEconomy']['actionsRemaining'] = 3;
    $state['actions']['threeActionEconomy']['reactionAvailable'] = TRUE;
    
    // Update condition durations (decrement round-based durations)
    $updated_conditions = [];
    foreach ($state['conditions'] as $condition) {
      if (!empty($condition['duration']) && $condition['duration']['type'] === 'rounds') {
        $condition['duration']['value'] = max(0, ($condition['duration']['value'] ?? 1) - 1);
        // Only keep conditions with duration remaining
        if ($condition['duration']['value'] > 0) {
          $updated_conditions[] = $condition;
        }
      }
      else {
        // Keep conditions without round-based duration
        $updated_conditions[] = $condition;
      }
    }
    $state['conditions'] = $updated_conditions;
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['actions']['threeActionEconomy'];
  }

  /**
   * Update inventory (add, remove, equip items).
   * 
   * @param string $character_id
   *   The character ID.
   * @param string $action
   *   Action: 'add', 'remove', 'equip', 'unequip'.
   * @param array $item
   *   Item data matching Item interface.
   * 
   * @return array
   *   Updated inventory state including bulk calculation.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#update-inventory-add-remove-equip-items
   */
  public function updateInventory(string $character_id, string $action, array $item): array {
    $state = $this->getState($character_id);
    
    switch ($action) {
      case 'add':
        $state['inventory']['carried'][] = $item;
        break;
        
      case 'remove':
        $state['inventory']['carried'] = array_values(array_filter(
          $state['inventory']['carried'],
          function ($i) use ($item) {
            return $i['id'] !== $item['id'];
          }
        ));
        break;
        
      case 'equip':
        // Remove from carried
        $state['inventory']['carried'] = array_values(array_filter(
          $state['inventory']['carried'],
          function ($i) use ($item) {
            return $i['id'] !== $item['id'];
          }
        ));
        // Add to worn
        if ($item['type'] === 'weapon') {
          $state['inventory']['worn']['weapons'][] = $item;
        }
        elseif ($item['type'] === 'armor') {
          $state['inventory']['worn']['armor'] = $item;
        }
        else {
          $state['inventory']['worn']['accessories'][] = $item;
        }
        break;
        
      case 'unequip':
        // Remove from worn and add to carried
        if ($item['type'] === 'weapon') {
          $state['inventory']['worn']['weapons'] = array_values(array_filter(
            $state['inventory']['worn']['weapons'],
            function ($i) use ($item) {
              return $i['id'] !== $item['id'];
            }
          ));
        }
        elseif ($item['type'] === 'armor' && !empty($state['inventory']['worn']['armor'])) {
          if ($state['inventory']['worn']['armor']['id'] === $item['id']) {
            unset($state['inventory']['worn']['armor']);
          }
        }
        else {
          $state['inventory']['worn']['accessories'] = array_values(array_filter(
            $state['inventory']['worn']['accessories'] ?? [],
            function ($i) use ($item) {
              return $i['id'] !== $item['id'];
            }
          ));
        }
        $state['inventory']['carried'][] = $item;
        break;
    }
    
    // Recalculate bulk
    $bulk_data = $this->calculateBulk($state);
    $state['inventory']['totalBulk'] = $bulk_data['totalBulk'];
    $state['inventory']['encumbrance'] = $bulk_data['encumbrance'];
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['inventory'];
  }

  /**
   * Gain experience points.
   * 
   * @param string $character_id
   *   The character ID.
   * @param int $xp
   *   Experience points to add.
   * 
   * @return array
   *   Updated XP and level up status.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#gain-experience-points
   */
  public function gainExperience(string $character_id, int $xp): array {
    $state = $this->getState($character_id);
    
    // Add XP
    $current_xp = $state['basicInfo']['experiencePoints'] + $xp;
    $state['basicInfo']['experiencePoints'] = $current_xp;
    
    // Check if level up is available
    $current_level = $state['basicInfo']['level'];
    $level_up_available = $this->isLevelUpAvailable($current_level, $current_xp);
    $xp_to_next_level = (1000 * $current_level) - $current_xp;
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return [
      'experiencePoints' => $current_xp,
      'level' => $current_level,
      'levelUpAvailable' => $level_up_available,
      'xpToNextLevel' => max(0, $xp_to_next_level),
    ];
  }

  /**
   * Apply optimistic update operation.
   * 
   * @param string $character_id
   *   The character ID.
   * @param array $operation
   *   Update operation with type, path, value, version.
   * 
   * @return array
   *   Result with success status and new version.
   * 
   * @throws \InvalidArgumentException
   *   If version conflict occurs.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#process-queued-updates-batch-send-to-server
   */
  public function applyUpdate(string $character_id, array $operation): array {
    // TODO: Implement optimistic locking
    // - Check operation['version'] matches current version
    // - Apply update to database
    // - Increment version
    // - Return new version
    // - Broadcast to WebSocket subscribers
    throw new \InvalidArgumentException('Not implemented');
  }

  /**
   * Recalculate bulk and encumbrance.
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return array
   *   Bulk and encumbrance data.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#recalculate-total-bulk-and-encumbrance
   */
  protected function recalculateBulk(string $character_id): array {
    $state = $this->getState($character_id);
    return $this->calculateBulk($state);
  }

  /**
   * Calculate bulk from inventory state.
   * 
   * @param array $state
   *   The character state.
   * 
   * @return array
   *   Bulk and encumbrance data.
   */
  protected function calculateBulk(array $state): array {
    $total_bulk = 0;
    
    // Add bulk from worn armor
    if (!empty($state['inventory']['worn']['armor'])) {
      $total_bulk += $state['inventory']['worn']['armor']['bulk'] ?? 0;
    }
    
    // Add bulk from worn weapons
    foreach ($state['inventory']['worn']['weapons'] ?? [] as $weapon) {
      $total_bulk += $weapon['bulk'] ?? 0;
    }
    
    // Add bulk from worn accessories
    foreach ($state['inventory']['worn']['accessories'] ?? [] as $accessory) {
      $total_bulk += $accessory['bulk'] ?? 0;
    }
    
    // Add bulk from carried items
    foreach ($state['inventory']['carried'] ?? [] as $item) {
      $total_bulk += ($item['bulk'] ?? 0) * ($item['quantity'] ?? 1);
    }
    
    // Calculate encumbrance based on STR
    $str_score = $state['abilities']['strength'] ?? 10;
    $encumbered_at = 5 + $str_score;
    $overloaded_at = 10 + $str_score;
    
    if ($total_bulk >= $overloaded_at) {
      $encumbrance = 'overloaded';
    }
    elseif ($total_bulk >= $encumbered_at) {
      $encumbrance = 'encumbered';
    }
    else {
      $encumbrance = 'unencumbered';
    }
    
    return [
      'totalBulk' => $total_bulk,
      'encumbrance' => $encumbrance,
    ];
  }

  /**
   * Check if character has enough XP to level up.
   * 
   * @param int $current_level
   *   Current character level.
   * @param int $current_xp
   *   Current experience points.
   * 
   * @return bool
   *   TRUE if level up is available.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#check-if-character-has-enough-xp-to-level-up
   */
  protected function isLevelUpAvailable(int $current_level, int $current_xp): bool {
    // PF2E XP table: 1000 XP per level (simplified)
    $xp_for_next_level = 1000 * $current_level;
    return $current_xp >= $xp_for_next_level;
  }

  /**
   * Save character state to database.
   * 
   * @param string $character_id
   *   The character ID.
   * @param array $state
   *   The character state array.
   * 
   * @return void
   */
  protected function saveState(string $character_id, array $state): void {
    // Extract fields for columns
    $name = $state['basicInfo']['name'];
    $level = $state['basicInfo']['level'];
    $ancestry = $state['basicInfo']['ancestry'];
    $class = $state['basicInfo']['class'];
    
    // Increment version for optimistic locking
    $state['metadata']['version'] = ($state['metadata']['version'] ?? 0) + 1;
    $state['metadata']['updatedAt'] = date('c');
    
    // Prepare character_data JSON (remove fields stored in columns)
    $character_data = $state;
    unset($character_data['characterId']);
    unset($character_data['userId']);
    
    // Update database
    $this->database->update('dc_characters')
      ->fields([
        'name' => $name,
        'level' => $level,
        'ancestry' => $ancestry,
        'class' => $class,
        'character_data' => json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        'changed' => time(),
      ])
      ->condition('id', $character_id)
      ->execute();
  }

}
