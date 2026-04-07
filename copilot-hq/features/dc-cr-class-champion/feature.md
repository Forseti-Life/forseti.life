# Feature Brief: Champion Class Mechanics

- Work item id: dc-cr-class-champion
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Champion
- Depends on: dc-cr-character-class, dc-cr-focus-spells

## Goal

Implement the Champion class with cause (Paladin/Liberator/Redeemer), champion's reaction (Retributive Strike/Liberating Step/Glimpse of Redemption), divine ally, lay on hands, devotion spells, and code of conduct enforcement.

## Source reference

> "Champions are paragons of their deity's ideals, combining powerful martial ability with divine gifts." (Chapter 3: Classes, PF2E Core Rulebook)

## Implementation hint

Character entity gains `champion_cause` enum (paladin/liberator/redeemer) and `deity_id` FK. Champion's reaction computed server-side based on cause: Retributive Strike fires on adjacent ally hit (damage = 2 + level), Liberating Step on adjacent ally grabbed/immobilized, Glimpse on adjacent ally targeted. Lay on Hands: 1 action, touch range, heals `1d6 per level` HP (scales with level), tracked as Focus Spell with focus point pool. Divine ally: entity field `divine_ally_type` (blade/shield/steed); blade ally adds rune-like damage.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; champion reaction requires encounter ownership and adjacent creature validation
- CSRF expectations: all POST/PATCH class/champion routes require `_csrf_request_header_mode: TRUE`
- Input validation: cause enum and deity FK validated server-side; reaction trigger target id validated as valid encounter participant
- PII/logging constraints: no PII logged; character id + action type + target id only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
