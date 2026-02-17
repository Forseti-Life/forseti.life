/**
 * @file
 * IdentityComponent - entity name, type, and description.
 */

import { Component } from '../Component.js';

export const EntityType = {
  PLAYER_CHARACTER: 'player_character',
  NPC: 'npc',
  CREATURE: 'creature',
  ITEM: 'item',
  OBSTACLE: 'obstacle',
  TRAP: 'trap',
  TREASURE: 'treasure',
  HAZARD: 'hazard'
};

/**
 * IdentityComponent
 * 
 * Stores identity information for entities including name, type, description, and tags.
 * 
 * @property {string} name - Entity name
 * @property {string} entityType - Entity type from EntityType enum
 * @property {string} description - Entity description
 * @property {string[]} tags - Array of string tags for flexible categorization
 */
export class IdentityComponent extends Component {
  /**
   * Create an identity component.
   * @param {string} name - Entity name
   * @param {string} entityType - Entity type from EntityType enum
   * @param {string} description - Entity description
   */
  constructor(name = 'Unnamed', entityType = EntityType.CREATURE, description = '') {
    super();
    this.name = name;
    this.entityType = entityType;
    this.description = description;
    this.tags = [];
  }

  /**
   * Add a tag.
   * @param {string} tag - Tag to add
   */
  addTag(tag) {
    if (!this.tags.includes(tag)) {
      this.tags.push(tag);
    }
  }

  /**
   * Remove a tag.
   * @param {string} tag - Tag to remove
   */
  removeTag(tag) {
    const index = this.tags.indexOf(tag);
    if (index !== -1) {
      this.tags.splice(index, 1);
    }
  }

  /**
   * Check if has tag.
   * @param {string} tag - Tag to check
   * @returns {boolean} True if has tag
   */
  hasTag(tag) {
    return this.tags.includes(tag);
  }

  /**
   * Check if entity is a player character.
   * @returns {boolean} True if player character
   */
  isPlayerCharacter() {
    return this.entityType === EntityType.PLAYER_CHARACTER;
  }

  /**
   * Check if entity is an NPC.
   * @returns {boolean} True if NPC
   */
  isNPC() {
    return this.entityType === EntityType.NPC;
  }

  /**
   * Check if entity is a creature (including NPCs and player characters).
   * @returns {boolean} True if creature, NPC, or player character
   */
  isCreature() {
    return this.entityType === EntityType.CREATURE || 
           this.entityType === EntityType.NPC ||
           this.entityType === EntityType.PLAYER_CHARACTER;
  }

  /**
   * Check if entity is an item.
   * @returns {boolean} True if item
   */
  isItem() {
    return this.entityType === EntityType.ITEM || this.entityType === EntityType.TREASURE;
  }

  /**
   * Check if entity blocks movement.
   * Entities that block movement make their hex impassable to other entities.
   * @returns {boolean} True if blocks movement
   */
  blocksMovement() {
    return this.entityType === EntityType.OBSTACLE || 
           this.entityType === EntityType.CREATURE ||
           this.entityType === EntityType.NPC ||
           this.entityType === EntityType.PLAYER_CHARACTER;
  }

  /**
   * Serialize component to JSON.
   * @returns {object} Serialized component data
   */
  toJSON() {
    return {
      name: this.name,
      entityType: this.entityType,
      description: this.description,
      tags: [...this.tags]
    };
  }

  /**
   * Deserialize component from JSON.
   * @param {object} data - Serialized component data
   * @returns {IdentityComponent} New component instance
   */
  static fromJSON(data) {
    const component = new IdentityComponent(
      data.name,
      data.entityType,
      data.description
    );
    if (data.tags) {
      component.tags = [...data.tags];
    }
    return component;
  }

  /**
   * Clone this component.
   * @returns {IdentityComponent} Cloned component
   */
  clone() {
    return IdentityComponent.fromJSON(this.toJSON());
  }
}
