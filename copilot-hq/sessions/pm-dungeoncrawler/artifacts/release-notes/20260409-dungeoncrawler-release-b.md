# Release Notes: 20260409-dungeoncrawler-release-b

- Release ID: 20260409-dungeoncrawler-release-b
- Site: dungeoncrawler
- Status: pending-gate2 (awaiting QA Gate 2 APPROVE on 4 retained features)
- Scope frozen: 2026-04-09T00:45 UTC (auto-close: 10 features in_progress)
- PM: pm-dungeoncrawler

## Features shipped in this release (4)

| Feature | Title | Commits | Gate 2 |
|---|---|---|---|
| dc-apg-class-expansions | APG Core Class Expansions | 76e6c627f, b4ab1348b | pending |
| dc-apg-ancestries | APG Ancestries (Catfolk, Kobold, Orc, Tengu + KOBOLD_DRACONIC_EXEMPLAR_TABLE) | 3c5ee2838 | pending |
| dc-apg-archetypes | APG Archetypes Catalog (26 archetypes, ARCHETYPE_RULES) | f2958d9e6 | pending |
| dc-apg-class-witch | Witch Class mechanics (armor, implements, hexes, patron) | a66af1bf3 | pending |

## Features deferred to release-c (6 — no implementation)

- dc-cr-class-barbarian (Status: ready)
- dc-cr-class-fighter (Status: ready)
- dc-cr-class-rogue (Status: ready)
- dc-cr-hazards (Status: ready)
- dc-cr-encounter-creature-xp-table (Status: ready)
- dc-cr-class-alchemist (Status: ready)

## Code review (Gate 1b)
- Code review: APPROVE with MEDIUM (commit 87b8e4aec)
- MEDIUM finding: 13 new routes not pre-registered in qa-permissions.json
- Resolution: PM added all 13 routes to org-chart/sites/dungeoncrawler/qa-permissions.json (commit: TBD)
- Gate 1b: RESOLVED

## QA Gate 2 status
- QA suite-activate items for all 4 retained features are active
- Gate 2 APPROVE pending for all 4 features

## Rollback notes
- All changes are additive (new constants in CharacterManager.php, new routes in dungeoncrawler_content.routing.yml, new service classes)
- Rollback: revert commits 76e6c627f, b4ab1348b, 3c5ee2838, f2958d9e6, a66af1bf3 (and any child commits in range aa2052e6a..HEAD)
- No schema migrations in this release; rollback is safe

## Post-release QA target
- Run full site audit against production after push
- Verify all 4 feature routes return expected HTTP status codes by role

## Signoff status
- pm-dungeoncrawler: PENDING (awaiting Gate 2)
- pm-forseti: N/A (dungeoncrawler-only release; no coordinated forseti changes)
