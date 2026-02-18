/**
 * @file CombatSystem.js
 * System for handling combat actions (attacks, damage, etc.).
 * 
 * SCHEMA CONFORMANCE NOTES (DCC-0249):
 * =====================================
 * This is a **client-side ECS system** that operates on runtime component data.
 * It does NOT directly persist to the database. Combat state changes (HP, position)
 * must be synchronized to the backend via API calls to persist to hot columns.
 * 
 * Database Hot Columns (dc_campaign_characters table):
 * - hp_current, hp_max: Synced from resources.hitPoints in state_data JSON
 * - armor_class: Synced from defenses.armorClass.total in state_data JSON
 * - position_q, position_r: Synced from position.{q,r} in state_data JSON
 * - experience_points: Synced from basicInfo.experiencePoints in state_data JSON
 * 
 * Hot columns are maintained by CharacterStateService::saveState() which extracts
 * these values from the JSON payload and updates both the state_data column and
 * the individual hot columns atomically. This provides:
 * 1. Fast queries for combat-critical data without JSON parsing
 * 2. Single source of truth (JSON payload is authoritative)
 * 3. Consistency guarantee via database transaction
 * 
 * PERSISTENCE FLOW:
 * =================
 * 1. CombatSystem modifies StatsComponent (e.g., HP via takeDamage())
 * 2. Frontend calls backend API endpoint (e.g., /api/character/{id}/update)
 * 3. Backend calls CharacterStateService::updateHitPoints() or saveState()
 * 4. CharacterStateService extracts hot columns from JSON and updates both atomically
 * 
 * IMPORTANT: Changes made to ECS components are transient until persisted via API.
 * 
 * @see CharacterStateService::saveState() for hot column synchronization
 * @see CharacterStateService::updateHitPoints() for HP-specific updates
 * @see dungeoncrawler_content.install for dc_campaign_characters schema
 */

import { System } from '../System.js';
import { ActionCost } from '../components/ActionsComponent.js';

/**
 * Attack result.
 */
export const AttackResult = {
  CRITICAL_HIT: 'critical_hit',
  HIT: 'hit',
  MISS: 'miss',
  CRITICAL_MISS: 'critical_miss'
};

/**
 * CombatSystem
 * 
 * Handles combat mechanics including:
 * - Attack rolls (d20 + bonuses vs AC)
 * - Damage rolls
 * - Critical hits/misses
 * - Multiple Attack Penalty (MAP)
 * - Damage application (transient - requires backend sync for persistence)
 * 
 * NOTE: This system operates on ECS components (StatsComponent, CombatComponent).
 * All state changes are runtime-only until synchronized to the database via
 * CharacterStateService API calls. See file header for schema conformance details.
 */
export class CombatSystem extends System {
  constructor(entityManager) {
    super(entityManager);
    this.priority = 30; // Run after turn management (10), before movement (50)
    
    // Callbacks for UI/animation
    this.onAttackCallback = null;
    this.onDamageCallback = null;
  }
  
  /**
   * Initialize system.
   */
  init() {
    console.log('CombatSystem initialized');
  }
  
  /**
   * Update system.
   * @param {number} deltaTime - Time elapsed since last update (ms)
   */
  update(deltaTime) {
    // Combat system is event-driven
  }
  
  /**
   * Roll a die.
   * @param {number} sides - Number of sides on the die
   * @returns {number} - Result (1 to sides)
   */
  rollDie(sides) {
    return Math.floor(Math.random() * sides) + 1;
  }
  
  /**
   * Roll multiple dice and sum.
   * @param {number} count - Number of dice
   * @param {number} sides - Sides per die
   * @returns {number} - Total
   */
  rollDice(count, sides) {
    let total = 0;
    for (let i = 0; i < count; i++) {
      total += this.rollDie(sides);
    }
    return total;
  }
  
  /**
   * Get attack bonus for an entity.
   * 
   * Reads from ECS components populated from database schema:
   * - CombatComponent.attackBonus: derived from character_data JSON
   * - CombatComponent.weaponProficiency: derived from character_data JSON
   * - StatsComponent.abilities.strength: derived from character_data JSON
   * 
   * These values should match the JSON payload structure used by CharacterStateService.
   * 
   * @param {Entity} attacker - Attacking entity
   * @returns {number} - Attack bonus
   */
  getAttackBonus(attacker) {
    const combat = attacker.getComponent('CombatComponent');
    const stats = attacker.getComponent('StatsComponent');
    
    if (!combat || !stats) {
      return 0;
    }
    
    // Base attack bonus + ability modifier (using STR for now)
    const strMod = stats.getAbilityModifier('strength');
    return combat.attackBonus + combat.weaponProficiency + strMod;
  }
  
  /**
   * Perform an attack roll.
   * 
   * SCHEMA CONFORMANCE:
   * - Reads AC from StatsComponent.ac (maps to hot column armor_class)
   * - AC is extracted from state_data -> defenses.armorClass.total
   * - Attack calculations are client-side only; results must be sent to backend
   * 
   * @param {Entity} attacker - Attacking entity
   * @param {Entity} target - Target entity
   * @returns {Object} - {result, roll, total, ac, damage}
   */
  makeAttack(attacker, target) {
    const attackerCombat = attacker.getComponent('CombatComponent');
    const attackerActions = attacker.getComponent('ActionsComponent');
    const targetStats = target.getComponent('StatsComponent');
    const targetIdentity = target.getComponent('IdentityComponent');
    
    if (!attackerCombat || !targetStats) {
      console.warn('Missing components for attack');
      return null;
    }
    
    // Get Multiple Attack Penalty if ActionsComponent exists
    let mapPenalty = 0;
    if (attackerActions) {
      const mapResult = attackerActions.makeAttack();
      if (mapResult === null) {
        console.warn('No actions remaining to attack');
        return null;
      }
      mapPenalty = mapResult;
    }
    
    // Roll attack (d20)
    const attackRoll = this.rollDie(20);
    
    // Calculate attack total
    const attackBonus = this.getAttackBonus(attacker);
    const attackTotal = attackRoll + attackBonus + mapPenalty;
    
    // Get target AC (from hot column armor_class via StatsComponent)
    const targetAC = targetStats.ac;
    
    // Determine result
    let result;
    if (attackRoll === 20) {
      result = AttackResult.CRITICAL_HIT;
    } else if (attackRoll === 1) {
      result = AttackResult.CRITICAL_MISS;
    } else if (attackTotal >= targetAC) {
      // Check for critical hit (beat AC by 10+)
      if (attackTotal >= targetAC + 10) {
        result = AttackResult.CRITICAL_HIT;
      } else {
        result = AttackResult.HIT;
      }
    } else {
      result = AttackResult.MISS;
    }
    
    // Roll damage if hit or critical
    let damage = 0;
    if (result === AttackResult.HIT || result === AttackResult.CRITICAL_HIT) {
      // Basic damage: 1d6 + STR mod
      const stats = attacker.getComponent('StatsComponent');
      const strMod = stats ? stats.getAbilityModifier('strength') : 0;
      damage = this.rollDice(1, 6) + strMod;
      
      // Double damage dice on critical hit
      if (result === AttackResult.CRITICAL_HIT) {
        damage += this.rollDice(1, 6);
      }
      
      // Apply damage to target (transient - requires API sync)
      this.applyDamage(target, damage);
    }
    
    const attackData = {
      attacker: attacker,
      target: target,
      result: result,
      attackRoll: attackRoll,
      attackTotal: attackTotal,
      mapPenalty: mapPenalty,
      targetAC: targetAC,
      damage: damage
    };
    
    // Log attack
    const attackerName = attacker.getComponent('IdentityComponent')?.name || `Entity ${attacker.id}`;
    const targetName = targetIdentity?.name || `Entity ${target.id}`;
    
    console.log(`${attackerName} attacks ${targetName}: ${attackRoll} + ${attackBonus} + ${mapPenalty} = ${attackTotal} vs AC ${targetAC}`);
    console.log(`Result: ${result}${damage > 0 ? `, Damage: ${damage}` : ''}`);
    
    // Trigger callback for UI/animation
    if (this.onAttackCallback) {
      this.onAttackCallback(attackData);
    }
    
    return attackData;
  }
  
  /**
   * Apply damage to an entity.
   * 
   * SCHEMA CONFORMANCE: This method modifies StatsComponent.currentHp which maps to:
   * - Hot Column: dc_campaign_characters.hp_current
   * - JSON Path: state_data -> resources.hitPoints.current
   * 
   * Changes are TRANSIENT until persisted via backend API call.
   * Frontend should call CharacterStateService::updateHitPoints() or saveState()
   * to synchronize hp_current hot column with the JSON payload.
   * 
   * @param {Entity} target - Target entity
   * @param {number} damage - Damage amount
   * @returns {boolean} - True if target is still alive
   */
  applyDamage(target, damage) {
    const stats = target.getComponent('StatsComponent');
    const combat = target.getComponent('CombatComponent');
    
    if (!stats) {
      return true;
    }
    
    // Apply damage (transient change - requires API sync for persistence)
    const actualDamage = stats.takeDamage(damage);
    
    // Check if defeated
    if (!stats.isAlive() && combat) {
      combat.defeat();
      console.log(`${target.getComponent('IdentityComponent')?.name || target.id} has been defeated!`);
    }
    
    // Trigger callback for UI/animation
    if (this.onDamageCallback) {
      this.onDamageCallback({
        target: target,
        damage: actualDamage,
        remainingHp: stats.currentHp,
        maxHp: stats.maxHp,
        defeated: !stats.isAlive()
      });
    }
    
    return stats.isAlive();
  }
  
  /**
   * Heal an entity.
   * 
   * SCHEMA CONFORMANCE: This method modifies StatsComponent.currentHp which maps to:
   * - Hot Column: dc_campaign_characters.hp_current
   * - JSON Path: state_data -> resources.hitPoints.current
   * 
   * Changes are TRANSIENT until persisted via backend API call.
   * 
   * @param {Entity} target - Target entity
   * @param {number} amount - Healing amount
   * @returns {number} - Actual HP healed
   */
  heal(target, amount) {
    const stats = target.getComponent('StatsComponent');
    
    if (!stats) {
      return 0;
    }
    
    // Apply healing (transient change - requires API sync for persistence)
    const healed = stats.heal(amount);
    
    console.log(`${target.getComponent('IdentityComponent')?.name || target.id} healed for ${healed} HP`);
    
    return healed;
  }
  
  /**
   * Check if entity can attack target.
   * 
   * SCHEMA CONFORMANCE:
   * - Position checks use PositionComponent (q, r coordinates)
   * - Position maps to hot columns: position_q, position_r
   * - Position is extracted from state_data -> position.{q,r}
   * 
   * @param {Entity} attacker - Attacking entity
   * @param {Entity} target - Target entity
   * @returns {Object} - {canAttack, reason}
   */
  canAttack(attacker, target) {
    // Check if entities exist
    if (!attacker || !target) {
      return { canAttack: false, reason: 'Invalid entities' };
    }
    
    // Check if attacker has actions
    const actions = attacker.getComponent('ActionsComponent');
    if (actions && !actions.canAfford(ActionCost.ONE)) {
      return { canAttack: false, reason: 'No actions remaining' };
    }
    
    // Check if target is alive
    const targetStats = target.getComponent('StatsComponent');
    if (targetStats && !targetStats.isAlive()) {
      return { canAttack: false, reason: 'Target is defeated' };
    }
    
    // Check if entities are hostile
    const attackerCombat = attacker.getComponent('CombatComponent');
    const targetCombat = target.getComponent('CombatComponent');
    if (attackerCombat && targetCombat && !attackerCombat.isHostileTo(targetCombat)) {
      return { canAttack: false, reason: 'Target is not hostile' };
    }
    
    // Check range (for now, assume melee - must be adjacent)
    // Position coordinates (q, r) map to hot columns for fast spatial queries
    const attackerPos = attacker.getComponent('PositionComponent');
    const targetPos = target.getComponent('PositionComponent');
    if (attackerPos && targetPos) {
      const distance = attackerPos.distanceTo(targetPos);
      if (distance > 1) {
        return { canAttack: false, reason: 'Target out of range' };
      }
    }
    
    return { canAttack: true, reason: null };
  }
  
  /**
   * Execute attack action (checks actions, performs attack).
   * @param {Entity} attacker - Attacking entity
   * @param {Entity} target - Target entity
   * @returns {Object|null} - Attack data or null if failed
   */
  attack(attacker, target) {
    // Check if attack is valid
    const check = this.canAttack(attacker, target);
    if (!check.canAttack) {
      console.warn(`Cannot attack: ${check.reason}`);
      return null;
    }

    // Perform attack (consumes actions inside makeAttack via ActionsComponent.makeAttack)
    return this.makeAttack(attacker, target);
  }
  
  /**
   * Register callback for attack events.
   * @param {Function} callback - Callback function(attackData)
   */
  onAttack(callback) {
    this.onAttackCallback = callback;
  }
  
  /**
   * Register callback for damage events.
   * @param {Function} callback - Callback function(damageData)
   */
  onDamage(callback) {
    this.onDamageCallback = callback;
  }
  
  /**
   * Cleanup system.
   */
  destroy() {
    console.log('CombatSystem destroyed');
  }
}
