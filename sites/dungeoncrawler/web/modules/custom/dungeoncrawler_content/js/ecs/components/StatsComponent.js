/**
 * @file StatsComponent.js
 * Component for entity statistics (Pathfinder 2e compliant).
 */

import { Component } from '../Component.js';

/**
 * StatsComponent
 * 
 * Stores core statistics for entities including ability scores,
 * hit points, armor class, saves, and speeds.
 * 
 * This is a minimal implementation focusing on movement-critical stats.
 * Will be expanded to full PF2e stats in future iterations.
 */
export class StatsComponent extends Component {
  /**
   * @param {Object} config - Configuration object
   */
  constructor(config = {}) {
    super();
    
    // Ability Scores (10 is average, modifiers = (score - 10) / 2)
    this.abilities = {
      strength: config.strength || 10,
      dexterity: config.dexterity || 10,
      constitution: config.constitution || 10,
      intelligence: config.intelligence || 10,
      wisdom: config.wisdom || 10,
      charisma: config.charisma || 10
    };
    
    // Hit Points
    this.maxHp = config.maxHp || 10;
    this.currentHp = config.currentHp || this.maxHp;
    this.tempHp = 0;
    
    // Armor Class
    this.ac = config.ac || 10;
    
    // Saving Throws (total bonus)
    this.saves = {
      fortitude: config.fortitude || 0,
      reflex: config.reflex || 0,
      will: config.will || 0
    };
    
    // Movement Speeds (feet per round)
    this.speeds = {
      walk: config.speed || 30,
      fly: config.flySpeed || 0,
      swim: config.swimSpeed || 0,
      burrow: config.burrowSpeed || 0,
      climb: config.climbSpeed || 0
    };
    
    // Level and proficiency
    this.level = config.level || 1;
    this.proficiencyBonus = config.proficiencyBonus || 2;
    
    // Perception
    this.perception = config.perception || 0;
  }
  
  /**
   * Get ability modifier.
   * @param {string} ability - Ability name (strength, dexterity, etc.)
   * @returns {number}
   */
  getAbilityModifier(ability) {
    const score = this.abilities[ability] || 10;
    return Math.floor((score - 10) / 2);
  }
  
  /**
   * Apply damage to HP.
   * @param {number} damage - Damage amount
   * @returns {number} - Actual damage dealt (after temp HP)
   */
  takeDamage(damage) {
    let remaining = damage;
    
    // Temp HP absorbs damage first
    if (this.tempHp > 0) {
      const absorbed = Math.min(this.tempHp, damage);
      this.tempHp -= absorbed;
      remaining -= absorbed;
    }
    
    // Apply remaining damage to current HP
    if (remaining > 0) {
      this.currentHp = Math.max(0, this.currentHp - remaining);
    }
    
    return damage;
  }
  
  /**
   * Heal HP.
   * @param {number} amount - Healing amount
   * @returns {number} - Actual HP healed
   */
  heal(amount) {
    const oldHp = this.currentHp;
    this.currentHp = Math.min(this.maxHp, this.currentHp + amount);
    return this.currentHp - oldHp;
  }
  
  /**
   * Check if entity is alive.
   * @returns {boolean}
   */
  isAlive() {
    return this.currentHp > 0;
  }
  
  /**
   * Check if entity is at full HP.
   * @returns {boolean}
   */
  isFullHealth() {
    return this.currentHp >= this.maxHp;
  }
  
  /**
   * Get HP percentage.
   * @returns {number} - 0.0 to 1.0
   */
  getHealthPercentage() {
    return this.maxHp > 0 ? this.currentHp / this.maxHp : 0;
  }
  
  /**
   * Serialize component to JSON.
   * @returns {Object}
   */
  toJSON() {
    return {
      type: this.constructor.name,
      abilities: {...this.abilities},
      maxHp: this.maxHp,
      currentHp: this.currentHp,
      tempHp: this.tempHp,
      ac: this.ac,
      saves: {...this.saves},
      speeds: {...this.speeds},
      level: this.level,
      proficiencyBonus: this.proficiencyBonus,
      perception: this.perception
    };
  }
  
  /**
   * Deserialize component from JSON.
   * @param {Object} data - Serialized data
   * @returns {StatsComponent}
   */
  static fromJSON(data) {
    const config = {
      strength: data.abilities.strength,
      dexterity: data.abilities.dexterity,
      constitution: data.abilities.constitution,
      intelligence: data.abilities.intelligence,
      wisdom: data.abilities.wisdom,
      charisma: data.abilities.charisma,
      maxHp: data.maxHp,
      currentHp: data.currentHp,
      ac: data.ac,
      fortitude: data.saves.fortitude,
      reflex: data.saves.reflex,
      will: data.saves.will,
      speed: data.speeds.walk,
      flySpeed: data.speeds.fly,
      swimSpeed: data.speeds.swim,
      burrowSpeed: data.speeds.burrow,
      climbSpeed: data.speeds.climb,
      level: data.level,
      proficiencyBonus: data.proficiencyBonus,
      perception: data.perception
    };
    
    const component = new StatsComponent(config);
    component.tempHp = data.tempHp;
    return component;
  }
}
