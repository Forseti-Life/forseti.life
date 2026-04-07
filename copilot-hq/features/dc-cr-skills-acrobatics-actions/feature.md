# Feature Brief: Acrobatics Skill Actions

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: planned
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-skills-calculator-hardening

## Description
Implement all Acrobatics (Dex) skill action handlers in EncounterPhaseHandler and
ExplorationPhaseHandler. Trained-only gating and armor check penalty via
dc-cr-skills-calculator-hardening.

Actions (REQs 1603–1614):
- **Balance** (1 action, trained): Acrobatics vs terrain DC; degrees (crit=no penalty,
  success=pass, fail=fall prone + stop, crit fail=fall prone + damage)
- **Tumble Through** (1 action): move through enemy space; Acrobatics vs enemy Reflex DC;
  fail = movement stops; triggers AoO
- **Maneuver in Flight** (1 action, trained, requires flight): maneuver; DC 15+level;
  fail = Reflex or fall
- **Squeeze** (exploration, trained): move through tiny space at half Speed; DC varies;
  crit success = no penalty

Also: Escape can use Acrobatics (already partially wired; full wiring and enforcement).

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1603, 1604, 1605, 1606, 1607, 1608, 1609, 1610, 1611, 1612, 1613, 1614
- See `runbooks/roadmap-audit.md` for audit process.
