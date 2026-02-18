/**
 * @file CombatComponent.js
 * Component for combat-related data (initiative, team, etc.).
 * 
 * Database Mapping:
 * - Runtime state stored in dc_campaign_characters.character_data JSON
 * - Active combat data stored in combat_participants table during encounters
 * - Initiative fields map to: initiative (final result), initiative_roll (d20 roll)
 * - Team enum values match combat_participants.team column constraints
 */

import { Component } from '../Component.js';

/**
 * Team affiliations.
 * @readonly
 * @enum {string}
 */
export const Team = {
  PLAYER: 'player',
  ENEMY: 'enemy',
  NEUTRAL: 'neutral',
  ALLY: 'ally'
};

/**
 * Constants for combat calculations.
 * @readonly
 */
const CombatConstants = {
  /** Standard d20 die size for initiative and attack rolls */
  D20: 20,
  /** Minimum roll value on a d20 */
  MIN_D20_ROLL: 1,
  /** Maximum roll value on a d20 */
  MAX_D20_ROLL: 20
};

/**
 * CombatComponent
 * 
 * Stores combat-related data including initiative, team affiliation,
 * and combat state. Follows ECS pattern - data container only, no complex logic.
 * 
 * @extends Component
 */
export class CombatComponent extends Component {
  /**
   * Create a new CombatComponent.
   * 
   * @param {Object} config - Configuration object
   * @param {number} [config.initiativeBonus=0] - Bonus to initiative rolls
   * @param {string} [config.team='neutral'] - Team affiliation (use Team enum)
   * @param {number} [config.weaponProficiency=0] - Weapon proficiency bonus
   * @param {number} [config.attackBonus=0] - Base attack bonus (STR/DEX mod added in CombatSystem)
   * @param {number} [config.armorProficiency=0] - Armor proficiency bonus for AC
   */
  constructor(config = {}) {
    super();
    
    // Validate team if provided
    if (config.team && !Object.values(Team).includes(config.team)) {
      console.warn(`Invalid team value: ${config.team}, defaulting to ${Team.NEUTRAL}`);
      config.team = Team.NEUTRAL;
    }
    
    // Initiative
    this.initiativeBonus = config.initiativeBonus || 0;
    this.initiativeRoll = null; // Rolled value (d20)
    this.initiativeResult = null; // Final initiative score (roll + bonuses)
    
    // Team affiliation
    this.team = config.team || Team.NEUTRAL;
    
    // Combat state
    this.inCombat = false;
    this.isDefeated = false;
    
    // Turn tracking
    this.hasTakenTurn = false;
    this.turnOrder = null; // Position in initiative order (set by TurnManagementSystem)
    
    // Weapon proficiencies (for attack rolls)
    this.weaponProficiency = config.weaponProficiency || 0;
    
    // Attack bonus (includes STR/DEX mod in combat system)
    this.attackBonus = config.attackBonus || 0;
    
    // Armor proficiency (for AC calculation)
    this.armorProficiency = config.armorProficiency || 0;
  }
  
  /**
   * Roll initiative using d20 + perception + initiative bonus.
   * 
   * @param {number} [perceptionBonus=0] - Perception bonus to add to the roll
   * @returns {number} The final initiative result
   */
  rollInitiative(perceptionBonus = 0) {
    // Roll d20
    this.initiativeRoll = Math.floor(Math.random() * CombatConstants.D20) + CombatConstants.MIN_D20_ROLL;
    
    // Calculate result: d20 + perception + initiativeBonus
    this.initiativeResult = this.initiativeRoll + perceptionBonus + this.initiativeBonus;
    
    return this.initiativeResult;
  }
  
  /**
   * Set initiative result manually (for server-authoritative ordering or tie resolution).
   * 
   * @param {number} result - Initiative result to set
   * @throws {TypeError} If result is not a number
   */
  setInitiative(result) {
    if (typeof result !== 'number' || isNaN(result)) {
      throw new TypeError(`Initiative result must be a number, got: ${result}`);
    }
    this.initiativeResult = result;
  }
  
  /**
   * Get the current initiative result.
   * 
   * @returns {number|null} The initiative result, or null if not yet rolled
   */
  getInitiative() {
    return this.initiativeResult;
  }
  
  /**
   * Check if initiative has been rolled for this entity.
   * 
   * @returns {boolean} True if initiative has been set
   */
  hasInitiative() {
    return this.initiativeResult !== null;
  }
  
  /**
   * Reset initiative data (for starting a new combat encounter).
   * Clears roll, result, turn tracking, and turn order.
   */
  resetInitiative() {
    this.initiativeRoll = null;
    this.initiativeResult = null;
    this.hasTakenTurn = false;
    this.turnOrder = null;
  }
  
  /**
   * Enter combat state. Marks entity as in combat and resets combat flags.
   */
  enterCombat() {
    this.inCombat = true;
    this.isDefeated = false;
    this.hasTakenTurn = false;
  }
  
  /**
   * Exit combat state. Marks entity as not in combat and resets initiative.
   */
  exitCombat() {
    this.inCombat = false;
    this.resetInitiative();
  }
  
  /**
   * Mark entity as defeated in combat.
   */
  defeat() {
    this.isDefeated = true;
  }
  
  /**
   * Check if entity is on the player team.
   * 
   * @returns {boolean} True if entity is on Team.PLAYER
   */
  isPlayerTeam() {
    return this.team === Team.PLAYER;
  }
  
  /**
   * Check if entity is hostile (enemy team).
   * 
   * @returns {boolean} True if entity is on Team.ENEMY
   */
  isHostile() {
    return this.team === Team.ENEMY;
  }
  
  /**
   * Check if two entities are on the same team.
   * 
   * @param {CombatComponent} other - Other entity's combat component
   * @returns {boolean} True if both entities are on the same team
   */
  isSameTeam(other) {
    return this.team === other.team;
  }
  
  /**
   * Check if this entity is hostile to another entity.
   * Implements team-based hostility rules:
   * - Neutral entities are never hostile
   * - Player team is hostile to Enemy team only
   * - Enemy team is hostile to Player and Ally teams
   * - Ally team follows Player hostility rules
   * 
   * @param {CombatComponent} other - Other entity's combat component
   * @returns {boolean} True if this entity is hostile to the other
   */
  isHostileTo(other) {
    // Neutral entities are never hostile
    if (this.team === Team.NEUTRAL || other.team === Team.NEUTRAL) {
      return false;
    }
    
    // Player/Ally teams are hostile to Enemy team
    if (this.team === Team.PLAYER || this.team === Team.ALLY) {
      return other.team === Team.ENEMY;
    }
    
    // Enemy team is hostile to Player and Ally teams
    if (this.team === Team.ENEMY) {
      return other.team === Team.PLAYER || other.team === Team.ALLY;
    }
    
    return false;
  }
  
  /**
   * Mark turn as started (called by TurnManagementSystem).
   */
  startTurn() {
    this.hasTakenTurn = true;
  }
  
  /**
   * Mark turn as complete (placeholder for future turn-end effects).
   * Turn tracking is handled by TurnManagementSystem.
   */
  endTurn() {
    // Turn tracking handled by TurnManagementSystem
  }
  
  /**
   * Check if entity has taken their turn this round.
   * 
   * @returns {boolean} True if turn has been taken
   */
  hasTurnCompleted() {
    return this.hasTakenTurn;
  }
  
  /**
   * Reset turn tracking for a new round (called by TurnManagementSystem).
   */
  resetTurnTracking() {
    this.hasTakenTurn = false;
  }
  
  /**
   * Serialize component to JSON for persistence.
   * 
   * Maps to database storage:
   * - character_data/state_data JSON in dc_campaign_characters (ECS runtime state)
   * - combat_participants table fields during active encounters:
   *   - initiativeResult → initiative (final value)
   *   - initiativeRoll → initiative_roll (raw d20)
   *   - team → team (varchar 32)
   *   - isDefeated → is_defeated (tiny int boolean)
   * 
   * @returns {Object} Serialized component data conforming to unified schema
   */
  toJSON() {
    return {
      type: this.constructor.name,
      initiativeBonus: this.initiativeBonus,
      initiativeRoll: this.initiativeRoll,
      initiativeResult: this.initiativeResult,
      team: this.team,
      inCombat: this.inCombat,
      isDefeated: this.isDefeated,
      hasTakenTurn: this.hasTakenTurn,
      turnOrder: this.turnOrder,
      weaponProficiency: this.weaponProficiency,
      attackBonus: this.attackBonus,
      armorProficiency: this.armorProficiency
    };
  }
  
  /**
   * Convert to combat_participants table format.
   * Maps component fields to database column names for active combat encounters.
   * 
   * @param {number} encounterId - Encounter ID for the combat_participants record
   * @param {number} entityId - Entity ID from ECS
   * @param {string} entityRef - External reference (character/content id)
   * @param {string} name - Display name
   * @returns {Object} Data structure matching combat_participants table schema
   */
  toCombatParticipant(encounterId, entityId, entityRef, name) {
    return {
      encounter_id: encounterId,
      entity_id: entityId,
      entity_ref: entityRef,
      name: name,
      team: this.team,
      initiative: this.initiativeResult ?? 0,
      initiative_roll: this.initiativeRoll,
      // Note: ac, hp, max_hp come from StatsComponent, not CombatComponent
      actions_remaining: 3, // Default PF2e action economy
      attacks_this_turn: 0,
      is_defeated: this.isDefeated ? 1 : 0
      // Note: position_x, position_y come from PositionComponent
    };
  }
  
  /**
   * Deserialize component from JSON data.
   * 
   * Accepts data from:
   * - character_data/state_data JSON fields (dc_campaign_characters)
   * - combat_participants table snapshot (with field mapping)
   * 
   * Field mapping for combat_participants:
   * - initiative → initiativeResult
   * - initiative_roll → initiativeRoll
   * - team → team
   * - is_defeated → isDefeated
   * 
   * @param {Object} data - Serialized component data
   * @param {number} [data.initiativeBonus] - Initiative bonus
   * @param {number|null} [data.initiativeRoll] - Last rolled initiative (d20)
   * @param {number|null} [data.initiativeResult] - Final initiative score
   * @param {number|null} [data.initiative] - Alternative: final initiative (from combat_participants)
   * @param {number|null} [data.initiative_roll] - Alternative: d20 roll (from combat_participants)
   * @param {string} [data.team] - Team affiliation
   * @param {boolean} [data.inCombat] - Combat state flag
   * @param {boolean} [data.isDefeated] - Defeated state flag
   * @param {number} [data.is_defeated] - Alternative: defeated flag (from combat_participants)
   * @param {boolean} [data.hasTakenTurn] - Turn tracking flag
   * @param {number|null} [data.turnOrder] - Position in initiative order
   * @param {number} [data.weaponProficiency] - Weapon proficiency bonus
   * @param {number} [data.attackBonus] - Base attack bonus
   * @param {number} [data.armorProficiency] - Armor proficiency bonus
   * @returns {CombatComponent} New CombatComponent instance with loaded data
   */
  static fromJSON(data) {
    const config = {
      initiativeBonus: data.initiativeBonus,
      team: data.team,
      weaponProficiency: data.weaponProficiency,
      attackBonus: data.attackBonus,
      armorProficiency: data.armorProficiency
    };
    
    const component = new CombatComponent(config);
    
    // Support both component format and combat_participants table format
    component.initiativeRoll = data.initiativeRoll ?? data.initiative_roll ?? null;
    component.initiativeResult = data.initiativeResult ?? data.initiative ?? null;
    component.inCombat = data.inCombat;
    component.isDefeated = data.isDefeated ?? (data.is_defeated === 1) ?? false;
    component.hasTakenTurn = data.hasTakenTurn;
    component.turnOrder = data.turnOrder;
    
    return component;
  }
  
  /**
   * Load from combat_participants table record.
   * Convenience method for loading during active combat encounters.
   * 
   * @param {Object} participantRecord - Database record from combat_participants table
   * @returns {CombatComponent} Component loaded from participant data
   */
  static fromCombatParticipant(participantRecord) {
    return CombatComponent.fromJSON({
      initiative: participantRecord.initiative,
      initiative_roll: participantRecord.initiative_roll,
      team: participantRecord.team,
      is_defeated: participantRecord.is_defeated,
      inCombat: true // Participant exists, so entity is in combat
    });
  }
}
