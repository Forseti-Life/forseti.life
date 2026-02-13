/**
 * @file TurnManagementSystem.js
 * System for managing turn order, initiative, and round progression.
 */

import { System } from '../System.js';

/**
 * Combat state.
 */
export const CombatState = {
  INACTIVE: 'inactive',       // No combat
  ROLLING_INITIATIVE: 'rolling_initiative', // Rolling initiative
  IN_PROGRESS: 'in_progress', // Combat active
  ENDED: 'ended'              // Combat ended
};

/**
 * TurnManagementSystem
 * 
 * Manages turn-based combat including:
 * - Initiative rolling and ordering
 * - Turn progression
 * - Round tracking
 * - Action economy integration
 */
export class TurnManagementSystem extends System {
  constructor(entityManager) {
    super(entityManager);
    this.priority = 10; // Run early (before movement, combat)
    
    // Combat state
    this.combatState = CombatState.INACTIVE;
    this.initiativeOrder = []; // Sorted array of entity IDs
    this.currentTurnIndex = -1;
    this.currentRound = 0;
    
    // Callbacks for UI updates
    this.onTurnChangeCallback = null;
    this.onRoundChangeCallback = null;
    this.onCombatStateChangeCallback = null;
  }
  
  /**
   * Initialize system.
   */
  init() {
    console.log('TurnManagementSystem initialized');
  }
  
  /**
   * Update system.
   * @param {number} deltaTime - Time elapsed since last update (ms)
   */
  update(deltaTime) {
    // Turn management is primarily event-driven
    // Could add time-based effects here (e.g., counters, durations)
  }
  
  /**
   * Start combat encounter.
   */
  startCombat() {
    if (this.combatState !== CombatState.INACTIVE) {
      console.warn('Combat already in progress');
      return;
    }
    
    console.log('Starting combat...');
    this.combatState = CombatState.ROLLING_INITIATIVE;
    this.currentRound = 0;
    this.initiativeOrder = [];
    this.currentTurnIndex = -1;
    
    // Mark all combatants as in combat
    const combatants = this.entityManager.getEntitiesWith('CombatComponent');
    for (const entity of combatants) {
      const combat = entity.getComponent('CombatComponent');
      combat.enterCombat();
    }
    
    // Roll initiative for all combatants
    this.rollInitiative();
    
    // Start first round
    this.startRound();
    
    if (this.onCombatStateChangeCallback) {
      this.onCombatStateChangeCallback(this.combatState);
    }
  }
  
  /**
   * Roll initiative for all combatants.
   */
  rollInitiative() {
    const combatants = this.entityManager.getEntitiesWith('CombatComponent', 'StatsComponent');
    
    const initiatives = [];
    
    for (const entity of combatants) {
      const combat = entity.getComponent('CombatComponent');
      const stats = entity.getComponent('StatsComponent');
      
      // Roll initiative (d20 + perception)
      const result = combat.rollInitiative(stats.perception);
      
      initiatives.push({
        entityId: entity.id,
        initiative: result,
        roll: combat.initiativeRoll
      });
      
      console.log(`Entity ${entity.id} rolled initiative: ${combat.initiativeRoll} + ${stats.perception} = ${result}`);
    }
    
    // Sort by initiative (highest first), with roll as tiebreaker
    initiatives.sort((a, b) => {
      if (b.initiative !== a.initiative) {
        return b.initiative - a.initiative;
      }
      // Tiebreaker: higher roll goes first
      return b.roll - a.roll;
    });
    
    // Set turn order
    this.initiativeOrder = initiatives.map((init, index) => {
      const entity = this.entityManager.getEntity(init.entityId);
      const combat = entity.getComponent('CombatComponent');
      combat.turnOrder = index;
      return init.entityId;
    });
    
    console.log('Initiative order:', this.initiativeOrder);
  }
  
  /**
   * Start a new round.
   */
  startRound() {
    this.currentRound++;
    this.currentTurnIndex = -1;
    
    console.log(`=== Round ${this.currentRound} ===`);
    
    // Reset turn tracking for all combatants
    const combatants = this.entityManager.getEntitiesWith('CombatComponent');
    for (const entity of combatants) {
      const combat = entity.getComponent('CombatComponent');
      combat.resetTurnTracking();
    }
    
    // Start first turn
    this.nextTurn();
    
    if (this.onRoundChangeCallback) {
      this.onRoundChangeCallback(this.currentRound);
    }
  }
  
  /**
   * Advance to next turn.
   */
  nextTurn() {
    // End current turn if there is one
    if (this.currentTurnIndex >= 0) {
      this.endCurrentTurn();
    }
    
    // Find next entity that can take a turn
    let nextIndex = this.currentTurnIndex + 1;
    let foundNextTurn = false;
    
    while (nextIndex < this.initiativeOrder.length && !foundNextTurn) {
      const entityId = this.initiativeOrder[nextIndex];
      const entity = this.entityManager.getEntity(entityId);
      
      if (!entity) {
        // Entity was removed, skip
        nextIndex++;
        continue;
      }
      
      const combat = entity.getComponent('CombatComponent');
      const stats = entity.getComponent('StatsComponent');
      
      // Skip defeated entities
      if (combat.isDefeated || (stats && !stats.isAlive())) {
        nextIndex++;
        continue;
      }
      
      // Found valid entity
      this.currentTurnIndex = nextIndex;
      foundNextTurn = true;
    }
    
    if (!foundNextTurn) {
      // No more turns this round, start next round
      this.startRound();
      return;
    }
    
    // Start turn for current entity
    this.startCurrentTurn();
  }
  
  /**
   * Start turn for current entity.
   */
  startCurrentTurn() {
    const entityId = this.initiativeOrder[this.currentTurnIndex];
    const entity = this.entityManager.getEntity(entityId);
    
    if (!entity) {
      this.nextTurn();
      return;
    }
    
    const combat = entity.getComponent('CombatComponent');
    const actions = entity.getComponent('ActionsComponent');
    const movement = entity.getComponent('MovementComponent');
    const identity = entity.getComponent('IdentityComponent');
    
    // Mark turn start
    combat.startTurn();
    
    // Restore actions
    if (actions) {
      actions.startTurn();
    }
    
    // Restore movement
    if (movement) {
      movement.restoreMovement();
    }
    
    const name = identity ? identity.name : `Entity ${entity.id}`;
    console.log(`>>> ${name}'s turn (${this.currentTurnIndex + 1}/${this.initiativeOrder.length})`);
    
    this.combatState = CombatState.IN_PROGRESS;
    
    if (this.onTurnChangeCallback) {
      this.onTurnChangeCallback(entity, this.currentTurnIndex, this.initiativeOrder.length);
    }
  }
  
  /**
   * End current entity's turn.
   */
  endCurrentTurn() {
    if (this.currentTurnIndex < 0) {
      return;
    }
    
    const entityId = this.initiativeOrder[this.currentTurnIndex];
    const entity = this.entityManager.getEntity(entityId);
    
    if (!entity) {
      return;
    }
    
    const combat = entity.getComponent('CombatComponent');
    const actions = entity.getComponent('ActionsComponent');
    
    // Mark turn end
    combat.endTurn();
    
    if (actions) {
      actions.endTurn();
    }
    
    const identity = entity.getComponent('IdentityComponent');
    const name = identity ? identity.name : `Entity ${entity.id}`;
    console.log(`<<< ${name}'s turn ended`);
  }
  
  /**
   * End current turn and advance (player/UI triggered).
   */
  endTurn() {
    if (this.combatState !== CombatState.IN_PROGRESS) {
      console.warn('No active turn to end');
      return;
    }
    
    this.nextTurn();
  }
  
  /**
   * End combat.
   */
  endCombat() {
    console.log('Combat ended');
    
    this.combatState = CombatState.ENDED;
    
    // Exit combat for all combatants
    const combatants = this.entityManager.getEntitiesWith('CombatComponent');
    for (const entity of combatants) {
      const combat = entity.getComponent('CombatComponent');
      combat.exitCombat();
    }
    
    // Reset state
    this.initiativeOrder = [];
    this.currentTurnIndex = -1;
    this.currentRound = 0;
    this.combatState = CombatState.INACTIVE;
    
    if (this.onCombatStateChangeCallback) {
      this.onCombatStateChangeCallback(this.combatState);
    }
  }
  
  /**
   * Get current turn entity.
   * @returns {Entity|null}
   */
  getCurrentTurnEntity() {
    if (this.currentTurnIndex < 0 || this.currentTurnIndex >= this.initiativeOrder.length) {
      return null;
    }
    
    const entityId = this.initiativeOrder[this.currentTurnIndex];
    return this.entityManager.getEntity(entityId);
  }
  
  /**
   * Check if it's a specific entity's turn.
   * @param {Entity} entity - Entity to check
   * @returns {boolean}
   */
  isEntityTurn(entity) {
    const currentEntity = this.getCurrentTurnEntity();
    return currentEntity && currentEntity.id === entity.id;
  }
  
  /**
   * Get initiative order with entity data.
   * @returns {Array} - Array of {entity, initiative, isCurrent}
   */
  getInitiativeOrder() {
    return this.initiativeOrder.map((entityId, index) => {
      const entity = this.entityManager.getEntity(entityId);
      const combat = entity ? entity.getComponent('CombatComponent') : null;
      const identity = entity ? entity.getComponent('IdentityComponent') : null;
      
      return {
        entity: entity,
        entityId: entityId,
        name: identity ? identity.name : `Entity ${entityId}`,
        initiative: combat ? combat.getInitiative() : 0,
        isCurrent: index === this.currentTurnIndex,
        isDefeated: combat ? combat.isDefeated : false
      };
    });
  }
  
  /**
   * Check if combat is active.
   * @returns {boolean}
   */
  isCombatActive() {
    return this.combatState === CombatState.IN_PROGRESS;
  }
  
  /**
   * Get current round number.
   * @returns {number}
   */
  getCurrentRound() {
    return this.currentRound;
  }
  
  /**
   * Register callback for turn changes.
   * @param {Function} callback - Callback function(entity, turnIndex, totalTurns)
   */
  onTurnChange(callback) {
    this.onTurnChangeCallback = callback;
  }
  
  /**
   * Register callback for round changes.
   * @param {Function} callback - Callback function(roundNumber)
   */
  onRoundChange(callback) {
    this.onRoundChangeCallback = callback;
  }
  
  /**
   * Register callback for combat state changes.
   * @param {Function} callback - Callback function(combatState)
   */
  onCombatStateChange(callback) {
    this.onCombatStateChangeCallback = callback;
  }
  
  /**
   * Cleanup system.
   */
  destroy() {
    this.endCombat();
    console.log('TurnManagementSystem destroyed');
  }
}
