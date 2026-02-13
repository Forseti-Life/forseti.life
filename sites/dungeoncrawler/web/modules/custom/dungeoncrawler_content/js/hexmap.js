/**
 * @file
 * Hex map rendering with PixiJS + ECS architecture.
 */

// Import ECS modules
import { EntityManager, PositionComponent, RenderComponent, IdentityComponent, EntityType, RenderSystem, MovementComponent, StatsComponent, MovementSystem, MovementMode, ActionsComponent, ActionType, ActionCost, CombatComponent, Team, TurnManagementSystem, CombatState, CombatSystem, AttackResult } from './ecs/index.js';

// Ensure Drupal and once are available
/* global Drupal, once, PIXI */

(function (Drupal, once) {
  'use strict';

  /**
   * UIManager - Handles all DOM interactions and UI updates.
   * Decouples business logic from DOM manipulation.
   */
  class UIManager {
    constructor() {
      this.elements = {};
      this.cacheElements();
    }

    /**
     * Cache frequently accessed DOM elements.
     */
    cacheElements() {
      this.elements = {
        hoveredHex: document.getElementById('hovered-hex'),
        selectedHex: document.getElementById('selected-hex'),
        currentTurn: document.getElementById('current-turn'),
        currentRound: document.getElementById('current-round'),
        initiativeList: document.getElementById('initiative-list'),
        combatControls: document.getElementById('combat-controls'),
        startCombatBtn: document.getElementById('start-combat'),
        endTurnBtn: document.getElementById('end-turn'),
        endCombatBtn: document.getElementById('end-combat'),
        initiativeTracker: document.getElementById('initiative-tracker'),
        entityInfoPanel: document.getElementById('entity-info-panel'),
        entityName: document.getElementById('entity-name'),
        entityType: document.getElementById('entity-type'),
        entityTeam: document.getElementById('entity-team'),
        entityHp: document.getElementById('entity-hp'),
        entityAc: document.getElementById('entity-ac'),
        entityActions: document.getElementById('entity-actions'),
        entityMovement: document.getElementById('entity-movement'),
        selectedObjectType: document.getElementById('selected-object-type'),
        zoomLevel: document.getElementById('zoom-level')
      };
    }

    /**
     * Update hovered hex display.
     */
    updateHoveredHex(q, r) {
      if (this.elements.hoveredHex) {
        this.elements.hoveredHex.textContent = q !== null ? `(${q}, ${r})` : 'None';
      }
    }

    /**
     * Update selected hex display.
     */
    updateSelectedHex(q, r) {
      if (this.elements.selectedHex) {
        this.elements.selectedHex.textContent = `(${q}, ${r})`;
      }
    }

    /**
     * Update current turn display.
     */
    updateCurrentTurn(name, actions, hasReaction) {
      if (this.elements.currentTurn) {
        let html = `<strong>${name}</strong>`;
        if (actions) {
          html += ` ${actions.getActionDisplay()}`;
          if (hasReaction) {
            html += ' ⚡';
          }
        }
        this.elements.currentTurn.innerHTML = html;
      }
    }

    /**
     * Update round display.
     */
    updateRound(roundNumber) {
      if (this.elements.currentRound) {
        this.elements.currentRound.textContent = `Round ${roundNumber}`;
      }
    }

    /**
     * Update initiative tracker.
     */
    updateInitiativeTracker(initiativeOrder) {
      if (!this.elements.initiativeList) return;

      let html = '';
      initiativeOrder.forEach((data) => {
        const activeClass = data.isCurrent ? 'active-turn' : '';
        const defeatedClass = data.isDefeated ? 'defeated' : '';
        html += `<div class="initiative-item ${activeClass} ${defeatedClass}">
          <span class="init-value">${data.initiative}</span>
          <span class="init-name">${data.name}</span>
        </div>`;
      });
      this.elements.initiativeList.innerHTML = html;
    }

    /**
     * Update combat controls visibility.
     */
    updateCombatControls(combatState) {
      const isInactive = (combatState === CombatState.INACTIVE || combatState === CombatState.ENDED);

      if (this.elements.startCombatBtn) {
        this.elements.startCombatBtn.style.display = isInactive ? 'inline-block' : 'none';
      }
      if (this.elements.endTurnBtn) {
        this.elements.endTurnBtn.style.display = isInactive ? 'none' : 'inline-block';
      }
      if (this.elements.endCombatBtn) {
        this.elements.endCombatBtn.style.display = isInactive ? 'none' : 'inline-block';
      }
      if (this.elements.initiativeTracker) {
        this.elements.initiativeTracker.style.display = isInactive ? 'none' : 'block';
      }
    }

    /**
     * Show entity info panel.
     */
    showEntityInfo(entity) {
      if (!this.elements.entityInfoPanel) return;

      this.elements.entityInfoPanel.style.display = 'block';

      const identity = entity.getComponent('IdentityComponent');
      const stats = entity.getComponent('StatsComponent');
      const combat = entity.getComponent('CombatComponent');
      const actions = entity.getComponent('ActionsComponent');
      const movement = entity.getComponent('MovementComponent');

      if (this.elements.entityName) {
        this.elements.entityName.textContent = identity?.name || 'Unknown';
      }
      if (this.elements.entityType) {
        this.elements.entityType.textContent = identity?.entityType || '-';
      }
      if (this.elements.entityTeam) {
        this.elements.entityTeam.textContent = combat?.team || '-';
      }
      if (this.elements.entityHp) {
        this.elements.entityHp.textContent = stats ? `${stats.currentHp}/${stats.maxHp}` : '-';
      }
      if (this.elements.entityAc) {
        this.elements.entityAc.textContent = stats?.ac || '-';
      }
      if (this.elements.entityActions) {
        this.elements.entityActions.textContent = actions ? actions.getActionDisplay() : '-';
      }
      if (this.elements.entityMovement) {
        this.elements.entityMovement.textContent = movement ?
          `${movement.movementRemaining}/${movement.movementSpeed} ft` : '-';
      }
    }

    /**
     * Hide entity info panel.
     */
    hideEntityInfo() {
      if (this.elements.entityInfoPanel) {
        this.elements.entityInfoPanel.style.display = 'none';
      }
    }

    /**
     * Update selected object type display.
     */
    updateSelectedObjectType(type) {
      if (this.elements.selectedObjectType) {
        const displayName = type ? type.charAt(0).toUpperCase() + type.slice(1) : 'None';
        this.elements.selectedObjectType.textContent = `Selected: ${displayName}`;
      }
    }

    /**
     * Update zoom level display.
     */
    updateZoomLevel(scale) {
      if (this.elements.zoomLevel) {
        const zoomPercent = Math.round(scale * 100);
        this.elements.zoomLevel.textContent = `${zoomPercent}%`;
      }
    }
  }

  /**
   * StateManager - Centralized state management.
   * Provides a single source of truth for application state.
   */
  class StateManager {
    constructor() {
      this.state = {
        // Selection state
        selectedEntity: null,
        selectedHex: null,
        hoveredHex: null,
        selectedObjectType: null,
        
        // Movement state
        movementRange: null,
        movementRangeOverlay: null,
        
        // Combat state
        combatActive: false,
        attackTarget: null,
        
        // Drag state
        draggedObject: null,
        
        // Flags
        assetsLoaded: false,
        showCoordinates: false,
        showGrid: true
      };
      
      this.listeners = {};
    }

    /**
     * Get state value.
     */
    get(key) {
      return this.state[key];
    }

    /**
     * Set state value and notify listeners.
     */
    set(key, value) {
      const oldValue = this.state[key];
      this.state[key] = value;
      
      // Notify listeners
      if (this.listeners[key]) {
        this.listeners[key].forEach(callback => callback(value, oldValue));
      }
    }

    /**
     * Subscribe to state changes.
     */
    subscribe(key, callback) {
      if (!this.listeners[key]) {
        this.listeners[key] = [];
      }
      this.listeners[key].push(callback);
      
      // Return unsubscribe function
      return () => {
        this.listeners[key] = this.listeners[key].filter(cb => cb !== callback);
      };
    }

    /**
     * Reset all state to defaults.
     */
    reset() {
      this.state = {
        selectedEntity: null,
        selectedHex: null,
        hoveredHex: null,
        selectedObjectType: null,
        movementRange: null,
        movementRangeOverlay: null,
        combatActive: false,
        attackTarget: null,
        draggedObject: null,
        assetsLoaded: false,
        showCoordinates: false,
        showGrid: true
      };
    }
  }

  /**
   * Hex map behavior.
   */
  Drupal.behaviors.hexMap = {
    // Configuration
    config: {
      hexSize: 30,
      gridWidth: 20,
      gridHeight: 20,
      minZoom: 0.5,
      maxZoom: 3.0,
      defaultWidth: 800,
      defaultHeight: 600,
      backgroundColor: 0x1a1a2e
    },
    
    // PixiJS containers
    app: null,
    hexContainer: null,
    gridContainer: null,
    objectContainer: null,
    uiContainer: null,
    
    // Managers
    uiManager: null,
    stateManager: null,
    
    // ECS architecture
    entityManager: null,
    renderSystem: null,
    movementSystem: null,
    turnManagementSystem: null,
    combatSystem: null,
    
    // Cleanup tracking
    eventListeners: [],
    stageListeners: [],
    tickerCallbacks: [],
    stateSubscriptions: [],

    attach: function (context, settings) {
      const container = once('hexmap-init', '#hexmap-canvas-container', context);
      
      if (container.length === 0) {
        return;
      }

      // Initialize managers
      this.uiManager = new UIManager();
      this.stateManager = new StateManager();
      this.setupStateSubscriptions();

      this.initPixiApp(container[0]);
      this.initECS(); // Initialize ECS architecture
      this.generateHexGrid();
      this.setupControls();
      this.setupInteraction();
      
      // Start game loop and track for cleanup
      const updateCallback = (delta) => this.update(delta);
      this.app.ticker.add(updateCallback);
      this.tickerCallbacks.push(updateCallback);
    },
    
    /**
     * Detach behavior and cleanup resources.
     */
    detach: function (context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      
      console.log('Cleaning up hexmap resources...');
      
      // Remove ticker callbacks
      this.tickerCallbacks.forEach(callback => {
        if (this.app && this.app.ticker) {
          this.app.ticker.remove(callback);
        }
      });
      this.tickerCallbacks = [];
      
      // Remove event listeners
      this.eventListeners.forEach(({ element, event, handler }) => {
        element.removeEventListener(event, handler);
      });
      this.eventListeners = [];

      // Remove stage listeners
      this.stageListeners.forEach(({ event, handler }) => {
        if (this.app && this.app.stage) {
          this.app.stage.off(event, handler);
        }
      });
      this.stageListeners = [];

      // Unsubscribe state listeners
      this.stateSubscriptions.forEach(unsubscribe => unsubscribe());
      this.stateSubscriptions = [];
      
      // Cleanup ECS systems
      if (this.entityManager) {
        this.entityManager.removeAllEntities();
        this.entityManager = null;
      }
      
      // Cleanup PixiJS
      const movementRangeOverlay = this.stateManager ? this.stateManager.get('movementRangeOverlay') : null;
      if (movementRangeOverlay) {
        movementRangeOverlay.destroy();
        this.stateManager.set('movementRangeOverlay', null);
      }
      
      if (this.app) {
        this.app.destroy(true, { children: true, texture: false, baseTexture: false });
        this.app = null;
      }
      
      // Reset state
      if (this.stateManager) {
        this.stateManager.reset();
      }
      
      console.log('Hexmap cleanup complete');
    },
    
    /**
     * Initialize ECS architecture.
     */
    initECS: function () {
      // Store self reference for callbacks
      const self = this;
      
      // Create entity manager
      this.entityManager = new EntityManager();
      
      // Create render system
      this.renderSystem = new RenderSystem(
        this.entityManager,
        this.app,
        {
          hex: this.hexContainer,
          object: this.objectContainer,
          ui: this.uiContainer
        }
      );
      this.renderSystem.setHexSize(this.config.hexSize);
      this.entityManager.addSystem(this.renderSystem);
      
      // Create movement system
      this.movementSystem = new MovementSystem(this.entityManager);
      this.entityManager.addSystem(this.movementSystem);
      
      // Create combat system
      this.combatSystem = new CombatSystem(this.entityManager);
      this.entityManager.addSystem(this.combatSystem);
      
      // Set up combat callbacks
      this.combatSystem.onAttack(function(attackData) {
        self.onAttackPerformed(attackData);
      });
      this.combatSystem.onDamage(function(damageData) {
        self.onDamageDealt(damageData);
      });
      
      // Create turn management system
      this.turnManagementSystem = new TurnManagementSystem(this.entityManager);
      this.entityManager.addSystem(this.turnManagementSystem);
      
      // Set up turn management callbacks
      this.turnManagementSystem.onTurnChange(function(entity, turnIndex, totalTurns) {
        self.onTurnChange(entity, turnIndex, totalTurns);
      });
      this.turnManagementSystem.onRoundChange(function(roundNumber) {
        self.onRoundChange(roundNumber);
      });
      this.turnManagementSystem.onCombatStateChange(function(combatState) {
        self.onCombatStateChange(combatState);
      });
      
      console.log('ECS initialized');
    },
    
    /**
     * Game loop update.
     * @param {number} delta - Time delta from PixiJS ticker
     */
    update: function (delta) {
      // Update all ECS systems
      if (this.entityManager) {
        this.entityManager.update(delta * 16.67); // Convert to milliseconds
      }
    },

    /**
     * Setup state subscriptions for reactive UI updates.
     */
    setupStateSubscriptions: function () {
      this.stateSubscriptions.push(
        this.stateManager.subscribe('selectedObjectType', (value) => {
          this.uiManager.updateSelectedObjectType(value);
        })
      );

      this.stateSubscriptions.push(
        this.stateManager.subscribe('showGrid', (value) => {
          if (this.gridContainer) {
            this.gridContainer.visible = value;
          }
        })
      );

      this.uiManager.updateSelectedObjectType(this.stateManager.get('selectedObjectType'));
    },

    /**
     * Set world layer position for all render containers.
     * @param {number} x - X coordinate
     * @param {number} y - Y coordinate
     */
    setWorldPosition: function (x, y) {
      this.hexContainer.x = x;
      this.hexContainer.y = y;
      this.gridContainer.x = x;
      this.gridContainer.y = y;
      this.objectContainer.x = x;
      this.objectContainer.y = y;
      this.uiContainer.x = x;
      this.uiContainer.y = y;
    },

    /**
     * Set world layer scale for all render containers.
     * @param {number} scale - Uniform scale value
     */
    setWorldScale: function (scale) {
      this.hexContainer.scale.set(scale);
      this.gridContainer.scale.set(scale);
      this.objectContainer.scale.set(scale);
      this.uiContainer.scale.set(scale);
    },

    /**
     * Clear all ECS entities and related overlays/state.
     */
    clearEntities: function () {
      if (!this.entityManager) {
        return;
      }

      this.deselectEntity();
      this.entityManager.removeAllEntities();
      this.uiManager.hideEntityInfo();
      this.uiManager.updateCurrentTurn('-', null, false);
      this.uiManager.updateInitiativeTracker([]);
      console.log('Cleared all ECS entities');
    },
    
    /**
     * Turn change callback.
     * @param {Entity} entity - Entity whose turn it is
     * @param {number} turnIndex - Current turn index
     * @param {number} totalTurns - Total turns in round
     */
    onTurnChange: function (entity, turnIndex, totalTurns) {
      const identity = entity.getComponent('IdentityComponent');
      const actions = entity.getComponent('ActionsComponent');
      const name = identity ? identity.name : `Entity ${entity.id}`;
      
      console.log(`Turn change: ${name} (${turnIndex + 1}/${totalTurns})`);
      
      // Update UI via UIManager
      this.uiManager.updateCurrentTurn(name, actions, actions?.hasReactionAvailable());
      this.uiManager.updateInitiativeTracker(this.turnManagementSystem.getInitiativeOrder());
      
      // Auto-select entity on their turn (if player controlled)
      const combat = entity.getComponent('CombatComponent');
      if (combat && combat.isPlayerTeam()) {
        this.selectEntity(entity);
      }
    },
    
    /**
     * Round change callback.
     * @param {number} roundNumber - New round number
     */
    onRoundChange: function (roundNumber) {
      console.log(`Round ${roundNumber} started`);
      this.uiManager.updateRound(roundNumber);
    },
    
    /**
     * Combat state change callback.
     * @param {string} combatState - New combat state
     */
    onCombatStateChange: function (combatState) {
      console.log(`Combat state: ${combatState}`);
      this.stateManager.set('combatActive', combatState === CombatState.IN_PROGRESS || combatState === CombatState.ROLLING_INITIATIVE);
      
      // Update UI
      this.uiManager.updateCombatControls(combatState);
    },

    /**
     * Initialize PixiJS application.
     */
    initPixiApp: function (container) {
      // Create PixiJS application
      this.app = new PIXI.Application({
        width: container.clientWidth || this.config.defaultWidth,
        height: container.clientHeight || this.config.defaultHeight,
        backgroundColor: this.config.backgroundColor,
        antialias: true,
        resolution: window.devicePixelRatio || 1,
        autoDensity: true,
      });

      container.appendChild(this.app.view);

      // Create containers for layers
      this.hexContainer = new PIXI.Container();
      this.gridContainer = new PIXI.Container();
      this.objectContainer = new PIXI.Container();
      this.uiContainer = new PIXI.Container();
      
      // Add layers in order: hexes (terrain), grid (coords), objects (sprites), ui (overlays)
      this.app.stage.addChild(this.hexContainer);
      this.app.stage.addChild(this.gridContainer);
      this.app.stage.addChild(this.objectContainer);
      this.app.stage.addChild(this.uiContainer);

      // Center the view
      this.hexContainer.x = this.app.screen.width / 2;
      this.hexContainer.y = this.app.screen.height / 2;
      this.gridContainer.x = this.hexContainer.x;
      this.gridContainer.y = this.hexContainer.y;
      this.uiContainer.x = this.hexContainer.x;
      this.uiContainer.y = this.hexContainer.y;
      this.objectContainer.x = this.hexContainer.x;
      this.objectContainer.y = this.hexContainer.y;

      // Enable interactivity on stage
      this.app.stage.interactive = true;
      this.app.stage.hitArea = this.app.screen;

      console.log('PixiJS initialized');
    },

    /**
     * Generate hexagonal grid.
     */
    generateHexGrid: function () {
      // Clear existing hexes
      this.hexContainer.removeChildren();
      this.gridContainer.removeChildren();

      const hexSize = this.config.hexSize;
      const width = this.config.gridWidth;
      const height = this.config.gridHeight;

      // Calculate hex dimensions
      const hexWidth = hexSize * 2;
      const hexHeight = Math.sqrt(3) * hexSize;

      // Generate grid (flat-top orientation)
      for (let q = -Math.floor(width / 2); q < Math.ceil(width / 2); q++) {
        for (let r = -Math.floor(height / 2); r < Math.ceil(height / 2); r++) {
          this.createHex(q, r, hexSize);
        }
      }

      console.log(`Generated ${width}x${height} hex grid`);
    },

    /**
     * Create a single hex.
     */
    createHex: function (q, r, size) {
      const hex = new PIXI.Graphics();
      const pos = this.axialToPixel(q, r, size);

      // Draw hex shape
      hex.beginFill(0x2d3748);
      hex.lineStyle(1, 0x4a5568, 1);
      
      // Draw hexagon (flat-top)
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = size * Math.cos(angle);
        const y = size * Math.sin(angle);
        
        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();

      // Position hex
      hex.x = pos.x;
      hex.y = pos.y;

      // Store hex data
      hex.hexData = { q, r };

      // Make interactive
      hex.interactive = true;
      hex.buttonMode = true;

      // Event handlers
      hex.on('pointerover', () => this.onHexHover(hex));
      hex.on('pointerout', () => this.onHexOut(hex));
      hex.on('pointerdown', () => this.onHexClick(hex));

      this.hexContainer.addChild(hex);

      // Add coordinates text if enabled
      if (this.stateManager.get('showCoordinates')) {
        this.addHexCoordinates(hex, q, r, pos);
      }
    },

    /**
     * Add coordinate text to hex.
     */
    addHexCoordinates: function (hex, q, r, pos) {
      const text = new PIXI.Text(`${q},${r}`, {
        fontFamily: 'Arial',
        fontSize: 10,
        fill: 0x718096,
        align: 'center',
      });
      
      text.anchor.set(0.5);
      text.x = pos.x;
      text.y = pos.y;
      
      this.gridContainer.addChild(text);
    },

    /**
     * Convert axial coordinates (q, r) to pixel position.
     */
    axialToPixel: function (q, r, size) {
      const x = size * (3 / 2 * q);
      const y = size * (Math.sqrt(3) / 2 * q + Math.sqrt(3) * r);
      return { x, y };
    },

    /**
     * Convert pixel position to axial coordinates.
     */
    pixelToAxial: function (x, y, size) {
      const q = (2 / 3 * x) / size;
      const r = (-1 / 3 * x + Math.sqrt(3) / 3 * y) / size;
      return this.roundAxial(q, r);
    },

    /**
     * Round fractional axial coordinates to nearest hex.
     */
    roundAxial: function (q, r) {
      const s = -q - r;
      
      let rq = Math.round(q);
      let rr = Math.round(r);
      let rs = Math.round(s);
      
      const qDiff = Math.abs(rq - q);
      const rDiff = Math.abs(rr - r);
      const sDiff = Math.abs(rs - s);
      
      if (qDiff > rDiff && qDiff > sDiff) {
        rq = -rr - rs;
      } else if (rDiff > sDiff) {
        rr = -rq - rs;
      }
      
      return { q: rq, r: rr };
    },

    /**
     * Hex hover event.
     */
    onHexHover: function (hex) {
      // Highlight hex
      hex.clear();
      hex.beginFill(0x4a5568);
      hex.lineStyle(2, 0xfbbf24, 1);
      
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = this.config.hexSize * Math.cos(angle);
        const y = this.config.hexSize * Math.sin(angle);
        
        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();

      this.stateManager.set('hoveredHex', hex);
      
      // Update UI
      const { q, r } = hex.hexData;
      this.uiManager.updateHoveredHex(q, r);
    },

    /**
     * Hex out event.
     */
    onHexOut: function (hex) {
      // Reset hex appearance (unless it's selected)
      if (this.stateManager.get('selectedHex') !== hex) {
        hex.clear();
        hex.beginFill(0x2d3748);
        hex.lineStyle(1, 0x4a5568, 1);
        
        for (let i = 0; i < 6; i++) {
          const angle = (Math.PI / 3) * i;
          const x = this.config.hexSize * Math.cos(angle);
          const y = this.config.hexSize * Math.sin(angle);
          
          if (i === 0) {
            hex.moveTo(x, y);
          } else {
            hex.lineTo(x, y);
          }
        }
        hex.closePath();
        hex.endFill();
      }

      this.stateManager.set('hoveredHex', null);
      this.uiManager.updateHoveredHex(null, null);
    },

    /**
     * Hex click event.
     */
    onHexClick: function (hex) {
      const { q, r } = hex.hexData;
      
      // Mode 1: Object placement mode
      const selectedObjectType = this.stateManager.get('selectedObjectType');
      if (selectedObjectType) {
        // Map object type to EntityType
        let entityType;
        let name;
        switch (selectedObjectType) {
          case 'creature':
            entityType = EntityType.CREATURE;
            name = 'Creature';
            break;
          case 'item':
            entityType = EntityType.ITEM;
            name = 'Item';
            break;
          case 'treasure':
            entityType = EntityType.TREASURE;
            name = 'Treasure';
            break;
          case 'obstacle':
            entityType = EntityType.OBSTACLE;
            name = 'Obstacle';
            break;
          default:
            entityType = EntityType.CREATURE;
            name = 'Unknown';
        }
        
        // Create entity using ECS (components are auto-added based on type)
        this.createEntityObject(q, r, entityType, name, null);
        
        return;
      }
      
      // Mode 2: Check if clicking on an entity
      const entitiesAtPos = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
      const selectedEntity = this.stateManager.get('selectedEntity');
      
      for (const entity of entitiesAtPos) {
        const pos = entity.getComponent('PositionComponent');
        if (pos.q === q && pos.r === r) {
          // Check if this is an attack action (selected entity + hostile target)
          if (selectedEntity && entity.id !== selectedEntity.id) {
            const attackerCombat = selectedEntity.getComponent('CombatComponent');
            const targetCombat = entity.getComponent('CombatComponent');
            
            if (attackerCombat && targetCombat && attackerCombat.isHostileTo(targetCombat)) {
              // Attempt attack
              this.performAttack(selectedEntity, entity);
              return;
            }
          }
          
          // Otherwise select the entity if it has MovementComponent
          if (entity.hasComponent('MovementComponent')) {
            this.selectEntity(entity);
            return;
          }
        }
      }
      
      // Mode 3: Move selected entity
      const movementRange = this.stateManager.get('movementRange');
      if (selectedEntity && movementRange) {
        const hexKey = `${q}_${r}`;
        if (movementRange.has(hexKey)) {
          // Try to move entity
          const success = this.movementSystem.moveEntity(selectedEntity, q, r);
          if (success) {
            console.log(`Moved entity to (${q}, ${r})`);
            // Refresh movement range after move
            this.showMovementRange(selectedEntity);
          }
          return;
        }
      }
      
      // Mode 4: Default hex selection
      // Deselect previous hex
      const previousSelectedHex = this.stateManager.get('selectedHex');
      if (previousSelectedHex) {
        this.onHexOut(previousSelectedHex);
      }

      // Select this hex
      this.stateManager.set('selectedHex', hex);
      
      hex.clear();
      hex.beginFill(0x3b82f6);
      hex.lineStyle(3, 0x60a5fa, 1);
      
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = this.config.hexSize * Math.cos(angle);
        const y = this.config.hexSize * Math.sin(angle);
        
        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();

      // Update UI
      this.uiManager.updateSelectedHex(q, r);
      
      console.log('Selected hex:', q, r);
    },
    
    /**
     * Create a game entity using ECS architecture.
     * @param {number} q - Hex Q coordinate
     * @param {number} r - Hex R coordinate
     * @param {string} entityType - Entity type from EntityType enum
     * @param {string} name - Entity name
     * @param {string} spriteKey - Optional sprite key
     * @returns {Entity} Created entity
     */
    createEntityObject: function (q, r, entityType, name, spriteKey = null) {
      // Check if entity already exists at this position
      const existingEntities = this.entityManager.getEntitiesWith('PositionComponent');
      for (const entity of existingEntities) {
        const pos = entity.getComponent('PositionComponent');
        if (pos.q === q && pos.r === r) {
          // Remove existing entity
          this.entityManager.removeEntity(entity.id);
          break;
        }
      }
      
      // Create new entity
      const entity = this.entityManager.createEntity();
      
      //Add core components
      entity.addComponent('PositionComponent', new PositionComponent(q, r));
      entity.addComponent('IdentityComponent', new IdentityComponent(name, entityType));
      entity.addComponent('RenderComponent', new RenderComponent(spriteKey));
      
      // Add components based on entity type
      if (entityType === EntityType.CREATURE || entityType === EntityType.PLAYER_CHARACTER || entityType === EntityType.NPC) {
        // Add stats
        const stats = new StatsComponent({ 
          speed: 30, 
          maxHp: 20,
          perception: 0
        });
        entity.addComponent('StatsComponent', stats);
        
        // Add movement
        const movement = new MovementComponent(30);
        entity.addComponent('MovementComponent', movement);
        
        // Add actions (3-action economy)
        const actions = new ActionsComponent(3);
        entity.addComponent('ActionsComponent', actions);
        
        // Add combat
        const team = entityType === EntityType.PLAYER_CHARACTER ? Team.PLAYER : Team.ENEMY;
        const combat = new CombatComponent({ 
          team: team,
          initiativeBonus: 0
        });
        entity.addComponent('CombatComponent', combat);
      }
      
      console.log(`Created entity "${name}" (${entityType}) at (${q}, ${r})`);
      return entity;
    },
    
    /**
     * Select an entity for movement.
     * @param {Entity} entity - Entity to select
     */
    selectEntity: function (entity) {
      // Deselect previous entity
      const previousEntity = this.stateManager.get('selectedEntity');
      if (previousEntity) {
        this.deselectEntity();
      }
      
      this.stateManager.set('selectedEntity', entity);
      
      // Check if entity can move
      const movement = entity.getComponent('MovementComponent');
      if (!movement) {
        console.warn('Entity has no MovementComponent');
        return;
      }
      
      // Calculate and show movement range
      this.showMovementRange(entity);
      
      // Update UI
      const identity = entity.getComponent('IdentityComponent');
      const name = identity ? identity.name : `Entity ${entity.id}`;
      console.log(`Selected entity: ${name}`);
      
      // Highlight selected entity (could add visual feedback on sprite)
      const render = entity.getComponent('RenderComponent');
      if (render && render.sprite) {
        render.sprite.tint = 0x60a5fa; // Blue tint
      }
      
      // Show entity info panel via UIManager
      this.uiManager.showEntityInfo(entity);
    },
    
    /**
     * Deselect currently selected entity.
     */
    deselectEntity: function () {
      const selectedEntity = this.stateManager.get('selectedEntity');
      if (!selectedEntity) {
        return;
      }
      
      // Remove tint from sprite
      const render = selectedEntity.getComponent('RenderComponent');
      if (render && render.sprite) {
        render.sprite.tint = 0xffffff; // Reset to white
      }
      
      this.stateManager.set('selectedEntity', null);
      this.hideMovementRange();
      
      // Hide entity info panel
      this.uiManager.hideEntityInfo();
      
      console.log('Entity deselected');
    },
    
    /**
     * Show movement range overlay for entity.
     * @param {Entity} entity - Entity to show range for
     */
    showMovementRange: function (entity) {
      // Clear existing overlay
      this.hideMovementRange();
      
      // Calculate movement range
      const movementRange = this.movementSystem.calculateMovementRange(entity);
      this.stateManager.set('movementRange', movementRange);
      
      // Create overlay graphics
      const movementRangeOverlay = new PIXI.Graphics();
      
      // Draw reachable hexes
      movementRange.forEach(hexKey => {
        const [q, r] = hexKey.split('_').map(Number);
        const pos = this.axialToPixel(q, r, this.config.hexSize);
        
        movementRangeOverlay.beginFill(0x3b82f6, 0.2); // Blue with transparency
        movementRangeOverlay.lineStyle(2, 0x60a5fa, 0.5);
        
        for (let i = 0; i < 6; i++) {
          const angle = (Math.PI / 3) * i;
          const x = pos.x + this.config.hexSize * Math.cos(angle);
          const y = pos.y + this.config.hexSize * Math.sin(angle);
          
          if (i === 0) {
            movementRangeOverlay.moveTo(x, y);
          } else {
            movementRangeOverlay.lineTo(x, y);
          }
        }
        movementRangeOverlay.closePath();
        movementRangeOverlay.endFill();
      });
      
      this.uiContainer.addChild(movementRangeOverlay);
      this.stateManager.set('movementRangeOverlay', movementRangeOverlay);
    },
    
    /**
     * Hide movement range overlay.
     */
    hideMovementRange: function () {
      const movementRangeOverlay = this.stateManager.get('movementRangeOverlay');
      if (movementRangeOverlay) {
        this.uiContainer.removeChild(movementRangeOverlay);
        movementRangeOverlay.destroy();
        this.stateManager.set('movementRangeOverlay', null);
      }
      this.stateManager.set('movementRange', null);
    },
    
    /**
     * Start combat encounter.
     */
    startCombat: function () {
      console.log('Starting combat...');
      this.turnManagementSystem.startCombat();
    },
    
    /**
     * End current turn.
     */
    endTurn: function () {
      console.log('Ending turn...');
      this.turnManagementSystem.endTurn();
    },
    
    /**
     * End combat encounter.
     */
    endCombat: function () {
      console.log('Ending combat...');
      this.turnManagementSystem.endCombat();
      this.deselectEntity();
    },

    /**
     * Perform attack action.
     * @param {Entity} attacker - Attacking entity
     * @param {Entity} target - Target entity
     */
    performAttack: function (attacker, target) {
      // Check if it's the attacker's turn (if combat is active)
      const combatActive = this.stateManager.get('combatActive');
      if (combatActive && this.turnManagementSystem) {
        if (!this.turnManagementSystem.isEntityTurn(attacker)) {
          console.warn('Not your turn!');
          return;
        }
      }
      
      // Attempt attack via combat system
      const attackData = this.combatSystem.attack(attacker, target);
      
      if (attackData) {
        console.log('Attack executed successfully');
        
        // Refresh UI after attack
        const actions = attacker.getComponent('ActionsComponent');
        const identity = attacker.getComponent('IdentityComponent');
        const name = identity ? identity.name : `Entity ${attacker.id}`;
        
        if (actions) {
          this.uiManager.updateCurrentTurn(name, actions, actions.hasReactionAvailable());
        }
      }
    },
    
    /**
     * Callback when attack is performed.
     * @param {Object} attackData - Attack data
     */
    onAttackPerformed: function (attackData) {
      const attackerName = attackData.attacker.getComponent('IdentityComponent')?.name || 'Attacker';
      const targetName = attackData.target.getComponent('IdentityComponent')?.name || 'Target';
      
      let message = `${attackerName} attacks ${targetName}: `;
      
      if (attackData.result === AttackResult.CRITICAL_HIT) {
        message += `💥 CRITICAL HIT! `;
      } else if (attackData.result === AttackResult.HIT) {
        message += `✓ Hit! `;
      } else if (attackData.result === AttackResult.MISS) {
        message += `✗ Miss! `;
      } else if (attackData.result === AttackResult.CRITICAL_MISS) {
        message += `❌ Critical Miss! `;
      }
      
      if (attackData.damage > 0) {
        message += `${attackData.damage} damage`;
      }
      
      console.log(message);
      
      // Could add floating damage numbers or attack animations here
    },
    
    /**
     * Callback when damage is dealt.
     * @param {Object} damageData - Damage data
     */
    onDamageDealt: function (damageData) {
      const targetName = damageData.target.getComponent('IdentityComponent')?.name || 'Target';
      
      console.log(`${targetName}: ${damageData.remainingHp}/${damageData.maxHp} HP`);
      
      if (damageData.defeated) {
        console.log(`${targetName} has been defeated!`);
        
        // Update sprite to show defeated state (could add death animation)
        const render = damageData.target.getComponent('RenderComponent');
        if (render && render.sprite) {
          render.sprite.alpha = 0.5; // Make semi-transparent
        }
      }
    },

    /**
     * Load game assets.
     */
    loadAssets: async function (assetList) {
      if (this.stateManager && this.stateManager.get('assetsLoaded')) return;
      
      console.log('Loading assets...');
      
      try {
        for (const asset of assetList) {
          await PIXI.Assets.load(asset);
        }
        if (this.stateManager) {
          this.stateManager.set('assetsLoaded', true);
        }
        console.log('Assets loaded successfully');
      } catch (error) {
        console.error('Error loading assets:', error);
      }
    },

    /**
     * Setup control handlers.
     */
    setupControls: function () {
      const self = this;

      // Helper to track event listeners for cleanup
      const addTrackedListener = (element, event, handler) => {
        if (element) {
          element.addEventListener(event, handler);
          self.eventListeners.push({ element, event, handler });
        }
      };

      // Grid size selector
      const gridSizeSelect = document.getElementById('grid-size');
      addTrackedListener(gridSizeSelect, 'change', function (e) {
        const size = e.target.value;
        switch (size) {
          case 'small':
            self.config.gridWidth = 10;
            self.config.gridHeight = 10;
            break;
          case 'medium':
            self.config.gridWidth = 20;
            self.config.gridHeight = 20;
            break;
          case 'large':
            self.config.gridWidth = 40;
            self.config.gridHeight = 40;
            break;
        }
        self.generateHexGrid();
      });

      // Hex size slider
      const hexSizeSlider = document.getElementById('hex-size');
      addTrackedListener(hexSizeSlider, 'input', function (e) {
        self.config.hexSize = parseInt(e.target.value);
        const hexSizeValue = document.getElementById('hex-size-value');
        if (hexSizeValue) {
          hexSizeValue.textContent = self.config.hexSize + 'px';
        }
        self.generateHexGrid();
      });

      // Toggle coordinates
      const toggleCoords = document.getElementById('toggle-coordinates');
      addTrackedListener(toggleCoords, 'click', function () {
        const current = self.stateManager.get('showCoordinates');
        self.stateManager.set('showCoordinates', !current);
        self.generateHexGrid();
      });

      // Toggle grid lines
      const toggleGrid = document.getElementById('toggle-grid');
      addTrackedListener(toggleGrid, 'click', function () {
        const current = self.stateManager.get('showGrid');
        const newValue = !current;
        self.stateManager.set('showGrid', newValue);
      });

      // Reset view
      const resetView = document.getElementById('reset-view');
      addTrackedListener(resetView, 'click', function () {
        self.setWorldScale(1);
        self.setWorldPosition(self.app.screen.width / 2, self.app.screen.height / 2);
        self.uiManager.updateZoomLevel(1);
      });

      // Object type buttons
      document.querySelectorAll('.btn-object').forEach(function (btn) {
        const clickHandler = function () {
          // Remove active class from all buttons
          document.querySelectorAll('.btn-object').forEach(b => b.classList.remove('active'));
          
          // Set active button
          btn.classList.add('active');
          const objectType = btn.dataset.type;
          self.stateManager.set('selectedObjectType', objectType);
          
          console.log('Selected object type:', objectType);
        };
        addTrackedListener(btn, 'click', clickHandler);
      });

      // Clear all objects
      const clearObjects = document.getElementById('clear-objects');
      addTrackedListener(clearObjects, 'click', function () {
        self.clearEntities();
        console.log('Cleared all objects');
      });
      
      // Deselect entity button
      const deselectBtn = document.getElementById('deselect-entity');
      addTrackedListener(deselectBtn, 'click', function () {
        self.deselectEntity();
      });
      
      // Combat controls
      const startCombatBtn = document.getElementById('start-combat');
      addTrackedListener(startCombatBtn, 'click', function () {
        self.startCombat();
      });
      
      const endTurnBtn = document.getElementById('end-turn');
      addTrackedListener(endTurnBtn, 'click', function () {
        self.endTurn();
      });
      
      const endCombatBtn = document.getElementById('end-combat');
      addTrackedListener(endCombatBtn, 'click', function () {
        self.endCombat();
      });
    },

    /**
     * Setup pan and zoom interaction.
     */
    setupInteraction: function () {
      const self = this;
      let isDragging = false;
      let dragStart = { x: 0, y: 0 };

      const addTrackedStageListener = (event, handler) => {
        this.app.stage.on(event, handler);
        this.stageListeners.push({ event, handler });
      };

      // Pan functionality
      addTrackedStageListener('pointerdown', function (e) {
        isDragging = true;
        dragStart = { x: e.data.global.x, y: e.data.global.y };
      });

      addTrackedStageListener('pointerup', function () {
        isDragging = false;
      });

      addTrackedStageListener('pointerupoutside', function () {
        isDragging = false;
      });

      addTrackedStageListener('pointermove', function (e) {
        if (isDragging) {
          const dx = e.data.global.x - dragStart.x;
          const dy = e.data.global.y - dragStart.y;

          const nextX = self.hexContainer.x + dx;
          const nextY = self.hexContainer.y + dy;
          self.setWorldPosition(nextX, nextY);
          
          dragStart = { x: e.data.global.x, y: e.data.global.y };
        }
      });

      // Zoom functionality
      const wheelHandler = function (e) {
        e.preventDefault();
        
        const delta = e.deltaY < 0 ? 1.1 : 0.9;
        const newScale = self.hexContainer.scale.x * delta;
        
        // Limit zoom using config values
        if (newScale > self.config.minZoom && newScale < self.config.maxZoom) {
          self.setWorldScale(newScale);
          
          self.uiManager.updateZoomLevel(newScale);
        }
      };
      
      this.app.view.addEventListener('wheel', wheelHandler);
      this.eventListeners.push({ element: this.app.view, event: 'wheel', handler: wheelHandler });
    }
  };

})(Drupal, once);
