<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for hex map rendering and interaction.
 */
class HexMapController extends ControllerBase {

  protected RequestStack $requestStack;

  protected Connection $database;
  public function __construct(RequestStack $request_stack, Connection $database) {
    $this->requestStack = $request_stack;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('database'),
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
