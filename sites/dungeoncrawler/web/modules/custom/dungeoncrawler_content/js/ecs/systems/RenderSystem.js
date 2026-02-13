/**
 * @file
 * RenderSystem - integrates ECS with PixiJS rendering.
 */

import { System } from '../System.js';

export class RenderSystem extends System {
  /**
   * Create render system.
   * @param {EntityManager} entityManager - Entity manager
   * @param {PIXI.Application} pixiApp - PixiJS application
   * @param {object} containers - PixiJS container references
   */
  constructor(entityManager, pixiApp, containers) {
    super(entityManager);
    this.pixiApp = pixiApp;
    this.hexContainer = containers.hex;
    this.objectContainer = containers.object;
    this.uiContainer = containers.ui;
    this.hexSize = 30;
    
    this.priority = 100; // Render last
  }

  /**
   * Initialize system.
   */
  init() {
    console.log('RenderSystem initialized');
  }

  /**
   * Update all rendered entities.
   * @param {number} deltaTime - Time since last update
   */
  update(deltaTime) {
    const entities = this.entityManager.getEntitiesWith('PositionComponent', 'RenderComponent');
    
    for (const entity of entities) {
      this.syncEntityToSprite(entity);
    }
    
    // Sort by zIndex
    this.objectContainer.children.sort((a, b) => (a.zIndex || 0) - (b.zIndex || 0));
  }

  /**
   * Sync entity data to PixiJS sprite.
   * @param {Entity} entity - Entity to sync
   */
  syncEntityToSprite(entity) {
    const position = entity.getComponent('PositionComponent');
    const render = entity.getComponent('RenderComponent');
    const stats = entity.getComponent('StatsComponent');
    const identity = entity.getComponent('IdentityComponent');
    
    if (!render.visible) {
      if (render.sprite) {
        render.sprite.visible = false;
      }
      if (render.healthBar) {
        render.healthBar.visible = false;
      }
      if (render.nameLabel) {
        render.nameLabel.visible = false;
      }
      return;
    }

    // Create sprite if doesn't exist
    if (!render.sprite) {
      this.createSprite(entity);
    }

    // Update position
    const pixelPos = this.hexToPixel(position.q, position.r);
    render.sprite.x = pixelPos.x;
    render.sprite.y = pixelPos.y;

    // Update appearance
    render.sprite.scale.set(render.scale);
    render.sprite.rotation = render.rotation;
    render.sprite.tint = render.tint;
    render.sprite.alpha = render.alpha;
    render.sprite.visible = render.visible;
    render.sprite.zIndex = render.zIndex;

    // Store entity reference on sprite
    render.sprite.entityId = entity.id;
    
    // Update or create health bar for entities with stats
    if (stats && (identity?.entityType === 'creature' || 
                  identity?.entityType === 'player_character' || 
                  identity?.entityType === 'npc')) {
      this.updateHealthBar(entity, render, stats, pixelPos);
    }
    
    // Update or create name label
    if (identity && identity.name) {
      this.updateNameLabel(entity, render, identity, pixelPos);
    }
  }
  
  /**
   * Update or create health bar for entity.
   * @param {Entity} entity - Entity
   * @param {RenderComponent} render - Render component
   * @param {StatsComponent} stats - Stats component
   * @param {Object} pixelPos - Pixel position {x, y}
   */
  updateHealthBar(entity, render, stats, pixelPos) {
    if (!render.healthBar) {
      // Create health bar container
      const container = new PIXI.Container();
      
      // Background bar (gray)
      const background = new PIXI.Graphics();
      background.beginFill(0x2d3748);
      background.drawRect(0, 0, 40, 4);
      background.endFill();
      container.addChild(background);
      
      // Health bar (green/yellow/red based on HP)
      const bar = new PIXI.Graphics();
      container.addChild(bar);
      
      // Border
      const border = new PIXI.Graphics();
      border.lineStyle(1, 0x1a202c);
      border.drawRect(0, 0, 40, 4);
      container.addChild(border);
      
      render.healthBar = container;
      render.healthBar.bar = bar;
      this.uiContainer.addChild(container);
    }
    
    // Update health bar position (above sprite)
    render.healthBar.x = pixelPos.x - 20;
    render.healthBar.y = pixelPos.y - this.hexSize * 0.8;
    render.healthBar.visible = render.visible;
    
    // Update health bar fill
    const healthPercent = stats.getHealthPercentage();
    const barWidth = 40 * healthPercent;
    
    // Color based on health percentage
    let barColor;
    if (healthPercent > 0.6) {
      barColor = 0x48bb78; // Green
    } else if (healthPercent > 0.3) {
      barColor = 0xed8936; // Orange
    } else {
      barColor = 0xe53e3e; // Red
    }
    
    render.healthBar.bar.clear();
    render.healthBar.bar.beginFill(barColor);
    render.healthBar.bar.drawRect(0, 0, barWidth, 4);
    render.healthBar.bar.endFill();
  }
  
  /**
   * Update or create name label for entity.
   * @param {Entity} entity - Entity
   * @param {RenderComponent} render - Render component
   * @param {IdentityComponent} identity - Identity component
   * @param {Object} pixelPos - Pixel position {x, y}
   */
  updateNameLabel(entity, render, identity, pixelPos) {
    if (!render.nameLabel) {
      // Create name label
      const text = new PIXI.Text(identity.name, {
        fontFamily: 'Arial',
        fontSize: 12,
        fill: 0xffffff,
        stroke: 0x000000,
        strokeThickness: 3,
        align: 'center'
      });
      text.anchor.set(0.5, 1);
      
      render.nameLabel = text;
      this.uiContainer.addChild(text);
    }
    
    // Update name label position (below sprite)
    render.nameLabel.x = pixelPos.x;
    render.nameLabel.y = pixelPos.y + this.hexSize * 0.7;
    render.nameLabel.visible = render.visible;
    
    // Update text if name changed
    if (render.nameLabel.text !== identity.name) {
      render.nameLabel.text = identity.name;
    }
  }

  /**
   * Create PixiJS sprite for entity.
   * @param {Entity} entity - Entity to create sprite for
   */
  createSprite(entity) {
    const render = entity.getComponent('RenderComponent');
    const identity = entity.getComponent('IdentityComponent');

    let sprite;

    // Check if we have a texture/sprite key
    if (render.spriteKey && PIXI.utils.TextureCache[render.spriteKey]) {
      sprite = new PIXI.Sprite(PIXI.utils.TextureCache[render.spriteKey]);
      sprite.anchor.set(0.5);
      sprite.width = this.hexSize * 1.5;
      sprite.height = this.hexSize * 1.5;
    } else {
      // Create placeholder graphics
      sprite = this.createPlaceholderSprite(identity ? identity.entityType : 'default');
    }

    render.sprite = sprite;
    this.objectContainer.addChild(sprite);

    console.log(`Created sprite for entity ${entity.id}`);
    return sprite;
  }

  /**
   * Create placeholder sprite based on entity type.
   * @param {string} entityType - Entity type
   * @returns {PIXI.Sprite} Placeholder sprite
   */
  createPlaceholderSprite(entityType) {
    const graphics = new PIXI.Graphics();
    const size = this.hexSize * 0.8;

    switch (entityType) {
      case 'creature':
      case 'npc':
        graphics.beginFill(0xe74c3c); // Red circle
        graphics.drawCircle(0, 0, size / 2);
        break;
      case 'player_character':
        graphics.beginFill(0x3498db); // Blue circle
        graphics.drawCircle(0, 0, size / 2);
        break;
      case 'item':
        graphics.beginFill(0xf39c12); // Orange square
        graphics.drawRect(-size / 3, -size / 3, size / 1.5, size / 1.5);
        break;
      case 'obstacle':
        graphics.beginFill(0x95a5a6); // Gray triangle
        graphics.drawPolygon([
          -size / 2, size / 2,
          0, -size / 2,
          size / 2, size / 2
        ]);
        break;
      case 'treasure':
        graphics.beginFill(0xf1c40f); // Gold outlined square
        graphics.lineStyle(3, 0xe67e22);
        graphics.drawRect(-size / 3, -size / 3, size / 1.5, size / 1.5);
        break;
      default:
        graphics.beginFill(0x7f8c8d); // Gray circle
        graphics.drawCircle(0, 0, size / 2);
    }
    graphics.endFill();

    // Convert to sprite
    const texture = this.pixiApp.renderer.generateTexture(graphics);
    const sprite = new PIXI.Sprite(texture);
    sprite.anchor.set(0.5);
    
    return sprite;
  }

  /**
   * Remove sprite and UI elements for entity.
   * @param {Entity} entity - Entity
   */
  removeSprite(entity) {
    const render = entity.getComponent('RenderComponent');
    if (render) {
      // Remove sprite
      if (render.sprite) {
        this.objectContainer.removeChild(render.sprite);
        render.sprite.destroy();
        render.sprite = null;
      }
      
      // Remove health bar
      if (render.healthBar) {
        this.uiContainer.removeChild(render.healthBar);
        render.healthBar.destroy({ children: true });
        render.healthBar = null;
      }
      
      // Remove name label
      if (render.nameLabel) {
        this.uiContainer.removeChild(render.nameLabel);
        render.nameLabel.destroy();
        render.nameLabel = null;
      }
      
      console.log(`Removed sprite and UI for entity ${entity.id}`);
    }
  }

  /**
   * Convert hex coordinates to pixel position.
   * @param {number} q - Axial Q coordinate
   * @param {number} r - Axial R coordinate
   * @returns {{x: number, y: number}} Pixel position
   */
  hexToPixel(q, r) {
    const size = this.hexSize;
    const x = size * (3 / 2 * q);
    const y = size * (Math.sqrt(3) / 2 * q + Math.sqrt(3) * r);
    return { x, y };
  }

  /**
   * Convert pixel position to hex coordinates.
   * @param {number} x - Pixel X
   * @param {number} y - Pixel Y
   * @returns {{q: number, r: number}} Hex coordinates
   */
  pixelToHex(x, y) {
    const size = this.hexSize;
    const q = (2 / 3 * x) / size;
    const r = (-1 / 3 * x + Math.sqrt(3) / 3 * y) / size;
    return this.roundHex(q, r);
  }

  /**
   * Round fractional hex coordinates to nearest hex.
   * @param {number} q - Fractional Q
   * @param {number} r - Fractional R
   * @returns {{q: number, r: number}} Rounded hex coordinates
   */
  roundHex(q, r) {
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
  }

  /**
   * Set hex size for rendering.
   * @param {number} size - New hex size in pixels
   */
  setHexSize(size) {
    this.hexSize = size;
  }

  /**
   * Cleanup system.
   */
  destroy() {
    // Remove all sprites
    const entities = this.entityManager.getEntitiesWith('RenderComponent');
    for (const entity of entities) {
      this.removeSprite(entity);
    }
    console.log('RenderSystem destroyed');
  }
}
