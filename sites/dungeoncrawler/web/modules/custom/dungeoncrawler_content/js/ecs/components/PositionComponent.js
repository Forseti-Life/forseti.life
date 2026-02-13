/**
 * @file
 * PositionComponent - hex grid position data.
 */

import { Component } from '../Component.js';

export class PositionComponent extends Component {
  /**
   * Create a position component.
   * @param {number} q - Axial Q coordinate
   * @param {number} r - Axial R coordinate
   * @param {number} elevation - Z-height (default 0)
   * @param {number} facing - Direction facing 0-5 (default 0)
   */
  constructor(q = 0, r = 0, elevation = 0, facing = 0) {
    super();
    this.q = q;
    this.r = r;
    this.elevation = elevation;
    this.facing = facing;
  }

  /**
   * Get hex coordinates as object.
   * @returns {{q: number, r: number}} Hex coordinates
   */
  getHex() {
    return { q: this.q, r: this.r };
  }

  /**
   * Set hex coordinates.
   * @param {number} q - Axial Q coordinate
   * @param {number} r - Axial R coordinate
   */
  setHex(q, r) {
    this.q = q;
    this.r = r;
  }

  /**
   * Get cube coordinates (for distance calculations).
   * @returns {{q: number, r: number, s: number}} Cube coordinates
   */
  getCube() {
    return {
      q: this.q,
      r: this.r,
      s: -this.q - this.r
    };
  }

  /**
   * Calculate distance to another position in hexes.
   * @param {PositionComponent} other - Other position
   * @returns {number} Distance in hexes
   */
  distanceTo(other) {
    return (
      Math.abs(this.q - other.q) +
      Math.abs(this.q + this.r - other.q - other.r) +
      Math.abs(this.r - other.r)
    ) / 2;
  }

  /**
   * Get hex key for map lookups.
   * @returns {string} Key in format "q_r"
   */
  getKey() {
    return `${this.q}_${this.r}`;
  }

  /**
   * Check if position equals another.
   * @param {PositionComponent} other - Other position
   * @returns {boolean} True if same position
   */
  equals(other) {
    return this.q === other.q && this.r === other.r && this.elevation === other.elevation;
  }

  /**
   * Clone this position.
   * @returns {PositionComponent} Cloned position
   */
  clone() {
    return new PositionComponent(this.q, this.r, this.elevation, this.facing);
  }
}
