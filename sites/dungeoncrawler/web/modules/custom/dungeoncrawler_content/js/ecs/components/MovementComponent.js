/**
 * @file MovementComponent.js
 * Component for entity movement capabilities and state.
 */

import { Component } from '../Component.js';

/**
 * Movement modes supported by the system.
 */
export const MovementMode = {
  WALK: 'walk',
  FLY: 'fly',
  SWIM: 'swim',
  BURROW: 'burrow',
  CLIMB: 'climb'
};

/**
 * MovementComponent
 * 
 * Stores movement-related data for entities that can move.
 * Tracks movement budget, speed, mode, and planned paths.
 */
export class MovementComponent extends Component {
  /**
   * @param {number} movementSpeed - Base movement speed in feet (typically 25 or 30)
   */
  constructor(movementSpeed = 30) {
    super();
    
    // Can this entity move this turn?
    this.canMove = true;
    
    // Movement remaining this turn (feet)
    this.movementRemaining = movementSpeed;
    
    // Base movement speed (feet per round)
    this.movementSpeed = movementSpeed;
    
    // Current movement mode
    this.movementMode = MovementMode.WALK;
    
    // Planned path as array of {q, r} coordinates
    this.path = [];
    
    // Movement costs per hex (modified by terrain)
    this.hexMovementCost = 5; // Standard: 5 feet per hex
    
    // Available movement modes with speeds
    this.movementModes = {
      [MovementMode.WALK]: movementSpeed,
      [MovementMode.FLY]: 0,
      [MovementMode.SWIM]: 0,
      [MovementMode.BURROW]: 0,
      [MovementMode.CLIMB]: 0
    };
  }
  
  /**
   * Set movement mode and update current budget.
   * @param {string} mode - Movement mode from MovementMode enum
   */
  setMovementMode(mode) {
    if (this.movementModes[mode] > 0) {
      this.movementMode = mode;
      this.movementRemaining = this.movementModes[mode];
      return true;
    }
    return false;
  }
  
  /**
   * Check if entity has a specific movement mode.
   * @param {string} mode - Movement mode to check
   * @returns {boolean}
   */
  hasMovementMode(mode) {
    return this.movementModes[mode] > 0;
  }
  
  /**
   * Add or update a movement mode.
   * @param {string} mode - Movement mode
   * @param {number} speed - Speed in feet
   */
  addMovementMode(mode, speed) {
    this.movementModes[mode] = speed;
  }
  
  /**
   * Consume movement budget.
   * @param {number} cost - Movement cost in feet
   * @returns {boolean} - True if movement was consumed, false if insufficient
   */
  consumeMovement(cost) {
    if (this.movementRemaining >= cost) {
      this.movementRemaining -= cost;
      return true;
    }
    return false;
  }
  
  /**
   * Restore movement budget (e.g., at start of turn).
   */
  restoreMovement() {
    this.movementRemaining = this.movementModes[this.movementMode];
    this.canMove = true;
    this.path = [];
  }
  
  /**
   * Get number of hexes this entity can move.
   * @returns {number}
   */
  getMaxHexes() {
    return Math.floor(this.movementRemaining / this.hexMovementCost);
  }
  
  /**
   * Serialize component to JSON.
   * @returns {Object}
   */
  toJSON() {
    return {
      type: this.constructor.name,
      canMove: this.canMove,
      movementRemaining: this.movementRemaining,
      movementSpeed: this.movementSpeed,
      movementMode: this.movementMode,
      path: this.path,
      hexMovementCost: this.hexMovementCost,
      movementModes: {...this.movementModes}
    };
  }
  
  /**
   * Deserialize component from JSON.
   * @param {Object} data - Serialized data
   * @returns {MovementComponent}
   */
  static fromJSON(data) {
    const component = new MovementComponent(data.movementSpeed);
    component.canMove = data.canMove;
    component.movementRemaining = data.movementRemaining;
    component.movementMode = data.movementMode;
    component.path = data.path || [];
    component.hexMovementCost = data.hexMovementCost;
    component.movementModes = {...data.movementModes};
    return component;
  }
}
