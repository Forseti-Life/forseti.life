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
   * Check if entity is a creature.
   * @returns {boolean} True if creature
   */
  isCreature() {
    return this.entityType === EntityType.CREATURE || this.entityType === EntityType.NPC;
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
   * @returns {boolean} True if blocks movement
   */
  blocksMovement() {
    return this.entityType === EntityType.OBSTACLE || 
           this.entityType === EntityType.CREATURE ||
           this.entityType === EntityType.PLAYER_CHARACTER;
  }
}
