/**
 * @file
 * Base System class - contains logic that operates on entities.
 */

export class System {
  /**
   * Create a new system.
   * @param {EntityManager} entityManager - Reference to entity manager
   */
  constructor(entityManager) {
    this.entityManager = entityManager;
    this.enabled = true;
    this.priority = 0; // Lower numbers run first
  }

  /**
   * Initialize system (called once).
   * Override in subclasses.
   */
  init() {
    // Override in subclasses
  }

  /**
   * Update system (called each frame/turn).
   * Override in subclasses.
   * @param {number} deltaTime - Time since last update (ms)
   */
  update(deltaTime) {
    // Override in subclasses
  }

  /**
   * Get entities that this system operates on.
   * Override in subclasses to specify required components.
   * @returns {Entity[]} Array of entities
   */
  getEntities() {
    return [];
  }

  /**
   * Enable this system.
   */
  enable() {
    this.enabled = true;
  }

  /**
   * Disable this system.
   */
  disable() {
    this.enabled = false;
  }

  /**
   * Check if system is enabled.
   * @returns {boolean} True if enabled
   */
  isEnabled() {
    return this.enabled;
  }

  /**
   * Cleanup system (called on removal).
   * Override in subclasses.
   */
  destroy() {
    // Override in subclasses
  }
}
