/**
 * @file ActionsComponent.js
 * Component for Pathfinder 2e 3-action economy.
 */

import { Component } from '../Component.js';

/**
 * Action types in PF2e.
 */
export const ActionType = {
  ACTION: 'action',           // Standard action (1 action)
  REACTION: 'reaction',       // Reaction (triggered)
  FREE_ACTION: 'free_action', // Free action
  ACTIVITY: 'activity'        // Multi-action activity (2-3 actions)
};

/**
 * Action cost in number of actions.
 */
export const ActionCost = {
  FREE: 0,
  ONE: 1,
  TWO: 2,
  THREE: 3,
  REACTION: -1 // Special: uses reaction
};

/**
 * ActionsComponent
 * 
 * Manages the 3-action economy for Pathfinder 2e.
 * Tracks available actions, reactions, and Multiple Attack Penalty (MAP).
 */
export class ActionsComponent extends Component {
  /**
   * @param {number} maxActions - Maximum actions per turn (default 3)
   */
  constructor(maxActions = 3) {
    super();
    
    // Action economy
    this.maxActions = maxActions;
    this.actionsRemaining = maxActions;
    this.hasReaction = true;
    
    // Multiple Attack Penalty (MAP)
    this.attacksMadeThisTurn = 0;
    this.mapPenalty = 0; // Current MAP (-5, -10, etc.)
    
    // Action history this turn
    this.actionHistory = [];
    
    // Can take actions?
    this.canAct = true;
    
    // Bonus/penalty to action count
    this.actionBonus = 0; // e.g., Haste gives +1
  }
  
  /**
   * Check if entity can afford an action.
   * @param {number} cost - Action cost (from ActionCost enum)
   * @returns {boolean}
   */
  canAfford(cost) {
    if (!this.canAct) {
      return false;
    }
    
    if (cost === ActionCost.FREE) {
      return true;
    }
    
    if (cost === ActionCost.REACTION) {
      return this.hasReaction;
    }
    
    return this.actionsRemaining >= cost;
  }
  
  /**
   * Consume actions.
   * @param {number} cost - Action cost
   * @param {string} actionName - Name of action for history
   * @returns {boolean} - True if actions were consumed
   */
  spendActions(cost, actionName = 'Unknown') {
    if (!this.canAfford(cost)) {
      return false;
    }
    
    if (cost === ActionCost.REACTION) {
      this.hasReaction = false;
      this.actionHistory.push({
        name: actionName,
        cost: cost,
        type: ActionType.REACTION,
        timestamp: Date.now()
      });
      return true;
    }
    
    if (cost === ActionCost.FREE) {
      this.actionHistory.push({
        name: actionName,
        cost: cost,
        type: ActionType.FREE_ACTION,
        timestamp: Date.now()
      });
      return true;
    }
    
    // Spend standard actions
    this.actionsRemaining -= cost;
    this.actionHistory.push({
      name: actionName,
      cost: cost,
      type: cost >= 2 ? ActionType.ACTIVITY : ActionType.ACTION,
      timestamp: Date.now()
    });
    
    return true;
  }
  
  /**
   * Record an attack for MAP calculation.
   * @returns {number} - MAP penalty to apply to this attack
   */
  makeAttack() {
    const currentMAP = this.mapPenalty;
    this.attacksMadeThisTurn++;
    
    // Update MAP for next attack
    // Standard MAP progression: 0, -5, -10
    // Agile weapons: 0, -4, -8 (can be handled in combat system)
    this.mapPenalty = this.attacksMadeThisTurn * -5;
    
    return currentMAP;
  }
  
  /**
   * Get current MAP penalty.
   * @returns {number}
   */
  getCurrentMAP() {
    return this.mapPenalty;
  }
  
  /**
   * Restore actions at start of turn.
   */
  startTurn() {
    this.actionsRemaining = this.maxActions + this.actionBonus;
    this.hasReaction = true;
    this.attacksMadeThisTurn = 0;
    this.mapPenalty = 0;
    this.actionHistory = [];
    this.canAct = true;
  }
  
  /**
   * End turn cleanup.
   */
  endTurn() {
    this.actionsRemaining = 0;
    // Reaction persists until start of next turn
    // MAP resets at start of turn, not end
  }
  
  /**
   * Check if has actions remaining.
   * @returns {boolean}
   */
  hasActionsRemaining() {
    return this.actionsRemaining > 0;
  }
  
  /**
   * Check if has reaction available.
   * @returns {boolean}
   */
  hasReactionAvailable() {
    return this.hasReaction;
  }
  
  /**
   * Get action count with visual representation.
   * @returns {string} - e.g., "◆◆◇" for 2 actions
   */
  getActionDisplay() {
    const filled = '◆'; // Filled diamond (action available)
    const empty = '◇';  // Empty diamond (action used)
    const total = this.maxActions + this.actionBonus;
    
    let display = '';
    for (let i = 0; i < total; i++) {
      display += i < this.actionsRemaining ? filled : empty;
    }
    return display;
  }
  
  /**
   * Apply status effect that modifies actions.
   * @param {string} effect - Effect name
   * @param {number} modifier - Action modifier
   */
  applyActionModifier(effect, modifier) {
    this.actionBonus += modifier;
    // Cap between -3 and +3
    this.actionBonus = Math.max(-3, Math.min(3, this.actionBonus));
  }
  
  /**
   * Prevent entity from taking actions (e.g., stunned, paralyzed).
   * @param {boolean} canAct - Can entity act?
   */
  setCanAct(canAct) {
    this.canAct = canAct;
    if (!canAct) {
      this.actionsRemaining = 0;
    }
  }
  
  /**
   * Serialize component to JSON.
   * @returns {Object}
   */
  toJSON() {
    return {
      type: this.constructor.name,
      maxActions: this.maxActions,
      actionsRemaining: this.actionsRemaining,
      hasReaction: this.hasReaction,
      attacksMadeThisTurn: this.attacksMadeThisTurn,
      mapPenalty: this.mapPenalty,
      actionHistory: [...this.actionHistory],
      canAct: this.canAct,
      actionBonus: this.actionBonus
    };
  }
  
  /**
   * Deserialize component from JSON.
   * @param {Object} data - Serialized data
   * @returns {ActionsComponent}
   */
  static fromJSON(data) {
    const component = new ActionsComponent(data.maxActions);
    component.actionsRemaining = data.actionsRemaining;
    component.hasReaction = data.hasReaction;
    component.attacksMadeThisTurn = data.attacksMadeThisTurn;
    component.mapPenalty = data.mapPenalty;
    component.actionHistory = data.actionHistory ? [...data.actionHistory] : [];
    component.canAct = data.canAct;
    component.actionBonus = data.actionBonus || 0;
    return component;
  }
}
