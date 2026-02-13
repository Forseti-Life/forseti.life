<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Combat API controller for HP, conditions, and state management.
 *
 * Handles HP changes, condition management, and combat state queries as defined in:
 * /docs/dungeoncrawler/issues/combat-api-endpoints.md
 * /docs/dungeoncrawler/issues/combat-engine-service.md (HPManager, ConditionManager)
 *
 * @see /docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md
 */
class CombatApiController extends ControllerBase {

  /**
   * The HP manager service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\HPManager
   */
  protected $hpManager;

  /**
   * The condition manager service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\ConditionManager
   */
  protected $conditionManager;

  /**
   * Constructor.
   */
  public function __construct($hp_manager, $condition_manager) {
    $this->hpManager = $hp_manager;
    $this->conditionManager = $condition_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.hp_manager'),
      $container->get('dungeoncrawler_content.condition_manager')
    );
  }

  /**
   * Update participant HP (damage or healing).
   *
   * PATCH /encounters/{encounter_id}/participants/{participant_id}/hp
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HP change data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated HP and conditions.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md (HPManager)
   * @see /docs/dungeoncrawler/issues/combat-api-endpoints.md#update-hp
   */
  public function updateHP($encounter_id, $participant_id, Request $request) {
    // TODO: Implement HP update
    // 1. Parse request: change_type (damage/healing), amount, damage_type, source
    // 2. If damage:
    //    a. Apply to temp HP first
    //    b. Apply resistances/weaknesses
    //    c. Reduce current_hp
    //    d. Check for death/dying:
    //       - HP = 0: unconscious
    //       - HP < 0: dying 1 (or wounded value)
    //       - HP <= -max_hp: instant death
    //    e. Log to combat_damage_log
    // 3. If healing:
    //    a. Increase current_hp (cap at max_hp)
    //    b. If was dying: remove dying, add wounded
    //    c. Log healing
    // 4. Return updated HP and new conditions
    
    return new JsonResponse([
      'participant_id' => $participant_id,
      'hp_before' => 0,
      'hp_after' => 0,
      'temp_hp_used' => 0,
      'conditions_applied' => [],
      'message' => '',
    ]);
  }

  /**
   * Apply temporary HP.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/temp-hp
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Temp HP amount and source.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated temp HP.
   */
  public function applyTempHP($encounter_id, $participant_id, Request $request) {
    // TODO: Implement temp HP application
    // 1. Get temp HP amount from request
    // 2. Temp HP doesn't stack (take higher value)
    // 3. Update temp_hp field
    // 4. Return new temp HP value
    
    return new JsonResponse([
      'participant_id' => $participant_id,
      'temp_hp_before' => 0,
      'temp_hp_after' => 0,
      'message' => 'Gained temporary HP',
    ]);
  }

  /**
   * Apply condition to participant.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/conditions
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Condition data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Applied condition with effects.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md (ConditionManager)
   * @see /docs/dungeoncrawler/issues/combat-database-schema.md (combat_conditions)
   */
  public function applyCondition($encounter_id, $participant_id, Request $request) {
    // TODO: Implement condition application
    // 1. Parse condition_type, value, duration_type, duration_remaining, source
    // 2. Check for immunities
    // 3. Check stacking rules (same type conditions)
    // 4. Insert into combat_conditions table
    // 5. Apply immediate stat effects:
    //    - Blinded: flat-footed, -4 Perception
    //    - Frightened X: -X to all checks and DCs
    //    - Flat-footed: -2 AC
    //    - Grabbed: can't move, flat-footed
    //    - Prone: -2 AC vs melee, +2 vs ranged
    //    - Slowed X: reduce actions by X
    //    - Stunned X: lose X actions
    //    - etc. (see combat-engine-service.md for full list)
    // 6. Log condition application
    // 7. Return condition ID and effects
    
    return new JsonResponse([
      'condition_id' => 0,
      'condition_type' => '',
      'value' => NULL,
      'applied_at_round' => 0,
      'effects' => [],
    ], 201);
  }

  /**
   * Remove condition from participant.
   *
   * DELETE /encounters/{encounter_id}/participants/{participant_id}/conditions/{condition_id}
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param int $condition_id
   *   The condition ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Removal confirmation.
   */
  public function removeCondition($encounter_id, $participant_id, $condition_id) {
    // TODO: Implement condition removal
    // 1. Load condition
    // 2. Remove condition effects from participant stats
    // 3. Mark removed_at timestamp
    // 4. Restore affected stats
    // 5. Log condition removal
    // 6. Return success
    
    return new JsonResponse([
      'condition_id' => $condition_id,
      'removed' => TRUE,
      'message' => 'Condition removed',
    ]);
  }

  /**
   * List active conditions for participant.
   *
   * GET /encounters/{encounter_id}/participants/{participant_id}/conditions
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   List of active conditions.
   */
  public function listConditions($encounter_id, $participant_id) {
    // TODO: Implement condition listing
    // 1. Query combat_conditions WHERE participant_id AND removed_at IS NULL
    // 2. Load condition details (type, value, duration, source)
    // 3. Calculate remaining duration
    // 4. Return condition list with effects
    
    return new JsonResponse([
      'participant_id' => $participant_id,
      'conditions' => [],
    ]);
  }

  /**
   * Get initiative order.
   *
   * GET /encounters/{encounter_id}/initiative
   *
   * @param int $encounter_id
   *   The encounter ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Sorted initiative order.
   *
   * @see /docs/dungeoncrawler/issues/combat-api-endpoints.md#get-initiative-order
   */
  public function getInitiative($encounter_id) {
    // TODO: Implement initiative order retrieval
    // 1. Query combat_participants
    // 2. Filter by is_active = TRUE
    // 3. Sort by initiative_total DESC, initiative_tiebreaker DESC
    // 4. Include HP, conditions, and current turn indicator
    // 5. Return sorted list
    
    return new JsonResponse([
      'encounter_id' => $encounter_id,
      'current_round' => 0,
      'initiative_order' => [],
    ]);
  }

  /**
   * Reroll initiative for participants.
   *
   * POST /encounters/{encounter_id}/initiative/reroll
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Participant IDs to reroll.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   New initiative values.
   */
  public function rerollInitiative($encounter_id, Request $request) {
    // TODO: Implement initiative reroll
    // 1. Get participant_ids[] from request
    // 2. For each participant:
    //    a. Roll d20 + perception modifier
    //    b. Generate new tiebreaker (0-99)
    //    c. Update initiative_total
    // 3. Re-sort initiative order
    // 4. Return new initiative values and order
    
    return new JsonResponse([
      'rerolled' => [],
      'new_initiative_order' => [],
    ]);
  }

  /**
   * Add participant to combat.
   *
   * POST /encounters/{encounter_id}/participants
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Participant data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   New participant info.
   */
  public function addParticipant($encounter_id, Request $request) {
    // TODO: Implement participant addition
    // 1. Parse type (character/monster), monster_id or character_id
    // 2. Load participant stats
    // 3. Roll initiative if roll_initiative = true
    // 4. Insert into combat_participants
    // 5. Insert into initiative order
    // 6. Return participant_id and initiative
    
    return new JsonResponse([
      'participant_id' => 0,
      'name' => '',
      'initiative' => 0,
      'added_at_round' => 0,
      'message' => 'Participant added',
    ], 201);
  }

  /**
   * Remove participant from combat.
   *
   * DELETE /encounters/{encounter_id}/participants/{participant_id}
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Removal reason.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Removal confirmation.
   */
  public function removeParticipant($encounter_id, $participant_id, Request $request) {
    // TODO: Implement participant removal
    // 1. Mark is_defeated = TRUE
    // 2. Set defeat_reason (fled, surrendered, removed)
    // 3. Remove from initiative order
    // 4. Log removal
    // 5. Return success
    
    return new JsonResponse([
      'participant_id' => $participant_id,
      'removed' => TRUE,
      'reason' => '',
      'message' => 'Participant removed',
    ]);
  }

  /**
   * Update participant stats.
   *
   * PATCH /encounters/{encounter_id}/participants/{participant_id}
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Updated fields.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Update confirmation.
   */
  public function updateParticipant($encounter_id, $participant_id, Request $request) {
    // TODO: Implement participant update
    // 1. Parse updated fields (position, max_hp, etc.)
    // 2. Validate GM permission
    // 3. Update combat_participants table
    // 4. Return success
    
    return new JsonResponse([
      'participant_id' => $participant_id,
      'updated_fields' => [],
      'message' => 'Participant updated',
    ]);
  }

  /**
   * Get combat log.
   *
   * GET /encounters/{encounter_id}/log
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Filter and pagination params.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Combat log entries.
   *
   * @see /docs/dungeoncrawler/issues/combat-database-schema.md (combat_actions)
   */
  public function getLog($encounter_id, Request $request) {
    // TODO: Implement combat log retrieval
    // 1. Get filters: round, participant_id, action_type
    // 2. Get pagination: page, per_page (default 50, max 200)
    // 3. Query combat_actions table with filters
    // 4. Include action details, results, timestamps
    // 5. Return paginated log entries
    
    return new JsonResponse([
      'encounter_id' => $encounter_id,
      'log_entries' => [],
      'meta' => [
        'page' => 1,
        'per_page' => 50,
        'total' => 0,
      ],
    ]);
  }

  /**
   * Get combat statistics.
   *
   * GET /encounters/{encounter_id}/statistics
   *
   * @param int $encounter_id
   *   The encounter ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Aggregate statistics.
   */
  public function getStatistics($encounter_id) {
    // TODO: Implement statistics calculation
    // 1. Count total actions by type
    // 2. Sum total damage by type
    // 3. Sum total healing
    // 4. Find top damage dealer
    // 5. Find top healer
    // 6. Calculate average damage per round
    // 7. Return statistics object
    
    return new JsonResponse([
      'encounter_id' => $encounter_id,
      'rounds_elapsed' => 0,
      'total_actions' => 0,
      'actions_by_type' => [],
      'damage_statistics' => [],
      'healing_statistics' => [],
    ]);
  }

}
