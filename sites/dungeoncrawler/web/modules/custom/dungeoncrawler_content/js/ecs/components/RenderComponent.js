/**
 * @file
 * RenderComponent - PixiJS rendering data.
 */

import { Component } from '../Component.js';

export class RenderComponent extends Component {
  /**
   * Create a render component.
   * @param {string} spriteKey - Asset path or texture key
   */
  constructor(spriteKey = null) {
    super();
    
    // Asset information
    this.spriteKey = spriteKey;
    
    // Visual properties
    this.scale = 1.0;
    this.rotation = 0;
    this.tint = 0xffffff;
    this.alpha = 1.0;
    this.visible = true;
    this.zIndex = 0;
    
    // PixiJS object references (not serialized)
    this.sprite = null;
    this.container = null;
    this.healthBar = null;
    this.nameLabel = null;
    this.statusIcons = null;
  }

  /**
   * Serialize to JSON (exclude PixiJS references).
   * @returns {object} Serialized data
   */
  toJSON() {
    return {
      type: this.constructor.name,
      spriteKey: this.spriteKey,
      scale: this.scale,
      rotation: this.rotation,
      tint: this.tint,
      alpha: this.alpha,
      visible: this.visible,
      zIndex: this.zIndex
    };
  }

  /**
   * Deserialize from JSON.
   * @param {object} data - Serialized data
   * @returns {RenderComponent} New component instance
   */
  static fromJSON(data) {
    const component = new RenderComponent(data.spriteKey);
    component.scale = data.scale ?? 1.0;
    component.rotation = data.rotation ?? 0;
    component.tint = data.tint ?? 0xffffff;
    component.alpha = data.alpha ?? 1.0;
    component.visible = data.visible ?? true;
    component.zIndex = data.zIndex ?? 0;
    return component;
  }

  /**
   * Clean up PixiJS resources.
   */
  destroy() {
    if (this.sprite) {
      this.sprite.destroy();
      this.sprite = null;
    }
    if (this.container) {
      this.container.destroy({ children: true });
      this.container = null;
    }
    if (this.healthBar) {
      this.healthBar.destroy({ children: true });
      this.healthBar = null;
    }
    if (this.nameLabel) {
      this.nameLabel.destroy();
      this.nameLabel = null;
    }
    this.statusIcons = null;
  }

  /**
   * Check if component has a sprite created.
   * @returns {boolean} True if sprite exists
   */
  hasSprite() {
    return this.sprite !== null;
  }

  /**
   * Check if component is visible.
   * @returns {boolean} True if visible
   */
  isVisible() {
    return this.visible;
  }

  /**
   * Set visibility.
   * @param {boolean} visible - Visibility state
   */
  setVisible(visible) {
    this.visible = visible;
  }

  /**
   * Set tint color.
   * @param {number} color - Hex color value (e.g., 0xff0000 for red)
   */
  setTint(color) {
    this.tint = color;
  }

  /**
   * Reset tint to default white.
   */
  resetTint() {
    this.tint = 0xffffff;
  }
}
