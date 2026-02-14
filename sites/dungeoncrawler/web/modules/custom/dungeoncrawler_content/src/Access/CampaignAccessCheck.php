<?php

namespace Drupal\dungeoncrawler_content\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Connection;

/**
 * Checks access for campaign operations based on ownership.
 */
class CampaignAccessCheck implements AccessInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs a CampaignAccessCheck object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Checks access to campaign based on ownership and permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param int $campaign_id
   *   The campaign ID from the route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, $campaign_id = NULL) {
    // Admin can access any campaign.
    if ($account->hasPermission('administer dungeoncrawler content')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Campaign ID is required.
    if (!$campaign_id) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    // Load campaign and check ownership.
    $query = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['uid'])
      ->condition('c.id', $campaign_id)
      ->execute();
    
    $campaign = $query->fetchAssoc();
    
    if (!$campaign) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    // Check if user owns the campaign.
    if ((int) $campaign['uid'] === (int) $account->id()) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheTags(['dc_campaign:' . $campaign_id]);
    }

    return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
  }

}
