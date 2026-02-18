/**
 * @file
 * Entity class - represents a game object by ID with attached components.
 * 
 * Supports both ECS component-based format and entity_instance.schema.json format
 * for unified JSON/hot-column structure conformance with database tables.
 */

export class Entity {
  /**
   * Create a new entity.
   * @param {number} id - Unique positive entity ID (for ECS compatibility)
   * @param {object} options - Optional entity_instance schema properties
   * @param {string} options.entity_instance_id - UUID for entity_instance schema
   * @param {string} options.entity_type - Entity type: creature, item, or obstacle
   * @param {object} options.entity_ref - Reference to content registry
   * @param {object} options.placement - Placement data (room_id, hex coordinates)
   * @throws {Error} If id is not a positive number
   */
  constructor(id, options = {}) {
    if (typeof id !== 'number' || id <= 0 || !Number.isInteger(id)) {
      throw new Error('Entity ID must be a positive integer');
    }
    this.id = id;
    this.components = new Map();
    this.active = true;
    
    // Optional entity_instance.schema.json properties
    if (options.entity_instance_id) {
      this.entity_instance_id = options.entity_instance_id;
    }
    if (options.entity_type) {
      this.entity_type = options.entity_type;
    }
    if (options.entity_ref) {
      this.entity_ref = options.entity_ref;
    }
    if (options.placement) {
      this.placement = options.placement;
    }
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
   * Supports both ECS format and entity_instance.schema.json format.
   * 
   * @param {string} format - Output format: 'ecs' (default) or 'entity_instance'
   * @returns {object} Serialized entity data
   */
  toJSON(format = 'ecs') {
    if (format === 'entity_instance') {
      return this._toEntityInstanceJSON();
    }
    
    // Default ECS format (backward compatible)
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
    
    // Include entity_instance properties if present
    if (this.entity_instance_id) {
      data.entity_instance_id = this.entity_instance_id;
    }
    if (this.entity_type) {
      data.entity_type = this.entity_type;
    }
    if (this.entity_ref) {
      data.entity_ref = this.entity_ref;
    }
    if (this.placement) {
      data.placement = this.placement;
    }

    return data;
  }
  
  /**
   * Serialize entity to entity_instance.schema.json format.
   * Converts component-based state to structured state object.
   * 
   * @private
   * @returns {object} Entity instance in schema format
   */
  _toEntityInstanceJSON() {
    // Extract state from components or use defaults
    const state = {
      active: this.active,
      destroyed: false,
      disabled: false,
      hidden: false,
      collected: false,
      hit_points: null,
      inventory: [],
      metadata: {}
    };
    
    // Convert common components to state properties
    const healthComp = this.getComponent('HealthComponent') || this.getComponent('Health');
    if (healthComp) {
      state.hit_points = {
        current: healthComp.currentHp || healthComp.current || 0,
        max: healthComp.maxHp || healthComp.max || 0
      };
    }
    
    const inventoryComp = this.getComponent('InventoryComponent') || this.getComponent('Inventory');
    if (inventoryComp && Array.isArray(inventoryComp.items)) {
      state.inventory = inventoryComp.items;
    }
    
    // Build entity_instance payload
    const data = {
      schema_version: '1.0.0',
      entity_instance_id: this.entity_instance_id || `temp-${this.id}`,
      entity_type: this.entity_type || 'creature',
      entity_ref: this.entity_ref || {
        content_type: 'creature',
        content_id: 'unknown',
        version: null
      },
      placement: this.placement || {
        room_id: 'unknown',
        hex: { q: 0, r: 0 },
        spawn_type: null
      },
      state: state,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };
    
    return data;
  }

  /**
   * Create entity from JSON data.
   * Supports both ECS format and entity_instance.schema.json format.
   * 
   * @param {object} data - Serialized entity data
   * @param {object} componentClasses - Map of component name to class constructor
   * @returns {Entity} Deserialized entity
   * @throws {Error} If data is invalid or missing required fields
   */
  static fromJSON(data, componentClasses) {
    if (!data || typeof data !== 'object') {
      throw new Error('Invalid data: must be an object');
    }
    
    // Detect format: entity_instance has entity_instance_id, ECS has id
    if (data.entity_instance_id && data.entity_type && data.entity_ref) {
      return Entity._fromEntityInstanceJSON(data, componentClasses);
    }
    
    // ECS format (backward compatible)
    if (!data.id) {
      throw new Error('Invalid data: missing required field "id"');
    }
    
    const entity = new Entity(data.id);
    entity.active = data.active !== undefined ? data.active : true;
    
    // Restore entity_instance properties if present
    if (data.entity_instance_id) {
      entity.entity_instance_id = data.entity_instance_id;
    }
    if (data.entity_type) {
      entity.entity_type = data.entity_type;
    }
    if (data.entity_ref) {
      entity.entity_ref = data.entity_ref;
    }
    if (data.placement) {
      entity.placement = data.placement;
    }

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
  
  /**
   * Create entity from entity_instance.schema.json format.
   * Converts structured state to components.
   * 
   * @private
   * @param {object} data - Entity instance in schema format
   * @param {object} componentClasses - Map of component name to class constructor
   * @returns {Entity} Entity with populated components
   * @throws {Error} If data is invalid
   */
  static _fromEntityInstanceJSON(data, componentClasses) {
    // Generate a numeric ID from UUID hash (for ECS compatibility)
    const numericId = Entity._hashUuidToId(data.entity_instance_id);
    
    const entity = new Entity(numericId, {
      entity_instance_id: data.entity_instance_id,
      entity_type: data.entity_type,
      entity_ref: data.entity_ref,
      placement: data.placement
    });
    
    // Set active state from entity_instance state
    if (data.state) {
      entity.active = data.state.active !== undefined ? data.state.active : true;
      
      // Convert state to components
      if (data.state.hit_points) {
        entity.addComponent('HealthComponent', {
          currentHp: data.state.hit_points.current,
          maxHp: data.state.hit_points.max
        });
      }
      
      if (data.state.inventory && Array.isArray(data.state.inventory)) {
        entity.addComponent('InventoryComponent', {
          items: data.state.inventory
        });
      }
      
      // Store additional state flags as MetadataComponent
      if (data.state.destroyed || data.state.disabled || data.state.hidden || data.state.collected) {
        entity.addComponent('StateComponent', {
          destroyed: data.state.destroyed || false,
          disabled: data.state.disabled || false,
          hidden: data.state.hidden || false,
          collected: data.state.collected || false
        });
      }
      
      // Store custom metadata
      if (data.state.metadata && Object.keys(data.state.metadata).length > 0) {
        entity.addComponent('MetadataComponent', data.state.metadata);
      }
    }
    
    // Add placement as PositionComponent if available
    if (data.placement && data.placement.hex) {
      entity.addComponent('PositionComponent', {
        q: data.placement.hex.q,
        r: data.placement.hex.r,
        room_id: data.placement.room_id
      });
    }
    
    return entity;
  }
  
  /**
   * Convert UUID to numeric ID via simple hash.
   * Used for entity_instance_id to ECS id conversion.
   * 
   * @private
   * @param {string} uuid - UUID string
   * @returns {number} Positive integer ID
   */
  static _hashUuidToId(uuid) {
    let hash = 0;
    for (let i = 0; i < uuid.length; i++) {
      const char = uuid.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash; // Convert to 32-bit integer
    }
    return Math.abs(hash) || 1; // Ensure positive
  }
}
