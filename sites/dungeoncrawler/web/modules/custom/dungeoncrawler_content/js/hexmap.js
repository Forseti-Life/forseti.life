/**
 * @file
 * Hex map rendering with PixiJS + ECS architecture.
 */

// Import ECS modules
import { EntityManager, PositionComponent, RenderComponent, IdentityComponent, EntityType, RenderSystem, MovementComponent, StatsComponent, MovementSystem, MovementMode, ActionsComponent, ActionType, ActionCost, CombatComponent, Team, TurnManagementSystem, CombatState, CombatSystem, AttackResult } from './ecs/index.js';
import combatApi from './hexmap-api.js';

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
        hoveredObject: document.getElementById('hovered-object'),
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
        zoomLevel: document.getElementById('zoom-level'),
        hexDetailRoom: document.getElementById('hex-detail-room'),
        hexDetailTerrain: document.getElementById('hex-detail-terrain'),
        hexDetailElevation: document.getElementById('hex-detail-elevation'),
        hexDetailLighting: document.getElementById('hex-detail-lighting'),
        hexDetailPassability: document.getElementById('hex-detail-passability'),
        hexDetailObjects: document.getElementById('hex-detail-objects'),
        hexDetailEntities: document.getElementById('hex-detail-entities'),
        hexDetailConnection: document.getElementById('hex-detail-connection')
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
     * Update hovered object label display.
     */
    updateHoveredObject(label) {
      if (this.elements.hoveredObject) {
        this.elements.hoveredObject.textContent = label || 'None';
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

    /**
     * Update hovered hex detail panel.
     * @param {Object|null} details - Detail payload for the hovered hex.
     */
    updateHexDetails(details) {
      const fallback = {
        room: 'None',
        terrain: 'Unknown',
        elevation: '-',
        lighting: 'Unknown',
        passability: 'Unknown',
        objects: 'None',
        entities: 'None',
        connection: 'None'
      };

      const payload = details ? {
        room: details.roomName || fallback.room,
        terrain: details.terrain || fallback.terrain,
        elevation: Number.isFinite(details.elevationFt) ? `${details.elevationFt} ft` : fallback.elevation,
        lighting: details.lighting || fallback.lighting,
        passability: details.passability || fallback.passability,
        objects: Array.isArray(details.objects) && details.objects.length ? details.objects.join(', ') : fallback.objects,
        entities: Array.isArray(details.entities) && details.entities.length ? details.entities.join(', ') : fallback.entities,
        connection: details.connection || fallback.connection
      } : fallback;

      const map = {
        hexDetailRoom: payload.room,
        hexDetailTerrain: payload.terrain,
        hexDetailElevation: payload.elevation,
        hexDetailLighting: payload.lighting,
        hexDetailPassability: payload.passability,
        hexDetailObjects: payload.objects,
        hexDetailEntities: payload.entities,
        hexDetailConnection: payload.connection
      };

      Object.entries(map).forEach(([key, value]) => {
        if (this.elements[key]) {
          this.elements[key].textContent = value;
        }
      });
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

    // Launch context from campaign/tavern flow.
    launchContext: {},

    // Dungeon payload for room-aware rendering and transitions.
    dungeonData: {},
    activeRoomId: null,
    
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
      this.launchContext = settings?.dungeoncrawlerContent?.hexmapLaunchContext || {};
      this.dungeonData = settings?.dungeoncrawlerContent?.hexmapDungeonData || {};
      this.activeRoomId = this.dungeonData?.active_room_id || null;

      this.initPixiApp(container[0]);
      this.initECS(); // Initialize ECS architecture
      this.generateHexGrid();
      this.setupControls();
      this.setupInteraction();
      this.applyDungeonData();
      this.applyLaunchContext();
      
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

      this.launchContext = {};
      this.dungeonData = {};
      this.activeRoomId = null;
      
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

      // End any existing combat before wiping entities so turn order resets cleanly.
      if (this.turnManagementSystem) {
        this.turnManagementSystem.endCombat();
      }

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
      } else if (combat && !combat.isPlayerTeam()) {
        // Basic AI: let non-player entities take their turn automatically.
        this.runNpcTurn(entity);
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

      // Reset transient UI state tied to previous hex graphics
      this.stateManager.set('hoveredHex', null);
      this.uiManager.updateHoveredHex(null, null);
      this.uiManager.updateHoveredObject('None');
      if (this.uiManager.elements.selectedHex) {
        this.uiManager.elements.selectedHex.textContent = 'None';
      }
      this.stateManager.set('selectedHex', null);
      this.hideMovementRange();

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

      // Reapply room/obstacle styling for the rebuilt grid
      this.paintActiveRoom();

      // If an entity remains selected, refresh its movement range overlay with the new grid sizing
      const selectedEntity = this.stateManager.get('selectedEntity');
      if (selectedEntity) {
        this.showMovementRange(selectedEntity);
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
      this.uiManager.updateHoveredObject(this.getObjectLabelAtHex(q, r));
      this.uiManager.updateHexDetails(this.getHexDetail(q, r));
    },

    /**
     * Hex out event.
     */
    onHexOut: function (hex) {
      // Reset hex appearance (unless it's selected)
      if (this.stateManager.get('selectedHex') !== hex) {
        this.resetHexAppearance(hex);
      }

      this.stateManager.set('hoveredHex', null);
      this.uiManager.updateHoveredHex(null, null);
      this.uiManager.updateHoveredObject('None');
      this.uiManager.updateHexDetails(null);
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

      // Mode 1.5: Room transition if hex is a passable room connection endpoint.
      if (this.tryTransitionAtHex(q, r)) {
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

      this.setSelectedHex(hex);
      
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
    createEntityObject: function (q, r, entityType, name, spriteKey = null, options = {}) {
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
        const statsConfig = options.stats || {};
        const movementSpeed = options.movementSpeed ?? statsConfig.speed ?? 30;
        const actionsPerTurn = options.actionsPerTurn ?? 3;

        // Add stats
        const stats = new StatsComponent({ 
          speed: movementSpeed,
          maxHp: statsConfig.maxHp ?? 20,
          currentHp: statsConfig.currentHp ?? statsConfig.maxHp ?? 20,
          ac: statsConfig.ac ?? 10,
          perception: statsConfig.perception ?? 0
        });
        entity.addComponent('StatsComponent', stats);
        
        // Add movement
        const movement = new MovementComponent(movementSpeed);
        entity.addComponent('MovementComponent', movement);
        
        // Add actions (3-action economy)
        const actions = new ActionsComponent(actionsPerTurn);
        entity.addComponent('ActionsComponent', actions);
        
        // Add combat
        const team = this.resolveTeamPreference(options.team, entityType);
        const combat = new CombatComponent({ 
          team: team,
          initiativeBonus: statsConfig.initiative_bonus ?? options.initiativeBonus ?? 0,
          attackBonus: statsConfig.attack_bonus ?? 0
        });
        entity.addComponent('CombatComponent', combat);
      } else if (entityType === EntityType.ITEM || entityType === EntityType.OBSTACLE) {
        // Items/furniture should be targetable but not join initiative.
        const statsConfig = options.stats || {};
        const stats = new StatsComponent({
          speed: 0,
          maxHp: statsConfig.maxHp ?? 10,
          currentHp: statsConfig.currentHp ?? statsConfig.maxHp ?? 10,
          ac: statsConfig.ac ?? 10,
          perception: statsConfig.perception ?? 0
        });
        entity.addComponent('StatsComponent', stats);
      }
      
      console.log(`Created entity "${name}" (${entityType}) at (${q}, ${r})`);
      return entity;
    },

    /**
     * Resolve team preference to CombatComponent team value.
     */
    resolveTeamPreference: function (teamPreference, entityType) {
      const normalized = teamPreference ? String(teamPreference).toLowerCase() : null;
      if (normalized === 'player') {
        return Team.PLAYER;
      }
      if (normalized === 'ally') {
        return Team.ALLY;
      }
      if (normalized === 'neutral') {
        return Team.NEUTRAL;
      }
      if (normalized === 'enemy') {
        return Team.ENEMY;
      }

      return entityType === EntityType.PLAYER_CHARACTER ? Team.PLAYER : Team.ENEMY;
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
    serializeCombatantsForApi: function () {
      if (!this.entityManager) {
        return [];
      }

      const entities = this.entityManager.getEntitiesWith('IdentityComponent', 'CombatComponent');
      return entities.map((entity) => {
        const identity = entity.getComponent('IdentityComponent');
        const combat = entity.getComponent('CombatComponent');
        const stats = entity.getComponent('StatsComponent');

        return {
          entityId: entity.id,
          name: identity?.name || `Entity ${entity.id}`,
          team: combat?.team,
          initiative: combat?.getInitiative ? combat.getInitiative() : null,
          initiative_bonus: combat?.initiativeBonus,
          perception: stats?.perception,
          ac: stats?.ac,
          hp: stats?.currentHp,
          max_hp: stats?.maxHp,
        };
      });
    },

    startCombat: async function (options = {}) {
      console.log('Starting combat...');

      const payload = {
        campaignId: this.config?.campaignId,
        roomId: this.stateManager.get('activeRoomId'),
        entities: this.serializeCombatantsForApi(),
        ...options
      };

      try {
        const serverState = await combatApi.startCombat(payload);

        // If backend returns a serialized encounter, hydrate client; otherwise fall back to local logic.
        if (serverState && typeof this.turnManagementSystem.hydrateFromServer === 'function') {
          if (serverState.encounter_id) {
            this.stateManager.set('encounterId', serverState.encounter_id);
          }
          this.turnManagementSystem.hydrateFromServer(serverState);
          return;
        }
        if (serverState && serverState.encounter_id) {
          this.stateManager.set('encounterId', serverState.encounter_id);
        }
      } catch (err) {
        console.warn('Combat start via API failed, falling back to client system.', err);
      }

      this.turnManagementSystem.startCombat(options);
    },
    
    /**
     * End current turn.
     */
    endTurn: async function () {
      console.log('Ending turn...');

      const currentTurn = this.turnManagementSystem?.getCurrentTurn?.();
      const payload = {
        encounterId: this.stateManager.get('encounterId'),
        participantId: currentTurn?.entityId
      };

      try {
        const serverState = await combatApi.endTurn(payload);
        if (serverState && typeof this.turnManagementSystem.hydrateFromServer === 'function') {
          if (serverState.encounter_id) {
            this.stateManager.set('encounterId', serverState.encounter_id);
          }
          this.turnManagementSystem.hydrateFromServer(serverState);
          return;
        }
        if (serverState && serverState.encounter_id) {
          this.stateManager.set('encounterId', serverState.encounter_id);
        }
      } catch (err) {
        console.warn('Turn end via API failed, falling back to client system.', err);
      }

      this.turnManagementSystem.endTurn();
    },
    
    /**
     * End combat encounter.
     */
    endCombat: async function () {
      console.log('Ending combat...');

      const payload = {
        encounterId: this.stateManager.get('encounterId')
      };

      try {
        await combatApi.endCombat(payload);
      } catch (err) {
        console.warn('Combat end via API failed, falling back to client system.', err);
      }

      this.turnManagementSystem.endCombat();
      this.stateManager.set('encounterId', null);
      this.deselectEntity();
    },

    /**
     * Free-action talk interface hook for AI conversation integration.
     * @param {Entity} speaker - Speaking entity
     * @param {string} message - Utterance content
     */
    performTalk: function (speaker, message) {
      if (!speaker || !message) {
        return;
      }

      const actions = speaker.getComponent('ActionsComponent');
      if (actions) {
        actions.spendActions(ActionCost.FREE, 'Talk');
      }

      const identity = speaker.getComponent('IdentityComponent');
      const combat = speaker.getComponent('CombatComponent');

      // Emit an event for downstream ai_conversation listeners.
      window.dispatchEvent(new CustomEvent('dungeoncrawler:talk', {
        detail: {
          entityId: speaker.id,
          name: identity?.name || `Entity ${speaker.id}`,
          team: combat?.team || null,
          roomId: this.activeRoomId || null,
          message: message
        }
      }));
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
     * Get all hostile, alive targets for an entity, sorted by distance.
     * @param {Entity} actor
     * @returns {Array<{target: Entity, distance: number}>}
     */
    getHostileTargets: function (actor) {
      const actorCombat = actor.getComponent('CombatComponent');
      const actorPos = actor.getComponent('PositionComponent');
      if (!actorCombat || !actorPos) {
        return [];
      }

      const candidates = this.entityManager.getEntitiesWith('CombatComponent', 'StatsComponent', 'PositionComponent');
      const hostileTargets = [];

      candidates.forEach((candidate) => {
        if (candidate.id === actor.id) {
          return;
        }

        const targetCombat = candidate.getComponent('CombatComponent');
        const targetStats = candidate.getComponent('StatsComponent');
        const targetPos = candidate.getComponent('PositionComponent');

        if (!targetCombat || !targetPos || !targetStats?.isAlive()) {
          return;
        }

        if (!actorCombat.isHostileTo(targetCombat)) {
          return;
        }

        const distance = this.movementSystem.hexDistance(actorPos.q, actorPos.r, targetPos.q, targetPos.r);
        hostileTargets.push({ target: candidate, distance });
      });

      hostileTargets.sort((a, b) => a.distance - b.distance);
      return hostileTargets;
    },

    /**
     * Choose the next step toward a target using pathfinding.
     * Moves up to available movement within one stride.
     * @param {Entity} actor
     * @param {Entity} target
     * @returns {{q:number,r:number}|null}
     */
    getNextStepToward: function (actor, target) {
      const pos = actor.getComponent('PositionComponent');
      const movement = actor.getComponent('MovementComponent');
      const targetPos = target.getComponent('PositionComponent');

      if (!pos || !movement || !targetPos) {
        return null;
      }

      // Find a reachable neighbor adjacent to target (avoid standing on target hex)
      const neighborOptions = this.movementSystem.hexDirections
        .map((dir) => ({ q: targetPos.q + dir.q, r: targetPos.r + dir.r }))
        .filter(({ q, r }) => this.movementSystem.getTerrainCost(q, r) !== Infinity);

      // Choose the neighbor with shortest distance to actor
      neighborOptions.sort((a, b) => {
        const da = this.movementSystem.hexDistance(pos.q, pos.r, a.q, a.r);
        const db = this.movementSystem.hexDistance(pos.q, pos.r, b.q, b.r);
        return da - db;
      });

      if (!neighborOptions.length) {
        return null;
      }

      const strideBudget = Math.floor(movement.movementRemaining / movement.hexMovementCost);
      const destination = neighborOptions.find((option) => {
        const path = this.movementSystem.findPath(pos.q, pos.r, option.q, option.r, strideBudget);
        return path && path.length > 1;
      });

      if (!destination) {
        return null;
      }

      // Step as far as possible toward the destination within stride budget
      const path = this.movementSystem.findPath(pos.q, pos.r, destination.q, destination.r, strideBudget);
      if (!path || path.length < 2) {
        return null;
      }

      // Move to the furthest reachable hex in this stride (path length limited by strideBudget)
      const steps = Math.min(path.length - 1, strideBudget);
      return path[steps];
    },

    /**
     * Very basic NPC AI: stride toward nearest hostile, attack when adjacent, then end turn.
     * @param {Entity} actor - Non-player entity taking its turn
     */
    runNpcTurn: function (actor) {
      const combat = actor.getComponent('CombatComponent');
      if (!combat || combat.isPlayerTeam()) {
        return;
      }

      const actions = actor.getComponent('ActionsComponent');
      const movement = actor.getComponent('MovementComponent');
      const stats = actor.getComponent('StatsComponent');
      const pos = actor.getComponent('PositionComponent');

      if (!actions || !stats || !pos) {
        this.turnManagementSystem.endTurn();
        return;
      }

      // Simple loop over remaining actions: stride if not adjacent, attack if adjacent.
      while (actions.actionsRemaining > 0) {
        const targets = this.getHostileTargets(actor);
        if (!targets.length) {
          break;
        }

        const { target, distance } = targets[0];
        const targetPos = target.getComponent('PositionComponent');
        if (!targetPos) {
          break;
        }

        if (distance <= 1) {
          // Adjacent: attack
          const result = this.combatSystem.attack(actor, target);
          if (!result) {
            break;
          }
          continue;
        }

        // Need to move closer
        if (!movement || movement.movementRemaining < movement.hexMovementCost) {
          break;
        }

        // Spend one action to stride
        if (!actions.spendActions(ActionCost.ONE, 'Stride')) {
          break;
        }

        const nextStep = this.getNextStepToward(actor, target);
        if (!nextStep) {
          break;
        }

        const moved = this.movementSystem.moveEntity(actor, nextStep.q, nextStep.r);
        if (!moved) {
          break;
        }
      }

      // End turn after AI finishes its allotted actions/movement
      this.turnManagementSystem.endTurn();
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
    },

    /**
     * Find a rendered hex by axial coordinates.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {PIXI.Graphics|null}
     */
    findHexByCoords: function (q, r) {
      const matchingHex = this.hexContainer.children.find((child) => {
        if (!child.hexData) {
          return false;
        }
        return child.hexData.q === q && child.hexData.r === r;
      });

      return matchingHex || null;
    },

    /**
     * Draw a hex with provided style.
     * @param {PIXI.Graphics} hex - Hex graphic
     * @param {number} fillColor - Fill color
     * @param {number} lineWidth - Border width
     * @param {number} lineColor - Border color
     * @param {number} alpha - Fill alpha
     */
    drawHexStyle: function (hex, fillColor, lineWidth, lineColor, alpha = 1) {
      hex.clear();
      hex.beginFill(fillColor, alpha);
      hex.lineStyle(lineWidth, lineColor, 1);

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
    },

    /**
     * Check whether a hex coordinate belongs to the active room.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {boolean}
     */
    isHexInActiveRoom: function (q, r) {
      const room = this.getActiveRoomData();
      if (!room || !Array.isArray(room.hexes)) {
        return false;
      }
      return room.hexes.some((roomHex) => roomHex.q === q && roomHex.r === r);
    },

    /**
     * Reset hex appearance based on active room membership.
     * @param {PIXI.Graphics} hex - Hex graphic
     */
    resetHexAppearance: function (hex) {
      if (!hex?.hexData) {
        return;
      }

      const { q, r } = hex.hexData;
      const obstacleProfile = this.getObstacleMobilityAtHex(q, r);

      if (obstacleProfile) {
        if (!obstacleProfile.passable && !obstacleProfile.movable) {
          this.drawHexStyle(hex, 0x5b2b2b, 2, 0x8b3a3a, 0.95);
          return;
        }

        if (!obstacleProfile.passable && obstacleProfile.movable) {
          this.drawHexStyle(hex, 0x7a5325, 2, 0xb7791f, 0.95);
          return;
        }

        if (obstacleProfile.passable && obstacleProfile.movable) {
          this.drawHexStyle(hex, 0x2d5170, 2, 0x4299e1, 0.95);
          return;
        }

        this.drawHexStyle(hex, 0x2d4b36, 2, 0x4d7a5b, 1);
        return;
      }

      if (this.isHexInActiveRoom(q, r)) {
        this.drawHexStyle(hex, 0x2d4b36, 2, 0x4d7a5b, 1);
      } else {
        this.drawHexStyle(hex, 0x2d3748, 1, 0x4a5568, 1);
      }
    },

    /**
     * Apply selected-hex visuals and state.
     * @param {PIXI.Graphics} hex - Hex graphic
     */
    setSelectedHex: function (hex) {
      if (!hex?.hexData) {
        return;
      }

      this.stateManager.set('selectedHex', hex);
      this.drawHexStyle(hex, 0x3b82f6, 3, 0x60a5fa, 1);

      const { q, r } = hex.hexData;
      this.uiManager.updateSelectedHex(q, r);
    },

    /**
     * Get currently active room payload.
     * @returns {Object|null}
     */
    getActiveRoomData: function () {
      if (!this.dungeonData || !this.activeRoomId || !this.dungeonData.rooms) {
        return null;
      }
      return this.dungeonData.rooms[this.activeRoomId] || null;
    },

    /**
     * Color room footprint for active room.
     */
    paintActiveRoom: function () {
      this.hexContainer.children.forEach((hex) => {
        if (!hex?.hexData) {
          return;
        }
        this.resetHexAppearance(hex);
      });
    },

    /**
     * Render active-room entities from dungeon payload.
     */
    renderActiveRoomEntities: function () {
      if (!this.entityManager) {
        return;
      }

      this.clearEntities();
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];

      entities.forEach((entity) => {
        const placement = entity?.placement;
        if (!placement || placement.room_id !== this.activeRoomId || !placement.hex) {
          return;
        }

        const q = Number(placement.hex.q);
        const r = Number(placement.hex.r);
        if (!Number.isFinite(q) || !Number.isFinite(r)) {
          return;
        }

        const rawType = entity?.entity_type ? String(entity.entity_type).toLowerCase() : '';
        let entityType = EntityType.OBSTACLE;
        if (rawType === 'creature') {
          entityType = EntityType.CREATURE;
        } else if (rawType === 'player_character' || rawType === 'player') {
          entityType = EntityType.PLAYER_CHARACTER;
        } else if (rawType === 'npc') {
          entityType = EntityType.NPC;
        } else if (rawType === 'item') {
          entityType = EntityType.ITEM;
        }

        const metadata = entity?.state?.metadata || {};
        const contentId = entity?.entity_ref?.content_id;
        const objectDefinition = this.getObjectDefinition(contentId);
        const entityName = metadata.display_name || metadata.name || entity?.display_name ||
          objectDefinition?.label || (contentId ? String(contentId).replace(/[_-]+/g, ' ') : String(entity.entity_type || 'entity'));

        const options = {
          team: metadata.team,
          stats: metadata.stats || {},
          movementSpeed: metadata.movement_speed,
          actionsPerTurn: metadata.actions_per_turn,
          initiativeBonus: metadata.initiative_bonus
        };

        this.createEntityObject(q, r, entityType, entityName, null, options);
      });

      // Automatically enter initiative for the active room so every area is treated as a live encounter.
      if (this.turnManagementSystem) {
        this.startCombat({ force: true });
      }
    },

    /**
     * Resolve object definition by content ID.
     * @param {string} contentId - Object content ID
     * @returns {Object|null}
     */
    getObjectDefinition: function (contentId) {
      if (!contentId) {
        return null;
      }

      const definitions = this.dungeonData?.object_definitions;
      if (!definitions || typeof definitions !== 'object') {
        return null;
      }

      return definitions[contentId] || null;
    },

    /**
     * Get obstacle mobility profile at hex in active room.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {{movable: boolean, passable: boolean, stackable: boolean}|null}
     */
    getObstacleMobilityAtHex: function (q, r) {
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      if (!entities.length || !this.activeRoomId) {
        return null;
      }

      const obstacle = entities.find((entity) => {
        if (entity?.entity_type !== 'obstacle') {
          return false;
        }

        const placement = entity.placement;
        if (!placement || placement.room_id !== this.activeRoomId || !placement.hex) {
          return false;
        }

        return Number(placement.hex.q) === q && Number(placement.hex.r) === r;
      });

      if (!obstacle) {
        return null;
      }

      const objectDefinition = this.getObjectDefinition(obstacle?.entity_ref?.content_id);
      const metadata = obstacle?.state?.metadata || {};
      const definitionMovement = objectDefinition?.movement || {};

      const movable = (typeof metadata.movable === 'boolean') ? metadata.movable : Boolean(objectDefinition?.movable);
      const passable = (typeof metadata.passable === 'boolean') ? metadata.passable : Boolean(definitionMovement.passable);
      const stackable = (typeof metadata.stackable === 'boolean') ? metadata.stackable : Boolean(objectDefinition?.stackable);

      return { movable, passable, stackable };
    },

    /**
     * Describe passability text for a hex.
     */
    describePassability: function (obstacleProfile, inActiveRoom) {
      if (obstacleProfile) {
        if (!obstacleProfile.passable && !obstacleProfile.movable) {
          return 'Impassable (fixed)';
        }
        if (!obstacleProfile.passable && obstacleProfile.movable) {
          return 'Impassable (movable)';
        }
        if (obstacleProfile.passable && obstacleProfile.movable) {
          return 'Passable (movable)';
        }
        return 'Passable';
      }

      return inActiveRoom ? 'Open floor' : 'Outside active room';
    },

    /**
     * Describe entities at a hex (live ECS first, then payload fallback).
     */
    describeEntitiesAtHex: function (q, r) {
      const labels = [];

      if (this.entityManager) {
        const liveEntities = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent', 'CombatComponent');
        liveEntities.forEach((entity) => {
          const pos = entity.getComponent('PositionComponent');
          if (pos?.q !== q || pos?.r !== r) {
            return;
          }
          const identity = entity.getComponent('IdentityComponent');
          const combat = entity.getComponent('CombatComponent');
          const teamLabel = combat?.team ? ` (${combat.team})` : '';
          labels.push(`${identity?.name || 'Entity'}${teamLabel}`);
        });
      }

      if (labels.length) {
        return labels;
      }

      const payloadEntities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      const fallback = payloadEntities.filter((candidate) => {
        if (!candidate?.placement || candidate.placement.room_id !== this.activeRoomId) {
          return false;
        }
        const hex = candidate.placement.hex;
        return hex && Number(hex.q) === q && Number(hex.r) === r;
      });

      fallback.forEach((candidate) => {
        const metadata = candidate?.state?.metadata || {};
        const displayName = metadata.display_name || metadata.name;
        if (displayName) {
          labels.push(displayName);
          return;
        }
        const contentId = candidate?.entity_ref?.content_id;
        labels.push(contentId ? String(contentId).replace(/[_-]+/g, ' ') : String(candidate.entity_type || 'entity'));
      });

      return labels;
    },

    /**
     * Describe objects on a hex from room payload and object definitions.
     */
    describeObjectsAtHex: function (hex, q, r) {
      const labels = [];

      if (hex && Array.isArray(hex.objects)) {
        hex.objects.forEach((object) => {
          if (object?.label) {
            labels.push(object.label);
          } else if (object?.object_id) {
            labels.push(String(object.object_id).replace(/[_-]+/g, ' '));
          }
        });
      }

      const obstacleLabel = this.getObstacleMobilityAtHex(q, r) ? this.getObjectLabelAtHex(q, r) : null;
      if (obstacleLabel) {
        labels.push(obstacleLabel);
      }

      return labels;
    },

    /**
     * Describe connection metadata for a hex if present.
     */
    describeConnectionAtHex: function (q, r) {
      const connections = Array.isArray(this.dungeonData?.connections) ? this.dungeonData.connections : [];
      if (!connections.length) {
        return null;
      }

      const match = connections.find((connection) => {
        const fromHex = connection?.from_hex;
        const toHex = connection?.to_hex;
        return (fromHex && Number(fromHex.q) === q && Number(fromHex.r) === r) ||
               (toHex && Number(toHex.q) === q && Number(toHex.r) === r);
      });

      if (!match) {
        return null;
      }

      const targetRoom = match.to_room === this.activeRoomId ? match.from_room : match.to_room;
      const status = [];
      status.push(match.is_passable ? 'passable' : 'blocked');
      if (match.is_discovered) {
        status.push('discovered');
      }

      return `${match.type || 'connection'} -> ${targetRoom || 'unknown'} (${status.join(', ')})`;
    },

    /**
     * Build a detail payload for the hovered hex.
     */
    getHexDetail: function (q, r) {
      const room = this.getActiveRoomData();
      if (!room) {
        return null;
      }

      const hex = Array.isArray(room.hexes) ? room.hexes.find((candidate) => Number(candidate.q) === q && Number(candidate.r) === r) : null;
      const inRoom = Boolean(hex);
      const obstacleProfile = this.getObstacleMobilityAtHex(q, r);

      return {
        roomName: inRoom ? room.name : `${room.name} (outside footprint)` ,
        terrain: room.terrain?.type || 'unknown',
        elevationFt: inRoom && Number.isFinite(Number(hex?.elevation_ft)) ? Number(hex.elevation_ft) : null,
        lighting: room.lighting?.level || 'unknown',
        passability: this.describePassability(obstacleProfile, inRoom),
        objects: this.describeObjectsAtHex(hex, q, r),
        entities: this.describeEntitiesAtHex(q, r),
        connection: this.describeConnectionAtHex(q, r)
      };
    },

    /**
     * Get object label (if any) at a given hex in the active room.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {string|null}
     */
    getObjectLabelAtHex: function (q, r) {
      // Prefer live ECS entities so session-placed objects are labeled
      if (this.entityManager) {
        const liveEntities = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
        const match = liveEntities.find((candidate) => {
          const pos = candidate.getComponent('PositionComponent');
          return pos && pos.q === q && pos.r === r;
        });

        if (match) {
          const identity = match.getComponent('IdentityComponent');
          if (identity?.name) {
            return identity.name;
          }
        }
      }

      // Fallback to dungeon payload for pre-seeded entities
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      if (!entities.length || !this.activeRoomId) {
        return null;
      }

      const entity = entities.find((candidate) => {
        if (!candidate?.placement || candidate.placement.room_id !== this.activeRoomId) {
          return false;
        }

        const hex = candidate.placement.hex;
        if (!hex) {
          return false;
        }
        return Number(hex.q) === q && Number(hex.r) === r;
      });

      if (!entity) {
        return null;
      }

      const contentId = entity?.entity_ref?.content_id;
      const definition = this.getObjectDefinition(contentId);
      if (definition?.label) {
        return definition.label;
      }

      if (contentId) {
        return String(contentId).replace(/[_-]+/g, ' ');
      }

      return entity.entity_type ? String(entity.entity_type) : null;
    },

    /**
     * Set active room and redraw room content.
     * @param {string} roomId - Target room ID
     */
    setActiveRoom: function (roomId) {
      if (!roomId || !this.dungeonData?.rooms || !this.dungeonData.rooms[roomId]) {
        return;
      }

      this.activeRoomId = roomId;
      this.paintActiveRoom();
      this.renderActiveRoomEntities();
      console.log('Active room set:', roomId);
    },

    /**
     * Apply dungeon payload and initialize active room view.
     */
    applyDungeonData: function () {
      const rooms = this.dungeonData?.rooms;
      if (!rooms || Object.keys(rooms).length === 0) {
        return;
      }

      if (!this.activeRoomId || !rooms[this.activeRoomId]) {
        this.activeRoomId = Object.keys(rooms)[0];
      }

      this.setActiveRoom(this.activeRoomId);
    },

    /**
     * Try to transition to a connected room at a given hex.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {boolean}
     */
    tryTransitionAtHex: function (q, r) {
      const connections = Array.isArray(this.dungeonData?.connections) ? this.dungeonData.connections : [];
      if (!connections.length || !this.activeRoomId) {
        return false;
      }

      const match = connections.find((connection) => {
        if (connection?.is_passable === false) {
          return false;
        }

        const fromMatch = connection.from_room === this.activeRoomId &&
          Number(connection?.from_hex?.q) === q &&
          Number(connection?.from_hex?.r) === r;
        const toMatch = connection.to_room === this.activeRoomId &&
          Number(connection?.to_hex?.q) === q &&
          Number(connection?.to_hex?.r) === r;

        return fromMatch || toMatch;
      });

      if (!match) {
        return false;
      }

      let nextRoomId = null;
      let nextHex = null;

      if (match.from_room === this.activeRoomId) {
        nextRoomId = match.to_room;
        nextHex = match.to_hex;
      } else {
        nextRoomId = match.from_room;
        nextHex = match.from_hex;
      }

      this.setActiveRoom(nextRoomId);

      const destinationHex = this.findHexByCoords(Number(nextHex?.q), Number(nextHex?.r));
      if (destinationHex) {
        const previousSelectedHex = this.stateManager.get('selectedHex');
        if (previousSelectedHex && previousSelectedHex !== destinationHex) {
          this.onHexOut(previousSelectedHex);
        }
        this.setSelectedHex(destinationHex);
      }

      console.log('Transitioned room:', this.activeRoomId, 'via connection', match.connection_id);
      return true;
    },

    /**
     * Apply campaign launch context to initialize map state.
     */
    applyLaunchContext: function () {
      const context = this.launchContext || {};
      const hasContext = Boolean(
        (Number(context.campaign_id) > 0) ||
        context.room_id ||
        context.dungeon_level_id ||
        context.map_id
      );

      if (!hasContext) {
        return;
      }

      const startQ = Number.isFinite(Number(context.start_q)) ? Number(context.start_q) : 0;
      const startR = Number.isFinite(Number(context.start_r)) ? Number(context.start_r) : 0;
      const startHex = this.findHexByCoords(startQ, startR);

      if (startHex) {
        const previousSelectedHex = this.stateManager.get('selectedHex');
        if (previousSelectedHex && previousSelectedHex !== startHex) {
          this.onHexOut(previousSelectedHex);
        }
        this.setSelectedHex(startHex);
        console.log('Applied launch context start hex:', startQ, startR, context);
      } else {
        console.warn('Launch context start hex not found in current grid:', startQ, startR, context);
      }
    }
  };

})(Drupal, once);
