/**
 * @file
 * ECS module exports - main entry point for the ECS architecture.
 */

// Core classes
export { Entity } from './Entity.js';
export { Component } from './Component.js';
export { System } from './System.js';
export { EntityManager } from './EntityManager.js';

// Components
export { PositionComponent } from './components/PositionComponent.js';
export { RenderComponent } from './components/RenderComponent.js';
export { IdentityComponent, EntityType } from './components/IdentityComponent.js';
export { MovementComponent, MovementMode, DEFAULT_MOVEMENT_SPEED, DEFAULT_HEX_MOVEMENT_COST } from './components/MovementComponent.js';
export { StatsComponent } from './components/StatsComponent.js';
export { ActionsComponent, ActionType, ActionCost } from './components/ActionsComponent.js';
export { CombatComponent, Team } from './components/CombatComponent.js';

// Systems
export { RenderSystem } from './systems/RenderSystem.js';
export { MovementSystem } from './systems/MovementSystem.js';
export { TurnManagementSystem, CombatState } from './systems/TurnManagementSystem.js';
export { CombatSystem, AttackResult } from './systems/CombatSystem.js';
