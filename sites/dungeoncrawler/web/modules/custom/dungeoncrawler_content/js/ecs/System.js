/**
 * @file
 * Base System class - contains logic that operates on entities.
 * 
 * Systems are the "S" in ECS (Entity-Component-System) architecture.
 * They contain the logic that operates on entities with specific components.
 * 
 * Key concepts:
 * - Systems process entities that have required components
 * - Systems run in priority order (lower numbers run first)
 * - Systems have lifecycle methods: init(), update(), destroy()
 * - Systems can be enabled/disabled without removal
 * 
 * Priority Guidelines:
 * - 0-10: Core systems (turn management, input handling)
 * - 20-40: Logic systems (AI, combat, abilities)
 * - 50-80: World systems (movement, physics, collision)
 * - 90-100: Presentation systems (animation, rendering, audio)
 * 
 * @example
 * class MySystem extends System {
 *   constructor(entityManager) {
 *     super(entityManager);
 *     this.priority = 50;
 *     this.requiredComponents = ['PositionComponent', 'VelocityComponent'];
 *   }
 *   
 *   update(deltaTime) {
 *     const entities = this.queryEntities(...this.requiredComponents);
 *     for (const entity of entities) {
 *       // Process entity
 *     }
 *   }
 * }
 */

export class System {
  /**
   * Create a new system.
   * 
   * @param {EntityManager} entityManager - Reference to entity manager
   * @throws {Error} If entityManager is not provided or invalid
   */
  constructor(entityManager) {
    if (!entityManager) {
      throw new Error('System requires an EntityManager instance');
    }
    
    if (typeof entityManager.getEntitiesWith !== 'function') {
      throw new Error('EntityManager must implement getEntitiesWith() method');
    }
    
    /**
     * Reference to the entity manager.
     * @type {EntityManager}
     * @protected
     */
    this.entityManager = entityManager;
    
    /**
     * Whether this system is currently enabled.
     * Disabled systems are skipped during update.
     * @type {boolean}
     * @protected
     */
    this.enabled = true;
    
    /**
     * System execution priority (lower numbers run first).
     * Range: 0-100
     * @type {number}
     * @protected
     */
    this.priority = 0;
    
    /**
     * Lifecycle state tracking.
     * Note: State transitions only work if subclasses call super.init()/super.destroy()
     * @type {'created'|'initialized'|'destroyed'}
     * @private
     */
    this._state = 'created';
    
    /**
     * Optional list of required component names for this system.
     * Subclasses can set this to use the queryEntities() helper.
     * @type {string[]}
     * @protected
     */
    this.requiredComponents = [];
  }

  /**
   * Initialize system (called once after construction).
   * Override in subclasses to perform setup.
   * 
   * Note: Subclasses should call super.init() to enable lifecycle state tracking.
   * 
   * @returns {void}
   */
  init() {
    this._state = 'initialized';
    // Override in subclasses for custom initialization
  }

  /**
   * Update system (called each frame/turn).
   * Only called if system is enabled.
   * Override in subclasses to implement system logic.
   * 
   * @param {number} deltaTime - Time since last update in milliseconds
   * @returns {void}
   * @throws {Error} If deltaTime is negative
   */
  update(deltaTime) {
    if (deltaTime < 0) {
      throw new Error('deltaTime cannot be negative');
    }
    // Override in subclasses for custom update logic
  }

  /**
   * Query entities with required components.
   * Subclasses can use this helper instead of implementing getEntities().
   * 
   * @param {...string} componentNames - Component names to query for
   * @returns {Entity[]} Array of entities with all specified components
   * @throws {Error} If no component names provided
   * @protected
   */
  queryEntities(...componentNames) {
    if (componentNames.length === 0) {
      throw new Error('queryEntities requires at least one component name');
    }
    
    return this.entityManager.getEntitiesWith(...componentNames);
  }

  /**
   * Get entities that this system operates on.
   * 
   * @deprecated Since refactoring DCC-0074. Use queryEntities() directly or set
   *   this.requiredComponents in your constructor for automatic querying.
   * 
   * Migration guide:
   * - Instead of: const entities = this.getEntities()
   * - Use: const entities = this.queryEntities('ComponentName1', 'ComponentName2')
   * - Or set: this.requiredComponents = ['ComponentName1', 'ComponentName2']
   *   and call: const entities = this.getEntities() (backward compatible)
   * 
   * @returns {Entity[]} Array of entities this system should process
   */
  getEntities() {
    // Legacy method for backward compatibility
    if (this.requiredComponents.length > 0) {
      return this.queryEntities(...this.requiredComponents);
    }
    return [];
  }

  /**
   * Enable this system.
   * Enabled systems have their update() method called each frame.
   * 
   * @returns {boolean} True if state changed, false if already enabled
   */
  enable() {
    if (this._state === 'destroyed') {
      console.warn(`Cannot enable destroyed system: ${this.constructor.name}`);
      return false;
    }
    
    const wasEnabled = this.enabled;
    this.enabled = true;
    return !wasEnabled;
  }

  /**
   * Disable this system.
   * Disabled systems are skipped during update but remain in memory.
   * 
   * @returns {boolean} True if state changed, false if already disabled
   */
  disable() {
    const wasEnabled = this.enabled;
    this.enabled = false;
    return wasEnabled;
  }

  /**
   * Check if system is enabled.
   * 
   * @returns {boolean} True if system is enabled
   */
  isEnabled() {
    return this.enabled;
  }

  /**
   * Check if system is initialized.
   * 
   * Note: Only accurate if subclass called super.init()
   * 
   * @returns {boolean} True if init() has been called successfully
   */
  isInitialized() {
    return this._state === 'initialized';
  }

  /**
   * Check if system is destroyed.
   * 
   * @returns {boolean} True if destroy() has been called
   */
  isDestroyed() {
    return this._state === 'destroyed';
  }

  /**
   * Get current system state.
   * 
   * @returns {'created'|'initialized'|'destroyed'} Current lifecycle state
   */
  getState() {
    return this._state;
  }

  /**
   * Set system priority.
   * Lower priority systems run first.
   * 
   * @param {number} priority - New priority value (0-100 recommended)
   * @throws {Error} If priority is not a finite number
   */
  setPriority(priority) {
    if (!Number.isFinite(priority)) {
      throw new Error('Priority must be a finite number');
    }
    
    this.priority = priority;
  }

  /**
   * Cleanup system resources (called on removal).
   * Override in subclasses to clean up resources.
   * 
   * Note: Subclasses should call super.destroy() to enable lifecycle state tracking.
   * 
   * @returns {void}
   */
  destroy() {
    this._state = 'destroyed';
    this.enabled = false;
    // Override in subclasses for custom cleanup
  }

  /**
   * Get system information for debugging.
   * 
   * @returns {object} System information
   */
  getInfo() {
    return {
      name: this.constructor.name,
      enabled: this.enabled,
      priority: this.priority,
      state: this._state,
      requiredComponents: this.requiredComponents
    };
  }
}
