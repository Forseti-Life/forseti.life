<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Reaction Handler service - Handle reaction triggers and execution.
 *
 * @see /docs/dungeoncrawler/issues/combat-engine-service.md (ReactionHandler)
 */
class ReactionHandler {

  protected $database;
  protected $actionProcessor;

  public function __construct(Connection $database, ActionProcessor $action_processor) {
    $this->database = $database;
    $this->actionProcessor = $action_processor;
  }

  /**
   * Check for reactions.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#checkforreactions
   */
  public function checkForReactions($encounter_id, $action, $actor) {
    // TODO: Get participants with reactions, check triggers
    return [];
  }

  /**
   * Execute reaction.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executereaction
   */
  public function executeReaction($participant_id, $reaction_type, $trigger_action, $encounter_id) {
    // TODO: Execute reaction logic, mark as used, log
    return [];
  }

  /**
   * Process Attack of Opportunity.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#processattackofopportunity
   */
  public function processAttackOfOpportunity($participant_id, $triggering_action, $target_id, $encounter_id) {
    // TODO: Strike with no MAP
    return [];
  }

  /**
   * Process Shield Block.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#processshieldblock
   */
  public function processShieldBlock($participant_id, $incoming_damage, $damage_type, $encounter_id) {
    // TODO: Reduce damage by hardness, check shield break
    return ['damage_blocked' => 0, 'shield_broke' => FALSE];
  }

}
