/**
 * @file
 * Hex map rendering with PixiJS + ECS architecture.
 */

// Import ECS modules
import { EntityManager, PositionComponent, RenderComponent, IdentityComponent, EntityType, RenderSystem, MovementComponent, StatsComponent, MovementSystem, MovementMode, ActionsComponent, ActionType, ActionCost, CombatComponent, Team, TurnManagementSystem, CombatState } from './ecs/index.js';

// Ensure Drupal and once are available
/* global Drupal, once, PIXI */

(function (Drupal, once) {
  'use strict';

  /**
   * Hex map behavior.
   */
  Drupal.behaviors.hexMap = {
    app: null,
    hexContainer: null,
    gridContainer: null,
    objectContainer: null,
    uiContainer: null,
    hexSize: 30,
    gridWidth: 20,
    gridHeight: 20,
    showCoordinates: false,
    showGrid: true,
    selectedHex: null,
    hoveredHex: null,
    objects: new Map(), // Legacy - will migrate to ECS
    draggedObject: null,
    assetsLoaded: false,
    
    // ECS architecture
    entityManager: null,
    renderSystem: null,
    movementSystem: null,
    turnManagementSystem: null,
    
    // Movement and selection
    selectedEntity: null,
    movementRange: null,
    movementRangeOverlay: null,
    
    // Combat state
    combatActive: false,

    attach: function (context, settings) {
      const container = once('hexmap-init', '#hexmap-canvas-container', context);
      
      if (container.length === 0) {
        return;
      }

      this.initPixiApp(container[0]);
      this.initECS(); // Initialize ECS architecture
      this.generateHexGrid();
      this.setupControls();
      this.setupInteraction();
      
      // Start game loop
      this.app.ticker.add((delta) => this.update(delta));
    },
    
    /**
     * Initialize ECS architecture.
     */
    initECS: function () {
      // Create entity manager
      this.entityManager = new EntityManager();
      
      // Create UI overlay container
      this.uiContainer = new PIXI.Container();
      
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
      this.renderSystem.setHexSize(this.hexSize);
      this.entityManager.addSystem(this.renderSystem);
      
      // Create movement system
      this.movementSystem = new MovementSystem(this.entityManager);
      this.entityManager.addSystem(this.movementSystem);
      
      // Create turn management system
      this.turnManagementSystem = new TurnManagementSystem(this.entityManager);
      this.entityManager.addSystem(this.turnManagementSystem);
      
      // Set up turn management callbacks
      const self = this;
      this.turnManagementSystem.onTurnChange(function(entity, turnIndex, totalTurns) {
        self.onTurnChange(entity, turnIndex, totalTurns);
      });
      this.turnManagementSystem.onRoundChange(function(roundNumber) {
        self.onRoundChange(roundNumber);
      });
      this.turnManagementSystem.onCombatStateChange(function(combatState) {
        self.onCombatStateChange(combatState);
      });
      
      // Add UI layer to stage
      this.app.stage.addChild(this.uiContainer);
      
      // Center UI container
      this.uiContainer.x = this.hexContainer.x;
      this.uiContainer.y = this.hexContainer.y;
      
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
      
      // Update UI
      this.updateTurnUI(entity, turnIndex, totalTurns);
      
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
      
      // Update round display
      const roundDisplay = document.getElementById('current-round');
      if (roundDisplay) {
        roundDisplay.textContent = `Round ${roundNumber}`;
      }
    },
    
    /**
     * Combat state change callback.
     * @param {string} combatState - New combat state
     */
    onCombatStateChange: function (combatState) {
      console.log(`Combat state: ${combatState}`);
      this.combatActive = (combatState === CombatState.IN_PROGRESS || combatState === CombatState.ROLLING_INITIATIVE);
      
      // Update UI
      this.updateCombatUI(combatState);
    },

    /**
     * Initialize PixiJS application.
     */
    initPixiApp: function (container) {
      // Create PixiJS application
      this.app = new PIXI.Application({
        width: container.clientWidth || 800,
        height: container.clientHeight || 600,
        backgroundColor: 0x1a1a2e,
        antialias: true,
        resolution: window.devicePixelRatio || 1,
        autoDensity: true,
      });

      container.appendChild(this.app.view);

      // Create containers for layers
      this.hexContainer = new PIXI.Container();
      this.gridContainer = new PIXI.Container();
      this.objectContainer = new PIXI.Container();
      
      // Add layers in order: hexes (terrain), grid (coords), objects (sprites)
      this.app.stage.addChild(this.hexContainer);
      this.app.stage.addChild(this.gridContainer);
      this.app.stage.addChild(this.objectContainer);

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

      const hexSize = this.hexSize;
      const width = this.gridWidth;
      const height = this.gridHeight;

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
      if (this.showCoordinates) {
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
        const x = this.hexSize * Math.cos(angle);
        const y = this.hexSize * Math.sin(angle);
        
        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();

      this.hoveredHex = hex;
      
      // Update UI
      const { q, r } = hex.hexData;
      document.getElementById('hovered-hex').textContent = `(${q}, ${r})`;
    },

    /**
     * Hex out event.
     */
    onHexOut: function (hex) {
      // Reset hex appearance (unless it's selected)
      if (this.selectedHex !== hex) {
        hex.clear();
        hex.beginFill(0x2d3748);
        hex.lineStyle(1, 0x4a5568, 1);
        
        for (let i = 0; i < 6; i++) {
          const angle = (Math.PI / 3) * i;
          const x = this.hexSize * Math.cos(angle);
          const y = this.hexSize * Math.sin(angle);
          
          if (i === 0) {
            hex.moveTo(x, y);
          } else {
            hex.lineTo(x, y);
          }
        }
        hex.closePath();
        hex.endFill();
      }

      this.hoveredHex = null;
      document.getElementById('hovered-hex').textContent = 'None';
    },

    /**
     * Hex click event.
     */
    onHexClick: function (hex) {
      const { q, r } = hex.hexData;
      
      // Mode 1: Object placement mode
      if (this.selectedObjectType) {
        // Map object type to EntityType
        let entityType;
        let name;
        switch (this.selectedObjectType) {
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
      
      // Mode 2: Check if clicking on an entity to select it
      const entitiesAtPos = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
      for (const entity of entitiesAtPos) {
        const pos = entity.getComponent('PositionComponent');
        if (pos.q === q && pos.r === r) {
          // Check if entity has MovementComponent (can be selected)
          if (entity.hasComponent('MovementComponent')) {
            this.selectEntity(entity);
            return;
          }
        }
      }
      
      // Mode 3: Move selected entity
      if (this.selectedEntity && this.movementRange) {
        const hexKey = `${q}_${r}`;
        if (this.movementRange.has(hexKey)) {
          // Try to move entity
          const success = this.movementSystem.moveEntity(this.selectedEntity, q, r);
          if (success) {
            console.log(`Moved entity to (${q}, ${r})`);
            // Refresh movement range after move
            this.showMovementRange(this.selectedEntity);
          }
          return;
        }
      }
      
      // Mode 4: Default hex selection
      // Deselect previous hex
      if (this.selectedHex) {
        this.onHexOut(this.selectedHex);
      }

      // Select this hex
      this.selectedHex = hex;
      
      hex.clear();
      hex.beginFill(0x3b82f6);
      hex.lineStyle(3, 0x60a5fa, 1);
      
      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = this.hexSize * Math.cos(angle);
        const y = this.hexSize * Math.sin(angle);
        
        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();

      // Update UI
      document.getElementById('selected-hex').textContent = `(${q}, ${r})`;
      
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
      if (this.selectedEntity) {
        this.deselectEntity();
      }
      
      this.selectedEntity = entity;
      
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
    },
    
    /**
     * Deselect currently selected entity.
     */
    deselectEntity: function () {
      if (!this.selectedEntity) {
        return;
      }
      
      // Remove tint from sprite
      const render = this.selectedEntity.getComponent('RenderComponent');
      if (render && render.sprite) {
        render.sprite.tint = 0xffffff; // Reset to white
      }
      
      this.selectedEntity = null;
      this.hideMovementRange();
      
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
      this.movementRange = this.movementSystem.calculateMovementRange(entity);
      
      // Create overlay graphics
      this.movementRangeOverlay = new PIXI.Graphics();
      
      // Draw reachable hexes
      this.movementRange.forEach(hexKey => {
        const [q, r] = hexKey.split('_').map(Number);
        const pos = this.axialToPixel(q, r, this.hexSize);
        
        this.movementRangeOverlay.beginFill(0x3b82f6, 0.2); // Blue with transparency
        this.movementRangeOverlay.lineStyle(2, 0x60a5fa, 0.5);
        
        for (let i = 0; i < 6; i++) {
          const angle = (Math.PI / 3) * i;
          const x = pos.x + this.hexSize * Math.cos(angle);
          const y = pos.y + this.hexSize * Math.sin(angle);
          
          if (i === 0) {
            this.movementRangeOverlay.moveTo(x, y);
          } else {
            this.movementRangeOverlay.lineTo(x, y);
          }
        }
        this.movementRangeOverlay.closePath();
        this.movementRangeOverlay.endFill();
      });
      
      this.uiContainer.addChild(this.movementRangeOverlay);
    },
    
    /**
     * Hide movement range overlay.
     */
    hideMovementRange: function () {
      if (this.movementRangeOverlay) {
        this.uiContainer.removeChild(this.movementRangeOverlay);
        this.movementRangeOverlay.destroy();
        this.movementRangeOverlay = null;
      }
      this.movementRange = null;
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
     * Update turn UI display.
     * @param {Entity} entity - Current turn entity
     * @param {number} turnIndex - Turn index
     * @param {number} totalTurns - Total turns
     */
    updateTurnUI: function (entity, turnIndex, totalTurns) {
      const identity = entity.getComponent('IdentityComponent');
      const actions = entity.getComponent('ActionsComponent');
      const name = identity ? identity.name : `Entity ${entity.id}`;
      
      // Update current turn display
      const currentTurnDiv = document.getElementById('current-turn');
      if (currentTurnDiv) {
        let html = `<strong>${name}</strong>`;
        if (actions) {
          html += ` ${actions.getActionDisplay()}`;
          if (actions.hasReactionAvailable()) {
            html += ' ⚡'; // Reaction available
          }
        }
        currentTurnDiv.innerHTML = html;
      }
      
      // Update initiative tracker
      const initiativeList = document.getElementById('initiative-list');
      if (initiativeList) {
        const order = this.turnManagementSystem.getInitiativeOrder();
        let html = '';
        
        order.forEach((data, index) => {
          const activeClass = data.isCurrent ? 'active-turn' : '';
          const defeatedClass = data.isDefeated ? 'defeated' : '';
          html += `<div class="initiative-item ${activeClass} ${defeatedClass}">
            <span class="init-value">${data.initiative}</span>
            <span class="init-name">${data.name}</span>
          </div>`;
        });
        
        initiativeList.innerHTML = html;
      }
    },
    
    /**
     * Update combat UI based on state.
     * @param {string} combatState - Combat state
     */
    updateCombatUI: function (combatState) {
      const combatControls = document.getElementById('combat-controls');
      const startCombatBtn = document.getElementById('start-combat');
      const endTurnBtn = document.getElementById('end-turn');
      const endCombatBtn = document.getElementById('end-combat');
      const initiativeTracker = document.getElementById('initiative-tracker');
      
      if (!combatControls) return;
      
      if (combatState === CombatState.INACTIVE || combatState === CombatState.ENDED) {
        // Show start button, hide others
        if (startCombatBtn) startCombatBtn.style.display = 'inline-block';
        if (endTurnBtn) endTurnBtn.style.display = 'none';
        if (endCombatBtn) endCombatBtn.style.display = 'none';
        if (initiativeTracker) initiativeTracker.style.display = 'none';
      } else {
        // Hide start button, show combat controls
        if (startCombatBtn) startCombatBtn.style.display = 'none';
        if (endTurnBtn) endTurnBtn.style.display = 'inline-block';
        if (endCombatBtn) endCombatBtn.style.display = 'inline-block';
        if (initiativeTracker) initiativeTracker.style.display = 'block';
      }
    },

    /**
     * Create a game object on a hex (LEGACY - use createEntityObject for new code).
     */
    createObject: function (q, r, type, spritePath) {
      const key = `${q}_${r}`;
      
      // Remove existing object at this position
      if (this.objects.has(key)) {
        this.removeObject(q, r);
      }

      // Create object sprite
      const sprite = this.createObjectSprite(type, spritePath);
      const pos = this.axialToPixel(q, r, this.hexSize);
      
      sprite.x = pos.x;
      sprite.y = pos.y;
      sprite.anchor.set(0.5);
      sprite.objectData = { q, r, type };
      
      // Make interactive for dragging
      sprite.interactive = true;
      sprite.buttonMode = true;
      sprite.on('pointerdown', (e) => this.onObjectDragStart(e, sprite));
      sprite.on('pointerup', () => this.onObjectDragEnd(sprite));
      sprite.on('pointerupoutside', () => this.onObjectDragEnd(sprite));
      sprite.on('pointermove', (e) => this.onObjectDrag(e, sprite));
      
      this.objectContainer.addChild(sprite);
      this.objects.set(key, sprite);
      
      console.log(`Created ${type} at (${q}, ${r})`);
      return sprite;
    },

    /**
     * Create a sprite for an object (placeholder graphics if no texture).
     */
    createObjectSprite: function (type, spritePath) {
      // If we have a sprite path and texture is loaded, use it
      if (spritePath && PIXI.utils.TextureCache[spritePath]) {
        const sprite = new PIXI.Sprite(PIXI.utils.TextureCache[spritePath]);
        sprite.width = this.hexSize * 1.5;
        sprite.height = this.hexSize * 1.5;
        return sprite;
      }
      
      // Otherwise create placeholder graphics
      const graphics = new PIXI.Graphics();
      const size = this.hexSize * 0.8;
      
      // Different shapes/colors for different types
      switch (type) {
        case 'creature':
          graphics.beginFill(0xe74c3c); // Red
          graphics.drawCircle(0, 0, size / 2);
          break;
        case 'item':
          graphics.beginFill(0xf39c12); // Orange
          graphics.drawRect(-size / 3, -size / 3, size / 1.5, size / 1.5);
          break;
        case 'obstacle':
          graphics.beginFill(0x95a5a6); // Gray
          graphics.drawPolygon([
            -size / 2, size / 2,
            0, -size / 2,
            size / 2, size / 2
          ]);
          break;
        case 'treasure':
          graphics.beginFill(0xf1c40f); // Gold
          graphics.lineStyle(3, 0xe67e22);
          graphics.drawRect(-size / 3, -size / 3, size / 1.5, size / 1.5);
          break;
        default:
          graphics.beginFill(0x3498db); // Blue
          graphics.drawRect(-size / 2, -size / 2, size, size);
      }
      graphics.endFill();
      
      // Convert to sprite for consistency
      const texture = this.app.renderer.generateTexture(graphics);
      const sprite = new PIXI.Sprite(texture);
      sprite.anchor.set(0.5);
      return sprite;
    },

    /**
     * Remove object from hex.
     */
    removeObject: function (q, r) {
      const key = `${q}_${r}`;
      const sprite = this.objects.get(key);
      
      if (sprite) {
        this.objectContainer.removeChild(sprite);
        sprite.destroy();
        this.objects.delete(key);
        console.log(`Removed object from (${q}, ${r})`);
      }
    },

    /**
     * Start dragging an object.
     */
    onObjectDragStart: function (e, sprite) {
      e.stopPropagation(); // Prevent stage drag
      this.draggedObject = sprite;
      sprite.alpha = 0.7;
      sprite.dragging = true;
      sprite.dragData = e.data;
      console.log('Started dragging object');
    },

    /**
     * Drag object.
     */
    onObjectDrag: function (e, sprite) {
      if (sprite.dragging) {
        const newPosition = sprite.dragData.getLocalPosition(this.objectContainer);
        sprite.x = newPosition.x;
        sprite.y = newPosition.y;
      }
    },

    /**
     * End dragging object (snap to hex).
     */
    onObjectDragEnd: function (sprite) {
      if (!sprite.dragging) return;
      
      sprite.dragging = false;
      sprite.alpha = 1;
      this.draggedObject = null;
      
      // Get position in hex coordinates
      const localPos = { x: sprite.x, y: sprite.y };
      const axial = this.pixelToAxial(localPos.x, localPos.y, this.hexSize);
      
      // Remove from old position
      const oldKey = `${sprite.objectData.q}_${sprite.objectData.r}`;
      this.objects.delete(oldKey);
      
      // Snap to nearest hex
      const pos = this.axialToPixel(axial.q, axial.r, this.hexSize);
      sprite.x = pos.x;
      sprite.y = pos.y;
      
      // Update object data
      sprite.objectData.q = axial.q;
      sprite.objectData.r = axial.r;
      
      // Add to new position
      const newKey = `${axial.q}_${axial.r}`;
      
      // If there's already an object at this position, remove it
      if (this.objects.has(newKey)) {
        const existingSprite = this.objects.get(newKey);
        this.objectContainer.removeChild(existingSprite);
        existingSprite.destroy();
      }
      
      this.objects.set(newKey, sprite);
      
      console.log(`Moved object to (${axial.q}, ${axial.r})`);
    },

    /**
     * Load game assets.
     */
    loadAssets: async function (assetList) {
      if (this.assetsLoaded) return;
      
      console.log('Loading assets...');
      
      try {
        for (const asset of assetList) {
          await PIXI.Assets.load(asset);
        }
        this.assetsLoaded = true;
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

      // Grid size selector
      document.getElementById('grid-size').addEventListener('change', function (e) {
        const size = e.target.value;
        switch (size) {
          case 'small':
            self.gridWidth = 10;
            self.gridHeight = 10;
            break;
          case 'medium':
            self.gridWidth = 20;
            self.gridHeight = 20;
            break;
          case 'large':
            self.gridWidth = 40;
            self.gridHeight = 40;
            break;
        }
        self.generateHexGrid();
      });

      // Hex size slider
      document.getElementById('hex-size').addEventListener('input', function (e) {
        self.hexSize = parseInt(e.target.value);
        document.getElementById('hex-size-value').textContent = self.hexSize + 'px';
        self.generateHexGrid();
      });

      // Toggle coordinates
      document.getElementById('toggle-coordinates').addEventListener('click', function () {
        self.showCoordinates = !self.showCoordinates;
        self.generateHexGrid();
      });

      // Toggle grid lines
      document.getElementById('toggle-grid').addEventListener('click', function () {
        self.showGrid = !self.showGrid;
        self.gridContainer.visible = self.showGrid;
      });

      // Reset view
      document.getElementById('reset-view').addEventListener('click', function () {
        self.hexContainer.scale.set(1);
        self.hexContainer.x = self.app.screen.width / 2;
        self.hexContainer.y = self.app.screen.height / 2;
        self.gridContainer.scale.set(1);
        self.gridContainer.x = self.hexContainer.x;
        self.gridContainer.y = self.hexContainer.y;
        self.objectContainer.scale.set(1);
        self.objectContainer.x = self.hexContainer.x;
        self.objectContainer.y = self.hexContainer.y;
        document.getElementById('zoom-level').textContent = '100%';
      });

      // Object palette controls
      self.selectedObjectType = null;

      // Object type buttons
      document.querySelectorAll('.btn-object').forEach(function (btn) {
        btn.addEventListener('click', function () {
          // Remove active class from all buttons
          document.querySelectorAll('.btn-object').forEach(b => b.classList.remove('active'));
          
          // Set active button
          btn.classList.add('active');
          self.selectedObjectType = btn.dataset.type;
          
          // Update display
          document.getElementById('selected-object-type').textContent = 
            'Selected: ' + btn.dataset.type.charAt(0).toUpperCase() + btn.dataset.type.slice(1);
          
          console.log('Selected object type:', self.selectedObjectType);
        });
      });

      // Clear all objects
      document.getElementById('clear-objects').addEventListener('click', function () {
        self.objects.forEach((sprite, key) => {
          self.objectContainer.removeChild(sprite);
          sprite.destroy();
        });
        self.objects.clear();
        console.log('Cleared all objects');
      });
      
      // Deselect entity button (if it exists)
      const deselectBtn = document.getElementById('deselect-entity');
      if (deselectBtn) {
        deselectBtn.addEventListener('click', function () {
          self.deselectEntity();
        });
      }
      
      // Combat controls
      const startCombatBtn = document.getElementById('start-combat');
      if (startCombatBtn) {
        startCombatBtn.addEventListener('click', function () {
          self.startCombat();
        });
      }
      
      const endTurnBtn = document.getElementById('end-turn');
      if (endTurnBtn) {
        endTurnBtn.addEventListener('click', function () {
          self.endTurn();
        });
      }
      
      const endCombatBtn = document.getElementById('end-combat');
      if (endCombatBtn) {
        endCombatBtn.addEventListener('click', function () {
          self.endCombat();
        });
      }
    },

    /**
     * Setup pan and zoom interaction.
     */
    setupInteraction: function () {
      const self = this;
      let isDragging = false;
      let dragStart = { x: 0, y: 0 };

      // Pan functionality
      this.app.stage.on('pointerdown', function (e) {
        isDragging = true;
        dragStart = { x: e.data.global.x, y: e.data.global.y };
      });

      this.app.stage.on('pointerup', function () {
        isDragging = false;
      });

      this.app.stage.on('pointerupoutside', function () {
        isDragging = false;
      });

      this.app.stage.on('pointermove', function (e) {
        if (isDragging) {
          const dx = e.data.global.x - dragStart.x;
          const dy = e.data.global.y - dragStart.y;
          
          self.hexContainer.x += dx;
          self.hexContainer.y += dy;
          self.gridContainer.x += dx;
          self.gridContainer.y += dy;
          self.objectContainer.x += dx;
          self.objectContainer.y += dy;
          
          dragStart = { x: e.data.global.x, y: e.data.global.y };
        }
      });

      // Zoom functionality
      this.app.view.addEventListener('wheel', function (e) {
        e.preventDefault();
        
        const delta = e.deltaY < 0 ? 1.1 : 0.9;
        const newScale = self.hexContainer.scale.x * delta;
        
        // Limit zoom
        if (newScale > 0.5 && newScale < 3) {
          self.hexContainer.scale.set(newScale);
          self.gridContainer.scale.set(newScale);
          self.objectContainer.scale.set(newScale);
          
          const zoomPercent = Math.round(newScale * 100);
          document.getElementById('zoom-level').textContent = zoomPercent + '%';
        }
      });
    }
  };

})(Drupal, once);
