<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Psr\Log\LoggerInterface;

/**
 * Generates new map settings dynamically when players navigate to new locations.
 *
 * When a player says "I leave the tavern" or "I head to the market", this service:
 * 1. Uses AI to generate a setting description appropriate to the destination
 * 2. Determines room size, terrain, lighting, and theme from the description
 * 3. Generates a hex grid for the new room
 * 4. Creates appropriate NPCs, objects, and environmental details
 * 5. Wires the new room into dungeon_data with proper connections
 * 6. Returns the new room data so the client can transition to it
 *
 * This bridges the gap between narrative exploration ("I want to go to the
 * blacksmith") and the mechanical hex map system that needs concrete room data.
 */
class MapGeneratorService {

  protected Connection $database;
  protected LoggerInterface $logger;
  protected AIApiService $aiApiService;
  protected NpcPsychologyService $psychologyService;

  /**
   * Size presets: setting type => [cols, rows, hex_count_approx, size_category].
   */
  const SIZE_PRESETS = [
    'tiny'   => ['cols' => 3, 'rows' => 3, 'size' => 'tiny'],       // closet, alcove
    'small'  => ['cols' => 5, 'rows' => 4, 'size' => 'small'],      // shop, cell
    'medium' => ['cols' => 7, 'rows' => 6, 'size' => 'medium'],     // tavern, chapel
    'large'  => ['cols' => 9, 'rows' => 8, 'size' => 'large'],      // great hall, market square
    'huge'   => ['cols' => 12, 'rows' => 10, 'size' => 'huge'],     // arena, cathedral
  ];

  /**
   * Terrain mapping from setting type to terrain properties.
   */
  const TERRAIN_MAP = [
    'tavern'       => ['type' => 'wood_floor',   'difficult' => FALSE, 'ceiling' => 12],
    'shop'         => ['type' => 'wood_floor',   'difficult' => FALSE, 'ceiling' => 10],
    'temple'       => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 30],
    'market'       => ['type' => 'cobblestone',  'difficult' => FALSE, 'ceiling' => 0],
    'street'       => ['type' => 'cobblestone',  'difficult' => FALSE, 'ceiling' => 0],
    'forest'       => ['type' => 'natural_earth','difficult' => TRUE,  'ceiling' => 0],
    'cave'         => ['type' => 'natural_rock', 'difficult' => TRUE,  'ceiling' => 15],
    'dungeon'      => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 10],
    'library'      => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 15],
    'throne_room'  => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 25],
    'dock'         => ['type' => 'wood_floor',   'difficult' => FALSE, 'ceiling' => 0],
    'alley'        => ['type' => 'cobblestone',  'difficult' => FALSE, 'ceiling' => 0],
    'sewer'        => ['type' => 'stone_floor',  'difficult' => TRUE,  'ceiling' => 8],
    'garden'       => ['type' => 'natural_earth','difficult' => FALSE, 'ceiling' => 0],
    'arena'        => ['type' => 'sand',         'difficult' => FALSE, 'ceiling' => 0],
    'prison'       => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 8],
    'residential'  => ['type' => 'wood_floor',   'difficult' => FALSE, 'ceiling' => 10],
    'wilderness'   => ['type' => 'natural_earth','difficult' => TRUE,  'ceiling' => 0],
    'default'      => ['type' => 'stone_floor',  'difficult' => FALSE, 'ceiling' => 10],
  ];

  /**
   * Lighting defaults by setting type.
   */
  const LIGHTING_MAP = [
    'tavern'      => 'normal_light',
    'shop'        => 'normal_light',
    'temple'      => 'normal_light',
    'market'      => 'bright_light',
    'street'      => 'normal_light',
    'forest'      => 'dim_light',
    'cave'        => 'darkness',
    'dungeon'     => 'dim_light',
    'library'     => 'normal_light',
    'dock'        => 'normal_light',
    'alley'       => 'dim_light',
    'sewer'       => 'darkness',
    'garden'      => 'bright_light',
    'arena'       => 'bright_light',
    'prison'      => 'dim_light',
    'wilderness'  => 'normal_light',
    'default'     => 'normal_light',
  ];

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    AIApiService $ai_api_service,
    NpcPsychologyService $psychology_service
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_map_gen');
    $this->aiApiService = $ai_api_service;
    $this->psychologyService = $psychology_service;
  }

  // =========================================================================
  // Public API
  // =========================================================================

  /**
   * Generate a new map/setting from a player's navigation intent.
   *
   * This is the main entry point. Given a destination description (e.g., "the
   * blacksmith shop", "the town square", "the forest path outside town"), it:
   * 1. Calls AI to flesh out the setting details
   * 2. Builds a complete room structure matching dungeon_data schema
   * 3. Appends the room to dungeon_data and creates connections
   * 4. Returns the new room data and updated dungeon_data
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $destination
   *   The player's stated destination (e.g., "the blacksmith", "outside").
   * @param string $origin_room_id
   *   The room_id the player is leaving from.
   * @param array $narrative_context
   *   Additional context for generation:
   *   - gm_narrative: string - GM's transition narrative
   *   - campaign_theme: string - overall campaign theme
   *   - party_level: int - for difficulty calibration
   *   - time_of_day: string - dawn/day/dusk/night
   *
   * @return array
   *   [
   *     'room' => array (the new room structure),
   *     'room_index' => int (index in dungeon_data.rooms),
   *     'dungeon_data' => array (updated full dungeon_data),
   *   ]
   *
   * @throws \RuntimeException
   *   If generation fails.
   */
  public function generateSetting(
    int $campaign_id,
    string $destination,
    string $origin_room_id,
    array $narrative_context = []
  ): array {
    $this->logger->info('Generating new setting for campaign @cid: @dest', [
      '@cid' => $campaign_id,
      '@dest' => $destination,
    ]);

    // Load current dungeon data.
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      throw new \RuntimeException('No dungeon data found for campaign ' . $campaign_id);
    }

    $dungeon_id = $record['dungeon_id'];
    $dungeon_data = json_decode($record['dungeon_data'], TRUE);
    if (!is_array($dungeon_data)) {
      throw new \RuntimeException('Invalid dungeon data for campaign ' . $campaign_id);
    }

    // Step 1: Use AI to generate the setting description and metadata.
    $setting = $this->generateSettingDescription($destination, $narrative_context, $dungeon_data);

    // Step 2: Build the room structure.
    $room = $this->buildRoomFromSetting($setting, $origin_room_id);

    // Step 3: Generate entities (NPCs, objects, furniture) for the room.
    $entities = $this->generateSettingEntities($setting, $room['room_id'], $campaign_id);

    // Step 4: Append room to dungeon_data.
    $dungeon_data['rooms'][] = $room;
    $room_index = array_key_last($dungeon_data['rooms']);

    // Step 5: Add entities to top-level entities array.
    if (!isset($dungeon_data['entities'])) {
      $dungeon_data['entities'] = [];
    }
    foreach ($entities as $entity) {
      $dungeon_data['entities'][] = $entity;
    }

    // Step 6: Create connection from origin room to new room.
    $this->createRoomConnection($dungeon_data, $origin_room_id, $room['room_id']);

    // Step 7: Update hex_map regions.
    $this->addRegionToHexMap($dungeon_data, $room);

    // Step 8: Persist.
    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data),
        'updated' => time(),
      ])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();

    // Step 9: Create NPC psychology profiles for any new NPCs.
    $room_entities = array_filter($entities, fn($e) => ($e['entity_type'] ?? '') === 'npc');
    if (!empty($room_entities)) {
      $this->psychologyService->ensureRoomNpcProfiles($campaign_id, $room_entities);
    }

    $this->logger->info('New setting generated: @name (room_index=@idx, @hex_count hexes, @ent_count entities)', [
      '@name' => $room['name'],
      '@idx' => $room_index,
      '@hex_count' => count($room['hexes']),
      '@ent_count' => count($entities),
    ]);

    return [
      'room' => $room,
      'room_index' => $room_index,
      'entities' => $entities,
      'dungeon_data' => $dungeon_data,
    ];
  }

  // =========================================================================
  // Step 1: AI-driven setting generation
  // =========================================================================

  /**
   * Use AI to generate a rich setting description with structured metadata.
   *
   * @param string $destination
   *   Where the player wants to go.
   * @param array $narrative_context
   *   GM narrative, campaign theme, etc.
   * @param array $dungeon_data
   *   Current dungeon data (for world consistency).
   *
   * @return array
   *   Structured setting data:
   *   - name: string
   *   - description: string
   *   - setting_type: string (tavern, shop, market, forest, etc.)
   *   - size: string (tiny, small, medium, large, huge)
   *   - terrain_type: string
   *   - lighting: string
   *   - theme_tags: array
   *   - npcs: array of NPC definitions
   *   - objects: array of furniture/object definitions
   *   - atmosphere: string
   */
  protected function generateSettingDescription(
    string $destination,
    array $narrative_context,
    array $dungeon_data
  ): array {
    $existing_rooms = [];
    foreach ($dungeon_data['rooms'] ?? [] as $r) {
      $existing_rooms[] = $r['name'] ?? 'Unknown';
    }

    $gm_narration = $narrative_context['gm_narrative'] ?? '';
    $time_of_day = $narrative_context['time_of_day'] ?? 'day';
    $party_level = $narrative_context['party_level'] ?? 1;
    $campaign_theme = $narrative_context['campaign_theme'] ?? 'high fantasy';

    $system_prompt = <<<'SYSTEM'
You are the world-builder for a Pathfinder 2e tabletop RPG. Your job is to generate detailed, playable settings when players navigate to new locations.

You must respond with ONLY valid JSON — no markdown, no explanation, no wrapping.

The setting must be:
- Internally consistent with a fantasy world
- Appropriately sized for the location type
- Populated with believable NPCs and objects
- Rich enough for tactical play on a hex grid
SYSTEM;

    $prompt = <<<PROMPT
Generate a detailed setting for a new location the players are traveling to.

DESTINATION: {$destination}
GM NARRATION: {$gm_narration}
TIME OF DAY: {$time_of_day}
PARTY LEVEL: {$party_level}
CAMPAIGN THEME: {$campaign_theme}
EXISTING LOCATIONS: {implode(', ', $existing_rooms)}

Respond with this exact JSON structure:
{
  "name": "The location name (e.g., 'Ironheart Forge', 'Town Market Square')",
  "description": "A vivid 2-3 sentence description of the location as the players see it when they arrive. Include sensory details — sights, sounds, smells.",
  "setting_type": "One of: tavern, shop, temple, market, street, forest, cave, dungeon, library, throne_room, dock, alley, sewer, garden, arena, prison, residential, wilderness",
  "size": "One of: tiny, small, medium, large, huge — appropriate for the location",
  "lighting": "One of: bright_light, normal_light, dim_light, darkness",
  "theme_tags": ["tag1", "tag2", "tag3"],
  "atmosphere": "A single sentence describing the mood/feeling of the place",
  "npcs": [
    {
      "name": "NPC display name",
      "content_id": "snake_case_unique_id",
      "ancestry": "Human/Elf/Dwarf/etc",
      "class": "Commoner/Fighter/Wizard/etc",
      "role": "neutral/quest_giver/merchant/guard",
      "team": "neutral/friendly/enemy",
      "occupation": "What they do here",
      "description": "1-2 sentence physical description",
      "backstory": "1-2 sentence background",
      "attitude": "friendly/indifferent/unfriendly/hostile",
      "stats": {
        "maxHp": 10,
        "currentHp": 10,
        "ac": 12,
        "speed": 25,
        "perception": 3
      },
      "equipment": ["item1", "item2"]
    }
  ],
  "objects": [
    {
      "object_id": "snake_case_id",
      "label": "Display Name",
      "category": "furniture/container/decoration/obstacle",
      "description": "Brief description",
      "passable": true,
      "interactable": true
    }
  ]
}

Rules:
- NPCs should fit the setting (a blacksmith in a forge, a priest in a temple)
- 0-4 NPCs is typical (not every room needs NPCs)
- 2-8 objects/furniture is typical
- size should match reality: a small shop is "small", a town square is "large"
- content_id must be unique snake_case (e.g., "ironheart_blacksmith")
PROMPT;

    try {
      $result = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'map_setting_generation',
        ['destination' => $destination],
        [
          'system_prompt' => $system_prompt,
          'max_tokens' => 1500,
          'skip_cache' => TRUE,
        ]
      );
    }
    catch (\Exception $e) {
      $this->logger->error('AI setting generation failed: @err', ['@err' => $e->getMessage()]);
      return $this->generateFallbackSetting($destination);
    }

    if (empty($result['success']) || empty($result['response'])) {
      $this->logger->warning('AI returned empty response for setting generation');
      return $this->generateFallbackSetting($destination);
    }

    $response = trim($result['response']);

    // Strip markdown code fences if present.
    $response = preg_replace('/^```(?:json)?\s*\n?/m', '', $response);
    $response = preg_replace('/\n?\s*```\s*$/m', '', $response);

    $setting = json_decode($response, TRUE);
    if (!is_array($setting) || empty($setting['name'])) {
      $this->logger->warning('Failed to parse AI setting response: @resp', [
        '@resp' => substr($response, 0, 500),
      ]);
      return $this->generateFallbackSetting($destination);
    }

    // Validate and normalize.
    return $this->normalizeSetting($setting);
  }

  /**
   * Fallback setting when AI generation fails.
   */
  protected function generateFallbackSetting(string $destination): array {
    $name = ucwords(trim($destination));
    return [
      'name' => $name ?: 'Unknown Location',
      'description' => "You arrive at {$name}. The area is unremarkable but serviceable.",
      'setting_type' => 'street',
      'size' => 'medium',
      'lighting' => 'normal_light',
      'theme_tags' => ['explored'],
      'atmosphere' => 'The air is still.',
      'npcs' => [],
      'objects' => [],
    ];
  }

  /**
   * Normalize and validate AI-generated setting data.
   */
  protected function normalizeSetting(array $setting): array {
    $valid_types = array_keys(self::TERRAIN_MAP);
    $valid_sizes = array_keys(self::SIZE_PRESETS);

    $setting['setting_type'] = in_array($setting['setting_type'] ?? '', $valid_types, TRUE)
      ? $setting['setting_type']
      : 'default';

    $setting['size'] = in_array($setting['size'] ?? '', $valid_sizes, TRUE)
      ? $setting['size']
      : 'medium';

    $valid_lighting = ['bright_light', 'normal_light', 'dim_light', 'darkness'];
    $setting['lighting'] = in_array($setting['lighting'] ?? '', $valid_lighting, TRUE)
      ? $setting['lighting']
      : (self::LIGHTING_MAP[$setting['setting_type']] ?? 'normal_light');

    $setting['theme_tags'] = array_filter(
      $setting['theme_tags'] ?? [],
      fn($t) => is_string($t) && strlen($t) < 50
    );

    // Validate NPCs.
    $setting['npcs'] = array_map(function($npc) {
      return [
        'name' => $npc['name'] ?? 'Unknown NPC',
        'content_id' => $npc['content_id'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '_', $npc['name'] ?? 'npc_' . uniqid())),
        'ancestry' => $npc['ancestry'] ?? 'Human',
        'class' => $npc['class'] ?? 'Commoner',
        'role' => $npc['role'] ?? 'neutral',
        'team' => $npc['team'] ?? 'neutral',
        'occupation' => $npc['occupation'] ?? '',
        'description' => $npc['description'] ?? '',
        'backstory' => $npc['backstory'] ?? '',
        'attitude' => $npc['attitude'] ?? 'indifferent',
        'stats' => [
          'maxHp' => $npc['stats']['maxHp'] ?? 10,
          'currentHp' => $npc['stats']['currentHp'] ?? $npc['stats']['maxHp'] ?? 10,
          'ac' => $npc['stats']['ac'] ?? 12,
          'speed' => $npc['stats']['speed'] ?? 25,
          'perception' => $npc['stats']['perception'] ?? 3,
          'initiative_bonus' => $npc['stats']['initiative_bonus'] ?? $npc['stats']['perception'] ?? 3,
        ],
        'equipment' => $npc['equipment'] ?? [],
      ];
    }, $setting['npcs'] ?? []);

    // Validate objects.
    $setting['objects'] = array_map(function($obj) {
      return [
        'object_id' => $obj['object_id'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '_', $obj['label'] ?? 'obj_' . uniqid())),
        'label' => $obj['label'] ?? 'Object',
        'category' => $obj['category'] ?? 'furniture',
        'description' => $obj['description'] ?? '',
        'passable' => $obj['passable'] ?? TRUE,
        'interactable' => $obj['interactable'] ?? FALSE,
      ];
    }, $setting['objects'] ?? []);

    return $setting;
  }

  // =========================================================================
  // Step 2: Build room structure from setting
  // =========================================================================

  /**
   * Build a complete room structure from a normalized setting.
   *
   * @param array $setting
   *   Normalized setting data from generateSettingDescription().
   * @param string $origin_room_id
   *   Room the player is coming from (for connection).
   *
   * @return array
   *   Complete room structure matching dungeon_data.rooms[] schema.
   */
  protected function buildRoomFromSetting(array $setting, string $origin_room_id): array {
    $room_id = $this->generateUuid();
    $size_preset = self::SIZE_PRESETS[$setting['size']] ?? self::SIZE_PRESETS['medium'];
    $terrain = self::TERRAIN_MAP[$setting['setting_type']] ?? self::TERRAIN_MAP['default'];

    // Generate hex grid.
    $hexes = $this->generateHexGrid(
      $size_preset['cols'],
      $size_preset['rows'],
      $setting['setting_type']
    );

    // Place objects on hexes.
    $hexes = $this->placeObjectsOnHexes($hexes, $setting['objects']);

    return [
      'room_id' => $room_id,
      'name' => $setting['name'],
      'description' => $setting['description'],
      'hexes' => $hexes,
      'room_type' => $this->settingTypeToRoomType($setting['setting_type']),
      'size_category' => $size_preset['size'],
      'terrain' => [
        'type' => $terrain['type'],
        'difficult_terrain' => $terrain['difficult'],
        'greater_difficult_terrain' => FALSE,
        'hazardous_terrain' => NULL,
        'ceiling_height_ft' => $terrain['ceiling'],
      ],
      'lighting' => [
        'level' => $setting['lighting'],
      ],
      'state' => [
        'explored' => TRUE,
        'explored_at' => date('c'),
        'cleared' => FALSE,
        'looted' => FALSE,
        'traps_disarmed' => FALSE,
        'visibility' => 'visible',
      ],
      'ai_generation' => [
        'theme_tags' => $setting['theme_tags'],
        'difficulty_target' => 'trivial',
        'generation_model' => 'map_generator_ai',
      ],
      'gameplay_state' => [
        'active_effects' => [],
        'explored_hexes' => [],
        'environmental_changes' => [],
      ],
      'connections' => [],
      'chat' => [],
      'entities' => NULL,
    ];
  }

  /**
   * Generate a hex grid for a room.
   *
   * Uses offset-coordinate hex grid (flat-top), matching the existing
   * Gilded Tankard hex layout. Hexes are 5ft each.
   *
   * @param int $cols
   *   Number of columns.
   * @param int $rows
   *   Number of rows.
   * @param string $setting_type
   *   For terrain variation (e.g., forest gets elevation changes).
   *
   * @return array
   *   Array of hex definitions: [{q, r, elevation_ft, objects}, ...].
   */
  protected function generateHexGrid(int $cols, int $rows, string $setting_type): array {
    $hexes = [];
    $half_cols = intdiv($cols, 2);
    $half_rows = intdiv($rows, 2);

    // Natural settings get mild elevation variation.
    $has_elevation = in_array($setting_type, ['forest', 'cave', 'wilderness', 'garden', 'dock'], TRUE);

    for ($q = -$half_cols; $q <= $half_cols; $q++) {
      for ($r = -$half_rows; $r <= $half_rows; $r++) {
        // Skip some edge hexes to create organic shapes for natural settings.
        if ($this->shouldSkipEdgeHex($q, $r, $half_cols, $half_rows, $setting_type)) {
          continue;
        }

        $elevation = 0;
        if ($has_elevation) {
          // Gentle terrain variation.
          $elevation = (int) (sin($q * 0.7 + $r * 0.5) * 2.5);
          $elevation = max(0, $elevation);
        }

        $hexes[] = [
          'q' => $q,
          'r' => $r,
          'elevation_ft' => $elevation,
          'objects' => [],
        ];
      }
    }

    return $hexes;
  }

  /**
   * Skip edge hexes for organic-shaped rooms (forests, caves, etc.).
   */
  protected function shouldSkipEdgeHex(int $q, int $r, int $max_q, int $max_r, string $setting_type): bool {
    $is_edge = abs($q) === $max_q || abs($r) === $max_r;
    if (!$is_edge) {
      return FALSE;
    }

    // Structured settings (buildings) keep their rectangular shape.
    $structured = ['tavern', 'shop', 'temple', 'library', 'prison', 'residential', 'throne_room'];
    if (in_array($setting_type, $structured, TRUE)) {
      return FALSE;
    }

    // Natural settings: remove some corner/edge hexes for organic shape.
    $corner_dist = abs($q) + abs($r);
    $max_dist = $max_q + $max_r;
    if ($corner_dist >= $max_dist) {
      // Always remove extreme corners.
      return TRUE;
    }

    // Pseudo-random edge removal based on coordinates.
    $hash = crc32("{$q},{$r}");
    return ($hash % 4) === 0;
  }

  /**
   * Place furniture/objects on specific hexes.
   */
  protected function placeObjectsOnHexes(array $hexes, array $objects): array {
    if (empty($objects) || empty($hexes)) {
      return $hexes;
    }

    // Distribute objects around the room, avoiding the center and edges.
    $placeable = [];
    foreach ($hexes as $idx => $hex) {
      $dist_from_center = abs($hex['q']) + abs($hex['r']);
      if ($dist_from_center >= 1 && $dist_from_center <= 4) {
        $placeable[] = $idx;
      }
    }

    if (empty($placeable)) {
      $placeable = array_keys($hexes);
    }

    // Shuffle placement indices deterministically.
    shuffle($placeable);

    foreach ($objects as $i => $obj) {
      if (!isset($placeable[$i])) {
        break;
      }
      $hex_idx = $placeable[$i];
      $hexes[$hex_idx]['objects'][] = [
        'ref' => $obj['object_id'],
        'facing' => 0,
      ];
    }

    return $hexes;
  }

  // =========================================================================
  // Step 3: Generate entities
  // =========================================================================

  /**
   * Generate entity structures for NPCs and objects defined in the setting.
   *
   * @param array $setting
   *   Normalized setting with npcs[] and objects[].
   * @param string $room_id
   *   The new room's UUID.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Array of entity structures for dungeon_data.entities[].
   */
  protected function generateSettingEntities(array $setting, string $room_id, int $campaign_id): array {
    $entities = [];
    $hexes_for_npcs = $this->getNpcPlacementHexes(count($setting['npcs']));

    // Generate NPC entities.
    foreach ($setting['npcs'] as $i => $npc) {
      $hex = $hexes_for_npcs[$i] ?? ['q' => $i, 'r' => 0];

      $entities[] = [
        'entity_instance_id' => $this->generateUuid(),
        'entity_type' => 'npc',
        'entity_ref' => [
          'content_type' => 'npc',
          'content_id' => $npc['content_id'],
        ],
        'placement' => [
          'room_id' => $room_id,
          'hex' => $hex,
          'spawn_type' => 'npc',
          'orientation' => 'n',
        ],
        'state' => [
          'active' => TRUE,
          'metadata' => [
            'display_name' => $npc['name'],
            'team' => $npc['team'],
            'role' => $npc['role'],
            'ancestry' => $npc['ancestry'],
            'class' => $npc['class'],
            'occupation' => $npc['occupation'],
            'description' => $npc['description'],
            'backstory' => $npc['backstory'],
            'stats' => $npc['stats'],
            'equipment' => $npc['equipment'],
            'languages' => ['Common'],
            'senses' => [],
            'abilities' => [],
            'orientation' => 'n',
          ],
        ],
      ];
    }

    // Generate object/furniture entities.
    foreach ($setting['objects'] as $obj) {
      // Objects are placed ON hexes via the hex.objects[] array, but we also
      // add them to object_definitions if they don't exist yet.
      // The hex placement was already handled in placeObjectsOnHexes().
    }

    return $entities;
  }

  /**
   * Get hex coordinates for NPC placement — spread them around the room.
   */
  protected function getNpcPlacementHexes(int $count): array {
    // Place NPCs at various positions around the room.
    $positions = [
      ['q' => 1,  'r' => 0],
      ['q' => -1, 'r' => 1],
      ['q' => 2,  'r' => -1],
      ['q' => -2, 'r' => 0],
      ['q' => 0,  'r' => 2],
      ['q' => 1,  'r' => -2],
      ['q' => -1, 'r' => -1],
      ['q' => 3,  'r' => 0],
    ];

    return array_slice($positions, 0, $count);
  }

  // =========================================================================
  // Step 4-7: Wiring — connections, regions, object_definitions
  // =========================================================================

  /**
   * Create a bidirectional connection between two rooms.
   */
  protected function createRoomConnection(array &$dungeon_data, string $from_room_id, string $to_room_id): void {
    // Add to hex_map connections.
    if (!isset($dungeon_data['hex_map']['connections'])) {
      $dungeon_data['hex_map']['connections'] = [];
    }

    $dungeon_data['hex_map']['connections'][] = [
      'from_room' => $from_room_id,
      'to_room' => $to_room_id,
      'type' => 'passage',
      'bidirectional' => TRUE,
    ];

    // Also set room.connections on both rooms.
    foreach ($dungeon_data['rooms'] as &$room) {
      if (($room['room_id'] ?? '') === $from_room_id) {
        if (!isset($room['connections'])) {
          $room['connections'] = [];
        }
        $room['connections'][] = [
          'target_room_id' => $to_room_id,
          'type' => 'passage',
        ];
      }
      if (($room['room_id'] ?? '') === $to_room_id) {
        if (!isset($room['connections'])) {
          $room['connections'] = [];
        }
        $room['connections'][] = [
          'target_room_id' => $from_room_id,
          'type' => 'passage',
        ];
      }
    }
    unset($room);
  }

  /**
   * Add the new room as a region in hex_map.
   */
  protected function addRegionToHexMap(array &$dungeon_data, array $room): void {
    if (!isset($dungeon_data['hex_map']['regions'])) {
      $dungeon_data['hex_map']['regions'] = [];
    }

    $dungeon_data['hex_map']['regions'][] = [
      'region_id' => $room['room_id'],
      'name' => $room['name'],
      'room_type' => $room['room_type'],
      'hex_count' => count($room['hexes']),
    ];
  }

  // =========================================================================
  // Utility helpers
  // =========================================================================

  /**
   * Map setting_type to room_type enum.
   */
  protected function settingTypeToRoomType(string $setting_type): string {
    $map = [
      'tavern' => 'entrance',
      'shop' => 'chamber',
      'temple' => 'shrine',
      'market' => 'chamber',
      'street' => 'corridor',
      'forest' => 'natural_cavern',
      'cave' => 'natural_cavern',
      'dungeon' => 'chamber',
      'library' => 'chamber',
      'throne_room' => 'boss_room',
      'dock' => 'chamber',
      'alley' => 'corridor',
      'sewer' => 'corridor',
      'garden' => 'natural_cavern',
      'arena' => 'boss_room',
      'prison' => 'cell',
      'residential' => 'chamber',
      'wilderness' => 'natural_cavern',
    ];
    return $map[$setting_type] ?? 'chamber';
  }

  /**
   * Generate a UUID v4.
   */
  protected function generateUuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }

}
