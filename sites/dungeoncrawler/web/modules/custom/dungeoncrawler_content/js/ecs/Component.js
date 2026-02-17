/**
 * @file
 * Base Component class - pure data containers with no logic.
 * 
 * This implements the Component pattern from Entity-Component-System (ECS) architecture.
 * Components are pure data containers with no game logic - logic belongs in Systems.
 * 
 * @example
 * // Extend Component for custom components
 * class HealthComponent extends Component {
 *   constructor(hp = 100) {
 *     super();
 *     this.currentHp = hp;
 *     this.maxHp = hp;
 *   }
 * }
 * 
 * // Use with entities
 * const health = new HealthComponent(50);
 * const cloned = health.clone();
 * const json = health.toJSON();
 * const restored = HealthComponent.fromJSON(json);
 */

export class Component {
  /**
   * Create a new component.
   * Components should be extended by specific component types.
   * 
   * Subclasses should call super() and then initialize their data properties.
   * Do not add methods that contain game logic - use Systems for that.
   */
  constructor() {
    this.type = this.constructor.name;
  }

  /**
   * Serialize component to JSON.
   * Override in subclasses if you need custom serialization logic.
   * 
   * Default implementation serializes all own properties except 'type' and functions.
   * Handles nested objects and arrays, but may not handle circular references.
   * 
   * @returns {Object} Serialized component data (plain object)
   */
  toJSON() {
    const data = {};
    const seen = new WeakSet();
    // Add this component to seen set immediately to detect self-references
    seen.add(this);
    
    const serialize = (obj) => {
      // Handle primitives and null
      if (obj === null || typeof obj !== 'object') {
        return obj;
      }
      
      // Detect circular references
      if (seen.has(obj)) {
        console.warn('Circular reference detected in component serialization');
        return '[Circular]';
      }
      seen.add(obj);
      
      // Handle arrays
      if (Array.isArray(obj)) {
        return obj.map(item => serialize(item));
      }
      
      // Handle plain objects
      const result = {};
      for (const [key, value] of Object.entries(obj)) {
        if (typeof value !== 'function') {
          const serialized = serialize(value);
          // Only include valid values (skip circular references)
          if (serialized !== '[Circular]') {
            result[key] = serialized;
          }
        }
      }
      return result;
    };
    
    for (const [key, value] of Object.entries(this)) {
      if (key !== 'type' && typeof value !== 'function') {
        const serialized = serialize(value);
        // Only include valid values (skip circular references)
        if (serialized !== '[Circular]') {
          data[key] = serialized;
        }
      }
    }
    return data;
  }

  /**
   * Deserialize component from JSON.
   * Override in subclasses if you need custom deserialization logic.
   * 
   * Default implementation creates a new instance and assigns all properties.
   * Note: This preserves the prototype chain of the component class.
   * 
   * @param {Object} data - Serialized component data
   * @returns {Component} New component instance
   * @throws {Error} If data is invalid or malformed
   */
  static fromJSON(data) {
    if (!data || typeof data !== 'object') {
      throw new Error('fromJSON requires a valid object');
    }
    
    try {
      const component = new this();
      Object.assign(component, data);
      return component;
    } catch (error) {
      throw new Error(`Failed to deserialize component: ${error.message}`);
    }
  }

  /**
   * Clone this component.
   * Creates a deep copy by serializing and deserializing.
   * 
   * Override in subclasses if you need custom cloning logic or have
   * properties that don't serialize well (e.g., functions, circular refs).
   * 
   * @returns {Component} Cloned component instance
   */
  clone() {
    return this.constructor.fromJSON(this.toJSON());
  }

  /**
   * Validate component data.
   * Override in subclasses to implement validation logic.
   * 
   * @returns {boolean} True if component data is valid
   * @example
   * class HealthComponent extends Component {
   *   validate() {
   *     return this.currentHp >= 0 && this.currentHp <= this.maxHp;
   *   }
   * }
   */
  validate() {
    return true;
  }
}
