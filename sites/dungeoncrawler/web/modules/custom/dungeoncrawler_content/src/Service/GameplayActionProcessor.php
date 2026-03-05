<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Processes gameplay actions from AI responses and applies state mutations.
 *
 * When a player describes an action in chat (e.g., "I cast Light on my staff"),
 * the AI returns structured mechanical data alongside narrative text. This
 * service parses that data and applies state changes to both dungeon_data
 * (room state, entities, effects) and character_data (HP, spell slots, conditions).
 */
class GameplayActionProcessor {

  protected Connection $database;
  protected LoggerInterface $logger;

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_gameplay');
  }

  /**
   * Build the enhanced system prompt that includes character abilities.
   *
   * Provides the AI with full mechanical context so it can declare structured
   * actions alongside narrative responses.
   *
   * @param string $base_system_prompt
   *   The base GM system prompt from PromptManager.
   * @param array $character_data
   *   Full character_data JSON from dc_campaign_characters.
   * @param array $room_meta
   *   Room metadata (name, description, entities, state).
   *
   * @return string
   *   Enhanced system prompt with mechanical instructions.
   */
  public function buildEnhancedSystemPrompt(string $base_system_prompt, array $character_data, array $room_meta, array $room_inventory = []): string {
    $char_name = $character_data['name'] ?? 'the character';
    $char_class = $character_data['class'] ?? 'unknown';
    $char_level = $character_data['level'] ?? 1;
    $ancestry = $character_data['ancestry'] ?? 'unknown';
    $heritage = $character_data['heritage'] ?? '';
    $background = $character_data['background'] ?? '';

    // Abilities
    $abilities = $character_data['abilities'] ?? [];
    $ability_str = [];
    foreach ($abilities as $key => $val) {
      $mod = floor(($val - 10) / 2);
      $sign = $mod >= 0 ? '+' : '';
      $ability_str[] = strtoupper(substr($key, 0, 3)) . " {$val} ({$sign}{$mod})";
    }

    // HP
    $hp = $character_data['hit_points'] ?? [];
    $hp_current = $hp['current'] ?? $hp['max'] ?? 0;
    $hp_max = $hp['max'] ?? 0;
    $hero_points = $character_data['hero_points'] ?? 0;

    // Saves
    $saves = $character_data['saves'] ?? [];
    $save_str = [];
    foreach ($saves as $key => $val) {
      $save_str[] = ucfirst($key) . " +{$val}";
    }

    // Perception
    $perception = $character_data['perception'] ?? 0;

    // Skills
    $trained_skills = $character_data['trained_skills'] ?? [];
    $skills_str = implode(', ', $trained_skills);

    // Feats
    $feats = $character_data['feats'] ?? [];
    $feat_lines = [];
    foreach ($feats as $feat) {
      if (!is_array($feat)) {
        $feat_lines[] = "  - {$feat}";
        continue;
      }
      $fname = $feat['name'] ?? 'Unknown';
      $ftype = $feat['type'] ?? 'general';
      $fdesc = $feat['description'] ?? '';
      if (is_array($fdesc)) {
        $fdesc = implode(' ', array_filter($fdesc));
      }
      $feat_lines[] = "  - {$fname} ({$ftype})" . ($fdesc ? ": " . substr($fdesc, 0, 100) : '');
    }

    // Spells
    $spells = $character_data['spells'] ?? [];
    $tradition = $spells['tradition'] ?? 'arcane';
    $casting_ability = $spells['casting_ability'] ?? 'intelligence';
    $spell_dc = $spells['spell_dc'] ?? 0;
    $spell_attack = $spells['spell_attack'] ?? 0;
    $slots = $spells['slots'] ?? [];
    $cantrips = $spells['cantrips'] ?? [];
    $spellbook = $spells['spellbook'] ?? [];
    $slots_used = $spells['slots_used'] ?? [];

    $cantrip_names = array_map(function($s) {
      return is_array($s) ? ($s['name'] ?? $s) : $s;
    }, $cantrips);

    // 1st level spells may be in 'first_level' or 'spellbook' key
    $first_level_spells = $spells['first_level'] ?? $spells['spellbook'] ?? [];
    $spell_1_names = [];
    foreach ($first_level_spells as $spell) {
      $spell_1_names[] = is_array($spell) ? ($spell['name'] ?? $spell) : $spell;
    }

    $slot_info = [];
    // Map named keys to display format
    $slot_display_map = ['cantrips' => 'Cantrips', 'first' => '1st', 'second' => '2nd', 'third' => '3rd'];
    $slots_used = $spells['slots_used'] ?? [];
    foreach ($slots as $level_key => $count) {
      if ($level_key === 'cantrips') continue; // cantrips are at-will
      $display = $slot_display_map[$level_key] ?? $level_key;
      $used = $slots_used[$level_key] ?? 0;
      $remaining = $count - $used;
      $slot_info[] = "{$display} level: {$remaining}/{$count} remaining";
    }

    // Inventory
    $inventory = $character_data['inventory'] ?? [];
    $inv_lines = [];
    foreach ($inventory as $item) {
      if (is_array($item)) {
        $iname = $item['name'] ?? 'Unknown';
        $inv_lines[] = $iname;
      }
    }

    // Conditions
    $conditions = $character_data['conditions'] ?? [];

    // Room context
    $room_name = $room_meta['name'] ?? 'Unknown Room';
    $room_desc = $room_meta['description'] ?? '';
    $room_lighting = $room_meta['lighting'] ?? 'normal';
    if (is_array($room_lighting)) {
      $room_lighting = $room_lighting['level'] ?? 'normal';
    }
    $room_terrain = $room_meta['terrain'] ?? 'normal';
    if (is_array($room_terrain)) {
      $room_terrain = $room_terrain['type'] ?? 'normal';
    }

    // Room inventory from caller (entities, items, environment, effects).
    $room_inventory_data = $room_inventory;

    $enhanced = $base_system_prompt . "\n\n";
    $enhanced .= "=== ACTIVE CHARACTER ===\n";
    $enhanced .= "Name: {$char_name} | Level {$char_level} {$ancestry}" . ($heritage ? " ({$heritage})" : '') . " {$char_class}\n";
    if ($background) {
      $enhanced .= "Background: {$background}\n";
    }
    $enhanced .= "HP: {$hp_current}/{$hp_max} | Hero Points: {$hero_points}\n";
    $enhanced .= "Abilities: " . implode(', ', $ability_str) . "\n";
    $enhanced .= "Saves: " . implode(', ', $save_str) . " | Perception: +{$perception}\n";
    $enhanced .= "Trained Skills: {$skills_str}\n";
    $enhanced .= "\nFeats:\n" . implode("\n", $feat_lines) . "\n";
    $enhanced .= "\nSpellcasting ({$tradition}, DC {$spell_dc}, attack +{$spell_attack}):\n";
    $enhanced .= "  Cantrips (at will): " . implode(', ', $cantrip_names) . "\n";
    if (!empty($slot_info)) {
      $enhanced .= "  Spell Slots: " . implode('; ', $slot_info) . "\n";
    }
    if (!empty($spell_1_names)) {
      $enhanced .= "  Spellbook (1st): " . implode(', ', $spell_1_names) . "\n";
    }
    $enhanced .= "\nInventory: " . implode(', ', $inv_lines) . "\n";
    if (!empty($conditions)) {
      $enhanced .= "Conditions: " . implode(', ', $conditions) . "\n";
    }
    $enhanced .= "\n=== CURRENT ROOM ===\n";
    $enhanced .= "Room: {$room_name}\n";
    if ($room_desc) {
      $enhanced .= "Description: {$room_desc}\n";
    }
    $enhanced .= "Lighting: {$room_lighting} | Terrain: {$room_terrain}\n";

    // Environment tags.
    $env_tags = $room_inventory_data['environment_tags'] ?? [];
    if (!empty($env_tags)) {
      $enhanced .= "Environment: " . implode(', ', $env_tags) . "\n";
    }

    // NPCs present.
    $npcs = $room_inventory_data['npcs'] ?? [];
    if (!empty($npcs)) {
      $enhanced .= "\nNPCs present:\n";
      foreach ($npcs as $npc) {
        $npc_line = "  - {$npc['name']}";
        if (!empty($npc['type'])) {
          $npc_line .= " ({$npc['type']})";
        }
        if (!empty($npc['role'])) {
          $npc_line .= " [" . $npc['role'] . "]";
        }
        if (!empty($npc['hp_status'])) {
          $npc_line .= " - HP: " . $npc['hp_status'];
        }
        if (!empty($npc['description'])) {
          $npc_line .= " — " . substr($npc['description'], 0, 120);
        }
        $enhanced .= $npc_line . "\n";
      }
    }

    // Obstacles / furniture / environmental objects.
    $obstacles = $room_inventory_data['obstacles'] ?? [];
    if (!empty($obstacles)) {
      $enhanced .= "\nObstacles/objects:\n";
      foreach ($obstacles as $obj) {
        $obj_line = "  - {$obj['name']}";
        if (!empty($obj['description'])) {
          $obj_line .= " — " . substr($obj['description'], 0, 100);
        }
        if (!empty($obj['impassable'])) {
          $obj_line .= " [impassable]";
        }
        $enhanced .= $obj_line . "\n";
      }
    }

    // Hazards.
    $hazards = $room_inventory_data['hazards'] ?? [];
    if (!empty($hazards)) {
      $enhanced .= "\nHazards:\n";
      foreach ($hazards as $hazard) {
        $h_line = "  - {$hazard['name']}";
        if (!empty($hazard['description'])) {
          $h_line .= " — " . substr($hazard['description'], 0, 100);
        }
        if (!empty($hazard['detected'])) {
          $h_line .= " [detected]";
        }
        $enhanced .= $h_line . "\n";
      }
    }

    // Detected traps.
    $traps = $room_inventory_data['traps'] ?? [];
    if (!empty($traps)) {
      $enhanced .= "\nDetected traps:\n";
      foreach ($traps as $trap) {
        $t_line = "  - {$trap['name']}";
        if (!empty($trap['description'])) {
          $t_line .= " — " . substr($trap['description'], 0, 100);
        }
        $enhanced .= $t_line . "\n";
      }
    }

    // Items on the ground / loot.
    $ground_items = $room_inventory_data['items'] ?? [];
    if (!empty($ground_items)) {
      $enhanced .= "\nItems in room:\n";
      foreach ($ground_items as $item) {
        $i_line = "  - {$item['name']}";
        if (!empty($item['quantity']) && $item['quantity'] > 1) {
          $i_line .= " (x{$item['quantity']})";
        }
        if (!empty($item['description'])) {
          $i_line .= " — " . substr($item['description'], 0, 80);
        }
        $enhanced .= $i_line . "\n";
      }
    }

    // Active room effects (spells, environmental hazards, etc.).
    $active_effects = $room_inventory_data['active_effects'] ?? [];
    if (!empty($active_effects)) {
      $enhanced .= "\nActive effects:\n";
      foreach ($active_effects as $effect) {
        $eff_name = is_array($effect) ? ($effect['name'] ?? 'Unknown') : $effect;
        $eff_desc = is_array($effect) ? ($effect['description'] ?? '') : '';
        $eff_line = "  - {$eff_name}";
        if ($eff_desc) {
          $eff_line .= " — " . substr($eff_desc, 0, 80);
        }
        $enhanced .= $eff_line . "\n";
      }
    }

    $enhanced .= <<<'INSTRUCTIONS'

=== MECHANICAL ACTION INSTRUCTIONS ===
When the player describes an action that has mechanical effects, you MUST include a JSON block at the END of your narrative response, wrapped in ```json and ``` markers.

The JSON block should declare ALL mechanical state changes that result from the action. Format:

```json
{
  "actions": [
    {
      "type": "cast_spell|use_skill|use_feat|strike|stride|interact|recall_knowledge|perception_check|save|other",
      "name": "Specific action name (e.g., 'Cast Light', 'Recall Knowledge: Arcana')",
      "details": {
        "spell_name": "light",
        "spell_level": "cantrip",
        "skill_used": "arcana",
        "feat_used": "experienced_smuggler",
        "roll_needed": "d20+5",
        "dc": 15,
        "result_description": "Brief mechanical outcome"
      },
      "state_changes": {
        "character": {
          "hp_delta": 0,
          "temp_hp": 0,
          "spell_slot_used": null,
          "conditions_add": [],
          "conditions_remove": [],
          "hero_points_delta": 0,
          "inventory_add": [],
          "inventory_remove": []
        },
        "room": {
          "lighting_change": null,
          "effects_add": [{"name": "light_spell", "hex": "3,2", "duration": "until_dismissed", "description": "Magical light illuminates the area"}],
          "effects_remove": [],
          "entities_add": [],
          "entities_modify": [],
          "terrain_change": null
        }
      }
    }
  ],
  "dice_rolls": [
    {"type": "d20", "modifier": 5, "result": 18, "total": 23, "purpose": "Arcana check"}
  ]
}
```

Rules for mechanical responses:
1. ALWAYS include the JSON block when the player attempts any mechanical action (spell, skill check, attack, feat usage, exploration action).
2. For spells that consume a spell slot (non-cantrips), set spell_slot_used to the level number (e.g., 1 for 1st-level).
3. For cantrips, spell_slot_used should be null.
4. Roll dice for the player when checks are needed. Use the character's actual modifiers.
5. If no mechanical action occurs (pure roleplay/conversation), do NOT include a JSON block.
6. Keep narrative text BEFORE the JSON block. The JSON block must be the LAST thing in your response.
7. Respect the character's current resources - don't let them cast if they have no slots.
8. Track conditions properly (frightened reduces by 1 each turn, etc.).

INSTRUCTIONS;

    return $enhanced;
  }

  /**
   * Parse structured action data from an AI response.
   *
   * Extracts the JSON block (if any) from the GM's narrative response.
   *
   * @param string $response
   *   The full AI response text.
   *
   * @return array
   *   ['narrative' => string, 'actions' => array|null, 'dice_rolls' => array]
   */
  public function parseResponse(string $response): array {
    $result = [
      'narrative' => $response,
      'actions' => NULL,
      'dice_rolls' => [],
    ];

    // Look for a JSON block wrapped in ```json ... ```
    if (preg_match('/```json\s*\n?(.*?)\n?\s*```/s', $response, $matches)) {
      $json_str = trim($matches[1]);
      $parsed = json_decode($json_str, TRUE);

      if ($parsed !== NULL && is_array($parsed)) {
        $result['actions'] = $parsed['actions'] ?? [];
        $result['dice_rolls'] = $parsed['dice_rolls'] ?? [];

        // Strip the JSON block from the narrative
        $result['narrative'] = trim(preg_replace('/```json\s*\n?.*?\n?\s*```/s', '', $response));
      }
      else {
        $this->logger->warning('Failed to parse JSON action block from GM response: @json', [
          '@json' => substr($json_str, 0, 500),
        ]);
      }
    }

    return $result;
  }

  /**
   * Apply character state changes from parsed actions.
   *
   * @param int $character_id
   *   The character row ID in dc_campaign_characters.
   * @param array $actions
   *   Parsed actions array from parseResponse().
   *
   * @return array
   *   State diff: what changed.
   */
  public function applyCharacterStateChanges(int $character_id, array $actions): array {
    $diff = [
      'hp_before' => NULL,
      'hp_after' => NULL,
      'spell_slots_before' => [],
      'spell_slots_after' => [],
      'conditions_added' => [],
      'conditions_removed' => [],
      'inventory_added' => [],
      'inventory_removed' => [],
      'hero_points_before' => NULL,
      'hero_points_after' => NULL,
    ];

    if (empty($actions)) {
      return $diff;
    }

    // Load character data
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['character_data'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      $this->logger->error('Character @id not found for state update.', ['@id' => $character_id]);
      return $diff;
    }

    $char_data = json_decode($record['character_data'], TRUE) ?? [];
    $changed = FALSE;

    // Snapshot before values
    $diff['hp_before'] = $char_data['hit_points']['current'] ?? ($char_data['hit_points']['max'] ?? 0);
    $diff['spell_slots_before'] = $char_data['spells']['slots_used'] ?? [];
    $diff['hero_points_before'] = $char_data['hero_points'] ?? 0;

    foreach ($actions as $action) {
      $state_changes = $action['state_changes']['character'] ?? [];

      // HP delta
      if (!empty($state_changes['hp_delta'])) {
        $delta = (int) $state_changes['hp_delta'];
        $current = $char_data['hit_points']['current'] ?? ($char_data['hit_points']['max'] ?? 0);
        $max = $char_data['hit_points']['max'] ?? 0;
        $new_hp = max(0, min($max, $current + $delta));
        $char_data['hit_points']['current'] = $new_hp;
        $changed = TRUE;
      }

      // Temp HP
      if (!empty($state_changes['temp_hp'])) {
        $char_data['hit_points']['temp'] = max(
          $char_data['hit_points']['temp'] ?? 0,
          (int) $state_changes['temp_hp']
        );
        $changed = TRUE;
      }

      // Spell slot usage
      if (!empty($state_changes['spell_slot_used'])) {
        $level = (string) $state_changes['spell_slot_used'];
        // Map numeric level to actual slot key (character_data uses "first", "second", etc.)
        $slot_key_map = [
          '1' => 'first', '2' => 'second', '3' => 'third',
          '4' => 'fourth', '5' => 'fifth', '6' => 'sixth',
          '7' => 'seventh', '8' => 'eighth', '9' => 'ninth', '10' => 'tenth',
          'first' => 'first', 'second' => 'second', 'third' => 'third',
        ];
        $slot_key = $slot_key_map[$level] ?? $level;

        if (!isset($char_data['spells']['slots_used'])) {
          $char_data['spells']['slots_used'] = [];
        }
        $used = ($char_data['spells']['slots_used'][$slot_key] ?? 0) + 1;
        $max_slots = $char_data['spells']['slots'][$slot_key] ?? 0;
        // Don't exceed max slots
        $char_data['spells']['slots_used'][$slot_key] = min($used, $max_slots);
        $changed = TRUE;
      }

      // Conditions add
      if (!empty($state_changes['conditions_add'])) {
        if (!isset($char_data['conditions'])) {
          $char_data['conditions'] = [];
        }
        foreach ($state_changes['conditions_add'] as $condition) {
          $cond_name = is_array($condition) ? ($condition['name'] ?? $condition) : $condition;
          // Avoid duplicates
          $exists = FALSE;
          foreach ($char_data['conditions'] as $existing) {
            $existing_name = is_array($existing) ? ($existing['name'] ?? $existing) : $existing;
            if ($existing_name === $cond_name) {
              $exists = TRUE;
              break;
            }
          }
          if (!$exists) {
            $char_data['conditions'][] = is_array($condition) ? $condition : ['name' => $condition, 'value' => 1];
            $diff['conditions_added'][] = $cond_name;
          }
        }
        $changed = TRUE;
      }

      // Conditions remove
      if (!empty($state_changes['conditions_remove'])) {
        if (isset($char_data['conditions'])) {
          foreach ($state_changes['conditions_remove'] as $cond_to_remove) {
            $cond_name = is_array($cond_to_remove) ? ($cond_to_remove['name'] ?? $cond_to_remove) : $cond_to_remove;
            $char_data['conditions'] = array_values(array_filter($char_data['conditions'], function($existing) use ($cond_name) {
              $existing_name = is_array($existing) ? ($existing['name'] ?? $existing) : $existing;
              return $existing_name !== $cond_name;
            }));
            $diff['conditions_removed'][] = $cond_name;
          }
          $changed = TRUE;
        }
      }

      // Hero points
      if (!empty($state_changes['hero_points_delta'])) {
        $hp_current = $char_data['hero_points'] ?? 0;
        $char_data['hero_points'] = max(0, min(3, $hp_current + (int) $state_changes['hero_points_delta']));
        $changed = TRUE;
      }

      // Inventory add
      if (!empty($state_changes['inventory_add'])) {
        if (!isset($char_data['inventory'])) {
          $char_data['inventory'] = [];
        }
        foreach ($state_changes['inventory_add'] as $item) {
          $char_data['inventory'][] = is_array($item) ? $item : ['name' => $item, 'quantity' => 1];
          $diff['inventory_added'][] = is_array($item) ? ($item['name'] ?? $item) : $item;
        }
        $changed = TRUE;
      }

      // Inventory remove
      if (!empty($state_changes['inventory_remove'])) {
        foreach ($state_changes['inventory_remove'] as $item_to_remove) {
          $item_name = is_array($item_to_remove) ? ($item_to_remove['name'] ?? $item_to_remove) : $item_to_remove;
          foreach ($char_data['inventory'] as $key => $inv_item) {
            $inv_name = is_array($inv_item) ? ($inv_item['name'] ?? $inv_item) : $inv_item;
            if (strtolower($inv_name) === strtolower($item_name)) {
              unset($char_data['inventory'][$key]);
              $char_data['inventory'] = array_values($char_data['inventory']);
              $diff['inventory_removed'][] = $item_name;
              break;
            }
          }
        }
        $changed = TRUE;
      }
    }

    // Snapshot after values
    $diff['hp_after'] = $char_data['hit_points']['current'] ?? 0;
    $diff['spell_slots_after'] = $char_data['spells']['slots_used'] ?? [];
    $diff['hero_points_after'] = $char_data['hero_points'] ?? 0;

    // Persist changes
    if ($changed) {
      $this->database->update('dc_campaign_characters')
        ->fields([
          'character_data' => json_encode($char_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
          'changed' => time(),
        ])
        ->condition('id', $character_id)
        ->execute();

      $this->logger->info('Character @id state updated: HP @hp_before->@hp_after, conditions added: @added, removed: @removed', [
        '@id' => $character_id,
        '@hp_before' => $diff['hp_before'],
        '@hp_after' => $diff['hp_after'],
        '@added' => implode(',', $diff['conditions_added']),
        '@removed' => implode(',', $diff['conditions_removed']),
      ]);
    }

    return $diff;
  }

  /**
   * Apply room/dungeon state changes from parsed actions.
   *
   * @param int $dungeon_id
   *   The dungeon record ID.
   * @param int $campaign_id
   *   The campaign ID.
   * @param int|string $room_index
   *   Array index of the room in dungeon_data['rooms'].
   * @param array &$dungeon_data
   *   The full dungeon_data array (modified in place).
   * @param array $actions
   *   Parsed actions array from parseResponse().
   *
   * @return array
   *   State diff: what changed in the room.
   */
  public function applyRoomStateChanges(int|string $dungeon_id, int $campaign_id, int|string $room_index, array &$dungeon_data, array $actions): array {
    $diff = [
      'effects_added' => [],
      'effects_removed' => [],
      'entities_modified' => [],
      'entities_added' => [],
      'lighting_change' => NULL,
      'terrain_change' => NULL,
    ];

    if (empty($actions)) {
      return $diff;
    }

    // Initialize room state storage if needed
    if (!isset($dungeon_data['rooms'][$room_index]['gameplay_state'])) {
      $dungeon_data['rooms'][$room_index]['gameplay_state'] = [
        'active_effects' => [],
        'explored_hexes' => [],
        'environmental_changes' => [],
      ];
    }

    $gameplay_state = &$dungeon_data['rooms'][$room_index]['gameplay_state'];

    foreach ($actions as $action) {
      $room_changes = $action['state_changes']['room'] ?? [];

      // Lighting change
      if (!empty($room_changes['lighting_change'])) {
        $old_lighting = $dungeon_data['rooms'][$room_index]['lighting'] ?? 'normal';
        $dungeon_data['rooms'][$room_index]['lighting'] = $room_changes['lighting_change'];
        $diff['lighting_change'] = [
          'from' => $old_lighting,
          'to' => $room_changes['lighting_change'],
        ];
      }

      // Effects add
      if (!empty($room_changes['effects_add'])) {
        foreach ($room_changes['effects_add'] as $effect) {
          $effect_entry = is_array($effect) ? $effect : ['name' => $effect];
          $effect_entry['added_at'] = date('c');
          $gameplay_state['active_effects'][] = $effect_entry;
          $diff['effects_added'][] = $effect_entry;
        }
      }

      // Effects remove
      if (!empty($room_changes['effects_remove'])) {
        foreach ($room_changes['effects_remove'] as $effect_to_remove) {
          $effect_name = is_array($effect_to_remove) ? ($effect_to_remove['name'] ?? $effect_to_remove) : $effect_to_remove;
          $gameplay_state['active_effects'] = array_values(array_filter(
            $gameplay_state['active_effects'],
            function($e) use ($effect_name) {
              $ename = is_array($e) ? ($e['name'] ?? $e) : $e;
              return $ename !== $effect_name;
            }
          ));
          $diff['effects_removed'][] = $effect_name;
        }
      }

      // Entities add
      if (!empty($room_changes['entities_add'])) {
        if (!isset($dungeon_data['entities'])) {
          $dungeon_data['entities'] = [];
        }
        foreach ($room_changes['entities_add'] as $entity) {
          $entity_entry = is_array($entity) ? $entity : ['entity_type' => 'object', 'entity_ref' => $entity];
          if (empty($entity_entry['entity_instance_id'])) {
            $entity_entry['entity_instance_id'] = 'gameplay_' . uniqid();
          }
          $dungeon_data['entities'][] = $entity_entry;
          $diff['entities_added'][] = $entity_entry;
        }
      }

      // Entities modify
      if (!empty($room_changes['entities_modify'])) {
        foreach ($room_changes['entities_modify'] as $mod) {
          $target_id = $mod['entity_instance_id'] ?? '';
          if ($target_id && isset($dungeon_data['entities'])) {
            foreach ($dungeon_data['entities'] as &$entity) {
              if (($entity['entity_instance_id'] ?? '') === $target_id) {
                if (isset($mod['state'])) {
                  $entity['state'] = array_merge($entity['state'] ?? [], $mod['state']);
                }
                $diff['entities_modified'][] = $mod;
                break;
              }
            }
            unset($entity);
          }
        }
      }

      // Terrain change
      if (!empty($room_changes['terrain_change'])) {
        $old_terrain = $dungeon_data['rooms'][$room_index]['terrain'] ?? 'normal';
        $dungeon_data['rooms'][$room_index]['terrain'] = $room_changes['terrain_change'];
        $diff['terrain_change'] = [
          'from' => $old_terrain,
          'to' => $room_changes['terrain_change'],
        ];
      }

      // Environmental changes log
      $gameplay_state['environmental_changes'][] = [
        'action_type' => $action['type'] ?? 'unknown',
        'action_name' => $action['name'] ?? 'Unknown Action',
        'timestamp' => date('c'),
        'details' => $action['details'] ?? [],
      ];
    }

    return $diff;
  }

  /**
   * Load character data for prompt building.
   *
   * @param int $character_id
   *   Character ID.
   *
   * @return array|null
   *   Character data array, or NULL if not found.
   */
  public function loadCharacterData(int $character_id): ?array {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['character_data', 'name', 'level', 'ancestry', 'class'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return NULL;
    }

    $char_data = json_decode($record['character_data'], TRUE) ?? [];
    // Ensure top-level fields are available
    $char_data['name'] = $char_data['name'] ?? $record['name'];
    $char_data['class'] = $char_data['class'] ?? $record['class'];
    $char_data['ancestry'] = $char_data['ancestry'] ?? $record['ancestry'];
    $char_data['level'] = $char_data['level'] ?? $record['level'];

    return $char_data;
  }

  /**
   * Build a summary of state changes for chat display.
   *
   * @param array $char_diff
   *   Character state diff from applyCharacterStateChanges().
   * @param array $room_diff
   *   Room state diff from applyRoomStateChanges().
   * @param array $dice_rolls
   *   Dice rolls from the parsed response.
   * @param array $actions
   *   Parsed actions.
   *
   * @return array
   *   A summary suitable for JSON response to the client.
   */
  public function buildStateDiffSummary(array $char_diff, array $room_diff, array $dice_rolls, array $actions): array {
    $summary = [
      'has_mechanical_effects' => !empty($actions),
      'actions_taken' => [],
      'dice_rolls' => $dice_rolls,
      'character_changes' => [],
      'room_changes' => [],
    ];

    foreach ($actions as $action) {
      $summary['actions_taken'][] = [
        'type' => $action['type'] ?? 'unknown',
        'name' => $action['name'] ?? 'Unknown',
        'details' => $action['details'] ?? [],
      ];
    }

    // Character changes
    if ($char_diff['hp_before'] !== $char_diff['hp_after']) {
      $summary['character_changes'][] = [
        'field' => 'hp',
        'from' => $char_diff['hp_before'],
        'to' => $char_diff['hp_after'],
      ];
    }
    if ($char_diff['spell_slots_before'] !== $char_diff['spell_slots_after']) {
      $summary['character_changes'][] = [
        'field' => 'spell_slots_used',
        'from' => $char_diff['spell_slots_before'],
        'to' => $char_diff['spell_slots_after'],
      ];
    }
    if (!empty($char_diff['conditions_added'])) {
      $summary['character_changes'][] = [
        'field' => 'conditions',
        'added' => $char_diff['conditions_added'],
      ];
    }
    if (!empty($char_diff['conditions_removed'])) {
      $summary['character_changes'][] = [
        'field' => 'conditions',
        'removed' => $char_diff['conditions_removed'],
      ];
    }
    if (!empty($char_diff['inventory_added'])) {
      $summary['character_changes'][] = [
        'field' => 'inventory',
        'added' => $char_diff['inventory_added'],
      ];
    }
    if (!empty($char_diff['inventory_removed'])) {
      $summary['character_changes'][] = [
        'field' => 'inventory',
        'removed' => $char_diff['inventory_removed'],
      ];
    }
    if ($char_diff['hero_points_before'] !== $char_diff['hero_points_after']) {
      $summary['character_changes'][] = [
        'field' => 'hero_points',
        'from' => $char_diff['hero_points_before'],
        'to' => $char_diff['hero_points_after'],
      ];
    }

    // Room changes
    if (!empty($room_diff['effects_added'])) {
      $summary['room_changes'][] = [
        'type' => 'effects_added',
        'effects' => $room_diff['effects_added'],
      ];
    }
    if (!empty($room_diff['effects_removed'])) {
      $summary['room_changes'][] = [
        'type' => 'effects_removed',
        'effects' => $room_diff['effects_removed'],
      ];
    }
    if (!empty($room_diff['lighting_change'])) {
      $summary['room_changes'][] = [
        'type' => 'lighting',
        'from' => $room_diff['lighting_change']['from'],
        'to' => $room_diff['lighting_change']['to'],
      ];
    }
    if (!empty($room_diff['entities_added'])) {
      $summary['room_changes'][] = [
        'type' => 'entities_added',
        'entities' => $room_diff['entities_added'],
      ];
    }
    if (!empty($room_diff['entities_modified'])) {
      $summary['room_changes'][] = [
        'type' => 'entities_modified',
        'entities' => $room_diff['entities_modified'],
      ];
    }

    return $summary;
  }

  /**
   * Build full room inventory context for the GM system prompt.
   *
   * Collects NPCs, obstacles, hazards, traps, items on the ground,
   * environment tags, and active effects from dungeon_data and DB.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room ID within the dungeon.
   * @param array $room_meta
   *   Room metadata from dungeon_data['rooms'][$index].
   * @param array $dungeon_data
   *   Full dungeon_data payload.
   *
   * @return array
   *   Structured room inventory:
   *   - environment_tags: string[]
   *   - npcs: array[]
   *   - obstacles: array[]
   *   - hazards: array[]
   *   - traps: array[] (only detected ones)
   *   - items: array[]
   *   - active_effects: array[]
   */
  public function buildRoomInventory(int $campaign_id, string $room_id, array $room_meta, array $dungeon_data): array {
    $inventory = [
      'environment_tags' => [],
      'npcs' => [],
      'obstacles' => [],
      'hazards' => [],
      'traps' => [],
      'items' => [],
      'active_effects' => [],
    ];

    // 1. Environment tags from static room definition.
    try {
      $room_row = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['environment_tags', 'contents_data'])
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $room_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      if ($room_row) {
        $env_tags = json_decode($room_row['environment_tags'] ?? '', TRUE);
        if (is_array($env_tags)) {
          $inventory['environment_tags'] = $env_tags;
        }

        // Static contents_data (placed objects from room JSON).
        $static_contents = json_decode($room_row['contents_data'] ?? '', TRUE);
        if (is_array($static_contents)) {
          // Static NPCs.
          foreach ($static_contents['npcs'] ?? [] as $npc) {
            $inventory['npcs'][] = [
              'name' => $npc['name'] ?? 'Unknown NPC',
              'type' => $npc['type'] ?? '',
              'role' => $npc['role'] ?? 'neutral',
              'description' => $npc['description'] ?? '',
              'hp_status' => '',
            ];
          }
          // Static items (placed in the room definition).
          foreach ($static_contents['items'] ?? [] as $item) {
            $inventory['items'][] = [
              'name' => $item['name'] ?? 'Unknown Item',
              'description' => $item['description'] ?? '',
              'quantity' => 1,
            ];
          }
          // Static obstacles.
          foreach ($static_contents['obstacles'] ?? [] as $obs) {
            $inventory['obstacles'][] = [
              'name' => $obs['name'] ?? 'Unknown Object',
              'description' => $obs['description'] ?? '',
              'impassable' => !empty($obs['impassable']),
            ];
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to load static room data for inventory: @error', ['@error' => $e->getMessage()]);
    }

    // 2. Runtime entities from dungeon_data (live state - overrides/enriches static data).
    $entities = $room_meta['entities'] ?? [];
    $seen_names = [];

    foreach ($entities as $entity) {
      $name = $entity['state']['metadata']['display_name']
        ?? $entity['name']
        ?? 'Unknown';
      $type = $entity['type'] ?? ($entity['entity_ref']['type'] ?? 'npc');
      $description = $entity['description']
        ?? $entity['state']['metadata']['description']
        ?? '';
      $role = $entity['role'] ?? ($entity['state']['metadata']['role'] ?? '');
      $is_hidden = !empty($entity['hidden']) || !empty($entity['state']['hidden']);
      $is_detected = !empty($entity['detected']) || !empty($entity['state']['detected']);

      // Skip completely hidden entities the party hasn't detected.
      if ($is_hidden && !$is_detected) {
        continue;
      }

      // HP status for encountered creatures.
      $hp_status = '';
      $stats = $entity['state']['metadata']['stats'] ?? $entity['stats'] ?? [];
      if (!empty($stats['hp_current']) && !empty($stats['hp_max'])) {
        $pct = round(($stats['hp_current'] / $stats['hp_max']) * 100);
        if ($pct >= 75) {
          $hp_status = 'healthy';
        }
        elseif ($pct >= 50) {
          $hp_status = 'hurt';
        }
        elseif ($pct >= 25) {
          $hp_status = 'bloodied';
        }
        else {
          $hp_status = 'near death';
        }
      }

      // Conditions on this entity.
      $conditions = $entity['state']['conditions'] ?? [];
      $cond_str = !empty($conditions) ? implode(', ', $conditions) : '';

      switch ($type) {
        case 'npc':
        case 'creature':
          $npc_entry = [
            'name' => $name,
            'type' => $entity['entity_ref']['content_id'] ?? $type,
            'role' => $role,
            'description' => $description,
            'hp_status' => $hp_status,
          ];
          if ($cond_str) {
            $npc_entry['conditions'] = $cond_str;
          }
          $inventory['npcs'][] = $npc_entry;
          $seen_names[] = $name;
          break;

        case 'obstacle':
          $inventory['obstacles'][] = [
            'name' => $name,
            'description' => $description,
            'impassable' => !empty($entity['impassable']),
          ];
          $seen_names[] = $name;
          break;

        case 'hazard':
          $inventory['hazards'][] = [
            'name' => $name,
            'description' => $description,
            'detected' => $is_detected,
          ];
          $seen_names[] = $name;
          break;

        case 'trap':
          // Only include detected traps.
          if ($is_detected) {
            $inventory['traps'][] = [
              'name' => $name,
              'description' => $description,
            ];
            $seen_names[] = $name;
          }
          break;
      }
    }

    // Deduplicate: remove static NPCs/obstacles that are also in the runtime entities.
    if (!empty($seen_names)) {
      $inventory['npcs'] = array_values(array_filter($inventory['npcs'], function ($npc) use ($seen_names) {
        // Keep runtime entries; remove static duplicates.
        static $idx = 0;
        $idx++;
        // Static entries were added first, so if we've seen this name in runtime, skip the static copy.
        return !in_array($npc['name'], $seen_names, TRUE) || !empty($npc['hp_status']) || !empty($npc['conditions']);
      }));
      $inventory['obstacles'] = array_values(array_filter($inventory['obstacles'], function ($obj) use ($seen_names) {
        return !in_array($obj['name'], $seen_names, TRUE);
      }));
    }

    // 3. Runtime entity instances from dc_campaign_characters (NPC/hazard/trap records).
    try {
      $entity_rows = $this->database->select('dc_campaign_characters', 'e')
        ->fields('e', ['name', 'type', 'state_data', 'character_data'])
        ->condition('campaign_id', $campaign_id)
        ->condition('location_type', 'room')
        ->condition('location_ref', $room_id)
        ->execute()
        ->fetchAll();

      foreach ($entity_rows as $row) {
        $ename = $row->name ?? 'Unknown';
        $etype = $row->type ?? 'npc';
        // Skip if we already have this entity from dungeon_data.
        if (in_array($ename, $seen_names, TRUE)) {
          continue;
        }

        $estate = json_decode($row->state_data ?? '{}', TRUE) ?: [];
        $echar = json_decode($row->character_data ?? '{}', TRUE) ?: [];
        $is_hidden = !empty($estate['hidden']);
        $is_detected = !empty($estate['detected']);

        if ($is_hidden && !$is_detected) {
          continue;
        }

        $edesc = $echar['description'] ?? ($estate['description'] ?? '');

        switch ($etype) {
          case 'npc':
          case 'creature':
            $inventory['npcs'][] = [
              'name' => $ename,
              'type' => $etype,
              'role' => $echar['role'] ?? 'neutral',
              'description' => $edesc,
              'hp_status' => '',
            ];
            break;
          case 'hazard':
            $inventory['hazards'][] = [
              'name' => $ename,
              'description' => $edesc,
              'detected' => $is_detected,
            ];
            break;
          case 'trap':
            if ($is_detected) {
              $inventory['traps'][] = [
                'name' => $ename,
                'description' => $edesc,
              ];
            }
            break;
          case 'obstacle':
            $inventory['obstacles'][] = [
              'name' => $ename,
              'description' => $edesc,
              'impassable' => !empty($estate['impassable']),
            ];
            break;
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to load entity instances for room inventory: @error', ['@error' => $e->getMessage()]);
    }

    // 4. Items on the ground from dc_campaign_item_instances.
    try {
      $item_rows = $this->database->select('dc_campaign_item_instances', 'i')
        ->fields('i', ['item_id', 'item_instance_id', 'quantity', 'state_data'])
        ->condition('campaign_id', $campaign_id)
        ->condition('location_type', 'room')
        ->condition('location_ref', $room_id)
        ->execute()
        ->fetchAll();

      foreach ($item_rows as $irow) {
        $istate = json_decode($irow->state_data ?? '{}', TRUE) ?: [];
        $iname = $istate['name'] ?? $irow->item_id;
        $idesc = $istate['description'] ?? '';

        // Try to resolve the display name from the content registry if not in state_data.
        if ($iname === $irow->item_id) {
          try {
            $registry = $this->database->select('dc_campaign_content_registry', 'cr')
              ->fields('cr', ['schema_data'])
              ->condition('content_id', $irow->item_id)
              ->condition('content_type', 'item')
              ->range(0, 1)
              ->execute()
              ->fetchField();

            if ($registry) {
              $schema = json_decode($registry, TRUE) ?: [];
              $iname = $schema['name'] ?? $irow->item_id;
              if (!$idesc) {
                $idesc = $schema['description'] ?? '';
              }
            }
          }
          catch (\Exception $e) {
            // Swallow — name will remain item_id.
          }
        }

        $inventory['items'][] = [
          'name' => $iname,
          'description' => $idesc,
          'quantity' => (int) ($irow->quantity ?? 1),
        ];
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to load item instances for room inventory: @error', ['@error' => $e->getMessage()]);
    }

    // 5. Active effects from gameplay_state.
    $gameplay_state = $room_meta['gameplay_state'] ?? [];
    $inventory['active_effects'] = $gameplay_state['active_effects'] ?? [];

    return $inventory;
  }

}
