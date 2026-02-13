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
    this.nameplate = null;
    this.statusIcons = null;
  }

  /**
   * Serialize to JSON (exclude PixiJS references).
   * @returns {object} Serialized data
   */
  toJSON() {
    return {
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
    component.scale = data.scale;
    component.rotation = data.rotation;
    component.tint = data.tint;
    component.alpha = data.alpha;
    component.visible = data.visible;
    component.zIndex = data.zIndex;
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
    this.healthBar = null;
    this.nameplate = null;
    this.statusIcons = null;
  }
}
