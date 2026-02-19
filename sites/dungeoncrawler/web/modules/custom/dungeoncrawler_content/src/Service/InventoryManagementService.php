<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Manages inventory and item transfers between characters and containers.
 *
 * This service provides a comprehensive inventory management system supporting:
 * - Item transfers between characters and containers
 * - Bulk calculation and encumbrance tracking
 * - Item state preservation (equipped, worn, runes, conditions)
 * - Transfer validation and authorization
 * - Operation logging
 *
 * @see docs/dungeoncrawler/INVENTORY_MANAGEMENT_SYSTEM.md
 */
class InventoryManagementService {

  protected Connection $database;
  protected AccountProxyInterface $currentUser;
  protected CharacterStateService $characterStateService;

  /**
   * Bulk weight mappings per PF2e spec.
   */
  private const BULK_MAP = [
    'negligible' => 0,
    'light' => 0.1,
    'L' => 0.1,
    '1' => 1,
    'medium' => 1,
  ];

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    AccountProxyInterface $current_user,
    CharacterStateService $character_state_service
  ) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->characterStateService = $character_state_service;
  }

  /**
   * Get inventory for a character or container.
   *
   * This is the unified inventory method that pulls from dc_campaign_item_instances
   * as the source of truth, then syncs with character state JSON.
   *
   * @param string $owner_id
   *   Character ID or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param int $campaign_id
   *   Campaign ID (required for campaign instances).
   *
   * @return array
   *   Inventory array with items grouped by location.
   *
   * @throws \InvalidArgumentException
   *   If owner not found or invalid type.
   */
  public function getInventory(
    string $owner_id,
    string $owner_type = 'character',
    ?int $campaign_id = NULL
  ): array {
    if (!in_array($owner_type, ['character', 'container', 'room'])) {
      throw new \InvalidArgumentException("Invalid owner type: {$owner_type}");
    }

    // For characters, load from item instances table (source of truth)
    if ($owner_type === 'character') {
      return $this->getCharacterInventoryFromInstances($owner_id, $campaign_id);
    }

    // For containers and rooms, load from items table
    return $this->getContainerInventory($owner_id, $campaign_id);
  }

  /**
   * Get character inventory from item instances table.
   *
   * This is the source of truth for character inventory.
   *
   * @param string $character_id
   *   Character ID.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Inventory organized by location.
   */
  protected function getCharacterInventoryFromInstances(
    string $character_id,
    ?int $campaign_id = NULL
  ): array {
    $query = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i')
      ->condition('location_ref', $character_id);

    if ($campaign_id !== NULL) {
      $query->condition('campaign_id', $campaign_id);
    }

    $items = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    // Organize by location type
    $inventory = [
      'worn' => [
        'weapons' => [],
        'armor' => [],
        'accessories' => [],
      ],
      'carried' => [],
      'equipped' => [],
      'stashed' => [],
      'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
      'totalBulk' => 0,
      'encumbrance' => 'unencumbered',
    ];

    foreach ($items as $item_row) {
      $state = json_decode($item_row['state_data'], TRUE) ?? [];
      
      $item_data = [
        'item_instance_id' => $item_row['item_instance_id'],
        'item_id' => $item_row['item_id'],
        'quantity' => (int) $item_row['quantity'],
        'location' => $item_row['location_type'],
        ...($state ?? []),
      ];

      $location = $item_row['location_type'];

      switch ($location) {
        case 'worn':
          $type = $state['type'] ?? 'accessory';
          if ($type === 'weapon') {
            $inventory['worn']['weapons'][] = $item_data;
          }
          elseif ($type === 'armor') {
            $inventory['worn']['armor'] = $item_data;
          }
          else {
            $inventory['worn']['accessories'][] = $item_data;
          }
          break;

        case 'equipped':
          $inventory['equipped'][] = $item_data;
          break;

        case 'stashed':
          $inventory['stashed'][] = $item_data;
          break;

        case 'carried':
        default:
          $inventory['carried'][] = $item_data;
          break;
      }
    }

    // Calculate bulk and encumbrance
    $current_bulk = $this->calculateCurrentBulk($character_id, 'character', $campaign_id);
    $capacity = $this->getInventoryCapacity($character_id, 'character');
    
    $inventory['totalBulk'] = $current_bulk;
    $inventory['encumbrance'] = $this->getEncumbranceStatus($current_bulk, $capacity);

    return $inventory;
  }

  /**
   * Add item to inventory.
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param array $item
   *   Item data to add.
   * @param string $location
   *   Item location: 'carried', 'stash', 'equipped'.
   * @param int $quantity
   *   Number of items to add.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Updated inventory state.
   *
   * @throws \Exception
   *   On validation or persistence errors.
   */
  public function addItemToInventory(
    string $owner_id,
    string $owner_type,
    array $item,
    string $location = 'carried',
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    $this->validateItemData($item);
    $this->validateOwner($owner_id, $owner_type);

    if ($quantity < 1) {
      throw new \InvalidArgumentException('Quantity must be at least 1');
    }

    try {
      $this->database->startTransaction();

      // Persist item instance for tracking
      $item_instance_id = $this->createItemInstance(
        $owner_id,
        $owner_type,
        $item,
        $location,
        $quantity,
        $campaign_id
      );

      // Update character state
      $inventory = $this->getInventory($owner_id, $owner_type, $campaign_id);

      // Sync character state if character owner
      if ($owner_type === 'character') {
        $this->syncCharacterStateInventory($owner_id, $campaign_id);
      }

      // Log operation
      $this->logInventoryOperation(
        'add_item',
        $owner_id,
        $owner_type,
        $campaign_id,
        [
          'item_id' => $item['id'] ?? '',
          'item_instance_id' => $item_instance_id,
          'quantity' => $quantity,
          'location' => $location,
        ]
      );

      $this->database->commit();

      return [
        'success' => TRUE,
        'inventory' => $inventory,
        'item_instance_id' => $item_instance_id,
        'message' => "Added {$quantity} of '{$item['name']}' to {$owner_type}",
      ];
    }
    catch (\Exception $e) {
      $this->database->rollBack();
      throw $e;
    }
  }

  /**
   * Remove item from inventory.
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param string $item_instance_id
   *   Item instance ID to remove.
   * @param int $quantity
   *   Number of items to remove (partial removal).
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Updated inventory state.
   *
   * @throws \Exception
   *   On validation or persistence errors.
   */
  public function removeItemFromInventory(
    string $owner_id,
    string $owner_type,
    string $item_instance_id,
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    $this->validateOwner($owner_id, $owner_type);

    if ($quantity < 1) {
      throw new \InvalidArgumentException('Remove quantity must be at least 1');
    }

    try {
      $this->database->startTransaction();

      // Get current item
      $item = $this->database->select('dc_campaign_item_instances', 'i')
        ->fields('i')
        ->condition('item_instance_id', $item_instance_id)
        ->condition('location_ref', $owner_id)
        ->condition('location_type', $this->ownerTypeToLocationType($owner_type))
        ->execute()
        ->fetchAssoc();

      if (!$item) {
        throw new \InvalidArgumentException("Item instance not found: {$item_instance_id}");
      }

      $current_qty = (int) $item['quantity'];

      if ($quantity > $current_qty) {
        throw new \InvalidArgumentException(
          "Cannot remove {$quantity} items; only {$current_qty} available"
        );
      }

      if ($quantity === $current_qty) {
        // Remove entirely
        $this->database->delete('dc_campaign_item_instances')
          ->condition('item_instance_id', $item_instance_id)
          ->execute();
      }
      else {
        // Partial removal
        $this->database->update('dc_campaign_item_instances')
          ->fields(['quantity' => $current_qty - $quantity])
          ->condition('item_instance_id', $item_instance_id)
          ->execute();
      }

      // Sync character state if character owner
      if ($owner_type === 'character') {
        $this->syncCharacterStateInventory($owner_id, $campaign_id);
      }

      $inventory = $this->getInventory($owner_id, $owner_type, $campaign_id);

      // Log operation
      $this->logInventoryOperation(
        'remove_item',
        $owner_id,
        $owner_type,
        $campaign_id,
        [
          'item_instance_id' => $item_instance_id,
          'quantity_removed' => $quantity,
          'quantity_remaining' => max(0, $current_qty - $quantity),
        ]
      );

      $this->database->commit();

      return [
        'success' => TRUE,
        'inventory' => $inventory,
        'message' => "Removed {$quantity} items",
      ];
    }
    catch (\Exception $e) {
      $this->database->rollBack();
      throw $e;
    }
  }

  /**
   * Transfer items between two inventories.
   *
   * @param string $source_owner_id
   *   Source character/container ID.
   * @param string $source_owner_type
   *   'character' or 'container'.
   * @param string $dest_owner_id
   *   Destination character/container ID.
   * @param string $dest_owner_type
   *   'character' or 'container'.
   * @param string $item_instance_id
   *   Item instance to transfer.
   * @param int $quantity
   *   Number of items to transfer.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Transfer result with both source and dest inventories.
   *
   * @throws \Exception
   *   On validation or transfer errors.
   */
  public function transferItems(
    string $source_owner_id,
    string $source_owner_type,
    string $dest_owner_id,
    string $dest_owner_type,
    string $item_instance_id,
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    $this->validateOwner($source_owner_id, $source_owner_type);
    $this->validateOwner($dest_owner_id, $dest_owner_type);
    $this->validateTransferPermission($source_owner_id, $source_owner_type);

    if ($quantity < 1) {
      throw new \InvalidArgumentException('Transfer quantity must be at least 1');
    }

    try {
      $this->database->startTransaction();

      // Get source item
      $source_item_row = $this->database->select('dc_campaign_item_instances', 'i')
        ->fields('i')
        ->condition('item_instance_id', $item_instance_id)
        ->condition('location_ref', $source_owner_id)
        ->execute()
        ->fetchAssoc();

      if (!$source_item_row) {
        throw new \InvalidArgumentException("Item not found in source inventory");
      }

      $source_qty = (int) $source_item_row['quantity'];
      if ($quantity > $source_qty) {
        throw new \InvalidArgumentException(
          "Cannot transfer {$quantity} items; only {$source_qty} available"
        );
      }

      // Get destination capacity
      $dest_capacity = $this->getInventoryCapacity($dest_owner_id, $dest_owner_type);
      $dest_current_bulk = $this->calculateCurrentBulk($dest_owner_id, $dest_owner_type);

      $item_bulk = $this->calculateItemBulk(
        json_decode($source_item_row['state_data'], TRUE) ?? [],
        $quantity
      );

      if ($dest_current_bulk + $item_bulk > $dest_capacity) {
        throw new \InvalidArgumentException(
          "Transfer would exceed destination capacity (current: {$dest_current_bulk}, capacity: {$dest_capacity}, item bulk: {$item_bulk})"
        );
      }

      // Create new instance for destination
      $new_item_instance_id = $this->createItemInstance(
        $dest_owner_id,
        $dest_owner_type,
        json_decode($source_item_row['state_data'], TRUE) ?? [
          'id' => $source_item_row['item_id'],
          'name' => 'Item',
        ],
        'carried',
        $quantity,
        $campaign_id
      );

      // Update source
      if ($quantity === $source_qty) {
        $this->database->delete('dc_campaign_item_instances')
          ->condition('item_instance_id', $item_instance_id)
          ->execute();
      }
      else {
        $this->database->update('dc_campaign_item_instances')
          ->fields(['quantity' => $source_qty - $quantity])
          ->condition('item_instance_id', $item_instance_id)
          ->execute();
      }

      // Sync character states if applicable
      if ($source_owner_type === 'character') {
        $this->syncCharacterStateInventory($source_owner_id, $campaign_id);
      }
      if ($dest_owner_type === 'character') {
        $this->syncCharacterStateInventory($dest_owner_id, $campaign_id);
      }

      // Get updated inventories
      $source_inventory = $this->getInventory($source_owner_id, $source_owner_type, $campaign_id);
      $dest_inventory = $this->getInventory($dest_owner_id, $dest_owner_type, $campaign_id);

      // Log operation
      $this->logInventoryOperation(
        'transfer_items',
        $source_owner_id,
        $source_owner_type,
        $campaign_id,
        [
          'from' => "{$source_owner_type}:{$source_owner_id}",
          'to' => "{$dest_owner_type}:{$dest_owner_id}",
          'item_instance_id' => $item_instance_id,
          'new_item_instance_id' => $new_item_instance_id,
          'quantity' => $quantity,
        ]
      );

      $this->database->commit();

      return [
        'success' => TRUE,
        'source_inventory' => $source_inventory,
        'dest_inventory' => $dest_inventory,
        'message' => "Transferred {$quantity} items from {$source_owner_type} to {$dest_owner_type}",
      ];
    }
    catch (\Exception $e) {
      $this->database->rollBack();
      throw $e;
    }
  }

  /**
   * Batch transfer multiple items at once.
   *
   * More efficient than multiple individual transfers.
   *
   * @param string $source_owner_id
   *   Source character/container ID.
   * @param string $source_owner_type
   *   'character' or 'container'.
   * @param string $dest_owner_id
   *   Destination character/container ID.
   * @param string $dest_owner_type
   *   'character' or 'container'.
   * @param array $items
   *   Array of items: [['item_instance_id' => '...', 'quantity' => 1], ...]
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Batch transfer results.
   *
   * @throws \Exception
   *   On validation or transfer errors.
   */
  public function batchTransferItems(
    string $source_owner_id,
    string $source_owner_type,
    string $dest_owner_id,
    string $dest_owner_type,
    array $items,
    ?int $campaign_id = NULL
  ): array {
    $this->validateOwner($source_owner_id, $source_owner_type);
    $this->validateOwner($dest_owner_id, $dest_owner_type);
    $this->validateTransferPermission($source_owner_id, $source_owner_type);

    if (empty($items)) {
      throw new \InvalidArgumentException('No items specified for batch transfer');
    }

    try {
      $this->database->startTransaction();

      $transferred = 0;
      $failed = [];

      foreach ($items as $item) {
        try {
          $this->transferItems(
            $source_owner_id,
            $source_owner_type,
            $dest_owner_id,
            $dest_owner_type,
            $item['item_instance_id'],
            $item['quantity'] ?? 1,
            $campaign_id
          );
          $transferred++;
        }
        catch (\Exception $e) {
          $failed[] = [
            'item_instance_id' => $item['item_instance_id'],
            'error' => $e->getMessage(),
          ];
        }
      }

      $this->database->commit();

      return [
        'success' => empty($failed),
        'transferred_count' => $transferred,
        'failed_count' => count($failed),
        'failed_items' => $failed,
        'message' => "Batch transfer: {$transferred} succeeded, " . count($failed) . " failed",
      ];
    }
    catch (\Exception $e) {
      $this->database->rollBack();
      throw $e;
    }
  }

  /**
   * Drop items into a room.
   *
   * @param string $character_id
   *   Character ID dropping items.
   * @param string $item_instance_id
   *   Item instance to drop.
   * @param string $room_id
   *   Room ID where items are dropped.
   * @param int $quantity
   *   Number to drop.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Operation result.
   *
   * @throws \Exception
   *   On errors.
   */
  public function dropItemInRoom(
    string $character_id,
    string $item_instance_id,
    string $room_id,
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    return $this->transferItems(
      $character_id,
      'character',
      $room_id,
      'room',
      $item_instance_id,
      $quantity,
      $campaign_id
    );
  }

  /**
   * Pick up items from a room.
   *
   * @param string $character_id
   *   Character ID picking up items.
   * @param string $item_instance_id
   *   Item instance to pick up.
   * @param string $room_id
   *   Room ID where items are located.
   * @param int $quantity
   *   Number to pick up.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Operation result.
   *
   * @throws \Exception
   *   On errors.
   */
  public function pickUpItemFromRoom(
    string $character_id,
    string $item_instance_id,
    string $room_id,
    int $quantity = 1,
    ?int $campaign_id = NULL
  ): array {
    return $this->transferItems(
      $room_id,
      'room',
      $character_id,
      'character',
      $item_instance_id,
      $quantity,
      $campaign_id
    );
  }

  /**
   * Change item location within same owner (e.g., equip/unequip).
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param string $item_instance_id
   *   Item instance ID.
   * @param string $new_location
   *   New location: 'carried', 'equipped', 'worn', etc.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Updated inventory.
   *
   * @throws \Exception
   *   On validation errors.
   */
  public function changeItemLocation(
    string $owner_id,
    string $owner_type,
    string $item_instance_id,
    string $new_location,
    ?int $campaign_id = NULL
  ): array {
    $this->validateOwner($owner_id, $owner_type);

    $valid_locations = ['carried', 'equipped', 'worn', 'stashed', 'dropped'];
    if (!in_array($new_location, $valid_locations)) {
      throw new \InvalidArgumentException("Invalid location: {$new_location}");
    }

    try {
      $this->database->startTransaction();

      // Update location in item instance
      $updated = $this->database->update('dc_campaign_item_instances')
        ->fields([
          'location_type' => $new_location,
          'updated' => time(),
        ])
        ->condition('item_instance_id', $item_instance_id)
        ->condition('location_ref', $owner_id)
        ->execute();

      if (!$updated) {
        throw new \InvalidArgumentException("Item instance not found or not in this inventory");
      }

      // Sync character state if character owner
      if ($owner_type === 'character') {
        $this->syncCharacterStateInventory($owner_id, $campaign_id);
      }

      $inventory = $this->getInventory($owner_id, $owner_type, $campaign_id);

      // Log operation
      $this->logInventoryOperation(
        'change_location',
        $owner_id,
        $owner_type,
        $campaign_id,
        [
          'item_instance_id' => $item_instance_id,
          'new_location' => $new_location,
        ]
      );

      $this->database->commit();

      return [
        'success' => TRUE,
        'inventory' => $inventory,
        'message' => "Item location changed to {$new_location}",
      ];
    }
    catch (\Exception $e) {
      $this->database->rollBack();
      throw $e;
    }
  }

  /**
   * Calculate total bulk for an inventory.
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return float
   *   Total bulk.
   */
  public function calculateCurrentBulk(
    string $owner_id,
    string $owner_type = 'character',
    ?int $campaign_id = NULL
  ): float {
    $items = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['state_data', 'quantity'])
      ->condition('location_ref', $owner_id)
      ->condition('location_type', $this->ownerTypeToLocationType($owner_type))
      ->execute()
      ->fetchAll();

    $total_bulk = 0.0;
    foreach ($items as $item) {
      $state = json_decode($item->state_data, TRUE) ?? [];
      $qty = (int) $item->quantity;
      $total_bulk += $this->calculateItemBulk($state, $qty);
    }

    return $total_bulk;
  }

  /**
   * Get encumbrance status based on bulk.
   *
   * @param float $current_bulk
   *   Current total bulk.
   * @param float $capacity
   *   Maximum capacity.
   *
   * @return string
   *   Encumbrance status: 'unencumbered', 'encumbered', 'overburdened'.
   */
  public function getEncumbranceStatus(float $current_bulk, float $capacity): string {
    if ($current_bulk > $capacity) {
      return 'overburdened';
    }
    if ($current_bulk > ($capacity * 0.75)) {
      return 'encumbered';
    }
    return 'unencumbered';
  }

  /**
   * Get inventory capacity for an owner.
   *
   * @param string $owner_id
   *   Character, container, or room ID.
   * @param string $owner_type
   *   'character', 'container', or 'room'.
   *
   * @return float
   *   Maximum bulk capacity (PHP_FLOAT_MAX for rooms).
   */
  public function getInventoryCapacity(
    string $owner_id,
    string $owner_type = 'character'
  ): float {
    if ($owner_type === 'character') {
      // Get character STR ability
      $state = $this->characterStateService->getState($owner_id);
      $str = $state['abilities']['strength'] ?? 10;
      $str_mod = floor(($str - 10) / 2);

      // PF2e base capacity = 5 + STR mod
      return 5 + $str_mod;
    }

    if ($owner_type === 'room') {
      // Rooms have unlimited capacity
      return PHP_FLOAT_MAX;
    }

    // For containers, get capacity from container_stats
    $container = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i', ['state_data'])
      ->condition('item_instance_id', $owner_id)
      ->execute()
      ->fetchObject();

    if (!$container) {
      return 10; // Default fallback
    }

    $state = json_decode($container->state_data, TRUE) ?? [];
    
    // Check for container_stats.capacity (standard location per item.schema.json)
    if (!empty($state['container_stats']['capacity'])) {
      return (float) $state['container_stats']['capacity'];
    }
    
    // Fallback to legacy capacity field for backwards compatibility
    if (!empty($state['capacity'])) {
      return (float) $state['capacity'];
    }
    
    // Default capacity if not specified
    return 10.0;
  }

  /**
   * Validate item data structure.
   *
   * @param array $item
   *   Item to validate.
   *
   * @throws \InvalidArgumentException
   *   If item is invalid.
   */
  protected function validateItemData(array $item): void {
    if (empty($item['id'])) {
      throw new \InvalidArgumentException('Item must have an id');
    }
    if (empty($item['name'])) {
      throw new \InvalidArgumentException('Item must have a name');
    }
  }

  /**
   * Validate owner exists.
   *
   * @param string $owner_id
   *   Character, container, or room ID.
   * @param string $owner_type
   *   'character', 'container', or 'room'.
   *
   * @throws \InvalidArgumentException
   *   If owner not found.
   */
  protected function validateOwner(string $owner_id, string $owner_type): void {
    if (!in_array($owner_type, ['character', 'container', 'room'], TRUE)) {
      throw new \InvalidArgumentException("Invalid owner type: {$owner_type}");
    }

    if ($owner_type === 'character') {
      try {
        $this->characterStateService->getState($owner_id);
      }
      catch (\Exception) {
        throw new \InvalidArgumentException("Character not found: {$owner_id}");
      }
    }
    elseif ($owner_type === 'container') {
      $exists = $this->database->select('dc_campaign_item_instances', 'i')
        ->fields('i', ['item_instance_id'])
        ->condition('item_instance_id', $owner_id)
        ->execute()
        ->fetchField();

      if (!$exists) {
        throw new \InvalidArgumentException("Container not found: {$owner_id}");
      }
    }
    elseif ($owner_type === 'room') {
      // Rooms have unlimited capacity, but verify they exist
      $exists = $this->database->select('dc_dungeon_rooms', 'r')
        ->fields('r', ['room_id'])
        ->condition('room_id', $owner_id)
        ->execute()
        ->fetchField();

      if (!$exists) {
        throw new \InvalidArgumentException("Room not found: {$owner_id}");
      }
    }
  }

  /**
   * Validate transfer permissions.
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   *
   * @throws \Exception
   *   If user lacks permission.
   */
  protected function validateTransferPermission(
    string $owner_id,
    string $owner_type
  ): void {
    if ($owner_type === 'character') {
      $uid = $this->currentUser->id();
      $owner_uid = $this->database->select('dc_campaign_characters', 'c')
        ->fields('c', ['uid'])
        ->condition('id', $owner_id)
        ->execute()
        ->fetchField();

      if ($owner_uid && $owner_uid != $uid) {
        throw new \Exception('You do not have permission to modify this character\'s inventory');
      }
    }
  }

  /**
   * Create item instance record.
   *
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param array $item
   *   Item data.
   * @param string $location
   *   Item location.
   * @param int $quantity
   *   Item quantity.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return string
   *   Item instance ID.
   */
  protected function createItemInstance(
    string $owner_id,
    string $owner_type,
    array $item,
    string $location,
    int $quantity,
    ?int $campaign_id
  ): string {
    $item_instance_id = uniqid('item_', TRUE);

    $this->database->insert('dc_campaign_item_instances')
      ->fields([
        'campaign_id' => $campaign_id ?? 0,
        'item_instance_id' => $item_instance_id,
        'item_id' => $item['id'] ?? '',
        'location_type' => $location,
        'location_ref' => $owner_id,
        'quantity' => $quantity,
        'state_data' => json_encode($item),
        'created' => time(),
        'updated' => time(),
      ])
      ->execute();

    return $item_instance_id;
  }

  /**
   * Calculate bulk for item(s).
   *
   * @param array $item_state
   *   Item state including bulk info.
   * @param int $quantity
   *   Quantity being calculated.
   *
   * @return float
   *   Total bulk for this item quantity.
   */
  protected function calculateItemBulk(array $item_state, int $quantity = 1): float {
    $bulk_value = $item_state['bulk'] ?? 'light';
    $bulk = self::BULK_MAP[$bulk_value] ?? 0;
    return ($bulk * $quantity);
  }

  /**
   * Check if an item is a container.
   *
   * @param array $item_state
   *   Item state data.
   *
   * @return bool
   *   TRUE if item has container_stats defined.
   */
  protected function isContainer(array $item_state): bool {
    return !empty($item_state['container_stats']) && 
           !empty($item_state['container_stats']['capacity']);
  }

  /**
   * Get container properties from item state.
   *
   * @param array $item_state
   *   Item state data.
   *
   * @return array
   *   Container properties or empty array if not a container.
   */
  protected function getContainerProperties(array $item_state): array {
    if (!$this->isContainer($item_state)) {
      return [];
    }

    $stats = $item_state['container_stats'];
    return [
      'capacity' => (float) $stats['capacity'],
      'capacity_reduction' => (float) ($stats['capacity_reduction'] ?? 1.0),
      'can_lock' => (bool) ($stats['can_lock'] ?? FALSE),
      'lock_dc' => (int) ($stats['lock_dc'] ?? 0),
      'is_locked' => (bool) ($stats['is_locked'] ?? FALSE),
      'access_time' => $stats['access_time'] ?? 'interact',
      'water_resistant' => (bool) ($stats['water_resistant'] ?? FALSE),
      'extradimensional' => (bool) ($stats['extradimensional'] ?? FALSE),
      'container_type' => $stats['container_type'] ?? 'backpack',
    ];
  }

  /**
   * Convert owner type to location type for database.
   *
   * @param string $owner_type
   *   'character', 'container', or 'room'.
   *
   * @return string
   *   Location type for database.
   */
  protected function ownerTypeToLocationType(string $owner_type): string {
    $map = [
      'character' => 'inventory',
      'container' => 'container',
      'room' => 'room',
    ];
    return $map[$owner_type] ?? 'inventory';
  }

  /**
   * Get items in a container.
   *
   * @param string $container_id
   *   Container ID.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return array
   *   Container inventory.
   */
  protected function getContainerInventory(string $container_id, ?int $campaign_id = NULL): array {
    $items = $this->database->select('dc_campaign_item_instances', 'i')
      ->fields('i')
      ->condition('location_ref', $container_id)
      ->condition('location_type', 'container')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return [
      'items' => array_map(function ($item) {
        return [
          'item_instance_id' => $item['item_instance_id'],
          'item_id' => $item['item_id'],
          'quantity' => (int) $item['quantity'],
          'state' => json_decode($item['state_data'], TRUE),
        ];
      }, $items),
      'totalBulk' => $this->calculateCurrentBulk($container_id, 'container'),
    ];
  }

  /**
   * Normalize inventory response format.
   *
   * @param array $inventory
   *   Raw inventory from character state.
   *
   * @return array
   *   Normalized response.
   */
  protected function normalizeInventoryResponse(array $inventory): array {
    return [
      'worn' => $inventory['worn'] ?? [],
      'carried' => $inventory['carried'] ?? [],
      'currency' => $inventory['currency'] ?? ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
      'totalBulk' => (float) ($inventory['totalBulk'] ?? 0),
      'encumbrance' => $inventory['encumbrance'] ?? 'unencumbered',
    ];
  }

  /**
   * Sync character state inventory from item instances table.
   *
   * This ensures the character state JSON matches the item instances table
   * (source of truth). Called after any inventory modification.
   *
   * @param string $character_id
   *   Character ID.
   * @param int $campaign_id
   *   Campaign ID.
   */
  protected function syncCharacterStateInventory(
    string $character_id,
    ?int $campaign_id = NULL
  ): void {
    try {
      // Get current inventory from instances
      $inventory = $this->getCharacterInventoryFromInstances($character_id, $campaign_id);

      // Get character state
      $state = $this->characterStateService->getState($character_id, $campaign_id);

      // Update inventory section
      $state['inventory'] = $inventory;

      // Save back to character state
      $this->characterStateService->setState(
        $character_id,
        $state,
        NULL, // Don't enforce version check for sync
        $campaign_id
      );
    }
    catch (\Exception $e) {
      // Log but don't fail - inventory instances are source of truth
      \Drupal::logger('dungeoncrawler_content')->warning(
        'Failed to sync character state inventory for @char_id: @message',
        [
          '@char_id' => $character_id,
          '@message' => $e->getMessage(),
        ]
      );
    }
  }

  /**
   * Log inventory operation for audit trail.
   *
   * @param string $operation
   *   Operation type.
   * @param string $owner_id
   *   Character or container ID.
   * @param string $owner_type
   *   'character' or 'container'.
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $context
   *   Operation context.
   */
  protected function logInventoryOperation(
    string $operation,
    string $owner_id,
    string $owner_type,
    ?int $campaign_id,
    array $context
  ): void {
    $this->database->insert('dc_campaign_log')
      ->fields([
        'campaign_id' => $campaign_id ?? 0,
        'log_type' => 'inventory',
        'message' => "{$owner_type}:{$owner_id} - {$operation}",
        'context' => json_encode([
          'operation' => $operation,
          'owner_id' => $owner_id,
          'owner_type' => $owner_type,
          'uid' => $this->currentUser->id(),
          'timestamp' => date('c'),
          ...($context ?? []),
        ]),
        'created' => time(),
      ])
      ->execute();
  }

}
