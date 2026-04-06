# Dungeoncrawler Feature Index

**Purpose:** Fast duplicate-detection and PM triage lookup. Before creating any new `dc-*` feature stub, check this file first. If the slug or concept already exists here, skip it. PM can filter by Category column to prioritize triage passes. `Depends on` column shows prerequisite features for implementation sequencing.

**Maintained by:** `ba-dungeoncrawler` — update this file at the end of every scan chunk (same commit as new feature stubs). Include `Category` and `Depends on` for every new row (`Depends on` may be blank if no dependencies).

**Last updated:** 2026-04-06 | Release: `20260406-dungeoncrawler-release-b` | Total: 54

---

## Index (sorted by work item id)

| Work item id | Category | Depends on | One-line summary |
|---|---|---|---|
| dc-cr-action-economy | rule-system |  | Three-action turn economy (3 actions + 1 reaction) underpinning all encounter play |
| dc-cr-alchemical-items | item |  | Alchemical consumables (bombs/elixirs/mutagens/poisons) with alchemist daily crafting |
| dc-cr-ancestry-feat-schedule | game-mechanic | dc-cr-ancestry-system, dc-cr-character-leveling | Ancestry feat progression slots at levels 1/5/9/13/17 with prerequisite checking |
| dc-cr-ancestry-system | game-mechanic |  | Ancestry selection (dwarf/elf/gnome/goblin/halfling/human) with stat grants and feat trees |
| dc-cr-ancestry-traits | rule-system | dc-cr-ancestry-system | Creature trait tags (e.g. Dwarf, Humanoid) enabling correct spell/ability targeting |
| dc-cr-animal-companion | game-mechanic |  | Animal companions for druids/rangers with own stat blocks and advancement |
| dc-cr-background-system | game-mechanic |  | Background selection granting ability boosts, skill training, and a skill feat |
| dc-cr-character-class | game-mechanic |  | 12 character classes (fighter, cleric, wizard, alchemist, etc.) with advancement tables |
| dc-cr-character-creation | rule-system |  | End-to-end character creation workflow (ancestry → class → background → stats) |
| dc-cr-character-leveling | rule-system |  | Level-up flow applying class features, ability boosts, and feats at each level |
| dc-cr-clan-dagger | item | dc-cr-dwarf-ancestry, dc-cr-equipment-system | Free dwarven ancestral starting weapon with cultural taboo on selling |
| dc-cr-conditions | rule-system |  | Conditions catalog (dying, frightened, flat-footed, etc.) with valued conditions engine |
| dc-cr-crafting | rule-system | dc-cr-downtime-mode | Crafting downtime: skill check vs. item DC, material cost, formula requirement |
| dc-cr-darkvision | rule-system |  | Darkvision sense: see in darkness/dim light as bright light (black-and-white in darkness); shared across multiple ancestries |
| dc-cr-dice-system | rule-system |  | Virtual polyhedral dice engine (d4 through d20, d%) powering all game resolution |
| dc-cr-difficulty-class | rule-system |  | DC system with level-based tables and four-degree success resolution (crit-success through crit-fail) |
| dc-cr-dwarf-ancestry | game-mechanic | dc-cr-ancestry-system, dc-cr-heritage-system, dc-cr-clan-dagger | Dwarf stat block: HP 10, speed 20, Con/Wis/Free boosts, Cha flaw, Common+Dwarven |
| dc-cr-dwarf-heritage-ancient-blooded | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-heritage-system | Ancient-Blooded Dwarf heritage granting Call on Ancient Blood reaction vs. magical saves |
| dc-cr-dwarf-heritage-death-warden | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-heritage-system | Death Warden heritage: success on necromancy saves upgraded to critical success |
| dc-cr-dwarf-heritage-forge | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-heritage-system | Forge Dwarf heritage: fire resistance (half level, min 1) and environmental heat severity downgrade |
| dc-cr-dwarf-heritage-rock | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-heritage-system | Rock Dwarf heritage: +2 DC vs. Shove/Trip, forced movement halved |
| dc-cr-dwarf-heritage-strong-blooded | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-heritage-system, dc-cr-conditions | Strong-Blooded heritage: poison resistance (half level) and accelerated poison stage reduction |
| dc-cr-dwarven-weapon-familiarity | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-equipment-system | Dwarven Weapon Familiarity feat: trained with battle axe/pick/warhammer; dwarf weapon category downgrade |
| dc-cr-dwarven-weapon-expertise | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-dwarven-weapon-familiarity, dc-cr-equipment-system | Dwarven Weapon Expertise (Feat 13): extend class expert+ proficiency to battle axe/pick/warhammer and trained dwarven weapons |
| dc-cr-downtime-mode | rule-system |  | Long-duration downtime activities: Earn Income, Craft, Retrain, etc. |
| dc-cr-elf-ancestry | game-mechanic | dc-cr-ancestry-system, dc-cr-heritage-system, dc-cr-low-light-vision, dc-cr-languages | Elf stat block: HP 6, speed 30, Dex/Int/Free boosts, Con flaw, Common+Elven+Int-bonus languages |
| dc-cr-elf-heritage-arctic | game-mechanic | dc-cr-elf-ancestry, dc-cr-heritage-system | Arctic Elf heritage: cold resistance (half level, min 1) and environmental cold severity downgrade |
| dc-cr-elf-heritage-cavern | game-mechanic | dc-cr-elf-ancestry, dc-cr-heritage-system, dc-cr-darkvision | Cavern Elf heritage: replaces low-light vision with darkvision |
| dc-cr-encounter-rules | rule-system |  | Full combat loop: initiative, MAP, degree-of-success attack resolution, HP tracking |
| dc-cr-equipment-system | item |  | Weapons, armor, shields, and adventuring gear with damage/AC/bulk fields |
| dc-cr-exploration-mode | rule-system |  | Between-encounter mode with ongoing exploration activities that affect initiative |
| dc-cr-familiar | game-mechanic |  | Magical familiars for casters with daily-selectable familiar abilities |
| dc-cr-focus-spells | game-mechanic |  | Focus Point pool and focus spells that auto-heighten; used by 6+ classes |
| dc-cr-general-feats | game-mechanic |  | General feat catalog available to all characters at levels 3/7/11/15/19 |
| dc-cr-gm-narrative-engine | rule-system | dc-cr-gm-tools, dc-cr-npc-system, dc-cr-session-structure | AI GM storytelling pipeline: scene framing, NPC dialogue, outcome narration |
| dc-cr-gm-tools | rule-system |  | GM encounter budgeting, NPC stat blocks, loot-by-level tables for AI GM use |
| dc-cr-heritage-system | game-mechanic | dc-cr-ancestry-system | Heritage selection: one heritage per ancestry at level 1, locked after creation |
| dc-cr-hazards | game-mechanic |  | Trap/haunt/environmental hazard stat blocks with Stealth/Disable DCs and effects |
| dc-cr-languages | game-mechanic |  | Language tracking with ancestry defaults and Intelligence-based free language slots |
| dc-cr-low-light-vision | rule-system |  | Low-Light Vision sense: see in dim light as bright light; ignore concealed condition due to dim light; elf default vision |
| dc-cr-magic-items | item |  | Magic item catalog (weapons/armor/wondrous) with investment, activation, rune system |
| dc-cr-mountains-stoutness | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-conditions | Mountains Stoutness: +4 HP at level 1, +1 HP/level going forward; +2 max HP for resting recovery |
| dc-cr-multiclass-archetype | game-mechanic |  | Multiclass archetypes via dedication feats enabling cross-class feature access |
| dc-cr-npc-system | game-mechanic |  | NPC entity type (allies/contacts/villains) distinct from monsters, with AI GM dialogue hooks |
| dc-cr-rituals | rule-system |  | Extended-casting rituals with skill checks, material costs, and four-degree outcomes |
| dc-cr-rock-runner | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule | Rock Runner: ignore difficult terrain from rubble/rock/stone; no flat-footed on uneven stone |
| dc-cr-session-structure | rule-system |  | One-shot and campaign session model with persistent character/world state between sessions |
| dc-cr-skill-feats | game-mechanic |  | Skill feat catalog gated by skill proficiency; taken at even levels for most classes |
| dc-cr-skill-system | game-mechanic |  | 17 skills with proficiency ranks (Untrained → Legendary) and skill check resolution |
| dc-cr-spellcasting | rule-system |  | Core spellcasting rules: spell slots, traditions, prepared vs. spontaneous, DCs |
| dc-cr-tactical-grid | rule-system |  | 5-foot grid spatial system for combat positioning, reach, area effects, and flanking |
| dc-cr-unburdened-iron | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-equipment-system | Unburdened Iron: reduce Speed penalty from armor to 0; 5-ft penalty reduction stacks for others |
| dc-cr-vengeful-hatred | game-mechanic | dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule | Vengeful Hatred: +1 circumstance bonus on attacks vs. chosen ancestry or creature type |
| dc-cr-xp-rewards | rule-system | dc-cr-character-leveling | XP tracking with 1,000 XP per level threshold; triggers character-leveling flow |
