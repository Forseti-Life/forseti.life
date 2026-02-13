/**
 * @file
 * Base Component class - pure data containers with no logic.
 */

export class Component {
  /**
   * Create a new component.
   * Components should be extended by specific component types.
   */
  constructor() {
    this.type = this.constructor.name;
  }

  /**
   * Serialize component to JSON.
   * Override in subclasses if needed.
   * @returns {object} Serialized component data
   */
  toJSON() {
    const data = {};
    for (const [key, value] of Object.entries(this)) {
      if (key !== 'type' && typeof value !== 'function') {
        data[key] = value;
      }
    }
    return data;
  }

  /**
   * Deserialize component from JSON.
   * Override in subclasses.
   * @param {object} data - Serialized component data
   * @returns {Component} New component instance
   */
  static fromJSON(data) {
    const component = new this();
    Object.assign(component, data);
    return component;
  }

  /**
   * Clone this component.
   * @returns {Component} Cloned component
   */
  clone() {
    return this.constructor.fromJSON(this.toJSON());
  }
}
