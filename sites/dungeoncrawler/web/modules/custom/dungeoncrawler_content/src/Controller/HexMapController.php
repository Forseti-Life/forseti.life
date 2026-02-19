<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\QuestTrackerService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for hex map rendering and interaction.
 */
class HexMapController extends ControllerBase {

  protected RequestStack $requestStack;

  protected Connection $database;
  protected QuestTrackerService $questTracker;
  public function __construct(RequestStack $request_stack, Connection $database, QuestTrackerService $quest_tracker) {
    $this->requestStack = $request_stack;
    $this->database = $database;
    $this->questTracker = $quest_tracker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('database'),
      $container->get('dungeoncrawler_content.quest_tracker'),
    );
  }

  /**
   * Hex map demo page.
   *
   * @return array
   *   Render array for the hex map demo.
   */
  public function demo() {
    $query = $this->requestStack->getCurrentRequest()->query;

    $launch_context = [
      'campaign_id' => (int) ($query->get('campaign_id') ?? 0),
      'character_id' => (int) ($query->get('character_id') ?? 0),
      'dungeon_level_id' => (string) ($query->get('dungeon_level_id') ?? ''),
      'map_id' => (string) ($query->get('map_id') ?? ''),
      'room_id' => (string) ($query->get('room_id') ?? ''),
      'next_room_id' => (string) ($query->get('next_room_id') ?? ''),
      'start_q' => (int) ($query->get('start_q') ?? 0),
      'start_r' => (int) ($query->get('start_r') ?? 0),
    ];

    $dungeon_payload = $this->loadDungeonPayload($launch_context);
    $launch_character = $this->loadLaunchCharacterSummary($launch_context);
    $quest_summary = $this->loadQuestSummary($launch_context);

    return [
      '#theme' => 'hexmap_demo',
      '#launch_context' => $launch_context,
      '#dungeon_payload' => $dungeon_payload,
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/hexmap',
        ],
        'drupalSettings' => [
          'dungeoncrawlerContent' => [
            'hexmapLaunchContext' => $launch_context,
            'hexmapDungeonData' => $dungeon_payload,
            'hexmapLaunchCharacter' => $launch_character,
            'hexmapQuestSummary' => $quest_summary,
          ],
        ],
      ],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['url.query_args:campaign_id', 'url.query_args:character_id', 'url.query_args:dungeon_level_id', 'url.query_args:map_id', 'url.query_args:room_id', 'url.query_args:next_room_id', 'url.query_args:start_q', 'url.query_args:start_r'],
      ],
    ];
  }

  /**
   * Load lightweight launch character summary for UI hydration.
   *
   * @param array $launch_context
   *   Current launch context query values.
   *
   * @return array
   *   Character summary for character sheet fallback.
   */
  protected function loadLaunchCharacterSummary(array $launch_context): array {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $character_id = (int) ($launch_context['character_id'] ?? 0);

    if ($campaign_id <= 0 || $character_id <= 0) {
      return [];
    }

    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'name', 'level', 'ancestry', 'class', 'hp_current', 'hp_max', 'armor_class', 'character_data'])
      ->condition('campaign_id', $campaign_id);

    $character_match = $query->orConditionGroup()
      ->condition('character_id', $character_id)
      ->condition('id', $character_id)
      ->condition('instance_id', sprintf('pc-%d-%d', $campaign_id, $character_id));

    $record = $query
      ->condition($character_match)
      ->orderBy('updated', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      // Fallback to canonical library/fact character record by direct ID.
      $record = $this->database->select('dc_campaign_characters', 'cc')
        ->fields('cc', ['id', 'name', 'level', 'ancestry', 'class', 'hp_current', 'hp_max', 'armor_class', 'character_data'])
        ->condition('id', $character_id)
        ->orderBy('updated', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
    }

    if (!$record) {
      return [
        'name' => sprintf('Character %d', $character_id),
        'level' => 0,
        'ancestry' => '',
        'class' => '',
        'hp_current' => 0,
        'hp_max' => 0,
        'armor_class' => 0,
        'team' => 'player',
        'entity_type' => 'player_character',
      ];
    }

    $character_data = json_decode((string) ($record['character_data'] ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }

    $name = (string) ($record['name'] ?? '');
    if ($name === '') {
      $name = (string) ($character_data['name'] ?? sprintf('Character %d', $character_id));
    }

    $ancestry = (string) ($record['ancestry'] ?? '');
    if ($ancestry === '') {
      $ancestry = is_array($character_data['ancestry'] ?? NULL)
        ? (string) ($character_data['ancestry']['name'] ?? '')
        : (string) ($character_data['ancestry'] ?? '');
    }

    $class = (string) ($record['class'] ?? '');
    if ($class === '') {
      $class = is_array($character_data['class'] ?? NULL)
        ? (string) ($character_data['class']['name'] ?? '')
        : (string) ($character_data['class'] ?? '');
    }

    $hp_max = (int) ($record['hp_max'] ?? 0);
    if ($hp_max <= 0) {
      $hp_max = (int) ($character_data['hp']['max'] ?? $character_data['calculated_stats']['max_hp'] ?? 0);
    }

    $hp_current = (int) ($record['hp_current'] ?? 0);
    if ($hp_current <= 0 && $hp_max > 0) {
      $hp_current = (int) ($character_data['hp']['current'] ?? $hp_max);
    }

    $armor_class = (int) ($record['armor_class'] ?? 0);
    if ($armor_class <= 0) {
      $armor_class = (int) ($character_data['ac'] ?? $character_data['calculated_stats']['ac'] ?? 0);
    }

    $level = (int) ($record['level'] ?? 0);
    if ($level <= 0) {
      $level = (int) ($character_data['level'] ?? 0);
    }

    // Extract ability scores
    $abilities = $character_data['abilities'] ?? [];
    if (!is_array($abilities)) {
      $abilities = [
        'strength' => 10,
        'dexterity' => 10,
        'constitution' => 10,
        'intelligence' => 10,
        'wisdom' => 10,
        'charisma' => 10,
      ];
    }

    // Extract skills
    $skills = $character_data['skills'] ?? [];
    if (!is_array($skills)) {
      $skills = [];
    }

    // Extract features/feats
    $feats = $character_data['feats'] ?? [];
    if (!is_array($feats)) {
      $feats = [];
    }

    // Extract inventory
    $equipment = $character_data['equipment'] ?? [];
    $gold = $character_data['gold'] ?? 0;

    // Extract hero points
    $hero_points = $character_data['hero_points'] ?? 1;

    // Extract conditions
    $conditions = $character_data['conditions'] ?? [];

    return [
      'name' => $name,
      'level' => $level,
      'ancestry' => $ancestry,
      'class' => $class,
      'hp_current' => $hp_current,
      'hp_max' => $hp_max,
      'armor_class' => $armor_class,
      'team' => 'player',
      'entity_type' => 'player_character',
      // Enhanced character sheet data
      'abilities' => $abilities,
      'skills' => $skills,
      'feats' => $feats,
      'equipment' => $equipment,
      'currency' => [
        'gp' => $gold,
        'sp' => 0,
        'cp' => 0,
      ],
      'hero_points' => $hero_points,
      'conditions' => $conditions,
    ];
  }

  /**
   * Load active and available quest summaries for launch context.
   */
  protected function loadQuestSummary(array $launch_context): array {
    $campaign_id = (int) ($launch_context['campaign_id'] ?? 0);
    $character_id = (int) ($launch_context['character_id'] ?? 0);

    if ($campaign_id <= 0 || $character_id <= 0) {
      return [];
    }

    $location_id = (string) ($launch_context['room_id'] ?? '');
    if ($location_id === '') {
      $location_id = (string) ($launch_context['map_id'] ?? '');
    }
    if ($location_id === '') {
      $location_id = 'tavern_entrance';
    }

    $active = $this->questTracker->getActiveQuests($campaign_id, $character_id);
    $available = $this->questTracker->getAvailableQuests($campaign_id, $location_id, $character_id);

    $normalize = static function (array $quest): array {
      $quest['generated_objectives'] = json_decode((string) ($quest['generated_objectives'] ?? '[]'), TRUE) ?? [];
      $quest['generated_rewards'] = json_decode((string) ($quest['generated_rewards'] ?? '[]'), TRUE) ?? [];
      $quest['objective_states'] = json_decode((string) ($quest['objective_states'] ?? '[]'), TRUE) ?? [];
      $quest['quest_data'] = json_decode((string) ($quest['quest_data'] ?? '{}'), TRUE) ?? [];
      return $quest;
    };

    $active_summary = array_map($normalize, $active);
    $available_summary = array_map($normalize, $available);

    return [
      'location_id' => $location_id,
      'active' => $active_summary,
      'available' => $available_summary,
      'counts' => [
        'active' => count($active_summary),
        'available' => count($available_summary),
      ],
    ];
  }

  /**
   * Load and normalize the tavern entrance example payload for hexmap runtime use.
   *
   * @param array $launch_context
   *   Current launch context query values.
   *
   * @return array
   *   Normalized dungeon payload.
   */
  protected function loadDungeonPayload(array $launch_context): array {
    $campaign_id = $launch_context['campaign_id'] ?? 0;

    if ($campaign_id > 0) {
      $query = $this->database->select('dc_campaign_dungeons', 'd')
        ->fields('d', ['dungeon_data'])
        ->condition('campaign_id', $campaign_id);

      // If caller supplied a map_id use it as dungeon_id selector when present.
      if (!empty($launch_context['map_id'])) {
        $query->condition('dungeon_id', $launch_context['map_id']);
      }

      $query->orderBy('updated', 'DESC');
      $query->orderBy('id', 'DESC');
      $raw = $query->range(0, 1)->execute()->fetchField();
      if ($raw !== FALSE) {
        $decoded = json_decode($raw, TRUE);
        if (is_array($decoded)) {
          return $this->normalizeDungeonPayload($decoded, $launch_context);
        }
      }
    }

    // Fallback to example payload when no campaign data is available.
    $example_path = dirname(__DIR__, 2) . '/config/examples/tavern-entrance-dungeon.json';
    $decoded = $this->readJsonFile($example_path);
    if (!is_array($decoded)) {
      return [];
    }

    $obstacle_catalog_path = dirname(__DIR__, 2) . '/config/examples/tavern-obstacle-objects.json';
    $obstacle_catalog = $this->readJsonFile($obstacle_catalog_path);
    $decoded['object_definitions'] = $obstacle_catalog['objects'] ?? [];

    return $this->normalizeDungeonPayload($decoded, $launch_context);
  }

  /**
   * Normalize a dungeon payload to the hexmap-ready shape.
   */
  protected function normalizeDungeonPayload(array $decoded, array $launch_context): array {
    $object_definitions = [];
    foreach (($decoded['object_definitions'] ?? []) as $object_definition) {
      if (!is_array($object_definition) || empty($object_definition['object_id'])) {
        continue;
      }
      $object_definitions[(string) $object_definition['object_id']] = $object_definition;
    }

    $rooms = [];
    foreach (($decoded['rooms'] ?? []) as $room) {
      if (!is_array($room) || empty($room['room_id'])) {
        continue;
      }
      $rooms[$room['room_id']] = [
        'room_id' => (string) $room['room_id'],
        'name' => (string) ($room['name'] ?? ''),
        'description' => (string) ($room['description'] ?? ''),
        'hexes' => is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [],
      ];
    }

    $active_room_id = (string) ($launch_context['room_id'] ?? '');
    if (!$active_room_id && !empty($rooms)) {
      $active_room_id = (string) array_key_first($rooms);
    }

    return [
      'schema_version' => (string) ($decoded['schema_version'] ?? '1.0.0'),
      'level_id' => (string) ($decoded['level_id'] ?? ''),
      'map_id' => (string) ($decoded['hex_map']['map_id'] ?? ''),
      'active_room_id' => $active_room_id,
      'rooms' => $rooms,
      'connections' => is_array($decoded['hex_map']['connections'] ?? NULL) ? $decoded['hex_map']['connections'] : [],
      'entities' => is_array($decoded['entities'] ?? NULL) ? $decoded['entities'] : [],
      'object_definitions' => $object_definitions,
    ];
  }

  /**
   * Read and decode a JSON file into an associative array.
   *
   * @param string $path
   *   Absolute path to JSON file.
   *
   * @return array|null
   *   Decoded array or NULL when unreadable/invalid.
   */
  protected function readJsonFile(string $path): ?array {
    if (!is_file($path)) {
      return NULL;
    }

    $contents = file_get_contents($path);
    if ($contents === FALSE) {
      return NULL;
    }

    $decoded = json_decode($contents, TRUE);
    return is_array($decoded) ? $decoded : NULL;
  }

}
