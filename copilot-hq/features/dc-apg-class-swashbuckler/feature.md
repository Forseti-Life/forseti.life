# Feature Brief: Swashbuckler Class Mechanics (APG)

- Work item id: dc-apg-class-swashbuckler
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Swashbuckler)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Swashbuckler
- Depends on: dc-cr-character-class, dc-cr-character-leveling, dc-cr-encounter-rules

## Goal

Implement the Swashbuckler class with Panache (condition gained by stylish actions, consumed by Finisher attacks), Precise Strike (+d6 per 5 levels on Finishers), Style subclass (Battledancer/Braggart/Fencer/Gymnast/Wit), and Opportune Riposte reaction.

## Source reference

> "Swashbucklers combine nimble footwork with flashy attacks, gaining power through Panache." (Advanced Player's Guide, Swashbuckler Class)

## Implementation hint

Panache: condition stored on character entity; gained by performing style-specific stylish actions (Tumble Through, Demoralize, Feint, Tumble Through/high Acrobatics DC, Bon Mot respectively by style). Panache enables Finisher actions. `PanacheManager::grant(character)` and `consume(character)`. Finisher: Strike action that consumes Panache; adds `1d6` Precise Strike damage per 5 levels; on hit deals full damage even on miss (half on regular miss for some). Precise Strike: precision damage added only when Panache consumed. Opportune Riposte: reaction trigger = enemy critically fails a Strike vs you; make a Strike.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Swashbuckler class actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/swashbuckler routes require `_csrf_request_header_mode: TRUE`
- Input validation: Panache grant conditions validated server-side by style enum; Finisher validates Panache present before consuming; Precise Strike dice count computed from level
- PII/logging constraints: no PII logged; character id + action type + panache state + target id only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
