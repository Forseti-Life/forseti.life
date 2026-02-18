/**
 * @file
 * Base Component class - pure data containers with no logic.
 * 
 * This implements the Component pattern from Entity-Component-System (ECS) architecture.
 * Components are pure data containers with no game logic - logic belongs in Systems.
 * 
 * ## Database Schema Alignment
 * 
 * Components serialize to JSON for storage in `character_data` column (dc_campaign_characters table).
 * Frequently-accessed properties are also extracted to "hot columns" for efficient queries:
 * 
 * - **Hot Columns** (indexed, searchable):
 *   - hp_current, hp_max (from StatsComponent)
 *   - armor_class (from StatsComponent)
 *   - position_q, position_r (from PositionComponent)
 *   - experience_points (from character state)
 *   - last_room_id (from location tracking)
 * 
 * - **JSON Blob** (complete state):
 *   - All component data serialized via toJSON()
 *   - Stored in `character_data` column for full reconstruction
 * 
 * Hot columns are synchronized by PHP CharacterStateService when state is saved.
 * 
 * @see /src/Service/CharacterStateService.php - Hot column extraction (lines 742-856)
 * @see dungeoncrawler_content.install - Schema definitions (lines 1282-1324)
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

// Sentinel value for circular reference detection
const CIRCULAR_REF = '[Circular]';

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
   * Default implementation serializes all own properties except functions.
   * Automatically handles nested objects and arrays. Circular references are detected
   * and excluded from the output with a console warning.
   * 
   * **Important**: The `type` field is always included in serialization output to enable
   * proper component reconstruction from stored data. The `type` field corresponds to
   * the component class name and is essential for polymorphic deserialization.
   * 
   * **Database Mapping**: Serialized output is stored in `character_data` JSON column.
   * Some properties may also be extracted to hot columns for efficient querying.
   * 
   * @returns {Object} Serialized component data (plain object with type field)
   */
  toJSON() {
    const data = {
      type: this.type  // Always include type field for proper deserialization
    };
    const seen = new WeakSet();
    // Add this component to seen set immediately to detect self-references
    seen.add(this);
    
    const serialize = (obj) => {
      // Handle primitives and null
      if (obj === null || typeof obj !== 'object') {
        return obj;
      }
      
      // Detect circular references - prevents infinite loops during serialization
      // Circular refs are replaced with a sentinel value and logged as warnings
      if (seen.has(obj)) {
        console.warn('Circular reference detected in component serialization');
        return CIRCULAR_REF;
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
          if (serialized !== CIRCULAR_REF) {
            result[key] = serialized;
          }
        }
      }
      return result;
    };
    
    // Serialize all component properties (including type already added above)
    for (const [key, value] of Object.entries(this)) {
      if (key !== 'type' && typeof value !== 'function') {
        const serialized = serialize(value);
        // Only include valid values (skip circular references)
        if (serialized !== CIRCULAR_REF) {
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
   * Validation should check:
   * - Required properties are present
   * - Property values are within valid ranges
   * - Data types are correct
   * - Relationships between properties are consistent
   * 
   * **Schema Conformance**: If this component maps to database hot columns,
   * validation should ensure data can be safely extracted for those columns.
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
