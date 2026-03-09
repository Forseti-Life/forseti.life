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
      'training_grants' => [
        'skills' => [],
        'lore' => [],
        'weapons' => [],
        'proficiencies' => [],
      ],
      'selection_grants' => [],
      'conditional_modifiers' => [
        'saving_throws' => [],
        'skills' => [],
        'movement' => [],
        'outcome_upgrades' => [],
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
      'todo_review_features' => [],
      'applied_feats' => [],
      'notes' => [],
    ];

    foreach ($this->extractSelectedFeatIds($character_data) as $feat_id) {
      $selection = $this->selectFeatureProcessingMode($feat_id, $character_data);
      if (($selection['mode'] ?? '') === 'todo_review') {
        $this->addTodoReviewFeature($effects, $feat_id, (string) ($selection['reason'] ?? 'todo-marker'));
        continue;
      }

      if ($this->applyBulkFirstPassFeat($effects, $feat_id, $character_data)) {
        $effects['applied_feats'][] = $feat_id;
        continue;
      }

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

        case 'rock-runner':
          $effects['derived_adjustments']['flags']['ignore_difficult_terrain_rubble_stone'] = TRUE;
          $this->addConditionalSkillModifier($effects, 'Acrobatics', 2, 'Balance on stone/earth surfaces');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'forest-step':
          $effects['derived_adjustments']['flags']['ignore_difficult_terrain_natural_undergrowth'] = TRUE;
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'graceful-step':
          $this->addConditionalSkillModifier($effects, 'Acrobatics', 2, 'Balance and Tumble Through');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'sure-feet':
          $effects['conditional_modifiers']['outcome_upgrades'][] = [
            'id' => 'sure-feet',
            'target' => 'Acrobatics:Balance',
            'from' => 'critical_failure',
            'to' => 'success',
            'context' => 'narrow or uneven surfaces',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'unfettered-halfling':
          $this->addConditionalSkillModifier($effects, 'Escape', 2, 'Escape checks');
          $effects['conditional_modifiers']['outcome_upgrades'][] = [
            'id' => 'unfettered-halfling',
            'target' => 'Escape',
            'from' => 'success',
            'to' => 'critical_success',
            'context' => 'all escape attempts',
          ];
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
          $selected_cantrip = $this->resolveFeatSelectionValue($character_data, 'adapted-cantrip', ['selected_cantrip', 'cantrip', 'spell_id']);
          $selected_tradition = $this->resolveFeatSelectionValue($character_data, 'adapted-cantrip', ['selected_tradition', 'tradition']);

          if ($selected_cantrip === NULL) {
            $this->addSelectionGrant(
              $effects,
              'adapted-cantrip',
              'adapted_cantrip_choice',
              1,
              'Select one cantrip from a non-native magical tradition for Adapted Cantrip.'
            );
          }

          $effects['spell_augments']['innate_spells'][] = [
            'id' => 'adapted-cantrip',
            'name' => 'Adapted Cantrip',
            'casting' => 'at_will',
            'tradition' => $selected_tradition,
            'spell_id' => $selected_cantrip,
            'description' => $selected_cantrip
              ? ('Innate cantrip: ' . $selected_cantrip . ($selected_tradition ? (' (' . $selected_tradition . ')') : '') . '.')
              : 'One extra innate cantrip from another tradition.',
          ];
          $effects['available_actions']['at_will'][] = [
            'id' => 'adapted-cantrip-cast',
            'name' => 'Cast Adapted Cantrip',
            'action_cost' => 2,
            'description' => $selected_cantrip
              ? ('Cast adapted cantrip: ' . $selected_cantrip . '.')
              : 'Cast your selected adapted innate cantrip.',
          ];
          $effects['notes'][] = $selected_cantrip
            ? ('Adapted Cantrip selected: ' . $selected_cantrip . ($selected_tradition ? (' (' . $selected_tradition . ')') : '') . '.')
            : 'Adapted Cantrip pending cantrip selection.';
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'ancestral-longevity':
          $selected_skills = array_slice(
            $this->resolveFeatSelectionList($character_data, 'ancestral-longevity', ['selected_skills', 'skills', 'trained_skills']),
            0,
            2
          );

          foreach ($selected_skills as $skill_name) {
            $this->addSkillTraining($effects, $skill_name);
          }

          $remaining_choices = max(0, 2 - count($selected_skills));
          if ($remaining_choices > 0) {
            $this->addSelectionGrant(
              $effects,
              'ancestral-longevity',
              'ancestral_longevity_skill_choices',
              $remaining_choices,
              'Select two skills to gain trained proficiency until your next daily preparations.'
            );
          }

          $effects['notes'][] = !empty($selected_skills)
            ? ('Ancestral Longevity: trained in ' . implode(', ', $selected_skills) . ' until next daily preparations.')
            : 'Ancestral Longevity: select two skills to gain trained proficiency until next daily preparations.';
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

        case 'haughty-obstinacy':
          $this->addConditionalSaveModifier($effects, 'Will', 1, 'mental effects');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'unyielding-will':
          $this->addConditionalSaveModifier($effects, 'Will', 1, 'fear effects');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'scar-thickened':
          $this->addConditionalSaveModifier($effects, 'Fortitude', 1, 'bleed and poison effects');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'forlorn':
          $this->addConditionalSaveModifier($effects, 'All', 1, 'emotion effects');
          $effects['conditional_modifiers']['outcome_upgrades'][] = [
            'id' => 'forlorn',
            'target' => 'saving_throw',
            'from' => 'success',
            'to' => 'critical_success',
            'context' => 'emotion effects',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'unwavering-mien':
          $effects['conditional_modifiers']['outcome_upgrades'][] = [
            'id' => 'unwavering-mien',
            'target' => 'saving_throw',
            'from' => 'success',
            'to' => 'critical_success',
            'context' => 'mental effects',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'distracting-shadows':
          $effects['conditional_modifiers']['movement'][] = [
            'id' => 'distracting-shadows',
            'rule' => 'can_use_larger_creatures_as_cover',
            'context' => 'Hide and Sneak',
          ];
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'general-training':
          $this->addSelectionGrant(
            $effects,
            'general-training',
            'bonus_general_feat',
            1,
            'Select one additional 1st-level general feat.'
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'natural-ambition':
          $this->addSelectionGrant(
            $effects,
            'natural-ambition',
            'bonus_class_feat',
            1,
            'Select one additional 1st-level class feat.'
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'natural-skill':
          $this->addSelectionGrant(
            $effects,
            'natural-skill',
            'bonus_skill_training',
            2,
            'Select two additional trained skills.'
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'adopted-ancestry':
          $this->addSelectionGrant(
            $effects,
            'adopted-ancestry',
            'adopted_ancestry_choice',
            1,
            'Select an ancestry to access adopted-ancestry feat options.'
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'canny-acumen':
          $this->addSelectionGrant(
            $effects,
            'canny-acumen',
            'proficiency_upgrade_choice',
            1,
            'Select Perception or one save to improve proficiency.'
          );
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'weapon-proficiency':
          $this->addProficiencyGrant($effects, 'weapon', 'martial_or_advanced_choice', 'trained');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'armor-proficiency':
          $this->addProficiencyGrant($effects, 'armor', 'light_or_medium_or_heavy_choice', 'trained');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'dwarven-lore':
          $this->addSkillTraining($effects, 'Crafting');
          $this->addSkillTraining($effects, 'Religion');
          $this->addLoreTraining($effects, 'Crafting Lore');
          $this->addLoreTraining($effects, 'Dwarven Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'elven-lore':
          $this->addSkillTraining($effects, 'Arcana');
          $this->addSkillTraining($effects, 'Nature');
          $this->addLoreTraining($effects, 'Elven Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'goblin-lore':
          $this->addSkillTraining($effects, 'Nature');
          $this->addSkillTraining($effects, 'Stealth');
          $this->addLoreTraining($effects, 'Goblin Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'halfling-lore':
          $this->addSkillTraining($effects, 'Acrobatics');
          $this->addSkillTraining($effects, 'Stealth');
          $this->addLoreTraining($effects, 'Halfling Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'catfolk-lore':
          $this->addSkillTraining($effects, 'Acrobatics');
          $this->addSkillTraining($effects, 'Stealth');
          $this->addLoreTraining($effects, 'Catfolk Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'kobold-lore':
          $this->addSkillTraining($effects, 'Crafting');
          $this->addSkillTraining($effects, 'Stealth');
          $this->addLoreTraining($effects, 'Kobold Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'leshy-lore':
          $this->addSkillTraining($effects, 'Nature');
          $this->addSkillTraining($effects, 'Diplomacy');
          $this->addLoreTraining($effects, 'Leshy Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'ratfolk-lore':
          $this->addSkillTraining($effects, 'Society');
          $this->addSkillTraining($effects, 'Thievery');
          $this->addLoreTraining($effects, 'Ratfolk Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'tengu-lore':
          $this->addSkillTraining($effects, 'Acrobatics');
          $this->addSkillTraining($effects, 'Deception');
          $this->addLoreTraining($effects, 'Tengu Lore');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'dwarven-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Dwarven Weapons', ['battle axe', 'pick', 'warhammer']);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'elven-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Elven Weapons', ['longbow', 'composite longbow', 'longsword', 'rapier', 'shortbow', 'composite shortbow']);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'gnome-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Gnome Weapons', ['glaive', 'kukri']);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'goblin-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Goblin Weapons', ['dogslicer', 'horsechopper']);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'halfling-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Halfling Weapons', ['sling', 'halfling sling staff']);
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'catfolk-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Catfolk Weapons');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'kobold-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Kobold Weapons');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'ratfolk-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Ratfolk Weapons');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'tengu-weapon-familiarity':
          $this->addWeaponFamiliarity($effects, 'Tengu Weapons');
          $effects['applied_feats'][] = $feat_id;
          break;

        case 'orc-weapon-familiarity':
        case 'orc-weapon-familiarity-half-orc':
          $this->addWeaponFamiliarity($effects, 'Orc Weapons');
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

        default:
          // Stub path for features without an implementation handler yet.
          $this->addTodoReviewFeature($effects, $feat_id, 'missing-handler-stub');
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

  /**
   * Add a trained skill grant.
   */
  private function addSkillTraining(array &$effects, string $skill_name): void {
    if (!in_array($skill_name, $effects['training_grants']['skills'], TRUE)) {
      $effects['training_grants']['skills'][] = $skill_name;
    }
  }

  /**
   * Add a lore skill grant.
   */
  private function addLoreTraining(array &$effects, string $lore_name): void {
    if (!in_array($lore_name, $effects['training_grants']['lore'], TRUE)) {
      $effects['training_grants']['lore'][] = $lore_name;
    }
  }

  /**
   * Add a weapon familiarity grant.
   */
  private function addWeaponFamiliarity(array &$effects, string $group_name, array $examples = []): void {
    foreach ($effects['training_grants']['weapons'] as $existing) {
      if (($existing['group'] ?? '') === $group_name) {
        return;
      }
    }

    $effects['training_grants']['weapons'][] = [
      'group' => $group_name,
      'proficiency' => 'trained',
      'examples' => $examples,
    ];
  }

  /**
   * Add a generic proficiency grant.
   */
  private function addProficiencyGrant(array &$effects, string $category, string $target, string $rank): void {
    foreach ($effects['training_grants']['proficiencies'] as $existing) {
      if (($existing['category'] ?? '') === $category && ($existing['target'] ?? '') === $target) {
        return;
      }
    }
    $effects['training_grants']['proficiencies'][] = [
      'category' => $category,
      'target' => $target,
      'rank' => $rank,
    ];
  }

  /**
   * Add a selection-slot grant for feats requiring player choice.
   */
  private function addSelectionGrant(array &$effects, string $source_feat, string $selection_type, int $count, string $description): void {
    foreach ($effects['selection_grants'] as $existing) {
      if (($existing['source_feat'] ?? '') === $source_feat && ($existing['selection_type'] ?? '') === $selection_type) {
        return;
      }
    }
    $effects['selection_grants'][] = [
      'source_feat' => $source_feat,
      'selection_type' => $selection_type,
      'count' => $count,
      'status' => 'pending_choice',
      'description' => $description,
    ];
  }

  /**
   * Add conditional saving throw modifier.
   */
  private function addConditionalSaveModifier(array &$effects, string $save, int $bonus, string $context): void {
    $effects['conditional_modifiers']['saving_throws'][] = [
      'save' => $save,
      'bonus' => $bonus,
      'context' => $context,
      'type' => 'circumstance',
    ];
  }

  /**
   * Add conditional skill modifier.
   */
  private function addConditionalSkillModifier(array &$effects, string $skill, int $bonus, string $context): void {
    $effects['conditional_modifiers']['skills'][] = [
      'skill' => $skill,
      'bonus' => $bonus,
      'context' => $context,
      'type' => 'circumstance',
    ];
  }

  /**
   * Stub selector for feature processing strategy.
   *
   * Features tagged with TODO metadata are routed to review queue.
   */
  private function selectFeatureProcessingMode(string $feat_id, array $character_data): array {
    $meta = $this->findSelectedFeatMeta($feat_id, $character_data);
    $markers = [
      $feat_id,
      (string) ($meta['name'] ?? ''),
      (string) ($meta['status'] ?? ''),
      (string) ($meta['implementation'] ?? ''),
      (string) ($meta['review'] ?? ''),
      (string) ($meta['note'] ?? ''),
    ];

    foreach ($markers as $value) {
      if ($value !== '' && stripos($value, 'todo') !== FALSE) {
        return [
          'mode' => 'todo_review',
          'reason' => 'todo-marker',
        ];
      }
    }

    return [
      'mode' => 'apply',
      'reason' => 'standard',
    ];
  }

  /**
   * Locate selected feat metadata from character payload.
   */
  private function findSelectedFeatMeta(string $feat_id, array $character_data): array {
    if (!empty($character_data['feats']) && is_array($character_data['feats'])) {
      foreach ($character_data['feats'] as $feat) {
        if (is_array($feat) && (($feat['id'] ?? '') === $feat_id)) {
          return $feat;
        }
      }
    }
    return [];
  }

  /**
   * Resolve a feat selection value from multiple character-data shapes.
   */
  private function resolveFeatSelectionValue(array $character_data, string $feat_id, array $candidate_keys): ?string {
    $meta = $this->findSelectedFeatMeta($feat_id, $character_data);
    foreach ($candidate_keys as $key) {
      if (isset($meta[$key]) && is_string($meta[$key]) && trim($meta[$key]) !== '') {
        return trim($meta[$key]);
      }
    }

    if (isset($character_data['feat_selections']) && is_array($character_data['feat_selections'])) {
      $selection_entry = $character_data['feat_selections'][$feat_id] ?? NULL;
      if (is_array($selection_entry)) {
        foreach ($candidate_keys as $key) {
          if (isset($selection_entry[$key]) && is_string($selection_entry[$key]) && trim($selection_entry[$key]) !== '') {
            return trim($selection_entry[$key]);
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Resolve multi-select feat values from character-data shapes.
   *
   * @return array<int,string>
   */
  private function resolveFeatSelectionList(array $character_data, string $feat_id, array $candidate_keys): array {
    $candidates = [];

    $meta = $this->findSelectedFeatMeta($feat_id, $character_data);
    foreach ($candidate_keys as $key) {
      if (!isset($meta[$key])) {
        continue;
      }

      $value = $meta[$key];
      if (is_string($value) && trim($value) !== '') {
        $candidates = array_merge($candidates, preg_split('/\s*,\s*/', trim($value)) ?: []);
      }
      elseif (is_array($value)) {
        foreach ($value as $entry) {
          if (is_string($entry) && trim($entry) !== '') {
            $candidates[] = trim($entry);
          }
        }
      }
    }

    if (isset($character_data['feat_selections']) && is_array($character_data['feat_selections'])) {
      $selection_entry = $character_data['feat_selections'][$feat_id] ?? NULL;
      if (is_array($selection_entry)) {
        foreach ($candidate_keys as $key) {
          if (!isset($selection_entry[$key])) {
            continue;
          }

          $value = $selection_entry[$key];
          if (is_string($value) && trim($value) !== '') {
            $candidates = array_merge($candidates, preg_split('/\s*,\s*/', trim($value)) ?: []);
          }
          elseif (is_array($value)) {
            foreach ($value as $entry) {
              if (is_string($entry) && trim($entry) !== '') {
                $candidates[] = trim($entry);
              }
            }
          }
        }
      }
    }

    $result = [];
    foreach ($candidates as $entry) {
      $normalized = trim((string) $entry);
      if ($normalized === '' || in_array($normalized, $result, TRUE)) {
        continue;
      }
      $result[] = $normalized;
    }

    return $result;
  }

  /**
   * Add a feat to explicit TODO review list.
   */
  private function addTodoReviewFeature(array &$effects, string $feat_id, string $reason): void {
    foreach ($effects['todo_review_features'] as $existing) {
      if (($existing['id'] ?? '') === $feat_id) {
        return;
      }
    }

    $effects['todo_review_features'][] = [
      'id' => $feat_id,
      'status' => 'Todo',
      'reason' => $reason,
    ];
  }

  /**
   * Apply bulk first-pass effects for the current tranche.
   */
  private function applyBulkFirstPassFeat(array &$effects, string $feat_id, array $character_data): bool {
    $wave_ids = $this->getBulkFirstPassWaveIds();
    if (!isset($wave_ids[$feat_id])) {
      return FALSE;
    }

    $label = $this->humanizeFeatId($feat_id);
    $applied_any = FALSE;

    $selection_grants = [
      'cross-cultural-upbringing' => ['cross_cultural_adopted_ancestry', 1, 'Select an alternate ancestry cultural training package.'],
      'elf-atavism' => ['ancestry_lineage_choice', 1, 'Select an alternate lineage trait expression.'],
      'mixed-heritage-adaptability' => ['mixed_heritage_adaptability_choice', 1, 'Select one mixed-heritage adaptability option.'],
      'multitalented' => ['multiclass_archetype_dedication', 1, 'Select a multiclass dedication feat.'],
      'orc-atavism' => ['ancestry_lineage_choice', 1, 'Select an alternate lineage trait expression.'],
      'unconventional-weaponry' => ['unconventional_weapon_choice', 1, 'Select one uncommon weapon for familiarity benefits.'],
      'multilingual' => ['additional_languages', 2, 'Select additional known languages.'],
      'specialty-crafting' => ['specialty_crafting_choice', 1, 'Select a crafting specialty.'],
      'terrain-expertise' => ['terrain_expertise_choice', 1, 'Select one terrain type for expertise benefits.'],
      'trick-magic-item' => ['trick_magic_item_tradition_choice', 1, 'Select a magical tradition to improvise item activation.'],
      'virtuosic-performer' => ['performance_specialty_choice', 1, 'Select a favored performance specialty.'],
    ];
    if (isset($selection_grants[$feat_id])) {
      [$selection_type, $count, $description] = $selection_grants[$feat_id];
      $this->addSelectionGrant($effects, $feat_id, $selection_type, $count, $description);
      $applied_any = TRUE;
    }

    $skill_mods = [
      'assurance' => 'Any Skill',
      'bargain-hunter' => 'Diplomacy',
      'cat-fall' => 'Acrobatics',
      'charming-liar' => 'Deception',
      'combat-climber' => 'Athletics',
      'courtly-graces' => 'Society',
      'experienced-smuggler' => 'Stealth',
      'experienced-tracker' => 'Survival',
      'fascinating-performance' => 'Performance',
      'forager' => 'Survival',
      'group-impression' => 'Diplomacy',
      'hefty-hauler' => 'Athletics',
      'hobnobber' => 'Diplomacy',
      'intimidating-glare' => 'Intimidation',
      'lengthy-diversion' => 'Deception',
      'lie-to-me' => 'Perception',
      'natural-medicine' => 'Medicine',
      'oddity-identification' => 'Occultism',
      'pickpocket' => 'Thievery',
      'quick-identification' => 'Arcana',
      'quick-jump' => 'Athletics',
      'rapid-mantel' => 'Athletics',
      'read-lips' => 'Perception',
      'sign-language' => 'Society',
      'snare-crafting' => 'Crafting',
      'specialty-crafting' => 'Crafting',
      'steady-balance' => 'Acrobatics',
      'streetwise' => 'Society',
      'student-of-the-canon' => 'Religion',
      'subtle-theft' => 'Thievery',
      'survey-wildlife' => 'Nature',
      'terrain-expertise' => 'Survival',
      'titan-wrestler' => 'Athletics',
      'train-animal' => 'Nature',
      'trick-magic-item' => 'Arcana',
      'virtuosic-performer' => 'Performance',
    ];
    if (isset($skill_mods[$feat_id])) {
      $this->addConditionalSkillModifier($effects, $skill_mods[$feat_id], 1, $label . ' first-pass baseline');
      $applied_any = TRUE;
    }

    $at_will_actions = [
      'animal-accomplice',
      'beak-adept',
      'burn-it',
      'burrow-elocutionist',
      'cheek-pouches',
      'city-scavenger',
      'draconic-ties',
      'fey-fellowship',
      'gnome-obsession',
      'goblin-scuttle',
      'goblin-song',
      'illusion-sense',
      'junk-tinker',
      'one-toed-hop',
      'orc-weapon-carnage',
      'scrounger',
      'seedpod',
      'sky-bridge-runner',
      'snare-setter',
      'squawk',
      'titan-slinger',
      'tunnel-runner',
      'verdant-voice',
      'well-groomed',
      'crossbow-ace',
      'double-slice',
      'eschew-materials',
      'exacting-strike',
      'familiar',
      'hand-of-the-apprentice',
      'hunted-shot',
      'monster-hunter',
      'point-blank-shot',
      'snagging-strike',
      'trap-finder',
      'twin-feint',
      'twin-takedown',
      'bargain-hunter',
      'forager',
      'group-impression',
      'hobnobber',
      'quick-identification',
      'snare-crafting',
      'student-of-the-canon',
      'survey-wildlife',
      'train-animal',
      'trick-magic-item',
      'virtuosic-performer',
    ];
    $reaction_actions = [
      'nimble-dodge',
      'you-re-next',
      'intimidating-glare-half-orc',
    ];
    if (in_array($feat_id, $at_will_actions, TRUE) || in_array($feat_id, $reaction_actions, TRUE)) {
      $action_cost = in_array($feat_id, $reaction_actions, TRUE) ? 'reaction' : 1;
      $effects['available_actions']['at_will'][] = [
        'id' => $feat_id,
        'name' => $label,
        'action_cost' => $action_cost,
        'description' => $label . ': first-pass feat action.',
      ];
      $applied_any = TRUE;
    }

    $long_rest_feats = [
      'cat-nap',
      'draconic-scout',
      'hold-scarred',
      'photosynthetic-recovery',
      'breath-control',
      'diehard',
      'fast-recovery',
    ];
    if (in_array($feat_id, $long_rest_feats, TRUE)) {
      $this->addLongRestLimitedAction(
        $effects,
        $feat_id,
        $label,
        $label . ': first-pass long-rest resource.',
        1,
        (int) ($this->resolveFeatUsage($character_data, $feat_id) ?? 0)
      );
      $applied_any = TRUE;
    }

    $save_mods = [
      'communal-instinct' => ['Will', 1, 'allies within 30 feet'],
      'cooperative-nature' => ['All', 1, 'when taking cooperative actions'],
      'forlorn-half-elf' => ['Will', 1, 'emotion effects'],
      'orc-superstition' => ['Will', 1, 'spells and magical effects'],
      'vengeful-hatred' => ['Will', 1, 'against chosen hated foe'],
      'ride' => ['Reflex', 1, 'while mounted'],
    ];
    if (isset($save_mods[$feat_id])) {
      [$save, $bonus, $context] = $save_mods[$feat_id];
      $this->addConditionalSaveModifier($effects, $save, $bonus, $context);
      $applied_any = TRUE;
    }

    if ($feat_id === 'draconic-scout') {
      $this->addSense($effects, 'low-light-vision', 'Low-Light Vision', 'First-pass draconic scout vision boost.');
      $applied_any = TRUE;
    }
    if ($feat_id === 'stonecunning') {
      $effects['derived_adjustments']['perception_bonus'] += 1;
      $effects['notes'][] = 'Stonecunning: +1 first-pass perception bonus for stonework and underground clues.';
      $applied_any = TRUE;
    }
    if ($feat_id === 'feather-step') {
      $effects['derived_adjustments']['flags']['ignore_difficult_terrain_light'] = TRUE;
      $applied_any = TRUE;
    }
    if ($feat_id === 'shield-block') {
      $effects['available_actions']['at_will'][] = [
        'id' => 'shield-block',
        'name' => 'Shield Block',
        'action_cost' => 'reaction',
        'description' => 'Block incoming damage with a shield.',
      ];
      $applied_any = TRUE;
    }
    if ($feat_id === 'animal-companion') {
      $this->addSelectionGrant($effects, 'animal-companion', 'animal_companion_choice', 1, 'Select one animal companion.');
      $applied_any = TRUE;
    }
    if ($feat_id === 'titan-wrestler') {
      $effects['conditional_modifiers']['movement'][] = [
        'id' => 'titan-wrestler',
        'rule' => 'can_grapple_larger_creatures',
        'context' => 'Athletics Grapple and Shove against larger targets',
      ];
      $applied_any = TRUE;
    }
    if ($feat_id === 'underwater-marauder') {
      $effects['conditional_modifiers']['movement'][] = [
        'id' => 'underwater-marauder',
        'rule' => 'reduced_underwater_attack_penalty',
        'context' => 'Underwater combat and movement',
      ];
      $applied_any = TRUE;
    }

    if (!$applied_any) {
      $effects['conditional_modifiers']['movement'][] = [
        'id' => $feat_id,
        'rule' => 'first_pass_baseline',
        'context' => $label,
      ];
    }

    $effects['notes'][] = $label . ': first-pass implementation applied.';
    return TRUE;
  }

  /**
   * IDs for the current bulk first-pass tranche (next 100 unchecked feats).
   *
   * @return array<string,bool>
   */
  private function getBulkFirstPassWaveIds(): array {
    static $ids = NULL;
    if ($ids !== NULL) {
      return $ids;
    }

    $list = [
      'animal-accomplice',
      'beak-adept',
      'burn-it',
      'burrow-elocutionist',
      'cat-nap',
      'cheek-pouches',
      'city-scavenger',
      'communal-instinct',
      'cooperative-nature',
      'cross-cultural-upbringing',
      'draconic-scout',
      'draconic-ties',
      'elf-atavism',
      'fey-fellowship',
      'forest-step',
      'forlorn-half-elf',
      'gnome-obsession',
      'goblin-scuttle',
      'goblin-song',
      'hold-scarred',
      'illusion-sense',
      'intimidating-glare-half-orc',
      'junk-tinker',
      'mixed-heritage-adaptability',
      'multitalented',
      'one-toed-hop',
      'orc-atavism',
      'orc-superstition',
      'orc-weapon-carnage',
      'photosynthetic-recovery',
      'rooted-resilience',
      'scrounger',
      'seedpod',
      'sky-bridge-runner',
      'snare-setter',
      'squawk',
      'stonecunning',
      'titan-slinger',
      'tunnel-runner',
      'tunnel-vision',
      'unconventional-weaponry',
      'vengeful-hatred',
      'verdant-voice',
      'well-groomed',
      'animal-companion',
      'crossbow-ace',
      'double-slice',
      'eschew-materials',
      'exacting-strike',
      'familiar',
      'hand-of-the-apprentice',
      'hunted-shot',
      'monster-hunter',
      'nimble-dodge',
      'point-blank-shot',
      'snagging-strike',
      'trap-finder',
      'twin-feint',
      'twin-takedown',
      'you-re-next',
      'breath-control',
      'diehard',
      'fast-recovery',
      'feather-step',
      'ride',
      'shield-block',
      'assurance',
      'bargain-hunter',
      'cat-fall',
      'charming-liar',
      'combat-climber',
      'courtly-graces',
      'experienced-smuggler',
      'experienced-tracker',
      'fascinating-performance',
      'forager',
      'group-impression',
      'hefty-hauler',
      'hobnobber',
      'intimidating-glare',
      'lengthy-diversion',
      'lie-to-me',
      'multilingual',
      'natural-medicine',
      'oddity-identification',
      'pickpocket',
      'quick-identification',
      'quick-jump',
      'rapid-mantel',
      'read-lips',
      'sign-language',
      'snare-crafting',
      'specialty-crafting',
      'steady-balance',
      'streetwise',
      'student-of-the-canon',
      'subtle-theft',
      'survey-wildlife',
      'terrain-expertise',
      'titan-wrestler',
      'train-animal',
      'trick-magic-item',
      'underwater-marauder',
      'virtuosic-performer',
    ];

    $ids = [];
    foreach ($list as $id) {
      $ids[$id] = TRUE;
    }
    return $ids;
  }

  /**
   * Convert feat id slug into human-readable title.
   */
  private function humanizeFeatId(string $feat_id): string {
    $parts = explode('-', $feat_id);
    $parts = array_map(function (string $part): string {
      return ucfirst($part);
    }, $parts);
    return implode(' ', $parts);
  }

}
