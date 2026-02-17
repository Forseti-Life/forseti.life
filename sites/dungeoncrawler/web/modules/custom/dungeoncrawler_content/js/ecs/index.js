/**
 * @file
 * ECS (Entity Component System) module - main entry point for the ECS architecture.
 * 
 * This module provides a complete ECS implementation for the dungeon crawler game,
 * including core classes (Entity, Component, System, EntityManager), specialized
 * game components for position/rendering/combat/stats, and systems that operate
 * on entities with specific component combinations.
 * 
 * @example
 * // Import core classes
 * import { Entity, EntityManager, PositionComponent } from './ecs/index.js';
 * 
 * // Import game systems
 * import { RenderSystem, CombatSystem, AttackResult } from './ecs/index.js';
 * 
 * // Create entity manager and systems
 * const entityManager = new EntityManager();
 * const renderSystem = new RenderSystem(entityManager);
 */

// Core classes (4 total)
// Entity: Game object container with ID and components
// Component: Base class for data-only component types
// System: Base class for logic that operates on entities
// EntityManager: Central registry for entities and component queries
export { Entity } from './Entity.js';
export { Component } from './Component.js';
export { System } from './System.js';
export { EntityManager } from './EntityManager.js';

// Components
export { PositionComponent, HexDirection } from './components/PositionComponent.js';
export { RenderComponent } from './components/RenderComponent.js';
export { IdentityComponent, EntityType } from './components/IdentityComponent.js';
export { MovementComponent, MovementMode, DEFAULT_MOVEMENT_SPEED, DEFAULT_HEX_MOVEMENT_COST } from './components/MovementComponent.js';
export { StatsComponent } from './components/StatsComponent.js';
export { ActionsComponent, ActionType, ActionCost, MAPConstants } from './components/ActionsComponent.js';
export { CombatComponent, Team } from './components/CombatComponent.js';

// Systems (4 total + 2 enums/constants)
// Logic modules that operate on entities with specific component combinations
export { RenderSystem } from './systems/RenderSystem.js';
export { MovementSystem } from './systems/MovementSystem.js';
export { TurnManagementSystem, CombatState } from './systems/TurnManagementSystem.js';
export { CombatSystem, AttackResult } from './systems/CombatSystem.js';
