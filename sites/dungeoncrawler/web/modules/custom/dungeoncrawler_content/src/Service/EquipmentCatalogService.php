<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Equipment catalog service.
 *
 * Provides the canonical PF2E equipment catalog as a PHP constant.
 * Covers weapons (simple + martial), armor (light/medium/heavy),
 * shields, and adventuring gear per PF2E Core Rulebook Chapter 6.
 *
 * Data is code — no DB needed; mirrors the CONDITIONS/CLASSES pattern.
 */
class EquipmentCatalogService {

  /**
   * Valid item types for filtering.
   */
  const VALID_TYPES = ['weapon', 'armor', 'shield', 'gear'];

  /**
   * Canonical PF2E equipment catalog.
   *
   * Weapons: 5 simple + 5 martial (plus longsword already in JSON catalog)
   * Armor: 3 light + 2 medium + 1 heavy
   * Gear: 10 adventuring items
   *
   * Fields:
   *   id, name, type, category (weapon/armor subcategory), price_gp, bulk
   *   + weapon_stats: {category, group, damage_dice, damage_type, traits[]}
   *   + armor_stats:  {category, ac_bonus, max_dex, check_penalty, speed_penalty, str_req}
   */
  const CATALOG = [

    // =========================================================
    // SIMPLE WEAPONS
    // =========================================================
    'club' => [
      'id'       => 'club',
      'name'     => 'Club',
      'type'     => 'weapon',
      'price_gp' => 0,
      'bulk'     => '1',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'club',
        'damage_dice' => '1d6',
        'damage_type' => 'bludgeoning',
        'traits'      => ['thrown-10'],
      ],
    ],
    'dagger' => [
      'id'       => 'dagger',
      'name'     => 'Dagger',
      'type'     => 'weapon',
      'price_gp' => 0.2,
      'bulk'     => 'L',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'knife',
        'damage_dice' => '1d4',
        'damage_type' => 'piercing',
        'traits'      => ['agile', 'finesse', 'thrown-10', 'versatile S'],
      ],
    ],
    'spear' => [
      'id'       => 'spear',
      'name'     => 'Spear',
      'type'     => 'weapon',
      'price_gp' => 0.1,
      'bulk'     => '1',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'spear',
        'damage_dice' => '1d6',
        'damage_type' => 'piercing',
        'traits'      => ['thrown-20'],
      ],
    ],
    'staff' => [
      'id'       => 'staff',
      'name'     => 'Staff',
      'type'     => 'weapon',
      'price_gp' => 0,
      'bulk'     => '1',
      'hands'    => '1+',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'club',
        'damage_dice' => '1d4',
        'damage_type' => 'bludgeoning',
        'traits'      => ['two-hand 1d8'],
      ],
    ],
    'crossbow' => [
      'id'       => 'crossbow',
      'name'     => 'Crossbow',
      'type'     => 'weapon',
      'price_gp' => 3,
      'bulk'     => '1',
      'hands'    => '2',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'bow',
        'damage_dice' => '1d8',
        'damage_type' => 'piercing',
        'range'       => 120,
        'reload'      => '1',
        'traits'      => [],
      ],
    ],

    // =========================================================
    // MARTIAL WEAPONS
    // =========================================================
    'longsword' => [
      'id'       => 'longsword',
      'name'     => 'Longsword',
      'type'     => 'weapon',
      'price_gp' => 1,
      'bulk'     => '1',
      'hands'    => '1+',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'sword',
        'damage_dice' => '1d8',
        'damage_type' => 'slashing',
        'traits'      => ['versatile P'],
      ],
    ],
    'shortsword' => [
      'id'       => 'shortsword',
      'name'     => 'Shortsword',
      'type'     => 'weapon',
      'price_gp' => 0.9,
      'bulk'     => 'L',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'sword',
        'damage_dice' => '1d6',
        'damage_type' => 'piercing',
        'traits'      => ['agile', 'finesse', 'versatile S'],
      ],
    ],
    'rapier' => [
      'id'       => 'rapier',
      'name'     => 'Rapier',
      'type'     => 'weapon',
      'price_gp' => 2,
      'bulk'     => '1',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'sword',
        'damage_dice' => '1d6',
        'damage_type' => 'piercing',
        'traits'      => ['deadly 1d8', 'disarm', 'finesse'],
      ],
    ],
    'shortbow' => [
      'id'       => 'shortbow',
      'name'     => 'Shortbow',
      'type'     => 'weapon',
      'price_gp' => 3,
      'bulk'     => '1',
      'hands'    => '2',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'bow',
        'damage_dice' => '1d6',
        'damage_type' => 'piercing',
        'range'       => 60,
        'reload'      => '0',
        'traits'      => ['deadly 1d10'],
      ],
    ],
    'battleaxe' => [
      'id'       => 'battleaxe',
      'name'     => 'Battleaxe',
      'type'     => 'weapon',
      'price_gp' => 1,
      'bulk'     => '1',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'axe',
        'damage_dice' => '1d8',
        'damage_type' => 'slashing',
        'traits'      => ['sweep'],
      ],
    ],
    'greataxe' => [
      'id'       => 'greataxe',
      'name'     => 'Greataxe',
      'type'     => 'weapon',
      'price_gp' => 2,
      'bulk'     => '2',
      'hands'    => '2',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'axe',
        'damage_dice' => '1d12',
        'damage_type' => 'slashing',
        'traits'      => ['sweep'],
      ],
    ],
    'longbow' => [
      'id'       => 'longbow',
      'name'     => 'Longbow',
      'type'     => 'weapon',
      'price_gp' => 6,
      'bulk'     => '2',
      'hands'    => '2',
      'weapon_stats' => [
        'category'    => 'martial',
        'group'       => 'bow',
        'damage_dice' => '1d8',
        'damage_type' => 'piercing',
        'range'       => 100,
        'reload'      => '0',
        'traits'      => ['deadly 1d10', 'volley 30ft'],
      ],
    ],
    'mace' => [
      'id'       => 'mace',
      'name'     => 'Mace',
      'type'     => 'weapon',
      'price_gp' => 1,
      'bulk'     => '1',
      'hands'    => '1',
      'weapon_stats' => [
        'category'    => 'simple',
        'group'       => 'club',
        'damage_dice' => '1d6',
        'damage_type' => 'bludgeoning',
        'traits'      => ['shove'],
      ],
    ],

    // =========================================================
    // LIGHT ARMOR
    // =========================================================
    'leather-armor' => [
      'id'       => 'leather-armor',
      'name'     => 'Leather Armor',
      'type'     => 'armor',
      'price_gp' => 2,
      'bulk'     => '1',
      'armor_stats' => [
        'category'      => 'light',
        'ac_bonus'      => 1,
        'max_dex'       => 5,
        'check_penalty' => 0,
        'speed_penalty' => 0,
        'str_req'       => 0,
      ],
    ],
    'studded-leather' => [
      'id'       => 'studded-leather',
      'name'     => 'Studded Leather',
      'type'     => 'armor',
      'price_gp' => 3,
      'bulk'     => '1',
      'armor_stats' => [
        'category'      => 'light',
        'ac_bonus'      => 2,
        'max_dex'       => 3,
        'check_penalty' => 0,
        'speed_penalty' => 0,
        'str_req'       => 12,
      ],
    ],
    'chain-shirt' => [
      'id'       => 'chain-shirt',
      'name'     => 'Chain Shirt',
      'type'     => 'armor',
      'price_gp' => 5,
      'bulk'     => '1',
      'armor_stats' => [
        'category'      => 'light',
        'ac_bonus'      => 2,
        'max_dex'       => 3,
        'check_penalty' => 0,
        'speed_penalty' => 0,
        'str_req'       => 12,
      ],
    ],

    // =========================================================
    // MEDIUM ARMOR
    // =========================================================
    'scale-mail' => [
      'id'       => 'scale-mail',
      'name'     => 'Scale Mail',
      'type'     => 'armor',
      'price_gp' => 4,
      'bulk'     => '2',
      'armor_stats' => [
        'category'      => 'medium',
        'ac_bonus'      => 3,
        'max_dex'       => 2,
        'check_penalty' => -2,
        'speed_penalty' => -5,
        'str_req'       => 14,
      ],
    ],
    'breastplate' => [
      'id'       => 'breastplate',
      'name'     => 'Breastplate',
      'type'     => 'armor',
      'price_gp' => 8,
      'bulk'     => '2',
      'armor_stats' => [
        'category'      => 'medium',
        'ac_bonus'      => 4,
        'max_dex'       => 3,
        'check_penalty' => -2,
        'speed_penalty' => -5,
        'str_req'       => 16,
      ],
    ],
    'hide-armor' => [
      'id'       => 'hide-armor',
      'name'     => 'Hide Armor',
      'type'     => 'armor',
      'price_gp' => 2,
      'bulk'     => '2',
      'armor_stats' => [
        'category'      => 'medium',
        'ac_bonus'      => 3,
        'max_dex'       => 2,
        'check_penalty' => -2,
        'speed_penalty' => -5,
        'str_req'       => 14,
      ],
    ],

    // =========================================================
    // HEAVY ARMOR
    // =========================================================
    'full-plate' => [
      'id'       => 'full-plate',
      'name'     => 'Full Plate',
      'type'     => 'armor',
      'price_gp' => 30,
      'bulk'     => '4',
      'armor_stats' => [
        'category'      => 'heavy',
        'ac_bonus'      => 6,
        'max_dex'       => 0,
        'check_penalty' => -3,
        'speed_penalty' => -10,
        'str_req'       => 18,
      ],
    ],

    // =========================================================
    // ADVENTURING GEAR
    // =========================================================
    'backpack' => [
      'id'       => 'backpack',
      'name'     => 'Backpack',
      'type'     => 'gear',
      'price_gp' => 0.1,
      'bulk'     => 'L',
      'description' => 'Worn on the back; holds up to 4 Bulk of gear. Reduces effective Bulk by 2 for carried items.',
    ],
    'bedroll' => [
      'id'       => 'bedroll',
      'name'     => 'Bedroll',
      'type'     => 'gear',
      'price_gp' => 0.02,
      'bulk'     => 'L',
      'description' => 'A cloth sleeping roll. Counts as adequate bedding for rest.',
    ],
    'rope' => [
      'id'       => 'rope',
      'name'     => 'Rope (50 ft.)',
      'type'     => 'gear',
      'price_gp' => 0.5,
      'bulk'     => 'L',
      'description' => '50 feet of hempen rope. Supports up to 500 lbs.',
    ],
    'torch' => [
      'id'       => 'torch',
      'name'     => 'Torch',
      'type'     => 'gear',
      'price_gp' => 0.01,
      'bulk'     => 'L',
      'description' => 'Burns for 1 hour; provides bright light in a 20-foot radius.',
    ],
    'rations-week' => [
      'id'       => 'rations-week',
      'name'     => 'Rations (1 week)',
      'type'     => 'gear',
      'price_gp' => 0.4,
      'bulk'     => 'L',
      'description' => 'One week of travel rations (hardtack, jerky, dried fruit).',
    ],
    'waterskin' => [
      'id'       => 'waterskin',
      'name'     => 'Waterskin',
      'type'     => 'gear',
      'price_gp' => 0.05,
      'bulk'     => 'L',
      'description' => 'Holds 1 gallon of liquid. Sufficient water for 1 day.',
    ],
    'chalk' => [
      'id'       => 'chalk',
      'name'     => 'Chalk (10 pieces)',
      'type'     => 'gear',
      'price_gp' => 0.01,
      'bulk'     => '-',
      'description' => '10 sticks of chalk for marking dungeon walls.',
    ],
    'flint-steel' => [
      'id'       => 'flint-steel',
      'name'     => 'Flint and Steel',
      'type'     => 'gear',
      'price_gp' => 0.05,
      'bulk'     => '-',
      'description' => 'Starts a fire in about 1 minute. No fuel included.',
    ],
    'lantern-hooded' => [
      'id'       => 'lantern-hooded',
      'name'     => 'Lantern (Hooded)',
      'type'     => 'gear',
      'price_gp' => 0.7,
      'bulk'     => 'L',
      'description' => 'Burns oil; bright light in 30-foot radius, low light 60 feet. Shutter reduces to dim.',
    ],
    'oil-pint' => [
      'id'       => 'oil-pint',
      'name'     => 'Oil (1 pint)',
      'type'     => 'gear',
      'price_gp' => 0.01,
      'bulk'     => 'L',
      'description' => 'Fuel for lanterns or torches; burns for 6 hours in a lantern.',
    ],
  ];

  /**
   * Return catalog items filtered by type.
   *
   * @param string|null $type  'weapon'|'armor'|'shield'|'gear'|NULL (all)
   *
   * @return array  Flat list of item arrays.
   */
  public function getByType(?string $type = NULL): array {
    if ($type === NULL) {
      return array_values(self::CATALOG);
    }
    return array_values(
      array_filter(self::CATALOG, static fn(array $item): bool => $item['type'] === $type)
    );
  }

  /**
   * Return a single item by ID, or NULL.
   */
  public function getById(string $id): ?array {
    return self::CATALOG[$id] ?? NULL;
  }

  /**
   * Return armor stats for a given item ID, or NULL if not armor.
   */
  public function getArmorStats(string $id): ?array {
    $item = self::CATALOG[$id] ?? NULL;
    if ($item === NULL || $item['type'] !== 'armor') {
      return NULL;
    }
    return $item['armor_stats'] ?? NULL;
  }

}
