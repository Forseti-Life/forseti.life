# Feature Brief: Environmental Hazards

- Work item id: dc-cr-hazards
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: deferred
- Defer reason: 20260228-dungeoncrawler-release-next focuses on core MVP (dice, DC, encounter, conditions, character creation, class, background, skill, equipment); this feature is secondary priority and will be re-evaluated next grooming cycle.
- Priority: unset (PM will set at triage)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26

## Goal

Implement hazards as dungeon/environment threats that are distinct from monsters: traps, haunts, and environmental dangers that have their own stat blocks (Stealth DC to detect, Disable DC to disarm, Attack/Save DCs for triggered effects). Hazards have levels, XP values, and may be simple (one-time trigger) or complex (initiative-tracked). They are essential for dungeoncrawler dungeon design.

## Source reference

> "Rules for setting Difficulty Classes, granting rewards, environments, and hazards can also be found here." (Chapter 10: Game Mastering)

## Implementation hint

Content type: `hazard` with fields for hazard type (trap/haunt/environmental), level, stealth DC, disable DC (skill), triggered effect (damage/condition), and complexity (simple|complex). Complex hazards enter initiative. Integration: hazard XP contributes to the reward system. Disable attempts use the skill check / DC system. Can be placed as dungeon room elements.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
