<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Combat action and turn management controller.
 *
 * Handles turn flow, action execution, and reactions as defined in:
 * /docs/dungeoncrawler/issues/combat-state-machine.md (Turn States)
 * /docs/dungeoncrawler/issues/combat-action-validation.md
 * /docs/dungeoncrawler/issues/combat-engine-service.md (ActionProcessor)
 *
 * @see /docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md
 */
class CombatActionController extends ControllerBase {

  /**
   * The action processor service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\ActionProcessor
   */
  protected $actionProcessor;

  /**
   * The combat engine service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CombatEngine
   */
  protected $combatEngine;

  /**
   * Constructor.
   */
  public function __construct($action_processor, $combat_engine) {
    $this->actionProcessor = $action_processor;
    $this->combatEngine = $combat_engine;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.action_processor'),
      $container->get('dungeoncrawler_content.combat_engine')
    );
  }

  /**
   * Get current turn information.
   *
   * GET /encounters/{encounter_id}/turn
   *
   * @param int $encounter_id
   *   The encounter ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Current turn participant info.
   *
   * @see /docs/dungeoncrawler/issues/combat-api-endpoints.md#get-current-turn
   */
  public function getCurrentTurn($encounter_id) {
    // TODO: Implement current turn retrieval
    // 1. Load encounter
    // 2. Get current_turn_participant_id
    // 3. Load participant with stats
    // 4. Get actions_remaining, reaction_available
    // 5. Get current_map_penalty
    // 6. Get active conditions
    // 7. Return participant state
    
    return new JsonResponse([
      'participant_id' => 0,
      'name' => '',
      'actions_remaining' => 3,
      'reaction_available' => TRUE,
      'current_map_penalty' => 0,
      'active_conditions' => [],
    ]);
  }

  /**
   * Start participant's turn.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/turn/start
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Turn state with granted actions.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#startturn
   * @see /docs/dungeoncrawler/issues/combat-state-machine.md (Turn States)
   */
  public function startTurn($encounter_id, $participant_id) {
    // TODO: Implement turn start
    // 1. Verify it's this participant's turn
    // 2. Grant 3 actions + 1 reaction
    // 3. Reset MAP to 0, attacks_this_turn to 0
    // 4. Process start-of-turn effects:
    //    - Roll recovery check if dying
    //    - Decrement frightened by 1
    //    - Check for stunned/slowed (reduces actions)
    //    - Check for quickened (grants extra action)
    // 5. Transition turn_state to 'awaiting_action'
    // 6. Log start-of-turn effects
    // 7. Return available actions and effects processed
    
    return new JsonResponse([
      'participant_id' => $participant_id,
      'turn_state' => 'awaiting_action',
      'actions_remaining' => 3,
      'reaction_available' => TRUE,
      'start_of_turn_effects' => [],
    ]);
  }

  /**
   * End participant's turn.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/turn/end
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   End effects and next turn info.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#endturn
   */
  public function endTurn($encounter_id, $participant_id) {
    // TODO: Implement turn end
    // 1. Apply persistent damage (with flat check DC 15)
    // 2. Remove "until end of turn" effects
    // 3. Decrement turn-based condition durations
    // 4. Process end-of-turn abilities (regeneration, etc.)
    // 5. Log end-of-turn effects
    // 6. Advance to next participant in initiative order
    // 7. If last participant: endRound()
    // 8. Return next turn participant info
    
    return new JsonResponse([
      'participant_id' => $participant_id,
      'turn_ended' => TRUE,
      'end_of_turn_effects' => [],
      'next_turn' => [],
    ]);
  }

  /**
   * Delay turn to act later.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/delay
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Delay confirmation.
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md (Delay Rules)
   */
  public function delay($encounter_id, $participant_id) {
    // TODO: Implement delay
    // 1. Mark participant as delaying
    // 2. Store original initiative
    // 3. Remove from current turn order
    // 4. Participant can rejoin at any later initiative
    // 5. Return success
    
    return new JsonResponse([
      'participant_id' => $participant_id,
      'is_delaying' => TRUE,
      'original_initiative' => 0,
      'message' => 'Participant delayed',
    ]);
  }

  /**
   * Resume from delay at new initiative.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/resume-delay
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   New initiative value.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated initiative order.
   */
  public function resumeDelay($encounter_id, $participant_id, Request $request) {
    // TODO: Implement resume from delay
    // 1. Get new_initiative from request
    // 2. Validate new_initiative < original_initiative
    // 3. Reinsert participant at new initiative
    // 4. New initiative becomes permanent
    // 5. Return updated initiative order
    
    return new JsonResponse([
      'participant_id' => $participant_id,
      'new_initiative' => 0,
      'is_delaying' => FALSE,
    ]);
  }

  /**
   * Execute combat action.
   *
   * POST /encounters/{encounter_id}/actions
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Action data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Action result.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executeaction
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md
   */
  public function executeAction($encounter_id, Request $request) {
    // TODO: Implement action execution
    // 1. Parse request: participant_id, action_type, target_id, action_data
    // 2. Validate action (6-layer validation):
    //    a. State validation (combat active, participant's turn)
    //    b. Action economy (enough actions remaining)
    //    c. Condition restrictions (not paralyzed/unconscious)
    //    d. Prerequisites (weapon equipped, spell slots, etc.)
    //    e. Resource validation (spell slots, abilities)
    //    f. Target validation (in range, line of sight)
    // 3. If validation fails: return error
    // 4. Execute action via ActionProcessor
    // 5. Deduct action cost from actions_remaining
    // 6. Update MAP if attack action
    // 7. Apply action effects to targets
    // 8. Log action to combat_actions table
    // 9. Check for triggered reactions
    // 10. Return action result and updated state
    
    return new JsonResponse([
      'action_id' => 0,
      'action_type' => '',
      'success' => TRUE,
      'result' => [],
      'reactions_triggered' => [],
      'participant_state' => [],
    ]);
  }

  /**
   * Execute Strike action.
   *
   * POST /encounters/{encounter_id}/actions/strike
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Strike data (participant_id, target_id, weapon_id).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Attack and damage results.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executestrike
   * @see /docs/dungeoncrawler/issues/combat-database-schema.md (combat_actions)
   */
  public function strike($encounter_id, Request $request) {
    // TODO: Implement Strike action
    // 1. Load attacker and target participants
    // 2. Load weapon stats
    // 3. Calculate attack bonus:
    //    = weapon_proficiency + ability_mod + item_bonus - MAP + bonuses - penalties
    // 4. Roll d20 + attack bonus
    // 5. Compare to target AC
    // 6. Determine degree of success (critical/success/failure/critical failure)
    // 7. If hit or critical hit:
    //    a. Roll damage: weapon_damage_dice + ability_mod
    //    b. If critical: double damage dice (not modifiers)
    //    c. Apply resistances/weaknesses
    //    d. Apply damage to target
    //    e. Check for dying/death conditions
    // 8. Increment attacks_this_turn
    // 9. Update MAP: -5 (or -4 if agile weapon)
    // 10. Log strike to combat_actions and combat_damage_log
    // 11. Return attack result
    
    return new JsonResponse([
      'action_id' => 0,
      'action_type' => 'strike',
      'success' => TRUE,
      'result' => [
        'attack_roll' => 0,
        'attack_total' => 0,
        'target_ac' => 0,
        'degree' => 'success',
        'damage_dealt' => 0,
        'target_hp_before' => 0,
        'target_hp_after' => 0,
      ],
      'participant_state' => [
        'actions_remaining' => 2,
        'current_map_penalty' => -5,
        'attacks_this_turn' => 1,
      ],
    ]);
  }

  /**
   * Execute Stride (movement) action.
   *
   * POST /encounters/{encounter_id}/actions/stride
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Movement data (participant_id, distance, path).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Movement result.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executestride
   */
  public function stride($encounter_id, Request $request) {
    // TODO: Implement Stride action
    // 1. Validate not immobilized/grabbed/paralyzed
    // 2. Calculate movement cost (difficult terrain doubles cost)
    // 3. Validate distance <= speed
    // 4. Check for Attack of Opportunity triggers
    // 5. Update participant position
    // 6. Deduct 1 action
    // 7. Log movement
    // 8. Return new position
    
    return new JsonResponse([
      'action_id' => 0,
      'action_type' => 'stride',
      'success' => TRUE,
      'distance_moved' => 0,
      'new_position' => ['x' => 0, 'y' => 0],
      'reactions_triggered' => [],
    ]);
  }

  /**
   * Execute Cast Spell action.
   *
   * POST /encounters/{encounter_id}/actions/cast-spell
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Spell data (participant_id, spell_id, spell_level, targets[]).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Spell results.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executecastspell
   */
  public function castSpell($encounter_id, Request $request) {
    // TODO: Implement Cast Spell action
    // 1. Validate spell slot available
    // 2. Validate not silenced (if verbal component)
    // 3. Validate hand free (if somatic component)
    // 4. Deduct spell slot
    // 5. Execute spell effects by type:
    //    - Attack roll spell: roll attack vs AC
    //    - Save spell: targets roll save vs spell DC
    //    - Automatic: apply effects directly
    // 6. Apply conditions/damage/buffs to targets
    // 7. Deduct actions (usually 2 for most spells)
    // 8. Log spell cast
    // 9. Return spell results per target
    
    return new JsonResponse([
      'action_id' => 0,
      'action_type' => 'cast_spell',
      'success' => TRUE,
      'spell_name' => '',
      'spell_slot_used' => TRUE,
      'results' => [],
      'participant_state' => [],
    ]);
  }

  /**
   * Ready an action with trigger.
   *
   * POST /encounters/{encounter_id}/actions/ready
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Readied action and trigger.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Ready confirmation.
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md (Ready Rules)
   */
  public function ready($encounter_id, Request $request) {
    // TODO: Implement Ready action
    // 1. Cost: 2 actions
    // 2. Store readied action (must be 1-action only)
    // 3. Store trigger condition
    // 4. Mark participant as having readied action
    // 5. When trigger occurs: execute as reaction
    // 6. Return success
    
    return new JsonResponse([
      'action_id' => 0,
      'action_type' => 'ready',
      'success' => TRUE,
      'readied_action' => [],
      'participant_state' => [
        'actions_remaining' => 1,
        'is_readying' => TRUE,
      ],
    ]);
  }

  /**
   * Execute reaction.
   *
   * POST /encounters/{encounter_id}/reactions
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Reaction data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Reaction result.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md (ReactionHandler)
   */
  public function executeReaction($encounter_id, Request $request) {
    // TODO: Implement reaction execution
    // 1. Validate reaction available
    // 2. Validate trigger conditions met
    // 3. Execute reaction by type:
    //    - Attack of Opportunity: Strike with no MAP
    //    - Shield Block: Reduce damage by hardness
    //    - Nimble Dodge: +2 AC against attack
    //    - Aid: Grant +1 bonus to ally
    //    - Readied Action: Execute prepared action
    // 4. Mark reaction as used
    // 5. Log reaction to combat_reactions table
    // 6. Return result (may modify triggering action)
    
    return new JsonResponse([
      'reaction_id' => 0,
      'reaction_type' => '',
      'success' => TRUE,
      'result' => [],
      'participant_state' => [
        'reaction_available' => FALSE,
      ],
    ]);
  }

}
