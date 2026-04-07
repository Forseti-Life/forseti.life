# Feature Brief: Skills Calculator Hardening

- Website: dungeoncrawler
- Type: extend
- Module: dungeoncrawler_content
- Priority: P1
- Status: ready
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-character-leveling

## Goal

Harden `CharacterCalculator` and `CharacterLevelingService` with all missing enforcement rules identified in QA audit of core/ch04: trained-only action gating, skill rank level-ceiling enforcement, armor check penalty application, secret-roll flag support, DC calculation method, redundant-training redirect, and DC adjustment modifier stack.

## Source reference

> "You're trained in a skill when your character's training and expertise is sufficient to attempt tasks that require training. You're not trained in the skill when your level isn't sufficient." (Chapter 4: Skills, PF2E Core Rulebook Fourth Printing)

## Implementation hint

Seven targeted fixes in `CharacterCalculator::calculateSkillCheck()` and `CharacterLevelingService::submitSkillIncrease()`: (1) check `action.requiresTraining && character.proficiencyRank(skill) === 0` → return error; (2) level-gate rank increases (≥7 for expert→master, ≥15 for master→legendary); (3) apply `armor.check_penalty` to Str/Dex skill rolls unless action has `attack` trait; (4) add `secret: bool` flag to request — when true, omit raw roll from player response; (5) expose `calculateSkillDC()` = 10 + skill modifier; (6) detect duplicate skill grant sources and redirect to a different skill; (7) implement DC adjustment table (−10/−5/−2/0/+2/+5/+10) as additive modifiers on base DC, removing fixed `TASK_DC` constants.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; skill check requests require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH skill action routes require `_csrf_request_header_mode: TRUE`
- Input validation: skill id, action id, and DC adjustment modifier validated against server-side enums; secret flag is boolean only; rank increase validated against level gating server-side
- PII/logging constraints: no PII logged; secret roll values must not appear in player-visible response; character id + skill id + outcome logged only

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1553, 1554, 1556, 1563, 1564, 1566, 1567, 1568, 1600, 2321, 2323
- See `runbooks/roadmap-audit.md` for audit process.
