<?php

namespace Drupal\dungeoncrawler_content\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access for character operations based on ownership.
 */
class CharacterAccessCheck implements AccessInterface {

  /**
   * Checks access to character based on ownership and permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param int $character_id
   *   The character ID from the route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, $character_id = NULL) {
    // Admin can access any character.
    if ($account->hasPermission('administer dungeoncrawler content')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Character ID is required.
    if (!$character_id) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    // Load character and check ownership.
    $database = \Drupal::database();
    $query = $database->select('dungeoncrawler_characters', 'c')
      ->fields('c', ['user_id'])
      ->condition('c.id', $character_id)
      ->execute();
    
    $character = $query->fetchAssoc();
    
    if (!$character) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    // Check if user owns the character.
    if ($character['user_id'] == $account->id()) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheTags(['dungeoncrawler_character:' . $character_id]);
    }

    return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
  }

}
