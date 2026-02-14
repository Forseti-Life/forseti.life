/**
 * @file
 * EntityManager - manages all entities and provides query functionality.
 */

import { Entity } from './Entity.js';

export class EntityManager {
  constructor() {
    this.entities = new Map();
    this.nextEntityId = 1;
    this.systems = [];
    
    // Cache for component queries
    this.queryCache = new Map();
  }

  /**
   * Create a new entity.
   * @returns {Entity} New entity
   */
  createEntity() {
    const entity = new Entity(this.nextEntityId++);
    this.entities.set(entity.id, entity);
    this.invalidateQueryCache();
    
    console.log(`Created entity ${entity.id}`);
    return entity;
  }

  /**
   * Get entity by ID.
   * @param {number} id - Entity ID
   * @returns {Entity|undefined} Entity or undefined
   */
  getEntity(id) {
    return this.entities.get(id);
  }

  /**
   * Remove entity by ID.
   * @param {number} id - Entity ID
   * @returns {boolean} True if entity was removed
   */
  removeEntity(id) {
    const entity = this.entities.get(id);
    if (entity) {
      entity.destroy();
      this.entities.delete(id);
      this.invalidateQueryCache();
      console.log(`Removed entity ${id}`);
      return true;
    }
    return false;
  }

  /**
   * Get all entities.
   * @returns {Entity[]} Array of all entities
   */
  getAllEntities() {
    return Array.from(this.entities.values()).filter(e => e.isActive());
  }

  /**
   * Get entities that have all specified components.
   * Results are cached for performance.
   * @param {...string} componentNames - Component names to query
   * @returns {Entity[]} Array of matching entities
   */
  getEntitiesWith(...componentNames) {
    // Create cache key
    const cacheKey = componentNames.sort().join('|');
    
    // Check cache
    if (this.queryCache.has(cacheKey)) {
      return this.queryCache.get(cacheKey);
    }

    // Query entities
    const result = [];
    for (const entity of this.entities.values()) {
      if (!entity.isActive()) continue;
      
      if (componentNames.every(name => entity.hasComponent(name))) {
        result.push(entity);
      }
    }

    // Cache result
    this.queryCache.set(cacheKey, result);
    return result;
  }

  /**
   * Get entities that have any of the specified components.
   * @param {...string} componentNames - Component names to query
   * @returns {Entity[]} Array of matching entities
   */
  getEntitiesWithAny(...componentNames) {
    const result = [];
    for (const entity of this.entities.values()) {
      if (!entity.isActive()) continue;
      
      if (componentNames.some(name => entity.hasComponent(name))) {
        result.push(entity);
      }
    }
    return result;
  }

  /**
   * Get entity count.
   * @returns {number} Number of active entities
   */
  getEntityCount() {
    return this.getAllEntities().length;
  }

  /**
   * Clear all entities.
   */
  clear() {
    this.entities.clear();
    this.nextEntityId = 1;
    this.invalidateQueryCache();
    console.log('Cleared all entities');
  }

  /**
   * Backwards-compatible alias for clear().
   */
  removeAllEntities() {
    this.clear();
  }

  /**
   * Invalidate query cache (call when entities change).
   */
  invalidateQueryCache() {
    this.queryCache.clear();
  }

  /**
   * Add a system to the manager.
   * @param {System} system - System instance
   */
  addSystem(system) {
    this.systems.push(system);
    this.systems.sort((a, b) => a.priority - b.priority);
    system.init();
    console.log(`Added system: ${system.constructor.name}`);
  }

  /**
   * Remove a system from the manager.
   * @param {System} system - System instance
   */
  removeSystem(system) {
    const index = this.systems.indexOf(system);
    if (index !== -1) {
      this.systems.splice(index, 1);
      system.destroy();
      console.log(`Removed system: ${system.constructor.name}`);
    }
  }

  /**
   * Update all systems.
   * @param {number} deltaTime - Time since last update (ms)
   */
  update(deltaTime) {
    for (const system of this.systems) {
      if (system.isEnabled()) {
        system.update(deltaTime);
      }
    }
  }

  /**
   * Serialize all entities to JSON.
   * @returns {object} Serialized data
   */
  toJSON() {
    const entities = [];
    for (const entity of this.entities.values()) {
      if (entity.isActive()) {
        entities.push(entity.toJSON());
      }
    }

    return {
      nextEntityId: this.nextEntityId,
      entities: entities
    };
  }

  /**
   * Deserialize entities from JSON.
   * @param {object} data - Serialized data
   * @param {object} componentClasses - Map of component name to class
   */
  fromJSON(data, componentClasses) {
    this.clear();
    this.nextEntityId = data.nextEntityId;

    for (const entityData of data.entities) {
      const entity = Entity.fromJSON(entityData, componentClasses);
      this.entities.set(entity.id, entity);
    }

    this.invalidateQueryCache();
    console.log(`Loaded ${data.entities.length} entities`);
  }
}
