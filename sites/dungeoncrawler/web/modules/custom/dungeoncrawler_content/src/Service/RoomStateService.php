<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages per-room runtime state with optimistic versioning.
 */
class RoomStateService {

  private Connection $database;
  private LoggerInterface $logger;

  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
  }

  /**
   * Get room state for a campaign.
   *
   * @throws \InvalidArgumentException 404 when not found.
   */
  public function getState(int $campaign_id, string $room_id): array {
    // Static room definition (layout, contents, tags).
    $room = $this->database->select('dc_campaign_rooms', 'c')
      ->fields('c', ['room_id', 'campaign_id', 'name', 'description', 'environment_tags', 'layout_data', 'contents_data'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$room) {
      throw new \InvalidArgumentException('Room not found', 404);
    }

    // Runtime state row.
    $record = $this->database->select('dc_campaign_room_states', 'r')
      ->fields('r', ['room_id', 'campaign_id', 'is_cleared', 'fog_state', 'last_visited', 'updated'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      throw new \InvalidArgumentException('Room state not found', 404);
    }

    $state = json_decode($record['fog_state'] ?? '', TRUE);
    if (!is_array($state)) {
      $state = [];
    }

    $state['isCleared'] = (bool) ($record['is_cleared'] ?? 0);

    $environment_tags = json_decode($room['environment_tags'] ?? '', TRUE);
    if (!is_array($environment_tags)) {
      $environment_tags = [];
    }

    $layout = json_decode($room['layout_data'] ?? '', TRUE);
    if (!is_array($layout)) {
      $layout = [];
    }

    $contents = json_decode($room['contents_data'] ?? '', TRUE);
    if (!is_array($contents)) {
      $contents = [];
    }

    // Limit layout hexes to those currently visible/LOS to the player to avoid leaking fogged areas.
    $visible_ids = [];
    if (isset($state['visibleHexIds']) && is_array($state['visibleHexIds'])) {
      $visible_ids = $state['visibleHexIds'];
    }
    elseif (isset($state['visible_hex_ids']) && is_array($state['visible_hex_ids'])) {
      $visible_ids = $state['visible_hex_ids'];
    }

    if (!empty($visible_ids) && isset($layout['hexes']) && is_array($layout['hexes'])) {
      $layout['hexes'] = array_values(array_filter($layout['hexes'], function ($hex) use ($visible_ids) {
        $id = $hex['id'] ?? $hex['hex_id'] ?? $hex['uuid'] ?? NULL;
        return $id !== NULL && in_array($id, $visible_ids, TRUE);
      }));
    }

    // Filter contents to only those placed in visible hexes, if placement data has hex refs.
    if (!empty($visible_ids) && isset($contents['objects']) && is_array($contents['objects'])) {
      $contents['objects'] = array_values(array_filter($contents['objects'], function ($obj) use ($visible_ids) {
        $hex_ref = $obj['hex_id'] ?? $obj['hexId'] ?? $obj['position']['hexId'] ?? NULL;
        return $hex_ref === NULL || in_array($hex_ref, $visible_ids, TRUE);
      }));
    }
    if (!empty($visible_ids) && isset($contents['entities']) && is_array($contents['entities'])) {
      $contents['entities'] = array_values(array_filter($contents['entities'], function ($ent) use ($visible_ids) {
        $hex_ref = $ent['hex_id'] ?? $ent['hexId'] ?? $ent['position']['hexId'] ?? NULL;
        return $hex_ref === NULL || in_array($hex_ref, $visible_ids, TRUE);
      }));
    }

    return [
      'campaignId' => $campaign_id,
      'roomId' => $record['room_id'],
      'room' => [
        'roomId' => $room['room_id'],
        'name' => $room['name'],
        'description' => $room['description'],
        'environmentTags' => $environment_tags,
        'layout' => $layout,
        'contents' => $contents,
      ],
      'state' => $state,
      'version' => (int) ($record['updated'] ?? 0),
      'updatedAt' => $record['updated'] ? date('c', (int) $record['updated']) : date('c'),
    ];
  }

  /**
   * Set room state with optimistic locking on updated timestamp.
   *
   * @throws \InvalidArgumentException 404 when base row missing and expectedVersion provided.
   * @throws \InvalidArgumentException 409 on version conflict.
   */
  public function setState(int $campaign_id, string $room_id, string $dungeon_id, array $state, ?int $expected_version): array {
    $record = $this->database->select('dc_campaign_room_states', 'r')
      ->fields('r', ['room_id', 'campaign_id', 'is_cleared', 'fog_state', 'last_visited', 'updated'])
      ->condition('campaign_id', $campaign_id)
      ->condition('room_id', $room_id)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record && $expected_version !== NULL) {
      throw new \InvalidArgumentException('Room state not found', 404);
    }

    $current_version = (int) ($record['updated'] ?? 0);
    if ($expected_version !== NULL && $expected_version !== $current_version) {
      $this->logger->warning('Room state version conflict for room {room} campaign {campaign}: expected {expected} got {current}', [
        'room' => $room_id,
        'campaign' => $campaign_id,
        'expected' => $expected_version,
        'current' => $current_version,
      ]);
      throw new \InvalidArgumentException('Version conflict', 409);
    }

    $now = time();
    $is_cleared = !empty($state['isCleared']) ? 1 : 0;

    // Persist full state JSON; include dungeonId for enforcement/audit.
    $state_payload = $state;
    $state_payload['dungeonId'] = $dungeon_id;
    $fog_state = json_encode($state_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($record) {
      $this->database->update('dc_campaign_room_states')
        ->fields([
          'is_cleared' => $is_cleared,
          'fog_state' => $fog_state,
          'last_visited' => $now,
          'updated' => $now,
        ])
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $room_id)
        ->execute();
    }
    else {
      $this->database->insert('dc_campaign_room_states')
        ->fields([
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'is_cleared' => $is_cleared,
          'fog_state' => $fog_state,
          'last_visited' => $now,
          'updated' => $now,
        ])
        ->execute();
    }

    // Return fresh combined view with static room data.
    return $this->getState($campaign_id, $room_id);
  }

}
