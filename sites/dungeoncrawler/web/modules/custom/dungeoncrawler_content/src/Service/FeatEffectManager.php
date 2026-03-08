<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Resolves feat-driven derived effects for character data.
 */
class FeatEffectManager {

  /**
   * Build feat effect state from selected feats.
   *
   * @param array $character_data
   *   Character payload from character_data JSON.
   * @param array $context
   *   Optional derivation context (level, base_speed, existing_hp_max).
   *
   * @return array
   *   Feat effect state for APIs and sheet rendering.
   */
  public function buildEffectState(array $character_data, array $context = []): array {
    $level = max(1, (int) ($context['level'] ?? $character_data['level'] ?? 1));
    $base_speed = (int) ($context['base_speed'] ?? $this->resolveBaseSpeed($character_data));

    $effects = [
      'derived_adjustments' => [
        'speed_bonus' => 0,
        'speed_override' => NULL,
        'initiative_bonus' => 0,
        'hp_max_bonus' => 0,
        'perception_bonus' => 0,
        'flags' => [],
      ],
      'senses' => [],
      'spell_augments' => [
        'metamagic' => [],
        'innate_spells' => [],
      ],
      'available_actions' => [
        'at_will' => [],
        'per_short_rest' => [],
        'per_long_rest' => [],
      ],
      'rest_resources' => [
        'per_short_rest' => [],
        'per_long_rest' => [],
      ],
      'applied_feats' => [],
      'notes' => [],
    ];

    foreach ($this->extractSelectedFeatIds($character_data) as $feat_id) {
      switch ($feat_id) {
        case 'toughness':
          $effects['derived_adjustments']['hp_max_bonus'] += $level;
          $effects['notes'][] = 'Toughness: +1 max HP per level.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'fleet':
          $effects['derived_adjustments']['speed_bonus'] += 5;
          $effects['notes'][] = 'Fleet: +5 status bonus to Speed.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'incredible-initiative':
          $effects['derived_adjustments']['initiative_bonus'] += 2;
          $effects['notes'][] = 'Incredible Initiative: +2 circumstance bonus to initiative.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'elven-instincts':
          $effects['derived_adjustments']['initiative_bonus'] += 1;
          $effects['notes'][] = 'Elven Instincts: +1 circumstance bonus to initiative.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'nimble-elf':
          $effects['derived_adjustments']['speed_override'] = max(35, (int) ($effects['derived_adjustments']['speed_override'] ?? 0));
          $effects['notes'][] = 'Nimble Elf: base Speed becomes at least 35 feet.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'unburdened-iron':
          $effects['derived_adjustments']['flags']['ignore_armor_speed_penalty'] = TRUE;
          $effects['notes'][] = 'Unburdened Iron: ignore armor Speed penalties.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'reactive-shield':
          $effects['available_actions']['at_will'][] = [
            'id' => 'reactive-shield',
            'name' => 'Reactive Shield',
            'action_cost' => 'reaction',
            'description' => 'Raise your shield as a reaction when needed.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'counterspell':
          $effects['available_actions']['at_will'][] = [
            'id' => 'counterspell',
            'name' => 'Counterspell',
            'action_cost' => 'reaction',
            'description' => 'Attempt to counter an enemy spell as a reaction.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'power-attack':
          $effects['available_actions']['at_will'][] = [
            'id' => 'power-attack',
            'name' => 'Power Attack',
            'action_cost' => 2,
            'description' => 'Make a heavy strike that deals extra weapon damage dice.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'reach-spell':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'reach-spell',
            'name' => 'Reach Spell',
            'description' => 'Increase spell range when applying metamagic.',
            'range_bonus_feet' => 30,
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'reach-spell',
            'name' => 'Reach Spell',
            'action_cost' => 1,
            'description' => 'Metamagic: increase range of your next spell by 30 feet.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'widen-spell':
          $effects['spell_augments']['metamagic'][] = [
            'id' => 'widen-spell',
            'name' => 'Widen Spell',
            'description' => 'Increase area of your next burst/emanation spell.',
            'area_multiplier' => 2,
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'widen-spell',
            'name' => 'Widen Spell',
            'action_cost' => 1,
            'description' => 'Metamagic: widen the area of your next spell.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'halfling-luck':
          $this->addLongRestLimitedAction(
            $effects,
            'halfling-luck',
            'Halfling Luck',
            'Reroll a failed check or save once per long rest.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'halfling-luck') ?? 0)
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'adapted-cantrip':
          $effects['spell_augments']['innate_spells'][] = [
            'id' => 'adapted-cantrip',
            'name' => 'Adapted Cantrip',
            'casting' => 'at_will',
            'description' => 'One extra innate cantrip from another tradition.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'adapted-cantrip-cast',
            'name' => 'Cast Adapted Cantrip',
            'action_cost' => 2,
            'description' => 'Cast your selected adapted innate cantrip.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'otherworldly-magic':
          $effects['spell_augments']['innate_spells'][] = [
            'id' => 'otherworldly-magic',
            'name' => 'Otherworldly Magic',
            'casting' => 'at_will',
            'description' => 'One extra innate primal cantrip.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'otherworldly-magic-cast',
            'name' => 'Cast Otherworldly Cantrip',
            'action_cost' => 2,
            'description' => 'Cast your selected otherworldly innate cantrip.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'first-world-magic':
          $effects['spell_augments']['innate_spells'][] = [
            'id' => 'first-world-magic',
            'name' => 'First World Magic',
            'casting' => 'at_will',
            'description' => 'One extra innate primal cantrip.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'first-world-magic-cast',
            'name' => 'Cast First World Cantrip',
            'action_cost' => 2,
            'description' => 'Cast your selected first world innate cantrip.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'recognize-spell':
          $effects['available_actions']['at_will'][] = [
            'id' => 'recognize-spell',
            'name' => 'Recognize Spell',
            'action_cost' => 'reaction',
            'description' => 'Attempt to identify a spell as it is being cast.',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'orc-ferocity':
          $this->addLongRestLimitedAction(
            $effects,
            'orc-ferocity',
            'Orc Ferocity',
            'When reduced to 0 HP, stay at 1 HP once per long rest.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'orc-ferocity') ?? 0)
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'feral-endurance':
          $this->addLongRestLimitedAction(
            $effects,
            'feral-endurance',
            'Feral Endurance',
            'When reduced to 0 HP, stay at 1 HP once per long rest.',
            1,
            (int) ($this->resolveFeatUsage($character_data, 'feral-endurance') ?? 0)
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'orc-sight':
          $this->addSense($effects, 'darkvision', 'Darkvision', 'See in darkness without needing light.');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'feline-eyes':
          $this->addSense($effects, 'low-light-vision', 'Low-Light Vision', 'See clearly in dim light.');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'tunnel-vision':
          $effects['derived_adjustments']['perception_bonus'] += 1;
          $effects['notes'][] = 'Tunnel Vision: +1 circumstance bonus to Perception in tunnels/corridors.';
          $effects['applied_feats'][] = $feat_id;
          break;
      }
    }

    $computed_speed = $base_speed + (int) ($effects['derived_adjustments']['speed_bonus'] ?? 0);
    $speed_override = $effects['derived_adjustments']['speed_override'];
    if (is_int($speed_override) && $speed_override > $computed_speed) {
      $computed_speed = $speed_override;
    }

    $effects['derived_adjustments']['computed_speed'] = $computed_speed;
    $effects['derived_adjustments']['base_speed'] = $base_speed;

    $effects['applied_feats'] = array_values(array_unique($effects['applied_feats']));

    return $effects;
  }

  /**
   * Add a unique sense entry.
   */
  private function addSense(array &$effects, string $id, string $name, string $description): void {
    $effects['senses'][$id] = [
      'id' => $id,
      'name' => $name,
      'description' => $description,
    ];
    $effects['senses'] = array_values($effects['senses']);
  }

  /**
   * Extract selected feat ids from multiple character data shapes.
   */
  private function extractSelectedFeatIds(array $character_data): array {
    $ids = [];

    if (!empty($character_data['feats']) && is_array($character_data['feats'])) {
      foreach ($character_data['feats'] as $feat) {
        if (is_array($feat) && !empty($feat['id'])) {
          $ids[] = (string) $feat['id'];
        }
      }
    }

    foreach (['ancestry_feat', 'class_feat', 'general_feat', 'skill_feat', 'background_skill_feat'] as $key) {
      if (!empty($character_data[$key]) && is_string($character_data[$key])) {
        $ids[] = strtolower(str_replace(' ', '-', $character_data[$key]));
      }
    }

    return array_values(array_unique(array_filter($ids)));
  }

  /**
   * Resolve base speed from available character data formats.
   */
  private function resolveBaseSpeed(array $character_data): int {
    if (!empty($character_data['ancestry']) && is_array($character_data['ancestry']) && isset($character_data['ancestry']['speed'])) {
      return (int) $character_data['ancestry']['speed'];
    }
    if (isset($character_data['speed'])) {
      return (int) $character_data['speed'];
    }
    return 25;
  }

  /**
   * Get persisted feat usage counter from character data.
   */
  private function resolveFeatUsage(array $character_data, string $feat_id): ?int {
    if (!isset($character_data['feat_resources']) || !is_array($character_data['feat_resources'])) {
      return NULL;
    }

    $resources = $character_data['feat_resources'];
    if (!isset($resources[$feat_id]) || !is_array($resources[$feat_id])) {
      return NULL;
    }

    return isset($resources[$feat_id]['used']) ? (int) $resources[$feat_id]['used'] : NULL;
  }

  /**
   * Add a long-rest-limited feat action and resource counter.
   */
  private function addLongRestLimitedAction(array &$effects, string $id, string $name, string $description, int $max_uses, int $used): void {
    $used_safe = max(0, min($max_uses, $used));
    $remaining = max(0, $max_uses - $used_safe);

    $effects['available_actions']['per_long_rest'][] = [
      'id' => $id,
      'name' => $name,
      'action_cost' => 'free',
      'description' => $description,
      'uses_remaining' => $remaining,
      'uses_max' => $max_uses,
    ];

    $effects['rest_resources']['per_long_rest'][] = [
      'id' => $id,
      'name' => $name,
      'used' => $used_safe,
      'max' => $max_uses,
      'remaining' => $remaining,
      'reset_on' => 'long_rest',
    ];
  }

}
