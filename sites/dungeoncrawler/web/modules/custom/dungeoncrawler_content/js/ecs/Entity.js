/**
 * @file
 * Entity class - represents a game object by ID with attached components.
 */

export class Entity {
  /**
   * Create a new entity.
   * @param {number} id - Unique entity ID
   */
  constructor(id) {
    this.id = id;
    this.components = new Map();
    this.active = true;
  }

  /**
   * Add a component to this entity.
   * @param {string} componentName - Component type name
   * @param {object} componentData - Component instance
   * @returns {Entity} This entity for chaining
   */
  addComponent(componentName, componentData) {
    this.components.set(componentName, componentData);
    return this;
  }

  /**
   * Get a component from this entity.
   * @param {string} componentName - Component type name
   * @returns {object|undefined} Component instance or undefined
   */
  getComponent(componentName) {
    return this.components.get(componentName);
  }

  /**
   * Check if entity has a component.
   * @param {string} componentName - Component type name
   * @returns {boolean} True if component exists
   */
  hasComponent(componentName) {
    return this.components.has(componentName);
  }

  /**
   * Remove a component from this entity.
   * @param {string} componentName - Component type name
   * @returns {boolean} True if component was removed
   */
  removeComponent(componentName) {
    return this.components.delete(componentName);
  }

  /**
   * Get all component names.
   * @returns {string[]} Array of component names
   */
  getComponentNames() {
    return Array.from(this.components.keys());
  }

  /**
   * Deactivate this entity (marks for removal).
   */
  destroy() {
    this.active = false;
  }

  /**
   * Check if entity is active.
   * @returns {boolean} True if active
   */
  isActive() {
    return this.active;
  }

  /**
   * Serialize entity to JSON.
   * @returns {object} Serialized entity data
   */
  toJSON() {
    const data = {
      id: this.id,
      active: this.active,
      components: {}
    };

    for (const [name, component] of this.components.entries()) {
      if (typeof component.toJSON === 'function') {
        data.components[name] = component.toJSON();
      } else {
        data.components[name] = { ...component };
      }
    }

    return data;
  }

  /**
   * Create entity from JSON data.
   * @param {object} data - Serialized entity data
   * @param {object} componentClasses - Map of component name to class
   * @returns {Entity} Deserialized entity
   */
  static fromJSON(data, componentClasses) {
    const entity = new Entity(data.id);
    entity.active = data.active;

    for (const [name, componentData] of Object.entries(data.components)) {
      if (componentClasses[name] && componentClasses[name].fromJSON) {
        entity.addComponent(name, componentClasses[name].fromJSON(componentData));
      } else {
        entity.addComponent(name, componentData);
      }
    }

    return entity;
  }
}
