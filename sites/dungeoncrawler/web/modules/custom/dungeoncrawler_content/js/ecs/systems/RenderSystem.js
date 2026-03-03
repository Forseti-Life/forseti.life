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
        if (render.sprite.__categoryMask) {
          render.sprite.__categoryMask.visible = false;
        }
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
    if (render.sprite.__categoryMask) {
      render.sprite.__categoryMask.x = pixelPos.x;
      render.sprite.__categoryMask.y = pixelPos.y;
      render.sprite.__categoryMask.visible = render.visible;
    }

    // Update appearance
    // For fixed-size sprites (generated/object textures), apply scale by
    // resizing from the intended base dimensions instead of overriding width/
    // height via scale.set(), which would revert to native texture size.
    if (render.sprite.__fixedHexSize) {
      const baseWidth = render.sprite.__baseWidth || (this.hexSize * 1.5);
      const baseHeight = render.sprite.__baseHeight || (this.hexSize * 1.5);
      const multiplier = Number.isFinite(render.scale) ? render.scale : 1;
      render.sprite.width = baseWidth * multiplier;
      render.sprite.height = baseHeight * multiplier;
    }
    else {
      render.sprite.scale.set(render.scale);
    }
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
      const baseDims = this.getSpriteBaseDimensions(render.objectCategory);
      sprite.__fixedHexSize = true;
      sprite.__baseWidth = baseDims.width;
      sprite.__baseHeight = baseDims.height;
      sprite.width = baseDims.width;
      sprite.height = baseDims.height;
      this.applyCategoryMask(sprite, render.objectCategory);
    } else {
      // Create placeholder graphics using category hints when available
      const entityType = identity ? identity.entityType : 'default';
      sprite = this.createPlaceholderSprite(entityType, render.objectCategory, render.objectColor);
    }

    render.sprite = sprite;
    this.objectContainer.addChild(sprite);

    console.log(`Created sprite for entity ${entity.id}`);
    return sprite;
  }

  /**
   * Replace an entity's sprite with a loaded texture from a URL.
   * Used when a generated sprite image becomes available after initial placeholder render.
   * @param {Entity} entity - Entity to update
   * @param {PIXI.Texture} texture - Loaded texture
   */
  replaceEntitySprite(entity, texture) {
    const render = entity.getComponent('RenderComponent');
    if (!render || !render.sprite) {
      return;
    }

    const oldSprite = render.sprite;
    const x = oldSprite.x;
    const y = oldSprite.y;
    const zIndex = oldSprite.zIndex;
    const entityId = oldSprite.entityId;

    // Remove old placeholder sprite
    if (oldSprite.parent) {
      oldSprite.parent.removeChild(oldSprite);
    }
    if (oldSprite.__categoryMask && oldSprite.__categoryMask.parent) {
      oldSprite.__categoryMask.parent.removeChild(oldSprite.__categoryMask);
      oldSprite.__categoryMask.destroy();
    }
    oldSprite.destroy({ texture: false, children: true });

    // Create new sprite from texture
    const newSprite = new PIXI.Sprite(texture);
    newSprite.anchor.set(0.5);
    const baseDims = this.getSpriteBaseDimensions(render.objectCategory);
    newSprite.__fixedHexSize = true;
    newSprite.__baseWidth = baseDims.width;
    newSprite.__baseHeight = baseDims.height;
    newSprite.width = baseDims.width;
    newSprite.height = baseDims.height;
    this.applyCategoryMask(newSprite, render.objectCategory);
    newSprite.x = x;
    newSprite.y = y;
    newSprite.zIndex = zIndex;
    newSprite.entityId = entityId;
    const multiplier = Number.isFinite(render.scale) ? render.scale : 1;
    newSprite.width = baseDims.width * multiplier;
    newSprite.height = baseDims.height * multiplier;
    newSprite.rotation = render.rotation;
    newSprite.tint = render.tint;
    newSprite.alpha = render.alpha;
    newSprite.visible = render.visible;

    render.sprite = newSprite;
    this.objectContainer.addChild(newSprite);
  }

  /**
   * Apply visual mask based on object category.
   * @param {PIXI.Sprite} sprite - Sprite to mask
   * @param {string|null} category - Object category hint
   */
  applyCategoryMask(sprite, category = null) {
    const normalized = typeof category === 'string' ? category.toLowerCase() : '';

    if (normalized !== 'door') {
      return;
    }

    const mask = this.createHexMaskGraphic(this.hexSize * 0.98);
    mask.x = sprite.x;
    mask.y = sprite.y;
    this.objectContainer.addChild(mask);
    sprite.mask = mask;
    sprite.__categoryMask = mask;
  }

  /**
   * Create a pointy-top hex graphics mask centered at (0, 0).
   * @param {number} radius - Hex corner radius
   * @returns {PIXI.Graphics}
   */
  createHexMaskGraphic(radius) {
    const graphic = new PIXI.Graphics();
    const points = [];
    for (let i = 0; i < 6; i++) {
      // Match hexmap.js drawHex orientation exactly.
      const angle = (Math.PI / 3) * i;
      points.push({
        x: radius * Math.cos(angle),
        y: radius * Math.sin(angle),
      });
    }

    graphic.beginFill(0xffffff, 1);
    graphic.moveTo(points[0].x, points[0].y);
    for (let i = 1; i < points.length; i++) {
      graphic.lineTo(points[i].x, points[i].y);
    }
    graphic.closePath();
    graphic.endFill();

    return graphic;
  }

  /**
   * Resolve fixed sprite base dimensions for object categories.
   * @param {string|null} category - Object category hint
   * @returns {{width: number, height: number}}
   */
  getSpriteBaseDimensions(category = null) {
    const normalized = typeof category === 'string' ? category.toLowerCase() : '';

    // Doors should visually occupy a full hex footprint.
    if (normalized === 'door') {
      return {
        width: this.hexSize * 1.75,
        height: this.hexSize * 2.0,
      };
    }

    return {
      width: this.hexSize * 1.5,
      height: this.hexSize * 1.5,
    };
  }

  /**
   * Parse a CSS hex color string to an integer.
   * @param {string} colorStr - e.g. '#8B4513'
   * @returns {number} Integer color value
   */
  parseColor(colorStr) {
    if (typeof colorStr === 'number') return colorStr;
    if (typeof colorStr !== 'string') return 0x888888;
    const hex = colorStr.replace('#', '');
    return parseInt(hex, 16) || 0x888888;
  }

  /**
   * Create placeholder sprite based on entity type and optional object category.
   * @param {string} entityType - Entity type
   * @param {string|null} category - Object definition category (bar, table, door, etc.)
   * @param {string|null} color - Object definition color hex string
   * @returns {PIXI.Sprite} Placeholder sprite
   */
  createPlaceholderSprite(entityType, category = null, color = null) {
    const graphics = new PIXI.Graphics();
    const size = this.hexSize * 0.8;

    switch (entityType) {
      case 'creature':
      case 'npc':
        // Red circle with white inner ring
        graphics.beginFill(0xe74c3c);
        graphics.drawCircle(0, 0, size / 2);
        graphics.endFill();
        graphics.lineStyle(2, 0xffffff, 0.6);
        graphics.drawCircle(0, 0, size / 3);
        break;
      case 'player_character':
        // Blue circle with star-like inner marker
        graphics.beginFill(0x3498db);
        graphics.drawCircle(0, 0, size / 2);
        graphics.endFill();
        graphics.beginFill(0xffffff, 0.7);
        graphics.drawCircle(0, 0, size / 5);
        graphics.endFill();
        break;
      case 'item':
        graphics.beginFill(0xf39c12);
        graphics.drawRect(-size / 3, -size / 3, size / 1.5, size / 1.5);
        graphics.endFill();
        break;
      case 'treasure':
        graphics.beginFill(0xf1c40f);
        graphics.lineStyle(3, 0xe67e22);
        graphics.drawRect(-size / 3, -size / 3, size / 1.5, size / 1.5);
        graphics.endFill();
        break;
      case 'obstacle':
        this.drawObstacleByCategory(graphics, size, category, color);
        break;
      default:
        graphics.beginFill(0x7f8c8d);
        graphics.drawCircle(0, 0, size / 2);
        graphics.endFill();
    }

    // Convert to sprite
    const texture = this.pixiApp.renderer.generateTexture(graphics);
    const sprite = new PIXI.Sprite(texture);
    sprite.anchor.set(0.5);
    
    return sprite;
  }

  /**
   * Draw obstacle shape based on object_definition category.
   * Categories: bar, table, door, stool, crate, decor
   * @param {PIXI.Graphics} graphics
   * @param {number} size - Base size
   * @param {string|null} category
   * @param {string|null} colorStr - Hex color from object definition
   */
  drawObstacleByCategory(graphics, size, category, colorStr) {
    const fill = colorStr ? this.parseColor(colorStr) : 0x95a5a6;

    switch (category) {
      case 'bar':
        // Wide rectangle — bar counter
        graphics.beginFill(fill);
        graphics.drawRoundedRect(-size / 2, -size / 5, size, size / 2.5, 3);
        graphics.endFill();
        // Top surface highlight
        graphics.beginFill(0xffffff, 0.15);
        graphics.drawRoundedRect(-size / 2 + 2, -size / 5 + 1, size - 4, size / 6, 2);
        graphics.endFill();
        break;

      case 'table':
        // Circle — round or long table
        graphics.beginFill(fill);
        graphics.drawEllipse(0, 0, size / 2.2, size / 3);
        graphics.endFill();
        // Surface highlight
        graphics.beginFill(0xffffff, 0.12);
        graphics.drawEllipse(0, -2, size / 3, size / 5);
        graphics.endFill();
        break;

      case 'door':
        // Arch shape
        graphics.beginFill(fill);
        graphics.drawRoundedRect(-size / 4, -size / 2.5, size / 2, size / 1.5, 6);
        graphics.endFill();
        // Doorknob
        graphics.beginFill(0xffd700);
        graphics.drawCircle(size / 8, 0, 2);
        graphics.endFill();
        break;

      case 'stool':
        // Small circle — passable seating
        graphics.beginFill(fill);
        graphics.drawCircle(0, 0, size / 4);
        graphics.endFill();
        graphics.lineStyle(1, 0x000000, 0.3);
        graphics.drawCircle(0, 0, size / 4);
        break;

      case 'crate':
        // Square with cross-braces
        graphics.beginFill(fill);
        graphics.drawRect(-size / 3, -size / 3, size / 1.5, size / 1.5);
        graphics.endFill();
        graphics.lineStyle(1, 0x000000, 0.25);
        graphics.moveTo(-size / 3, -size / 3);
        graphics.lineTo(size / 3, size / 3);
        graphics.moveTo(size / 3, -size / 3);
        graphics.lineTo(-size / 3, size / 3);
        break;

      case 'decor':
        // Diamond shape
        graphics.beginFill(fill);
        graphics.drawPolygon([
          0, -size / 3,
          size / 3, 0,
          0, size / 3,
          -size / 3, 0
        ]);
        graphics.endFill();
        break;

      default:
        // Generic obstacle — gray triangle (legacy fallback)
        graphics.beginFill(fill);
        graphics.drawPolygon([
          -size / 2, size / 2,
          0, -size / 2,
          size / 2, size / 2
        ]);
        graphics.endFill();
    }
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
