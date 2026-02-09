# Hex Map Architecture — DungeonCrawler.life

## Overview

A procedurally-generated, AI-driven hex dungeon crawl system built on PF2e open rules. Rooms are generated on first entry and persist permanently. Creatures have AI personalities that drive social and combat interactions. The entire system is defined by JSON schemas, stored as data files, and rendered through Drupal's content module.

## Core Design Principles

1. **Generate Once, Persist Forever** — When a party enters an unexplored room, the AI generates it. That room never changes (except through player interaction).
2. **PF2e-Compatible** — All stats, DCs, damage, conditions, and encounter budgets follow Pathfinder 2nd Edition rules (ORC/OGL licensed).
3. **Hex Grid** — Flat-top hexagonal grid, axial coordinates `(q, r)`, 5ft per hex (= 1 PF2e square).
4. **AI-Driven NPCs** — Creatures aren't just stat blocks. They have personalities, goals, fears, memories, and social options.
5. **Fog of War** — Players only see hexes they've explored. The map reveals as they move.

## Coordinate System

```
Flat-Top Hex Grid — Axial Coordinates (q, r)

        ___
       /   \
  ___/  0,0 \___
 /   \      /   \
/ -1,0\___/ 1,0  \
\      /   \     /
 \___/ 0,1  \___/
 /   \      /   \
/-1,1 \___/ 1,1  \
\      /   \     /
 \___/      \___/
```

- **q** = column (increases east)
- **r** = row (increases southeast)
- **Hex size** = 5ft (1 PF2e square equivalent)
- **Distance** = `max(|Δq|, |Δr|, |Δq + Δr|) × 5` feet
- **Neighbors** (flat-top): `(+1,0) (-1,0) (0,+1) (0,-1) (+1,-1) (-1,+1)`

## Schema Hierarchy

```
dungeon_level.schema.json          ← Top-level: one floor of the dungeon
├── hexmap.schema.json             ← The hex grid, connections, regions
├── room.schema.json[]             ← Rooms spanning multiple hexes
│   ├── creature references        → creature.schema.json
│   ├── item references            → item.schema.json
│   ├── trap references            → trap.schema.json
│   └── hazard references          → hazard.schema.json
├── creature.schema.json[]         ← Full PF2e stat blocks + AI personality
├── item.schema.json[]             ← Weapons, armor, consumables, treasure
├── trap.schema.json[]             ← PF2e traps (simple/complex)
├── hazard.schema.json[]           ← Environmental hazards
├── encounter.schema.json[]        ← Active combat/social encounters
└── stairways[]                    ← Connections to other levels

party.schema.json                  ← Separate: tracks the adventuring party
├── members[]                      ← Character stats, conditions, resources
├── shared_inventory[]             ← Party loot
├── fog_of_war                     ← What the party has revealed
├── exploration_state              ← Mode, light, movement speed
└── encounter_log[]                ← History of completed encounters
```

## Schema Files

| Schema | Purpose | Key Features |
|--------|---------|--------------|
| `hexmap.schema.json` | Hex grid definition | Axial coords, terrain, room assignments, connections (doors/secrets/portals), named regions with environmental effects |
| `room.schema.json` | Room spanning multiple hexes | Terrain modifiers, lighting, creature/item/trap placement, interactables, fog of war state, AI generation metadata |
| `creature.schema.json` | PF2e creature stat block | Full stats (HP/AC/saves/attacks/spells), AI personality (disposition/goals/fears/speech), combat AI (tactics/morale/flee), social options, lifecycle (boss/permanent/respawning/wandering), loot tables |
| `trap.schema.json` | PF2e trap mechanics | Simple/complex, stealth DC, multi-skill disable DCs, trigger/effect, reset, hardness/HP, state tracking |
| `hazard.schema.json` | Environmental hazards | Like traps but ongoing, optional initiative (complex hazards), area of effect, disable mechanics |
| `item.schema.json` | Items and equipment | Weapons/armor/shields/consumables/magic items, PF2e stats (damage/AC/bulk/runes), AI generation metadata (condition, identification) |
| `party.schema.json` | Adventuring party | Members with PF2e conditions/resources, marching order, exploration activities, shared inventory, currency, fog of war, map notes, encounter log, dungeon stats |
| `encounter.schema.json` | Combat/social encounters | PF2e initiative, XP budgets & threat levels, combatant tracking (HP/AC/conditions/actions), full action log with rolls & degrees of success, terrain effects, rewards |
| `dungeon_level.schema.json` | Floor orchestrator | Ties everything together, generation rules (density/difficulty/loot quality), theme system, wandering monsters, level completion state |

## Data Flow

### 1. Dungeon Generation (AI-Driven)

```
Party descends to new level
    ↓
dungeon_level created with:
  - theme (based on depth + randomness)
  - generation_rules (scaled to party level)
  - empty hex_map (grid dimensions set)
    ↓
Entrance room generated immediately
    ↓
Adjacent rooms generated on approach
  (fog of war reveals connections)
    ↓
Each room generation:
  1. Pick terrain type (from theme)
  2. Place creatures (from allowed types, within level range)
  3. Scatter loot (based on loot_quality setting)
  4. Add traps/hazards (based on frequency setting)
  5. Generate interactables and flavor text
  6. Create connections to adjacent unexplored space
```

### 2. Exploration Loop

```
Party is at hex (q, r) in exploration mode
    ↓
Reveal visible hexes (light radius + line of sight)
    ↓
Check for wandering monsters (timer-based)
    ↓
Party chooses direction → move to adjacent hex
    ↓
If entering new room:
  - Generate room if not yet generated
  - Mark as explored
  - Trigger any traps (if not detected)
  - Roll encounter check
    ↓
If encounter triggered:
  - Switch to encounter mode
  - Roll initiative for all combatants
  - Begin PF2e combat rounds
```

### 3. Combat Flow

```
Encounter starts
    ↓
Roll initiative (Perception or Stealth for Avoid Notice)
    ↓
Sort combatants by initiative
    ↓
Each round:
  Active combatant gets 3 actions + 1 reaction
    ↓
  AI creatures use combat_personality:
    - aggression → how likely to attack vs. defend
    - preferred_tactics → specific strategies
    - morale_threshold → when to consider fleeing
    - flee_behavior → where and how they run
    ↓
  Log every action to encounter.action_log
    ↓
  Check for end conditions:
    - All enemies defeated → victory
    - All PCs unconscious → defeat
    - Morale break → enemies flee/surrender
    - Negotiation attempt → social encounter
```

### 4. Persistence Model

```
Generated content:
  ✓ Rooms — permanent once created
  ✓ Room layout/terrain — never changes
  ✓ Connections — permanent
  ✓ Boss creatures — permanent death (never respawn)
  ✓ Unique creatures — permanent death

  ↻ Respawning creatures — return after interval
  ↻ Wandering monsters — random encounters reset
  ↻ Consumable items — once taken, gone
  ↻ Traps — can be reset (by creatures or auto)

  ~ Creature memory — persists across encounters
  ~ Player notes on map — persist per-party
  ~ Fog of war — persists per-party
```

## PF2e Compatibility Reference

### Encounter XP Budget (4 players)

| Threat Level | XP Budget | Use When |
|-------------|-----------|----------|
| Trivial | 40 | Routine, no real danger |
| Low | 60 | Some risk, resource drain |
| Moderate | 80 | Standard encounter |
| Severe | 120 | Challenging, real danger |
| Extreme | 160 | Boss fights, potential TPK |

### Creature XP by Level Difference

| Creature Level vs Party | XP Each |
|------------------------|---------|
| Party Level -4 | 10 |
| Party Level -3 | 15 |
| Party Level -2 | 20 |
| Party Level -1 | 30 |
| Party Level +0 | 40 |
| Party Level +1 | 60 |
| Party Level +2 | 80 |
| Party Level +3 | 120 |
| Party Level +4 | 160 |

### Degrees of Success

All d20 checks use the four-degree system:
- **Critical Success**: Beat DC by 10+
- **Success**: Meet or beat DC
- **Failure**: Below DC
- **Critical Failure**: Below DC by 10+

Natural 20 upgrades one degree. Natural 1 downgrades one degree.

### Hex Distance → PF2e Range

| Hexes | Feet | PF2e Equivalent |
|-------|------|-----------------|
| 1 | 5ft | Adjacent/melee reach |
| 2 | 10ft | Reach weapons |
| 3 | 15ft | Close burst |
| 6 | 30ft | Standard ranged |
| 12 | 60ft | Shortbow |
| 24 | 120ft | Longbow |

## File Structure

```
dungeoncrawler_content/
├── config/
│   ├── schemas/                         ← JSON Schema definitions
│   │   ├── hexmap.schema.json
│   │   ├── room.schema.json
│   │   ├── creature.schema.json
│   │   ├── trap.schema.json
│   │   ├── hazard.schema.json
│   │   ├── item.schema.json
│   │   ├── party.schema.json
│   │   ├── encounter.schema.json
│   │   └── dungeon_level.schema.json
│   └── examples/                        ← Example data files
│       └── level-1-goblin-warrens.json
├── characters/                          ← NPC character sheets
│   └── gribbles-rindsworth.json
├── src/
│   ├── Controller/
│   │   └── DashboardController.php
│   ├── Form/
│   │   └── DungeonCrawlerSettingsForm.php
│   └── Service/
│       └── GameContentManager.php
├── js/
│   └── game-cards.js
└── dungeoncrawler_content.module
```

## Next Steps

### Phase 1: Data Layer (Current)
- [x] Define JSON schemas for all game objects
- [x] Create example dungeon level with Gribbles' domain
- [x] Document architecture and PF2e compatibility

### Phase 2: PHP Services
- [ ] `HexMapService` — hex math, pathfinding, line of sight, fog of war
- [ ] `DungeonGeneratorService` — AI-driven procedural room generation
- [ ] `EncounterService` — initiative, combat rounds, action resolution
- [ ] `CreatureAIService` — NPC decision-making, personality-driven behavior
- [ ] `PartyService` — party state management, exploration tracking
- [ ] `LootService` — treasure generation based on level/rarity tables

### Phase 3: Frontend
- [ ] Hex grid renderer (Canvas or SVG)
- [ ] Fog of war overlay
- [ ] Combat UI with initiative tracker
- [ ] Character/creature stat panels
- [ ] Interactive room exploration UI

### Phase 4: AI Integration
- [ ] Room description generation
- [ ] NPC dialogue generation
- [ ] Dynamic encounter scaling
- [ ] Creature personality-driven combat decisions
- [ ] Procedural loot naming and lore generation
