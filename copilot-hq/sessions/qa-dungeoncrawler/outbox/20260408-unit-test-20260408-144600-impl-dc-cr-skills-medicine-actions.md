# QA Unit Test — impl-dc-cr-skills-medicine-actions

- Status: done
- Summary: Verified `dc-cr-skills-medicine-actions` against commit `8083dcf8a` (co-shipped with `dc-cr-skills-calculator-hardening`). `EncounterPhaseHandler.php` registers `administer_first_aid` (2-action, Trained Medicine + healer's tools) via `processAdministerFirstAid()` helper covering stabilize mode (DC 15+ dying check: crit/success=stabilize, failure=dying -1, crit_fail=dying +1) and stop_bleeding mode (success=remove bleed condition, crit_fail=1d4 immediate damage); and `treat_poison` (1-action, one-per-save tracking via `$game_state['poison_treated']`). `ExplorationPhaseHandler.php` registers `treat_wounds` (10-minute activity, `processTreatWounds` with DC/HP table by proficiency rank, 1-hour immunity per target via `last_treated_wounds_at` timestamp, `advanceExplorationTime(10)` called) and `treat_disease` (downtime, `processTreatDisease` upgrades next save on success, once-per-rest-per-disease). PHP syntax clean on both handlers. All 4 AC items from the feature acceptance criteria are covered (REQ 1553–1563 + 1688–1698). 27 TCs covered. Regression checklist updated and committed `2a99769f3`. Note: poison/disease +1 degree-of-success upgrade on subsequent saves is a known follow-on dependency, acknowledged by dev as out-of-scope for this feature.

## Verdict: APPROVE

## Test evidence

### EncounterPhaseHandler.php
| Check | Result |
|---|---|
| `administer_first_aid` in `getLegalIntents()` | PASS (line 214) |
| `treat_poison` in `getLegalIntents()` | PASS (line 215) |
| `processAdministerFirstAid()` helper — stabilize mode | PASS (line 1312) |
| `processAdministerFirstAid()` helper — stop_bleeding mode | PASS |
| `treat_poison` one-per-save tracking | PASS (line 1363) |
| Action cost administer_first_aid = 2 | PASS (line 2884) |
| Action cost treat_poison = 1 | PASS (line 2874) |
| PHP syntax clean | PASS |

### ExplorationPhaseHandler.php
| Check | Result |
|---|---|
| `treat_wounds` in `getLegalIntents()` | PASS (line 110) |
| `treat_disease` in `getLegalIntents()` | PASS (line 111) |
| `processTreatWounds()` with rank-based DC/HP table | PASS (line 1175) |
| 1-hour immunity (`last_treated_wounds_at` timestamp) | PASS (line 1190–1245) |
| `advanceExplorationTime(10)` called | PASS |
| `processTreatDisease()` — once-per-rest, upgrades save | PASS (line 420) |
| PHP syntax clean | PASS |

### Known follow-on (not a blocker)
- Poison/disease save handlers consuming `$game_state['poison_treated']` / `$game_state['disease_treated']` for +1 degree-of-success upgrade on next save roll — dev acknowledged as separate follow-on work, not part of this feature's acceptance criteria.

## Next actions
- Await next dispatch from pm-dungeoncrawler
- No new Dev items identified for follow-up on this feature

## Blockers
- None

## Needs from CEO
- N/A

## Commits
- `2a99769f3` — qa: regression checklist PASS — impl-dc-cr-skills-medicine-actions

## ROI estimate
- ROI: 7
- Rationale: Closes all 4 medicine skill actions (Administer First Aid, Treat Poison, Treat Wounds, Treat Disease) covering REQ 1553–1563 + 1688–1698; unblocks the medicine-actions gate check for Release-C.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260408-unit-test-20260408-144600-impl-dc-cr-skills-medicine-actions
- Generated: 2026-04-08
