/**
 * @file
 * Entity class - represents a game object by ID with attached components.
 */

export class Entity {
  /**
   * Create a new entity.
   * @param {number} id - Unique positive entity ID
   * @throws {Error} If id is not a positive number
   */
  constructor(id) {
    if (typeof id !== 'number' || id <= 0 || !Number.isInteger(id)) {
      throw new Error('Entity ID must be a positive integer');
    }
    this.id = id;
    this.components = new Map();
    this.active = true;
  }

  /**
   * Add a component to this entity.
   * @param {string} componentName - Component type name (non-empty string)
   * @param {object} componentData - Component instance (must be an object)
   * @returns {Entity} This entity for chaining
   * @throws {Error} If componentName is empty or componentData is null/undefined
   */
  addComponent(componentName, componentData) {
    if (!componentName || typeof componentName !== 'string') {
      throw new Error('Component name must be a non-empty string');
    }
    if (componentData === null || componentData === undefined) {
      throw new Error('Component data cannot be null or undefined');
    }
    this.components.set(componentName, componentData);
    return this;
  }

  /**
   * Get a component from this entity.
   * @param {string} componentName - Component type name
   * @returns {object|undefined} Component instance or undefined if not found
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
        // Deep clone to prevent shared references
        data.components[name] = JSON.parse(JSON.stringify(component));
      }
    }

    return data;
  }

  /**
   * Create entity from JSON data.
   * @param {object} data - Serialized entity data with id, active, and components
   * @param {object} componentClasses - Map of component name to class constructor
   * @returns {Entity} Deserialized entity
   * @throws {Error} If data is invalid or missing required fields
   */
  static fromJSON(data, componentClasses) {
    if (!data || typeof data !== 'object') {
      throw new Error('Invalid data: must be an object');
    }
    if (!data.id) {
      throw new Error('Invalid data: missing required field "id"');
    }
    
    const entity = new Entity(data.id);
    entity.active = data.active !== undefined ? data.active : true;

    if (data.components) {
      for (const [name, componentData] of Object.entries(data.components)) {
        if (componentClasses && componentClasses[name] && componentClasses[name].fromJSON) {
          entity.addComponent(name, componentClasses[name].fromJSON(componentData));
        } else {
          entity.addComponent(name, componentData);
        }
      }
    }

    return entity;
  }
}
