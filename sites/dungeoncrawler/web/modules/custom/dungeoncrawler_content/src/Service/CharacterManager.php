<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\InventoryManagementService;

/**
 * Manages PF2e character CRUD operations and JSON storage.
 */
class CharacterManager {

  protected Connection $database;
  protected AccountProxyInterface $currentUser;
  protected UuidInterface $uuid;
  protected ?InventoryManagementService $inventoryManagement = NULL;

  /**
   * PF2e ancestries with base stats.
   */
  const ANCESTRIES = [
    'Human' => [
      'hp' => 8,
      'size' => 'Medium',
      'speed' => 25,
      'boosts' => ['Free', 'Free'],
      'languages' => ['Common'],
      'traits' => ['Human', 'Humanoid'],
      'vision' => 'normal',
      // Human-specific bonuses: +1 trained skill, +1 skill feat, and one
      // additional language slot for every positive Int modifier point.
      'special' => [
        'extra_trained_skill'       => 1,
        'extra_skill_feat'          => 1,
        'bonus_language_per_int'    => 1,
      ],
    ],
    'Elf' => ['hp' => 6, 'size' => 'Medium', 'speed' => 30, 'boosts' => ['Dexterity', 'Intelligence'], 'flaw' => 'Constitution', 'languages' => ['Common', 'Elven'], 'traits' => ['Elf', 'Humanoid'], 'vision' => 'low-light vision'],
    'Dwarf' => ['hp' => 10, 'size' => 'Medium', 'speed' => 20, 'boosts' => ['Constitution', 'Wisdom'], 'flaw' => 'Charisma', 'languages' => ['Common', 'Dwarven'], 'traits' => ['Dwarf', 'Humanoid'], 'vision' => 'darkvision'],
    'Gnome' => ['hp' => 8, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Constitution', 'Charisma'], 'flaw' => 'Strength', 'languages' => ['Common', 'Gnomish', 'Sylvan'], 'traits' => ['Gnome', 'Humanoid'], 'vision' => 'low-light vision'],
    'Goblin' => ['hp' => 6, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Dexterity', 'Charisma'], 'flaw' => 'Wisdom', 'languages' => ['Common', 'Goblin'], 'traits' => ['Goblin', 'Humanoid'], 'vision' => 'darkvision'],
    'Halfling' => ['hp' => 6, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Dexterity', 'Wisdom'], 'flaw' => 'Strength', 'languages' => ['Common', 'Halfling'], 'traits' => ['Halfling', 'Humanoid'], 'vision' => 'normal'],
    'Half-Elf' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Free', 'Free'], 'languages' => ['Common', 'Elven'], 'traits' => ['Human', 'Elf', 'Humanoid', 'Half-Elf'], 'vision' => 'low-light vision'],
    'Half-Orc' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Free', 'Free'], 'languages' => ['Common', 'Orcish'], 'traits' => ['Human', 'Orc', 'Humanoid', 'Half-Orc'], 'vision' => 'low-light vision'],
    'Leshy' => ['hp' => 8, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Constitution', 'Wisdom'], 'flaw' => 'Intelligence', 'languages' => ['Common', 'Sylvan'], 'traits' => ['Leshy', 'Plant', 'Humanoid'], 'vision' => 'low-light vision'],
    'Orc' => [
      'hp' => 10, 'size' => 'Medium', 'speed' => 25,
      'boosts' => ['Strength', 'Free'],
      'languages' => ['Common', 'Orcish'],
      'traits' => ['Orc', 'Humanoid'],
      'vision' => 'darkvision',
      // Orc has no ability flaw (APG distinction).
    ],
    'Catfolk' => [
      'hp' => 8, 'size' => 'Medium', 'speed' => 25,
      'boosts' => ['Dexterity', 'Charisma'], 'flaw' => 'Wisdom',
      'languages' => ['Common', 'Amurrun'],
      'traits' => ['Catfolk', 'Humanoid'],
      'vision' => 'low-light vision',
      'special' => [
        // Halve falling damage and do not land Prone from any fall.
        'land_on_your_feet' => TRUE,
      ],
    ],
    'Kobold' => [
      'hp' => 6, 'size' => 'Small', 'speed' => 25,
      'boosts' => ['Dexterity', 'Charisma'], 'flaw' => 'Constitution',
      'languages' => ['Common', 'Draconic'],
      'traits' => ['Kobold', 'Humanoid'],
      'vision' => 'darkvision',
      'special' => [
        // Player selects one entry from KOBOLD_DRACONIC_EXEMPLAR_TABLE at L1.
        'draconic_exemplar' => TRUE,
      ],
    ],
    'Ratfolk' => [
      'hp' => 6, 'size' => 'Small', 'speed' => 25,
      'boosts' => ['Dexterity', 'Intelligence'], 'flaw' => 'Strength',
      'languages' => ['Common', 'Ysoki'],
      'traits' => ['Ratfolk', 'Humanoid'],
      'vision' => 'low-light vision',
    ],
    'Tengu' => [
      'hp' => 6, 'size' => 'Medium', 'speed' => 25,
      'boosts' => ['Dexterity', 'Free'],
      'languages' => ['Common', 'Tengu'],
      'traits' => ['Tengu', 'Humanoid'],
      'vision' => 'low-light vision',
      'special' => [
        // All tengus have this unarmed attack from birth (not heritage-gated).
        'sharp_beak' => [
          'damage' => '1d6', 'type' => 'piercing',
          'group' => 'brawling',
          'traits' => ['finesse', 'unarmed'],
        ],
      ],
    ],
  ];

  /**
   * Canonical creature trait catalog — all valid trait strings.
   *
   * Derived from the union of all ANCESTRIES['traits'] arrays.
   * Trait comparison is case-sensitive; only strings in this list are valid.
   */
  const TRAIT_CATALOG = [
    'Aasimar',
    'Catfolk',
    'Changeling',
    'Dhampir',
    'Duskwalker',
    'Dwarf',
    'Elf',
    'Gnome',
    'Goblin',
    'Half-Elf',
    'Half-Orc',
    'Halfling',
    'Human',
    'Humanoid',
    'Kobold',
    'Leshy',
    'Orc',
    'Plant',
    'Ratfolk',
    'Tengu',
    'Tiefling',
  ];

  /**
   * Resolves an ancestry machine ID (e.g. "half-elf") to its canonical name.
   *
   * Returns '' if the machine ID does not match any known ancestry.
   */
  public static function resolveAncestryCanonicalName(string $machine_id): string {
    if ($machine_id === '') {
      return '';
    }
    foreach (array_keys(self::ANCESTRIES) as $canonical) {
      if (strtolower(str_replace(' ', '-', $canonical)) === strtolower($machine_id)) {
        return $canonical;
      }
    }
    return '';
  }

  /**
   * Returns the creature traits for the given ancestry machine ID.
   *
   * @param string $ancestry_machine_id
   *   E.g. "half-elf", "dwarf".
   *
   * @return string[]
   *   Trait strings from ANCESTRIES, or [] if ancestry not found.
   */
  public static function getAncestryTraits(string $ancestry_machine_id): array {
    $canonical = self::resolveAncestryCanonicalName($ancestry_machine_id);
    if ($canonical === '') {
      return [];
    }
    return self::ANCESTRIES[$canonical]['traits'] ?? [];
  }

  /**
   * Checks whether all required traits are present in the character's trait set.
   *
   * Comparison is case-sensitive (canonical strings only).
   *
   * @param string[] $character_traits
   *   The character's current traits array.
   * @param string[] $required_traits
   *   The traits to check for.
   *
   * @return bool
   *   TRUE if all required traits are present, FALSE otherwise.
   */
  public static function hasTraits(array $character_traits, array $required_traits): bool {
    foreach ($required_traits as $trait) {
      if (!in_array($trait, $character_traits, TRUE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Validates a trait string against the canonical catalog.
   *
   * @param string $trait
   *   The trait string to validate.
   *
   * @return bool
   *   TRUE if the trait is in TRAIT_CATALOG (case-sensitive).
   */
  public static function isValidTrait(string $trait): bool {
    return in_array($trait, self::TRAIT_CATALOG, TRUE);
  }

  /**
   * Merges new traits into an existing trait set idempotently.
   *
   * @param string[] $existing
   *   Existing trait strings.
   * @param string[] $new_traits
   *   Traits to add.
   *
   * @return string[]
   *   Merged trait array with duplicates removed (values reindexed).
   */
  public static function mergeTraits(array $existing, array $new_traits): array {
    return array_values(array_unique(array_merge($existing, $new_traits)));
  }

  /**
   * Step 2 Option Selection Tree Reference (Ancestry → Heritage IDs → Feat IDs).
   *
   * Purpose:
   * - Single in-code reference for refactoring Step 2 option wiring.
   * - Keep branch expectations visible near source-of-truth constants.
   * - Validate parity when adding/removing ancestries, heritages, or feats.
   *
   * Branch summary:
   * - Total ancestries: 14
   * - Heritage model: ancestry-dependent select (usually 4 options, Human=1)
   * - Feat model: ancestry-dependent radios (6-7 options per ancestry)
   *
   * Tree:
   * - Catfolk (heritages: 4, feats: 6)
   *   H: clawed, hunting, jungle, nine-lives
   *   F: catfolk-lore, catfolk-weapon-familiarity, graceful-step, feline-eyes, well-groomed, cat-nap
   * - Dwarf (heritages: 4, feats: 6)
   *   H: ancient-blooded, forge, rock, strong-blooded
   *   F: dwarven-lore, dwarven-weapon-familiarity, rock-runner, stonecunning, unburdened-iron, vengeful-hatred
   * - Elf (heritages: 4, feats: 7)
   *   H: arctic, cavern, seer, woodland
   *   F: ancestral-longevity, elven-lore, elven-weapon-familiarity, forlorn, nimble-elf, otherworldly-magic, unwavering-mien
   * - Gnome (heritages: 4, feats: 7)
   *   H: chameleon, fey-touched, sensate, umbral
   *   F: animal-accomplice, burrow-elocutionist, fey-fellowship, first-world-magic, gnome-obsession, gnome-weapon-familiarity, illusion-sense
   * - Goblin (heritages: 4, feats: 7)
   *   H: charhide, irongut, razortooth, snow
   *   F: burn-it, city-scavenger, goblin-lore, goblin-scuttle, goblin-song, goblin-weapon-familiarity, junk-tinker
   * - Half-Elf (heritages: 4, feats: 6)
   *   H: ancient-elf-blood, arcane-bloodline, keen-senses, wanderer
   *   F: elf-atavism, forlorn-half-elf, multitalented, mixed-heritage-adaptability, elven-instincts, cross-cultural-upbringing
   * - Half-Orc (heritages: 4, feats: 6)
   *   H: battle-hardened, grim-scarred, orc-sight, unyielding
   *   F: orc-atavism, feral-endurance, intimidating-glare-half-orc, orc-weapon-familiarity-half-orc, scar-thickened, unyielding-will
   * - Halfling (heritages: 4, feats: 7)
   *   H: gutsy, hillock, nomadic, twilight
   *   F: distracting-shadows, halfling-lore, halfling-luck, halfling-weapon-familiarity, sure-feet, titan-slinger, unfettered-halfling
   * - Human (heritages: 4, feats: 7)
   *   H: versatile, skilled, half-elf, half-orc
   *   F: adapted-cantrip (req: spellcasting), cooperative-nature, general-training, haughty-obstinacy, natural-ambition, natural-skill, unconventional-weaponry
   * - Kobold (heritages: 5, feats: 6)
   *   H: cavern, dragonscaled, spellscale, strongjaw, venomtail
   *   F: kobold-lore, snare-setter, draconic-ties, tunnel-runner, draconic-scout, kobold-weapon-familiarity
   * - Leshy (heritages: 4, feats: 6)
   *   H: cactus, gourd, leaf, vine
   *   F: leshy-lore, seedpod, photosynthetic-recovery, rooted-resilience, verdant-voice, forest-step
   * - Orc (heritages: 5, feats: 6)
   *   H: badlands, battle-ready, deep-orc, grave, rainfall
   *   F: hold-scarred, orc-ferocity, orc-sight, orc-superstition, orc-weapon-familiarity, orc-weapon-carnage
   * - Ratfolk (heritages: 4, feats: 6)
   *   H: desert, sewer, shadow, tunnel
   *   F: ratfolk-lore, cheek-pouches, tunnel-vision, scrounger, communal-instinct, ratfolk-weapon-familiarity
   * - Tengu (heritages: 4, feats: 6)
   *   H: jinxed, skyborn, stormtossed, taloned
   *   F: tengu-lore, one-toed-hop, squawk, sky-bridge-runner, beak-adept, tengu-weapon-familiarity
   */

  /**
   * PF2e heritages for each ancestry.
   */
  const HERITAGES = [
    'Dwarf' => [
      ['id' => 'ancient-blooded', 'name' => 'Ancient-Blooded Dwarf', 'benefit' => 'Resistance to magic', 'granted_abilities' => ['call-on-ancient-blood']],
      ['id' => 'forge', 'name' => 'Forge Dwarf', 'benefit' => 'Fire resistance'],
      ['id' => 'rock', 'name' => 'Rock Dwarf', 'benefit' => 'Extended darkvision'],
      ['id' => 'strong-blooded', 'name' => 'Strong-Blooded Dwarf', 'benefit' => 'Poison resistance'],
    ],
    'Elf' => [
      ['id' => 'arctic', 'name' => 'Arctic Elf', 'benefit' => 'Cold resistance'],
      ['id' => 'cavern', 'name' => 'Cavern Elf', 'benefit' => 'Darkvision'],
      ['id' => 'seer', 'name' => 'Seer Elf', 'benefit' => 'Detect magic cantrip'],
      ['id' => 'woodland', 'name' => 'Woodland Elf', 'benefit' => 'Climb speed'],
    ],
    'Gnome' => [
      ['id' => 'chameleon', 'name' => 'Chameleon Gnome', 'benefit' => 'Change colors'],
      ['id' => 'fey-touched', 'name' => 'Fey-Touched Gnome', 'benefit' => 'First World magic'],
      ['id' => 'sensate', 'name' => 'Sensate Gnome', 'benefit' => 'Enhanced senses'],
      ['id' => 'umbral', 'name' => 'Umbral Gnome', 'benefit' => 'Darkvision'],
    ],
    'Goblin' => [
      ['id' => 'charhide', 'name' => 'Charhide Goblin', 'benefit' => 'Fire resistance'],
      ['id' => 'irongut', 'name' => 'Irongut Goblin', 'benefit' => 'Eat anything'],
      ['id' => 'razortooth', 'name' => 'Razortooth Goblin', 'benefit' => 'Bite attack'],
      ['id' => 'snow', 'name' => 'Snow Goblin', 'benefit' => 'Cold resistance'],
    ],
    'Halfling' => [
      ['id' => 'gutsy', 'name' => 'Gutsy Halfling', 'benefit' => 'Bonus vs fear'],
      ['id' => 'hillock', 'name' => 'Hillock Halfling', 'benefit' => 'Faster healing'],
      ['id' => 'nomadic', 'name' => 'Nomadic Halfling', 'benefit' => 'Extra languages'],
      ['id' => 'twilight', 'name' => 'Twilight Halfling', 'benefit' => 'Low-light vision'],
    ],
    'Human' => [
      ['id' => 'versatile', 'name' => 'Versatile Heritage', 'benefit' => 'Gain one extra 1st-level general feat at character creation'],
      [
        'id'      => 'skilled',
        'name'    => 'Skilled Heritage',
        'benefit' => 'Gain training in one additional skill; become an expert in that skill at level 5',
        'special' => ['extra_trained_skill' => 1, 'expert_skill_at_level' => 5],
      ],
      [
        'id'              => 'half-elf',
        'name'            => 'Half-Elf',
        'benefit'         => 'Gain low-light vision; may select elf ancestry feats in addition to human ones',
        'vision_override' => 'low-light',
        'cross_ancestry_feat_pool' => 'Elf',
      ],
      [
        'id'              => 'half-orc',
        'name'            => 'Half-Orc',
        'benefit'         => 'Gain low-light vision; may select orc ancestry feats in addition to human ones',
        'vision_override' => 'low-light',
        'cross_ancestry_feat_pool' => 'Half-Orc',
      ],
    ],
    'Catfolk' => [
      [
        'id' => 'clawed', 'name' => 'Clawed Catfolk',
        'benefit' => 'Sharp claws grant an agile unarmed claw attack',
        'unarmed_attack' => [
          'name' => 'claw', 'damage' => '1d6', 'type' => 'slashing',
          'traits' => ['agile', 'finesse', 'unarmed'],
        ],
      ],
      [
        'id' => 'hunting', 'name' => 'Hunting Catfolk',
        'benefit' => 'Imprecise scent at 30 ft',
        'special' => ['scent' => ['range' => 30, 'precision' => 'imprecise']],
      ],
      [
        'id' => 'jungle', 'name' => 'Jungle Catfolk',
        'benefit' => 'Ignore difficult terrain from vegetation and rubble',
        'special' => ['ignore_difficult_terrain' => ['vegetation', 'rubble']],
      ],
      [
        'id' => 'nine-lives', 'name' => 'Nine Lives Catfolk',
        'benefit' => 'One-time critical hit death mitigation: treat one killing crit as a normal hit',
        'special' => [
          'death_mitigation' => [
            'trigger' => 'critical_hit_would_kill',
            'effect' => 'treat_as_normal_hit',
            'uses' => 1,
            'per' => 'lifetime',
          ],
        ],
      ],
    ],
    'Half-Elf' => [
      ['id' => 'ancient-elf-blood', 'name' => 'Ancient Elf-Blooded', 'benefit' => 'Elven lineage grants broader familiarity with long-lived traditions and magic'],
      ['id' => 'arcane-bloodline', 'name' => 'Arcane Bloodline', 'benefit' => 'Innate magical aptitude provides a minor cantrip-level magical expression'],
      ['id' => 'keen-senses', 'name' => 'Keen Senses', 'benefit' => 'Heightened perception grants stronger awareness in low-light conditions'],
      ['id' => 'wanderer', 'name' => 'Wanderer Half-Elf', 'benefit' => 'Mixed upbringing improves social adaptability and cross-cultural interaction'],
    ],
    'Half-Orc' => [
      ['id' => 'battle-hardened', 'name' => 'Battle-Hardened Half-Orc', 'benefit' => 'Durable frame improves resilience when taking heavy damage'],
      ['id' => 'grim-scarred', 'name' => 'Grim-Scarred Half-Orc', 'benefit' => 'Intimidating presence boosts social pressure in hostile encounters'],
      ['id' => 'orc-sight', 'name' => 'Orc-Sighted Half-Orc', 'benefit' => 'Enhanced dark-adapted vision improves low-visibility navigation'],
      ['id' => 'unyielding', 'name' => 'Unyielding Half-Orc', 'benefit' => 'Refusal to fall grants a brief endurance surge when dropped low'],
    ],
    'Kobold' => [
      [
        'id' => 'cavern', 'name' => 'Cavern Kobold',
        'benefit' => 'Climb natural stone surfaces; squeeze success → crit success',
        'special' => [
          'climb_natural_stone' => [
            'success_speed' => 'half', 'crit_success_speed' => 'full',
          ],
          'squeeze_success_upgrade' => TRUE,
        ],
      ],
      [
        'id' => 'dragonscaled', 'name' => 'Dragonscaled Kobold',
        'benefit' => 'Resistance to exemplar damage type = level/2 (min 1); doubled vs dragon breath',
        'special' => [
          'resistance' => [
            'damage_type' => 'draconic_exemplar',
            'value' => 'level_half_min_1',
            'double_vs_dragon_breath' => TRUE,
          ],
        ],
      ],
      [
        'id' => 'spellscale', 'name' => 'Spellscale Kobold',
        'benefit' => '1 at-will arcane cantrip; trained in arcane spellcasting (Cha-based)',
        'special' => [
          'cantrip_slots' => 1,
          'cantrip_tradition' => 'arcane',
          'spellcasting_ability' => 'cha',
          'spellcasting_proficiency' => 'trained',
        ],
      ],
      [
        'id' => 'strongjaw', 'name' => 'Strongjaw Kobold',
        'benefit' => 'Jaws unarmed attack (1d6 piercing)',
        'unarmed_attack' => [
          'name' => 'jaws', 'damage' => '1d6', 'type' => 'piercing',
          'group' => 'brawling',
          'traits' => ['finesse', 'unarmed'],
        ],
      ],
      [
        'id' => 'venomtail', 'name' => 'Venomtail Kobold',
        'benefit' => 'Tail Toxin: 1 action, 1/day — apply to weapon; next hit before end of next turn deals persistent poison = level',
        'special' => [
          'tail_toxin' => [
            'action_cost' => 1,
            'uses_per_day' => 1,
            'effect' => 'persistent_poison',
            'damage' => 'level',
          ],
        ],
      ],
    ],
    'Leshy' => [
      ['id' => 'cactus', 'name' => 'Cactus Leshy', 'benefit' => 'Spiny body deters attackers and improves arid survival'],
      ['id' => 'gourd', 'name' => 'Gourd Leshy', 'benefit' => 'Hollowed body grants utility storage and buoyant movement'],
      ['id' => 'leaf', 'name' => 'Leaf Leshy', 'benefit' => 'Photosynthetic vigor improves recovery in natural light'],
      ['id' => 'vine', 'name' => 'Vine Leshy', 'benefit' => 'Flexible tendrils improve grasping and maneuvering through vegetation'],
    ],
    'Orc' => [
      [
        'id' => 'badlands', 'name' => 'Badlands Orc',
        'benefit' => 'Ignore non-magical difficult terrain; extra Fortitude save vs heat exhaustion',
        'special' => ['ignore_difficult_terrain' => ['non_magical'], 'heat_fortitude_bonus' => 2],
      ],
      [
        'id' => 'battle-ready', 'name' => 'Battle-Ready Orc',
        'benefit' => 'Trained in martial weapons (if not already); +1 bonus to initiative when using Perception',
        'special' => ['martial_weapons_trained' => TRUE, 'initiative_perception_bonus' => 1],
      ],
      [
        'id' => 'deep-orc', 'name' => 'Deep Orc',
        'benefit' => 'Low-light vision upgrades to darkvision',
        'vision_override' => 'darkvision',
      ],
      [
        'id' => 'grave', 'name' => 'Grave Orc',
        'benefit' => 'Negative healing: harmed by positive energy, healed by negative energy; treated as undead for energy effects',
        'special' => [
          'negative_healing'       => TRUE,
          'positive_damage_heals'  => FALSE,
          'negative_damage_heals'  => TRUE,
          'undead_energy_rules'    => TRUE,
        ],
      ],
      [
        'id' => 'rainfall', 'name' => 'Rainfall Orc',
        'benefit' => 'Ignore difficult terrain from rain/mud; fire resistance = level/2 (min 1)',
        'special' => [
          'ignore_difficult_terrain' => ['rain', 'mud'],
          'resistance' => ['damage_type' => 'fire', 'value' => 'level_half_min_1'],
        ],
      ],
    ],
    'Ratfolk' => [
      [
        'id' => 'desert', 'name' => 'Desert Ratfolk',
        'benefit' => 'All-fours speed 30 (both hands free); starvation/thirst threshold ×10; heat/cold extremes modified',
        'special' => [
          'all_fours_speed' => 30,
          'all_fours_requires_free_hands' => 2,
          'starvation_thirst_multiplier' => 10,
          'extreme_heat_cold_modified' => TRUE,
        ],
      ],
      [
        'id' => 'sewer', 'name' => 'Sewer Ratfolk',
        'benefit' => 'Immune to filth fever; disease/poison stage reduction improved (success: −2 stages, crit: −3 stages; halved for virulent)',
        'special' => [
          'immune' => ['filth-fever'],
          'disease_poison_stage_reduction' => [
            'success' => 2, 'crit_success' => 3,
            'virulent_halved' => TRUE,
          ],
        ],
      ],
      [
        'id' => 'shadow', 'name' => 'Shadow Ratfolk',
        'benefit' => 'Trained in Intimidation; can Coerce animals without language penalty; animals start one attitude step worse',
        'special' => [
          'trained_skill' => 'Intimidation',
          'coerce_animals_no_language_penalty' => TRUE,
          'animal_starting_attitude_penalty' => 1,
        ],
      ],
      [
        'id' => 'tunnel', 'name' => 'Tunnel Ratfolk',
        'benefit' => 'Burrow-network familiarity improves movement through cramped passages',
      ],
    ],
    'Tengu' => [
      [
        'id' => 'jinxed', 'name' => 'Jinxed Tengu',
        'benefit' => 'Curse/misfortune saves: success → crit success; doomed gain → flat DC 17 to reduce by 1',
        'special' => [
          'curse_misfortune_save_upgrade' => 'success_to_crit',
          'doomed_gain_reduction' => ['type' => 'flat_check', 'dc' => 17, 'reduce_by' => 1],
        ],
      ],
      [
        'id' => 'skyborn', 'name' => 'Skyborn Tengu',
        'benefit' => 'Take 0 damage from any fall; never land Prone from falling',
        'special' => [
          'fall_damage' => 0,
          'fall_prevents_prone' => TRUE,
        ],
      ],
      [
        'id' => 'stormtossed', 'name' => 'Stormtossed Tengu',
        'benefit' => 'Electricity resistance = level/2 (min 1); ignore concealment from rain/fog when targeting',
        'special' => [
          'resistance' => ['damage_type' => 'electricity', 'value' => 'level_half_min_1'],
          'ignore_concealment' => ['rain', 'fog'],
        ],
      ],
      [
        'id' => 'taloned', 'name' => 'Taloned Tengu',
        'benefit' => 'Talons unarmed attack (1d4 slashing, agile/finesse/unarmed/versatile piercing)',
        'unarmed_attack' => [
          'name' => 'talons', 'damage' => '1d4', 'type' => 'slashing',
          'traits' => ['agile', 'finesse', 'unarmed', 'versatile piercing'],
        ],
      ],
    ],
  ];

  /**
   * PF2e Ancestry Feats (Level 1 feats available at character creation).
   * Organized by ancestry with feat traits, prerequisites, and effects.
   */
  const ANCESTRY_FEATS = [
    'Human' => [
      ['id' => 'adapted-cantrip', 'name' => 'Adapted Cantrip', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => 'Spellcasting class feature',
        'benefit' => 'Choose one cantrip from the arcane, divine, occult, or primal spell list. You can cast this spelled as an innate spell at will.'],
      ['id' => 'cooperative-nature', 'name' => 'Cooperative Nature', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'Aid grants a +5 circumstance bonus to skill checks instead of +2, and a +2 circumstance bonus to attack rolls or AC instead of +1.'],
      ['id' => 'general-training', 'name' => 'General Training', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'You gain one 1st-level general feat.'],
      ['id' => 'haughty-obstinacy', 'name' => 'Haughty Obstinacy', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => '+1 circumstance bonus to Will saves against mental effects. On a success, the effect source is temporarily immune to further attempts for 10 minutes.'],
      ['id' => 'natural-ambition', 'name' => 'Natural Ambition', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'You gain a 1st-level class feat for your class.'],
      ['id' => 'natural-skill', 'name' => 'Natural Skill', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'You gain training in two skills of your choice.'],
      ['id' => 'unconventional-weaponry', 'name' => 'Unconventional Weaponry', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'Choose one uncommon weapon. You gain access to that weapon and become trained in that weapon.'],
    ],
    'Dwarf' => [
      ['id' => 'dwarven-lore', 'name' => 'Dwarven Lore', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'Trained in Crafting and Religion. Gain Crafting Lore and Dwarven Lore.'],
      ['id' => 'dwarven-weapon-familiarity', 'name' => 'Dwarven Weapon Familiarity', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'You are trained with the battle axe, pick, and warhammer, and all dwarf weapons. For proficiency, treat martial dwarf weapons as simple, and advanced dwarf weapons as martial.'],
      ['id' => 'rock-runner', 'name' => 'Rock Runner', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'You can ignore difficult terrain caused by rubble and uneven ground made of stone and earth. Acrobatics DC to Balance on narrow surfaces and uneven ground made of stone or earth reduced by 2.'],
      ['id' => 'stonecunning', 'name' => 'Stonecunning', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => '+2 circumstance bonus on Perception checks to notice unusual stonework. When not Seeking, get a check to find unusual stonework anyway.'],
      ['id' => 'unburdened-iron', 'name' => 'Unburdened Iron', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'Ignore the reduction to Speed from wearing armor and reduce the encumbered speed penalty from 5 feet to only 0 feet.'],
      ['id' => 'vengeful-hatred', 'name' => 'Vengeful Hatred', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'Choose drow, duergar, giant, or orc when you take this feat. +1 circumstance damage per weapon die against creatures with that trait.'],
    ],
    'Elf' => [
      ['id' => 'ancestral-longevity', 'name' => 'Ancestral Longevity', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'You become trained in one skill of your choice. Once per day after rest, you can switch which skill you are trained in.'],
      ['id' => 'elven-lore', 'name' => 'Elven Lore', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'Trained in Arcana and Nature. Gain Elven Lore skill.'],
      ['id' => 'elven-weapon-familiarity', 'name' => 'Elven Weapon Familiarity', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'You are trained with longbows, composite longbows, longswords, rapiers, shortbows, and composite shortbows. For proficiency, treat martial elf weapons as simple, and advanced elf weapons as martial.'],
      ['id' => 'forlorn', 'name' => 'Forlorn', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => '+1 circumstance bonus on saving throws against emotion effects. If you roll a success on a save against an emotion effect, you get a critical success instead.'],
      ['id' => 'nimble-elf', 'name' => 'Nimble Elf', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'Your Speed increases to 35 feet.'],
      ['id' => 'otherworldly-magic', 'name' => 'Otherworldly Magic', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'Choose one cantrip from the primal spell list. You can cast it as a primal innate spell at will.'],
      ['id' => 'unwavering-mien', 'name' => 'Unwavering Mien', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'When you roll a success on a saving throw against a mental effect, you critically succeed instead.'],
    ],
    'Gnome' => [
      ['id' => 'animal-accomplice', 'name' => 'Animal Accomplice', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'You gain a familiar. If you retrain this feat, you lose the familiar.'],
      ['id' => 'burrow-elocutionist', 'name' => 'Burrow Elocutionist', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'You can speak with burrowing animals (badgers, moles, rabbits, etc.). This doesn\'t make them friendly.'],
      ['id' => 'fey-fellowship', 'name' => 'Fey Fellowship', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'Fey creatures of your level or lower automatically improve their attitude toward you by one step (hostile becomes unfriendly, unfriendly becomes indifferent, etc.).'],
      ['id' => 'first-world-magic', 'name' => 'First World Magic', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'Choose one primal cantrip. You can cast it as a primal innate spell at will.'],
      ['id' => 'gnome-obsession', 'name' => 'Gnome Obsession', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'Choose a Lore skill. You become trained in that skill and gain the Assurance skill feat with it.'],
      ['id' => 'gnome-weapon-familiarity', 'name' => 'Gnome Weapon Familiarity', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'Trained with glaive and kukri. For proficiency, treat martial gnome weapons as simple, advanced gnome weapons as martial.'],
      ['id' => 'illusion-sense', 'name' => 'Illusion Sense', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'You automatically get a Perception check to disbelieve illusions you can see, with a +2 circumstance bonus.'],
    ],
    'Goblin' => [
      ['id' => 'burn-it', 'name' => 'Burn It!', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Fire damage you deal with non-magical weapons and alchemical items gains a +1 status bonus. Resistance to your fire damage is reduced by an amount equal to half your level (minimum 1).'],
      ['id' => 'city-scavenger', 'name' => 'City Scavenger', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'You know the urban environment intimately. You can Subsist using Society or Survival in a settlement. You can use Society in place of Survival to Track and Seek in urban environments.'],
      ['id' => 'goblin-lore', 'name' => 'Goblin Lore', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Trained in Nature and Stealth. Gain Goblin Lore skill.'],
      ['id' => 'goblin-scuttle', 'name' => 'Goblin Scuttle', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'When an ally ends a move action adjacent to you, you can Step as a reaction.'],
      ['id' => 'goblin-song', 'name' => 'Goblin Song', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'You sing annoying songs. Attempt a Performance check against the Will DC of a single enemy within 30 feet. Success imposes frightened 1, critical success frightened 2. Target is then temporarily immune for 1 hour.'],
      ['id' => 'goblin-weapon-familiarity', 'name' => 'Goblin Weapon Familiarity', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Trained with dogslicers and horsechoppers. For proficiency, treat martial goblin weapons as simple, advanced goblin weapons as martial.'],
      ['id' => 'junk-tinker', 'name' => 'Junk Tinker', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Trained in Crafting. You can Craft nonmagical items from junk. Crafting DCs for such items are 5 easier, but items are shoddy (break on failed attack/check).'],
    ],
    'Halfling' => [
      ['id' => 'distracting-shadows', 'name' => 'Distracting Shadows', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'You have a knack for avoiding notice. You can use creatures one or more sizes larger than you as cover for Hide and Sneak checks.'],
      ['id' => 'halfling-lore', 'name' => 'Halfling Lore', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Trained in Acrobatics and Stealth. Gain Halfling Lore skill.'],
      ['id' => 'halfling-luck', 'name' => 'Halfling Luck', 'level' => 1, 'traits' => ['Halfling', 'Fortune'], 'prerequisites' => '',
        'benefit' => 'You can reroll a failed skill check or save once per day. Must use second result even if worse.'],
      ['id' => 'halfling-weapon-familiarity', 'name' => 'Halfling Weapon Familiarity', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Trained with sling and halfling sling staff. For proficiency, treat martial halfling weapons as simple, advanced halfling weapons as martial.'],
      ['id' => 'sure-feet', 'name' => 'Sure Feet', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'You can attempt Acrobatics checks to Balance on narrow surfaces and uneven ground without rolling. On a critical failure, you succeed instead.'],
      ['id' => 'titan-slinger', 'name' => 'Titan Slinger', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Your thrown weapons and sling range increment increased by 10 feet. Increases to 20 feet at 13th level.'],
      ['id' => 'unfettered-halfling', 'name' => 'Unfettered Halfling', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Success on a check to Escape is automatically a critical success. +2 circumstance bonus to checks to Escape.'],
    ],
    'Catfolk' => [
      ['id' => 'catfolk-lore', 'name' => 'Catfolk Lore', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'You become trained in Acrobatics and Stealth, and gain Catfolk Lore.'],
      ['id' => 'catfolk-weapon-familiarity', 'name' => 'Catfolk Weapon Familiarity', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'You are trained with traditional catfolk weapons and treat martial catfolk weapons as simple for proficiency.'],
      ['id' => 'graceful-step', 'name' => 'Graceful Step', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'You gain a +2 circumstance bonus to Acrobatics checks to Balance and Tumble Through.'],
      ['id' => 'feline-eyes', 'name' => 'Feline Eyes', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'Your low-light vision sharpens; checks relying on sight in dim conditions gain a +1 circumstance bonus.'],
      ['id' => 'well-groomed', 'name' => 'Well-Groomed', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Diplomacy checks to Make an Impression in social settings where appearance matters.'],
      ['id' => 'cat-nap', 'name' => 'Cat Nap', 'level' => 1, 'traits' => ['Catfolk'], 'prerequisites' => '',
        'benefit' => 'You require less downtime for light rest and can recover from short rests more efficiently.'],
    ],
    'Half-Elf' => [
      ['id' => 'elf-atavism', 'name' => 'Elf Atavism', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain one elf ancestry feat for which you meet the prerequisites.'],
      ['id' => 'forlorn-half-elf', 'name' => 'Forlorn Half-Elf', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to saves against emotion effects and can treat one success each day as a critical success.'],
      ['id' => 'multitalented', 'name' => 'Multitalented', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain training in one skill and one additional language of your choice.'],
      ['id' => 'mixed-heritage-adaptability', 'name' => 'Mixed Heritage Adaptability', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to one trained skill of your choice; you can change it after daily preparations.'],
      ['id' => 'elven-instincts', 'name' => 'Elven Instincts', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to initiative rolls and Perception checks to Seek.'],
      ['id' => 'cross-cultural-upbringing', 'name' => 'Cross-Cultural Upbringing', 'level' => 1, 'traits' => ['Half-Elf'], 'prerequisites' => '',
        'benefit' => 'You gain Society training and can use Society to Recall Knowledge about either human or elven communities.'],
    ],
    'Half-Orc' => [
      ['id' => 'orc-atavism', 'name' => 'Orc Atavism', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'You gain one orc ancestry feat for which you meet the prerequisites.'],
      ['id' => 'feral-endurance', 'name' => 'Feral Endurance', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'Once per day when reduced to 0 HP, you remain at 1 HP and become wounded 1.'],
      ['id' => 'intimidating-glare-half-orc', 'name' => 'Intimidating Glare', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'You can Demoralize a target without sharing a language.'],
      ['id' => 'orc-weapon-familiarity-half-orc', 'name' => 'Orc Weapon Familiarity', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'You are trained in iconic orc weapons and treat martial orc weapons as simple for proficiency.'],
      ['id' => 'scar-thickened', 'name' => 'Scar-Thickened', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Fortitude saves against persistent bleed and poison effects.'],
      ['id' => 'unyielding-will', 'name' => 'Unyielding Will', 'level' => 1, 'traits' => ['Half-Orc'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Will saves against fear effects.'],
    ],
    'Kobold' => [
      ['id' => 'kobold-lore', 'name' => 'Kobold Lore', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'You become trained in Crafting and Stealth and gain Kobold Lore.'],
      ['id' => 'snare-setter', 'name' => 'Snare Setter', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'You can craft and deploy simple snares more quickly, reducing setup time.'],
      ['id' => 'draconic-ties', 'name' => 'Draconic Ties', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'Choose a draconic damage type; gain minor resistance to that type.'],
      ['id' => 'tunnel-runner', 'name' => 'Tunnel Runner', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'You ignore movement penalties from cramped underground passages and gain +2 to Acrobatics checks to Squeeze.'],
      ['id' => 'draconic-scout', 'name' => 'Draconic Scout', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to initiative and Survival checks when underground.'],
      ['id' => 'kobold-weapon-familiarity', 'name' => 'Kobold Weapon Familiarity', 'level' => 1, 'traits' => ['Kobold'], 'prerequisites' => '',
        'benefit' => 'You are trained with traditional kobold weapons and treat martial kobold weapons as simple for proficiency.'],
    ],
    'Leshy' => [
      ['id' => 'leshy-lore', 'name' => 'Leshy Lore', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'You become trained in Nature and Diplomacy and gain Leshy Lore.'],
      ['id' => 'seedpod', 'name' => 'Seedpod', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'You can produce and throw small seed pods as a minor ranged natural attack.'],
      ['id' => 'photosynthetic-recovery', 'name' => 'Photosynthetic Recovery', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'When resting in natural sunlight, you recover additional Hit Points.'],
      ['id' => 'rooted-resilience', 'name' => 'Rooted Resilience', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus against forced movement and effects that would knock you prone.'],
      ['id' => 'verdant-voice', 'name' => 'Verdant Voice', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'You can communicate simple intent with common plants and gain +1 to Nature checks to influence plant creatures.'],
      ['id' => 'forest-step', 'name' => 'Forest Step', 'level' => 1, 'traits' => ['Leshy'], 'prerequisites' => '',
        'benefit' => 'You ignore difficult terrain caused by natural undergrowth.'],
    ],
    'Ratfolk' => [
      ['id' => 'ratfolk-lore', 'name' => 'Ratfolk Lore', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'You become trained in Society and Thievery and gain Ratfolk Lore.'],
      ['id' => 'cheek-pouches', 'name' => 'Cheek Pouches', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'You can stow and retrieve a small held item more efficiently each round.'],
      ['id' => 'tunnel-vision', 'name' => 'Tunnel Vision', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Perception checks to detect movement in narrow corridors and tunnels.'],
      ['id' => 'scrounger', 'name' => 'Scrounger', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Crafting checks to Repair and to checks to Subsist in settlements.'],
      ['id' => 'communal-instinct', 'name' => 'Communal Instinct', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'When adjacent to an ally, you gain a +1 circumstance bonus to saves against fear.'],
      ['id' => 'ratfolk-weapon-familiarity', 'name' => 'Ratfolk Weapon Familiarity', 'level' => 1, 'traits' => ['Ratfolk'], 'prerequisites' => '',
        'benefit' => 'You are trained with traditional ratfolk weapons and treat martial ratfolk weapons as simple for proficiency.'],
    ],
    'Tengu' => [
      ['id' => 'tengu-lore', 'name' => 'Tengu Lore', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'You become trained in Acrobatics and Deception and gain Tengu Lore.'],
      ['id' => 'one-toed-hop', 'name' => 'One-Toed Hop', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'Your mobility training grants a +2 circumstance bonus to checks to Balance and Leap.'],
      ['id' => 'squawk', 'name' => 'Squawk', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'You can emit a harsh cry to Demoralize; targets are temporarily immune for 1 hour after you use this effect.'],
      ['id' => 'sky-bridge-runner', 'name' => 'Sky-Bridge Runner', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'You gain a +1 circumstance bonus to Acrobatics checks while traversing narrow or elevated surfaces.'],
      ['id' => 'beak-adept', 'name' => 'Beak Adept', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'Your beak Strike gains improved handling and a +1 circumstance bonus to Disarm attempts.'],
      ['id' => 'tengu-weapon-familiarity', 'name' => 'Tengu Weapon Familiarity', 'level' => 1, 'traits' => ['Tengu'], 'prerequisites' => '',
        'benefit' => 'You are trained with traditional tengu weapons and treat martial tengu weapons as simple for proficiency.'],
    ],
    'Orc' => [
      ['id' => 'hold-scarred', 'name' => 'Hold-Scarred Orc', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => 'Trained in Stealth. Gain the Terrain Stalker feat for underground terrain. If you retrain out of this feat, you lose Terrain Stalker.'],
      ['id' => 'orc-ferocity', 'name' => 'Orc Ferocity', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => 'Once per day when reduced to 0 HP, you remain at 1 HP and become wounded 1 (or increase your wounded by 1).'],
      ['id' => 'orc-sight', 'name' => 'Orc Sight', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => 'Low-light vision',
        'benefit' => 'Your low-light vision is replaced with darkvision.'],
      ['id' => 'orc-superstition', 'name' => 'Orc Superstition', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => '+1 circumstance bonus to saving throws against magic. If you succeed at a save against a magical effect, treat it as a critical success instead (once per day).'],
      ['id' => 'orc-weapon-familiarity', 'name' => 'Orc Weapon Familiarity', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => 'Trained with the falchion and greataxe. For proficiency, treat martial orc weapons as simple, advanced orc weapons as martial.'],
      ['id' => 'orc-weapon-carnage', 'name' => 'Orc Weapon Carnage', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => 'Orc Weapon Familiarity',
        'benefit' => 'When you critically succeed at an attack roll with an orc weapon, you apply the weapon\'s critical specialization effect.'],
    ],
  ];

  /**
   * PF2e backgrounds with mechanical benefits.
   * Each background grants: 1 fixed ability boost (auto-applied) + 1 free ability boost (player choice,
   * must differ from fixed), 1 skill training, 1 lore skill, and 1 skill feat.
   */
  const BACKGROUNDS = [
    'acolyte' => [
      'id' => 'acolyte',
      'name' => 'Acolyte',
      'description' => 'You spent your early days in a religious monastery or cloister.',
      'fixed_boost' => 'wis',
      'skill' => 'Religion',
      'feat' => 'Student of the Canon',
      'lore' => 'Scribing Lore',
    ],
    'acrobat' => [
      'id' => 'acrobat',
      'name' => 'Acrobat',
      'description' => 'You trained as a tumbler, aerialist, or gymnast, performing breathtaking feats.',
      'fixed_boost' => 'dex',
      'skill' => 'Acrobatics',
      'feat' => 'Steady Balance',
      'lore' => 'Circus Lore',
    ],
    'animal_whisperer' => [
      'id' => 'animal_whisperer',
      'name' => 'Animal Whisperer',
      'description' => 'You have a natural affinity for animals and have spent time learning their ways.',
      'fixed_boost' => 'wis',
      'skill' => 'Nature',
      'feat' => 'Train Animal',
      'lore' => 'Plains Lore',
    ],
    'artisan' => [
      'id' => 'artisan',
      'name' => 'Artisan',
      'description' => 'You served as an apprentice to a master artisan and learned the intricacies of a craft.',
      'fixed_boost' => 'str',
      'skill' => 'Crafting',
      'feat' => 'Specialty Crafting',
      'lore' => 'Guild Lore',
    ],
    'barkeep' => [
      'id' => 'barkeep',
      'name' => 'Barkeep',
      'description' => 'You tended bar, serving drinks and managing the locals at a tavern or inn.',
      'fixed_boost' => 'cha',
      'skill' => 'Diplomacy',
      'feat' => 'Hobnobber',
      'lore' => 'Alcohol Lore',
    ],
    'criminal' => [
      'id' => 'criminal',
      'name' => 'Criminal',
      'description' => 'You have a history of breaking the law and living in the criminal underworld.',
      'fixed_boost' => 'dex',
      'skill' => 'Stealth',
      'feat' => 'Experienced Smuggler',
      'lore' => 'Underworld Lore',
    ],
    'entertainer' => [
      'id' => 'entertainer',
      'name' => 'Entertainer',
      'description' => 'You performed before crowds, earning your coin through art and panache.',
      'fixed_boost' => 'cha',
      'skill' => 'Performance',
      'feat' => 'Fascinating Performance',
      'lore' => 'Theater Lore',
    ],
    'farmhand' => [
      'id' => 'farmhand',
      'name' => 'Farmhand',
      'description' => 'You grew up in a rural area, working the land and tending livestock.',
      'fixed_boost' => 'con',
      'skill' => 'Athletics',
      'feat' => 'Assurance (Athletics)',
      'lore' => 'Farming Lore',
    ],
    'guard' => [
      'id' => 'guard',
      'name' => 'Guard',
      'description' => 'You served in a military, guard force, or city watch, protecting others.',
      'fixed_boost' => 'str',
      'skill' => 'Intimidation',
      'feat' => 'Quick Coercion',
      'lore' => 'Legal Lore',
    ],
    'merchant' => [
      'id' => 'merchant',
      'name' => 'Merchant',
      'description' => 'You come from a family of traders, or you worked in commerce yourself.',
      'fixed_boost' => 'int',
      'skill' => 'Diplomacy',
      'feat' => 'Bargain Hunter',
      'lore' => 'Mercantile Lore',
    ],
    'noble' => [
      'id' => 'noble',
      'name' => 'Noble',
      'description' => 'You were born into nobility or achieved a position of privilege.',
      'fixed_boost' => 'cha',
      'skill' => 'Society',
      'feat' => 'Courtly Graces',
      'lore' => 'Heraldry Lore',
    ],
    'scholar' => [
      'id' => 'scholar',
      'name' => 'Scholar',
      'description' => 'You spent years studying in libraries, academies, or under mentors.',
      'fixed_boost' => 'int',
      'skill' => 'Arcana',
      'feat' => 'Assurance',
      'lore' => 'Academia Lore',
    ],
    'warrior' => [
      'id' => 'warrior',
      'name' => 'Warrior',
      'description' => 'You have a history of fighting, whether through military service or personal conflict.',
      'fixed_boost' => 'str',
      'skill' => 'Intimidation',
      'feat' => 'Intimidating Glare',
      'lore' => 'Warfare Lore',
    ],
    // APG backgrounds
    'haunted' => [
      'id' => 'haunted',
      'name' => 'Haunted',
      'description' => 'A malevolent entity has latched onto you, aiding you while creating havoc.',
      'fixed_boost' => 'wis',
      'skill' => 'Occultism',
      'feat' => 'Dubious Knowledge',
      'lore' => 'Haunted Lore',
      'special' => [
        // On Aid failure → Frightened 2; on critical fail → Frightened 4.
        // Initial Frightened from this ability cannot be reduced by prevention effects.
        'haunted_aid' => [
          'fail_condition' => 'frightened_2',
          'crit_fail_condition' => 'frightened_4',
          'initial_frightened_prevention_immune' => TRUE,
        ],
      ],
    ],
    'fey_touched' => [
      'id' => 'fey_touched',
      'name' => 'Fey-Touched',
      'description' => 'You were touched by fey magic, giving you a hint of their luck and whimsy.',
      'fixed_boost' => 'cha',
      'skill' => 'Nature',
      'feat' => 'Fey Fellowship',
      'lore' => 'Fey Lore',
      'special' => [
        // Fey's Fortune: 1/day free-action fortune on any skill check (roll twice, use better).
        'feys_fortune' => [
          'action_cost' => 0,
          'uses_per_day' => 1,
          'effect' => 'fortune_skill_check',
          'description' => 'Roll twice and use the better result on one skill check.',
        ],
      ],
    ],
    'returned' => [
      'id' => 'returned',
      'name' => 'Returned',
      'description' => 'You have died and returned to life, giving you an uncanny knack for cheating death.',
      'fixed_boost' => 'con',
      'skill' => 'Medicine',
      'feat' => 'Diehard',
      'lore' => 'Underworld Lore',
      'special' => [
        // Diehard feat is automatically granted — not a selection. No separate feat choice needed.
        'auto_grant_feat' => 'Diehard',
      ],
    ],
  ];

  /**
   * PF2e classes with base stats.
   */
  const CLASSES = [
    'fighter' => [
      'id' => 'fighter',
      'name' => 'Fighter',
      'description' => 'A master of martial combat, skilled with a variety of weapons and armor.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Expert',
        'reflex' => 'Trained',
        'will' => 'Trained',
      ],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Expert in simple and martial weapons, trained in advanced weapons',
      'trained_skills' => 3,
    ],
    'rogue' => [
      'id' => 'rogue',
      'name' => 'Rogue',
      'description' => 'You are skilled and opportunistic. Using your sharp wits and quick reactions, you take advantage of your opponents\' missteps.',
      'hp' => 8,
      'key_ability' => 'Dexterity',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Trained',
        'reflex' => 'Expert',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 7 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons, rapier, sap, shortbow, and shortsword',
      'trained_skills' => 7,
    ],
    'wizard' => [
      'id' => 'wizard',
      'name' => 'Wizard',
      'description' => 'You are an eternal student of the arcane secrets of the universe, using your mastery of magic to cast powerful spells.',
      'hp' => 6,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in club, crossbow, dagger, heavy crossbow, and staff',
      'spellcasting' => 'Arcane spellcasting, Intelligence',
      'trained_skills' => 2,
    ],
    'cleric' => [
      'id' => 'cleric',
      'name' => 'Cleric',
      'description' => 'Deities work their will upon the world in infinite ways, and you serve as one of their most stalwart mortal servants.',
      'hp' => 8,
      'key_ability' => 'Wisdom',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons and the favored weapon of your deity',
      'spellcasting' => 'Divine spellcasting, Wisdom',
      'trained_skills' => 2,
    ],
    'ranger' => [
      'id' => 'ranger',
      'name' => 'Ranger',
      'description' => 'Some rangers believe civilization wears down the soul, but still needs to be protected. Others say nature needs to be protected from the greedy.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Expert',
        'reflex' => 'Expert',
        'will' => 'Trained',
      ],
      'skills' => 'Choose 4 + Intelligence modifier',
      'weapons' => 'Trained in simple and martial weapons',
      'trained_skills' => 4,
    ],
    'bard' => [
      'id' => 'bard',
      'name' => 'Bard',
      'description' => 'You are a master of artistry, a scholar of hidden secrets, and a captivating persuader.',
      'hp' => 8,
      'key_ability' => 'Charisma',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 4 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons, longsword, rapier, sap, shortbow, shortsword, and whip',
      'spellcasting' => 'Occult spellcasting, Charisma',
      'trained_skills' => 4,
    ],
    'barbarian' => [
      'id' => 'barbarian',
      'name' => 'Barbarian',
      'description' => 'Rage consumes you in battle. You delight in wreaking havoc and using powerful weapons to carve through your enemies.',
      'hp' => 12,
      'key_ability' => 'Strength',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Expert',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Trained in simple and martial weapons',
      'trained_skills' => 3,
    ],
    'champion' => [
      'id' => 'champion',
      'name' => 'Champion',
      'description' => 'You are a divine fighting servant, an instrument of your deity\'s will.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Expert',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in simple and martial weapons, and the favored weapon of your deity',
      'trained_skills' => 2,
      'alignment_options' => [
        'good'  => ['access' => 'standard',  'label' => 'Good Champion',  'description' => 'Paladins and other good champions serve deities of light and justice.'],
        'evil'  => ['access' => 'uncommon',  'label' => 'Evil Champion',  'description' => 'Antipaladins and other evil champions require GM access grant (Uncommon). They gain alignment-appropriate champion\'s reaction and devotion spells paralleling the good champion structure.'],
      ],
    ],
    'druid' => [
      'id' => 'druid',
      'name' => 'Druid',
      'description' => 'You hold a deep commitment to nature and natural order. You gain primal magic through communion with nature.',
      'hp' => 8,
      'key_ability' => 'Wisdom',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons',
      'spellcasting' => 'Primal spellcasting, Wisdom',
      'trained_skills' => 2,
    ],
    'monk' => [
      'id' => 'monk',
      'name' => 'Monk',
      'description' => 'The strength of your fist flows from your mind and spirit. You seek perfection through discipline.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Expert',
        'reflex' => 'Expert',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 4 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons and unarmed attacks',
      'trained_skills' => 4,
    ],
    'sorcerer' => [
      'id' => 'sorcerer',
      'name' => 'Sorcerer',
      'description' => 'You didn\'t choose to become a spellcaster—you were born one. Magic is in your blood, whether from a draconic bloodline or strange magical essence.',
      'hp' => 6,
      'key_ability' => 'Charisma',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons',
      'spellcasting' => 'Bloodline spellcasting, Charisma',
      'trained_skills' => 2,
    ],
    'alchemist' => [
      'id' => 'alchemist',
      'name' => 'Alchemist',
      'description' => 'You enjoy tinkering with alchemical items and formulas to discover their secrets.',
      'hp' => 8,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Expert',
        'reflex' => 'Expert',
        'will' => 'Trained',
      ],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons and alchemical bombs',
      'trained_skills' => 3,
    ],
    'investigator' => [
      'id' => 'investigator',
      'name' => 'Investigator',
      'description' => 'You seek to uncover the truth, doggedly pursuing leads to reveal the plots of devious villains.',
      'hp' => 8,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude'  => 'Trained',
        'reflex'     => 'Expert',
        'will'       => 'Expert',
      ],
      // Light armor + unarmored; simple weapons + rapier.
      'armor'   => ['light', 'unarmored'],
      'weapons' => 'Trained in simple weapons and the rapier',
      // Total trained skills = 4 + Int + 1 (Society, always) + 1 (methodology skill).
      'trained_skills'         => 4,
      'class_skills'           => ['Society'],
      'methodology_bonus_skill' => TRUE,
      // ── Core Abilities ──────────────────────────────────────────────────────
      'devise_a_stratagem' => [
        'action_cost'      => 1,
        'traits'           => ['Fortune'],
        'frequency'        => '1 per round',
        'effect'           => 'Roll a d20 immediately; stored result replaces the next qualifying Strike attack roll this turn.',
        'qualifying_weapons' => ['agile melee', 'finesse melee', 'ranged', 'sap', 'agile unarmed', 'finesse unarmed'],
        'attack_modifier'  => 'Intelligence (replaces Strength/Dexterity on qualifying Strike)',
        'stored_roll' => [
          // Cleared at end of turn whether used or not.
          'discard_at_end_of_turn' => TRUE,
          'discard_if_no_qualifying_strike' => TRUE,
        ],
        // Free action when the target is an active lead.
        'active_lead_cost_reduction' => ['action_cost' => 0, 'condition' => 'target_is_active_lead'],
      ],
      'pursue_a_lead' => [
        'action_cost'   => '1 minute (exploration)',
        'benefit'       => '+1 circumstance bonus to investigative checks against the designated lead target.',
        'max_leads'     => 2,
        // Designating a 3rd lead removes the oldest automatically.
        'oldest_lead_removed_at_cap' => TRUE,
        'target_types'  => ['creature', 'object', 'location'],
      ],
      'clue_in' => [
        'action_cost' => 0,
        'traits'      => ['Reaction'],
        'frequency'   => '1 per 10 minutes',
        'trigger'     => 'Successful investigative check',
        'effect'      => 'Share the Pursue a Lead circumstance bonus with one ally within 30 feet.',
        'range'       => '30 feet',
      ],
      'strategic_strike' => [
        'description' => 'Precision damage on attacks preceded by Devise a Stratagem in the same turn.',
        'damage_type' => 'precision',
        // Only the highest precision damage applies (does not stack with sneak attack).
        'precision_damage_no_stack' => TRUE,
        'progression' => [
          1  => '1d6',
          5  => '2d6',
          9  => '3d6',
          13 => '4d6',
          17 => '5d6',
        ],
      ],
      // ── Methodologies ───────────────────────────────────────────────────────
      'methodology' => [
        'required' => TRUE,
        'note'     => 'Chosen at L1; grants one additional trained skill plus methodology-specific features.',
        'options' => [
          'alchemical-sciences' => [
            'id'   => 'alchemical-sciences',
            'name' => 'Alchemical Sciences',
            'auto_grants' => [
              'skill_proficiency' => 'Crafting',
              'feat'              => 'Alchemical Crafting',
            ],
            'formula_book' => TRUE,
            // Daily preparations produce versatile vials = Int modifier.
            'versatile_vials' => [
              'count_basis' => 'intelligence_modifier',
              'refreshed'   => 'daily_preparations',
            ],
            'quick_tincture' => [
              'id'          => 'quick-tincture',
              'action_cost' => 1,
              'effect'      => 'Consume one versatile vial to produce an alchemical item from known formulas.',
              'cost'        => 'one versatile vial',
            ],
          ],
          'empiricism' => [
            'id'   => 'empiricism',
            'name' => 'Empiricism',
            'auto_grants' => [
              'skill_proficiency' => 'one Intelligence-based skill (player choice)',
              'feat'              => "That's Odd",
            ],
            'expeditious_inspection' => [
              'id'          => 'expeditious-inspection',
              'action_cost' => 0,
              'traits'      => ['Free Action'],
              'frequency'   => '1 per 10 minutes',
              'options'     => ['Recall Knowledge', 'Seek', 'Sense Motive'],
              'effect'      => 'Perform one of the listed actions instantly.',
            ],
            // Empiricism removes the lead requirement for Devise a Stratagem action cost.
            // Free-action waiver applies only to the action cost, not other lead-dependent effects.
            'devise_a_stratagem_lead_waiver' => TRUE,
            'devise_a_stratagem_lead_waiver_note' => 'Empiricism waiver applies only to Devise a Stratagem action cost; other lead-dependent effects still require an active lead.',
          ],
          'forensic-medicine' => [
            'id'   => 'forensic-medicine',
            'name' => 'Forensic Medicine',
            'auto_grants' => [
              'skill_proficiency' => 'Medicine',
              'feats' => ['Battle Medicine', 'Forensic Acumen'],
            ],
            'battle_medicine_bonus' => [
              // Adds investigator level to Battle Medicine healing result.
              'bonus_type'  => 'investigator_level',
              'applies_to'  => 'battle_medicine_healing',
            ],
            // Reduces Battle Medicine recovery immunity from 1 day to 1 hour.
            'battle_medicine_immunity_duration' => '1 hour',
          ],
          'interrogation' => [
            'id'   => 'interrogation',
            'name' => 'Interrogation',
            'auto_grants' => [
              'skill_proficiency' => 'Diplomacy',
              'feat'              => 'No Cause for Alarm',
            ],
            // Pursue a Lead can designate a social target in conversation mode.
            'pursue_lead_social_mode' => TRUE,
            'pointed_question' => [
              'id'          => 'pointed-question',
              'action_cost' => 1,
              'skills'      => ['Intimidation', 'Deception'],
              'effect'      => 'Expose an inconsistency in a target\'s statements.',
              // Target must have made a statement this encounter (GM adjudicated).
              'requires_prior_statement' => TRUE,
              'prior_statement_note'     => 'GM check: target must have made a statement this encounter.',
            ],
          ],
        ],
      ],
    ],
    'oracle' => [
      'id' => 'oracle',
      'name' => 'Oracle',
      'description' => 'You draw upon divine power through your mysterious connection to a curse that grants you abilities.',
      'hp' => 8,
      'key_ability' => 'Charisma',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons',
      'spellcasting' => 'Divine spellcasting, Charisma',
      'trained_skills' => 3,
      'mystery' => [
        'required' => TRUE,
        'options'  => ['ancestors', 'battle', 'bones', 'cosmos', 'flames', 'life', 'lore', 'tempest'],
        'note'     => 'Grants initial/advanced/greater revelation spells and unique 4-stage curse. See ORACLE_MYSTERIES.',
      ],
      'focus_pool' => [
        'start'  => 2,
        'cap'    => 3,
        'note'   => 'Oracle starts with 2 Focus Points (unique). Revelation spells carry Cursebound trait; casting one advances the curse stage.',
      ],
      'cursebound' => [
        'rule'   => 'Every revelation spell carries the Cursebound trait. Casting any one advances the oracle curse tracker by one stage.',
        'stages' => 4,
      ],
    ],
    'swashbuckler' => [
      'id' => 'swashbuckler',
      'name' => 'Swashbuckler',
      'description' => 'You fight with flair and style, performing daring athletic feats in the heat of battle.',
      'hp' => 10,
      'key_ability' => 'Dexterity',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Trained',
        'reflex' => 'Expert',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 5 + Intelligence modifier',
      'weapons' => 'Trained in simple and martial weapons',
      'trained_skills' => 5,
    ],
    'witch' => [
      'id' => 'witch',
      'name' => 'Witch',
      'description' => 'You command powerful magic through your patron, who granted you a familiar to aid your spellcasting. Your familiar is a class-locked feature that stores all your spells; you must commune with it to prepare each day.',
      'hp' => 6,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'armor_proficiency' => 'unarmored_only',
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons',
      'spellcasting' => 'Patron spellcasting, Intelligence',
      'trained_skills' => 3,
      'familiar' => [
        'required' => TRUE,
        'stores_spells' => TRUE,
        'starting_cantrips' => 10,
        'starting_spells' => 5,
        'patron_granted_spell' => 1,
        'spells_per_level_up' => 2,
        'bonus_abilities_at_levels' => [1, 6, 12, 18],
        'scroll_learning' => TRUE,
        'death_note' => 'Familiar death does not erase known spells; replacement familiar with all same spells granted at next daily prep.',
      ],
      'hexes' => [
        'focus_pool_start' => 1,
        'refocus' => '10 minutes communing with familiar',
        'one_hex_per_turn' => TRUE,
        'hex_cantrips_free' => TRUE,
        'hex_cantrip_auto_heighten' => 'half level rounded up',
      ],
    ],
  ];

  /**
   * PF2e Class Feats (Level 1 feats available at character creation).
   * Organized by class with feat traits, prerequisites, and effects.
   */
  const CLASS_FEATS = [
    'fighter' => [
      ['id' => 'double-slice', 'name' => 'Double Slice', 'level' => 1, 'traits' => ['Fighter'], 'prerequisites' => '',
        'benefit' => 'You lash out at your foe with both weapons. Make two Strikes, one with each of your two melee weapons, each using your current multiple attack penalty. Both Strikes must have the same target. If the second Strike hits, combine their damage for the purposes of resistances and weaknesses. Apply your multiple attack penalty to the Strikes normally.'],
      ['id' => 'exacting-strike', 'name' => 'Exacting Strike', 'level' => 1, 'traits' => ['Fighter', 'Press'], 'prerequisites' => '',
        'benefit' => 'You make a controlled attack, fully accounting for your momentum. Make a melee Strike. It counts as two attacks when calculating your multiple attack penalty. If this Strike fails, you don\'t increase your multiple attack penalty.'],
      ['id' => 'point-blank-shot', 'name' => 'Point-Blank Shot', 'level' => 1, 'traits' => ['Fighter', 'Open', 'Stance'], 'prerequisites' => '',
        'benefit' => 'You take aim to pick off nearby enemies quickly. When using a ranged volley weapon while in this stance, you don\'t take the penalty for attacking within the weapon\'s volley range. When using a ranged weapon that doesn\'t have the volley trait, you gain a +2 circumstance bonus to damage rolls on attacks against targets within the weapon\'s first range increment.'],
      ['id' => 'power-attack', 'name' => 'Power Attack', 'level' => 1, 'traits' => ['Fighter', 'Flourish'], 'prerequisites' => '',
        'benefit' => 'You unleash a particularly powerful attack that clobbers your foe but leaves you a bit unbalanced. Make a melee Strike. This counts as two attacks when calculating your multiple attack penalty. If this Strike hits, you deal an extra die of weapon damage.'],
      ['id' => 'reactive-shield', 'name' => 'Reactive Shield', 'level' => 1, 'traits' => ['Fighter'], 'prerequisites' => '',
        'benefit' => 'Trigger: An enemy hits you with a melee Strike. You can snap your shield into place just as you would take a blow, avoiding the hit at the last second. You immediately use the Raise a Shield action and gain your shield\'s bonus to AC. The circumstance bonus applies to your AC when you\'re determining the outcome of the triggering attack.'],
      ['id' => 'snagging-strike', 'name' => 'Snagging Strike', 'level' => 1, 'traits' => ['Fighter'], 'prerequisites' => '',
        'benefit' => 'You combine an attack with quick grappling moves to throw an enemy off balance as long as it stays in your reach. Make a Strike while wielding a weapon with the two-hand trait, using only one hand. If this Strike hits and deals damage, the target is flat-footed until the start of your next turn.'],
    ],
    'rogue' => [
      ['id' => 'nimble-dodge', 'name' => 'Nimble Dodge', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '',
        'benefit' => 'Trigger: A creature targets you with an attack and you can see the attacker. You deftly dodge out of the way, gaining a +2 circumstance bonus to AC against the triggering attack.'],
      ['id' => 'trap-finder', 'name' => 'Trap Finder', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '',
        'benefit' => 'You have an intuitive sense that alerts you to the dangers and presence of traps. You gain a +1 circumstance bonus to Perception checks to find traps, to AC against attacks made by traps, and to saves against traps. You can find traps that require legendary proficiency in Perception. If you critically fail a check to Disable a Device on a trap, you don\'t trigger it.'],
      ['id' => 'twin-feint', 'name' => 'Twin Feint', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '',
        'benefit' => 'You make a dazzling series of attacks with both weapons, using the first attack to throw your foe off guard against a second attack. Make one Strike with each of your two melee weapons, both against the same target. The target is automatically flat-footed against the second attack.'],
      ['id' => 'you-re-next', 'name' => 'You\'re Next', 'level' => 1, 'traits' => ['Rogue', 'Emotion', 'Fear', 'Mental'], 'prerequisites' => '',
        'benefit' => 'Trigger: You reduce an enemy to 0 Hit Points. After downing a foe, you menace another to sow fear. Attempt an Intimidation check with a +2 circumstance bonus to Demoralize a single creature that you can see and that can see you. This creature doesn\'t need to be within 30 feet, but it must be able to perceive the creature you just killed.'],
      ['id' => 'eldritch-trickster-racket', 'name' => 'Eldritch Trickster Racket', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '', 'racket' => TRUE,
        'benefit' => 'You blend arcane power with criminal cunning. You gain a free multiclass spellcasting archetype dedication at 1st level. You can select the Magical Trickster feat at 2nd level (instead of 4th). Intelligence is your key ability score.'],
      ['id' => 'mastermind-racket', 'name' => 'Mastermind Racket', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '', 'racket' => TRUE,
        'benefit' => 'You use cunning deduction rather than brute force. Intelligence is your key ability score. You gain training in Society and one additional knowledge skill. When you successfully Recall Knowledge about a creature, it is flat-footed against your attacks until the start of your next turn. On a critical success, it is flat-footed for 1 minute.'],
    ],
    'wizard' => [
      ['id' => 'counterspell', 'name' => 'Counterspell', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'Trigger: A creature Casts a Spell that you have prepared. When a foe Casts a Spell and you can see its manifestations, you can use your own magic to counter it. You expend a prepared spell to counter the triggering creature\'s casting of that same spell. You lose your spell slot as if you had cast 

the triggering spell. You then attempt to counteract the triggering spell.'],
      ['id' => 'eschew-materials', 'name' => 'Eschew Materials', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'You can use clever workarounds to replicate the arcane essence of certain materials. When Casting a Spell that requires material components, you can provide these material components without a spell component pouch by drawing intricate replacement sigils in the air. Unlike when providing somatic components, you still must have a hand completely free. This doesn\'t remove the need for any materials listed in the spell\'s cost entry.'],
      ['id' => 'familiar', 'name' => 'Familiar', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'You make a pact with a creature that serves you and assists your spellcasting. You gain a familiar.'],
      ['id' => 'hand-of-the-apprentice', 'name' => 'Hand of the Apprentice', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => 'Universalist wizard',
        'benefit' => 'You can magically hurl your weapon at your foe. You gain the Hand of the Apprentice arcane school spell. If you don\'t already have one, you gain a focus pool of 1 Focus Point, which you can Refocus by studying your spellbook.'],
      ['id' => 'reach-spell', 'name' => 'Reach Spell', 'level' => 1, 'traits' => ['Concentrate', 'Metamagic', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'You extend your spell\'s range. If the next action you use is to Cast a Spell that has a range, increase that spell\'s range by 30 feet. As is standard for increasing spell ranges, if the spell normally has a range of touch, you extend its range to 30 feet.'],
      ['id' => 'widen-spell', 'name' => 'Widen Spell', 'level' => 1, 'traits' => ['Manipulate', 'Metamagic', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'You manipulate the energy of your spell, causing it to affect a wider area. If the next action you use is to Cast a Spell that has an area of a burst, cone, or line and doesn\'t have a duration, increase the area of that spell. Add 5 feet to the radius of a burst that normally has a radius of at least 10 feet (a burst with a smaller radius is not affected). Add 5 feet to the length of a cone or line that is normally 15 feet long or smaller, and add 10 feet to the length of a larger cone or line.'],
      ['id' => 'staff-nexus', 'name' => 'Staff Nexus Thesis', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => '', 'thesis' => TRUE,
        'benefit' => 'Your arcane thesis focuses on the creation and empowerment of magical staves. You begin play with a makeshift staff containing 1 cantrip and 1 first-level spell from your spellbook. The makeshift staff gains charges only by expending spell slots (1 slot = number of spell levels in charges). At 8th level you may expend 2 slots per day; at 16th level up to 3 slots. You can Craft the makeshift staff into any standard staff type at standard cost, retaining the two original spells.'],
    ],
    'ranger' => [
      ['id' => 'animal-companion', 'name' => 'Animal Companion', 'level' => 1, 'traits' => ['Ranger'], 'prerequisites' => '',
        'benefit' => 'You gain the service of a young animal companion that travels with you and obeys your commands. The rules for animal companions appear on page 214.'],
      ['id' => 'crossbow-ace', 'name' => 'Crossbow Ace', 'level' => 1, 'traits' => ['Ranger'], 'prerequisites' => '',
        'benefit' => 'Your extensive practice with the crossbow has helped you develop an eye for trajectory. When you use a crossbow, the Quick Draw action also reloads the crossbow. When your crossbow is loaded, you can reload without drawing weapon hand.'],
      ['id' => 'hunted-shot', 'name' => 'Hunted Shot', 'level' => 1, 'traits' => ['Flourish', 'Ranger'], 'prerequisites' => '',
        'benefit' => 'You carefully track a target and then launch two arrows in rapid succession. Make two Strikes against your prey with your ranged weapon, or one Strike if your weapon has the volley trait. If both hit, combine their damage for resistances and weaknesses. Apply your multiple attack penalty to both. This attack counts as two attacks for your multiple attack penalty.'],
      ['id' => 'monster-hunter', 'name' => 'Monster Hunter', 'level' => 1, 'traits' => ['Ranger'], 'prerequisites' => '',
        'benefit' => 'You swear to hunt down a specific type of creature. Choose one of the following monster types: aberration, animal, beast, construct, dragon, elemental, fey, fungus, giant, humanoid, ooze, or undead. You gain a +2 circumstance bonus to Recall Knowledge checks and Investigation checks against creatures with this trait.'],
      ['id' => 'twin-takedown', 'name' => 'Twin Takedown', 'level' => 1, 'traits' => ['Flourish', 'Ranger'], 'prerequisites' => '',
        'benefit' => 'You swiftly move from one opponent to the next. Make two Strikes, each against a different target and with a different weapon. The second Strike takes the normal multiple attack penalty, but the Double Slice ability applies.'],
    ],
    'bard' => [
      ['id' => 'lingering-composition', 'name' => 'Lingering Composition', 'level' => 1, 'traits' => ['Bard'], 'prerequisites' => '',
        'benefit' => 'By adding a flourish, you make your composition last longer. If your next action is to cast a cantrip composition with a duration of 1 round, attempt a Performance check. On a success, the composition lasts 3 rounds; on a critical success, 4 rounds.'],
      ['id' => 'martial-performance', 'name' => 'Martial Performance', 'level' => 1, 'traits' => ['Bard'], 'prerequisites' => 'Warrior muse',
        'benefit' => 'Your muse grants you skill in martial weaponry. You become trained in all martial weapons. When you use a composition spell that benefits your own attacks, the bonus also applies to attacks with martial weapons you are wielding.'],
      ['id' => 'warrior-muse', 'name' => 'Warrior Muse', 'level' => 1, 'traits' => ['Bard'], 'prerequisites' => '', 'muse' => TRUE,
        'benefit' => 'Your muse is a great hero, deity of battle, or embodiment of conflict. You are granted the Martial Performance feat at 1st level. You add fear to your spell repertoire at 1st level. At 2nd level you can take the Song of Strength feat (composition cantrip: grants all allies a +2 circumstance bonus to Athletics checks for the duration).'],
      ['id' => 'song-of-strength', 'name' => 'Song of Strength', 'level' => 2, 'traits' => ['Bard', 'Cantrip', 'Composition', 'Enchantment'], 'prerequisites' => 'Warrior muse',
        'benefit' => 'You inspire your allies to great feats of strength. You and all allies in a 60-foot emanation gain a +2 circumstance bonus to Athletics checks and Strength-based damage rolls for as long as you Sustain the Cantrip (up to 1 minute).'],
    ],
    'witch' => [
      // Basic Lessons (L2+)
      ['id' => 'lesson-of-dreams',      'name' => 'Basic Lesson: Dreams',      'level' => 2, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'basic', 'lesson' => 'dreams',
        'benefit' => 'You commune with dream spirits. You learn the veil-of-dreams hex. Your familiar learns sleep.'],
      ['id' => 'lesson-of-elements',    'name' => 'Basic Lesson: Elements',    'level' => 2, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'basic', 'lesson' => 'elements',
        'benefit' => 'You call on raw elemental power. You learn the elemental-betrayal hex. Your familiar learns your choice of burning hands, gust of wind, hydraulic push, or pummeling rubble.'],
      ['id' => 'lesson-of-life',        'name' => 'Basic Lesson: Life',        'level' => 2, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'basic', 'lesson' => 'life',
        'benefit' => 'You connect with life energy. You learn the life-boost hex. Your familiar learns spirit link.'],
      ['id' => 'lesson-of-protection',  'name' => 'Basic Lesson: Protection',  'level' => 2, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'basic', 'lesson' => 'protection',
        'benefit' => 'You conjure wards against harm. You learn the blood-ward hex. Your familiar learns mage armor.'],
      ['id' => 'lesson-of-vengeance',   'name' => 'Basic Lesson: Vengeance',   'level' => 2, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'basic', 'lesson' => 'vengeance',
        'benefit' => 'You call upon retribution. You learn the needle-of-vengeance hex. Your familiar learns phantom pain.'],
      // Greater Lessons (L6+)
      ['id' => 'lesson-of-mischief',    'name' => 'Greater Lesson: Mischief',  'level' => 6, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'greater', 'lesson' => 'mischief',
        'benefit' => 'You dabble in chaos. You learn the deceiver\'s-cloak hex. Your familiar learns mad monkeys.'],
      ['id' => 'lesson-of-shadow',      'name' => 'Greater Lesson: Shadow',    'level' => 6, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'greater', 'lesson' => 'shadow',
        'benefit' => 'You command shadow and darkness. You learn the malicious-shadow hex. Your familiar learns chilling darkness.'],
      ['id' => 'lesson-of-snow',        'name' => 'Greater Lesson: Snow',      'level' => 6, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'greater', 'lesson' => 'snow',
        'benefit' => 'You channel winter\'s fury. You learn the personal-blizzard hex. Your familiar learns wall of wind.'],
      // Major Lessons (L10+)
      ['id' => 'lesson-of-death',       'name' => 'Major Lesson: Death',       'level' => 10, 'traits' => ['Witch', 'Uncommon'], 'prerequisites' => '', 'lesson_tier' => 'major', 'lesson' => 'death',
        'benefit' => 'You peer into death itself. You learn the curse-of-death hex. Your familiar learns raise dead.'],
      ['id' => 'lesson-of-renewal',     'name' => 'Major Lesson: Renewal',     'level' => 10, 'traits' => ['Witch'], 'prerequisites' => '', 'lesson_tier' => 'major', 'lesson' => 'renewal',
        'benefit' => 'You channel renewal and rebirth. You learn the restorative-moment hex. Your familiar learns field of life.'],
    ],
  ];

  /**
   * PF2e Spells database (Cantrips and 1st level spells).
   * Organized by tradition (Arcane, Divine, Occult, Primal).
   */
  const SPELLS = [
    'arcane' => [
      // Cantrips (Level 0)
      'cantrips' => [
        ['id' => 'acid-splash', 'name' => 'Acid Splash', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Acid', 'Attack', 'Cantrip', 'Evocation'],
          'description' => 'You splash a glob of acid that deals 1d6 acid damage plus 1 splash damage. On a critical hit, the target takes 2 splash damage instead of 1.'],
        ['id' => 'chill-touch', 'name' => 'Chill Touch', 'level' => 0, 'school' => 'Necromancy', 'cast' => '2 actions', 'range' => 'touch', 'traits' => ['Cantrip', 'Necromancy', 'Negative'],
          'description' => 'Your touch does 1d4 negative damage and 1 persistent negative damage. The target\'s healing from positive energy is reduced by half until the persistent damage ends.'],
        ['id' => 'daze', 'name' => 'Daze', 'level' => 0, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '60 feet', 'traits' => ['Cantrip', 'Enchantment', 'Mental', 'Nonlethal'],
          'description' => 'You cloud the target\'s mind. The target must attempt a Will save. Success: 1d6 mental damage. Critical Failure: 4d6 mental damage and stunned 1.'],
        ['id' => 'detect-magic', 'name' => 'Detect Magic', 'level' => 0, 'school' => 'Divination', 'cast' => '2 actions', 'duration' => 'sustained', 'traits' => ['Cantrip', 'Detection', 'Divination'],
          'description' => 'You send out a pulse that registers the presence of magic. Detects magic auras within 30 feet and reveals their school and strength.'],
        ['id' => 'electric-arc', 'name' => 'Electric Arc', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Cantrip', 'Electricity', 'Evocation'],
          'description' => 'An arc of lightning leaps from you to up to two targets. Each target takes 1d4 electricity damage (basic Reflex save).'],
        ['id' => 'ghost-sound', 'name' => 'Ghost Sound', 'level' => 0, 'school' => 'Illusion', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Auditory', 'Cantrip', 'Illusion'],
          'description' => 'You create an auditory illusion of simple sounds. The sound can be as loud as four normal humans talking.'],
        ['id' => 'light', 'name' => 'Light', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => 'touch', 'duration' => 'until your next daily preparations', 'traits' => ['Cantrip', 'Evocation', 'Light'],
          'description' => 'The object glows, shedding bright light in a 20-foot radius (and dim light for the next 20 feet).'],
        ['id' => 'mage-hand', 'name' => 'Mage Hand', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => 'sustained', 'traits' => ['Cantrip', 'Evocation'],
          'description' => 'You create a floating, disembodied hand. It can manipulate objects (lift up to 1 Bulk, but can\'t attack).'],
        ['id' => 'prestidigitation', 'name' => 'Prestidigitation', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '10 feet', 'duration' => 'sustained', 'traits' => ['Cantrip', 'Evocation'],
          'description' => 'Simple magical effects: create harmless sensory effects, lift up to 1 Bulk, color/clean/soil objects, chill/warm/flavor food.'],
        ['id' => 'produce-flame', 'name' => 'Produce Flame', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Attack', 'Cantrip', 'Evocation', 'Fire'],
          'description' => 'A small ball of flame appears in your hand. You can throw it as a ranged attack that deals 1d4 fire damage plus 1 splash fire damage.'],
        ['id' => 'ray-of-frost', 'name' => 'Ray of Frost', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '120 feet', 'traits' => ['Attack', 'Cantrip', 'Cold', 'Evocation'],
          'description' => 'You blast an icy ray. The ray deals 1d4 cold damage. On a critical hit, the target is slowed 1 until the end of your next turn.'],
        ['id' => 'read-aura', 'name' => 'Read Aura', 'level' => 0, 'school' => 'Divination', 'cast' => '1 minute', 'traits' => ['Cantrip', 'Detection', 'Divination'],
          'description' => 'You study the aura of one object or creature to learn its magical, religious, or alignment qualities.'],
        ['id' => 'shield', 'name' => 'Shield', 'level' => 0, 'school' => 'Abjuration', 'cast' => '1 action', 'duration' => 'until the start of your next turn', 'traits' => ['Abjuration', 'Cantrip', 'Force'],
          'description' => 'You raise a magical shield. Gain a +1 circumstance bonus to AC. You can Shield Block with your shield spell (Hardness 5, 20 HP).'],
        ['id' => 'tanglefoot', 'name' => 'Tanglefoot', 'level' => 0, 'school' => 'Conjuration', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Attack', 'Cantrip', 'Conjuration'],
          'description' => 'A mass of sticky webbing clings to the target. The target takes a -10-foot status penalty to Speed for 1 round (critical hit: immobilized for 1 round then -10 Speed for 1 round).'],
        ['id' => 'telekinetic-projectile', 'name' => 'Telekinetic Projectile', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Attack', 'Cantrip', 'Evocation'],
          'description' => 'You hurl a loose object at the target. The object deals 1d6 bludgeoning, piercing, or slashing damage (your choice).'],
      ],
      // 1st Level Spells
      '1st' => [
        ['id' => 'burning-hands', 'name' => 'Burning Hands', 'level' => 1, 'school' => 'Evocation', 'cast' => '2 actions', 'area' => '15-foot cone', 'traits' => ['Evocation', 'Fire'],
          'description' => 'Gouts of flame rush from your hands. Creatures in the area take 2d6 fire damage (basic Reflex save).'],
        ['id' => 'charm', 'name' => 'Charm', 'level' => 1, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 hour', 'traits' => ['Emotion', 'Enchantment', 'Incapacitation', 'Mental'],
          'description' => 'The target views you as a good friend. They don\'t necessarily agree with everything you say, but they respond positively to you. Critical Success: The target is unaffected and aware you tried to charm it. Success: Unaffected. Failure: Attitude improves by one step. Critical Failure: Improves by two steps.'],
        ['id' => 'color-spray', 'name' => 'Color Spray', 'level' => 1, 'school' => 'Illusion', 'cast' => '2 actions', 'area' => '15-foot cone', 'traits' => ['Illusion', 'Incapacitation', 'Visual'],
          'description' => 'Vivid colors overwhelm creatures in the area. Each creature must attempt a Will save. Critical Success: Unaffected. Success: Dazzled until the end of your next turn. Failure: Stunned 1, blinded and dazzled for 1 round. Critical Failure: Stunned for 1 round and blinded for 1 minute.'],
        ['id' => 'fear', 'name' => 'Fear', 'level' => 1, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Emotion', 'Enchantment', 'Fear', 'Mental'],
          'description' => 'You plant fear in the target. It must attempt a Will save. Critical Success: Unaffected. Success: Frightened 1. Failure: Frightened 2. Critical Failure: Frightened 3 and fleeing for 1 round.'],
        ['id' => 'grease', 'name' => 'Grease', 'level' => 1, 'school' => 'Conjuration', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 minute', 'traits' => ['Conjuration'],
          'description' => 'You conjure grease in a 10-foot square. Creatures entering or standing in the grease must succeed at Acrobatics check (DC = spell DC) or fall prone. A creature can avoid this by Balancing through it.'],
        ['id' => 'mage-armor', 'name' => 'Mage Armor', 'level' => 1, 'school' => 'Abjuration', 'cast' => '2 actions', 'duration' => 'until your next daily preparations', 'traits' => ['Abjuration', 'Force'],
          'description' => 'You ward yourself with shimmering magical energy, gaining a +1 item bonus to AC and a +1 item bonus to saves against magic missiles. While wearing mage armor, you use your unarmored proficiency.'],
        ['id' => 'magic-missile', 'name' => 'Magic Missile', 'level' => 1, 'school' => 'Evocation', 'cast' => '1 to 3 actions', 'range' => '120 feet', 'traits' => ['Evocation', 'Force'],
          'description' => 'You send a dart of force streaking toward a creature. The dart automatically hits and deals 1d4+1 force damage. If you Cast this Spell using 2 actions, create two darts. If you Cast this Spell using 3 actions, create three darts.'],
        ['id' => 'ray-of-enfeeblement', 'name' => 'Ray of Enfeeblement', 'level' => 1, 'school' => 'Necromancy', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 minute', 'traits' => ['Attack', 'Necromancy'],
          'description' => 'A ray that saps the target\'s strength. The target takes a -2 status penalty to Strength-based attack rolls, damage rolls, Athletics checks, and Strength-based skill checks.'],
        ['id' => 'shocking-grasp', 'name' => 'Shocking Grasp', 'level' => 1, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => 'touch', 'traits' => ['Attack', 'Electricity', 'Evocation'],
          'description' => 'You shroud your hands in a crackling field of lightning. Make a melee spell attack. On a hit, the target takes 2d12 electricity damage. If the target is wearing metal armor or is made of metal, you gain a +1 circumstance bonus to your attack roll with shocking grasp.'],
        ['id' => 'sleep', 'name' => 'Sleep', 'level' => 1, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 minute', 'traits' => ['Enchantment', 'Incapacitation', 'Mental', 'Sleep'],
          'description' => 'Each creature in a 5-foot burst must attempt a Will save. Critical Success: Unaffected. Success: -1 status penalty to Perception checks for identifying creatures until the end of your next turn. Failure: Falls unconscious. Critical Failure: Falls unconscious for 1 minute.'],
        ['id' => 'true-strike', 'name' => 'True Strike', 'level' => 1, 'school' => 'Divination', 'cast' => '1 action', 'duration' => 'until the end of your turn', 'traits' => ['Divination', 'Fortune'],
          'description' => 'A glimpse into the future ensures your next blow strikes true. The next attack roll you make before the end of your turn gains a +10 circumstance bonus.'],
      ],
    ],
  ];

  /**
   * Maps caster classes to their spellcasting tradition.
   * Used to look up spells from the registry by tradition tag.
   * Non-caster classes are not listed here.
   */
  const CLASS_TRADITIONS = [
    'wizard'   => 'arcane',
    'cleric'   => 'divine',
    'bard'     => 'occult',
    'druid'    => 'primal',
    'sorcerer' => NULL,   // Sorcerer picks a tradition via bloodline.
    'oracle'   => 'divine',
    'witch'    => NULL,   // Witch picks via patron; default occult.
  ];

  /**
   * Sorcerer bloodline → tradition mapping.
   */
  const SORCERER_BLOODLINES = [
    'aberrant'    => ['tradition' => 'occult',  'label' => 'Aberrant',    'description' => 'Something extradimensional warped your lineage, granting occult power.'],
    'angelic'     => ['tradition' => 'divine',  'label' => 'Angelic',     'description' => 'Celestial blood flows through you, granting divine spellcasting.'],
    'demonic'     => ['tradition' => 'divine',  'label' => 'Demonic',     'description' => 'Fiendish ancestry grants you raw divine power twisted toward destruction.'],
    'draconic'    => ['tradition' => 'arcane',  'label' => 'Draconic',    'description' => 'The blood of dragons flows through your veins, granting arcane mastery.'],
    'elemental'   => ['tradition' => 'primal',  'label' => 'Elemental',   'description' => 'Elemental forces surge within you, granting primal spellcasting.'],
    'fey'         => ['tradition' => 'primal',  'label' => 'Fey',         'description' => 'Fey creatures somewhere in your lineage grant primal power.'],
    'hag'         => ['tradition' => 'occult',  'label' => 'Hag',         'description' => 'A hag ancestor grants you occult spellcasting.'],
    'imperial'    => ['tradition' => 'arcane',  'label' => 'Imperial',    'description' => 'Your bloodline carries arcane power from ancient rulers or conquerors.'],
    'undead'      => ['tradition' => 'divine',  'label' => 'Undead',      'description' => 'Undead taint in your lineage grants you divine necromantic power.'],
    'genie'       => ['tradition' => 'arcane',  'label' => 'Genie',       'description' => 'Elemental genie power flows in your blood. Choose a subtype at 1st level: Janni, Djinni, Efreeti, Marid, or Shaitan — each determines certain granted spells.', 'subtype_required' => TRUE, 'subtypes' => ['janni', 'djinni', 'efreeti', 'marid', 'shaitan']],
    'nymph'       => ['tradition' => 'primal',  'label' => 'Nymph',       'description' => 'A nymph ancestor grants you primal connection to natural beauty and elemental forces.'],
  ];

  /**
   * Witch patron → tradition mapping.
   */
  const WITCH_PATRONS = [
    'curse'   => ['tradition' => 'occult',  'label' => 'Curse',   'patron_skill' => 'Occultism',  'hex_cantrip' => 'evil-eye',          'granted_spell' => 'phantom-pain',      'description' => 'Your patron embodies curses and misfortune, granting occult power.'],
    'fate'    => ['tradition' => 'occult',  'label' => 'Fate',    'patron_skill' => 'Occultism',  'hex_cantrip' => 'nudge-fate',        'granted_spell' => 'augury',            'description' => 'Your patron sees and manipulates the threads of fate.'],
    'fervor'  => ['tradition' => 'divine',  'label' => 'Fervor',  'patron_skill' => 'Religion',   'hex_cantrip' => 'stoke-the-heart',   'granted_spell' => 'zealous-conviction', 'description' => 'Your patron is a divine being of zealous conviction.'],
    'night'   => ['tradition' => 'occult',  'label' => 'Night',   'patron_skill' => 'Stealth',    'hex_cantrip' => 'shroud-of-night',   'granted_spell' => 'sleep',             'description' => 'Darkness and shadow are your patron\'s domain.'],
    'rune'    => ['tradition' => 'arcane',  'label' => 'Rune',    'patron_skill' => 'Arcana',     'hex_cantrip' => 'discern-secrets',   'granted_spell' => 'magic-missile',     'description' => 'Your patron commands the power of arcane runes.'],
    'wild'    => ['tradition' => 'primal',  'label' => 'Wild',    'patron_skill' => 'Nature',     'hex_cantrip' => 'wilding-word',      'granted_spell' => 'natures-enmity',    'description' => 'Nature and the wild are your patron\'s domain.'],
    'winter'  => ['tradition' => 'primal',  'label' => 'Winter',  'patron_skill' => 'Nature',     'hex_cantrip' => 'clinging-ice',      'granted_spell' => 'gust-of-wind',      'description' => 'The cold power of winter flows through your patron.'],
  ];

  /**
   * Cantrip and 1st-level spell slot counts at level 1 for each caster class.
   */
  const CASTER_SPELL_SLOTS = [
    'wizard'   => ['cantrips' => 5, 'first' => 2, 'spellbook' => 10],
    'cleric'   => ['cantrips' => 5, 'first' => 2],
    'bard'     => ['cantrips' => 5, 'first' => 2],
    'druid'    => ['cantrips' => 5, 'first' => 2],
    'sorcerer' => ['cantrips' => 5, 'first' => 3],
    'oracle'   => ['cantrips' => 5, 'first' => 2, 'focus_pool_start' => 2],
    'witch'    => ['cantrips' => 5, 'first' => 1, 'familiar_cantrips' => 10, 'familiar_spells' => 5, 'familiar_model' => TRUE],
  ];

  /**
   * Witch hex focus spells and cantrips.
   * Hexes are focus spells (cost 1 FP). Hex cantrips are free (no FP cost).
   * Only one hex (regular or cantrip) may be cast per turn.
   */
  const WITCH_HEXES = [
    'hex_cantrips' => [
      ['id' => 'evil-eye',         'name' => 'Evil Eye',         'traits' => ['Hex', 'Cantrip', 'Curse', 'Emotion', 'Fear', 'Mental', 'Occult'], 'free' => TRUE,
        'sustain' => TRUE, 'will_save_ends' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'Imposes a –2 status penalty to a target\'s AC (sustained). Ends early if the target succeeds at a Will save. Auto-heightens to half witch level rounded up.'],
      ['id' => 'nudge-fate',       'name' => 'Nudge Fate',       'traits' => ['Hex', 'Cantrip', 'Divination', 'Fortune', 'Occult'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'You subtly alter fate. One creature within 30 feet must reroll its next attack roll or saving throw and use the worse result (sustained).'],
      ['id' => 'stoke-the-heart',  'name' => 'Stoke the Heart',  'traits' => ['Hex', 'Cantrip', 'Divine', 'Emotion', 'Enchantment', 'Mental'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'You fill an ally with zeal. The target gains a +1 status bonus to attack rolls and weapon damage rolls (sustained up to 1 minute).'],
      ['id' => 'shroud-of-night',  'name' => 'Shroud of Night',  'traits' => ['Hex', 'Cantrip', 'Darkness', 'Occult'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'You create a cloak of darkness around a target (sustained). The target becomes concealed in dim light or darkness.'],
      ['id' => 'discern-secrets',  'name' => 'Discern Secrets',  'traits' => ['Hex', 'Cantrip', 'Arcane', 'Divination', 'Revelation'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'You reveal one hidden secret about a target creature or object within 30 feet (sustained).'],
      ['id' => 'wilding-word',     'name' => 'Wilding Word',     'traits' => ['Hex', 'Cantrip', 'Enchantment', 'Mental', 'Primal'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'You speak to animals or plants (sustained). They react favorably to you and may perform simple tasks.'],
      ['id' => 'clinging-ice',     'name' => 'Clinging Ice',     'traits' => ['Hex', 'Cantrip', 'Attack', 'Cold', 'Primal'], 'free' => TRUE,
        'sustain' => TRUE, 'one_hex_per_turn' => TRUE, 'auto_heighten' => 'half_level_rounded_up',
        'description' => 'Ice clings to a target on a spell attack, dealing 1d4 cold damage and imposing a –10-foot status penalty to Speed (sustained).'],
    ],
    'regular_hexes' => [
      ['id' => 'cackle',         'name' => 'Cackle',         'action_cost' => 1, 'traits' => ['Hex', 'Concentrate'], 'fp_cost' => 0, 'one_hex_per_turn' => TRUE,
        'requires_active_hex' => TRUE,
        'free_action_feat_required' => TRUE,
        'description' => 'You cackle to extend another active hex\'s duration by 1 round. Requires an active sustained hex — fails gracefully if none. This is a free action only when unlocked by a feat; system checks for feat before allowing free-action trigger.'],
      ['id' => 'phase-familiar', 'name' => 'Phase Familiar', 'action_cost' => 'reaction', 'trigger' => 'Familiar would take damage', 'traits' => ['Hex', 'Abjuration', 'Reaction'], 'fp_cost' => 1, 'one_hex_per_turn' => TRUE,
        'incorporeal_brief' => TRUE,
        'description' => 'Your familiar briefly becomes incorporeal, negating the triggering damage entirely. Incorporeal state is brief (one instance of damage negated); does not persist between uses.'],
      ['id' => 'veil-of-dreams',       'name' => 'Veil of Dreams',       'action_cost' => 2, 'traits' => ['Hex', 'Enchantment', 'Mental', 'Sleep'], 'fp_cost' => 1,  'lesson' => 'dreams', 'one_hex_per_turn' => TRUE,
        'description' => 'Target must succeed at a Will save or become drowsy (–2 status to Perception; critical failure: also slowed 1).'],
      ['id' => 'elemental-betrayal',   'name' => 'Elemental Betrayal',   'action_cost' => 2, 'traits' => ['Hex', 'Divination'], 'fp_cost' => 1,  'lesson' => 'elements', 'one_hex_per_turn' => TRUE,
        'description' => 'Target becomes vulnerable to a chosen element: next attack with that damage type gains +2 circumstance bonus to damage.'],
      ['id' => 'life-boost',           'name' => 'Life Boost',           'action_cost' => 1, 'traits' => ['Hex', 'Healing', 'Positive'], 'fp_cost' => 1,  'lesson' => 'life', 'one_hex_per_turn' => TRUE,
        'description' => 'You channel healing energy. Target regains 1d6+4 HP (scales with level).'],
      ['id' => 'blood-ward',           'name' => 'Blood Ward',           'action_cost' => 2, 'traits' => ['Hex', 'Abjuration'], 'fp_cost' => 1,  'lesson' => 'protection', 'one_hex_per_turn' => TRUE,
        'description' => 'You protect a target from a specific damage type. Target gains +1 circumstance bonus to AC and saves against the chosen damage type until your next turn.'],
      ['id' => 'needle-of-vengeance',  'name' => 'Needle of Vengeance',  'action_cost' => 1, 'traits' => ['Hex', 'Attack', 'Curse', 'Necromancy'], 'fp_cost' => 1,  'lesson' => 'vengeance', 'one_hex_per_turn' => TRUE,
        'description' => 'A psychic needle impales the target. If the target attacks your ally before your next turn, it takes 2d6 mental damage.'],
      ['id' => 'deceivers-cloak',      'name' => 'Deceiver\'s Cloak',    'action_cost' => 2, 'traits' => ['Hex', 'Illusion', 'Mental'], 'fp_cost' => 1,  'lesson' => 'mischief', 'one_hex_per_turn' => TRUE,
        'description' => 'The target appears as a different creature for the duration (Will save to see through). Lasts until the target attacks or casts.'],
      ['id' => 'malicious-shadow',     'name' => 'Malicious Shadow',     'action_cost' => 2, 'traits' => ['Hex', 'Attack', 'Shadow'], 'fp_cost' => 1,  'lesson' => 'shadow', 'one_hex_per_turn' => TRUE,
        'description' => 'Target\'s shadow becomes your weapon. Shadow attack deals 2d6 cold damage on a hit (spell attack roll).'],
      ['id' => 'personal-blizzard',    'name' => 'Personal Blizzard',    'action_cost' => 2, 'traits' => ['Hex', 'Cold', 'Evocation', 'Primal'], 'fp_cost' => 1,  'lesson' => 'snow', 'one_hex_per_turn' => TRUE,
        'description' => 'Blizzard surrounds target (Basic Reflex save for 4d6 cold). While sustained, target is buffeted (-2 penalty to ranged attacks).'],
      ['id' => 'curse-of-death',       'name' => 'Curse of Death',       'action_cost' => 2, 'traits' => ['Hex', 'Curse', 'Death', 'Necromancy'], 'fp_cost' => 1,  'lesson' => 'death', 'one_hex_per_turn' => TRUE,
        'description' => 'Target must succeed at a Fortitude save or gain the Doomed 1 condition. On a critical failure, Doomed 2 and a –1 status penalty to all saving throws.'],
      ['id' => 'restorative-moment',   'name' => 'Restorative Moment',   'action_cost' => 2, 'traits' => ['Hex', 'Healing', 'Positive', 'Primal'], 'fp_cost' => 1,  'lesson' => 'renewal', 'one_hex_per_turn' => TRUE,
        'description' => 'Touched target regains HP equal to twice your spellcasting modifier and is no longer Sickened 1.'],
    ],
  ];

  /**
   * Oracle mysteries with curse progressions and revelation focus spells (APG).
   *
   * Each mystery defines:
   *   - initial_revelation: rank-1 focus spell (cursed; cost 1 FP)
   *   - advanced_revelation: rank-3 focus spell (cursed; cost 1 FP)
   *   - greater_revelation: rank-7 focus spell (cursed; cost 1 FP)
   *   - curse_stages: 4 stages (basic/minor/moderate/major) — unique per mystery
   *
   * All revelation spells have the Cursebound trait; casting one advances the
   * curse stage tracker. The curse is unique to each mystery (not a shared condition).
   */
  const ORACLE_MYSTERIES = [
    'ancestors' => [
      'id'          => 'ancestors',
      'name'        => 'Ancestors',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'ancestral-touch', 'name' => 'Ancestral Touch',
        'traits' => ['Cursebound', 'Divine', 'Necromancy', 'Revelation'],
        'description' => 'You touch a creature, channeling an ancestor\'s power: deal 1d4 negative damage and impose –1 status penalty to saves (Will negates). Scales with heightening.',
      ],
      'advanced_revelation' => [
        'id' => 'ancestral-defense', 'name' => 'Ancestral Defense',
        'traits' => ['Cursebound', 'Divine', 'Necromancy', 'Revelation'],
        'description' => 'You draw the protection of an ancestor. Target gains resistance 5 to all damage for 1 round.',
      ],
      'greater_revelation' => [
        'id' => 'ancestral-form', 'name' => 'Ancestral Form',
        'traits' => ['Cursebound', 'Divine', 'Morph', 'Necromancy', 'Revelation', 'Transmutation'],
        'description' => 'You briefly manifest in ancestral form, gaining the incorporeal trait, fly speed 30 ft, and +2 status to AC for 1 round.',
      ],
      'curse_stages' => [
        'basic'    => 'Your ancestors whisper constantly. –1 status to Perception checks.',
        'minor'    => 'Ancestors grow insistent. –1 status to initiative rolls and –1 to skill checks until end of next turn each time you cast a spell.',
        'moderate' => 'Ancestors overwhelm your senses. Fatigued condition while this stage persists.',
        'major'    => 'Ancestors take partial control. After each spell, roll 1d4: 1–2 you are stunned 1; 3–4 you act normally.',
      ],
    ],
    'battle' => [
      'id'          => 'battle',
      'name'        => 'Battle',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'battlefield-persistence', 'name' => 'Battlefield Persistence',
        'traits' => ['Cursebound', 'Divine', 'Revelation', 'Transmutation'],
        'description' => 'You stand firm against blows. You gain resistance 2 to all physical damage until the start of your next turn. Scales with heightening.',
      ],
      'advanced_revelation' => [
        'id' => 'weapon-surge', 'name' => 'Weapon Surge',
        'traits' => ['Cursebound', 'Divine', 'Revelation', 'Transmutation'],
        'description' => 'One weapon you hold becomes a +1 striking weapon for 1 minute.',
      ],
      'greater_revelation' => [
        'id' => 'divine-immolation', 'name' => 'Divine Immolation',
        'traits' => ['Cursebound', 'Divine', 'Revelation', 'Transmutation'],
        'description' => 'You are suffused with divine combat energy. Gain the effects of haste and +2 status to weapon damage rolls for 1 minute.',
      ],
      'curse_stages' => [
        'basic'    => 'The battle calls to you. –1 status to Stealth checks and Diplomacy checks.',
        'minor'    => 'You hear clashing blades. Must succeed at a DC 14 flat check or become distracted when using Recall Knowledge.',
        'moderate' => 'The battle rage seizes you. At the start of each turn, you must use a Strike or Stride toward the nearest foe.',
        'major'    => 'Battle fully possesses you. You are quickened but must use the extra action to Strike; you can\'t voluntarily retreat.',
      ],
    ],
    'bones' => [
      'id'          => 'bones',
      'name'        => 'Bones',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'soul-siphon', 'name' => 'Soul Siphon',
        'traits' => ['Cursebound', 'Divine', 'Necromancy', 'Revelation'],
        'description' => 'Deal 1d6 negative damage to a target within 30 feet (Fortitude halves). You regain HP equal to half the damage dealt.',
      ],
      'advanced_revelation' => [
        'id' => 'death-s-call', 'name' => 'Death\'s Call',
        'traits' => ['Cursebound', 'Death', 'Divine', 'Necromancy', 'Revelation'],
        'description' => 'Target must succeed at a Fortitude save or gain the Doomed 1 condition for 1 minute (critical failure: Doomed 2).',
      ],
      'greater_revelation' => [
        'id' => 'undying-form', 'name' => 'Undying Form',
        'traits' => ['Cursebound', 'Divine', 'Necromancy', 'Revelation'],
        'description' => 'You temporarily assume a deathly form. Gain negative healing, resistance 10 to negative damage, and immunity to the paralyzed condition for 1 minute.',
      ],
      'curse_stages' => [
        'basic'    => 'Death lingers about you. Living creatures adjacent to you take –1 circumstance penalty to saves vs. fear.',
        'minor'    => 'Your flesh grows pallid and cold. –2 circumstance penalty to Deception and Diplomacy checks with living creatures.',
        'moderate' => 'Half your face becomes skeletal. Allies must succeed at a DC 10 flat check or become frightened 1 when they first see you each combat.',
        'major'    => 'You oscillate between life and unlife. At the start of each turn roll 1d6: on 1–2, take 1d6 negative damage; on 3–6, regain 1d6 HP.',
      ],
    ],
    'cosmos' => [
      'id'          => 'cosmos',
      'name'        => 'Cosmos',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'spray-of-stars', 'name' => 'Spray of Stars',
        'traits' => ['Cursebound', 'Divine', 'Evocation', 'Fire', 'Light', 'Revelation'],
        'description' => 'You spray a burst of starlight in a 15-foot cone. Each creature takes 1d4 fire damage (Basic Reflex). Scales with heightening.',
      ],
      'advanced_revelation' => [
        'id' => 'interstellar-void', 'name' => 'Interstellar Void',
        'traits' => ['Cursebound', 'Cold', 'Divine', 'Evocation', 'Revelation'],
        'description' => 'The void between stars tears at a target. Deal 2d6 cold damage (Fortitude halves) and impose the slowed 1 condition on a critical failure.',
      ],
      'greater_revelation' => [
        'id' => 'moonlight-bridge', 'name' => 'Moonlight Bridge',
        'traits' => ['Cursebound', 'Conjuration', 'Divine', 'Light', 'Revelation', 'Teleportation'],
        'description' => 'You create a shimmering bridge of moonlight (30 ft, 5 ft wide) for 1 minute. Creatures on it gain a fly speed of 30 ft and are concealed from darkness-based attacks.',
      ],
      'curse_stages' => [
        'basic'    => 'Stars appear around your head. You gain a +1 status bonus to Astronomy-related Recall Knowledge but –1 to Intimidation.',
        'minor'    => 'Your eyes shine like stars. You are dazzled in bright light; you gain low-light vision in dim light.',
        'moderate' => 'Cosmic energy consumes your attention. –2 status penalty to Perception checks for creatures within 30 ft.',
        'major'    => 'The cosmos speaks through you. At the start of your turn roll 1d6: 1–3 you are blinded for 1 round; 4–6 you gain +2 status to spell attack rolls for 1 round.',
      ],
    ],
    'flames' => [
      'id'          => 'flames',
      'name'        => 'Flames',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'incendiary-aura', 'name' => 'Incendiary Aura',
        'traits' => ['Cursebound', 'Divine', 'Evocation', 'Fire', 'Revelation'],
        'description' => 'You emit a 10-foot aura of flame until the start of your next turn. Creatures that enter or start their turn in the aura take 1d6 fire damage (Basic Reflex).',
      ],
      'advanced_revelation' => [
        'id' => 'whirling-flames', 'name' => 'Whirling Flames',
        'traits' => ['Cursebound', 'Divine', 'Evocation', 'Fire', 'Revelation'],
        'description' => 'Flames swirl around you in a 15-ft burst. Each creature in the area takes 2d6 fire damage (Basic Reflex); on a critical failure, target also catches fire (persistent fire 1d4).',
      ],
      'greater_revelation' => [
        'id' => 'flames-oracle-form', 'name' => 'Form of the Flames',
        'traits' => ['Cursebound', 'Divine', 'Evocation', 'Fire', 'Morph', 'Revelation', 'Transmutation'],
        'description' => 'You temporarily become a being of fire. Gain immunity to fire, fire resistance 15, and deal 2d6 fire splash damage to adjacent creatures hit by your Strikes for 1 minute.',
      ],
      'curse_stages' => [
        'basic'    => 'Flames flicker around your hands. +1 status to fire damage but –1 AC against cold attacks.',
        'minor'    => 'Fire crackles in your eyes. Gain fire resistance 5 but cold damage you take is increased by 2.',
        'moderate' => 'You emit smoke and heat. Creatures adjacent to you must succeed at a DC 12 flat check or become sickened 1 from smoke.',
        'major'    => 'You are partially aflame. At the start of each turn, adjacent creatures take 1d6 fire damage (no save); you also take 1d6 fire damage.',
      ],
    ],
    'life' => [
      'id'          => 'life',
      'name'        => 'Life',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'life-link', 'name' => 'Life Link',
        'traits' => ['Cursebound', 'Divine', 'Healing', 'Necromancy', 'Positive', 'Revelation'],
        'description' => 'Form a temporary life-link with a willing creature within 30 ft. While the link persists, when that creature would die, you can spend a reaction to transfer the killing blow\'s damage to yourself.',
      ],
      'advanced_revelation' => [
        'id' => 'delay-affliction', 'name' => 'Delay Affliction',
        'traits' => ['Cursebound', 'Divine', 'Healing', 'Necromancy', 'Revelation'],
        'description' => 'Touch a creature afflicted with a disease or poison. Suspend the affliction for 1 day (does not progress, does not recover naturally).',
      ],
      'greater_revelation' => [
        'id' => 'life-oracle-font', 'name' => 'Heaven\'s Thunder',
        'traits' => ['Cursebound', 'Divine', 'Healing', 'Necromancy', 'Positive', 'Revelation'],
        'description' => 'Release a torrent of life energy in a 30-foot burst. Living allies regain 4d6+10 HP; undead in the area take 4d6+10 positive damage (Basic Fortitude).',
      ],
      'curse_stages' => [
        'basic'    => 'Life force bleeds from you. You gain a +2 status bonus to Healing skill checks but –1 HP per minute of combat.',
        'minor'    => 'Your healing overflows. Each time you restore HP to another creature, you take 1 persistent bleed damage.',
        'moderate' => 'You are suffused with life. At the start of each turn, you automatically attempt to counteract any disease or poison on yourself (counteract level = half your level).',
        'major'    => 'You are overwhelmed by life energy. At the start of each turn, roll 1d4: on 1, take 2d6 positive damage (yes, too much life hurts); on 2–4, regain 1d6 HP.',
      ],
    ],
    'lore' => [
      'id'          => 'lore',
      'name'        => 'Lore',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'brain-drain', 'name' => 'Brain Drain',
        'traits' => ['Cursebound', 'Divine', 'Divination', 'Mental', 'Revelation'],
        'description' => 'Force a creature to share its knowledge. Target takes 1d6 mental damage (Will halves) and you learn a piece of knowledge it holds (GM adjudicates).',
      ],
      'advanced_revelation' => [
        'id' => 'the-lore-oracle-sight', 'name' => 'Ancestral Clairvoyance',
        'traits' => ['Cursebound', 'Detection', 'Divine', 'Divination', 'Revelation'],
        'description' => 'Your senses extend. You gain tremorsense 15 ft, darkvision, and +2 to all Perception checks for 1 minute.',
      ],
      'greater_revelation' => [
        'id' => 'dread-secret', 'name' => 'Dread Secret',
        'traits' => ['Cursebound', 'Divine', 'Divination', 'Emotion', 'Fear', 'Mental', 'Revelation'],
        'description' => 'You tear a terrible secret from the universe and speak it aloud. All creatures within 60 ft that can hear you must succeed at a Will save or become frightened 2 (critical failure: frightened 4 + fleeing for 1 round).',
      ],
      'curse_stages' => [
        'basic'    => 'Forbidden knowledge intrudes. +2 status to Recall Knowledge checks; –1 penalty to Will saves vs. mental effects.',
        'minor'    => 'Whispers fill your head. –2 status penalty to Perception; +2 status to all Recall Knowledge checks.',
        'moderate' => 'Lore overwhelms you. At the start of each turn, roll 1d6: on 1–2, you are confused until the start of your next turn.',
        'major'    => 'You know too much. At the start of each turn, you must succeed at a DC 20 Will save or share one of your active secrets with the GM (mechanic: GM may reveal a held piece of info to foes for 1 round).',
      ],
    ],
    'tempest' => [
      'id'          => 'tempest',
      'name'        => 'Tempest',
      'tradition'   => 'divine',
      'initial_revelation' => [
        'id' => 'tempest-touch', 'name' => 'Tempest Touch',
        'traits' => ['Cursebound', 'Divine', 'Electricity', 'Revelation', 'Transmutation'],
        'description' => 'Your touch crackles with lightning. Deal 1d4 electricity damage + 1d4 sonic damage (Basic Fortitude) to a touched target. Scales with heightening.',
      ],
      'advanced_revelation' => [
        'id' => 'lightning-form', 'name' => 'Lightning Form',
        'traits' => ['Cursebound', 'Divine', 'Electricity', 'Morph', 'Revelation', 'Transmutation'],
        'description' => 'Partially transform into lightning. Gain electricity resistance 10, a 10-ft-wide line of electricity (1d6 per 2 levels) as a free action once per turn, and fly speed 30 ft for 1 minute.',
      ],
      'greater_revelation' => [
        'id' => 'tempest-form', 'name' => 'Form of the Tempest',
        'traits' => ['Cursebound', 'Divine', 'Electricity', 'Morph', 'Revelation', 'Sonic', 'Transmutation'],
        'description' => 'Fully become a storm. Gain immunity to electricity and sonic, Fly speed 60 ft, and when hit by a melee attack the attacker takes 1d12 electricity damage (no save) for 1 minute.',
      ],
      'curse_stages' => [
        'basic'    => 'Static crackles from your hair. +1 status to electricity spell damage; –1 status to Stealth and Thievery checks.',
        'minor'    => 'Wind roars around you. Ranged attacks against you take a –1 circumstance penalty; ranged attacks you make take a –1 circumstance penalty.',
        'moderate' => 'Lightning dances across your skin. Creatures that hit you with a metal weapon take 1d6 electricity damage (no save).',
        'major'    => 'You become a storm. At the start of each turn, roll 1d4: on 1–2, a random creature within 30 ft takes 2d6 electricity damage; on 3–4, you gain +2 status to spell attack rolls for 1 round.',
      ],
    ],
  ];

  /**
   * Bard APG composition focus spells (Advanced Player's Guide).
   *
   * These are composition spells granted by APG bard feats (e.g., Warrior Muse
   * and associated feats). All cost 1 Focus Point and are composition spells.
   * Song of Strength's circumstance bonus does not stack with other circumstance
   * bonuses to Athletics.
   */
  const BARD_FOCUS_SPELLS = [
    'hymn-of-healing' => [
      'id'          => 'hymn-of-healing',
      'name'        => 'Hymn of Healing',
      'type'        => 'composition',
      'action_cost' => 2,
      'fp_cost'     => 1,
      'sustain'     => TRUE,
      'traits'      => ['Composition', 'Focus', 'Healing', 'Occult'],
      'healing'     => ['per_round' => '2 HP', 'heighten_scaling' => TRUE],
      'description' => 'A sustained composition focus spell. Heals 2 HP per round while sustained. Scales with spell heightening (additional HP per rank above base).',
    ],
    'song-of-strength' => [
      'id'          => 'song-of-strength',
      'name'        => 'Song of Strength',
      'type'        => 'composition',
      'action_cost' => 2,
      'fp_cost'     => 1,
      'traits'      => ['Composition', 'Emotion', 'Enchantment', 'Focus', 'Mental', 'Occult'],
      'bonus'       => [
        'type'       => 'circumstance',
        'stat'       => 'Athletics',
        'value'      => 2,
        'stacking'   => FALSE,
        'stack_note' => 'Circumstance bonus — does not stack with other circumstance bonuses to Athletics.',
      ],
      'description' => 'Grants all allies in the area a +2 circumstance bonus to Athletics checks for the duration. Circumstance bonuses do not stack.',
    ],
    'gravity-weapon' => [
      'id'          => 'gravity-weapon',
      'name'        => 'Gravity Weapon',
      'type'        => 'composition',
      'action_cost' => 1,
      'fp_cost'     => 1,
      'traits'      => ['Composition', 'Focus', 'Occult', 'Transmutation'],
      'bonus'       => [
        'type'              => 'status',
        'stat'              => 'weapon damage',
        'value_source'      => 'number_of_weapon_damage_dice',
        'value_note'        => 'Status bonus to damage = number of weapon damage dice (e.g., a 2d6 weapon grants +2). Doubles vs. Large or larger targets (+4 in that case).',
        'doubles_vs_large'  => TRUE,
      ],
      'description' => 'A status bonus to damage equal to the weapon\'s damage dice count. Doubles against Large or larger targets. Damage-dice count sourced from the weapon\'s damage dice (a 2d6 weapon grants +2; vs. Large+ grants +4).',
    ],
  ];

  /**
   * Ranger Warden Spells (APG focus spells).
   *
   * Warden spells use the ranger's primal focus pool.
   * Refocus activity: 10 minutes spent in nature.
   * Warden spell effects are terrain-based or creature-type bonuses per spell.
   */
  const RANGER_WARDEN_SPELLS = [
    'pool' => [
      'tradition'      => 'primal',
      'refocus_method' => '10 minutes spent in nature',
      'pool_shared'    => TRUE,
      'pool_note'      => 'Warden spells draw from the same primal focus pool as other ranger focus spells. Refocus in nature counts toward the general focus pool (same FP pool, different activity name).',
    ],
    'spells' => [
      'animal-form' => [
        'id'          => 'animal-form',
        'name'        => 'Animal Form (Warden)',
        'action_cost' => 2,
        'fp_cost'     => 1,
        'traits'      => ['Focus', 'Morph', 'Polymorph', 'Primal', 'Transmutation'],
        'description' => 'Assume the form of a small or medium animal native to the terrain you scouted. Gain that animal\'s natural attacks and movement modes for 1 minute.',
      ],
      'terrain-form' => [
        'id'          => 'terrain-form',
        'name'        => 'Terrain Form',
        'action_cost' => 2,
        'fp_cost'     => 1,
        'traits'      => ['Focus', 'Morph', 'Primal', 'Transmutation'],
        'terrain_based' => TRUE,
        'description' => 'Your body adapts to the favored terrain. Gain a movement benefit (climb speed, swim speed, burrow speed, or similar) appropriate to the terrain for 10 minutes.',
      ],
      'warden-s-boon' => [
        'id'          => 'wardens-boon',
        'name'        => "Warden's Boon",
        'action_cost' => 1,
        'fp_cost'     => 1,
        'traits'      => ['Focus', 'Primal', 'Transmutation'],
        'creature_type_bonus' => TRUE,
        'description' => 'You and allies within 30 ft gain +1 status bonus to attack rolls and skill checks against creatures of a type matching your Warden Spells feat selection for 1 minute.',
      ],
    ],
  ];

  /**
   * Focus pool configuration by class (APG).
   *
   * Defines the starting focus pool size and expansion rules.
   * Oracle starts at 2 (unique — not the normal 1).
   * Each additional focus spell source may expand the pool (cap: 3).
   */
  const FOCUS_POOLS = [
    'oracle' => [
      'start'     => 2,
      'cap'       => 3,
      'expand_per_source' => TRUE,
      'note'      => 'Oracle focus pool starts at 2 Focus Points (unique; not the default 1). Each additional focus spell source (revelation feats, domain spells) expands the pool by 1 up to the cap of 3.',
    ],
    'witch' => [
      'start'     => 1,
      'cap'       => 3,
      'expand_per_source' => TRUE,
      'note'      => 'Witch focus pool starts at 1 Focus Point. Expands by 1 for each additional focus spell source (lesson hexes, patron feats) up to a cap of 3.',
    ],
    'bard' => [
      'start'     => 1,
      'cap'       => 3,
      'expand_per_source' => TRUE,
      'note'      => 'Bard focus pool starts at 1 Focus Point. APG composition spells (Hymn of Healing, Song of Strength, Gravity Weapon) expand the pool when their granting feats are taken.',
    ],
    'ranger' => [
      'start'     => 1,
      'cap'       => 3,
      'tradition' => 'primal',
      'expand_per_source' => TRUE,
      'note'      => 'Ranger warden spell pool is primal. Refocus requires 10 minutes in nature. Pool shared across all ranger focus spells.',
    ],
  ];

  /**
   * PF2e Ritual catalog — CRB and APG entries.
   *
   * Structure per entry:
   *   id, name, level, book_id, rarity, traits,
   *   casting_time, cost,
   *   primary_check: ['skill', 'min_proficiency'],
   *   secondary_casters: int (0 = primary-only ritual),
   *   secondary_checks: array of ['skill', 'min_proficiency'],
   *   targets, description
   *
   * Lookup key is (id + book_id) — book_id differentiates same-named rituals
   * across sourcebooks (edge-case guard, AC Edge-2).
   *
   * Rarity values: 'common', 'uncommon', 'rare'
   * Uncommon/Rare rituals require GM-approval gate before a character may initiate.
   */
  const RITUALS = [

    // -------------------------------------------------------------------------
    // Core Rulebook (CRB) rituals — baseline data for integration parity checks
    // -------------------------------------------------------------------------
    [
      'id'                => 'sanctify-water',
      'name'              => 'Sanctify Water',
      'level'             => 1,
      'book_id'           => 'crb',
      'rarity'            => 'common',
      'traits'            => ['Consecration', 'Divine', 'Ritual', 'Water'],
      'casting_time'      => '1 hour',
      'cost'              => 'The water to be sanctified',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'trained'],
      'secondary_casters' => 0,
      'secondary_checks'  => [],
      'targets'           => '1 gallon of water per level',
      'description'       => 'You imbue water with the power of your deity, transforming it into holy (or unholy) water. The sanctified water can be thrown or used to douse undead or fiends.',
    ],
    [
      'id'                => 'create-undead',
      'name'              => 'Create Undead',
      'level'             => 2,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Divine', 'Evil', 'Necromancy', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => 'Black onyx gems worth 50 gp × the creature\'s level',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'expert'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Religion', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 corpse',
      'description'       => 'You infuse a corpse with negative energy to create an undead creature of a level up to double the ritual\'s level. The undead is under your control for 24 hours, after which you must cast the ritual again.',
    ],
    [
      'id'                => 'divination',
      'name'              => 'Divination',
      'level'             => 2,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Divination', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => '100 gp of rare incense and offerings',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'trained'],
      'secondary_casters' => 0,
      'secondary_checks'  => [],
      'targets'           => 'You',
      'description'       => 'You contact a divine entity for advice. Ask a question; receive a cryptic but accurate answer. On a critical success the answer is clear; on a failure the answer is misleading.',
    ],
    [
      'id'                => 'heartbond',
      'name'              => 'Heartbond',
      'level'             => 2,
      'book_id'           => 'crb',
      'rarity'            => 'common',
      'traits'            => ['Ritual'],
      'casting_time'      => '1 day',
      'cost'              => 'Rings or tokens worth 20 gp total',
      'primary_check'     => ['skill' => 'Society', 'min_proficiency' => 'trained'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Society', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '2 willing creatures',
      'description'       => 'You bind two creatures in a magical bond. Each bonded creature always knows the other\'s direction and rough distance. On a critical success, they can also share simple emotions.',
    ],
    [
      'id'                => 'geas',
      'name'              => 'Geas',
      'level'             => 3,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Enchantment', 'Mental', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => '300 gp',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'expert'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Occultism', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 creature',
      'description'       => 'You impose a magical directive on a creature. If the geas is reasonable, the target must obey; if impossible it is suspended. Violating it deals 4d6 mental damage per day.',
    ],
    [
      'id'                => 'atone',
      'name'              => 'Atone',
      'level'             => 4,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Divine', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '400 gp in offerings',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'expert'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Religion', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 creature of your deity\'s faith',
      'description'       => 'You beseech your deity to forgive a follower\'s transgression. On a success the target regains their divine connection; on a failure the deity ignores the plea for one year.',
    ],
    [
      'id'                => 'community-gathering',
      'name'              => 'Community Gathering',
      'level'             => 4,
      'book_id'           => 'crb',
      'rarity'            => 'common',
      'traits'            => ['Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '100 gp in food and drink',
      'primary_check'     => ['skill' => 'Society', 'min_proficiency' => 'expert'],
      'secondary_casters' => 3,
      'secondary_checks'  => [
        ['skill' => 'Diplomacy', 'min_proficiency' => 'trained'],
        ['skill' => 'Performance', 'min_proficiency' => 'trained'],
        ['skill' => 'Society',    'min_proficiency' => 'trained'],
      ],
      'targets'           => 'One community',
      'description'       => 'You organize a community event to strengthen social ties. On a success, attitude toward your party improves; on a critical success you can also gather important rumours.',
    ],
    [
      'id'                => 'planar-binding',
      'name'              => 'Planar Binding',
      'level'             => 5,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Conjuration', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '500 gp in rare materials',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Arcana',   'min_proficiency' => 'expert'],
        ['skill' => 'Religion', 'min_proficiency' => 'expert'],
      ],
      'targets'           => '1 extraplanar creature',
      'description'       => 'You summon an extraplanar creature and force a bargain. On a success it agrees to perform one service of up to 1 week; on a critical success the service is indefinite.',
    ],
    [
      'id'                => 'call-spirit',
      'name'              => 'Call Spirit',
      'level'             => 5,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Divination', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => '500 gp in offerings',
      'primary_check'     => ['skill' => 'Occultism', 'min_proficiency' => 'master'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Occultism', 'min_proficiency' => 'expert'],
      ],
      'targets'           => 'The spirit of 1 dead creature',
      'description'       => 'You call a deceased creature\'s spirit to answer up to 3 questions. The spirit must answer truthfully; hostile spirits may give misleading but technically true answers.',
    ],
    [
      'id'                => 'commune',
      'name'              => 'Commune',
      'level'             => 6,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Divination', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => '600 gp in incense and offerings',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'master'],
      'secondary_casters' => 0,
      'secondary_checks'  => [],
      'targets'           => 'You',
      'description'       => 'You ask your deity up to 6 yes/no questions. Deities answer truthfully but may decline to answer questions outside their portfolio.',
    ],
    [
      'id'                => 'raise-dead',
      'name'              => 'Raise Dead',
      'level'             => 7,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Healing', 'Necromancy', 'Positive', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => 'Diamonds worth 400 gp per level of the target',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Religion', 'min_proficiency' => 'expert'],
        ['skill' => 'Medicine',  'min_proficiency' => 'expert'],
      ],
      'targets'           => '1 dead creature',
      'description'       => 'You attempt to call back a recently slain creature. The target must have died within 3 days; it returns with 1 HP and is clumsy 2, enfeebled 2, and stupefied 2 for 1 week.',
    ],
    [
      'id'                => 'teleportation-circle',
      'name'              => 'Teleportation Circle',
      'level'             => 7,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Conjuration', 'Ritual', 'Teleportation'],
      'casting_time'      => '1 day',
      'cost'              => '1,500 gp in rare chalk and ink',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'master'],
      'secondary_casters' => 3,
      'secondary_checks'  => [
        ['skill' => 'Arcana',     'min_proficiency' => 'expert'],
        ['skill' => 'Arcana',     'min_proficiency' => 'expert'],
        ['skill' => 'Occultism',  'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 permanent circle up to 10 feet in diameter',
      'description'       => 'You inscribe a permanent teleportation circle linked to another circle you know the sigil sequence of. Any creature that steps into the circle is instantly transported.',
    ],
    [
      'id'                => 'resurrect',
      'name'              => 'Resurrect',
      'level'             => 10,
      'book_id'           => 'crb',
      'rarity'            => 'uncommon',
      'traits'            => ['Healing', 'Necromancy', 'Positive', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => 'Diamonds worth 1,000 gp per level of the target',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'legendary'],
      'secondary_casters' => 3,
      'secondary_checks'  => [
        ['skill' => 'Religion', 'min_proficiency' => 'master'],
        ['skill' => 'Religion', 'min_proficiency' => 'master'],
        ['skill' => 'Medicine',  'min_proficiency' => 'expert'],
      ],
      'targets'           => '1 dead creature',
      'description'       => 'You return a dead creature to life with no limit on the time since death. The target returns at full HP with all its gear. Creatures that died of old age cannot be resurrected.',
    ],

    // -------------------------------------------------------------------------
    // Advanced Player's Guide (APG) rituals
    // -------------------------------------------------------------------------
    [
      'id'                => 'bless-the-hearth',
      'name'              => 'Bless the Hearth',
      'level'             => 1,
      'book_id'           => 'apg',
      'rarity'            => 'common',
      'traits'            => ['Abjuration', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => 'Herbs and candles worth 10 gp',
      'primary_check'     => ['skill' => 'Nature', 'min_proficiency' => 'trained'],
      'secondary_casters' => 0,
      'secondary_checks'  => [],
      'targets'           => '1 dwelling (up to 10 rooms)',
      'description'       => 'You bless a home or dwelling place. Residents gain a +1 status bonus to saving throws against disease and poison while inside; the blessing lasts until the next full moon.',
    ],
    [
      'id'                => 'fantastic-facade',
      'name'              => 'Fantastic Facade',
      'level'             => 4,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Illusion', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '400 gp in pigments and illusory components',
      'primary_check'     => ['skill' => 'Occultism', 'min_proficiency' => 'expert'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Arcana',      'min_proficiency' => 'trained'],
        ['skill' => 'Performance', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 building or structure up to 200 feet on a side',
      'description'       => 'You wrap a structure in a powerful illusion, changing its apparent size, shape, and appearance. The illusion persists for 1 year. Observers who interact with the facade can attempt a Perception check against your spell DC to disbelieve.',
    ],
    [
      'id'                => 'fey-influence',
      'name'              => 'Fey Influence',
      'level'             => 4,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Enchantment', 'Ritual'],
      'casting_time'      => '1 hour',
      'cost'              => 'Fey tokens and silver worth 150 gp',
      'primary_check'     => ['skill' => 'Nature', 'min_proficiency' => 'expert'],
      'secondary_casters' => 1,
      'secondary_checks'  => [
        ['skill' => 'Diplomacy', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 creature',
      'description'       => 'You call upon fey spirits to bless a creature with minor fey traits. On a success the target gains low-light vision and a +1 circumstance bonus to Nature checks involving the First World; on a critical success they also gain a fey cantrip.',
    ],
    [
      'id'                => 'inveigle',
      'name'              => 'Inveigle',
      'level'             => 4,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Enchantment', 'Incapacitation', 'Mental', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '400 gp in rare powders and perfumes',
      'primary_check'     => ['skill' => 'Occultism', 'min_proficiency' => 'expert'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Diplomacy',   'min_proficiency' => 'expert'],
        ['skill' => 'Deception',   'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 creature',
      'description'       => 'You subtly influence a creature\'s memory and desires over the casting period. On a success the target believes a fabricated memory or desire is real; on a critical success they actively defend the false belief.',
    ],
    [
      'id'                => 'angelic-messenger',
      'name'              => 'Angelic Messenger',
      'level'             => 5,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Conjuration', 'Divine', 'Good', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '500 gp in blessed silver and incense',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Religion',  'min_proficiency' => 'expert'],
        ['skill' => 'Diplomacy', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 creature on any plane',
      'description'       => 'You call upon a celestial messenger to deliver a message of up to 25 words to a creature on any plane. The messenger travels instantly and returns with a reply of equal length. On a critical success the messenger also reports the target\'s general condition.',
    ],
    [
      'id'                => 'elemental-sentinel',
      'name'              => 'Elemental Sentinel',
      'level'             => 5,
      'book_id'           => 'apg',
      'rarity'            => 'common',
      'traits'            => ['Conjuration', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '500 gp in elemental focals tied to chosen element',
      'primary_check'     => ['skill' => 'Nature', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Nature',   'min_proficiency' => 'expert'],
        ['skill' => 'Crafting', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 area up to 100 feet in radius',
      'description'       => 'You bind an elemental spirit to guard a location. The sentinel (level 5 elemental of chosen type) patrols the area, attacking intruders and alerting you via a telepathic alarm. It remains for 1 month.',
    ],
    [
      'id'                => 'primal-call',
      'name'              => 'Primal Call',
      'level'             => 5,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Conjuration', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '500 gp in natural offerings appropriate to the creature',
      'primary_check'     => ['skill' => 'Nature', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Nature',    'min_proficiency' => 'expert'],
        ['skill' => 'Diplomacy', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 fey or beast creature up to level 10',
      'description'       => 'You call a fey or beast creature from the wilds and negotiate a service. On a success it agrees to serve for 1 month; on a critical success the service extends to 1 year. Hostile beasts may refuse unless the check succeeds by 10 or more.',
    ],
    [
      'id'                => 'ravenous-reanimation',
      'name'              => 'Ravenous Reanimation',
      'level'             => 5,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Evil', 'Necromancy', 'Ritual'],
      'gm_approval'       => TRUE,
      'casting_time'      => '1 day',
      'cost'              => 'Corrupted black onyx worth 500 gp',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'master'],
      'secondary_casters' => 3,
      'secondary_checks'  => [
        ['skill' => 'Arcana',    'min_proficiency' => 'expert'],
        ['skill' => 'Occultism', 'min_proficiency' => 'expert'],
        ['skill' => 'Religion',  'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 corpse',
      'description'       => 'You infuse a corpse with ravenous hunger, creating an unusually powerful undead that drains life force from nearby creatures. On a critical success the undead also spreads a minor curse to those it kills.',
    ],
    [
      'id'                => 'establish-stronghold',
      'name'              => 'Establish Stronghold',
      'level'             => 6,
      'book_id'           => 'apg',
      'rarity'            => 'common',
      'traits'            => ['Abjuration', 'Ritual'],
      'casting_time'      => '1 week',
      'cost'              => '2,000 gp in building materials and enchanting components',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'master'],
      'secondary_casters' => 4,
      'secondary_checks'  => [
        ['skill' => 'Arcana',    'min_proficiency' => 'expert'],
        ['skill' => 'Crafting',  'min_proficiency' => 'expert'],
        ['skill' => 'Society',   'min_proficiency' => 'trained'],
        ['skill' => 'Diplomacy', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 structure up to 10,000 square feet',
      'description'       => 'You bind protective magic into a structure, establishing it as a magical stronghold. The stronghold gains a +4 status bonus to Hardness for walls and doors; occupants gain a +1 status bonus to Will saves while inside. Duration: permanent.',
    ],
    [
      'id'                => 'infuse-companion',
      'name'              => 'Infuse Companion',
      'level'             => 6,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Polymorph', 'Ritual', 'Transmutation'],
      'casting_time'      => '1 day',
      'cost'              => '600 gp in alchemical compounds and rare herbs',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Nature',    'min_proficiency' => 'expert'],
        ['skill' => 'Medicine',  'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 willing animal companion or familiar',
      'description'       => 'You infuse an animal companion or familiar with magical essence, granting it unusual capabilities. On a success it gains one additional familiar ability (if familiar) or one additional companion support benefit (if animal companion). Duration: permanent.',
    ],
    [
      'id'                => 'create-nexus',
      'name'              => 'Create Nexus',
      'level'             => 7,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Abjuration', 'Conjuration', 'Ritual'],
      'casting_time'      => '1 week',
      'cost'              => '3,000 gp in ley-line crystals and rare pigments',
      'primary_check'     => ['skill' => 'Arcana', 'min_proficiency' => 'master'],
      'secondary_casters' => 3,
      'secondary_checks'  => [
        ['skill' => 'Arcana',    'min_proficiency' => 'master'],
        ['skill' => 'Occultism', 'min_proficiency' => 'expert'],
        ['skill' => 'Nature',    'min_proficiency' => 'expert'],
      ],
      'targets'           => '1 location of significant magical resonance',
      'description'       => 'You tap into ley lines and weave them into a permanent magical nexus. Spellcasters at the nexus treat their spell level as 1 higher for purposes of identifying magic; rituals cast at the nexus reduce their cost by 10%. Duration: permanent.',
    ],
    [
      'id'                => 'subjugate-undead',
      'name'              => 'Subjugate Undead',
      'level'             => 7,
      'book_id'           => 'apg',
      'rarity'            => 'uncommon',
      'traits'            => ['Divine', 'Necromancy', 'Ritual'],
      'casting_time'      => '1 day',
      'cost'              => '700 gp in silver dust and holy symbols',
      'primary_check'     => ['skill' => 'Religion', 'min_proficiency' => 'master'],
      'secondary_casters' => 2,
      'secondary_checks'  => [
        ['skill' => 'Religion',  'min_proficiency' => 'expert'],
        ['skill' => 'Occultism', 'min_proficiency' => 'trained'],
      ],
      'targets'           => '1 undead creature of up to level 12',
      'description'       => 'You force an undead creature into permanent submission. On a success it obeys your commands indefinitely; on a critical success it is destroyed if you so command. The subjugated undead retains its intelligence but cannot act against your will.',
    ],
    [
      'id'                => 'unspeakable-shadow',
      'name'              => 'Unspeakable Shadow',
      'level'             => 7,
      'book_id'           => 'apg',
      'rarity'            => 'rare',
      'traits'            => ['Dark', 'Necromancy', 'Ritual', 'Shadow'],
      'gm_approval'       => TRUE,
      'casting_time'      => '1 day',
      'cost'              => '2,000 gp in void-touched obsidian and rare shadow essences',
      'primary_check'     => ['skill' => 'Occultism', 'min_proficiency' => 'legendary'],
      'secondary_casters' => 4,
      'secondary_checks'  => [
        ['skill' => 'Occultism', 'min_proficiency' => 'master'],
        ['skill' => 'Arcana',    'min_proficiency' => 'master'],
        ['skill' => 'Religion',  'min_proficiency' => 'expert'],
        ['skill' => 'Stealth',   'min_proficiency' => 'expert'],
      ],
      'targets'           => '1 creature',
      'description'       => 'You tear a fragment of the Plane of Shadow and bind it to a creature, manifesting a living shadow servant that cannot be named or described by those who witness it. The shadow is immune to non-magical damage and follows your commands.',
    ],

  ];

  /**
   * APG new spells by tradition and level.
   *
   * Structure mirrors SPELLS: tradition → level-key → array of spell entries.
   * Traditions: 'arcane', 'divine', 'occult', 'primal'.
   * Level keys match SPELLS convention: 'cantrips', '1st' … '9th'.
   *
   * Each entry carries:
   *   id, name, level, school, cast, traditions (array),
   *   [range|area|targets], [duration], [save], [components], traits,
   *   description,
   *   [heightened_scaling] — keyed on "+N" or absolute rank for graduated effects.
   *
   * Multi-tradition spells are stored once per tradition key so that
   * tradition-based lookups work without join logic.
   *
   * Complex-mechanic spells carry extra metadata fields per AC:
   *   animate_dead: summon_level_cap_table
   *   blood_vendetta: trigger, eligible_caster_note, save_outcomes
   *   deja_vu: state_machine (record_turn / replay_turn), stupefied_fallback
   *   final_sacrifice: minion_killed_note, evil_trait_condition, cold_water_override
   *   heat_metal: target_types, persistent_fire_bound, release_escape_note
   *   mad_monkeys: modes (flagrant_burglary / raucous_din / tumultuous_gymnastics),
   *                calm_emotions_overlay, mode_is_fixed_at_cast
   */
  const APG_SPELLS = [

    // =========================================================================
    // ARCANE
    // =========================================================================
    'arcane' => [

      '1st' => [

        // ------------------------------------------------------------------
        // Animate Dead (Arcane/Divine/Occult — stored in each tradition key)
        // ------------------------------------------------------------------
        [
          'id'          => 'animate-dead',
          'name'        => 'Animate Dead',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Necromancy', 'Summoning'],
          'description' => 'You animate a corpse to fight for you. Choose one common undead creature whose level is equal to or lower than the level allowed for this spell\'s rank. The summoned undead obeys your commands but disappears when the spell ends or you stop sustaining it. No damage roll; no saving throw — summon only.',
          'summon_level_cap_table' => [
            1 => -1, 2 => 1, 3 => 2, 4 => 3, 5 => 5,
            6 => 7, 7 => 9, 8 => 11, 9 => 13, 10 => 15,
          ],
          'edge_case' => 'If no valid undead of the correct level is available, the spell fails gracefully with an error message rather than summoning nothing silently.',
        ],

        // ------------------------------------------------------------------
        // Blood Vendetta (Arcane/Occult/Primal — reaction)
        // ------------------------------------------------------------------
        [
          'id'          => 'blood-vendetta',
          'name'        => 'Blood Vendetta',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => 'Reaction',
          'components'  => ['Verbal'],
          'range'       => '30 feet',
          'duration'    => 'varies (persistent damage)',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Arcane', 'Curse', 'Necromancy'],
          'trigger'     => 'A creature deals piercing, slashing, or bleed damage to you',
          'save'        => 'Will',
          'eligible_caster_note' => 'Caster must be able to bleed (constructs and undead are ineligible; cast automatically fails if ineligible).',
          'description' => 'You curse the attacker with sympathetic bleeding. Base effect: 2d6 persistent bleed damage (Will save). Critical Success: Unaffected. Success: Half persistent bleed damage. Failure: Full 2d6 persistent bleed + Weakness 1 to piercing and slashing while bleeding persists. Critical Failure: Same as Failure but double persistent bleed damage.',
          'save_outcomes' => [
            'critical_success' => 'Unaffected.',
            'success'          => 'Half persistent bleed damage only.',
            'failure'          => 'Full persistent bleed + Weakness 1 to piercing/slashing while bleeding lasts.',
            'critical_failure' => 'Double persistent bleed + Weakness 1 to piercing/slashing while bleeding lasts.',
          ],
          'heightened_scaling' => [
            '+2' => '+2d6 persistent bleed damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Pummeling Rubble (Arcane/Primal)
        // ------------------------------------------------------------------
        [
          'id'          => 'pummeling-rubble',
          'name'        => 'Pummeling Rubble',
          'level'       => 1,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '15-foot cone',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Earth', 'Evocation'],
          'save'        => 'Reflex',
          'description' => 'A spray of rocks and debris deals 2d4 bludgeoning damage in a 15-foot cone (Reflex save). Critical Success: Unaffected. Success: Half damage. Failure: Full damage + pushed 5 feet directly away from caster. Critical Failure: Double damage + pushed 10 feet directly away from caster. Forced movement respects normal blocking constraints.',
          'save_outcomes' => [
            'critical_success' => 'Unaffected.',
            'success'          => 'Half damage.',
            'failure'          => 'Full 2d4 bludgeoning + pushed 5 feet away.',
            'critical_failure' => 'Double 2d4 bludgeoning + pushed 10 feet away.',
          ],
          'heightened_scaling' => [
            '+1' => '+2d4 bludgeoning damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Vomit Swarm (Arcane/Occult/Primal)
        // ------------------------------------------------------------------
        [
          'id'          => 'vomit-swarm',
          'name'        => 'Vomit Swarm',
          'level'       => 1,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Arcane', 'Conjuration'],
          'save'        => 'Reflex (basic)',
          'description' => 'You vomit a swarm of insects, worms, or other vermin in a 30-foot cone, dealing 2d8 piercing damage (basic Reflex save). Creatures that fail or critically fail the save also become Sickened 1. The swarm manifestation is visual/flavor only; no persistent summon entity remains.',
          'sickened_on_fail' => TRUE,
          'heightened_scaling' => [
            '+1' => '+1d8 piercing damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Goblin Pox (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'goblin-pox',
          'name'        => 'Goblin Pox',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => 'touch',
          'duration'    => 'varies',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Disease', 'Necromancy'],
          'save'        => 'Fortitude',
          'description' => 'You afflict the touched creature with goblin pox. On a failed save the target becomes sickened 1 for 1 round; on a critical failure it becomes sickened 2 for 1 minute and is slowed 1 while sickened.',
        ],

        // ------------------------------------------------------------------
        // Summon Construct (Arcane — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'summon-construct',
          'name'        => 'Summon Construct',
          'level'       => 1,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane'],
          'traits'      => ['Arcane', 'Conjuration', 'Summoning'],
          'description' => 'You conjure a construct to fight for you. It must be common and its level no higher than your spell rank minus 1 (or equal to your spell rank on a critical success). The construct obeys your commands and vanishes when the spell ends.',
        ],

      ], // end arcane 1st

      '2nd' => [

        // ------------------------------------------------------------------
        // Final Sacrifice (Arcane/Divine)
        // ------------------------------------------------------------------
        [
          'id'          => 'final-sacrifice',
          'name'        => 'Final Sacrifice',
          'level'       => 2,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'area'        => '20-foot burst centered on minion',
          'traditions'  => ['arcane', 'divine'],
          'traits'      => ['Arcane', 'Evocation', 'Fire'],
          'save'        => 'Reflex (basic)',
          'description' => 'You detonate a minion you summon or permanently control, killing it instantly and dealing 6d6 fire damage (basic Reflex save) to creatures within 20 feet of where it stood. Minion is slain as a mandatory cost; cannot be cast on a temporarily-controlled minion (fails silently without triggering the explosion). If the minion has the cold or water trait, damage type becomes cold. Casting on a non-mindless creature applies the Evil trait to this spell instance in the session log.',
          'minion_killed_note'    => 'Minion is slain as part of casting; not a secondary effect.',
          'cold_water_override'   => 'If minion has Cold or Water trait: fire damage becomes cold damage.',
          'evil_trait_condition'  => 'Applied to session log metadata when target minion is not mindless.',
          'temp_control_fails'    => TRUE,
          'heightened_scaling' => [
            '+1' => '+2d6 fire (or cold) damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Heat Metal (Arcane/Primal)
        // ------------------------------------------------------------------
        [
          'id'          => 'heat-metal',
          'name'        => 'Heat Metal',
          'level'       => 2,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Evocation', 'Fire'],
          'save'        => 'Reflex',
          'description' => 'You superheat a metal object or creature made of metal. Unattended items: no saving throw; heat is environmental — GM adjudicates secondary effects. Worn/carried items or metal creatures: 4d6 fire + 2d4 persistent fire (Reflex save). Critical Success: Unaffected. Success: Half initial + no persistent fire. Failure: Full initial + full persistent. Critical Failure: Double initial + double persistent. Held item: wielder may Release after the roll to improve their degree of success by one step. Persistent fire is bound to the item — any creature holding or wearing the heated item takes damage until it is extinguished.',
          'target_types' => [
            'unattended'    => 'No save; GM adjudicates environmental effects.',
            'worn_carried'  => '4d6 fire + 2d4 persistent fire; Reflex save.',
            'metal_creature'=> '4d6 fire + 2d4 persistent fire; Reflex save.',
          ],
          'release_escape_note' => 'Wielder may Release held item after roll to improve degree of success by one step.',
          'persistent_fire_bound' => TRUE,
          'save_outcomes' => [
            'critical_success' => 'Unaffected.',
            'success'          => 'Half initial fire; no persistent fire.',
            'failure'          => 'Full 4d6 fire + full 2d4 persistent fire.',
            'critical_failure' => 'Double initial fire + double persistent fire.',
          ],
          'heightened_scaling' => [
            '+1' => '+2d6 initial fire + +1d4 persistent fire',
          ],
        ],

        // ------------------------------------------------------------------
        // Enthrall (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'enthrall',
          'name'        => 'Enthrall',
          'level'       => 2,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '120 feet',
          'area'        => '60-foot burst',
          'duration'    => 'sustained',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Auditory', 'Emotion', 'Enchantment', 'Mental'],
          'save'        => 'Will',
          'description' => 'You captivate creatures in the area with your speech or performance. Each creature must attempt a Will save. Failure: Fascinated for the duration. Critical Failure: Fascinated and cannot take actions to move away from you. On a success, the creature is temporarily immune to your Enthrall for 24 hours.',
        ],

        // ------------------------------------------------------------------
        // Humanoid Form (Arcane/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'humanoid-form',
          'name'        => 'Humanoid Form',
          'level'       => 2,
          'school'      => 'Transmutation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'duration'    => '1 hour',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Arcane', 'Occult', 'Polymorph', 'Transmutation'],
          'description' => 'You transform yourself into any Medium or Small humanoid ancestry. You gain that ancestry\'s low-light vision or darkvision (if any) but do not gain any other ancestry feats, abilities, or special senses. Your size, reach, and natural attacks change to match a typical member of that ancestry.',
        ],

        // ------------------------------------------------------------------
        // Summon Elemental (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'summon-elemental',
          'name'        => 'Summon Elemental',
          'level'       => 2,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Conjuration', 'Summoning'],
          'description' => 'You conjure an elemental (air, earth, fire, or water) of a level equal to your spell rank minus 1. The elemental obeys your commands for the duration and vanishes when the spell ends.',
        ],

      ], // end arcane 2nd

      '3rd' => [

        // ------------------------------------------------------------------
        // Déjà Vu (Occult only — in occult key below; arcane/occult)
        // Listed here for arcane tradition coverage
        // ------------------------------------------------------------------
        [
          'id'          => 'deja-vu',
          'name'        => 'Déjà Vu',
          'level'       => 3,
          'school'      => 'Divination',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '100 feet',
          'targets'     => '1 creature',
          'traditions'  => ['occult'],
          'traits'      => ['Divination', 'Occult', 'Mental'],
          'save'        => 'Will',
          'description' => 'On a failed Will save, the target is afflicted with a temporal echo. The engine records the exact action order and targets from the target\'s NEXT turn. On the FOLLOWING turn, the target is forced to repeat that sequence exactly (same targets, same movement direction). For each action that cannot be legally repeated: the target may substitute a legal action and gains Stupefied 1 until end of that turn. No direct damage. If the target has no valid actions to replay (all targets dead, etc.), each replaced action triggers Stupefied 1.',
          'state_machine' => [
            'record_turn'  => 'Round N+1: record target\'s action order and targets/directions.',
            'replay_turn'  => 'Round N+2: target must replay recorded actions; illegal actions trigger Stupefied 1.',
          ],
          'stupefied_fallback' => 'Each action that cannot be legally repeated grants Stupefied 1 until end of the turn that action was replaced.',
        ],

        // ------------------------------------------------------------------
        // Mad Monkeys (Primal/Occult — also in primal key below)
        // ------------------------------------------------------------------
        [
          'id'          => 'mad-monkeys',
          'name'        => 'Mad Monkeys',
          'level'       => 3,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '10-foot burst',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['occult', 'primal'],
          'traits'      => ['Conjuration', 'Occult', 'Primal'],
          'description' => 'You summon a chaotic swarm of monkeys that fill a 10-foot burst. On Sustain you may reposition the area 5 feet. Choose ONE mode at cast time; mode is fixed for the duration.',
          'mode_is_fixed_at_cast' => TRUE,
          'calm_emotions_overlay' => 'Calm Emotions suppresses monkey mischief effects while both effects overlap.',
          'modes' => [
            'flagrant_burglary' => [
              'description'     => 'Monkeys attempt one Steal action per round against one creature in the area.',
              'thievery_mod'    => 'spell_dc_minus_10',
              'stolen_items'    => 'Drop in a chosen square when the spell ends.',
            ],
            'raucous_din' => [
              'description'     => 'Fortitude save each round for each creature in area.',
              'save_outcomes'   => [
                'critical_success' => 'Unaffected; 10-minute immunity to this mode.',
                'success'          => 'Unaffected.',
                'failure'          => 'Deafened for 1 round.',
                'critical_failure' => 'Deafened for 1 minute.',
              ],
            ],
            'tumultuous_gymnastics' => [
              'description'     => 'Reflex save each round for each creature in area.',
              'save_outcomes'   => [
                'critical_success' => 'Unaffected; 10-minute immunity to this mode.',
                'success'          => 'Unaffected.',
                'failure'          => 'DC 5 flat check to perform manipulate actions for 1 round; fail flat = lose that action.',
                'critical_failure' => 'Same flat check required until spell ends, even if the creature leaves the area.',
              ],
            ],
          ],
        ],

        // ------------------------------------------------------------------
        // Agonizing Despair (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'agonizing-despair',
          'name'        => 'Agonizing Despair',
          'level'       => 3,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Emotion', 'Enchantment', 'Mental'],
          'save'        => 'Will',
          'description' => 'You fill the target with crushing despair. Critical Failure: the target takes 7d6 mental damage and is Stunned 1. Failure: 7d6 mental damage and Slowed 1 for 1 round. Success: 3d6 mental damage. Critical Success: Unaffected.',
          'heightened_scaling' => [
            '+1' => '+2d6 mental damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Howling Blizzard (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'howling-blizzard',
          'name'        => 'Howling Blizzard',
          'level'       => 3,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Cold', 'Evocation', 'Water'],
          'save'        => 'Reflex',
          'description' => 'You unleash a blast of freezing wind and snow. Creatures in the area take 5d8 cold damage (Reflex save). Failure: also slowed 1 until the end of their next turn.',
          'heightened_scaling' => [
            '+1' => '+2d8 cold damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Bind Undead (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'bind-undead',
          'name'        => 'Bind Undead',
          'level'       => 3,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 mindless undead of up to 6th level',
          'duration'    => '1 day',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Necromancy'],
          'description' => 'You take control of a mindless undead creature. It obeys your spoken commands for 1 day. On a success you may issue any legal command; on a critical success the duration extends to 1 week.',
        ],

      ], // end arcane 3rd

      '4th' => [

        // ------------------------------------------------------------------
        // Shadow Blast (Arcane/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'shadow-blast',
          'name'        => 'Shadow Blast',
          'level'       => 4,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '60-foot line or 30-foot cone',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Arcane', 'Cold', 'Darkness', 'Evocation', 'Shadow'],
          'save'        => 'Reflex (basic)',
          'description' => 'You channel shadow into a line or cone, dealing 8d6 cold damage to creatures in the area (basic Reflex save). On a critical failure the target is Blinded for 1 round.',
          'heightened_scaling' => [
            '+1' => '+2d6 cold damage',
          ],
        ],

        // ------------------------------------------------------------------
        // Shape Stone (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'shape-stone',
          'name'        => 'Shape Stone',
          'level'       => 4,
          'school'      => 'Transmutation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => 'touch',
          'duration'    => 'permanent',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Earth', 'Transmutation'],
          'description' => 'You reshape up to 10 cubic feet of stone into any shape. Creatures inside the stone when it reshapes must succeed at a Reflex save or become Grabbed.',
        ],

        // ------------------------------------------------------------------
        // Spiritual Anamnesis (Divine/Occult — APG) — stored in divine below
        // ------------------------------------------------------------------

      ], // end arcane 4th

      '5th' => [

        // ------------------------------------------------------------------
        // Warp Mind (Arcane/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'warp-mind',
          'name'        => 'Warp Mind',
          'level'       => 5,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Arcane', 'Emotion', 'Enchantment', 'Incapacitation', 'Mental'],
          'save'        => 'Will',
          'description' => 'You scramble a creature\'s mind. Critical Failure: Confused permanently (until cured). Failure: Confused for 1 minute. Success: Confused for 1 round. Critical Success: Unaffected.',
        ],

        // ------------------------------------------------------------------
        // Pillars of Sand (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'pillars-of-sand',
          'name'        => 'Pillars of Sand',
          'level'       => 5,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '60 feet',
          'duration'    => '1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Conjuration', 'Earth'],
          'description' => 'You conjure up to four pillars of sand (each 5 feet wide and up to 20 feet tall) in unoccupied squares within range. Creatures in those squares are pushed to adjacent squares. The pillars can be used for cover or to block movement; they crumble at the end of the duration.',
        ],

      ], // end arcane 5th

      '6th' => [

        // ------------------------------------------------------------------
        // Vampiric Exsanguination (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'vampiric-exsanguination',
          'name'        => 'Vampiric Exsanguination',
          'level'       => 6,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Negative', 'Necromancy'],
          'save'        => 'Fortitude',
          'description' => 'You drain the life force from all creatures in a cone. Each takes 12d6 negative damage (Fortitude save); you regain HP equal to half the total damage dealt (before saves). Critical Success: No damage. Success: Half damage. Failure: Full damage. Critical Failure: Double damage.',
          'healing_note' => 'Caster regains HP equal to half total damage dealt to all targets (summed before individual saves).',
          'heightened_scaling' => [
            '+1' => '+2d6 negative damage',
          ],
        ],

      ], // end arcane 6th

      '7th' => [

        // ------------------------------------------------------------------
        // Executioner's Eyes (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'executioners-eyes',
          'name'        => "Executioner's Eyes",
          'level'       => 7,
          'school'      => 'Divination',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '60 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Curse', 'Divination', 'Fortune', 'Misfortune'],
          'save'        => 'Will',
          'description' => 'You curse a creature with the sight of its own death. Until the start of your next turn, any attack roll that would kill or reduce the target to 0 HP automatically becomes a critical hit regardless of the natural die result (once only). Failure: Target is Frightened 2 for 1 minute as well.',
        ],

      ], // end arcane 7th

      '8th' => [

        // ------------------------------------------------------------------
        // Devour Life (Arcane/Divine/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'devour-life',
          'name'        => 'Devour Life',
          'level'       => 8,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 living creature',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Arcane', 'Necromancy', 'Negative'],
          'save'        => 'Fortitude',
          'description' => 'You devour a creature\'s life essence. The target takes 10d6+40 negative damage (Fortitude save); you gain temporary HP equal to half the damage dealt (lost after 1 minute). Critical Success: Half damage. Success: Full damage. Failure: Full damage and drained 2. Critical Failure: Double damage and drained 4.',
          'healing_note' => 'Caster gains temporary HP equal to half the damage dealt to the target.',
        ],

        // ------------------------------------------------------------------
        // Horrid Wilting (Arcane/Primal — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'horrid-wilting',
          'name'        => 'Horrid Wilting',
          'level'       => 8,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '500 feet',
          'area'        => '60-foot burst',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Arcane', 'Necromancy', 'Negative'],
          'save'        => 'Fortitude',
          'description' => 'You evaporate moisture from all living creatures in the area. Each takes 10d10 negative damage (basic Fortitude save). Plants and water-based creatures take double damage.',
        ],

      ], // end arcane 8th

      '9th' => [

        // ------------------------------------------------------------------
        // Cannibalize Magic (Arcane/Occult — APG)
        // ------------------------------------------------------------------
        [
          'id'          => 'cannibalize-magic',
          'name'        => 'Cannibalize Magic',
          'level'       => 9,
          'school'      => 'Abjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature with an active spell effect',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Arcane', 'Abjuration'],
          'save'        => 'Will',
          'description' => 'You devour one of the target\'s active spell effects (your choice or the highest-level one on a failed save). If successful you gain a number of temporary Focus Points equal to the spell\'s level ÷ 3 (minimum 1, maximum 3), usable until the end of your next turn.',
        ],

      ], // end arcane 9th

    ], // end arcane

    // =========================================================================
    // DIVINE
    // =========================================================================
    'divine' => [

      '1st' => [

        // Animate Dead — divine tradition
        [
          'id'          => 'animate-dead',
          'name'        => 'Animate Dead',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Divine', 'Necromancy', 'Summoning'],
          'description' => 'You animate a corpse to fight for you. Summoned undead level is capped by spell rank (see summon_level_cap_table). No damage roll; no saving throw.',
          'summon_level_cap_table' => [
            1 => -1, 2 => 1, 3 => 2, 4 => 3, 5 => 5,
            6 => 7, 7 => 9, 8 => 11, 9 => 13, 10 => 15,
          ],
        ],

        // Heal (already CRB; not duplicated)

      ], // end divine 1st

      '2nd' => [

        // Final Sacrifice — divine tradition
        [
          'id'          => 'final-sacrifice',
          'name'        => 'Final Sacrifice',
          'level'       => 2,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'area'        => '20-foot burst centered on minion',
          'traditions'  => ['arcane', 'divine'],
          'traits'      => ['Divine', 'Evocation', 'Fire'],
          'save'        => 'Reflex (basic)',
          'description' => 'You detonate a summoned or permanently controlled minion, dealing 6d6 fire damage. Cold/water minion: damage becomes cold. Evil trait applied to session log if minion is not mindless. Fails silently on temporary-control minions.',
          'cold_water_override'  => TRUE,
          'evil_trait_condition' => 'Non-mindless minion: evil trait logged.',
          'temp_control_fails'   => TRUE,
          'heightened_scaling' => ['+1' => '+2d6 fire (or cold) damage'],
        ],

        // Enthrall — divine tradition
        [
          'id'          => 'enthrall',
          'name'        => 'Enthrall',
          'level'       => 2,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '120 feet',
          'area'        => '60-foot burst',
          'duration'    => 'sustained',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Auditory', 'Divine', 'Emotion', 'Enchantment', 'Mental'],
          'save'        => 'Will',
          'description' => 'You captivate creatures in the area. Failure: Fascinated. Critical Failure: Fascinated and cannot take actions to move away from you.',
        ],

      ], // end divine 2nd

      '3rd' => [

        // Agonizing Despair — divine tradition
        [
          'id'          => 'agonizing-despair',
          'name'        => 'Agonizing Despair',
          'level'       => 3,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Divine', 'Emotion', 'Enchantment', 'Mental'],
          'save'        => 'Will',
          'description' => 'Crushing despair overwhelms the target. See arcane entry for full save outcomes.',
          'heightened_scaling' => ['+1' => '+2d6 mental damage'],
        ],

        // Bind Undead — divine tradition
        [
          'id'          => 'bind-undead',
          'name'        => 'Bind Undead',
          'level'       => 3,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 mindless undead of up to 6th level',
          'duration'    => '1 day',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Divine', 'Necromancy'],
          'description' => 'You take control of a mindless undead for 1 day.',
        ],

        // Chilling Darkness (Divine/Occult — APG)
        [
          'id'          => 'chilling-darkness',
          'name'        => 'Chilling Darkness',
          'level'       => 3,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Cold', 'Darkness', 'Divine', 'Evil', 'Evocation'],
          'save'        => 'Reflex',
          'description' => 'You blast a target with cold infused with unholy darkness, dealing 5d6 cold + 5d6 evil damage (Reflex save). On a failure the target is Blinded for 1 round. Critical Failure: Blinded for 1 minute.',
          'heightened_scaling' => ['+1' => '+1d6 cold + 1d6 evil damage'],
        ],

      ], // end divine 3rd

      '4th' => [

        // Spiritual Anamnesis (Divine/Occult — APG)
        [
          'id'          => 'spiritual-anamnesis',
          'name'        => 'Spiritual Anamnesis',
          'level'       => 4,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Divine', 'Enchantment', 'Mental'],
          'save'        => 'Will',
          'description' => 'You flood the target\'s mind with the memories of every sin it has committed. Critical Failure: Target is Stunned 3 and takes 8d6 mental damage. Failure: Stunned 1 and 4d6 mental damage. Success: 2d6 mental damage. Critical Success: Unaffected.',
        ],

      ], // end divine 4th

      '6th' => [

        // Vampiric Exsanguination — divine tradition
        [
          'id'          => 'vampiric-exsanguination',
          'name'        => 'Vampiric Exsanguination',
          'level'       => 6,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Divine', 'Negative', 'Necromancy'],
          'save'        => 'Fortitude',
          'description' => 'You drain life from all creatures in a cone for 12d6 negative damage (Fortitude save). You regain HP equal to half the total damage dealt.',
          'heightened_scaling' => ['+1' => '+2d6 negative damage'],
        ],

        // Spirit Blast (Divine/Occult — APG)
        [
          'id'          => 'spirit-blast',
          'name'        => 'Spirit Blast',
          'level'       => 6,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Divine', 'Force', 'Necromancy'],
          'save'        => 'Fortitude',
          'description' => 'You blast the target\'s spirit with raw spiritual force, dealing 16d6 force damage regardless of resistances or immunities (Fortitude save). Constructs and undead take the full damage despite not having spirits in the usual sense.',
          'heightened_scaling' => ['+1' => '+2d6 force damage'],
        ],

      ], // end divine 6th

      '7th' => [

        // Executioner's Eyes — divine tradition
        [
          'id'          => 'executioners-eyes',
          'name'        => "Executioner's Eyes",
          'level'       => 7,
          'school'      => 'Divination',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '60 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Curse', 'Divine', 'Divination', 'Fortune', 'Misfortune'],
          'save'        => 'Will',
          'description' => 'A killing-blow vision curses the target. The next attack that would kill the target becomes a critical hit. Failure: Frightened 2 for 1 minute.',
        ],

      ], // end divine 7th

      '8th' => [

        // Devour Life — divine tradition
        [
          'id'          => 'devour-life',
          'name'        => 'Devour Life',
          'level'       => 8,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 living creature',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Divine', 'Necromancy', 'Negative'],
          'save'        => 'Fortitude',
          'description' => 'Devour the target\'s life essence for 10d6+40 negative damage; gain temporary HP equal to half damage dealt.',
        ],

      ], // end divine 8th

    ], // end divine

    // =========================================================================
    // OCCULT
    // =========================================================================
    'occult' => [

      '1st' => [

        // Animate Dead — occult tradition
        [
          'id'          => 'animate-dead',
          'name'        => 'Animate Dead',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Necromancy', 'Occult', 'Summoning'],
          'description' => 'Animate one common undead; level capped by spell rank.',
          'summon_level_cap_table' => [
            1 => -1, 2 => 1, 3 => 2, 4 => 3, 5 => 5,
            6 => 7, 7 => 9, 8 => 11, 9 => 13, 10 => 15,
          ],
        ],

        // Blood Vendetta — occult tradition
        [
          'id'          => 'blood-vendetta',
          'name'        => 'Blood Vendetta',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => 'Reaction',
          'components'  => ['Verbal'],
          'range'       => '30 feet',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Curse', 'Necromancy', 'Occult'],
          'trigger'     => 'Incoming piercing, slashing, or bleed damage to caster',
          'save'        => 'Will',
          'eligible_caster_note' => 'Caster must be able to bleed.',
          'description' => '2d6 persistent bleed on attacker (Will save). See arcane entry for full save outcomes.',
          'heightened_scaling' => ['+2' => '+2d6 persistent bleed'],
        ],

        // Vomit Swarm — occult tradition
        [
          'id'          => 'vomit-swarm',
          'name'        => 'Vomit Swarm',
          'level'       => 1,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Conjuration', 'Occult'],
          'save'        => 'Reflex (basic)',
          'description' => '2d8 piercing in 30-foot cone; fail/crit-fail = Sickened 1.',
          'sickened_on_fail' => TRUE,
          'heightened_scaling' => ['+1' => '+1d8 piercing'],
        ],

      ], // end occult 1st

      '2nd' => [

        // Enthrall — occult tradition
        [
          'id'          => 'enthrall',
          'name'        => 'Enthrall',
          'level'       => 2,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '120 feet',
          'area'        => '60-foot burst',
          'duration'    => 'sustained',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Auditory', 'Emotion', 'Enchantment', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'Fascinate creatures in area.',
        ],

        // Humanoid Form — occult tradition
        [
          'id'          => 'humanoid-form',
          'name'        => 'Humanoid Form',
          'level'       => 2,
          'school'      => 'Transmutation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'duration'    => '1 hour',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Occult', 'Polymorph', 'Transmutation'],
          'description' => 'Transform into any Medium/Small humanoid. Gain special senses; no ancestry abilities.',
        ],

      ], // end occult 2nd

      '3rd' => [

        // Déjà Vu — occult only
        [
          'id'          => 'deja-vu',
          'name'        => 'Déjà Vu',
          'level'       => 3,
          'school'      => 'Divination',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '100 feet',
          'targets'     => '1 creature',
          'traditions'  => ['occult'],
          'traits'      => ['Divination', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'Failed Will save: engine records the target\'s next-turn actions. The following turn the target must replay them identically. Illegal actions trigger Stupefied 1 per action replaced. No damage.',
          'state_machine' => [
            'record_turn' => 'Round N+1: record target\'s action sequence.',
            'replay_turn' => 'Round N+2: target replays; illegal actions trigger Stupefied 1.',
          ],
          'stupefied_fallback' => 'Each legally-unresolvable action: Stupefied 1 until end of that turn.',
        ],

        // Agonizing Despair — occult tradition
        [
          'id'          => 'agonizing-despair',
          'name'        => 'Agonizing Despair',
          'level'       => 3,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Emotion', 'Enchantment', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'Crushing despair. Critical Failure: Stunned 1 + 7d6 mental. Failure: Slowed 1 + 7d6 mental. Success: 3d6 mental. Critical Success: Unaffected.',
          'heightened_scaling' => ['+1' => '+2d6 mental damage'],
        ],

        // Bind Undead — occult tradition
        [
          'id'          => 'bind-undead',
          'name'        => 'Bind Undead',
          'level'       => 3,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 mindless undead of up to 6th level',
          'duration'    => '1 day',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Necromancy', 'Occult'],
          'description' => 'Control a mindless undead for 1 day.',
        ],

        // Chilling Darkness — occult tradition
        [
          'id'          => 'chilling-darkness',
          'name'        => 'Chilling Darkness',
          'level'       => 3,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Cold', 'Darkness', 'Evil', 'Evocation', 'Occult'],
          'save'        => 'Reflex',
          'description' => '5d6 cold + 5d6 evil; Blinded 1 round on failure, 1 minute on crit failure.',
          'heightened_scaling' => ['+1' => '+1d6 cold + 1d6 evil'],
        ],

        // Mad Monkeys — occult tradition
        [
          'id'          => 'mad-monkeys',
          'name'        => 'Mad Monkeys',
          'level'       => 3,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '10-foot burst',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['occult', 'primal'],
          'traits'      => ['Conjuration', 'Occult'],
          'description' => 'Chaotic monkey swarm; mode fixed at cast. See arcane entry for full mode definitions.',
          'mode_is_fixed_at_cast' => TRUE,
          'calm_emotions_overlay' => TRUE,
          'modes'       => ['flagrant_burglary', 'raucous_din', 'tumultuous_gymnastics'],
        ],

      ], // end occult 3rd

      '4th' => [

        // Shadow Blast — occult tradition
        [
          'id'          => 'shadow-blast',
          'name'        => 'Shadow Blast',
          'level'       => 4,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '60-foot line or 30-foot cone',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Cold', 'Darkness', 'Evocation', 'Occult', 'Shadow'],
          'save'        => 'Reflex (basic)',
          'description' => '8d6 cold in line/cone; Blinded 1 round on critical failure.',
          'heightened_scaling' => ['+1' => '+2d6 cold damage'],
        ],

        // Spiritual Anamnesis — occult tradition
        [
          'id'          => 'spiritual-anamnesis',
          'name'        => 'Spiritual Anamnesis',
          'level'       => 4,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Enchantment', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'Flood target with sinful memories. Crit Fail: Stunned 3 + 8d6 mental. Fail: Stunned 1 + 4d6. Success: 2d6.',
        ],

        // Never Mind (Occult only — APG)
        [
          'id'          => 'never-mind',
          'name'        => 'Never Mind',
          'level'       => 4,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'duration'    => '1 minute',
          'traditions'  => ['occult'],
          'traits'      => ['Enchantment', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'You plant a seed of doubt. Failure: the target forgets any single piece of information it learned within the last minute (your choice). Critical Failure: it forgets any single piece of information it has ever learned that you specify. Critical Success: Unaffected and immune for 24 hours.',
        ],

      ], // end occult 4th

      '5th' => [

        // Warp Mind — occult tradition
        [
          'id'          => 'warp-mind',
          'name'        => 'Warp Mind',
          'level'       => 5,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Emotion', 'Enchantment', 'Incapacitation', 'Mental', 'Occult'],
          'save'        => 'Will',
          'description' => 'Scramble target\'s mind. Crit Fail: permanently Confused. Fail: Confused 1 minute. Success: Confused 1 round.',
        ],

        // Dreaming Potential (Occult only — APG)
        [
          'id'          => 'dreaming-potential',
          'name'        => 'Dreaming Potential',
          'level'       => 5,
          'school'      => 'Enchantment',
          'cast'        => '10 minutes',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => 'touch',
          'targets'     => '1 sleeping or willing creature',
          'duration'    => 'until the next daily preparations',
          'traditions'  => ['occult'],
          'traits'      => ['Dream', 'Enchantment', 'Mental', 'Occult'],
          'description' => 'You guide the target through enlightening dreams. Until the next daily preparations, the target gains one skill feat they meet the prerequisites for (chosen at cast time). They retain any knowledge needed to use the feat temporarily.',
        ],

      ], // end occult 5th

      '6th' => [

        // Vampiric Exsanguination — occult tradition
        [
          'id'          => 'vampiric-exsanguination',
          'name'        => 'Vampiric Exsanguination',
          'level'       => 6,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Necromancy', 'Negative', 'Occult'],
          'save'        => 'Fortitude',
          'description' => '12d6 negative in cone; caster regains half total damage dealt.',
          'heightened_scaling' => ['+1' => '+2d6 negative'],
        ],

        // Spirit Blast — occult tradition
        [
          'id'          => 'spirit-blast',
          'name'        => 'Spirit Blast',
          'level'       => 6,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature',
          'traditions'  => ['divine', 'occult'],
          'traits'      => ['Force', 'Necromancy', 'Occult'],
          'save'        => 'Fortitude',
          'description' => '16d6 force damage bypassing all resistances; affects constructs and undead.',
          'heightened_scaling' => ['+1' => '+2d6 force'],
        ],

      ], // end occult 6th

      '7th' => [

        // Executioner's Eyes — occult tradition
        [
          'id'          => 'executioners-eyes',
          'name'        => "Executioner's Eyes",
          'level'       => 7,
          'school'      => 'Divination',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '60 feet',
          'targets'     => '1 creature',
          'duration'    => '1 round',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Curse', 'Divination', 'Fortune', 'Misfortune', 'Occult'],
          'save'        => 'Will',
          'description' => 'Death-vision curse. Next lethal attack is a critical hit. Failure: Frightened 2 for 1 minute.',
        ],

      ], // end occult 7th

      '8th' => [

        // Devour Life — occult tradition
        [
          'id'          => 'devour-life',
          'name'        => 'Devour Life',
          'level'       => 8,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 living creature',
          'traditions'  => ['arcane', 'divine', 'occult'],
          'traits'      => ['Necromancy', 'Negative', 'Occult'],
          'save'        => 'Fortitude',
          'description' => '10d6+40 negative; gain temp HP equal to half damage.',
        ],

      ], // end occult 8th

      '9th' => [

        // Cannibalize Magic — occult tradition
        [
          'id'          => 'cannibalize-magic',
          'name'        => 'Cannibalize Magic',
          'level'       => 9,
          'school'      => 'Abjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'targets'     => '1 creature with an active spell effect',
          'traditions'  => ['arcane', 'occult'],
          'traits'      => ['Abjuration', 'Occult'],
          'save'        => 'Will',
          'description' => 'Devour one active spell from target; gain temporary Focus Points equal to spell level ÷ 3 (min 1, max 3).',
        ],

        // Unfathomable Song (Occult only — APG)
        [
          'id'          => 'unfathomable-song',
          'name'        => 'Unfathomable Song',
          'level'       => 9,
          'school'      => 'Enchantment',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '60-foot emanation',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['occult'],
          'traits'      => ['Auditory', 'Enchantment', 'Fear', 'Mental', 'Occult'],
          'save'        => 'Will (each round)',
          'description' => 'You utter alien syllables that ravage the minds of those who hear them. Each round, each creature in range must attempt a Will save. Critical Failure: Confused for 1 round and takes 10d6 mental damage. Failure: 5d6 mental damage and Frightened 2. Success: Frightened 1. Critical Success: Unaffected (immune for 24 hours).',
        ],

        // Summon Entity (Occult only — APG)
        [
          'id'          => 'summon-entity',
          'name'        => 'Summon Entity',
          'level'       => 9,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['occult'],
          'traits'      => ['Conjuration', 'Occult', 'Summoning'],
          'description' => 'You summon a powerful entity (aberration, monitor, or similar) of up to level 16 to fight for you. It obeys your commands and vanishes when the spell ends.',
        ],

      ], // end occult 9th

    ], // end occult

    // =========================================================================
    // PRIMAL
    // =========================================================================
    'primal' => [

      '1st' => [

        // Blood Vendetta — primal tradition
        [
          'id'          => 'blood-vendetta',
          'name'        => 'Blood Vendetta',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => 'Reaction',
          'components'  => ['Verbal'],
          'range'       => '30 feet',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Curse', 'Necromancy', 'Primal'],
          'trigger'     => 'Incoming piercing, slashing, or bleed damage to caster',
          'save'        => 'Will',
          'eligible_caster_note' => 'Caster must be able to bleed.',
          'description' => '2d6 persistent bleed on attacker (Will save).',
          'heightened_scaling' => ['+2' => '+2d6 persistent bleed'],
        ],

        // Pummeling Rubble — primal tradition
        [
          'id'          => 'pummeling-rubble',
          'name'        => 'Pummeling Rubble',
          'level'       => 1,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '15-foot cone',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Earth', 'Evocation', 'Primal'],
          'save'        => 'Reflex',
          'description' => '2d4 bludgeoning cone; failure = pushed 5 ft, crit failure = pushed 10 ft.',
          'heightened_scaling' => ['+1' => '+2d4 bludgeoning'],
        ],

        // Vomit Swarm — primal tradition
        [
          'id'          => 'vomit-swarm',
          'name'        => 'Vomit Swarm',
          'level'       => 1,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'occult', 'primal'],
          'traits'      => ['Conjuration', 'Primal'],
          'save'        => 'Reflex (basic)',
          'description' => '2d8 piercing; fail/crit-fail = Sickened 1.',
          'sickened_on_fail' => TRUE,
          'heightened_scaling' => ['+1' => '+1d8 piercing'],
        ],

        // Goblin Pox — primal tradition
        [
          'id'          => 'goblin-pox',
          'name'        => 'Goblin Pox',
          'level'       => 1,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => 'touch',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Disease', 'Necromancy', 'Primal'],
          'save'        => 'Fortitude',
          'description' => 'Disease touch. Fail: Sickened 1 for 1 round. Crit Fail: Sickened 2 + Slowed 1 for 1 minute.',
        ],

      ], // end primal 1st

      '2nd' => [

        // Heat Metal — primal tradition
        [
          'id'          => 'heat-metal',
          'name'        => 'Heat Metal',
          'level'       => 2,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Evocation', 'Fire', 'Primal'],
          'save'        => 'Reflex',
          'description' => 'Superheat metal. 4d6 fire + 2d4 persistent fire (Reflex save) for worn/carried items or metal creatures. Release escape available. See arcane entry for full detail.',
          'heightened_scaling' => ['+1' => '+2d6 fire + +1d4 persistent fire'],
        ],

        // Summon Elemental — primal tradition
        [
          'id'          => 'summon-elemental',
          'name'        => 'Summon Elemental',
          'level'       => 2,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '30 feet',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Conjuration', 'Primal', 'Summoning'],
          'description' => 'Summon an elemental of level = spell rank − 1.',
        ],

      ], // end primal 2nd

      '3rd' => [

        // Mad Monkeys — primal tradition
        [
          'id'          => 'mad-monkeys',
          'name'        => 'Mad Monkeys',
          'level'       => 3,
          'school'      => 'Conjuration',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '10-foot burst',
          'duration'    => 'sustained up to 1 minute',
          'traditions'  => ['occult', 'primal'],
          'traits'      => ['Conjuration', 'Primal'],
          'description' => 'Monkey swarm; mode fixed at cast. See arcane entry for full mode definitions.',
          'mode_is_fixed_at_cast' => TRUE,
          'calm_emotions_overlay' => TRUE,
          'modes'       => ['flagrant_burglary', 'raucous_din', 'tumultuous_gymnastics'],
        ],

        // Howling Blizzard — primal tradition
        [
          'id'          => 'howling-blizzard',
          'name'        => 'Howling Blizzard',
          'level'       => 3,
          'school'      => 'Evocation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'area'        => '30-foot cone',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Cold', 'Evocation', 'Primal', 'Water'],
          'save'        => 'Reflex',
          'description' => '5d8 cold in 30-foot cone; failure = slowed 1 until end of next turn.',
          'heightened_scaling' => ['+1' => '+2d8 cold'],
        ],

      ], // end primal 3rd

      '4th' => [

        // Shape Stone — primal tradition
        [
          'id'          => 'shape-stone',
          'name'        => 'Shape Stone',
          'level'       => 4,
          'school'      => 'Transmutation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => 'touch',
          'duration'    => 'permanent',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Earth', 'Primal', 'Transmutation'],
          'description' => 'Reshape up to 10 cubic feet of stone. Creatures inside must Reflex save or be Grabbed.',
        ],

      ], // end primal 4th

      '5th' => [

        // Pillars of Sand — primal tradition
        [
          'id'          => 'pillars-of-sand',
          'name'        => 'Pillars of Sand',
          'level'       => 5,
          'school'      => 'Conjuration',
          'cast'        => '3 actions',
          'components'  => ['Material', 'Somatic', 'Verbal'],
          'range'       => '60 feet',
          'duration'    => '1 minute',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Conjuration', 'Earth', 'Primal'],
          'description' => 'Conjure up to 4 sand pillars (5-ft wide, 20-ft tall); creatures in squares are pushed adjacent.',
        ],

        // Mantle of the Magma Heart (Primal only — APG)
        [
          'id'          => 'mantle-of-the-magma-heart',
          'name'        => 'Mantle of the Magma Heart',
          'level'       => 5,
          'school'      => 'Transmutation',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'duration'    => '1 minute',
          'traditions'  => ['primal'],
          'traits'      => ['Fire', 'Primal', 'Transmutation'],
          'description' => 'You take on traits of living magma. You gain fire resistance 10, your unarmed strikes deal an additional 2d6 fire damage, and any creature that hits you with an unarmed or natural attack takes 2d6 fire damage. On a critical success at cast time, the resistance becomes 15.',
        ],

      ], // end primal 5th

      '8th' => [

        // Horrid Wilting — primal tradition
        [
          'id'          => 'horrid-wilting',
          'name'        => 'Horrid Wilting',
          'level'       => 8,
          'school'      => 'Necromancy',
          'cast'        => '2 actions',
          'components'  => ['Somatic', 'Verbal'],
          'range'       => '500 feet',
          'area'        => '60-foot burst',
          'traditions'  => ['arcane', 'primal'],
          'traits'      => ['Necromancy', 'Negative', 'Primal'],
          'save'        => 'Fortitude',
          'description' => '10d10 negative in 60-foot burst; basic Fortitude. Plants and water-based creatures take double damage.',
        ],

      ], // end primal 8th

    ], // end primal

  ];

  /**
   * Kobold Draconic Exemplar lookup table.
   *
   * Kobold players choose one entry at character creation. The chosen
   * dragon type drives resistance (Dragonscaled), breath weapon shape, and
   * other kobold abilities that reference the exemplar.
   *
   * Key: dragon type id. Value: mechanical properties.
   */
  const KOBOLD_DRACONIC_EXEMPLAR_TABLE = [
    'black'   => ['name' => 'Black Dragon',   'damage_type' => 'acid',        'breath_shape' => 'line',       'save' => 'reflex'],
    'blue'    => ['name' => 'Blue Dragon',    'damage_type' => 'electricity', 'breath_shape' => 'line',       'save' => 'reflex'],
    'brass'   => ['name' => 'Brass Dragon',   'damage_type' => 'fire',        'breath_shape' => 'line',       'save' => 'reflex'],
    'bronze'  => ['name' => 'Bronze Dragon',  'damage_type' => 'electricity', 'breath_shape' => 'line',       'save' => 'reflex'],
    'copper'  => ['name' => 'Copper Dragon',  'damage_type' => 'acid',        'breath_shape' => 'line',       'save' => 'reflex'],
    'gold'    => ['name' => 'Gold Dragon',    'damage_type' => 'fire',        'breath_shape' => 'cone',       'save' => 'reflex'],
    'green'   => ['name' => 'Green Dragon',   'damage_type' => 'poison',      'breath_shape' => 'cone',       'save' => 'fortitude'],
    'red'     => ['name' => 'Red Dragon',     'damage_type' => 'fire',        'breath_shape' => 'cone',       'save' => 'reflex'],
    'silver'  => ['name' => 'Silver Dragon',  'damage_type' => 'cold',        'breath_shape' => 'cone',       'save' => 'reflex'],
    'white'   => ['name' => 'White Dragon',   'damage_type' => 'cold',        'breath_shape' => 'cone',       'save' => 'reflex'],
  ];

  /**
   * APG Versatile Heritages.
   *
   * Versatile heritages occupy the heritage slot; the character has no normal
   * ancestry heritage abilities. They gain access to the versatile heritage
   * feat list PLUS their original ancestry feat list.
   *
   * Rules:
   * - All versatile heritages have the Uncommon trait (require GM approval).
   * - Sense upgrade: if the character's ancestry already grants low-light
   *   vision and the versatile heritage would also grant it, the heritage
   *   upgrades that to darkvision instead.
   * - Each character's versatile heritage feat list is independent.
   */
  const VERSATILE_HERITAGES = [
    'aasimar' => [
      'id' => 'aasimar', 'name' => 'Aasimar',
      'traits' => ['Aasimar', 'Uncommon'],
      'benefit' => 'Celestial heritage; low-light vision (upgrade rule applies)',
      'vision' => 'low-light vision',
      'vision_upgrade_if_already_low_light' => 'darkvision',
      'ancestry_feats' => [
        [
          'id' => 'lawbringer', 'name' => 'Lawbringer', 'level' => 1,
          'traits' => ['Aasimar'],
          'benefit' => 'When you succeed on a save against an emotion effect, treat it as a critical success.',
          'special' => ['save_success_upgrade' => ['effect_type' => 'emotion', 'success_to_crit' => TRUE]],
        ],
      ],
    ],
    'changeling' => [
      'id' => 'changeling', 'name' => 'Changeling',
      'traits' => ['Changeling', 'Uncommon'],
      'benefit' => 'Hag heritage; low-light vision (upgrade rule applies)',
      'vision' => 'low-light vision',
      'vision_upgrade_if_already_low_light' => 'darkvision',
      'ancestry_feats' => [
        [
          'id' => 'slag-may', 'name' => 'Slag May', 'level' => 1,
          'traits' => ['Changeling'],
          'benefit' => 'You grow a cold iron claw unarmed attack.',
          'unarmed_attack' => [
            'name' => 'claw', 'damage' => '1d6', 'type' => 'slashing',
            'group' => 'brawling',
            'traits' => ['unarmed', 'grapple'],
            'material' => 'cold iron',
          ],
        ],
      ],
    ],
    'dhampir' => [
      'id' => 'dhampir', 'name' => 'Dhampir',
      'traits' => ['Dhampir', 'Uncommon'],
      'benefit' => 'Vampire heritage; negative healing; low-light vision (upgrade rule applies)',
      'vision' => 'low-light vision',
      'vision_upgrade_if_already_low_light' => 'darkvision',
      'special' => [
        // Same negative healing semantics as Grave Orc.
        'negative_healing'      => TRUE,
        'positive_damage_heals' => FALSE,
        'negative_damage_heals' => TRUE,
        'undead_energy_rules'   => TRUE,
      ],
      'ancestry_feats' => [
        [
          'id' => 'dhampir-fangs', 'name' => 'Dhampir Fangs', 'level' => 1,
          'traits' => ['Dhampir'],
          'benefit' => 'You grow fangs, usable as an unarmed attack.',
          'unarmed_attack' => [
            'name' => 'fangs', 'damage' => '1d6', 'type' => 'piercing',
            'group' => 'brawling',
            'traits' => ['unarmed', 'grapple'],
          ],
        ],
      ],
    ],
    'duskwalker' => [
      'id' => 'duskwalker', 'name' => 'Duskwalker',
      'traits' => ['Duskwalker', 'Uncommon'],
      'benefit' => 'Psychopomp heritage; immune to becoming undead; low-light vision (upgrade rule applies)',
      'vision' => 'low-light vision',
      'vision_upgrade_if_already_low_light' => 'darkvision',
      'special' => [
        'immune_to_becoming_undead' => TRUE,
        // Detects haunts without Searching (still must meet other requirements).
        'passive_haunt_detection' => TRUE,
      ],
      'ancestry_feats' => [],
    ],
    'tiefling' => [
      'id' => 'tiefling', 'name' => 'Tiefling',
      'traits' => ['Tiefling', 'Uncommon'],
      'benefit' => 'Fiend heritage; low-light vision (upgrade rule applies)',
      'vision' => 'low-light vision',
      'vision_upgrade_if_already_low_light' => 'darkvision',
      'ancestry_feats' => [],
    ],
  ];

  /**
   * APG Archetypes (Chapter 3).
   *
   * Structure per archetype:
   *   id, name, type (martial|skill|magic), dedication (feat entry),
   *   rule (system-level rules applied at selection time),
   *   feats[] (non-dedication archetype feats)
   *
   * Archetype system rules enforced at selection time:
   *   1) Dedication feat is L2+ and requires a class feat slot.
   *   2) Cannot select a second Dedication from the same archetype until
   *      2 other feats from that archetype are taken ("2-before-another").
   *   3) Proficiency grants from Dedication feats are capped at the
   *      character's current class maximums.
   */
  const ARCHETYPES = [

    // ─── Martial / Combat ────────────────────────────────────────────────────

    'acrobat' => [
      'id' => 'acrobat', 'name' => 'Acrobat', 'type' => 'martial',
      'dedication' => [
        'id' => 'acrobat-dedication', 'name' => 'Acrobat Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Acrobatics',
        'benefit' => 'Become Expert in Acrobatics (or increase by one step).',
        'grants' => ['acrobatics_proficiency' => 'expert'],
      ],
      'feats' => [
        ['id' => 'acrobat-tumble-through-crit', 'name' => 'Tumbling Strike', 'level' => 4,
         'benefit' => 'Crit Tumble Through ignores difficult terrain for the move.',
         'special' => ['crit_tumble_through_ignores_difficult_terrain' => TRUE]],
        ['id' => 'acrobat-master-acrobatics', 'name' => 'Master Acrobatics', 'level' => 7,
         'benefit' => 'Become Master in Acrobatics.',
         'grants' => ['acrobatics_proficiency' => 'master']],
        ['id' => 'acrobat-legendary-acrobatics', 'name' => 'Legendary Acrobatics', 'level' => 15,
         'benefit' => 'Become Legendary in Acrobatics.',
         'grants' => ['acrobatics_proficiency' => 'legendary']],
      ],
    ],

    'archer' => [
      'id' => 'archer', 'name' => 'Archer', 'type' => 'martial',
      'dedication' => [
        'id' => 'archer-dedication', 'name' => 'Archer Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Become Trained in all simple and martial bows.',
        'grants' => ['weapon_training' => ['bows_simple', 'bows_martial']],
        'special' => [
          // Bow proficiency scales at the same levels as class weapon proficiency.
          'bow_proficiency_scales_with_class' => TRUE,
          // When Expert in a bow, gain its crit specialization.
          'expert_bow_crit_specialization' => TRUE,
        ],
      ],
      'feats' => [],
    ],

    'assassin' => [
      'id' => 'assassin', 'name' => 'Assassin', 'type' => 'martial',
      'dedication' => [
        'id' => 'assassin-dedication', 'name' => 'Assassin Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Stealth',
        'benefit' => 'Gain Mark for Death (3-action); +2 circumstance to Seek/Feint vs. mark; agile/finesse/unarmed attacks vs. mark gain backstabber + deadly d6 (or upgrade existing deadly die).',
        'grants' => [
          'mark_for_death' => [
            'action_cost' => 3,
            'max_marks'   => 1,
          ],
        ],
        'special' => [
          'mark_bonus_seek_feint'       => ['type' => 'circumstance', 'value' => 2],
          'mark_weapon_bonus'           => [
            'apply_to_traits' => ['agile', 'finesse', 'unarmed'],
            'grants_backstabber' => TRUE,
            'grants_deadly'      => 'd6',
            'deadly_upgrade_if_existing' => TRUE,
          ],
        ],
      ],
      'feats' => [],
    ],

    'bastion' => [
      'id' => 'bastion', 'name' => 'Bastion', 'type' => 'martial',
      'dedication' => [
        'id' => 'bastion-dedication', 'name' => 'Bastion Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in light or heavy armor',
        'benefit' => 'Gain the Reactive Shield fighter feat; satisfies Reactive Shield prerequisites.',
        'grants' => ['feat' => 'reactive-shield'],
        'special' => ['satisfies_reactive_shield_prereqs' => TRUE],
      ],
      'feats' => [],
    ],

    'cavalier' => [
      'id' => 'cavalier', 'name' => 'Cavalier', 'type' => 'martial',
      'dedication' => [
        'id' => 'cavalier-dedication', 'name' => 'Cavalier Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Gain mount training; mount-based combat bonuses per feat chain.',
        'special' => [
          // Requires a mount to be present at time of mounted actions.
          'requires_mount'       => TRUE,
          'mount_dependency_flag' => 'mount_system_required',
        ],
      ],
      'feats' => [],
    ],

    'dragon-disciple' => [
      'id' => 'dragon-disciple', 'name' => 'Dragon Disciple', 'type' => 'martial',
      'dedication' => [
        'id' => 'dragon-disciple-dedication', 'name' => 'Dragon Disciple Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Sorcerer with draconic bloodline, or ability to cast spells',
        'benefit' => 'Begin draconic transformation chain; breath weapon and physical dragon traits gained via feats.',
        'special' => ['draconic_transformation_chain' => TRUE],
      ],
      'feats' => [],
    ],

    'dual-weapon-warrior' => [
      'id' => 'dual-weapon-warrior', 'name' => 'Dual-Weapon Warrior', 'type' => 'martial',
      'dedication' => [
        'id' => 'dual-weapon-warrior-dedication', 'name' => 'Dual-Weapon Warrior Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in all simple and martial weapons',
        'benefit' => 'Gain dual weapon attack benefits; two-weapon fighting bonuses.',
        'special' => ['dual_weapon_fighting' => TRUE],
      ],
      'feats' => [],
    ],

    'duelist' => [
      'id' => 'duelist', 'name' => 'Duelist', 'type' => 'martial',
      'dedication' => [
        'id' => 'duelist-dedication', 'name' => 'Duelist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Expert in a one-handed melee weapon',
        'benefit' => 'Precise dueling bonuses with one-handed weapons.',
        'special' => ['one_handed_weapon_focus' => TRUE],
      ],
      'feats' => [],
    ],

    'eldritch-archer' => [
      'id' => 'eldritch-archer', 'name' => 'Eldritch Archer', 'type' => 'martial',
      'dedication' => [
        'id' => 'eldritch-archer-dedication', 'name' => 'Eldritch Archer Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Expert in a bow; ability to cast spells',
        'benefit' => 'Imbue arrows with spells; ranged spell delivery options.',
        'special' => ['ranged_spell_delivery' => TRUE],
      ],
      'feats' => [],
    ],

    'gladiator' => [
      'id' => 'gladiator', 'name' => 'Gladiator', 'type' => 'martial',
      'dedication' => [
        'id' => 'gladiator-dedication', 'name' => 'Gladiator Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Performance',
        'benefit' => 'Crowd-fighting bonuses; demoralize enhancements.',
        'special' => ['demoralize_enhancement' => TRUE, 'crowd_fighting_bonuses' => TRUE],
      ],
      'feats' => [],
    ],

    'marshal' => [
      'id' => 'marshal', 'name' => 'Marshal', 'type' => 'martial',
      'dedication' => [
        'id' => 'marshal-dedication', 'name' => 'Marshal Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Diplomacy or Intimidation',
        'benefit' => '1-action aura (10-ft emanation): choose on activation — allies gain +1 circumstance to attack rolls OR +1 status bonus to saves.',
        'grants' => [
          'marshal_aura' => [
            'action_cost'  => 1,
            'range'        => '10-ft emanation',
            'choice_on_activation' => ['attack_circumstance_bonus' => 1, 'save_status_bonus' => 1],
          ],
        ],
      ],
      'feats' => [],
    ],

    'martial-artist' => [
      'id' => 'martial-artist', 'name' => 'Martial Artist', 'type' => 'martial',
      'dedication' => [
        'id' => 'martial-artist-dedication', 'name' => 'Martial Artist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in unarmed attacks',
        'benefit' => 'Unarmed attack proficiency bump; ki spell options via feats.',
        'grants' => ['unarmed_proficiency_bump' => 1],
        'special' => ['ki_spell_options' => TRUE],
      ],
      'feats' => [],
    ],

    'mauler' => [
      'id' => 'mauler', 'name' => 'Mauler', 'type' => 'martial',
      'dedication' => [
        'id' => 'mauler-dedication', 'name' => 'Mauler Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in all simple and martial weapons',
        'benefit' => 'Two-handed weapon focus; damage-focused feat chain.',
        'special' => ['two_handed_weapon_focus' => TRUE],
      ],
      'feats' => [],
    ],

    'sentinel' => [
      'id' => 'sentinel', 'name' => 'Sentinel', 'type' => 'martial',
      'dedication' => [
        'id' => 'sentinel-dedication', 'name' => 'Sentinel Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Become Trained in all armor including heavy; access heavy armor without prerequisites.',
        'grants' => ['armor_training' => ['light', 'medium', 'heavy']],
      ],
      'feats' => [],
    ],

    'viking' => [
      'id' => 'viking', 'name' => 'Viking', 'type' => 'martial',
      'dedication' => [
        'id' => 'viking-dedication', 'name' => 'Viking Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in a shield',
        'benefit' => 'Shield-focused abilities; brutal strike enhancements.',
        'special' => ['shield_focus' => TRUE, 'brutal_strike_enhancement' => TRUE],
      ],
      'feats' => [],
    ],

    'weapon-improviser' => [
      'id' => 'weapon-improviser', 'name' => 'Weapon Improviser', 'type' => 'martial',
      'dedication' => [
        'id' => 'weapon-improviser-dedication', 'name' => 'Weapon Improviser Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Improvised weapon proficiency; improvised weapons gain additional traits.',
        'grants' => ['improvised_weapon_proficiency' => 'trained'],
        'special' => ['improvised_weapon_trait_bonus' => TRUE],
      ],
      'feats' => [],
    ],

    // ─── Skill / Social ───────────────────────────────────────────────────────

    'archaeologist' => [
      'id' => 'archaeologist', 'name' => 'Archaeologist', 'type' => 'skill',
      'dedication' => [
        'id' => 'archaeologist-dedication', 'name' => 'Archaeologist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Society and Thievery',
        'benefit' => 'Become Expert in Society and Expert in Thievery; +1 circumstance to Recall Knowledge on ancient or historical subjects.',
        'grants' => [
          'society_proficiency'  => 'expert',
          'thievery_proficiency' => 'expert',
        ],
        'special' => [
          'recall_knowledge_bonus' => [
            'type'   => 'circumstance',
            'value'  => 1,
            'filter' => ['ancient', 'historical'],
          ],
        ],
      ],
      'feats' => [],
    ],

    'bounty-hunter' => [
      'id' => 'bounty-hunter', 'name' => 'Bounty Hunter', 'type' => 'skill',
      'dedication' => [
        'id' => 'bounty-hunter-dedication', 'name' => 'Bounty Hunter Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Survival',
        'benefit' => 'Gain Hunt Prey (Ranger feature restricted to known creatures); +2 circumstance to Gather Information about prey.',
        'grants' => ['feat' => 'hunt-prey'],
        'special' => [
          'hunt_prey_target_must_be_known' => TRUE,
          'gather_information_prey_bonus'  => ['type' => 'circumstance', 'value' => 2],
        ],
      ],
      'feats' => [],
    ],

    'celebrity' => [
      'id' => 'celebrity', 'name' => 'Celebrity', 'type' => 'skill',
      'dedication' => [
        'id' => 'celebrity-dedication', 'name' => 'Celebrity Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Expert in Performance',
        'benefit' => 'Fame/recognition mechanics; Perform-based social benefits.',
        'special' => ['performance_social_benefits' => TRUE],
      ],
      'feats' => [],
    ],

    'dandy' => [
      'id' => 'dandy', 'name' => 'Dandy', 'type' => 'skill',
      'dedication' => [
        'id' => 'dandy-dedication', 'name' => 'Dandy Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Society',
        'benefit' => 'Social manipulation; bonuses to Make an Impression.',
        'special' => ['make_an_impression_bonus' => TRUE],
      ],
      'feats' => [],
    ],

    'horizon-walker' => [
      'id' => 'horizon-walker', 'name' => 'Horizon Walker', 'type' => 'skill',
      'dedication' => [
        'id' => 'horizon-walker-dedication', 'name' => 'Horizon Walker Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Survival',
        'benefit' => 'Terrain movement bonuses; Trackless Step options via feats.',
        'special' => ['terrain_movement_bonuses' => TRUE],
      ],
      'feats' => [],
    ],

    'linguist' => [
      'id' => 'linguist', 'name' => 'Linguist', 'type' => 'skill',
      'dedication' => [
        'id' => 'linguist-dedication', 'name' => 'Linguist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Society',
        'benefit' => 'Gain 2 bonus languages; accelerated language learning.',
        'grants' => ['bonus_languages' => 2],
        'special' => ['accelerated_language_learning' => TRUE],
      ],
      'feats' => [],
    ],

    'loremaster' => [
      'id' => 'loremaster', 'name' => 'Loremaster', 'type' => 'skill',
      'dedication' => [
        'id' => 'loremaster-dedication', 'name' => 'Loremaster Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in two or more Lore skills',
        'benefit' => 'Recall Knowledge bonuses; secret lore access via feats.',
        'special' => ['recall_knowledge_bonuses' => TRUE],
      ],
      'feats' => [],
    ],

    'pirate' => [
      'id' => 'pirate', 'name' => 'Pirate', 'type' => 'skill',
      'dedication' => [
        'id' => 'pirate-dedication', 'name' => 'Pirate Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Athletics and Intimidation',
        'benefit' => 'Ship combat bonuses; nautical action access.',
        'special' => ['nautical_actions' => TRUE],
      ],
      'feats' => [],
    ],

    'scout' => [
      'id' => 'scout', 'name' => 'Scout', 'type' => 'skill',
      'dedication' => [
        'id' => 'scout-dedication', 'name' => 'Scout Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Stealth and Survival',
        'benefit' => '+2 circumstance to initiative when using Stealth; Avoid Notice enhancements.',
        'grants' => [
          'initiative_stealth_bonus' => ['type' => 'circumstance', 'value' => 2],
        ],
        'special' => ['avoid_notice_enhancement' => TRUE],
      ],
      'feats' => [],
    ],

    // ─── Magic / Hybrid ───────────────────────────────────────────────────────

    'beastmaster' => [
      'id' => 'beastmaster', 'name' => 'Beastmaster', 'type' => 'magic',
      'dedication' => [
        'id' => 'beastmaster-dedication', 'name' => 'Beastmaster Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Gain a young animal companion; stackable with existing companion. Gain Call Companion 1-action (switch active companion; available only with ≥2 companions). Cha-based primal focus pool (1 FP); Refocus by tending companion.',
        'grants' => [
          'animal_companion' => ['type' => 'young'],
          'focus_pool'       => ['size' => 1, 'tradition' => 'primal', 'ability' => 'cha'],
        ],
        'special' => [
          'call_companion' => [
            'action_cost'       => 1,
            'requires_companions' => 2,
          ],
          'refocus_method' => 'tend_companion',
        ],
      ],
      'feats' => [],
    ],

    'blessed-one' => [
      'id' => 'blessed-one', 'name' => 'Blessed One', 'type' => 'magic',
      'dedication' => [
        'id' => 'blessed-one-dedication', 'name' => 'Blessed One Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        // Available to ALL classes — not gated behind divine spellcasting.
        'all_classes' => TRUE,
        'benefit' => 'Gain Lay on Hands (divine devotion spell); creates focus pool of 1 FP. Refocus via 10-min meditation.',
        'grants' => [
          'devotion_spell' => 'lay-on-hands',
          'focus_pool'     => ['size' => 1, 'tradition' => 'divine'],
        ],
        'special' => ['refocus_method' => '10_min_meditation'],
      ],
      'feats' => [],
    ],

    'familiar-master' => [
      'id' => 'familiar-master', 'name' => 'Familiar Master', 'type' => 'magic',
      'dedication' => [
        'id' => 'familiar-master-dedication', 'name' => 'Familiar Master Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => '',
        'benefit' => 'Gain a familiar even without a class that normally grants one; uses standard familiar rules.',
        'grants' => ['familiar' => TRUE],
      ],
      'feats' => [],
    ],

    'herbalist' => [
      'id' => 'herbalist', 'name' => 'Herbalist', 'type' => 'magic',
      'dedication' => [
        'id' => 'herbalist-dedication', 'name' => 'Herbalist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Nature',
        'benefit' => 'Advanced healing items; herbal preparation actions.',
        'special' => ['herbal_preparation' => TRUE],
      ],
      'feats' => [],
    ],

    'medic' => [
      'id' => 'medic', 'name' => 'Medic', 'type' => 'magic',
      'dedication' => [
        'id' => 'medic-dedication', 'name' => 'Medic Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Medicine',
        'benefit' => 'Battle Medicine improvements; expanded healing feat chain.',
        'special' => ['battle_medicine_enhancement' => TRUE],
      ],
      'feats' => [],
    ],

    'poisoner' => [
      'id' => 'poisoner', 'name' => 'Poisoner', 'type' => 'magic',
      'dedication' => [
        'id' => 'poisoner-dedication', 'name' => 'Poisoner Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Crafting',
        'benefit' => 'Poison application improvements; poison DC scaling.',
        'special' => ['poison_dc_scaling' => TRUE, 'poison_application_improvement' => TRUE],
      ],
      'feats' => [],
    ],

    'ritualist' => [
      'id' => 'ritualist', 'name' => 'Ritualist', 'type' => 'magic',
      'dedication' => [
        'id' => 'ritualist-dedication', 'name' => 'Ritualist Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in one of the skills used for rituals',
        'benefit' => 'Cast rituals without class spellcasting; ritual casting modifier uses a chosen skill.',
        'special' => [
          // Character does not need class spellcasting to perform rituals.
          'no_spellcasting_required' => TRUE,
          'ritual_modifier_skill'    => 'player_choice',
        ],
      ],
      'feats' => [],
    ],

    'scroll-trickster' => [
      'id' => 'scroll-trickster', 'name' => 'Scroll Trickster', 'type' => 'magic',
      'dedication' => [
        'id' => 'scroll-trickster-dedication', 'name' => 'Scroll Trickster Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Arcana, Nature, Occultism, or Religion',
        'benefit' => 'Use Magic Item for scrolls without tradition match; improvised spellcasting.',
        'special' => ['scroll_tradition_mismatch_allowed' => TRUE],
      ],
      'feats' => [],
    ],

    'scrounger' => [
      'id' => 'scrounger', 'name' => 'Scrounger', 'type' => 'magic',
      'dedication' => [
        'id' => 'scrounger-dedication', 'name' => 'Scrounger Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Crafting',
        'benefit' => 'Improvised item creation from found materials; Craft without kits.',
        'special' => ['craft_without_kit' => TRUE],
      ],
      'feats' => [],
    ],

    'shadowdancer' => [
      'id' => 'shadowdancer', 'name' => 'Shadowdancer', 'type' => 'magic',
      'dedication' => [
        'id' => 'shadowdancer-dedication', 'name' => 'Shadowdancer Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Master in Stealth, trained in Performance and Acrobatics',
        'benefit' => 'Shadow jump/teleport options; shadow-based stealth bonuses.',
        'special' => ['shadow_teleport' => TRUE, 'shadow_stealth_bonus' => TRUE],
      ],
      'feats' => [],
    ],

    'snarecrafter' => [
      'id' => 'snarecrafter', 'name' => 'Snarecrafter', 'type' => 'magic',
      'dedication' => [
        'id' => 'snarecrafter-dedication', 'name' => 'Snarecrafter Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Crafting',
        'benefit' => 'Snare crafting time reduction; snare feat access.',
        'special' => ['snare_craft_time_reduction' => TRUE],
      ],
      'feats' => [],
    ],

    'talisman-dabbler' => [
      'id' => 'talisman-dabbler', 'name' => 'Talisman Dabbler', 'type' => 'magic',
      'dedication' => [
        'id' => 'talisman-dabbler-dedication', 'name' => 'Talisman Dabbler Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Occultism or Arcana',
        'benefit' => 'Attach talismans faster; affix without proficiency restrictions.',
        'special' => ['talisman_affix_without_proficiency' => TRUE, 'talisman_attach_speed' => TRUE],
      ],
      'feats' => [],
    ],

    'vigilante' => [
      'id' => 'vigilante', 'name' => 'Vigilante', 'type' => 'magic',
      'dedication' => [
        'id' => 'vigilante-dedication', 'name' => 'Vigilante Dedication',
        'level' => 2, 'traits' => ['Archetype', 'Dedication'],
        'prerequisites' => 'Trained in Deception',
        'benefit' => 'Dual identity mechanics (social/vigilante personas); Perception-based identity protection.',
        'special' => [
          'dual_identity'              => TRUE,
          'identity_protection'        => ['check' => 'Perception', 'mode' => 'opposed'],
          'social_persona_maintained'  => TRUE,
        ],
      ],
      'feats' => [],
    ],

  ];

  /**
   * Archetype system rules (enforced at character creation / feat selection).
   *
   * These are referenced by the character builder when evaluating feat choices.
   */
  const ARCHETYPE_RULES = [
    // Dedication feats are class feats selected at L2+.
    'dedication_min_level'         => 2,
    'dedication_uses_class_feat'   => TRUE,
    // Must take 2 feats from an archetype before a second Dedication from it.
    'two_before_another_dedication' => TRUE,
    // Proficiency grants from Dedication feats are capped at class maximums.
    'proficiency_capped_by_class'  => TRUE,
  ];

  /**
   * PF2e General Feats (Level 1).
   * Available to all characters at 1st level.
   */
  const GENERAL_FEATS = [
    ['id' => 'adopted-ancestry', 'name' => 'Adopted Ancestry', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You were raised by or have deep ties to an ancestry other than your own. Choose a common ancestry. You can select ancestry feats from that ancestry as if it were your own.'],
    ['id' => 'armor-proficiency', 'name' => 'Armor Proficiency', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You become trained in light armor. If you were already trained in light armor, you become trained in medium armor. If you were trained in both, you become trained in heavy armor.'],
    ['id' => 'breath-control', 'name' => 'Breath Control', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You have incredible breath control. You can hold your breath for 25× as long as usual (typically 25 minutes). You gain a +1 circumstance bonus to saving throws against inhaled threats.'],
    ['id' => 'canny-acumen', 'name' => 'Canny Acumen', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'Your avoidance or observation is beyond the norm. Choose Fortitude saves, Reflex saves, Will saves, or Perception. You become an expert in your choice.'],
    ['id' => 'diehard', 'name' => 'Diehard', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'It takes more to kill you than most. You die from the dying condition at dying 5, rather than dying 4.'],
    ['id' => 'fast-recovery', 'name' => 'Fast Recovery', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => 'Constitution 14',
      'benefit' => 'Your body quickly recovers from maladies. You regain twice as many Hit Points from resting. Each time you succeed at a Fortitude save against an ongoing disease or poison, reduce its stage by 2 instead of 1.'],
    ['id' => 'feather-step', 'name' => 'Feather Step', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => 'Dexterity 14',
      'benefit' => 'You step carefully and nimbly. You can Step into difficult terrain.'],
    ['id' => 'fleet', 'name' => 'Fleet', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You move more quickly on foot. Your Speed increases by 5 feet.'],
    ['id' => 'incredible-initiative', 'name' => 'Incredible Initiative', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You react more quickly than others can. You gain a +2 circumstance bonus to initiative rolls.'],
    ['id' => 'ride', 'name' => 'Ride', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'When you Command an Animal mount to move, you automatically succeed instead of making a check. You do not take the -2 circumstance penalty to attacks while mounted.'],
    ['id' => 'shield-block', 'name' => 'Shield Block', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You snap your shield in place to ward off a blow. Your shield prevents you from taking an amount of damage equal to the shield\'s Hardness. Both you and the shield take any remaining damage.'],
    ['id' => 'toughness', 'name' => 'Toughness', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You can withstand more punishment than most. Increase your maximum Hit Points by your level. The DC of recovery checks is equal to 9 + your dying condition value.'],
    ['id' => 'weapon-proficiency', 'name' => 'Weapon Proficiency', 'level' => 1, 'traits' => ['General'],
      'prerequisites' => '',
      'benefit' => 'You become trained in all simple weapons. If you were already trained in simple weapons, you become trained in all martial weapons. If you were trained in both, choose one advanced weapon to become trained in.'],
  ];

  /**
   * PF2e Skill Feats (Level 1).
   * Available to characters who have training in the prerequisite skill.
   * The background grants one skill feat automatically; this list is for
   * reference and future expansion when users can pick additional skill feats.
   */
  const SKILL_FEATS = [
    ['id' => 'assurance', 'name' => 'Assurance', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Any',
      'benefit' => 'You can forgo rolling a skill check to instead receive a result of 10 + your proficiency bonus (don\'t apply any other modifiers). Choose a skill you are trained in when you select this feat.'],
    ['id' => 'bargain-hunter', 'name' => 'Bargain Hunter', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Diplomacy',
      'benefit' => 'You can use Diplomacy to Earn Income by wheeling and dealing. When in a settlement, you spend 1 extra day of downtime to haggle and get a 10% discount on an item.'],
    ['id' => 'cat-fall', 'name' => 'Cat Fall', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Acrobatics',
      'benefit' => 'Your catlike reflexes allow you to treat falls shorter by 10 feet. If you are an expert in Acrobatics, treat them as 25 feet shorter; master, 50 feet shorter.'],
    ['id' => 'charming-liar', 'name' => 'Charming Liar', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Deception',
      'benefit' => 'Your charm makes your lies more convincing. When you successfully Lie, the target\'s attitude toward you improves by one step as if you had used Diplomacy to Make an Impression.'],
    ['id' => 'combat-climber', 'name' => 'Combat Climber', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'Your climbing skills prepare you for combat. You don\'t need a free hand to Climb, and you aren\'t flat-footed while Climbing.'],
    ['id' => 'courtly-graces', 'name' => 'Courtly Graces', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Society',
      'benefit' => 'You were raised among the nobility or studied court etiquette. You can use Society to Make an Impression on a noble and to Gather Information in a court setting.'],
    ['id' => 'experienced-smuggler', 'name' => 'Experienced Smuggler', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Stealth',
      'benefit' => 'You know just how to hide your contraband. When Concealing an Object, your Stealth DC is increased by 2. When the GM rolls your Stealth check, they use a secret check.'],
    ['id' => 'experienced-tracker', 'name' => 'Experienced Tracker', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Survival',
      'benefit' => 'Tracking is second nature to you. You can track while moving at full Speed, and you don\'t need to attempt a new check every hour while tracking.'],
    ['id' => 'fascinating-performance', 'name' => 'Fascinating Performance', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Performance',
      'benefit' => 'When you Perform, compare your result to the Will DC of one observer. If you succeed, the target is fascinated for 1 round. This is an emotion and mental effect.'],
    ['id' => 'forager', 'name' => 'Forager', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Survival',
      'benefit' => 'You know how to provide for yourself in the wild. You can use Survival to Subsist and find food in the wild, providing for up to 4 more creatures.'],
    ['id' => 'group-impression', 'name' => 'Group Impression', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Diplomacy',
      'benefit' => 'When you Make an Impression, you can compare your Diplomacy check result to the Will DCs of up to 4 targets instead of 1. It takes you 1 minute to Make an Impression on this many people.'],
    ['id' => 'hefty-hauler', 'name' => 'Hefty Hauler', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'You can carry more than most. Increase your maximum and encumbered Bulk limits by 2.'],
    ['id' => 'hobnobber', 'name' => 'Hobnobber', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Diplomacy',
      'benefit' => 'You are skilled at learning information through conversation. Gathering Information normally takes about half a day; you can do it in about 2 hours. If you are an expert or better, you can do it even faster.'],
    ['id' => 'intimidating-glare', 'name' => 'Intimidating Glare', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Intimidation',
      'benefit' => 'You can Demoralize with a mere glare. When Demoralizing, you can target a creature that doesn\'t share a language with you or that can\'t hear you. You do not take the -4 circumstance penalty for not sharing a language.'],
    ['id' => 'lengthy-diversion', 'name' => 'Lengthy Diversion', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Deception',
      'benefit' => 'When you Create a Diversion, you continue to remain hidden after the end of your turn. This lasts for 1 minute or until you do anything except Step or use the Hide or the Sneak action.'],
    ['id' => 'lie-to-me', 'name' => 'Lie to Me', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Deception',
      'benefit' => 'You can use Deception instead of Perception to detect someone\'s dishonesty. Your Deception DC is used as the DC for the check.'],
    ['id' => 'multilingual', 'name' => 'Multilingual', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Society',
      'benefit' => 'You easily pick up new languages. You learn two new languages of your choice. You must be trained in Society.'],
    ['id' => 'natural-medicine', 'name' => 'Natural Medicine', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Nature',
      'benefit' => 'You can apply natural remedies to heal. You can use Nature instead of Medicine to Treat Wounds in wilderness environments. If you are in wilderness and using fresh ingredients, you gain a +2 circumstance bonus.'],
    ['id' => 'oddity-identification', 'name' => 'Oddity Identification', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Occultism',
      'benefit' => 'You have a sense for the bizarre. You gain a +2 circumstance bonus to Occultism checks to Identify Magic with the mental, possession, prediction, or scrying traits.'],
    ['id' => 'pickpocket', 'name' => 'Pickpocket', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Thievery',
      'benefit' => 'You can Steal or Palm an Object that\'s closely guarded without taking the -5 penalty. You are also more difficult to detect when pickpocketing.'],
    ['id' => 'quick-identification', 'name' => 'Quick Identification', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Arcana',
      'benefit' => 'You can Identify Magic swiftly. You can Identify Magic in a single action instead of 10 minutes if the item or effect is common and you are trained in the appropriate tradition\'s skill.'],
    ['id' => 'quick-jump', 'name' => 'Quick Jump', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'You can use High Jump and Long Jump as a single action instead of 2 actions. If you do, you don\'t perform the initial Stride.'],
    ['id' => 'rapid-mantel', 'name' => 'Rapid Mantel', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'You easily pull yourself onto ledges. When you Grab an Edge and succeed, you can pull yourself up as a free action instead of a Climb action.'],
    ['id' => 'read-lips', 'name' => 'Read Lips', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Society',
      'benefit' => 'You can read lips of someone you can see speaking a language you know. This requires a Society check against a standard DC for the language. In combat, this is harder (secret check by the GM).'],
    ['id' => 'recognize-spell', 'name' => 'Recognize Spell', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Arcana',
      'benefit' => 'If you are trained in the appropriate tradition\'s skill, you can use a reaction to attempt to Recognize a Spell when someone casts it. You use Arcana for arcane, Nature for primal, Occultism for occult, or Religion for divine.'],
    ['id' => 'sign-language', 'name' => 'Sign Language', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Society',
      'benefit' => 'You know sign language and can communicate silently so long as you and any creatures you communicate with have a free hand. Sign language isn\'t a language itself but lets you use any language you know in signed form.'],
    ['id' => 'snare-crafting', 'name' => 'Snare Crafting', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Crafting',
      'benefit' => 'You can use the Craft activity to create snares. When you select this feat, you add the formulas for four common snares to your formula book.'],
    ['id' => 'specialty-crafting', 'name' => 'Specialty Crafting', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Crafting',
      'benefit' => 'Choose a specialty from alchemy, artistry, bookmaking, glassmaking, leatherworking, pottery, shipbuilding, stonemasonry, tailoring, and woodworking. You gain a +1 circumstance bonus to Craft checks for items of that type.'],
    ['id' => 'steady-balance', 'name' => 'Steady Balance', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Acrobatics',
      'benefit' => 'You can keep your balance easily. Whenever you roll a success on an Acrobatics check to Balance, you get a critical success instead. You\'re not flat-footed while Balancing on narrow surfaces.'],
    ['id' => 'streetwise', 'name' => 'Streetwise', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Society',
      'benefit' => 'You know about life on the streets and feel the pulse of your local settlement. You can use Society to Gather Information and to Recall Knowledge about local history, rumors, and organizations in settlements of your size or smaller.'],
    ['id' => 'student-of-the-canon', 'name' => 'Student of the Canon', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Religion',
      'benefit' => 'You studied religious texts extensively. When you Recall Knowledge about religions, religious history, divine effects, or related topics using Religion, you get a critical success on a success, and on a critical failure you get a failure instead.'],
    ['id' => 'subtle-theft', 'name' => 'Subtle Theft', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Thievery',
      'benefit' => 'When you successfully Steal or Palm an Object, observers take a -2 circumstance penalty to their Perception DC to detect the theft.'],
    ['id' => 'survey-wildlife', 'name' => 'Survey Wildlife', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Survival',
      'benefit' => 'You can study an area to find what creatures live there. You can spend 10 minutes in any outdoor area to learn about the creatures living there, gaining a +2 circumstance bonus to Recall Knowledge about local wildlife.'],
    ['id' => 'terrain-expertise', 'name' => 'Terrain Expertise', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Survival',
      'benefit' => 'Choose a specific type of terrain (aquatic, arctic, desert, forest, mountain, plains, sky, swamp, or underground). You gain a +1 circumstance bonus to Survival checks in that terrain.'],
    ['id' => 'titan-wrestler', 'name' => 'Titan Wrestler', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'You can attempt to Disarm, Grapple, Shove, or Trip creatures up to two sizes larger than you, or up to three sizes larger than you if you are a master in Athletics.'],
    ['id' => 'train-animal', 'name' => 'Train Animal', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Nature',
      'benefit' => 'You spend time teaching an animal to do certain things. Choose a young or companion animal. You can spend a week of downtime trying to train the animal to perform a trick, using a Nature check against a DC determined by the GM.'],
    ['id' => 'trick-magic-item', 'name' => 'Trick Magic Item', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Arcana',
      'benefit' => 'You examine a magic item. You can try to Activate a magic item that normally requires a tradition or belief. If you succeed at a check using the relevant skill, you can use the item as if you could normally use it.'],
    ['id' => 'underwater-marauder', 'name' => 'Underwater Marauder', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Athletics',
      'benefit' => 'You\'ve learned to fight underwater. You don\'t take the normal penalties for using bludgeoning or slashing melee weapons underwater or for attacking with ranged weapons underwater.'],
    ['id' => 'virtuosic-performer', 'name' => 'Virtuosic Performer', 'level' => 1, 'traits' => ['General', 'Skill'], 'skill' => 'Performance',
      'benefit' => 'You have exceptional talent in one type of performance. Choose a performance type such as dancing, singing, or acting. You gain a +1 circumstance bonus to Performance checks for that type.'],
  ];

  /**
   * PF2e class-specific auto-apply features per level (no player choice required).
   *
   * Keys: class_name → level (int) → ['auto_features' => [...]]
   * Universal advancement (feat slots, ability boosts, skill increases) is computed
   * by getClassAdvancement() and does NOT live here.
   */
  const CLASS_ADVANCEMENT = [
    'fighter' => [
      1  => ['auto_features' => [
        ['id' => 'attack-of-opportunity', 'name' => 'Attack of Opportunity',
          'description' => 'You react to an opening from a foe. You can use a reaction to make a melee Strike against a triggering creature.'],
        ['id' => 'fighter-weapon-training', 'name' => 'Fighter Weapon Training',
          'description' => 'You are trained with all simple and martial weapons, and with all advanced weapons in one weapon group of your choice.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'bravery', 'name' => 'Bravery',
          'description' => 'You gain a +1 circumstance bonus to Will saves against fear effects and to your Will DC against attempts to Demoralize you.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'fighter-weapon-mastery', 'name' => 'Fighter Weapon Mastery',
          'description' => 'Your proficiency rank with your chosen weapon group increases to Master.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'battlefield-surveyor', 'name' => 'Battlefield Surveyor',
          'description' => 'You gain a +2 circumstance bonus to Perception checks for Initiative.'],
        ['id' => 'weapon-specialization', 'name' => 'Weapon Specialization',
          'description' => 'You deal additional damage equal to half your weapon proficiency rank (minimum 1) with any weapon you are trained in.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'combat-flexibility', 'name' => 'Combat Flexibility',
          'description' => 'Once per day when you prepare, you can gain a fighter feat of 8th level or lower that you don\'t already have.'],
        ['id' => 'juggernaut', 'name' => 'Juggernaut',
          'description' => 'Your Fortitude saving throw proficiency increases to Master.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'armor-expertise', 'name' => 'Armor Expertise',
          'description' => 'Your armor proficiency increases to Expert for all armor and unarmored defense.'],
        ['id' => 'fighter-expertise', 'name' => 'Fighter Expertise',
          'description' => 'Your class DC and attack rolls with all weapons increase to Expert proficiency.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'weapon-legend', 'name' => 'Weapon Legend',
          'description' => 'Your proficiency with your chosen weapon group increases to Legendary, and simple/martial weapons increase to Master.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'greater-weapon-specialization', 'name' => 'Greater Weapon Specialization',
          'description' => 'Your additional damage from Weapon Specialization increases to your full proficiency rank.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'armor-mastery', 'name' => 'Armor Mastery',
          'description' => 'Your armor proficiency increases to Master for all armor and unarmored defense.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'versatile-legend', 'name' => 'Versatile Legend',
          'description' => 'Your proficiency with simple and martial weapons increases to Legendary.'],
      ]],
    ],
    'wizard' => [
      1  => ['auto_features' => [
        ['id' => 'arcane-spellcasting', 'name' => 'Arcane Spellcasting',
          'description' => 'You can cast arcane spells using the Cast a Spell activity. Your spellcasting ability modifier is Intelligence.'],
        ['id' => 'arcane-school', 'name' => 'Arcane School',
          'description' => 'You specialize in an arcane school of magic, gaining additional spells and abilities.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'expert-spellcaster', 'name' => 'Expert Spellcaster',
          'description' => 'Your proficiency ranks for spell attack rolls and spell DCs increase to Expert.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'wizard-weapon-expertise', 'name' => 'Wizard Weapon Expertise',
          'description' => 'Your proficiency rank for your wizard weapons increases to Expert.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'magical-fortitude', 'name' => 'Magical Fortitude',
          'description' => 'Your Fortitude saving throw proficiency increases to Expert.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'wizard-expertise', 'name' => 'Wizard Expertise',
          'description' => 'Your proficiency ranks for spell attack rolls and spell DCs increase to Master.'],
        ['id' => 'spell-penetration', 'name' => 'Spell Penetration',
          'description' => 'Your spells ignore some amount of spell resistance. Targets take a -2 circumstance penalty to counteract checks against your spells.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'weapon-specialization-wizard', 'name' => 'Weapon Specialization',
          'description' => 'You deal additional damage equal to half your proficiency rank with weapons you are expert in or better.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'master-spellcaster', 'name' => 'Master Spellcaster',
          'description' => 'Your proficiency ranks for spell attack rolls and spell DCs increase to Legendary.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'resolve', 'name' => 'Resolve',
          'description' => 'Your Will saving throw proficiency increases to Master.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'archwizards-spellcraft', 'name' => 'Archwizard\'s Spellcraft',
          'description' => 'You can cast 10th-rank spells. You gain a single 10th-rank spell slot per day.'],
      ]],
      20 => ['auto_features' => [
        ['id' => 'supreme-spellcaster', 'name' => 'Supreme Spellcaster',
          'description' => 'Your proficiency ranks for spell attack rolls and spell DCs are now Legendary.'],
      ]],
    ],
    'rogue' => [
      1  => ['auto_features' => [
        ['id' => 'sneak-attack', 'name' => 'Sneak Attack',
          'description' => 'When your target is flat-footed to you, you deal an extra 1d6 precision damage. This increases by 1d6 at levels 5, 11, and 17.'],
        ['id' => 'surprise-attack', 'name' => 'Surprise Attack',
          'description' => 'On the first round of combat, if you rolled Deception or Stealth for initiative, creatures that haven\'t acted are flat-footed to you.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'deny-advantage', 'name' => 'Deny Advantage',
          'description' => 'You aren\'t flat-footed to creatures of equal or lower level.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'sneak-attack-2d6', 'name' => 'Sneak Attack Upgrade (2d6)',
          'description' => 'Your Sneak Attack increases to 2d6 precision damage.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'evasion', 'name' => 'Evasion',
          'description' => 'Your Reflex save proficiency increases to Master. When you critically fail a Reflex save, you fail instead.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'debilitating-strike', 'name' => 'Debilitating Strike',
          'description' => 'When you hit a flat-footed target with a Strike, you can inflict a debilitating condition.'],
      ]],
    ],
    'cleric' => [
      1  => ['auto_features' => [
        ['id' => 'divine-spellcasting', 'name' => 'Divine Spellcasting',
          'description' => 'You can cast divine spells. Your spellcasting ability is Wisdom.'],
        ['id' => 'divine-font', 'name' => 'Divine Font',
          'description' => 'You gain additional heal or harm spells per day based on your deity.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'second-doctrine', 'name' => 'Second Doctrine',
          'description' => 'You gain an additional doctrine benefit based on your divine order.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'third-doctrine', 'name' => 'Third Doctrine',
          'description' => 'You gain a third doctrine benefit based on your divine order.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'fourth-doctrine', 'name' => 'Fourth Doctrine',
          'description' => 'You gain a fourth doctrine benefit based on your divine order.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'fifth-doctrine', 'name' => 'Fifth Doctrine',
          'description' => 'You gain a fifth doctrine benefit based on your divine order.'],
      ]],
    ],
    'ranger' => [
      1  => ['auto_features' => [
        ['id' => 'hunt-prey', 'name' => 'Hunt Prey',
          'description' => 'You can designate a creature as your prey with a free action. You gain a +2 circumstance bonus to Perception checks to locate your prey.'],
        ['id' => 'hunters-edge', 'name' => 'Hunter\'s Edge',
          'description' => 'You gain the benefit of one of the following hunter\'s edge options: Flurry, Precision, or Outwit.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'trackless-step', 'name' => 'Trackless Step',
          'description' => 'When you move through natural environments, you leave no tracks and can\'t be tracked.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'swift-prey', 'name' => 'Swift Prey',
          'description' => 'You can Hunt Prey as a free action once per turn on your turn.'],
      ]],
    ],
    'bard' => [
      1  => ['auto_features' => [
        ['id' => 'occult-spellcasting', 'name' => 'Occult Spellcasting',
          'description' => 'You can cast occult spells. Your spellcasting ability is Charisma.'],
        ['id' => 'composition-spells', 'name' => 'Composition Spells',
          'description' => 'You can cast composition cantrips and spells, which enhance your performances.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'lightning-reflexes', 'name' => 'Lightning Reflexes',
          'description' => 'Your Reflex saving throw proficiency increases to Expert.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'maestro-muse', 'name' => 'Expert Spellcaster (Bard)',
          'description' => 'Your spell attack rolls and spell DCs increase to Expert proficiency.'],
      ]],
    ],
    'barbarian' => [
      1  => ['auto_features' => [
        ['id' => 'rage', 'name' => 'Rage',
          'description' => 'You can enter a Rage as a single action. While raging you gain a +2 status bonus to melee damage and take a -1 penalty to AC.'],
        ['id' => 'instinct', 'name' => 'Instinct',
          'description' => 'You choose an instinct (animal, dragon, fury, giant, spirit, or superstition) that grants additional abilities while raging.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'deny-advantage-barbarian', 'name' => 'Deny Advantage',
          'description' => 'You aren\'t flat-footed to creatures of equal or lower level.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'brutality', 'name' => 'Brutality',
          'description' => 'Your weapon proficiency increases to Expert while raging, and you gain the weapon specialization damage bonus.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'juggernaut-barbarian', 'name' => 'Juggernaut',
          'description' => 'Your Fortitude saving throw proficiency increases to Master.'],
      ]],
    ],
    'alchemist' => [
      1  => ['auto_features' => [
        ['id' => 'alchemy', 'name' => 'Alchemy',
          'description' => 'You gain the Alchemical Crafting feat and can use the Craft activity to create alchemical items. You can use Intelligence instead of the normal ability for these checks.'],
        ['id' => 'advanced-alchemy', 'name' => 'Advanced Alchemy',
          'description' => 'Each day during your daily preparations you can spend batches of infused reagents to create infused alchemical items without the normal time and cost.'],
        ['id' => 'infused-reagents', 'name' => 'Infused Reagents',
          'description' => 'You infuse reagents each day for use in advanced alchemy. Your pool of infused reagents equals your level + your Intelligence modifier.'],
        ['id' => 'quick-alchemy', 'name' => 'Quick Alchemy',
          'description' => 'You spend 1 batch of infused reagents to create a single alchemical item using a single action, consuming the reagents immediately.'],
        ['id' => 'formula-book', 'name' => 'Formula Book',
          'description' => 'You start with a formula book containing the formulas for four common 1st-level alchemical items. You can expand it by finding or scribing new formulas.'],
        ['id' => 'research-field', 'name' => 'Research Field',
          'description' => 'Your research has led you to specialize in one of four fields: Bomber, Chirurgeon, Mutagenist, or Toxicologist. This choice grants unique abilities at levels 1, 5, and 13. Toxicologist: start with 2 common 1st-level poison formulas; applying injury poisons costs 1 action (instead of 2); may substitute class DC for poison save DC when using infused poisons. L5: create 3 poisons per batch. L15: apply two injury poisons to the same weapon simultaneously (combined as double poison at the lower DC; cannot use perpetual poisons with this option).'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'field-discovery', 'name' => 'Field Discovery',
          'description' => 'You gain a feature based on your research field: Bombers create a versatile vial, Chirurgeons gain the healing bomb variant, Mutagenists learn to use mutagens safely.'],
        ['id' => 'powerful-alchemy', 'name' => 'Powerful Alchemy',
          'description' => 'Alchemical items you create with infused reagents that have a DC use your class DC instead of the item\'s listed DC.'],
      ]],
      7  => ['auto_features' => [
        ['id' => 'alchemical-weapon-expertise', 'name' => 'Alchemical Weapon Expertise',
          'description' => 'Your proficiency rank with alchemical bombs and simple weapons increases to Expert.'],
        ['id' => 'iron-will', 'name' => 'Iron Will',
          'description' => 'Your Will saving throw proficiency increases to Expert.'],
        ['id' => 'perpetual-infusions', 'name' => 'Perpetual Infusions',
          'description' => 'You gain the ability to create a small batch of infused alchemical items at no cost each day, based on your research field.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'alchemical-expertise', 'name' => 'Alchemical Expertise',
          'description' => 'Your class DC for alchemical items increases to Expert proficiency.'],
        ['id' => 'alertness', 'name' => 'Alertness',
          'description' => 'Your Perception proficiency increases to Expert.'],
        ['id' => 'double-brew', 'name' => 'Double Brew',
          'description' => 'When using Quick Alchemy you can spend up to 2 batches of infused reagents to create 2 items simultaneously.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'juggernaut-alchemist', 'name' => 'Juggernaut',
          'description' => 'Your Fortitude saving throw proficiency increases to Master.'],
        ['id' => 'perpetual-potency', 'name' => 'Perpetual Potency',
          'description' => 'Your perpetual infusions improve; you can create more potent versions of your free items each day.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'greater-field-discovery', 'name' => 'Greater Field Discovery',
          'description' => 'You gain a powerful ability based on your research field, representing a major breakthrough in your area of specialty.'],
        ['id' => 'medium-armor-expertise', 'name' => 'Medium Armor Expertise',
          'description' => 'Your armor proficiency for light and medium armor increases to Expert.'],
        ['id' => 'weapon-specialization-alchemist', 'name' => 'Weapon Specialization',
          'description' => 'You deal additional damage equal to half your proficiency rank with weapons you are an expert in or better.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'alchemical-alacrity', 'name' => 'Alchemical Alacrity',
          'description' => 'When using Quick Alchemy you can create up to 3 items simultaneously (spending 1 batch of reagents per item).'],
        ['id' => 'evasion-alchemist', 'name' => 'Evasion',
          'description' => 'Your Reflex saving throw proficiency increases to Master. When you critically fail a Reflex save, you fail instead.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'alchemical-mastery', 'name' => 'Alchemical Mastery',
          'description' => 'Your class DC for alchemical items increases to Master proficiency. Critical failures on your alchemical item DCs become failures.'],
        ['id' => 'perpetual-perfection', 'name' => 'Perpetual Perfection',
          'description' => 'Your perpetual infusions reach their greatest potency, granting the highest available version of your free items each day.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'medium-armor-mastery', 'name' => 'Medium Armor Mastery',
          'description' => 'Your armor proficiency for light and medium armor increases to Master.'],
      ]],
    ],
    'witch' => [
      1  => ['auto_features' => [
        ['id' => 'witch-spellcasting', 'name' => 'Patron Spellcasting',
          'description' => 'You can cast spells of your patron\'s tradition (determined by patron theme). Your spellcasting ability is Intelligence. All spells are stored in your familiar; you must commune with your familiar during daily preparations to prepare spells.'],
        ['id' => 'familiar-witch', 'name' => 'Witch\'s Familiar',
          'description' => 'You gain a familiar, a class-locked feature. Your familiar stores all your spells and grants bonus familiar abilities at levels 1, 6, 12, and 18. Familiar death does not erase spells; a replacement familiar with the same spells is granted at next daily prep.'],
        ['id' => 'patron-theme', 'name' => 'Patron Theme',
          'description' => 'Choose your patron theme (cannot change): Curse, Fate, Fervor, Night, Rune, Wild, or Winter. This determines your spell tradition, patron skill (automatically trained), hex cantrip, and familiar\'s first granted spell.'],
        ['id' => 'hexes', 'name' => 'Hexes',
          'description' => 'You gain access to hex focus spells. You start with a focus pool of 1 Focus Point. Refocus by communing with your familiar for 10 minutes. Only one hex (regular or cantrip) may be cast per turn. Hex cantrips do not cost Focus Points and auto-heighten to half your level rounded up.'],
      ]],
      3  => ['auto_features' => [
        ['id' => 'witch-magical-fortitude', 'name' => 'Magical Fortitude',
          'description' => 'Your Fortitude saving throw proficiency increases to Expert.'],
      ]],
      5  => ['auto_features' => [
        ['id' => 'witch-expert-spellcaster', 'name' => 'Expert Spellcaster',
          'description' => 'Your spell attack rolls and spell DCs increase to Expert proficiency.'],
      ]],
      6  => ['auto_features' => [
        ['id' => 'familiar-witch-l6', 'name' => 'Familiar (Bonus Abilities)',
          'description' => 'Your familiar gains one additional familiar ability.'],
      ]],
      9  => ['auto_features' => [
        ['id' => 'witch-alertness', 'name' => 'Alertness',
          'description' => 'Your Perception proficiency increases to Expert.'],
      ]],
      11 => ['auto_features' => [
        ['id' => 'witch-master-spellcaster', 'name' => 'Master Spellcaster',
          'description' => 'Your spell attack rolls and spell DCs increase to Master proficiency.'],
        ['id' => 'witch-resolve', 'name' => 'Resolve',
          'description' => 'Your Will saving throw proficiency increases to Master.'],
      ]],
      12 => ['auto_features' => [
        ['id' => 'familiar-witch-l12', 'name' => 'Familiar (Bonus Abilities)',
          'description' => 'Your familiar gains one additional familiar ability.'],
      ]],
      13 => ['auto_features' => [
        ['id' => 'witch-weapon-expertise', 'name' => 'Weapon Expertise',
          'description' => 'Your proficiency rank with simple weapons and unarmed attacks increases to Expert.'],
      ]],
      15 => ['auto_features' => [
        ['id' => 'witch-evasion', 'name' => 'Evasion',
          'description' => 'Your Reflex saving throw proficiency increases to Expert.'],
      ]],
      17 => ['auto_features' => [
        ['id' => 'witch-legendary-spellcaster', 'name' => 'Legendary Spellcaster',
          'description' => 'Your spell attack rolls and spell DCs increase to Legendary proficiency.'],
      ]],
      18 => ['auto_features' => [
        ['id' => 'familiar-witch-l18', 'name' => 'Familiar (Bonus Abilities)',
          'description' => 'Your familiar gains one additional familiar ability.'],
      ]],
      19 => ['auto_features' => [
        ['id' => 'witch-patron-gift', 'name' => 'Patron\'s Gift',
          'description' => 'Your patron bestows a powerful gift. You can cast one additional 10th-rank spell per day, chosen from your tradition\'s spell list.'],
      ]],
    ],
  ];

  /**
   * Get the full advancement data for a class at a given level.
   *
   * Returns merged universal + class-specific features. Universal advancement
   * follows PF2e core rules (feats/boosts/skill increases); class-specific
   * auto_features are defined in CLASS_ADVANCEMENT above.
   *
   * @param string $class_name
   *   Lowercase class name (e.g., 'fighter', 'wizard').
   * @param int $level
   *   Target level (2–20; level 1 is handled at character creation).
   *
   * @return array
   *   Keys: hp_bonus, feat_slots, skill_increases, ability_boosts, auto_features.
   */
  public static function getClassAdvancement(string $class_name, int $level): array {
    // Universal PF2e advancement by level (applying to all classes).
    // Ancestry feats: 1, 5, 9, 13, 17
    // Skill feats: 2, 4, 6, 8, 10, 12, 14, 16, 18, 20
    // Class feats: every level 1+
    // General feats: 3, 7, 11, 15, 19
    // Skill increases: 3, 7, 11, 15, 19
    // Ability boosts: 5, 10, 15, 20
    $class_feat = ['slot_type' => 'class_feat', 'label' => 'Class Feat'];
    $skill_feat  = ['slot_type' => 'skill_feat',  'label' => 'Skill Feat'];
    $general_feat = ['slot_type' => 'general_feat', 'label' => 'General Feat'];
    $ancestry_feat = ['slot_type' => 'ancestry_feat', 'label' => 'Ancestry Feat'];

    $universal = [
      2  => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      3  => ['feat_slots' => [$class_feat, $general_feat], 'skill_increases' => 1, 'ability_boosts' => 0],
      4  => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      5  => ['feat_slots' => [$class_feat, $skill_feat, $ancestry_feat], 'skill_increases' => 0, 'ability_boosts' => 4],
      6  => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      7  => ['feat_slots' => [$class_feat, $general_feat], 'skill_increases' => 1, 'ability_boosts' => 0],
      8  => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      9  => ['feat_slots' => [$class_feat, $skill_feat, $ancestry_feat], 'skill_increases' => 0, 'ability_boosts' => 0],
      10 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 4],
      11 => ['feat_slots' => [$class_feat, $general_feat], 'skill_increases' => 1, 'ability_boosts' => 0],
      12 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      13 => ['feat_slots' => [$class_feat, $skill_feat, $ancestry_feat], 'skill_increases' => 0, 'ability_boosts' => 0],
      14 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      15 => ['feat_slots' => [$class_feat, $general_feat], 'skill_increases' => 1, 'ability_boosts' => 4],
      16 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      17 => ['feat_slots' => [$class_feat, $skill_feat, $ancestry_feat], 'skill_increases' => 0, 'ability_boosts' => 0],
      18 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 0],
      19 => ['feat_slots' => [$class_feat, $general_feat], 'skill_increases' => 1, 'ability_boosts' => 0],
      20 => ['feat_slots' => [$class_feat, $skill_feat],  'skill_increases' => 0, 'ability_boosts' => 4],
    ];

    $lvl_universal = $universal[$level] ?? ['feat_slots' => [$class_feat], 'skill_increases' => 0, 'ability_boosts' => 0];
    $class_specific = self::CLASS_ADVANCEMENT[$class_name][$level] ?? [];

    return [
      'hp_bonus' => self::CLASSES[$class_name]['hp'] ?? 8,
      'feat_slots' => $lvl_universal['feat_slots'],
      'skill_increases' => $lvl_universal['skill_increases'],
      'ability_boosts' => $lvl_universal['ability_boosts'],
      'auto_features' => $class_specific['auto_features'] ?? [],
    ];
  }

  /**
   * PF2E starting equipment by class.
   * Each class entry lists the standard starting gear at level 1.
   * Items reference IDs from EquipmentCatalogService::CATALOG.
   */
  const STARTING_EQUIPMENT = [
    'fighter' => [
      'weapons'  => ['longsword', 'dagger'],
      'armor'    => ['scale-mail'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Scale mail + longsword + dagger is the standard fighter kit.',
    ],
    'rogue' => [
      'weapons'  => ['shortsword', 'dagger', 'shortbow'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Light armor, quick weapons, climbing gear.',
    ],
    'wizard' => [
      'weapons'  => ['staff', 'dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'lantern-hooded', 'oil-pint'],
      'currency' => ['gp' => 15],
      'note'     => 'Wizards rely on spells; minimal mundane kit.',
    ],
    'cleric' => [
      'weapons'  => ['mace', 'dagger'],
      'armor'    => ['chain-shirt'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Chain shirt and mace, standard healer loadout.',
    ],
    'ranger' => [
      'weapons'  => ['shortsword', 'dagger', 'longbow'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Longbow + light melee; ranger wilderness kit.',
    ],
    'bard' => [
      'weapons'  => ['rapier', 'dagger'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Rapier + leather for the performative combatant.',
    ],
    'barbarian' => [
      'weapons'  => ['greataxe', 'dagger'],
      'armor'    => ['hide-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Two-handed greataxe and hide armor.',
    ],
    'champion' => [
      'weapons'  => ['longsword', 'dagger'],
      'armor'    => ['breastplate'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Heavy warrior of faith; breastplate + longsword.',
    ],
    'druid' => [
      'weapons'  => ['staff', 'dagger'],
      'armor'    => ['hide-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Nature magic; hide armor, simple weapons.',
    ],
    'monk' => [
      'weapons'  => ['dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Unarmed combatant; no armor needed.',
    ],
    'sorcerer' => [
      'weapons'  => ['dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'lantern-hooded'],
      'currency' => ['gp' => 15],
      'note'     => 'Innate magic user; light travel kit.',
    ],
    'alchemist' => [
      'weapons'  => ['dagger', 'crossbow'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'waterskin', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Crossbow + dagger + leather for the field alchemist.',
    ],
    'investigator' => [
      'weapons'  => ['rapier', 'dagger'],
      'armor'    => ['studded-leather'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'lantern-hooded'],
      'currency' => ['gp' => 15],
      'note'     => 'Finesse + studded leather for the analytical combatant.',
    ],
    'oracle' => [
      'weapons'  => ['mace', 'dagger'],
      'armor'    => ['chain-shirt'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Divine conduit; chain shirt and mace.',
    ],
    'swashbuckler' => [
      'weapons'  => ['rapier', 'dagger'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'chalk'],
      'currency' => ['gp' => 15],
      'note'     => 'Finesse fighter; rapier and light armor.',
    ],
    'witch' => [
      'weapons'  => ['staff', 'dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'oil-pint'],
      'currency' => ['gp' => 15],
      'note'     => 'Patron spellcaster; minimal kit.',
    ],
  ];

  public function __construct(Connection $database, AccountProxyInterface $current_user, UuidInterface $uuid, ?InventoryManagementService $inventory_management = NULL) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->uuid = $uuid;
    $this->inventoryManagement = $inventory_management;
  }

  /**
   * Returns the database connection for direct queries by controllers.
   */
  public function getDatabase(): Connection {
    return $this->database;
  }

  /**
   * Get all characters for the current user, optionally scoped to a campaign.
   */
  public function getUserCharacters(?int $uid = NULL, ?int $campaign_id = NULL): array {
    $uid = $uid ?? (int) $this->currentUser->id();
    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('c.uid', $uid)
      // Archived characters are hidden from the roster and selection flows.
      ->condition('c.status', 2, '<>')
      ->orderBy('c.changed', 'DESC');

    if ($campaign_id !== NULL) {
      $query->condition('c.campaign_id', $campaign_id);
    }

    return $query->execute()->fetchAll();
  }

  /**
   * Load a single character by ID.
   */
  public function loadCharacter(int $id): ?object {
    $record = $this->database->select('dc_campaign_characters', 'c')
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
    $record = $this->database->select('dc_campaign_characters', 'c')
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
    $hot = $this->extractHotColumnValues($character_data);

    $now = \Drupal::time()->getRequestTime();
    $instanceId = $this->uuid->generate();

    $id = $this->database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => $instanceId,
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => $instanceId,
        'uid' => (int) $this->currentUser->id(),
        'name' => $name,
        'level' => 1,
        'ancestry' => $ancestry,
        'class' => $class,
        'hp_current' => $hot['hp_current'],
        'hp_max' => $hot['hp_max'],
        'armor_class' => $hot['armor_class'],
        'experience_points' => $hot['experience_points'],
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'character_data' => json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        'status' => 1,
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    $this->grantAncestryStartingEquipment((int) $id, $ancestry);

    return (int) $id;
  }

  /**
   * Update character data.
   */
  public function updateCharacter(int $id, array $fields): bool {
    $fields['changed'] = \Drupal::time()->getRequestTime();
    $updated = $this->database->update('dc_campaign_characters')
      ->fields($fields)
      ->condition('id', $id)
      ->execute();

    return (bool) $updated;
  }

  /**
   * Delete a character.
   */
  public function deleteCharacter(int $id): bool {
    $deleted = $this->database->delete('dc_campaign_characters')
      ->condition('id', $id)
      ->condition('uid', (int) $this->currentUser->id())
      ->condition('campaign_id', 0)
      ->execute();

    return (bool) $deleted;
  }

  /**
   * Grants ancestry-specific starting equipment after character creation.
   *
   * Dwarves receive one free Clan Dagger per PF2e rules.
   * Additional ancestry starting items can be added here as they are implemented.
   */
  protected function grantAncestryStartingEquipment(int $character_id, string $ancestry_name): void {
    if (!$this->inventoryManagement) {
      \Drupal::logger('dungeoncrawler_content')->warning(
        'InventoryManagementService not available; skipping ancestry starting equipment grant for character @id.',
        ['@id' => $character_id]
      );
      return;
    }

    $canonical = self::resolveAncestryCanonicalName($ancestry_name) ?: $ancestry_name;

    if ($canonical !== 'Dwarf') {
      return;
    }

    $clan_dagger = [
      'id' => 'clan-dagger',
      'name' => 'Clan Dagger',
      'item_type' => 'weapon',
      'level' => 0,
      'bulk' => 'L',
      'traits' => ['agile', 'dwarf', 'versatile S'],
      'ancestry_granted' => TRUE,
      'sell_taboo' => TRUE,
      'sell_taboo_message' => 'Selling your clan dagger is a social taboo and shameful act. This violates dwarven cultural norms and dishonors your clan. A GM must explicitly authorize this action.',
      'weapon_stats' => [
        'category' => 'simple',
        'group' => 'knife',
        'damage' => [
          'dice_count' => 1,
          'die_size' => 'd4',
          'damage_type' => 'piercing',
        ],
        'weapon_traits' => ['agile', 'dwarf', 'versatile S'],
      ],
    ];

    try {
      $this->inventoryManagement->addItemToInventory(
        (string) $character_id,
        'character',
        $clan_dagger,
        'carried',
        1,
        0
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('dungeoncrawler_content')->error(
        'Failed to grant Clan Dagger to Dwarf character @id: @error',
        ['@id' => $character_id, '@error' => $e->getMessage()]
      );
    }
  }

  /**
   */
  public function buildCharacterJson(string $name, string $ancestry_name, string $class_name, array $options = []): array {
    $ancestry = self::ANCESTRIES[$ancestry_name] ?? self::ANCESTRIES['Human'];
    $class = self::CLASSES[$class_name] ?? self::CLASSES['fighter'];

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

    $class_proficiencies = [
      'perception' => (string) ($class['proficiencies']['perception'] ?? $class['perception'] ?? 'Trained'),
      'fortitude' => (string) ($class['proficiencies']['fortitude'] ?? $class['fortitude'] ?? 'Trained'),
      'reflex' => (string) ($class['proficiencies']['reflex'] ?? $class['reflex'] ?? 'Trained'),
      'will' => (string) ($class['proficiencies']['will'] ?? $class['will'] ?? 'Trained'),
    ];

    // Proficiency bonus at level 1 = 2 + level for trained, 4 + level for expert.
    $trained = 3; // 2 + level(1)
    $expert = 5;  // 4 + level(1)

    $prof_to_bonus = function (?string $prof, int $ability_mod) use ($trained, $expert): int {
      return match((string) $prof) {
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
            'perception' => $class_proficiencies['perception'],
            'fortitude' => $class_proficiencies['fortitude'],
            'reflex' => $class_proficiencies['reflex'],
            'will' => $class_proficiencies['will'],
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
            'modifier' => $prof_to_bonus($class_proficiencies['fortitude'], $con_mod),
            'proficiency' => $class_proficiencies['fortitude'],
          ],
          'reflex' => [
            'modifier' => $prof_to_bonus($class_proficiencies['reflex'], $dex_mod),
            'proficiency' => $class_proficiencies['reflex'],
          ],
          'will' => [
            'modifier' => $prof_to_bonus($class_proficiencies['will'], $wis_mod),
            'proficiency' => $class_proficiencies['will'],
          ],
        ],
        'perception' => [
          'modifier' => $prof_to_bonus($class_proficiencies['perception'], $wis_mod),
          'proficiency' => $class_proficiencies['perception'],
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
   * Get the skill list for a character with proficiency rank and bonus.
   *
   * Returns all 17 core skills plus any Lore specializations stored on the character.
   *
   * @param int $characterId Character ID.
   * @param \Drupal\dungeoncrawler_content\Service\CharacterCalculator $calculator
   *   Injected or inline-constructed for proficiency math.
   *
   * @return array List of skills with keys: name, rank, ability, bonus, is_lore.
   *   Returns ['error' => '...'] on failure.
   */
  public function getCharacterSkills(int $characterId, $calculator = NULL): array {
    $record = $this->loadCharacter($characterId);
    if (!$record) {
      return ['error' => "Character {$characterId} not found."];
    }

    $data = $this->getCharacterData($record);
    $level = max(0, (int) ($record->level ?? $data['level'] ?? 1));
    $storedSkills = $data['skills'] ?? [];
    $abilities = $data['abilities'] ?? [];

    if ($calculator === NULL) {
      $calculator = new \Drupal\dungeoncrawler_content\Service\CharacterCalculator();
    }

    $skills = [];
    foreach (\Drupal\dungeoncrawler_content\Service\CharacterCalculator::SKILLS as $skillKey => $abilityKey) {
      $rankRaw = $storedSkills[$skillKey] ?? 'untrained';
      $rank = is_numeric($rankRaw)
        ? (\Drupal\dungeoncrawler_content\Service\CharacterCalculator::PROFICIENCY_RANKS[(int) $rankRaw] ?? 'untrained')
        : $rankRaw;

      $abilityScore = (int) ($abilities[$abilityKey] ?? $abilities[substr($abilityKey, 0, 3)] ?? 10);
      $abilityMod = $calculator->calculateAbilityModifier($abilityScore);
      $profBonus = $calculator->calculateProficiencyBonus($rank, $level);

      $skills[] = [
        'name'    => $skillKey,
        'rank'    => $rank,
        'ability' => $abilityKey,
        'bonus'   => $abilityMod + $profBonus,
        'is_lore' => FALSE,
      ];
    }

    // Add Lore specializations.
    if (!empty($data['lore_skills'])) {
      foreach ($data['lore_skills'] as $lore) {
        $spec   = $lore['specialization'] ?? $lore['name'] ?? 'Unknown Lore';
        $rank   = $lore['rank'] ?? 'trained';
        $abilityScore = (int) ($abilities['intelligence'] ?? $abilities['int'] ?? 10);
        $abilityMod   = $calculator->calculateAbilityModifier($abilityScore);
        $profBonus    = $calculator->calculateProficiencyBonus($rank, $level);

        $skills[] = [
          'name'            => strtolower($spec) . ' lore',
          'specialization'  => $spec,
          'rank'            => $rank,
          'ability'         => 'intelligence',
          'bonus'           => $abilityMod + $profBonus,
          'is_lore'         => TRUE,
        ];
      }
    }

    return $skills;
  }

  /**
   * Extract hot-column values from character payload.
   *
   * Maps JSON schema fields to hot relational columns for high-frequency gameplay:
   * - hit_points.max → hp_max
   * - hit_points.current → hp_current
   * - armor_class → armor_class
   * - experience_points → experience_points
   *
   * Hot columns enable fast reads/writes for gameplay mechanics without parsing JSON.
   * See character.schema.json for field definitions and hybrid storage documentation.
   *
   * @param array $characterData
   *   Character data array (may be nested under 'character' key).
   *
   * @return array{hp_current:int,hp_max:int,armor_class:int,experience_points:int}
   *   Normalized values for hot relational columns with safe defaults.
   */
  public function extractHotColumnsFromData(array $characterData): array {
    $character = is_array($characterData['character'] ?? NULL) ? $characterData['character'] : $characterData;
    $hitPoints = is_array($character['hit_points'] ?? NULL) ? $character['hit_points'] : [];

    $hpMax = (int) ($hitPoints['max'] ?? 0);
    $hpCurrent = (int) ($hitPoints['current'] ?? $hpMax);

    return [
      'hp_current' => $hpCurrent,
      'hp_max' => $hpMax,
      'armor_class' => (int) ($character['armor_class'] ?? 10),
      'experience_points' => (int) ($character['experience_points'] ?? 0),
    ];
  }

  /**
   * Resolve hot-column values using row columns first, then JSON payload fallback.
   *
   * Implements hybrid columnar storage pattern:
   * 1. Prefer values from dedicated hot columns (fast, indexed)
   * 2. Fall back to JSON schema fields if hot columns are null/unset
   * 3. Use safe defaults if neither source has data
   *
   * This ensures compatibility with characters created before hot columns were added
   * and provides resilience if data synchronization issues occur.
   *
   * @param object $record
   *   Database record from dc_campaign_characters table.
   * @param array $characterData
   *   Parsed character_data JSON payload.
   *
   * @return array{hp_current:int,hp_max:int,armor_class:int,experience_points:int}
   *   Row-preferred hot values with JSON fallback.
   */
  public function resolveHotColumnsForRecord(object $record, array $characterData): array {
    $fromJson = $this->extractHotColumnsFromData($characterData);

    return [
      'hp_current' => (int) ($record->hp_current ?? $fromJson['hp_current']),
      'hp_max' => (int) ($record->hp_max ?? $fromJson['hp_max']),
      'armor_class' => (int) ($record->armor_class ?? $fromJson['armor_class']),
      'experience_points' => (int) ($record->experience_points ?? $fromJson['experience_points']),
    ];
  }

  /**
   * Check if a character belongs to the current user.
   */
  public function isOwner(object $record): bool {
    return (int) $record->uid === (int) $this->currentUser->id();
  }

  /**
   * Returns class data by class id.
   */
  public function getClassData(string $classId): ?array {
    return self::CLASSES[strtolower($classId)] ?? NULL;
  }

  /**
   * Returns base HP for a class with safe fallback.
   */
  public function getClassHP(string $classId): int {
    $classData = $this->getClassData($classId);
    return (int) ($classData['hp'] ?? 8);
  }

  /**
   * Extract hot relational values from a character JSON payload.
   *
   * @return array{hp_current:int,hp_max:int,armor_class:int,experience_points:int}
   *   Normalized hot-column values.
   */
  private function extractHotColumnValues(array $characterData): array {
    return $this->extractHotColumnsFromData($characterData);
  }

  /**
   * Fetch spells from the registry for a given tradition and level.
   *
   * @param string $tradition
   *   One of 'arcane', 'divine', 'occult', 'primal'.
   * @param int $level
   *   Spell level (0 = cantrips, 1 = 1st-level, etc.).
   *
   * @return array
   *   Array of spell records: ['id' => ..., 'name' => ..., 'description' => ...].
   */
  /**
   * Valid PF2e spell schools used to filter out non-spell data pollution.
   */
  /**
   * Heritage-granted reaction abilities catalog.
   *
   * Key: reaction ability ID (matches granted_abilities entries in HERITAGES).
   * Each entry describes the trigger, effect, and type for use by the combat engine.
   */
  const HERITAGE_REACTIONS = [
    'call-on-ancient-blood' => [
      'id'          => 'call-on-ancient-blood',
      'name'        => 'Call on Ancient Blood',
      'action_type' => 'reaction',
      'heritage'    => 'ancient-blooded',
      'ancestry'    => 'Dwarf',
      'trigger'     => 'saving_throw_before_roll_magical',
      'effect'      => [
        'type'        => 'circumstance_bonus',
        'stat'        => 'saving_throw',
        'value'       => 1,
        'duration'    => 'end_of_turn',
        'includes_trigger' => TRUE,
      ],
      'description' => 'Your ancestors\' innate resistance to magic surges. You gain a +1 circumstance bonus to saving throws until the end of this turn (including the triggering save).',
    ],
  ];

  /**
   * Returns the granted ability IDs for a given ancestry and heritage ID.
   *
   * Usage: CharacterManager::getHeritageGrantedAbilities('Dwarf', 'ancient-blooded')
   *
   * @param string $ancestry_canonical
   *   Canonical ancestry name (e.g., 'Dwarf').
   * @param string $heritage_id
   *   Heritage machine ID (e.g., 'ancient-blooded').
   *
   * @return string[]
   *   Array of granted ability IDs, or empty array if none.
   */
  public static function getHeritageGrantedAbilities(string $ancestry_canonical, string $heritage_id): array {
    $heritages = self::HERITAGES[$ancestry_canonical] ?? [];
    foreach ($heritages as $heritage) {
      if (($heritage['id'] ?? '') === $heritage_id) {
        return $heritage['granted_abilities'] ?? [];
      }
    }
    return [];
  }

  /**
   * Validates that a heritage_id belongs to the given ancestry.
   *
   * @param string $ancestry_canonical
   *   Canonical ancestry name.
   * @param string $heritage_id
   *   Heritage machine ID to validate.
   *
   * @return bool
   *   TRUE if the heritage is valid for this ancestry.
   */
  public static function isValidHeritageForAncestry(string $ancestry_canonical, string $heritage_id): bool {
    $heritages = self::HERITAGES[$ancestry_canonical] ?? [];
    foreach ($heritages as $heritage) {
      if (($heritage['id'] ?? '') === $heritage_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  const VALID_SPELL_SCHOOLS = [
    'abjuration', 'conjuration', 'divination', 'enchantment',
    'evocation', 'illusion', 'necromancy', 'transmutation',
  ];

  /**
   * Retrieves spells from the content registry filtered by tradition and level.
   *
   * Applies three data-quality guards:
   * 1. Excludes entries whose school is not a valid PF2e school (filters out
   *    cleric doctrines, deadly sins, and other class features that were
   *    incorrectly tagged as spells during import).
   * 2. Excludes duplicate "_c" suffix entries (primal-only copies of
   *    multi-tradition spells created during bulk import).
   * 3. Filters by rarity — only "common" spells by default, since PF2e
   *    restricts uncommon/rare spells at character creation without GM
   *    approval.
   *
   * @param string $tradition
   *   The spell tradition to filter by (arcane, divine, occult, primal).
   * @param int $level
   *   The spell level (0 = cantrips).
   * @param string $rarity
   *   Rarity filter: 'common' (default), 'uncommon', 'rare', or 'all'.
   *
   * @return array
   *   Array of spell data arrays, each with id, name, level, school,
   *   traditions, description, and rarity.
   */
  public function getSpellsByTradition(string $tradition, int $level = 0, string $rarity = 'common'): array {
    $tradition = strtolower($tradition);
    $query = $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'level', 'tags', 'schema_data'])
      ->condition('content_type', 'spell')
      ->condition('level', $level)
      ->condition('tags', '%"' . $this->database->escapeLike($tradition) . '"%', 'LIKE');

    // Exclude _c suffix duplicates (primal-only copies from bulk import).
    $query->condition('r.content_id', '%\_c', 'NOT LIKE');

    $query->orderBy('name');
    $rows = $query->execute()->fetchAll();

    $spells = [];
    foreach ($rows as $row) {
      $schema = json_decode($row->schema_data, TRUE) ?: [];

      // Filter out non-spell entries with invalid school values.
      $school = strtolower($schema['school'] ?? '');
      if ($school !== '' && !in_array($school, self::VALID_SPELL_SCHOOLS, TRUE)) {
        continue;
      }

      // Rarity gate: skip spells that don't match the requested rarity.
      $spell_rarity = strtolower($schema['rarity'] ?? 'common');
      if ($rarity !== 'all' && $spell_rarity !== $rarity) {
        continue;
      }

      $spells[] = [
        'id' => $row->content_id,
        'name' => $row->name,
        'level' => (int) $row->level,
        'school' => $school ?: 'unknown',
        'traditions' => $schema['traditions'] ?? [],
        'description' => $schema['description_snippet'] ?? $row->name,
        'rarity' => $spell_rarity,
      ];
    }
    return $spells;
  }

  /**
   * Resolves the spellcasting tradition for a class + character data.
   *
   * Handles fixed-tradition classes (wizard, cleric, bard, druid, oracle)
   * and flexible-tradition classes (sorcerer via bloodline, witch via patron).
   *
   * @param string $class
   *   The class ID.
   * @param array $character_data
   *   Full character data for resolving subclass choices.
   *
   * @return string|null
   *   The tradition string or NULL if not a caster / not yet chosen.
   */
  public function resolveClassTradition(string $class, array $character_data = []): ?string {
    $class = strtolower($class);
    if (!array_key_exists($class, self::CLASS_TRADITIONS)) {
      return NULL;
    }

    $tradition = self::CLASS_TRADITIONS[$class];
    if ($tradition !== NULL) {
      return $tradition;
    }

    // Sorcerer: resolve via bloodline.
    if ($class === 'sorcerer') {
      $bloodline = $character_data['subclass'] ?? $character_data['bloodline'] ?? '';
      $bl_data = self::SORCERER_BLOODLINES[$bloodline] ?? NULL;
      return $bl_data['tradition'] ?? NULL;
    }

    // Witch: resolve via patron.
    if ($class === 'witch') {
      $patron = $character_data['subclass'] ?? $character_data['patron'] ?? '';
      $patron_data = self::WITCH_PATRONS[$patron] ?? NULL;
      return $patron_data['tradition'] ?? 'occult';
    }

    return NULL;
  }

}
