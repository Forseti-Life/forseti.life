/**
 * @file
 * ECS (Entity Component System) module - main entry point for the ECS architecture.
 * 
 * This module provides a complete ECS implementation for the dungeon crawler game,
 * including core classes (Entity, Component, System, EntityManager), specialized
 * game components for position/rendering/combat/stats, and systems that operate
 * on entities with specific component combinations.
 * 
 * ## Database Schema Conformance (DCC-0248)
 * 
 * ### Table References
 * This ECS module is designed to work with the following database tables:
 * - **dc_campaign_characters**: Runtime character/entity instances (unified library + campaign)
 * - **entity_instance.schema.json**: Runtime entity placement and state validation
 * - **character.schema.json**: Character data structure for character_data JSON column
 * 
 * ### Hot Columns (dc_campaign_characters)
 * The following hot columns provide O(1) indexed access for high-frequency game operations:
 * - **position_q**, **position_r**: Hex axial coordinates (maps to PositionComponent.q, .r)
 * - **hp_current**, **hp_max**: Hit points (maps to StatsComponent.currentHp, .maxHp)
 * - **armor_class**: AC for combat calculations (maps to StatsComponent.ac)
 * - **experience_points**: XP tracking for leveling
 * - **last_room_id**: Most recent room location
 * 
 * ### Unified JSON Structures
 * 
 * #### character_data (TEXT column)
 * Stores complete character sheet conforming to character.schema.json:
 * - abilities: {str, dex, con, int, wis, cha} → StatsComponent.abilities
 * - hit_points: {max, current, temp} → StatsComponent HP tracking
 * - level, skills, feats, equipment, spells
 * - All PF2e character data for character creation and display
 * 
 * #### state_data (TEXT column)
 * Campaign-scoped runtime state conforming to entity_instance.state structure:
 * - active, destroyed, disabled, hidden, collected → lifecycle flags
 * - hit_points: {current, max} → synced with hot columns
 * - inventory: array of {content_id, quantity} items
 * - metadata: extensible object for entity-specific runtime data
 *   - Examples: patrol_pattern, aggression_level, detected, triggered_count
 *   - Max 50 properties, accepts: string, number, boolean, object, array, null
 * 
 * ### Component-to-Schema Mapping
 * 
 * | Component | Hot Columns | JSON Fields | Schema Reference |
 * |-----------|-------------|-------------|------------------|
 * | PositionComponent | position_q, position_r | placement.hex.{q,r} | entity_instance.placement.hex |
 * | StatsComponent | hp_current, hp_max, armor_class | character_data.abilities, hit_points | character.abilities, character.hit_points |
 * | CombatComponent | — | state_data.metadata.{initiative, team, inCombat} | entity_instance.state.metadata |
 * | ActionsComponent | — | state_data.metadata.{actionsRemaining, attacksMade} | entity_instance.state.metadata |
 * | MovementComponent | — | state_data.metadata.{movementRemaining, movementMode} | entity_instance.state.metadata |
 * | RenderComponent | — | state_data.metadata.{spriteKey, visible, tint} | entity_instance.state.metadata |
 * | IdentityComponent | type, name | character_data.{name, class, ancestry} | entity_instance.entity_type, entity_ref |
 * 
 * All components implement `toJSON()` and `fromJSON()` for serialization to these structures.
 * 
 * ### Persistence Strategy
 * - **Hot columns**: Updated on every position/HP change for fast queries
 * - **character_data**: Updated on character sheet changes (leveling, equipment, feats)
 * - **state_data**: Updated on runtime state changes (combat, movement, metadata)
 * - **Hybrid model**: Balance between indexed access and flexible JSON storage
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
 * 
 * @example
 * // Serialize components to database format
 * const position = entity.getComponent(PositionComponent);
 * const stats = entity.getComponent(StatsComponent);
 * 
 * // Hot column values
 * const hotColumns = {
 *   position_q: position.q,
 *   position_r: position.r,
 *   hp_current: stats.currentHp,
 *   hp_max: stats.maxHp,
 *   armor_class: stats.ac
 * };
 * 
 * // state_data JSON
 * const stateData = {
 *   active: true,
 *   hit_points: { current: stats.currentHp, max: stats.maxHp },
 *   metadata: {
 *     initiative: entity.getComponent(CombatComponent)?.initiativeResult,
 *     actionsRemaining: entity.getComponent(ActionsComponent)?.actionsRemaining
 *   }
 * };
 * 
 * @see {@link https://dungeoncrawler.life/schemas/entity_instance.schema.json}
 * @see {@link https://dungeoncrawler.life/schemas/character.schema.json}
 * @see dungeoncrawler_content.install - dungeoncrawler_content_schema()
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
