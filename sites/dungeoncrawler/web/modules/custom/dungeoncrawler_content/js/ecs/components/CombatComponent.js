/**
 * @file CombatComponent.js
 * Component for combat-related data (initiative, team, etc.).
 */

import { Component } from '../Component.js';

/**
 * Team affiliations.
 */
export const Team = {
  PLAYER: 'player',
  ENEMY: 'enemy',
  NEUTRAL: 'neutral',
  ALLY: 'ally'
};

/**
 * CombatComponent
 * 
 * Stores combat-related data including initiative, team affiliation,
 * and combat state.
 */
export class CombatComponent extends Component {
  /**
   * @param {Object} config - Configuration object
   */
  constructor(config = {}) {
    super();
    
    // Initiative
    this.initiativeBonus = config.initiativeBonus || 0;
    this.initiativeRoll = null; // Rolled value (d20 + bonus)
    this.initiativeResult = null; // Final initiative score
    
    // Team affiliation
    this.team = config.team || Team.NEUTRAL;
    
    // Combat state
    this.inCombat = false;
    this.isDefeated = false;
    
    // Turn tracking
    this.hasTakenTurn = false;
    this.turnOrder = null; // Position in initiative order
    
    // Weapon proficiencies (for attack rolls)
    this.weaponProficiency = config.weaponProficiency || 0;
    
    // Attack bonus (includes STR/DEX mod in combat system)
    this.attackBonus = config.attackBonus || 0;
    
    // Armor proficiency (for AC calculation)
    this.armorProficiency = config.armorProficiency || 0;
  }
  
  /**
   * Roll initiative.
   * @param {number} perceptionBonus - Perception bonus to add
   * @returns {number} - Initiative result
   */
  rollInitiative(perceptionBonus = 0) {
    // Roll d20
    this.initiativeRoll = Math.floor(Math.random() * 20) + 1;
    
    // Calculate result: d20 + perception + initiativeBonus
    this.initiativeResult = this.initiativeRoll + perceptionBonus + this.initiativeBonus;
    
    return this.initiativeResult;
  }
  
  /**
   * Set initiative result manually (for sorting).
   * @param {number} result - Initiative result
   */
  setInitiative(result) {
    this.initiativeResult = result;
  }
  
  /**
   * Get initiative result.
   * @returns {number|null}
   */
  getInitiative() {
    return this.initiativeResult;
  }
  
  /**
   * Check if initiative has been rolled.
   * @returns {boolean}
   */
  hasInitiative() {
    return this.initiativeResult !== null;
  }
  
  /**
   * Reset initiative (for new combat).
   */
  resetInitiative() {
    this.initiativeRoll = null;
    this.initiativeResult = null;
    this.hasTakenTurn = false;
    this.turnOrder = null;
  }
  
  /**
   * Start combat.
   */
  enterCombat() {
    this.inCombat = true;
    this.isDefeated = false;
    this.hasTakenTurn = false;
  }
  
  /**
   * End combat.
   */
  exitCombat() {
    this.inCombat = false;
    this.resetInitiative();
  }
  
  /**
   * Mark as defeated.
   */
  defeat() {
    this.isDefeated = true;
  }
  
  /**
   * Check if entity is on player team.
   * @returns {boolean}
   */
  isPlayerTeam() {
    return this.team === Team.PLAYER;
  }
  
  /**
   * Check if entity is hostile.
   * @returns {boolean}
   */
  isHostile() {
    return this.team === Team.ENEMY;
  }
  
  /**
   * Check if two entities are on same team.
   * @param {CombatComponent} other - Other combat component
   * @returns {boolean}
   */
  isSameTeam(other) {
    return this.team === other.team;
  }
  
  /**
   * Check if entity is hostile to another.
   * @param {CombatComponent} other - Other combat component
   * @returns {boolean}
   */
  isHostileTo(other) {
    if (this.team === Team.NEUTRAL || other.team === Team.NEUTRAL) {
      return false;
    }
    
    if (this.team === Team.PLAYER) {
      return other.team === Team.ENEMY;
    }
    
    if (this.team === Team.ENEMY) {
      return other.team === Team.PLAYER || other.team === Team.ALLY;
    }
    
    return false;
  }
  
  /**
   * Start turn.
   */
  startTurn() {
    this.hasTakenTurn = true;
  }
  
  /**
   * Mark turn as complete.
   */
  endTurn() {
    // Turn tracking handled by TurnManagementSystem
  }
  
  /**
   * Check if has taken turn this round.
   * @returns {boolean}
   */
  hasTurnCompleted() {
    return this.hasTakenTurn;
  }
  
  /**
   * Reset turn tracking (new round).
   */
  resetTurnTracking() {
    this.hasTakenTurn = false;
  }
  
  /**
   * Serialize component to JSON.
   * @returns {Object}
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
   * Deserialize component from JSON.
   * @param {Object} data - Serialized data
   * @returns {CombatComponent}
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
    component.initiativeRoll = data.initiativeRoll;
    component.initiativeResult = data.initiativeResult;
    component.inCombat = data.inCombat;
    component.isDefeated = data.isDefeated;
    component.hasTakenTurn = data.hasTakenTurn;
    component.turnOrder = data.turnOrder;
    
    return component;
  }
}
