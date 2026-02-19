/**
 * @file
 * Hex map rendering with PixiJS + ECS architecture.
 */

// Import ECS modules
import { EntityManager, PositionComponent, RenderComponent, IdentityComponent, EntityType, RenderSystem, MovementComponent, StatsComponent, MovementSystem, MovementMode, ActionsComponent, ActionType, ActionCost, CombatComponent, Team, TurnManagementSystem, CombatState, CombatSystem, AttackResult } from './ecs/index.js';
import combatApi from './hexmap-api.js';

// Ensure Drupal and once are available
/* global Drupal, once, PIXI */

(function (Drupal, once) {
  'use strict';

  /**
   * UIManager - Handles all DOM interactions and UI updates.
   * Decouples business logic from DOM manipulation.
   */
  class UIManager {
    constructor(stateManager = null) {
      this.stateManager = stateManager;
      this.elements = {};
      this.ensureActionFooter();
      this.setupActionFooterToggle();
      this.setupFullscreenToggle();
      this.cacheElements();
      this.setupChatLog();
    }

    /**
     * Ensure the action footer exists in the DOM even if the template is missing it.
     */
    ensureActionFooter() {
      if (document.getElementById('action-footer')) {
        return;
      }

      const host = document.getElementById('hexmap-canvas-container') || document.body;
      const footer = document.createElement('div');
      footer.id = 'action-footer';
      footer.className = 'action-footer';

      footer.innerHTML = `
        <div class="action-footer__toggle" id="action-footer-toggle">Actions ▾</div>
        <div class="action-section" data-section="controls">
          <div class="action-section__header"><span>Actions</span><span class="action-section__chevron">▾</span></div>
          <div class="action-section__body">
            <div class="action-buttons" id="action-menu">
              <button id="action-move" class="btn btn-ghost">Move</button>
              <button id="action-attack" class="btn btn-primary">Attack</button>
              <button id="action-interact" class="btn btn-ghost">Interact</button>
              <button id="action-talk" class="btn btn-ghost">Talk</button>
              <button id="end-turn" class="btn btn-primary" style="display:none;">End Turn</button>
            </div>
            <p id="action-instruction" class="action-instruction">Select a hostile target to attack.</p>
          </div>
        </div>`;

      host.appendChild(footer);
    }

    setupActionFooterToggle() {
      const footer = document.getElementById('action-footer');
      if (!footer) {
        return;
      }

      const toggle = footer.querySelector('#action-footer-toggle');
      if (toggle && toggle.dataset.bound !== 'true') {
        toggle.dataset.bound = 'true';
        toggle.addEventListener('click', () => {
          const collapsed = footer.classList.toggle('collapsed');
          toggle.textContent = collapsed ? 'Actions ▸' : 'Actions ▾';
        });
      }

      const sections = footer.querySelectorAll('.action-section');
      sections.forEach((section) => {
        const header = section.querySelector('.action-section__header');
        const body = section.querySelector('.action-section__body');
        if (!header || !body || header.dataset.bound === 'true') {
          return;
        }
        header.dataset.bound = 'true';
        header.addEventListener('click', () => {
          const collapsed = section.classList.toggle('collapsed');
          const chevron = section.querySelector('.action-section__chevron');
          if (chevron) {
            chevron.textContent = collapsed ? '▸' : '▾';
          }
        });
      });

      this.applyInitialSectionState(footer, sections);
    }

    applyInitialSectionState(footer, sections) {
      if (!footer || footer.dataset.initialStateApplied === 'true') {
        return;
      }

      const isMobile = window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
      if (isMobile && sections && sections.length) {
        sections.forEach((section) => {
          section.classList.add('collapsed');
          const chevron = section.querySelector('.action-section__chevron');
          if (chevron) {
            chevron.textContent = '▸';
          }
        });
      }

      footer.dataset.initialStateApplied = 'true';
    }

    setupFullscreenToggle() {
      const btn = document.getElementById('fullscreen-toggle');
      if (!btn || btn.dataset.bound === 'true') {
        return;
      }

      btn.dataset.bound = 'true';
      btn.addEventListener('click', () => {
        const container = document.getElementById('hexmap-container');
        if (!container) {
          return;
        }

        const isFullscreen = document.fullscreenElement !== null;

        if (isFullscreen) {
          // Exit fullscreen
          document.exitFullscreen().catch(() => {});
          btn.textContent = '⛶';
          container.classList.remove('fullscreen');
        } else {
          // Enter fullscreen
          container.requestFullscreen().catch(() => {});
          btn.textContent = '⛌';
          container.classList.add('fullscreen');
        }
      });

      // Listen for fullscreen change events (e.g., user presses Esc)
      document.addEventListener('fullscreenchange', () => {
        const btn = document.getElementById('fullscreen-toggle');
        const isFullscreen = document.fullscreenElement !== null;
        if (btn) {
          btn.textContent = isFullscreen ? '⛌' : '⛶';
          const container = document.getElementById('hexmap-container');
          if (container) {
            container.classList.toggle('fullscreen', isFullscreen);
          }
        }
      });
    }

    /**
     * Cache frequently accessed DOM elements.
     */
    cacheElements() {
      this.elements = {
        hoveredHex: document.getElementById('hovered-hex'),
        hoveredObject: document.getElementById('hovered-object'),
        selectedHex: document.getElementById('selected-hex'),
        currentRound: document.getElementById('current-round'),
        initiativeList: document.getElementById('initiative-list'),
        combatControls: document.getElementById('combat-controls'),
        startCombatBtn: document.getElementById('start-combat'),
        endCombatBtn: document.getElementById('end-combat'),
        initiativeTracker: document.getElementById('initiative-tracker'),
        entityInfoPanel: document.getElementById('entity-info-panel'),
        entityName: document.getElementById('entity-name'),
        entityType: document.getElementById('entity-type'),
        entityTeam: document.getElementById('entity-team'),
        entityHp: document.getElementById('entity-hp'),
        entityAc: document.getElementById('entity-ac'),
        entityActions: document.getElementById('entity-actions'),
        entityMovement: document.getElementById('entity-movement'),
        selectedObjectType: document.getElementById('selected-object-type'),
        zoomLevel: document.getElementById('zoom-level'),
        hexDetailRoom: document.getElementById('hex-detail-room'),
        hexDetailTerrain: document.getElementById('hex-detail-terrain'),
        hexDetailElevation: document.getElementById('hex-detail-elevation'),
        hexDetailLighting: document.getElementById('hex-detail-lighting'),
        hexDetailPassability: document.getElementById('hex-detail-passability'),
        hexDetailObjects: document.getElementById('hex-detail-objects'),
        hexDetailEntities: document.getElementById('hex-detail-entities'),
        hexDetailConnection: document.getElementById('hex-detail-connection'),

        // Turn clarity HUD
        turnHud: document.getElementById('turn-hud'),
        turnOwner: document.getElementById('turn-owner'),
        turnActionSummary: document.getElementById('turn-action-summary'),
        turnMoveSummary: document.getElementById('turn-move-summary'),
        turnReaction: document.getElementById('turn-reaction'),
        turnActionChips: document.getElementById('turn-action-chips'),
        actionInstruction: document.getElementById('action-instruction'),
        actionMenu: document.getElementById('action-menu'),
        actionMoveBtn: document.getElementById('action-move'),
        actionAttackBtn: document.getElementById('action-attack'),
        actionInteractBtn: document.getElementById('action-interact'),
        actionTalkBtn: document.getElementById('action-talk'),
        endTurnBtn: document.getElementById('end-turn'),

        // Character sheet panel
        characterName: document.getElementById('char-name'),
        characterType: document.getElementById('char-type'),
        characterAncestry: document.getElementById('char-ancestry'),
        characterLevel: document.getElementById('char-level'),
        characterHp: document.getElementById('char-hp'),
        characterAc: document.getElementById('char-ac'),
        characterHero: document.getElementById('char-hero'),
        characterXp: document.getElementById('char-xp'),
        characterStr: document.getElementById('char-str'),
        characterStrMod: document.getElementById('char-str-mod'),
        characterDex: document.getElementById('char-dex'),
        characterDexMod: document.getElementById('char-dex-mod'),
        characterCon: document.getElementById('char-con'),
        characterConMod: document.getElementById('char-con-mod'),
        characterInt: document.getElementById('char-int'),
        characterIntMod: document.getElementById('char-int-mod'),
        characterWis: document.getElementById('char-wis'),
        characterWisMod: document.getElementById('char-wis-mod'),
        characterCha: document.getElementById('char-cha'),
        characterChaMod: document.getElementById('char-cha-mod'),
        characterFort: document.getElementById('char-fort'),
        characterRef: document.getElementById('char-ref'),
        characterWill: document.getElementById('char-will'),
        characterSkills: document.getElementById('char-skills'),
        characterConditions: document.getElementById('char-conditions'),
        characterGp: document.getElementById('char-gp'),
        characterSp: document.getElementById('char-sp'),
        characterCp: document.getElementById('char-cp'),
        characterInventory: document.getElementById('char-inventory'),
        characterFeatures: document.getElementById('char-features'),

        // Dialog log & chat
        chatLog: document.getElementById('chat-log'),
        chatForm: document.getElementById('chat-form'),
        chatInput: document.getElementById('chat-input'),
        chatSend: document.getElementById('chat-send')
      };

      this.setupCharacterSheetSections();
    }

    /**
     * Setup collapsible character sheet sections.
     */
    setupCharacterSheetSections() {
      const sectionHeaders = document.querySelectorAll('.character-sheet__section .section-header');
      sectionHeaders.forEach(header => {
        if (header.dataset.bound === 'true') return;
        header.dataset.bound = 'true';

        header.addEventListener('click', () => {
          const section = header.closest('.character-sheet__section');
          const sectionName = header.dataset.section;
          const body = section.querySelector(`.section-body[data-section="${sectionName}"]`);
          const toggle = header.querySelector('.section-toggle');

          if (!body || !toggle) return;

          const isCollapsed = section.classList.contains('collapsed');

          if (isCollapsed) {
            section.classList.remove('collapsed');
            body.style.display = '';
            toggle.textContent = '▾';
          } else {
            section.classList.add('collapsed');
            body.style.display = 'none';
            toggle.textContent = '▸';
          }
        });
      });
    }

    /**
     * Update hovered hex display.
     */
    updateHoveredHex(q, r) {
      if (this.elements.hoveredHex) {
        this.elements.hoveredHex.textContent = q !== null ? `(${q}, ${r})` : 'None';
      }
    }

    /**
     * Update hovered object label display.
     */
    updateHoveredObject(label) {
      if (this.elements.hoveredObject) {
        this.elements.hoveredObject.textContent = label || 'None';
      }
    }

    /**
     * Update selected hex display.
     */
    updateSelectedHex(q, r) {
      if (this.elements.selectedHex) {
        this.elements.selectedHex.textContent = `(${q}, ${r})`;
      }
    }

    /**
     * Update current turn display.
     */
    updateCurrentTurn(name, actions, movement, hasReaction, team = null, isPlayersTurn = false) {
      if (this.elements.currentTurn) {
        const turnLabel = isPlayersTurn ? 'Your turn' : (team ? `${team} turn` : 'Turn');
        const reactionBadge = hasReaction ? '<span class="pill pill-positive">Reaction ready</span>' : '<span class="pill pill-muted">Reaction spent</span>';
        this.elements.currentTurn.innerHTML = `
          <div class="turn-name">${name}</div>
          <div class="turn-sub">
            <span class="pill pill-strong">${turnLabel}</span>
            ${reactionBadge}
          </div>`;
      }

      if (this.elements.turnOwner) {
        this.elements.turnOwner.textContent = isPlayersTurn ? 'Your turn' : (team ? `${team} turn` : 'Awaiting combat');
      }

      const maxActions = actions ? actions.maxActions + (actions.actionBonus || 0) : null;
      if (this.elements.turnActionSummary) {
        const remaining = actions ? `${actions.actionsRemaining}/${maxActions} actions` : 'Actions: -';
        this.elements.turnActionSummary.textContent = remaining;
      }

      if (this.elements.turnMoveSummary) {
        const moveText = movement && Number.isFinite(movement.movementRemaining)
          ? `${movement.movementRemaining} ft left`
          : 'Movement: -';
        this.elements.turnMoveSummary.textContent = moveText;
      }

      if (this.elements.turnReaction) {
        this.elements.turnReaction.textContent = hasReaction ? 'Reaction ready' : 'Reaction spent';
        this.elements.turnReaction.classList.toggle('pill-positive', !!hasReaction);
        this.elements.turnReaction.classList.toggle('pill-muted', !hasReaction);
      }

      if (this.elements.turnActionChips) {
        const canAct = actions ? actions.actionsRemaining > 0 : false;
        const moveLeft = movement ? movement.movementRemaining > 0 : false;
        this.elements.turnActionChips.innerHTML = `
          <span class="chip ${moveLeft ? 'chip-live' : 'chip-dim'}">Move</span>
          <span class="chip ${canAct ? 'chip-live' : 'chip-dim'}">Strike</span>
          <span class="chip ${canAct ? 'chip-live' : 'chip-dim'}">Interact</span>
          <span class="chip chip-live">Talk</span>
          <span class="chip chip-end">End Turn</span>`;
      }

      if (this.elements.actionInstruction) {
        if (!isPlayersTurn) {
          this.elements.actionInstruction.textContent = 'Watching enemy turn...';
        } else if (actions && actions.actionsRemaining > 0) {
          this.elements.actionInstruction.textContent = 'Select a hostile target to attack or click a blue hex to move.';
        } else if (movement && movement.movementRemaining > 0) {
          this.elements.actionInstruction.textContent = 'Move to a blue hex, then end turn.';
        } else {
          this.elements.actionInstruction.textContent = 'No actions left — end your turn.';
        }
      }

      this.renderActionButtons(actions, movement, isPlayersTurn);
    }

    /**
     * Update action mode buttons and instruction text.
     */
    updateActionMode(mode, { canAct = false, canInteract = false, moveLeft = 0, isPlayersTurn = false } = {}) {
      const { actionMoveBtn, actionAttackBtn, actionInteractBtn, actionInstruction } = this.elements;

      const setActive = (btn, active) => {
        if (!btn) return;
        btn.classList.toggle('btn-active', !!active);
      };

      setActive(actionMoveBtn, mode === 'move');
      setActive(actionAttackBtn, mode === 'attack');
      setActive(actionInteractBtn, mode === 'interact');

      if (actionMoveBtn) {
        actionMoveBtn.title = isPlayersTurn
          ? (moveLeft > 0 ? `${moveLeft} ft remaining` : 'No movement left')
          : 'Not your turn';
      }
      if (actionAttackBtn) {
        actionAttackBtn.title = isPlayersTurn
          ? (canAct ? 'Click an enemy to attack' : 'No actions remaining')
          : 'Not your turn';
      }
      if (actionInteractBtn) {
        actionInteractBtn.title = isPlayersTurn
          ? (canInteract ? 'Interact with nearby objects, doors, and room transitions' : 'No interaction actions available')
          : 'Not your turn';
      }

      if (actionInstruction) {
        if (!isPlayersTurn) {
          actionInstruction.textContent = 'Watching enemy turn...';
        } else if (mode === 'move') {
          actionInstruction.textContent = moveLeft > 0 ? `Click a blue hex to move (${moveLeft} ft left).` : 'No movement left; switch to attack or end turn.';
        } else if (mode === 'interact') {
          actionInstruction.textContent = canInteract ? 'Click an adjacent door, obstacle, or connection to interact.' : 'No interaction actions remaining; attack, move, or end turn.';
        } else {
          actionInstruction.textContent = canAct ? 'Select a hostile target to attack.' : 'No actions remaining; move or end turn.';
        }
      }
    }

    renderActionButtons(actions, movement, isPlayersTurn) {
      const { actionMoveBtn, actionAttackBtn, actionInteractBtn, actionTalkBtn, endTurnBtn } = this.elements;
      const maxActions = actions ? actions.maxActions + (actions.actionBonus || 0) : null;
      const actionsRemaining = actions ? actions.actionsRemaining : 0;
      const canAct = !!(isPlayersTurn && actions && actions.canAct !== false && actionsRemaining > 0);
      const canMove = !!(isPlayersTurn && movement && Number.isFinite(movement.movementRemaining) && movement.movementRemaining > 0);
      const canInteract = canAct;

      const applyDisabledState = (button, disabled) => {
        if (!button) {
          return;
        }
        button.classList.toggle('btn-disabled', !!disabled);
        button.disabled = !!disabled;
        button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
      };

      if (actionMoveBtn) {
        const moveLabel = movement && Number.isFinite(movement.movementRemaining)
          ? `Move (${movement.movementRemaining} ft)`
          : 'Move';
        actionMoveBtn.textContent = moveLabel;
        applyDisabledState(actionMoveBtn, !canMove);
      }

      if (actionAttackBtn) {
        const attackLabel = maxActions !== null
          ? `Attack (${actionsRemaining}/${maxActions})`
          : 'Attack';
        actionAttackBtn.textContent = attackLabel;
        applyDisabledState(actionAttackBtn, !canAct);
      }

      if (actionInteractBtn) {
        actionInteractBtn.textContent = maxActions !== null
          ? `Interact (${actionsRemaining}/${maxActions})`
          : 'Interact';
        applyDisabledState(actionInteractBtn, !canInteract);
      }

      if (actionTalkBtn) {
        actionTalkBtn.textContent = 'Talk (Free)';
        applyDisabledState(actionTalkBtn, !isPlayersTurn);
      }

      if (endTurnBtn) {
        applyDisabledState(endTurnBtn, !isPlayersTurn);
      }
    }

    /**
     * Update round display.
     */
    updateRound(roundNumber) {
      if (this.elements.currentRound) {
        this.elements.currentRound.textContent = `Round ${roundNumber}`;
      }
    }

    /**
     * Update initiative tracker.
     */
    updateInitiativeTracker(initiativeOrder) {
      if (!this.elements.initiativeList) return;

      let html = '';
      initiativeOrder.forEach((data) => {
        const activeClass = data.isCurrent ? 'active-turn' : '';
        const defeatedClass = data.isDefeated ? 'defeated' : '';
        html += `<div class="initiative-item ${activeClass} ${defeatedClass}">
          <span class="init-value">${data.initiative}</span>
          <span class="init-name">${data.name}</span>
        </div>`;
      });
      this.elements.initiativeList.innerHTML = html;
    }

    /**
     * Update combat controls visibility.
     */
    updateCombatControls(combatState) {
      const isInactive = (combatState === CombatState.INACTIVE || combatState === CombatState.ENDED);

      if (this.elements.startCombatBtn) {
        this.elements.startCombatBtn.style.display = isInactive ? 'inline-block' : 'none';
      }
      if (this.elements.endTurnBtn) {
        this.elements.endTurnBtn.style.display = isInactive ? 'none' : 'inline-block';
      }
      if (this.elements.endCombatBtn) {
        this.elements.endCombatBtn.style.display = isInactive ? 'none' : 'inline-block';
      }
      if (this.elements.initiativeTracker) {
        this.elements.initiativeTracker.style.display = isInactive ? 'none' : 'block';
      }

      if (this.elements.turnHud) {
        this.elements.turnHud.classList.toggle('hud-inactive', isInactive);
      }
      if (this.elements.turnOwner) {
        this.elements.turnOwner.textContent = isInactive ? 'No active combat' : 'Active encounter';
      }
    }

    /**
     * Show entity info panel.
     */
    showEntityInfo(entity) {
      if (!this.elements.entityInfoPanel) return;

      this.elements.entityInfoPanel.style.display = 'block';

      const identity = entity.getComponent('IdentityComponent');
      const stats = entity.getComponent('StatsComponent');
      const combat = entity.getComponent('CombatComponent');
      const actions = entity.getComponent('ActionsComponent');
      const movement = entity.getComponent('MovementComponent');

      if (this.elements.entityName) {
        this.elements.entityName.textContent = identity?.name || 'Unknown';
      }
      if (this.elements.entityType) {
        this.elements.entityType.textContent = identity?.entityType || '-';
      }
      if (this.elements.entityTeam) {
        this.elements.entityTeam.textContent = combat?.team || '-';
      }
      if (this.elements.entityHp) {
        this.elements.entityHp.textContent = stats ? `${stats.currentHp}/${stats.maxHp}` : '-';
      }
      if (this.elements.entityAc) {
        this.elements.entityAc.textContent = stats?.ac || '-';
      }
      if (this.elements.entityActions) {
        this.elements.entityActions.textContent = actions ? actions.getActionDisplay?.() || `${actions.actionsRemaining}/${actions.maxActions ?? actions.actionsRemaining} actions` : '-';
      }

      if (this.elements.characterName) {
        this.elements.characterName.textContent = identity?.name || 'Select a character';
      }

      if (this.elements.characterType) {
          this.elements.characterType.textContent = identity?.entityType || '-';
        }
        if (this.elements.characterTeam) {
          this.elements.characterTeam.textContent = combat?.team || '-';
        }
        if (this.elements.characterLevel) {
          const level = stats?.level || stats?.lvl;
          this.elements.characterLevel.textContent = level ? `Lvl ${level}` : 'Lvl —';
        }
        if (this.elements.characterHp) {
          this.elements.characterHp.textContent = stats ? `${stats.currentHp}/${stats.maxHp}` : '-';
        }
        if (this.elements.characterAc) {
          this.elements.characterAc.textContent = stats?.ac || '-';
        }
        if (this.elements.characterSpeed) {
          this.elements.characterSpeed.textContent = movement ? `${movement.movementSpeed} ft` : '-';
        }
        if (this.elements.characterActions) {
          this.elements.characterActions.textContent = actions
            ? (actions.getActionDisplay?.() || `${actions.actionsRemaining}/${actions.maxActions ?? actions.actionsRemaining} actions`)
            : '-';
        }
        if (this.elements.characterInventory) {
          // Placeholder until inventory wiring is implemented; keep the list readable.
          this.elements.characterInventory.innerHTML = '<li class="inventory-empty">Inventory not loaded in this demo</li>';
        }
    }

    /**
     * Display character sheet from either legacy launchCharacter or full API state.
     */
    showLaunchCharacter(launchCharacter) {
      if (!launchCharacter || typeof launchCharacter !== 'object') {
        return;
      }

      console.log('showLaunchCharacter received:', launchCharacter);

      // Support both legacy format and new API state format
      const state = launchCharacter.data || launchCharacter;
      const basicInfo = state.basicInfo || {};
      const abilities = state.abilities || {};
      const resources = state.resources || {};
      const defenses = state.defenses || {};
      const conditions = state.conditions || [];
      const skills = state.skills || [];
      const features = state.features || {};
      const feats = state.feats || []; // Direct feats array from legacy format
      const equipment = state.equipment || [];
      const inventory = state.inventory || resources.inventory || {};
      
      // Normalize ability scores (support both short 'str' and long 'strength' keys)
      const normalizeAbilities = (abs) => ({
        strength: abs.strength || abs.str || 10,
        dexterity: abs.dexterity || abs.dex || 10,
        constitution: abs.constitution || abs.con || 10,
        intelligence: abs.intelligence || abs.int || 10,
        wisdom: abs.wisdom || abs.wis || 10,
        charisma: abs.charisma || abs.cha || 10,
      });
      const normalizedAbilities = normalizeAbilities(abilities);

      // Basic info
      const name = basicInfo.name || state.name || launchCharacter.name || 'Selected character';
      const ancestry = basicInfo.ancestry || state.ancestry || launchCharacter.ancestry || '';
      const characterClass = basicInfo.class || state.class || launchCharacter.class || '';
      const level = Number(basicInfo.level || state.level || launchCharacter.level || 0);
      
      // Resources
      const hpCurrent = Number(resources.hitPoints?.current ?? state.hp_current ?? launchCharacter.hp_current ?? 0);
      const hpMax = Number(resources.hitPoints?.max ?? state.hp_max ?? launchCharacter.hp_max ?? 0);
      const heroCurrent = Number(resources.heroPoints?.current ?? state.hero_points ?? launchCharacter.hero_points ?? 1);
      const heroMax = Number(resources.heroPoints?.max ?? 3);
      const armorClass = Number(defenses.armorClass ?? state.armor_class ?? launchCharacter.armor_class ?? 0);
      const xp = Number(basicInfo.experiencePoints ?? state.experience_points ?? 0);
      
      // Currency (support both API format and legacy format)
      const currency = inventory.currency || state.currency || { 
        gp: state.gold || 0, 
        sp: 0, 
        cp: 0 
      };

      // Calculate ability modifiers
      const calcMod = (score) => {
        const mod = Math.floor((score - 10) / 2);
        return mod >= 0 ? `+${mod}` : `${mod}`;
      };

      // Update basic info
      if (this.elements.characterName) this.elements.characterName.textContent = name;
      if (this.elements.characterType) {
        const subtitleParts = [ancestry, characterClass].filter(Boolean);
        this.elements.characterType.textContent = subtitleParts.length ? subtitleParts.join(' ') : 'Type —';
      }
      if (this.elements.characterAncestry) this.elements.characterAncestry.textContent = ancestry || '—';
      if (this.elements.characterLevel) this.elements.characterLevel.textContent = level > 0 ? `Lvl ${level}` : 'Lvl —';

      // Update core stats
      if (this.elements.characterHp) {
        this.elements.characterHp.textContent = Number.isFinite(hpCurrent) && Number.isFinite(hpMax) ? `${hpCurrent}/${hpMax}` : '-';
      }
      if (this.elements.characterAc) {
        this.elements.characterAc.textContent = armorClass > 0 ? `${armorClass}` : '-';
      }
      if (this.elements.characterHero) {
        this.elements.characterHero.textContent = `${heroCurrent}/${heroMax}`;
      }

      // Update ability scores
      const abilityPairs = [
        ['Str', normalizedAbilities.strength],
        ['Dex', normalizedAbilities.dexterity],
        ['Con', normalizedAbilities.constitution],
        ['Int', normalizedAbilities.intelligence],
        ['Wis', normalizedAbilities.wisdom],
        ['Cha', normalizedAbilities.charisma]
      ];

      abilityPairs.forEach(([name, score]) => {
        const valueEl = this.elements[`character${name}`];
        const modEl = this.elements[`character${name}Mod`];
        if (valueEl) valueEl.textContent = score;
        if (modEl) modEl.textContent = calcMod(score);
      });

      // Update saving throws
      const saves = defenses.savingThrows || {};
      if (this.elements.characterFort) {
        const fort = saves.fortitude || defenses.fortitude || 0;
        this.elements.characterFort.textContent = fort >= 0 ? `+${fort}` : `${fort}`;
      }
      if (this.elements.characterRef) {
        const ref = saves.reflex || defenses.reflex || 0;
        this.elements.characterRef.textContent = ref >= 0 ? `+${ref}` : `${ref}`;
      }
      if (this.elements.characterWill) {
        const will = saves.will || defenses.will || 0;
        this.elements.characterWill.textContent = will >= 0 ? `+${will}` : `${will}`;
      }

      // Update skills
      if (this.elements.characterSkills) {
        if (Array.isArray(skills) && skills.length > 0) {
          this.elements.characterSkills.innerHTML = skills
            .map(skill => {
              const name = skill.name || skill;
              const bonus = skill.modifier !== undefined ? (skill.modifier >= 0 ? `+${skill.modifier}` : skill.modifier) : '';
              return `<li><span>${name}</span><span>${bonus}</span></li>`;
            })
            .join('');
        } else {
          this.elements.characterSkills.innerHTML = '<li class="skills-empty">No skills</li>';
        }
      }

      // Update conditions
      if (this.elements.characterConditions) {
        if (Array.isArray(conditions) && conditions.length > 0) {
          const conditionNames = conditions.map(c => typeof c === 'string' ? c : (c.name || 'Unknown'));
          this.elements.characterConditions.innerHTML = conditionNames
            .map(name => `<li>${name}</li>`)
            .join('');
        } else {
          this.elements.characterConditions.innerHTML = '<li class="conditions-empty">No conditions</li>';
        }
      }

      // Update currency
      if (this.elements.characterGp) this.elements.characterGp.textContent = currency.gp || 0;
      if (this.elements.characterSp) this.elements.characterSp.textContent = currency.sp || 0;
      if (this.elements.characterCp) this.elements.characterCp.textContent = currency.cp || 0;

      // Update inventory (support both equipment array and inventory.carried)
      if (this.elements.characterInventory) {
        const carried = inventory.carried || equipment || [];
        const worn = inventory.worn || {};
        const weapons = worn.weapons || [];
        const allItems = [...weapons, ...carried];
        
        if (allItems.length > 0) {
          this.elements.characterInventory.innerHTML = allItems
            .map(item => `<li>${item.name || item}</li>`)
            .join('');
        } else {
          this.elements.characterInventory.innerHTML = '<li class="inventory-empty">No items</li>';
        }
      }

      // Update features
      if (this.elements.characterFeatures) {
        const ancestryFeatures = features.ancestryFeatures || [];
        const classFeatures = features.classFeatures || [];
        const feats = features.feats || [];
        const allFeatures = [...ancestryFeatures, ...classFeatures, ...feats];
        
        if (allFeatures.length > 0) {
          this.elements.characterFeatures.innerHTML = allFeatures
            .map(feat => `<li>${feat.name || feat}</li>`)
            .join('');
        } else {
          this.elements.characterFeatures.innerHTML = '<li class="features-empty">No features</li>';
        }
      }
    }

    /**
     * Hide entity info panel.
     */
    hideEntityInfo() {
      if (this.elements.entityInfoPanel) {
        this.elements.entityInfoPanel.style.display = 'none';
      }
    }

    /**
     * Update selected object type display.
     */
    updateSelectedObjectType(type) {
      if (this.elements.selectedObjectType) {
        const displayName = type ? type.charAt(0).toUpperCase() + type.slice(1) : 'None';
        this.elements.selectedObjectType.textContent = `Selected: ${displayName}`;
      }
    }

    /**
     * Update zoom level display.
     */
    updateZoomLevel(scale) {
      if (this.elements.zoomLevel) {
        const zoomPercent = Math.round(scale * 100);
        this.elements.zoomLevel.textContent = `${zoomPercent}%`;
      }
    }

    /**
     * Update hovered hex detail panel.
     * @param {Object|null} details - Detail payload for the hovered hex.
     */
    updateHexDetails(details) {
      const fallback = {
        room: 'None',
        terrain: 'Unknown',
        elevation: '-',
        lighting: 'Unknown',
        passability: 'Unknown',
        objects: 'None',
        entities: 'None',
        connection: 'None'
      };

      const payload = details ? {
        room: details.roomName || fallback.room,
        terrain: details.terrain || fallback.terrain,
        elevation: Number.isFinite(details.elevationFt) ? `${details.elevationFt} ft` : fallback.elevation,
        lighting: details.lighting || fallback.lighting,
        passability: details.passability || fallback.passability,
        objects: Array.isArray(details.objects) && details.objects.length ? details.objects.join(', ') : fallback.objects,
        entities: Array.isArray(details.entities) && details.entities.length ? details.entities.join(', ') : fallback.entities,
        connection: details.connection || fallback.connection
      } : fallback;

      const map = {
        hexDetailRoom: payload.room,
        hexDetailTerrain: payload.terrain,
        hexDetailElevation: payload.elevation,
        hexDetailLighting: payload.lighting,
        hexDetailPassability: payload.passability,
        hexDetailObjects: payload.objects,
        hexDetailEntities: payload.entities,
        hexDetailConnection: payload.connection
      };

      Object.entries(map).forEach(([key, value]) => {
        if (this.elements[key]) {
          this.elements[key].textContent = value;
        }
      });
    }

    /**
     * Initialize the dialog log and chat input.
     */
    setupChatLog() {
      const form = this.elements.chatForm;
      const input = this.elements.chatInput;
      const log = this.elements.chatLog;

      if (!form || !input || !log || form.dataset.bound === 'true') {
        return;
      }

      form.dataset.bound = 'true';
      let isSubmitting = false;

      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        
        // Prevent double submission
        if (isSubmitting) {
          return;
        }

        const message = input.value.trim();
        if (!message) {
          return;
        }

        // Validate message length (matches backend limit)
        if (message.length > 2000) {
          this.appendChatLine('System', 'Message too long (max 2000 characters)', 'system');
          return;
        }

        // Get context from state manager
        const campaignId = this.stateManager.hexmap?.resolveCampaignId?.() || null;
        const roomId = this.stateManager.hexmap?.resolveActiveRoomId?.() || null;
        const characterData = this.stateManager.hexmap?.characterData || {};
        const characterName = characterData.name || 'You';
        const characterId = characterData.id || null;

        if (!campaignId || !roomId) {
          this.appendChatLine('System', 'Unable to send message: No active room', 'system');
          return;
        }

        // Set loading state
        isSubmitting = true;
        const sendButton = this.elements.chatSend;
        const originalText = sendButton?.textContent;
        if (sendButton) {
          sendButton.disabled = true;
          sendButton.textContent = 'Sending...';
        }

        // Clear input immediately for better UX
        input.value = '';

        // Send to server
        try {
          await this.postChatMessage(campaignId, roomId, characterName, message, characterId);
          // Message will appear when server confirms (or from loadChatHistory)
        } catch (error) {
          // Handle permission errors silently (user doesn't have access)
          if (error.message.includes('403')) {
            console.warn('Chat message send denied (permission)');
            // Don't show error in chat, don't restore message, just silently fail
          } else {
            console.error('Failed to send chat message:', error);
            this.appendChatLine('System', `Failed to send message: ${error.message}`, 'system');
            // Restore message to input so user can retry (non-permission errors only)
            input.value = message;
          }
        } finally {
          // Reset loading state
          isSubmitting = false;
          if (sendButton) {
            sendButton.disabled = false;
            sendButton.textContent = originalText || 'Send';
          }
        }
      });

      // Chat history will be loaded when room becomes active
      // (via state subscription or explicit call from room change handler)
    }

    async loadChatHistory() {
      const campaignId = this.stateManager.hexmap?.resolveCampaignId?.() || null;
      const roomId = this.stateManager.hexmap?.resolveActiveRoomId?.() || null;

      if (!campaignId || !roomId) {
        return;
      }

      try {
        const response = await fetch(`/api/campaign/${campaignId}/room/${roomId}/chat`);
        
        // Handle 403 (permission denied) gracefully  
        if (response.status === 403) {
          console.warn('Chat access denied for campaign:', campaignId);
          return; // Silently skip loading - user doesn't have access
        }
        
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();
        if (result.success && result.data?.messages) {
          // Clear existing messages
          const log = this.elements.chatLog;
          if (log) {
            log.innerHTML = '';
          }

          // Render all messages
          result.data.messages.forEach(msg => {
            this.appendChatLine(msg.speaker, msg.message, msg.type);
          });

          if (result.data.messages.length === 0) {
            this.appendChatLine('System', 'Welcome to the room. Start a conversation!', 'system');
          }
        }
      } catch (error) {
        console.error('Failed to load chat history:', error);
        // Don't show error message in chat, just log to console
        // The chat interface will still work for new messages
      }
    }

    async postChatMessage(campaignId, roomId, speaker, message, characterId = null) {
      const response = await fetch(`/api/campaign/${campaignId}/room/${roomId}/chat`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          speaker,
          message,
          type: 'player',
          character_id: characterId,
        }),
      });

      if (!response.ok) {
        const result = await response.json().catch(() => ({}));
        throw new Error(result.error || `HTTP ${response.status}`);
      }

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.error || 'Unknown error');
      }

      // Reload chat history to show confirmed message
      await this.loadChatHistory();

      return result;
    }

    appendChatLine(speaker, message, type = 'npc') {
      const log = this.elements.chatLog;
      if (!log) {
        return;
      }

      const line = document.createElement('div');
      line.className = `chat-line chat-line--${type}`;

      if (speaker) {
        const name = document.createElement('span');
        name.className = 'chat-line__speaker';
        name.textContent = `${speaker}:`;
        line.appendChild(name);
      }

      const text = document.createElement('span');
      text.textContent = message;
      line.appendChild(text);

      log.appendChild(line);
      log.scrollTop = log.scrollHeight;
    }
  }

  /**
   * StateManager - Centralized state management.
   * Provides a single source of truth for application state.
   */
  class StateManager {
    constructor() {
      this.state = {
        // Selection state
        selectedEntity: null,
        selectedHex: null,
        hoveredHex: null,
        selectedObjectType: null,
        
        // Movement state
        movementRange: null,
        movementRangeOverlay: null,
        
        // Combat state
        combatActive: false,
        serverCombatMode: false,
        attackTarget: null,
        
        // Drag state
        draggedObject: null,
        
        // Flags
        assetsLoaded: false,
        showCoordinates: false,
        showGrid: true
      };
      
      this.listeners = {};
    }

    /**
     * Get state value.
     */
    get(key) {
      return this.state[key];
    }

    /**
     * Set state value and notify listeners.
     */
    set(key, value) {
      const oldValue = this.state[key];
      this.state[key] = value;
      
      // Notify listeners
      if (this.listeners[key]) {
        this.listeners[key].forEach(callback => callback(value, oldValue));
      }
    }

    /**
     * Subscribe to state changes.
     */
    subscribe(key, callback) {
      if (!this.listeners[key]) {
        this.listeners[key] = [];
      }
      this.listeners[key].push(callback);
      
      // Return unsubscribe function
      return () => {
        this.listeners[key] = this.listeners[key].filter(cb => cb !== callback);
      };
    }

    /**
     * Reset all state to defaults.
     */
    reset() {
      this.state = {
        selectedEntity: null,
        selectedHex: null,
        hoveredHex: null,
        selectedObjectType: null,
        movementRange: null,
        movementRangeOverlay: null,
        combatActive: false,
        serverCombatMode: false,
        attackTarget: null,
        draggedObject: null,
        assetsLoaded: false,
        showCoordinates: false,
        showGrid: true,
        showFog: true,
        fogOverlay: null,
        visibleHexes: null
      };
    }
  }

  /**
   * Hex map behavior.
   */
  Drupal.behaviors.hexMap = {
    // Configuration
    config: {
      hexSize: 30,
      gridWidth: 20,
      gridHeight: 20,
      minZoom: 0.5,
      maxZoom: 3.0,
      defaultVisionRange: 8,
      defaultWidth: 800,
      defaultHeight: 600,
      backgroundColor: 0x1a1a2e
    },
    
    // PixiJS containers
    app: null,
    hexContainer: null,
    gridContainer: null,
    objectContainer: null,
    uiContainer: null,
    
    // Managers
    uiManager: null,
    stateManager: null,
    
    // ECS architecture
    entityManager: null,
    renderSystem: null,
    movementSystem: null,
    turnManagementSystem: null,
    combatSystem: null,

    // Launch context from campaign/tavern flow.
    launchContext: {},

    // Current user id from drupalSettings.user.uid (0 for anonymous).
    currentUserId: 0,

    // Launch character summary from campaign flow for initial sheet hydration.
    launchCharacter: {},

    // Dungeon payload for room-aware rendering and transitions.
    dungeonData: {},
    activeRoomId: null,
    
    // Cleanup tracking
    eventListeners: [],
    stageListeners: [],
    tickerCallbacks: [],
    stateSubscriptions: [],

    attach: function (context, settings) {
      const container = once('hexmap-init', '#hexmap-canvas-container', context);
      
      if (container.length === 0) {
        return;
      }

      // Initialize managers
      this.uiManager = new UIManager();
      this.stateManager = new StateManager();
      this.uiManager.stateManager = this.stateManager; // Give UIManager access to state manager
      this.stateManager.hexmap = this; // Allow state manager to reference hexmap methods
      this.setupStateSubscriptions();
      
      // Load dungeon data from drupalSettings (populated by HexMapController.php)
      // Data flow: dc_campaign_dungeons.dungeon_data (JSON column) -> HexMapController::normalizeDungeonPayload() -> drupalSettings
      // Schema: dungeon_level.schema.json + hexmap.schema.json + entity_instance.schema.json
      this.launchContext = settings?.dungeoncrawlerContent?.hexmapLaunchContext || {};
      this.dungeonData = settings?.dungeoncrawlerContent?.hexmapDungeonData || {};
      this.launchCharacter = settings?.dungeoncrawlerContent?.hexmapLaunchCharacter || {};

      console.log('HexMap Init - Launch Context:', this.launchContext);
      console.log('HexMap Init - Launch Character:', this.launchCharacter);
      console.log('HexMap Init - Has Dungeon Data:', Object.keys(this.dungeonData).length > 0);
      this.activeRoomId = this.dungeonData?.active_room_id || null;
      this.currentUserId = Number(settings?.user?.uid || 0);

      this.initPixiApp(container[0]);
      this.initECS(); // Initialize ECS architecture
      this.generateHexGrid();
      this.setupControls();
      this.setupInteraction();
      this.applyDungeonData();
      this.applyLaunchContext();

      try {
        const launchEntitySelected = this.applyLaunchCharacterSelection();
        if (!launchEntitySelected) {
          this.applyLaunchCharacterSummary();
        }
      } catch (error) {
        console.error('Launch character hydration failed; falling back to summary.', error);
        this.applyLaunchCharacterSummary();
      }
      
      // Start game loop and track for cleanup
      const updateCallback = (delta) => this.update(delta);
      this.app.ticker.add(updateCallback);
      this.tickerCallbacks.push(updateCallback);
    },
    
    /**
     * Detach behavior and cleanup resources.
     */
    detach: function (context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      
      console.log('Cleaning up hexmap resources...');
      
      // Remove ticker callbacks
      this.tickerCallbacks.forEach(callback => {
        if (this.app && this.app.ticker) {
          this.app.ticker.remove(callback);
        }
      });
      this.tickerCallbacks = [];
      
      // Remove event listeners
      this.eventListeners.forEach(({ element, event, handler }) => {
        element.removeEventListener(event, handler);
      });
      this.eventListeners = [];

      // Remove stage listeners
      this.stageListeners.forEach(({ event, handler }) => {
        if (this.app && this.app.stage) {
          this.app.stage.off(event, handler);
        }
      });
      this.stageListeners = [];

      // Unsubscribe state listeners
      this.stateSubscriptions.forEach(unsubscribe => unsubscribe());
      this.stateSubscriptions = [];
      
      // Cleanup ECS systems
      if (this.entityManager) {
        this.entityManager.removeAllEntities();
        this.entityManager = null;
      }
      
      // Cleanup PixiJS
      const movementRangeOverlay = this.stateManager ? this.stateManager.get('movementRangeOverlay') : null;
      if (movementRangeOverlay) {
        movementRangeOverlay.destroy();
        this.stateManager.set('movementRangeOverlay', null);
      }

      const fogOverlay = this.stateManager ? this.stateManager.get('fogOverlay') : null;
      if (fogOverlay) {
        fogOverlay.destroy();
        this.stateManager.set('fogOverlay', null);
      }
      
      if (this.app) {
        this.app.destroy(true, { children: true, texture: false, baseTexture: false });
        this.app = null;
      }
      
      // Reset state
      if (this.stateManager) {
        this.stateManager.reset();
      }

      this.launchContext = {};
      this.dungeonData = {};
      this.launchCharacter = {};
      this.activeRoomId = null;
      
      console.log('Hexmap cleanup complete');
    },
    
    /**
     * Initialize ECS architecture.
     */
    initECS: function () {
      // Store self reference for callbacks
      const self = this;
      
      // Create entity manager
      this.entityManager = new EntityManager();
      
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
      this.renderSystem.setHexSize(this.config.hexSize);
      this.entityManager.addSystem(this.renderSystem);
      
      // Create movement system
      this.movementSystem = new MovementSystem(this.entityManager);
      this.entityManager.addSystem(this.movementSystem);
      
      // Create combat system
      this.combatSystem = new CombatSystem(this.entityManager);
      this.entityManager.addSystem(this.combatSystem);
      
      // Set up combat callbacks
      this.combatSystem.onAttack(function(attackData) {
        self.onAttackPerformed(attackData);
      });
      this.combatSystem.onDamage(function(damageData) {
        self.onDamageDealt(damageData);
      });
      
      // Create turn management system
      this.turnManagementSystem = new TurnManagementSystem(this.entityManager);
      this.entityManager.addSystem(this.turnManagementSystem);
      
      // Set up turn management callbacks
      this.turnManagementSystem.onTurnChange(function(entity, turnIndex, totalTurns) {
        self.onTurnChange(entity, turnIndex, totalTurns);
      });
      this.turnManagementSystem.onRoundChange(function(roundNumber) {
        self.onRoundChange(roundNumber);
      });
      this.turnManagementSystem.onCombatStateChange(function(combatState) {
        self.onCombatStateChange(combatState);
      });
      
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
     * Setup state subscriptions for reactive UI updates.
     */
    setupStateSubscriptions: function () {
      this.stateSubscriptions.push(
        this.stateManager.subscribe('selectedObjectType', (value) => {
          this.uiManager.updateSelectedObjectType(value);
        })
      );

      this.stateSubscriptions.push(
        this.stateManager.subscribe('showGrid', (value) => {
          if (this.gridContainer) {
            this.gridContainer.visible = value;
          }
        })
      );

      this.stateSubscriptions.push(
        this.stateManager.subscribe('showFog', () => {
          this.refreshFogOfWar();
        })
      );


      this.uiManager.updateSelectedObjectType(this.stateManager.get('selectedObjectType'));
    },

    /**
     * Set world layer position for all render containers.
     * @param {number} x - X coordinate
     * @param {number} y - Y coordinate
     */
    setWorldPosition: function (x, y) {
      this.hexContainer.x = x;
      this.hexContainer.y = y;
      this.gridContainer.x = x;
      this.gridContainer.y = y;
      this.objectContainer.x = x;
      this.objectContainer.y = y;
      this.uiContainer.x = x;
      this.uiContainer.y = y;
    },

    /**
     * Set world layer scale for all render containers.
     * @param {number} scale - Uniform scale value
     */
    setWorldScale: function (scale) {
      this.hexContainer.scale.set(scale);
      this.gridContainer.scale.set(scale);
      this.objectContainer.scale.set(scale);
      this.uiContainer.scale.set(scale);
    },

    /**
     * Clear all ECS entities and related overlays/state.
     */
    clearEntities: function () {
      if (!this.entityManager) {
        return;
      }

      this.deselectEntity();

      // End any existing combat before wiping entities so turn order resets cleanly.
      if (this.turnManagementSystem) {
        this.turnManagementSystem.endCombat();
      }

      this.entityManager.removeAllEntities();
      this.uiManager.hideEntityInfo();
      this.uiManager.updateCurrentTurn('-', null, null, false, null, false);
      this.uiManager.updateInitiativeTracker([]);
      console.log('Cleared all ECS entities');
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
      const movement = entity.getComponent('MovementComponent');
      const combat = entity.getComponent('CombatComponent');
      const name = identity ? identity.name : `Entity ${entity.id}`;
      const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');
      
      console.log(`Turn change: ${name} (${turnIndex + 1}/${totalTurns})`);
      
      // Update UI via UIManager
      this.uiManager.updateCurrentTurn(name, actions, movement, actions?.hasReactionAvailable(), combat?.team, isPlayersTurn);
      this.uiManager.updateInitiativeTracker(this.turnManagementSystem.getInitiativeOrder());
      const moveLeft = movement ? movement.movementRemaining : 0;
      const canAct = actions ? actions.actionsRemaining > 0 : false;
      const actionMode = this.stateManager.get('actionMode') || 'attack';
      this.uiManager.updateActionMode(actionMode, { canAct, canInteract: canAct, moveLeft, isPlayersTurn });
      
      // Auto-select entity on their turn (if player controlled). NPCs are resolved server-side.
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
      this.uiManager.updateRound(roundNumber);
      this.stateManager.set('actionMode', 'attack');
    },
    
    /**
     * Combat state change callback.
     * @param {string} combatState - New combat state
     */
    onCombatStateChange: function (combatState) {
      console.log(`Combat state: ${combatState}`);
      this.stateManager.set('combatActive', combatState === CombatState.IN_PROGRESS || combatState === CombatState.ROLLING_INITIATIVE);
      
      // Update UI
      this.uiManager.updateCombatControls(combatState);
    },

    /**
     * Initialize PixiJS application.
     */
    initPixiApp: function (container) {
      // Create PixiJS application
      this.app = new PIXI.Application({
        width: container.clientWidth || this.config.defaultWidth,
        height: container.clientHeight || this.config.defaultHeight,
        backgroundColor: this.config.backgroundColor,
        antialias: true,
        resolution: window.devicePixelRatio || 1,
        autoDensity: true,
      });

      container.appendChild(this.app.view);

      // Create containers for layers
      this.hexContainer = new PIXI.Container();
      this.gridContainer = new PIXI.Container();
      this.objectContainer = new PIXI.Container();
      this.uiContainer = new PIXI.Container();

      // Keep overlay layers deterministic; ui sits on top of sprites.
      this.app.stage.sortableChildren = true;
      this.hexContainer.zIndex = 10;
      this.gridContainer.zIndex = 20;
      this.objectContainer.zIndex = 30;
      this.uiContainer.zIndex = 40;
      
      // Add layers in order: hexes (terrain), grid (coords), objects (sprites), ui (overlays)
      this.app.stage.addChild(this.hexContainer);
      this.app.stage.addChild(this.gridContainer);
      this.app.stage.addChild(this.objectContainer);
      this.app.stage.addChild(this.uiContainer);

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

      // Reset transient UI state tied to previous hex graphics
      this.stateManager.set('hoveredHex', null);
      this.uiManager.updateHoveredHex(null, null);
      this.uiManager.updateHoveredObject('None');
      if (this.uiManager.elements.selectedHex) {
        this.uiManager.elements.selectedHex.textContent = 'None';
      }
      this.stateManager.set('selectedHex', null);
      this.hideMovementRange();

      const hexSize = this.config.hexSize;
      const width = this.config.gridWidth;
      const height = this.config.gridHeight;

      // Calculate hex dimensions
      const hexWidth = hexSize * 2;
      const hexHeight = Math.sqrt(3) * hexSize;

      // Generate grid (flat-top orientation)
      for (let q = -Math.floor(width / 2); q < Math.ceil(width / 2); q++) {
        for (let r = -Math.floor(height / 2); r < Math.ceil(height / 2); r++) {
          this.createHex(q, r, hexSize);
        }
      }

      // Reapply room/obstacle styling for the rebuilt grid
      this.paintActiveRoom();

      // If an entity remains selected, refresh its movement range overlay with the new grid sizing
      const selectedEntity = this.stateManager.get('selectedEntity');
      if (selectedEntity) {
        this.showMovementRange(selectedEntity);
      }

      this.refreshFogOfWar();

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
      if (this.stateManager.get('showCoordinates')) {
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
        const x = this.config.hexSize * Math.cos(angle);
        const y = this.config.hexSize * Math.sin(angle);
        
        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();

      this.stateManager.set('hoveredHex', hex);
      
      // Update UI
      const { q, r } = hex.hexData;
      this.uiManager.updateHoveredHex(q, r);
      this.uiManager.updateHoveredObject(this.getObjectLabelAtHex(q, r));
      this.uiManager.updateHexDetails(this.getHexDetail(q, r));
    },

    /**
     * Hex out event.
     */
    onHexOut: function (hex) {
      // Reset hex appearance (unless it's selected)
      if (this.stateManager.get('selectedHex') !== hex) {
        this.resetHexAppearance(hex);
      }

      this.stateManager.set('hoveredHex', null);
      this.uiManager.updateHoveredHex(null, null);
      this.uiManager.updateHoveredObject('None');
      this.uiManager.updateHexDetails(null);
    },

    /**
     * Hex click event.
     */
    onHexClick: function (hex) {
      const { q, r } = hex.hexData;
      
      // Mode 1: Object placement mode
      const selectedObjectType = this.stateManager.get('selectedObjectType');
      if (selectedObjectType) {
        // Map object type to EntityType
        let entityType;
        let name;
        switch (selectedObjectType) {
          case 'creature':
            entityType = EntityType.CREATURE;
            name = 'Creature';
            break;
          case 'item':
            entityType = EntityType.ITEM;
            name = 'Item';
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

      // Mode 1.5: Room transition if hex is a passable room connection endpoint.
      if (this.tryTransitionAtHex(q, r)) {
        return;
      }
      
      // Mode 2: Check if clicking on an entity
      const entitiesAtPos = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
      const selectedEntity = this.stateManager.get('selectedEntity');
      
      for (const entity of entitiesAtPos) {
        const pos = entity.getComponent('PositionComponent');
        if (pos.q === q && pos.r === r) {
          const actionMode = this.stateManager.get('actionMode') || 'attack';

          if (selectedEntity && actionMode === 'interact' && entity.id !== selectedEntity.id) {
            if (this.performInteractAtHex(selectedEntity, q, r, entity)) {
              return;
            }
          }

          // Check if this is an attack action (selected entity + hostile target)
              if (selectedEntity && entity.id !== selectedEntity.id) {
            const attackerCombat = selectedEntity.getComponent('CombatComponent');
            const targetCombat = entity.getComponent('CombatComponent');
            
                if (actionMode === 'attack' && attackerCombat && targetCombat && attackerCombat.isHostileTo(targetCombat)) {
              const canAttackCheck = this.combatSystem.canAttack(selectedEntity, entity);
              console.info('Click attack check', { actorId: selectedEntity.id, targetId: entity.id, mode: actionMode, check: canAttackCheck });
              if (!canAttackCheck.canAttack) {
                console.warn('Cannot attack target', canAttackCheck.reason);
                return;
              }
              // Attempt attack
              this.performAttack(selectedEntity, entity);
              return;
            }
          }
          
          // Otherwise select the entity if it has MovementComponent
          if (entity.hasComponent('MovementComponent')) {
            this.selectEntity(entity);
            return;
          }
        }
      }
      
      // Mode 3: Move selected entity
      const actionMode = this.stateManager.get('actionMode') || 'attack';
      let movementRange = this.stateManager.get('movementRange');

      // If we lost the cached range (e.g., after switching modes), rebuild so clicks still work.
      if (selectedEntity && actionMode === 'move' && (!movementRange || movementRange.size === 0)) {
        this.showMovementRange(selectedEntity);
        movementRange = this.stateManager.get('movementRange');
      }

      if (selectedEntity && movementRange) {
        const hexKey = `${q}_${r}`;
        if (movementRange.has(hexKey)) {
          if (actionMode !== 'move') {
            // Require explicit move mode to avoid accidental moves while targeting.
            this.uiManager.updateActionMode('attack', { canAct: true, canInteract: true, moveLeft: 0, isPlayersTurn: true });
            return;
          }
          // Try to move entity
          const success = this.movementSystem.moveEntity(selectedEntity, q, r);
          if (success) {
            console.log(`Moved entity to (${q}, ${r})`);
            // Refresh movement range after move
            this.showMovementRange(selectedEntity);
            const actions = selectedEntity.getComponent('ActionsComponent');
            const movementComp = selectedEntity.getComponent('MovementComponent');
            const combat = selectedEntity.getComponent('CombatComponent');
            const identity = selectedEntity.getComponent('IdentityComponent');
            const name = identity ? identity.name : `Entity ${selectedEntity.id}`;
            const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');
            if (actions && movementComp) {
              this.uiManager.updateCurrentTurn(name, actions, movementComp, actions.hasReactionAvailable(), combat?.team, isPlayersTurn);
            }
            this.refreshFogOfWar();
          }
          return;
        }
      }

      if (selectedEntity && actionMode === 'interact') {
        const interacted = this.performInteractAtHex(selectedEntity, q, r);
        if (interacted) {
          return;
        }
      }
      
      // Mode 4: Default hex selection
      // Deselect previous hex
      const previousSelectedHex = this.stateManager.get('selectedHex');
      if (previousSelectedHex) {
        this.onHexOut(previousSelectedHex);
      }

      this.setSelectedHex(hex);
      
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
    createEntityObject: function (q, r, entityType, name, spriteKey = null, options = {}) {
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
        const statsConfig = options.stats || {};
        const movementSpeed = options.movementSpeed ?? statsConfig.speed ?? 30;
        const actionsPerTurn = options.actionsPerTurn ?? 3;

        // Add stats
        const stats = new StatsComponent({ 
          speed: movementSpeed,
          maxHp: statsConfig.maxHp ?? 20,
          currentHp: statsConfig.currentHp ?? statsConfig.maxHp ?? 20,
          ac: statsConfig.ac ?? 10,
          perception: statsConfig.perception ?? 0
        });
        entity.addComponent('StatsComponent', stats);
        
        // Add movement
        const movement = new MovementComponent(movementSpeed);
        entity.addComponent('MovementComponent', movement);
        
        // Add actions (3-action economy)
        const actions = new ActionsComponent(actionsPerTurn);
        entity.addComponent('ActionsComponent', actions);
        
        // Add combat
        const team = this.resolveTeamPreference(options.team, entityType);
        const combat = new CombatComponent({ 
          team: team,
          initiativeBonus: statsConfig.initiative_bonus ?? options.initiativeBonus ?? 0,
          attackBonus: statsConfig.attack_bonus ?? 0
        });
        entity.addComponent('CombatComponent', combat);
      } else if (entityType === EntityType.ITEM || entityType === EntityType.OBSTACLE) {
        // Items/furniture should be targetable but not join initiative.
        const statsConfig = options.stats || {};
        const stats = new StatsComponent({
          speed: 0,
          maxHp: statsConfig.maxHp ?? 10,
          currentHp: statsConfig.currentHp ?? statsConfig.maxHp ?? 10,
          ac: statsConfig.ac ?? 10,
          perception: statsConfig.perception ?? 0
        });
        entity.addComponent('StatsComponent', stats);
      }
      
      console.log(`Created entity "${name}" (${entityType}) at (${q}, ${r})`);
      return entity;
    },

    /**
     * Resolve team preference to CombatComponent team value.
     */
    resolveTeamPreference: function (teamPreference, entityType) {
      const normalized = teamPreference ? String(teamPreference).toLowerCase() : null;
      if (normalized === 'player') {
        return Team.PLAYER;
      }
      if (normalized === 'ally') {
        return Team.ALLY;
      }
      if (normalized === 'neutral') {
        return Team.NEUTRAL;
      }
      if (normalized === 'enemy') {
        return Team.ENEMY;
      }

      return entityType === EntityType.PLAYER_CHARACTER ? Team.PLAYER : Team.ENEMY;
    },
    
    /**
     * Select an entity for movement.
     * @param {Entity} entity - Entity to select
     */
    selectEntity: function (entity) {
      // Deselect previous entity
      const previousEntity = this.stateManager.get('selectedEntity');
      if (previousEntity) {
        this.deselectEntity();
      }
      
      this.stateManager.set('selectedEntity', entity);
      // Default to attack mode when a new player entity is selected
      this.stateManager.set('actionMode', 'attack');
      
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
      
      // Show entity info panel via UIManager
      this.uiManager.showEntityInfo(entity);

      const actions = entity.getComponent('ActionsComponent');
      const combat = entity.getComponent('CombatComponent');
      const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');
      this.uiManager.updateActionMode('attack', {
        canAct: actions ? actions.actionsRemaining > 0 : false,
        canInteract: actions ? actions.actionsRemaining > 0 : false,
        moveLeft: movement ? movement.movementRemaining : 0,
        isPlayersTurn
      });
      this.refreshFogOfWar(entity);
    },
    
    /**
     * Deselect currently selected entity.
     */
    deselectEntity: function () {
      const selectedEntity = this.stateManager.get('selectedEntity');
      if (!selectedEntity) {
        return;
      }
      
      // Remove tint from sprite
      const render = selectedEntity.getComponent('RenderComponent');
      if (render && render.sprite) {
        render.sprite.tint = 0xffffff; // Reset to white
      }
      
      this.stateManager.set('selectedEntity', null);
      this.hideMovementRange();
      this.hideAttackTargets();
      this.hideFogOfWar();
      
      // Hide entity info panel
      this.uiManager.hideEntityInfo();
      
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
      const movementRange = this.movementSystem.calculateMovementRange(entity);
      this.stateManager.set('movementRange', movementRange);

      const movement = entity.getComponent('MovementComponent');
      console.info('Range: movement range calculated', {
        entityId: entity.id,
        movementRemaining: movement?.movementRemaining,
        reachable: movementRange.size
      });
      
      // Create overlay graphics
      const movementRangeOverlay = new PIXI.Graphics();
      
      // Draw reachable hexes
      movementRange.forEach(hexKey => {
        const [q, r] = hexKey.split('_').map(Number);
        const pos = this.axialToPixel(q, r, this.config.hexSize);
        
        movementRangeOverlay.beginFill(0x3b82f6, 0.2); // Blue with transparency
        movementRangeOverlay.lineStyle(2, 0x60a5fa, 0.5);
        
        for (let i = 0; i < 6; i++) {
          const angle = (Math.PI / 3) * i;
          const x = pos.x + this.config.hexSize * Math.cos(angle);
          const y = pos.y + this.config.hexSize * Math.sin(angle);
          
          if (i === 0) {
            movementRangeOverlay.moveTo(x, y);
          } else {
            movementRangeOverlay.lineTo(x, y);
          }
        }
        movementRangeOverlay.closePath();
        movementRangeOverlay.endFill();
      });
      
      // Ensure the overlay never intercepts clicks.
      movementRangeOverlay.interactive = false;
      movementRangeOverlay.eventMode = 'none';
      movementRangeOverlay.zIndex = 9000;

      this.uiContainer.addChild(movementRangeOverlay);
      this.stateManager.set('movementRangeOverlay', movementRangeOverlay);
    },
    
    /**
     * Hide movement range overlay.
     */
    hideMovementRange: function () {
      const movementRangeOverlay = this.stateManager.get('movementRangeOverlay');
      if (movementRangeOverlay) {
        this.uiContainer.removeChild(movementRangeOverlay);
        movementRangeOverlay.destroy();
        this.stateManager.set('movementRangeOverlay', null);
      }
      this.stateManager.set('movementRange', null);
    },

    /**
     * Show hostile targets for attack mode.
     * @param {Entity} actor
     */
    showAttackTargets: function (actor) {
      this.hideAttackTargets();
      const targets = this.getHostileTargets(actor);
      const overlay = new PIXI.Container();
      overlay.zIndex = 9001;

      targets.forEach(({ target }) => {
        const posComp = target.getComponent('PositionComponent');
        if (!posComp) return;
        const pos = this.axialToPixel(posComp.q, posComp.r, this.config.hexSize);
        const radius = this.config.hexSize * 0.9;

        const ring = new PIXI.Graphics();
        ring.beginFill(0xef4444, 0.15);
        ring.lineStyle(2, 0xf97316, 0.9);
        ring.drawCircle(0, 0, radius * 0.6);
        ring.endFill();
        ring.x = pos.x;
        ring.y = pos.y;
        ring.targetId = target.id;
        ring.eventMode = 'static';
        ring.interactive = true;
        ring.cursor = 'pointer';

        ring.on('pointertap', () => {
          const currentActionMode = this.stateManager.get('actionMode');
          const attacker = this.stateManager.get('selectedEntity') || actor;
          if (currentActionMode !== 'attack') {
            console.warn('Tap ignored: not in attack mode');
            return;
          }
          const targetEntity = this.entityManager.getEntity(ring.targetId);
          const canAttackCheck = targetEntity ? this.combatSystem.canAttack(attacker, targetEntity) : { canAttack: false, reason: 'Target missing' };
          console.info('Overlay attack tap', { attackerId: attacker?.id, targetId: ring.targetId, check: canAttackCheck });
          if (!canAttackCheck.canAttack) {
            return;
          }
          this.performAttack(attacker, targetEntity);
        });

        overlay.addChild(ring);
      });

      // Allow pointer events only on target rings so movement clicks pass through elsewhere.
      overlay.eventMode = 'passive';

      this.uiContainer.addChild(overlay);
      this.stateManager.set('attackTargetsOverlay', overlay);

      console.info('Range: attack targets highlighted', {
        actorId: actor.id,
        targets: targets.map(({ target }) => target.id)
      });
    },

    /**
     * Hide attack target overlay.
     */
    hideAttackTargets: function () {
      const overlay = this.stateManager.get('attackTargetsOverlay');
      if (overlay) {
        this.uiContainer.removeChild(overlay);
        overlay.destroy({ children: true });
        this.stateManager.set('attackTargetsOverlay', null);
      }
    },

    /**
     * Refresh fog-of-war overlay for the currently selected or active player actor.
     * @param {Entity|null} actorOverride - Optional explicit actor
     */
    refreshFogOfWar: function (actorOverride = null) {
      const showFog = this.stateManager.get('showFog');
      if (!showFog) {
        this.hideFogOfWar();
        return;
      }

      const selected = actorOverride || this.stateManager.get('selectedEntity');
      const selectedCombat = selected?.getComponent?.('CombatComponent');
      const selectedIsPlayer = selectedCombat?.isPlayerTeam ? selectedCombat.isPlayerTeam() : (selectedCombat?.team === Team.PLAYER || selectedCombat?.team === 'player');

      let actor = selectedIsPlayer ? selected : null;
      if (!actor && this.turnManagementSystem?.getCurrentTurnEntity) {
        const current = this.turnManagementSystem.getCurrentTurnEntity();
        const currentCombat = current?.getComponent?.('CombatComponent');
        const currentIsPlayer = currentCombat?.isPlayerTeam ? currentCombat.isPlayerTeam() : (currentCombat?.team === Team.PLAYER || currentCombat?.team === 'player');
        if (currentIsPlayer) {
          actor = current;
        }
      }

      if (!actor) {
        this.hideFogOfWar();
        return;
      }

      this.renderFogOfWarForEntity(actor);
    },

    /**
     * Hide and destroy fog overlay.
     */
    hideFogOfWar: function () {
      const fogOverlay = this.stateManager.get('fogOverlay');
      if (fogOverlay) {
        this.uiContainer.removeChild(fogOverlay);
        fogOverlay.destroy();
        this.stateManager.set('fogOverlay', null);
      }
      this.stateManager.set('visibleHexes', null);
    },

    /**
     * Return default/derived vision radius for an actor.
     * @param {Entity} actor
     * @returns {number}
     */
    getVisionRangeForEntity: function (actor) {
      const stats = actor?.getComponent?.('StatsComponent');
      const perception = Number(stats?.perception ?? 0);
      const derived = this.config.defaultVisionRange + Math.max(-2, Math.min(2, Math.floor(perception / 4)));
      return Math.max(4, Math.min(12, derived));
    },

    /**
     * Render fog overlay by darkening non-visible hexes.
     * @param {Entity} actor
     */
    renderFogOfWarForEntity: function (actor) {
      this.hideFogOfWar();

      const visibleHexes = this.getVisibleHexSet(actor);
      this.stateManager.set('visibleHexes', visibleHexes);

      const fogOverlay = new PIXI.Graphics();
      fogOverlay.zIndex = 8500;
      fogOverlay.interactive = false;
      fogOverlay.eventMode = 'none';

      this.hexContainer.children.forEach((hex) => {
        const data = hex?.hexData;
        if (!data) {
          return;
        }

        const key = `${data.q}_${data.r}`;
        if (visibleHexes.has(key)) {
          return;
        }

        const pos = this.axialToPixel(data.q, data.r, this.config.hexSize);
        fogOverlay.beginFill(0x020617, 0.72);
        fogOverlay.lineStyle(0, 0x000000, 0);
        for (let i = 0; i < 6; i++) {
          const angle = (Math.PI / 3) * i;
          const x = pos.x + this.config.hexSize * Math.cos(angle);
          const y = pos.y + this.config.hexSize * Math.sin(angle);
          if (i === 0) {
            fogOverlay.moveTo(x, y);
          } else {
            fogOverlay.lineTo(x, y);
          }
        }
        fogOverlay.closePath();
        fogOverlay.endFill();
      });

      this.uiContainer.addChild(fogOverlay);
      this.stateManager.set('fogOverlay', fogOverlay);
    },

    /**
     * Compute visible hex set based on range + line of sight.
     * @param {Entity} actor
     * @returns {Set<string>}
     */
    getVisibleHexSet: function (actor) {
      const visible = new Set();
      const actorPos = actor?.getComponent?.('PositionComponent');
      if (!actorPos) {
        return visible;
      }

      const range = this.getVisionRangeForEntity(actor);
      this.hexContainer.children.forEach((hex) => {
        const data = hex?.hexData;
        if (!data) {
          return;
        }

        const distance = this.movementSystem?.hexDistance
          ? this.movementSystem.hexDistance(actorPos.q, actorPos.r, data.q, data.r)
          : Math.max(Math.abs(actorPos.q - data.q), Math.abs(actorPos.r - data.r), Math.abs((actorPos.q + actorPos.r) - (data.q + data.r)));
        if (distance > range) {
          return;
        }

        if (this.hasLineOfSight(actorPos.q, actorPos.r, data.q, data.r)) {
          visible.add(`${data.q}_${data.r}`);
        }
      });

      visible.add(`${actorPos.q}_${actorPos.r}`);
      return visible;
    },

    /**
     * Determine line of sight using axial interpolation and obstacle checks.
     * @param {number} fromQ
     * @param {number} fromR
     * @param {number} toQ
     * @param {number} toR
     * @returns {boolean}
     */
    hasLineOfSight: function (fromQ, fromR, toQ, toR) {
      if (fromQ === toQ && fromR === toR) {
        return true;
      }

      const line = this.getAxialLine(fromQ, fromR, toQ, toR);
      for (let i = 1; i < line.length - 1; i++) {
        const { q, r } = line[i];
        const obstacle = this.getObstacleMobilityAtHex(q, r);
        if (obstacle && !obstacle.passable) {
          return false;
        }
      }

      return true;
    },

    /**
     * Build axial line coordinates from origin to target.
     * @param {number} fromQ
     * @param {number} fromR
     * @param {number} toQ
     * @param {number} toR
     * @returns {Array<{q:number,r:number}>}
     */
    getAxialLine: function (fromQ, fromR, toQ, toR) {
      const toCube = (q, r) => ({ x: q, z: r, y: -q - r });
      const fromCube = toCube(fromQ, fromR);
      const targetCube = toCube(toQ, toR);
      const distance = this.movementSystem?.hexDistance
        ? this.movementSystem.hexDistance(fromQ, fromR, toQ, toR)
        : Math.max(Math.abs(fromQ - toQ), Math.abs(fromR - toR), Math.abs((fromQ + fromR) - (toQ + toR)));

      const points = [];
      for (let step = 0; step <= distance; step++) {
        const t = distance === 0 ? 0 : step / distance;
        const x = fromCube.x + (targetCube.x - fromCube.x) * t;
        const y = fromCube.y + (targetCube.y - fromCube.y) * t;
        const z = fromCube.z + (targetCube.z - fromCube.z) * t;

        let rx = Math.round(x);
        let ry = Math.round(y);
        let rz = Math.round(z);
        const dx = Math.abs(rx - x);
        const dy = Math.abs(ry - y);
        const dz = Math.abs(rz - z);

        if (dx > dy && dx > dz) {
          rx = -ry - rz;
        } else if (dy > dz) {
          ry = -rx - rz;
        } else {
          rz = -rx - ry;
        }

        points.push({ q: rx, r: rz });
      }

      return points;
    },

    /**
     * Return 6 neighboring axial coordinates for a hex.
     * @param {number} q
     * @param {number} r
     * @returns {Array<{q:number,r:number}>}
     */
    getAdjacentHexes: function (q, r) {
      return [
        { q: q + 1, r },
        { q: q + 1, r: r - 1 },
        { q, r: r - 1 },
        { q: q - 1, r },
        { q: q - 1, r: r + 1 },
        { q, r: r + 1 }
      ];
    },

    /**
     * Find payload-backed obstacle record at hex.
     * @param {number} q
     * @param {number} r
     * @returns {Object|null}
     */
    findObstaclePayloadAtHex: function (q, r) {
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      return entities.find((entity) => {
        if (entity?.entity_type !== 'obstacle') {
          return false;
        }
        const placement = entity?.placement;
        return placement && placement.room_id === this.activeRoomId && Number(placement?.hex?.q) === q && Number(placement?.hex?.r) === r;
      }) || null;
    },

    /**
     * Find ECS obstacle entity at hex.
     * @param {number} q
     * @param {number} r
     * @returns {Entity|null}
     */
    findObstacleEntityAtHex: function (q, r) {
      if (!this.entityManager) {
        return null;
      }

      const entities = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
      for (const entity of entities) {
        const pos = entity.getComponent('PositionComponent');
        const identity = entity.getComponent('IdentityComponent');
        if (pos?.q === q && pos?.r === r && identity?.entityType === EntityType.OBSTACLE) {
          return entity;
        }
      }

      return null;
    },

    /**
     * Find room connection touching this hex in the active room.
     * @param {number} q
     * @param {number} r
     * @returns {Object|null}
     */
    findConnectionAtHex: function (q, r) {
      const connections = Array.isArray(this.dungeonData?.connections) ? this.dungeonData.connections : [];
      return connections.find((connection) => {
        const fromMatch = connection?.from_room === this.activeRoomId &&
          Number(connection?.from_hex?.q) === q &&
          Number(connection?.from_hex?.r) === r;
        const toMatch = connection?.to_room === this.activeRoomId &&
          Number(connection?.to_hex?.q) === q &&
          Number(connection?.to_hex?.r) === r;
        return fromMatch || toMatch;
      }) || null;
    },

    /**
     * Send a generic non-attack action to server combat API and hydrate state.
     * @param {Object} payload
     * @returns {Promise<boolean>}
     */
    performCombatAction: async function (payload = {}) {
      const encounterId = this.stateManager.get('encounterId');
      if (!encounterId) {
        console.info('Combat action skipped; no active encounter id.', payload);
        return null;
      }

      try {
        // Always use mapId from stateManager (captured from startCombat response).
        const mapId = this.stateManager.get('mapId');
        const serverState = await combatApi.performAction({
          encounterId,
          ...(mapId ? { mapId } : {}),
          ...payload
        });

        if (!serverState) {
          console.error('Combat action returned no state; keeping current client view.');
          return null;
        }

        if (serverState.encounter_id) {
          this.stateManager.set('encounterId', serverState.encounter_id);
        }

        if (typeof this.turnManagementSystem.hydrateFromServer === 'function') {
          this.stateManager.set('serverCombatMode', true);
          this.turnManagementSystem.hydrateFromServer(serverState);
          this.syncSelectedToCurrentTurn();
        }

        return serverState;
      } catch (err) {
        console.error('Combat action via API failed; client will not fall back.', err);
        return null;
      }
    },

    /**
     * Apply backend-authoritative world delta returned by combat action API.
     * @param {Object|null} worldDelta
     */
    applyWorldDelta: function (worldDelta) {
      if (!worldDelta || typeof worldDelta !== 'object') {
        return;
      }

      const type = String(worldDelta.type || '');
      const roomId = String(worldDelta.room_id || this.activeRoomId || '');
      const targetHex = worldDelta.target_hex || {};
      const destinationHex = worldDelta.destination_hex || {};

      if (type === 'open_passage') {
        const connectionId = worldDelta.connection_id;
        const connections = Array.isArray(this.dungeonData?.connections) ? this.dungeonData.connections : [];
        connections.forEach((connection) => {
          if (connectionId && connection.connection_id !== connectionId) {
            return;
          }

          const fromMatch = connection?.from_room === roomId
            && Number(connection?.from_hex?.q) === Number(targetHex.q)
            && Number(connection?.from_hex?.r) === Number(targetHex.r);
          const toMatch = connection?.to_room === roomId
            && Number(connection?.to_hex?.q) === Number(targetHex.q)
            && Number(connection?.to_hex?.r) === Number(targetHex.r);

          if (!connectionId && !fromMatch && !toMatch) {
            return;
          }

          connection.is_passable = true;
          connection.is_discovered = true;
        });
      }

      if (type === 'open_door') {
        const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
        entities.forEach((entity) => {
          if (entity?.entity_type !== 'obstacle') {
            return;
          }

          const placement = entity?.placement;
          if (!placement || placement.room_id !== roomId) {
            return;
          }

          if (Number(placement?.hex?.q) !== Number(targetHex.q) || Number(placement?.hex?.r) !== Number(targetHex.r)) {
            return;
          }

          entity.state = entity.state || {};
          entity.state.metadata = entity.state.metadata || {};
          entity.state.metadata.passable = true;
        });
      }

      if (type === 'move_object') {
        const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
        entities.forEach((entity) => {
          if (entity?.entity_type !== 'obstacle') {
            return;
          }

          const placement = entity?.placement;
          if (!placement || placement.room_id !== roomId) {
            return;
          }

          if (Number(placement?.hex?.q) !== Number(targetHex.q) || Number(placement?.hex?.r) !== Number(targetHex.r)) {
            return;
          }

          placement.hex.q = Number(destinationHex.q);
          placement.hex.r = Number(destinationHex.r);
        });

        // Move matching ECS obstacle sprite/entity as well.
        const ecsObstacle = this.findObstacleEntityAtHex(Number(targetHex.q), Number(targetHex.r));
        if (ecsObstacle) {
          const pos = ecsObstacle.getComponent('PositionComponent');
          if (pos) {
            pos.q = Number(destinationHex.q);
            pos.r = Number(destinationHex.r);
          }
        }
      }

      this.paintActiveRoom();
      this.refreshFogOfWar();
    },

    /**
     * Perform interact action at an adjacent hex (doors, movable obstacles, blocked connections).
     * @param {Entity} actor
     * @param {number} targetQ
     * @param {number} targetR
     * @returns {boolean}
     */
    performInteractAtHex: function (actor, targetQ, targetR) {
      const actorPos = actor?.getComponent?.('PositionComponent');
      const actorActions = actor?.getComponent?.('ActionsComponent');
      if (!actorPos || !actorActions) {
        return false;
      }

      const combatActive = this.stateManager.get('combatActive');
      if (combatActive && this.turnManagementSystem && !this.turnManagementSystem.isEntityTurn(actor)) {
        return false;
      }

      const distance = this.movementSystem?.hexDistance
        ? this.movementSystem.hexDistance(actorPos.q, actorPos.r, targetQ, targetR)
        : Math.max(Math.abs(actorPos.q - targetQ), Math.abs(actorPos.r - targetR), Math.abs((actorPos.q + actorPos.r) - (targetQ + targetR)));
      if (distance > 1) {
        return false;
      }

      const connection = this.findConnectionAtHex(targetQ, targetR);
      if (connection && connection.is_passable === false) {
        this.performCombatAction({
          actorId: actor.id,
          actionType: 'interact',
          interactionType: 'open_passage',
          actionCost: 1,
          targetId: connection.connection_id,
          targetHex: { q: targetQ, r: targetR }
        }).then((serverState) => {
          if (!serverState) {
            return;
          }

          this.applyWorldDelta(serverState.world_delta || null);
          console.info('Interaction: opened room connection', { connectionId: connection.connection_id, q: targetQ, r: targetR });
        });
        return true;
      }

      const obstacleProfile = this.getObstacleMobilityAtHex(targetQ, targetR);
      if (!obstacleProfile) {
        return false;
      }

      if (obstacleProfile.movable) {
        const pushDeltaQ = targetQ - actorPos.q;
        const pushDeltaR = targetR - actorPos.r;
        const preferredDestination = { q: targetQ + pushDeltaQ, r: targetR + pushDeltaR };
        const candidates = [preferredDestination, ...this.getAdjacentHexes(targetQ, targetR)];

        const destination = candidates.find((candidate) => {
          if (!this.isHexInActiveRoom(candidate.q, candidate.r)) {
            return false;
          }
          if (this.getObstacleMobilityAtHex(candidate.q, candidate.r)) {
            return false;
          }

          const occupied = this.entityManager?.getEntitiesWith('PositionComponent', 'IdentityComponent').some((entity) => {
            const pos = entity.getComponent('PositionComponent');
            return pos?.q === candidate.q && pos?.r === candidate.r;
          });

          return !occupied;
        });

        if (!destination) {
          return false;
        }

        this.performCombatAction({
          actorId: actor.id,
          actionType: 'interact',
          interactionType: 'move_object',
          actionCost: 1,
          targetId: this.getObjectIdAtHex(targetQ, targetR) || null,
          targetHex: { q: targetQ, r: targetR },
          destinationHex: destination
        }).then((serverState) => {
          if (!serverState) {
            return;
          }

          this.applyWorldDelta(serverState.world_delta || null);

          console.info('Interaction: moved obstacle', {
            from: { q: targetQ, r: targetR },
            to: destination
          });
        });
        return true;
      }

      if (!obstacleProfile.passable) {
        const label = (this.getObjectLabelAtHex(targetQ, targetR) || '').toLowerCase();
        const isDoorLike = /(door|gate|hatch|portal)/.test(label);

        if (isDoorLike) {
          this.performCombatAction({
            actorId: actor.id,
            actionType: 'interact',
            interactionType: 'open_door',
            actionCost: 1,
            targetId: this.getObjectIdAtHex(targetQ, targetR) || null,
            targetHex: { q: targetQ, r: targetR },
            label
          }).then((serverState) => {
            if (!serverState) {
              return;
            }

            this.applyWorldDelta(serverState.world_delta || null);

            console.info('Interaction: opened door-like obstacle', { q: targetQ, r: targetR, label });
          });
          return true;
        }
      }

      return false;
    },
    
    /**
     * Start combat encounter.
     */
    serializeCombatantsForApi: function () {
      if (!this.entityManager) {
        return [];
      }

      const entities = this.entityManager.getEntitiesWith('IdentityComponent', 'CombatComponent');
      return entities.map((entity) => {
        const identity = entity.getComponent('IdentityComponent');
        const combat = entity.getComponent('CombatComponent');
        const stats = entity.getComponent('StatsComponent');
        const position = entity.getComponent('PositionComponent');

        return {
          entityId: entity.id,
          entityRef: entity.dcEntityRef || entity.instanceId || null,
          characterId: entity.dcCharacterId || null,
          name: identity?.name || `Entity ${entity.id}`,
          team: combat?.team,
          initiative: combat?.getInitiative ? combat.getInitiative() : null,
          initiative_bonus: combat?.initiativeBonus,
          perception: stats?.perception,
          ac: stats?.ac,
          hp: stats?.currentHp,
          max_hp: stats?.maxHp,
          position: position ? { q: position.q, r: position.r } : null,
        };
      });
    },

    /**
     * Resolve campaign id from launch context.
     * @returns {number|null}
     */
    resolveCampaignId: function () {
      const launchCampaignId = Number(this.launchContext?.campaign_id || 0);
      return Number.isFinite(launchCampaignId) && launchCampaignId > 0 ? launchCampaignId : null;
    },

    /**
     * Resolve active room id for combat API payloads.
     * @returns {string|null}
     */
    resolveActiveRoomId: function () {
      return this.activeRoomId || this.stateManager.get('activeRoomId') || this.launchContext?.room_id || null;
    },

    /**
     * Determine whether server combat APIs should be used for this user/session.
     * @returns {boolean}
     */
    canUseServerCombatApi: function () {
      const uid = Number(this.currentUserId || 0);
      return Number.isFinite(uid) && uid > 0;
    },

    startCombat: async function (options = {}) {
      console.log('Starting combat (server authoritative)...');

      if (!this.canUseServerCombatApi()) {
        console.info('Combat start skipped; authenticated user is required for server combat APIs.');
        return;
      }

      const encounterId = this.stateManager.get('encounterId');
      if (encounterId) {
        console.info('Combat start skipped; encounter already active.', { encounterId });
        return;
      }

      const campaignId = this.resolveCampaignId();
      if (!campaignId) {
        console.info('Combat start skipped; campaign context is required for server combat APIs.');
        return;
      }

      const payload = {
        campaignId,
        roomId: this.resolveActiveRoomId(),
        entities: this.serializeCombatantsForApi(),
        ...options
      };

      try {
        const serverState = await combatApi.startCombat(payload);
        if (!serverState) {
          console.error('Combat start returned no state; aborting client start.');
          return;
        }

        if (serverState.encounter_id) {
          this.stateManager.set('encounterId', serverState.encounter_id);
        }

        if (serverState.map_id) {
          this.stateManager.set('mapId', serverState.map_id);
          console.log('Captured map_id from startCombat:', serverState.map_id);
        }

        if (typeof this.turnManagementSystem.hydrateFromServer === 'function') {
          this.stateManager.set('serverCombatMode', true);
          this.turnManagementSystem.hydrateFromServer(serverState);
          this.syncSelectedToCurrentTurn();
        }
      } catch (err) {
        console.error('Combat start via API failed; client will not fall back.', err);
      }
    },
    
    /**
     * End current turn.
     */
    endTurn: async function () {
      console.log('Ending turn (server authoritative)...');

      const encounterId = this.stateManager.get('encounterId');
      if (!encounterId) {
        console.info('End turn skipped; no active encounter id.');
        return;
      }

      const currentTurn = this.turnManagementSystem?.getCurrentTurn?.();
      const payload = {
        encounterId,
        participantId: currentTurn?.entityId
      };

      try {
        const serverState = await combatApi.endTurn(payload);
        if (!serverState) {
          console.error('End turn returned no state; keeping current client view.');
          return;
        }

        if (serverState.encounter_id) {
          this.stateManager.set('encounterId', serverState.encounter_id);
        }

        if (typeof this.turnManagementSystem.hydrateFromServer === 'function') {
          this.stateManager.set('serverCombatMode', true);
          this.turnManagementSystem.hydrateFromServer(serverState);
          this.syncSelectedToCurrentTurn();
        }
      } catch (err) {
        console.error('Turn end via API failed; client will not fall back.', err);
      }
    },
    
    /**
     * End combat encounter.
     */
    endCombat: async function () {
      console.log('Ending combat (server authoritative)...');

      const encounterId = this.stateManager.get('encounterId');
      if (!encounterId) {
        console.info('End combat skipped; no active encounter id.');
        return;
      }

      const payload = {
        encounterId
      };

      try {
        await combatApi.endCombat(payload);
      } catch (err) {
        console.error('Combat end via API failed; client will not fall back.', err);
        return;
      }

      this.turnManagementSystem.endCombat();
      this.stateManager.set('encounterId', null);
      this.deselectEntity();
    },

    /**
     * Free-action talk interface hook for AI conversation integration.
     * @param {Entity} speaker - Speaking entity
     * @param {string} message - Utterance content
     */
    performTalk: async function (speaker, message) {
      if (!speaker || !message) {
        return;
      }

      const actionAccepted = await this.performCombatAction({
        actorId: speaker.id,
        actionType: 'talk',
        actionCost: 0,
        message
      });

      if (!actionAccepted) {
        return;
      }

      const identity = speaker.getComponent('IdentityComponent');
      const combat = speaker.getComponent('CombatComponent');

      // Emit an event for downstream ai_conversation listeners.
      window.dispatchEvent(new CustomEvent('dungeoncrawler:talk', {
        detail: {
          entityId: speaker.id,
          name: identity?.name || `Entity ${speaker.id}`,
          team: combat?.team || null,
          roomId: this.activeRoomId || null,
          message: message
        }
      }));
    },

    /**
     * Perform attack action.
     * @param {Entity} attacker - Attacking entity
     * @param {Entity} target - Target entity
     */
    performAttack: async function (attacker, target) {
      const combatActive = this.stateManager.get('combatActive');
      if (combatActive && this.turnManagementSystem) {
        if (!this.turnManagementSystem.isEntityTurn(attacker)) {
          console.warn('Not your turn!', { attackerId: attacker?.id, currentTurn: this.turnManagementSystem?.getCurrentTurnEntity?.()?.id });
          return;
        }
      }

      const encounterId = this.stateManager.get('encounterId');
      if (!encounterId) {
        console.info('Attack skipped; no active encounter id.');
        return;
      }

      const payload = {
        encounterId,
        ...(this.stateManager.get('mapId') ? { mapId: this.stateManager.get('mapId') } : {}),
        attackerId: attacker?.id,
        targetId: target?.id,
        action: 'attack'
      };

      try {
        const serverState = await combatApi.performAttack(payload);
        if (!serverState) {
          console.error('Attack returned no state; keeping current client view.');
          return;
        }

        if (serverState.encounter_id) {
          this.stateManager.set('encounterId', serverState.encounter_id);
        }

        if (typeof this.turnManagementSystem.hydrateFromServer === 'function') {
          this.stateManager.set('serverCombatMode', true);
          this.turnManagementSystem.hydrateFromServer(serverState);
          this.syncSelectedToCurrentTurn();
        }

        const actionResult = serverState.action_result || {};
        const projectedResult = {
          result: actionResult.result || actionResult.outcome || (actionResult.hit ? AttackResult.HIT : AttackResult.MISS),
          attackRoll: actionResult.attack_roll,
          attackTotal: actionResult.attack_total,
          damage: Number(actionResult.damage || 0),
          applyDamage: false
        };

        if (this.combatSystem && attacker && target) {
          this.combatSystem.makeAttack(attacker, target, projectedResult);
        }
      } catch (err) {
        console.error('Attack via API failed; client will not fall back.', err);
      }
    },

    /**
     * Get all hostile, alive targets for an entity, sorted by distance.
     * @param {Entity} actor
     * @returns {Array<{target: Entity, distance: number}>}
     */
    getHostileTargets: function (actor) {
      const actorCombat = actor.getComponent('CombatComponent');
      const actorPos = actor.getComponent('PositionComponent');
      if (!actorCombat || !actorPos) {
        return [];
      }

      const candidates = this.entityManager.getEntitiesWith('CombatComponent', 'StatsComponent', 'PositionComponent');
      const hostileTargets = [];

      candidates.forEach((candidate) => {
        if (candidate.id === actor.id) {
          return;
        }

        const targetCombat = candidate.getComponent('CombatComponent');
        const targetStats = candidate.getComponent('StatsComponent');
        const targetPos = candidate.getComponent('PositionComponent');

        if (!targetCombat || !targetPos || !targetStats?.isAlive()) {
          return;
        }

        if (!actorCombat.isHostileTo(targetCombat)) {
          return;
        }

        const distance = this.movementSystem.hexDistance(actorPos.q, actorPos.r, targetPos.q, targetPos.r);
        if (!this.hasLineOfSight(actorPos.q, actorPos.r, targetPos.q, targetPos.r)) {
          return;
        }
        hostileTargets.push({ target: candidate, distance });
      });

      hostileTargets.sort((a, b) => a.distance - b.distance);
      return hostileTargets;
    },

    
    /**
     * Callback when attack is performed.
     * @param {Object} attackData - Attack data
     */
    onAttackPerformed: function (attackData) {
      const attackerName = attackData.attacker.getComponent('IdentityComponent')?.name || 'Attacker';
      const targetName = attackData.target.getComponent('IdentityComponent')?.name || 'Target';
      
      let message = `${attackerName} attacks ${targetName}: `;
      
      if (attackData.result === AttackResult.CRITICAL_HIT) {
        message += `💥 CRITICAL HIT! `;
      } else if (attackData.result === AttackResult.HIT) {
        message += `✓ Hit! `;
      } else if (attackData.result === AttackResult.MISS) {
        message += `✗ Miss! `;
      } else if (attackData.result === AttackResult.CRITICAL_MISS) {
        message += `❌ Critical Miss! `;
      }
      
      if (attackData.damage > 0) {
        message += `${attackData.damage} damage`;
      }
      
      console.log(message);
      
      // Could add floating damage numbers or attack animations here
    },
    
    /**
     * Callback when damage is dealt.
     * @param {Object} damageData - Damage data
     */
    onDamageDealt: function (damageData) {
      const targetName = damageData.target.getComponent('IdentityComponent')?.name || 'Target';
      
      console.log(`${targetName}: ${damageData.remainingHp}/${damageData.maxHp} HP`);
      
      if (damageData.defeated) {
        console.log(`${targetName} has been defeated!`);
        
        // Update sprite to show defeated state (could add death animation)
        const render = damageData.target.getComponent('RenderComponent');
        if (render && render.sprite) {
          render.sprite.alpha = 0.5; // Make semi-transparent
        }
      }
    },

    /**
     * Select the current turn entity (player) after a server hydration so buttons work.
     */
    syncSelectedToCurrentTurn: function () {
      const current = this.turnManagementSystem?.getCurrentTurnEntity?.();
      if (current) {
        const combat = current.getComponent('CombatComponent');
        if (combat && combat.isPlayerTeam && combat.isPlayerTeam()) {
          this.selectEntity(current);
          return;
        }
      }
      // If no player current, clear selection.
      this.deselectEntity();
    },

    /**
     * Load game assets.
     */
    loadAssets: async function (assetList) {
      if (this.stateManager && this.stateManager.get('assetsLoaded')) return;
      
      console.log('Loading assets...');
      
      try {
          this.syncSelectedToCurrentTurn();
        for (const asset of assetList) {
          await PIXI.Assets.load(asset);
        }
        if (this.stateManager) {
          this.stateManager.set('assetsLoaded', true);
        }
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

      // Helper to track event listeners for cleanup
      const addTrackedListener = (element, event, handler) => {
        if (element) {
          element.addEventListener(event, handler);
          self.eventListeners.push({ element, event, handler });
        }
      };

      // Grid size selector
      const gridSizeSelect = document.getElementById('grid-size');
      addTrackedListener(gridSizeSelect, 'change', function (e) {
        const size = e.target.value;
        switch (size) {
          case 'small':
            self.config.gridWidth = 10;
            self.config.gridHeight = 10;
            break;
          case 'medium':
            self.config.gridWidth = 20;
            self.config.gridHeight = 20;
            break;
          case 'large':
            self.config.gridWidth = 40;
            self.config.gridHeight = 40;
            break;
        }
        self.generateHexGrid();
      });

      // Hex size slider
      const hexSizeSlider = document.getElementById('hex-size');
      addTrackedListener(hexSizeSlider, 'input', function (e) {
        self.config.hexSize = parseInt(e.target.value);
        const hexSizeValue = document.getElementById('hex-size-value');
        if (hexSizeValue) {
          hexSizeValue.textContent = self.config.hexSize + 'px';
        }
        self.generateHexGrid();
      });

      // Toggle coordinates
      const toggleCoords = document.getElementById('toggle-coordinates');
      addTrackedListener(toggleCoords, 'click', function () {
        const current = self.stateManager.get('showCoordinates');
        self.stateManager.set('showCoordinates', !current);
        self.generateHexGrid();
      });

      // Toggle grid lines
      const toggleGrid = document.getElementById('toggle-grid');
      addTrackedListener(toggleGrid, 'click', function () {
        const current = self.stateManager.get('showGrid');
        const newValue = !current;
        self.stateManager.set('showGrid', newValue);
      });

      // Toggle fog of war
      const toggleFog = document.getElementById('toggle-fog');
      addTrackedListener(toggleFog, 'click', function () {
        const current = self.stateManager.get('showFog');
        const next = !current;
        self.stateManager.set('showFog', next);
        this.textContent = next ? 'Hide Fog of War' : 'Show Fog of War';
      });
      if (toggleFog) {
        toggleFog.textContent = self.stateManager.get('showFog') ? 'Hide Fog of War' : 'Show Fog of War';
      }

      // Reset view
      const resetView = document.getElementById('reset-view');
      addTrackedListener(resetView, 'click', function () {
        self.setWorldScale(1);
        self.setWorldPosition(self.app.screen.width / 2, self.app.screen.height / 2);
        self.uiManager.updateZoomLevel(1);
      });

      // Object type buttons
      document.querySelectorAll('.btn-object').forEach(function (btn) {
        const clickHandler = function () {
          // Remove active class from all buttons
          document.querySelectorAll('.btn-object').forEach(b => b.classList.remove('active'));
          
          // Set active button
          btn.classList.add('active');
          const objectType = btn.dataset.type;
          self.stateManager.set('selectedObjectType', objectType);
          
          console.log('Selected object type:', objectType);
        };
        addTrackedListener(btn, 'click', clickHandler);
      });

      // Clear all objects
      const clearObjects = document.getElementById('clear-objects');
      addTrackedListener(clearObjects, 'click', function () {
        self.clearEntities();
        console.log('Cleared all objects');
      });
      
      // Deselect entity button
      const deselectBtn = document.getElementById('deselect-entity');
      addTrackedListener(deselectBtn, 'click', function () {
        self.deselectEntity();
      });
      
      // Combat controls
      const startCombatBtn = document.getElementById('start-combat');
      addTrackedListener(startCombatBtn, 'click', function () {
        self.startCombat();
      });
      
      const actionMoveBtn = document.getElementById('action-move');
      addTrackedListener(actionMoveBtn, 'click', function () {
        if (this.disabled || this.classList.contains('btn-disabled')) {
          return;
        }

        const selected = self.stateManager.get('selectedEntity');
        const current = self.turnManagementSystem?.getCurrentTurnEntity?.();
        const actor = selected || current;
        if (!actor) {
          console.warn('No actor available to move');
          return;
        }

        self.stateManager.set('actionMode', 'move');
        const actions = actor.getComponent('ActionsComponent');
        const movement = actor.getComponent('MovementComponent');
        const combat = actor.getComponent('CombatComponent');
        const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');

        if (actor !== selected) {
          self.stateManager.set('selectedEntity', actor);
          self.uiManager.showEntityInfo(actor);
        }

        console.info('UI: Move button clicked', {
          actorId: actor.id,
          actionsRemaining: actions?.actionsRemaining,
          movementRemaining: movement?.movementRemaining
        });

        self.hideAttackTargets?.();
        self.showMovementRange(actor);
        self.uiManager.updateActionMode('move', {
          canAct: actions ? actions.actionsRemaining > 0 : false,
          canInteract: actions ? actions.actionsRemaining > 0 : false,
          moveLeft: movement ? movement.movementRemaining : 0,
          isPlayersTurn
        });
      });

      const actionAttackBtn = document.getElementById('action-attack');
      addTrackedListener(actionAttackBtn, 'click', function () {
        if (this.disabled || this.classList.contains('btn-disabled')) {
          return;
        }

        const selected = self.stateManager.get('selectedEntity');
        const current = self.turnManagementSystem?.getCurrentTurnEntity?.();
        const actor = selected || current;
        if (!actor) {
          console.warn('No actor available to attack');
          return;
        }

        self.stateManager.set('actionMode', 'attack');
        const actions = actor.getComponent('ActionsComponent');
        const movement = actor.getComponent('MovementComponent');
        const combat = actor.getComponent('CombatComponent');
        const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');

        console.info('UI: Attack button clicked', {
          actorId: actor.id,
          actionsRemaining: actions?.actionsRemaining,
          movementRemaining: movement?.movementRemaining
        });

        self.hideMovementRange();
        self.showAttackTargets?.(actor);
        self.uiManager.updateActionMode('attack', {
          canAct: actions ? actions.actionsRemaining > 0 : false,
          canInteract: actions ? actions.actionsRemaining > 0 : false,
          moveLeft: movement ? movement.movementRemaining : 0,
          isPlayersTurn
        });
      });

      const actionInteractBtn = document.getElementById('action-interact');
      addTrackedListener(actionInteractBtn, 'click', function () {
        if (this.disabled || this.classList.contains('btn-disabled')) {
          return;
        }

        const selected = self.stateManager.get('selectedEntity');
        const current = self.turnManagementSystem?.getCurrentTurnEntity?.();
        const actor = selected || current;
        if (!actor) {
          return;
        }

        self.stateManager.set('actionMode', 'interact');
        const actions = actor.getComponent('ActionsComponent');
        const movement = actor.getComponent('MovementComponent');
        const combat = actor.getComponent('CombatComponent');
        const isPlayersTurn = combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');

        if (actor !== selected) {
          self.stateManager.set('selectedEntity', actor);
          self.uiManager.showEntityInfo(actor);
        }

        self.hideMovementRange();
        self.hideAttackTargets();
        self.uiManager.updateActionMode('interact', {
          canAct: actions ? actions.actionsRemaining > 0 : false,
          canInteract: actions ? actions.actionsRemaining > 0 : false,
          moveLeft: movement ? movement.movementRemaining : 0,
          isPlayersTurn
        });
      });

      const actionTalkBtn = document.getElementById('action-talk');
      addTrackedListener(actionTalkBtn, 'click', function () {
        if (this.disabled || this.classList.contains('btn-disabled')) {
          return;
        }

        const selected = self.stateManager.get('selectedEntity');
        const current = self.turnManagementSystem?.getCurrentTurnEntity?.();
        const actor = selected || current;
        if (!actor) {
          return;
        }

        const message = window.prompt('What does your character say?', '');
        if (!message || !message.trim()) {
          return;
        }

        self.performTalk(actor, message.trim());
      });

      const endTurnBtn = document.getElementById('end-turn');
      addTrackedListener(endTurnBtn, 'click', function () {
        if (this.disabled || this.classList.contains('btn-disabled')) {
          return;
        }

        self.endTurn();
      });
      
      const endCombatBtn = document.getElementById('end-combat');
      addTrackedListener(endCombatBtn, 'click', function () {
        self.endCombat();
      });
    },

    /**
     * Setup pan and zoom interaction.
     */
    setupInteraction: function () {
      const self = this;
      let isDragging = false;
      let dragStart = { x: 0, y: 0 };

      const addTrackedStageListener = (event, handler) => {
        this.app.stage.on(event, handler);
        this.stageListeners.push({ event, handler });
      };

      // Pan functionality
      addTrackedStageListener('pointerdown', function (e) {
        isDragging = true;
        dragStart = { x: e.data.global.x, y: e.data.global.y };
      });

      addTrackedStageListener('pointerup', function () {
        isDragging = false;
      });

      addTrackedStageListener('pointerupoutside', function () {
        isDragging = false;
      });

      addTrackedStageListener('pointermove', function (e) {
        if (isDragging) {
          const dx = e.data.global.x - dragStart.x;
          const dy = e.data.global.y - dragStart.y;

          const nextX = self.hexContainer.x + dx;
          const nextY = self.hexContainer.y + dy;
          self.setWorldPosition(nextX, nextY);
          
          dragStart = { x: e.data.global.x, y: e.data.global.y };
        }
      });

      // Zoom functionality
      const wheelHandler = function (e) {
        e.preventDefault();
        
        const delta = e.deltaY < 0 ? 1.1 : 0.9;
        const newScale = self.hexContainer.scale.x * delta;
        
        // Limit zoom using config values
        if (newScale > self.config.minZoom && newScale < self.config.maxZoom) {
          self.setWorldScale(newScale);
          
          self.uiManager.updateZoomLevel(newScale);
        }
      };
      
      this.app.view.addEventListener('wheel', wheelHandler);
      this.eventListeners.push({ element: this.app.view, event: 'wheel', handler: wheelHandler });
    },

    /**
     * Find a rendered hex by axial coordinates.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {PIXI.Graphics|null}
     */
    findHexByCoords: function (q, r) {
      const matchingHex = this.hexContainer.children.find((child) => {
        if (!child.hexData) {
          return false;
        }
        return child.hexData.q === q && child.hexData.r === r;
      });

      return matchingHex || null;
    },

    /**
     * Draw a hex with provided style.
     * @param {PIXI.Graphics} hex - Hex graphic
     * @param {number} fillColor - Fill color
     * @param {number} lineWidth - Border width
     * @param {number} lineColor - Border color
     * @param {number} alpha - Fill alpha
     */
    drawHexStyle: function (hex, fillColor, lineWidth, lineColor, alpha = 1) {
      hex.clear();
      hex.beginFill(fillColor, alpha);
      hex.lineStyle(lineWidth, lineColor, 1);

      for (let i = 0; i < 6; i++) {
        const angle = (Math.PI / 3) * i;
        const x = this.config.hexSize * Math.cos(angle);
        const y = this.config.hexSize * Math.sin(angle);

        if (i === 0) {
          hex.moveTo(x, y);
        } else {
          hex.lineTo(x, y);
        }
      }
      hex.closePath();
      hex.endFill();
    },

    /**
     * Check whether a hex coordinate belongs to the active room.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {boolean}
     */
    isHexInActiveRoom: function (q, r) {
      const room = this.getActiveRoomData();
      if (!room || !Array.isArray(room.hexes)) {
        return false;
      }
      return room.hexes.some((roomHex) => roomHex.q === q && roomHex.r === r);
    },

    /**
     * Reset hex appearance based on active room membership.
     * @param {PIXI.Graphics} hex - Hex graphic
     */
    resetHexAppearance: function (hex) {
      if (!hex?.hexData) {
        return;
      }

      const { q, r } = hex.hexData;
      const obstacleProfile = this.getObstacleMobilityAtHex(q, r);

      if (obstacleProfile) {
        if (!obstacleProfile.passable && !obstacleProfile.movable) {
          this.drawHexStyle(hex, 0x5b2b2b, 2, 0x8b3a3a, 0.95);
          return;
        }

        if (!obstacleProfile.passable && obstacleProfile.movable) {
          this.drawHexStyle(hex, 0x7a5325, 2, 0xb7791f, 0.95);
          return;
        }

        if (obstacleProfile.passable && obstacleProfile.movable) {
          this.drawHexStyle(hex, 0x2d5170, 2, 0x4299e1, 0.95);
          return;
        }

        this.drawHexStyle(hex, 0x2d4b36, 2, 0x4d7a5b, 1);
        return;
      }

      if (this.isHexInActiveRoom(q, r)) {
        this.drawHexStyle(hex, 0x2d4b36, 2, 0x4d7a5b, 1);
      } else {
        this.drawHexStyle(hex, 0x2d3748, 1, 0x4a5568, 1);
      }
    },

    /**
     * Apply selected-hex visuals and state.
     * @param {PIXI.Graphics} hex - Hex graphic
     */
    setSelectedHex: function (hex) {
      if (!hex?.hexData) {
        return;
      }

      this.stateManager.set('selectedHex', hex);
      this.drawHexStyle(hex, 0x3b82f6, 3, 0x60a5fa, 1);

      const { q, r } = hex.hexData;
      this.uiManager.updateSelectedHex(q, r);
    },

    /**
     * Get currently active room payload.
     * @returns {Object|null}
     */
    getActiveRoomData: function () {
      if (!this.dungeonData || !this.activeRoomId || !this.dungeonData.rooms) {
        return null;
      }
      return this.dungeonData.rooms[this.activeRoomId] || null;
    },

    /**
     * Color room footprint for active room.
     */
    paintActiveRoom: function () {
      this.hexContainer.children.forEach((hex) => {
        if (!hex?.hexData) {
          return;
        }
        this.resetHexAppearance(hex);
      });
    },

    /**
     * Render active-room entities from dungeon payload.
     */
    renderActiveRoomEntities: function () {
      if (!this.entityManager) {
        return;
      }

      this.clearEntities();
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];

      entities.forEach((entity) => {
        const placement = entity?.placement;
        if (!placement || placement.room_id !== this.activeRoomId || !placement.hex) {
          return;
        }

        const q = Number(placement.hex.q);
        const r = Number(placement.hex.r);
        if (!Number.isFinite(q) || !Number.isFinite(r)) {
          return;
        }

        const rawType = entity?.entity_type ? String(entity.entity_type).toLowerCase() : '';
        let entityType = EntityType.OBSTACLE;
        if (rawType === 'creature') {
          entityType = EntityType.CREATURE;
        } else if (rawType === 'player_character' || rawType === 'player') {
          entityType = EntityType.PLAYER_CHARACTER;
        } else if (rawType === 'npc') {
          entityType = EntityType.NPC;
        } else if (rawType === 'item') {
          entityType = EntityType.ITEM;
        }

        const metadata = entity?.state?.metadata || {};
        const contentId = entity?.entity_ref?.content_id;
        const objectDefinition = this.getObjectDefinition(contentId);
        const entityName = metadata.display_name || metadata.name || entity?.display_name ||
          objectDefinition?.label || (contentId ? String(contentId).replace(/[_-]+/g, ' ') : String(entity.entity_type || 'entity'));

        const options = {
          team: metadata.team,
          stats: metadata.stats || {},
          movementSpeed: metadata.movement_speed,
          actionsPerTurn: metadata.actions_per_turn,
          initiativeBonus: metadata.initiative_bonus
        };

        const created = this.createEntityObject(q, r, entityType, entityName, null, options);
        if (created) {
          created.dcEntityRef = entity?.instance_id || entity?.entity_ref?.content_id || null;
          created.dcCharacterId = Number(metadata.character_id || entity?.character_id || 0) || null;
          created.dcStatePayload = entity?.state || null;
        }
      });

      // Auto-enter initiative only once per campaign-backed encounter context.
      const shouldAutoStart = this.turnManagementSystem &&
        this.canUseServerCombatApi() &&
        this.resolveCampaignId() &&
        !this.stateManager.get('encounterId') &&
        !this.stateManager.get('combatActive');

      if (shouldAutoStart) {
        this.startCombat({ force: true });
      }
    },

    /**
     * Resolve object definition by content ID.
     * @param {string} contentId - Object content ID
     * @returns {Object|null}
     */
    getObjectDefinition: function (contentId) {
      if (!contentId) {
        return null;
      }

      const definitions = this.dungeonData?.object_definitions;
      if (!definitions || typeof definitions !== 'object') {
        return null;
      }

      return definitions[contentId] || null;
    },

    /**
     * Get obstacle mobility profile at hex in active room.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {{movable: boolean, passable: boolean, stackable: boolean}|null}
     */
    getObstacleMobilityAtHex: function (q, r) {
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      if (!entities.length || !this.activeRoomId) {
        return null;
      }

      const obstacle = entities.find((entity) => {
        if (entity?.entity_type !== 'obstacle') {
          return false;
        }

        const placement = entity.placement;
        if (!placement || placement.room_id !== this.activeRoomId || !placement.hex) {
          return false;
        }

        return Number(placement.hex.q) === q && Number(placement.hex.r) === r;
      });

      if (!obstacle) {
        return null;
      }

      const objectDefinition = this.getObjectDefinition(obstacle?.entity_ref?.content_id);
      const metadata = obstacle?.state?.metadata || {};
      const definitionMovement = objectDefinition?.movement || {};

      const movable = (typeof metadata.movable === 'boolean') ? metadata.movable : Boolean(objectDefinition?.movable);
      const passable = (typeof metadata.passable === 'boolean') ? metadata.passable : Boolean(definitionMovement.passable);
      const stackable = (typeof metadata.stackable === 'boolean') ? metadata.stackable : Boolean(objectDefinition?.stackable);

      return { movable, passable, stackable };
    },

    /**
     * Describe passability text for a hex.
     */
    describePassability: function (obstacleProfile, inActiveRoom) {
      if (obstacleProfile) {
        if (!obstacleProfile.passable && !obstacleProfile.movable) {
          return 'Impassable (fixed)';
        }
        if (!obstacleProfile.passable && obstacleProfile.movable) {
          return 'Impassable (movable)';
        }
        if (obstacleProfile.passable && obstacleProfile.movable) {
          return 'Passable (movable)';
        }
        return 'Passable';
      }

      return inActiveRoom ? 'Open floor' : 'Outside active room';
    },

    /**
     * Describe entities at a hex (live ECS first, then payload fallback).
     */
    describeEntitiesAtHex: function (q, r) {
      const labels = [];

      if (this.entityManager) {
        const liveEntities = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent', 'CombatComponent');
        liveEntities.forEach((entity) => {
          const pos = entity.getComponent('PositionComponent');
          if (pos?.q !== q || pos?.r !== r) {
            return;
          }
          const identity = entity.getComponent('IdentityComponent');
          const combat = entity.getComponent('CombatComponent');
          const teamLabel = combat?.team ? ` (${combat.team})` : '';
          labels.push(`${identity?.name || 'Entity'}${teamLabel}`);
        });
      }

      if (labels.length) {
        return labels;
      }

      const payloadEntities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      const fallback = payloadEntities.filter((candidate) => {
        if (!candidate?.placement || candidate.placement.room_id !== this.activeRoomId) {
          return false;
        }
        const hex = candidate.placement.hex;
        return hex && Number(hex.q) === q && Number(hex.r) === r;
      });

      fallback.forEach((candidate) => {
        const metadata = candidate?.state?.metadata || {};
        const displayName = metadata.display_name || metadata.name;
        if (displayName) {
          labels.push(displayName);
          return;
        }
        const contentId = candidate?.entity_ref?.content_id;
        labels.push(contentId ? String(contentId).replace(/[_-]+/g, ' ') : String(candidate.entity_type || 'entity'));
      });

      return labels;
    },

    /**
     * Describe objects on a hex from room payload and object definitions.
     */
    describeObjectsAtHex: function (hex, q, r) {
      const labels = [];

      if (hex && Array.isArray(hex.objects)) {
        hex.objects.forEach((object) => {
          if (object?.label) {
            labels.push(object.label);
          } else if (object?.object_id) {
            labels.push(String(object.object_id).replace(/[_-]+/g, ' '));
          }
        });
      }

      const obstacleLabel = this.getObstacleMobilityAtHex(q, r) ? this.getObjectLabelAtHex(q, r) : null;
      if (obstacleLabel) {
        labels.push(obstacleLabel);
      }

      return labels;
    },

    /**
     * Describe connection metadata for a hex if present.
     */
    describeConnectionAtHex: function (q, r) {
      const connections = Array.isArray(this.dungeonData?.connections) ? this.dungeonData.connections : [];
      if (!connections.length) {
        return null;
      }

      const match = connections.find((connection) => {
        const fromHex = connection?.from_hex;
        const toHex = connection?.to_hex;
        return (fromHex && Number(fromHex.q) === q && Number(fromHex.r) === r) ||
               (toHex && Number(toHex.q) === q && Number(toHex.r) === r);
      });

      if (!match) {
        return null;
      }

      const targetRoom = match.to_room === this.activeRoomId ? match.from_room : match.to_room;
      const status = [];
      status.push(match.is_passable ? 'passable' : 'blocked');
      if (match.is_discovered) {
        status.push('discovered');
      }

      return `${match.type || 'connection'} -> ${targetRoom || 'unknown'} (${status.join(', ')})`;
    },

    /**
     * Build a detail payload for the hovered hex.
     */
    getHexDetail: function (q, r) {
      const room = this.getActiveRoomData();
      if (!room) {
        return null;
      }

      const hex = Array.isArray(room.hexes) ? room.hexes.find((candidate) => Number(candidate.q) === q && Number(candidate.r) === r) : null;
      const inRoom = Boolean(hex);
      const obstacleProfile = this.getObstacleMobilityAtHex(q, r);

      return {
        roomName: inRoom ? room.name : `${room.name} (outside footprint)` ,
        terrain: room.terrain?.type || 'unknown',
        elevationFt: inRoom && Number.isFinite(Number(hex?.elevation_ft)) ? Number(hex.elevation_ft) : null,
        lighting: room.lighting?.level || 'unknown',
        passability: this.describePassability(obstacleProfile, inRoom),
        objects: this.describeObjectsAtHex(hex, q, r),
        entities: this.describeEntitiesAtHex(q, r),
        connection: this.describeConnectionAtHex(q, r)
      };
    },

    /**
     * Get object label (if any) at a given hex in the active room.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {string|null}
     */
    getObjectLabelAtHex: function (q, r) {
      // Prefer live ECS entities so session-placed objects are labeled
      if (this.entityManager) {
        const liveEntities = this.entityManager.getEntitiesWith('PositionComponent', 'IdentityComponent');
        const match = liveEntities.find((candidate) => {
          const pos = candidate.getComponent('PositionComponent');
          return pos && pos.q === q && pos.r === r;
        });

        if (match) {
          const identity = match.getComponent('IdentityComponent');
          if (identity?.name) {
            return identity.name;
          }
        }
      }

      // Fallback to dungeon payload for pre-seeded entities
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      if (!entities.length || !this.activeRoomId) {
        return null;
      }

      const entity = entities.find((candidate) => {
        if (!candidate?.placement || candidate.placement.room_id !== this.activeRoomId) {
          return false;
        }

        const hex = candidate.placement.hex;
        if (!hex) {
          return false;
        }
        return Number(hex.q) === q && Number(hex.r) === r;
      });

      if (!entity) {
        return null;
      }

      const contentId = entity?.entity_ref?.content_id;
      const definition = this.getObjectDefinition(contentId);
      if (definition?.label) {
        return definition.label;
      }

      if (contentId) {
        return String(contentId).replace(/[_-]+/g, ' ');
      }

      return entity.entity_type ? String(entity.entity_type) : null;
    },

    /**
     * Get object identifier (if any) at a given hex in the active room.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {string|null}
     */
    getObjectIdAtHex: function (q, r) {
      const entities = Array.isArray(this.dungeonData?.entities) ? this.dungeonData.entities : [];
      if (!entities.length || !this.activeRoomId) {
        return null;
      }

      const entity = entities.find((candidate) => {
        if (!candidate?.placement || candidate.placement.room_id !== this.activeRoomId) {
          return false;
        }

        const hex = candidate.placement.hex;
        if (!hex) {
          return false;
        }
        return Number(hex.q) === q && Number(hex.r) === r;
      });

      if (!entity) {
        return null;
      }

      return entity?.instance_id || entity?.entity_ref?.content_id || entity?.entity_type || null;
    },

    /**
     * Set active room and redraw room content.
     * @param {string} roomId - Target room ID
     */
    setActiveRoom: function (roomId) {
      if (!roomId || !this.dungeonData?.rooms || !this.dungeonData.rooms[roomId]) {
        return;
      }

      this.activeRoomId = roomId;
      this.stateManager.set('activeRoomId', roomId);
      this.paintActiveRoom();
      this.renderActiveRoomEntities();
      this.refreshFogOfWar();
      // Load chat history for the newly active room
      if (this.uiManager && this.uiManager.loadChatHistory) {
        this.uiManager.loadChatHistory();
      }
      console.log('Active room set:', roomId);
    },

    /**
     * Apply dungeon payload and initialize active room view.
     */
    applyDungeonData: function () {
      // Validate schema version for compatibility
      const schemaVersion = this.dungeonData?.schema_version;
      if (!schemaVersion) {
        console.warn('Dungeon payload missing schema_version field. Assuming 1.0.0.');
      } else if (schemaVersion !== '1.0.0') {
        console.warn(`Dungeon schema version ${schemaVersion} may not be fully compatible. Expected 1.0.0.`);
      }

      const rooms = this.dungeonData?.rooms;
      if (!rooms || Object.keys(rooms).length === 0) {
        return;
      }

      if (!this.activeRoomId || !rooms[this.activeRoomId]) {
        this.activeRoomId = Object.keys(rooms)[0];
      }

      this.setActiveRoom(this.activeRoomId);
    },

    /**
     * Try to transition to a connected room at a given hex.
     * @param {number} q - Axial q coordinate
     * @param {number} r - Axial r coordinate
     * @returns {boolean}
     */
    tryTransitionAtHex: function (q, r) {
      const connections = Array.isArray(this.dungeonData?.connections) ? this.dungeonData.connections : [];
      if (!connections.length || !this.activeRoomId) {
        return false;
      }

      const match = connections.find((connection) => {
        if (connection?.is_passable === false) {
          return false;
        }

        const fromMatch = connection.from_room === this.activeRoomId &&
          Number(connection?.from_hex?.q) === q &&
          Number(connection?.from_hex?.r) === r;
        const toMatch = connection.to_room === this.activeRoomId &&
          Number(connection?.to_hex?.q) === q &&
          Number(connection?.to_hex?.r) === r;

        return fromMatch || toMatch;
      });

      if (!match) {
        return false;
      }

      let nextRoomId = null;
      let nextHex = null;

      if (match.from_room === this.activeRoomId) {
        nextRoomId = match.to_room;
        nextHex = match.to_hex;
      } else {
        nextRoomId = match.from_room;
        nextHex = match.from_hex;
      }

      this.setActiveRoom(nextRoomId);

      const destinationHex = this.findHexByCoords(Number(nextHex?.q), Number(nextHex?.r));
      if (destinationHex) {
        const previousSelectedHex = this.stateManager.get('selectedHex');
        if (previousSelectedHex && previousSelectedHex !== destinationHex) {
          this.onHexOut(previousSelectedHex);
        }
        this.setSelectedHex(destinationHex);
      }

      console.log('Transitioned room:', this.activeRoomId, 'via connection', match.connection_id);
      return true;
    },

    /**
     * Apply campaign launch context to initialize map state.
     */
    applyLaunchContext: function () {
      const context = this.launchContext || {};
      const hasContext = Boolean(
        (Number(context.campaign_id) > 0) ||
        context.room_id ||
        context.dungeon_level_id ||
        context.map_id
      );

      if (!hasContext) {
        return;
      }

      const startQ = Number.isFinite(Number(context.start_q)) ? Number(context.start_q) : 0;
      const startR = Number.isFinite(Number(context.start_r)) ? Number(context.start_r) : 0;
      const startHex = this.findHexByCoords(startQ, startR);

      if (startHex) {
        const previousSelectedHex = this.stateManager.get('selectedHex');
        if (previousSelectedHex && previousSelectedHex !== startHex) {
          this.onHexOut(previousSelectedHex);
        }
        this.setSelectedHex(startHex);
        this.refreshFogOfWar();
        console.log('Applied launch context start hex:', startQ, startR, context);
      } else {
        console.warn('Launch context start hex not found in current grid:', startQ, startR, context);
      }
    }

    ,

    /**
     * Find best player entity candidate for launch hydration.
     * Prefers a player-team entity on the launch start hex.
     * @returns {Entity|null}
     */
    findLaunchPlayerEntity: function () {
      if (!this.entityManager) {
        return null;
      }

      const entities = this.entityManager.getEntitiesWith('PositionComponent', 'CombatComponent');
      if (!Array.isArray(entities) || !entities.length) {
        return null;
      }

      const playerEntities = entities.filter((entity) => {
        const combat = entity.getComponent('CombatComponent');
        if (!combat) {
          return false;
        }

        return combat?.isPlayerTeam ? combat.isPlayerTeam() : (combat?.team === Team.PLAYER || combat?.team === 'player');
      });

      if (!playerEntities.length) {
        return null;
      }

      const startQ = Number.isFinite(Number(this.launchContext?.start_q)) ? Number(this.launchContext.start_q) : 0;
      const startR = Number.isFinite(Number(this.launchContext?.start_r)) ? Number(this.launchContext.start_r) : 0;
      const onStartHex = playerEntities.find((entity) => {
        const pos = entity.getComponent('PositionComponent');
        return pos && pos.q === startQ && pos.r === startR;
      });

      return onStartHex || playerEntities[0] || null;
    }

    ,

    /**
     * Select launch player entity to hydrate character sheet.
     * @returns {boolean}
     */
    applyLaunchCharacterSelection: function () {
      if (!this.stateManager || this.stateManager.get('selectedEntity')) {
        return true;
      }

      const hasCampaignContext = Number(this.launchContext?.campaign_id || 0) > 0;
      if (!hasCampaignContext) {
        return false;
      }

      const entity = this.findLaunchPlayerEntity();
      if (!entity) {
        return false;
      }

      this.selectEntity(entity);
      return true;
    }

    ,

    /**
     * Populate character sheet from launch character context when no entity is selected.
     */
    applyLaunchCharacterSummary: function () {
      if (!this.uiManager || !this.stateManager) {
        return;
      }

      if (this.stateManager.get('selectedEntity')) {
        return;
      }

      const hasCampaignContext = Number(this.launchContext?.campaign_id || 0) > 0;
      const characterId = Number(this.launchContext?.character_id || 0);

      if (!hasCampaignContext) {
        return;
      }

      // First display launch character, then load full state from API if available
      this.uiManager.showLaunchCharacter(this.launchCharacter || {});

      // Load full character state from API
      if (characterId > 0) {
        this.loadCharacterFromApi(characterId);
      }
    }

    ,

    /**
     * Load full character state from API and display in character sheet.
     */
    loadCharacterFromApi: function (characterId) {
      if (!characterId || !this.uiManager) {
        return;
      }

      const url = `/api/character/${characterId}/state`;
      
      fetch(url)
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          if (data.success && data.data) {
            this.uiManager.showLaunchCharacter(data.data);
          }
        })
        .catch(error => {
          console.log('Character API load optional; demo continues:', error);
        });
    }
  };

})(Drupal, once);
