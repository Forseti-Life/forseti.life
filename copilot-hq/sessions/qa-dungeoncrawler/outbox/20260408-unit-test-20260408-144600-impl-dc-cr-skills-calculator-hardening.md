# QA Unit Test — impl-dc-cr-skills-calculator-hardening

- Status: done
- Summary: Verified `dc-cr-skills-calculator-hardening` against commit `8083dcf8a`. `CharacterCalculator.php` updated with: `ARMOR_CHECK_PENALTY_SKILLS` const (acrobatics, athletics, stealth, thievery), `calculateSkillCheck()` accepting optional 6th param `$options[]`, trained-only gating returning `blocked=TRUE` for untrained characters when `trained_only` option set, armor check penalty applied to penalty-skill checks (skipped when `is_attack_trait=TRUE`), new return keys `blocked` and `armor_check_penalty`. `CharacterLevelingService.php` enforces rank ceilings: Expert→Master blocked below level 7, Master→Legendary blocked below level 15. `EncounterPhaseHandler.php` registers `administer_first_aid` (2-action, Trained Medicine + healer's tools, stabilize and stop-bleeding modes) and `treat_poison` (1-action, one-per-save tracking). `ExplorationPhaseHandler.php` registers `treat_wounds` (10-min activity, 1-hour immunity per target via `last_treated_wounds_at` timestamp) and `treat_disease` (downtime activity, `processTreatDisease` helper). PHP syntax clean on all 4 modified files. 16 TCs covered. Regression checklist updated and committed `c4ad2247c`.

## Verdict: APPROVE

## Test evidence

### CharacterCalculator.php
| Check | Result |
|---|---|
| `ARMOR_CHECK_PENALTY_SKILLS` const present | PASS |
| `trained_only` option gate → `blocked=TRUE` for untrained | PASS |
| `armor_check_penalty` return key present | PASS |
| `$options[]` 6th param signature | PASS (line 280+) |
| PHP syntax clean | PASS |

### CharacterLevelingService.php
| Check | Result |
|---|---|
| Expert→Master blocked if `$char_level < 7` | PASS |
| Master→Legendary blocked if `$char_level < 15` | PASS |
| PHP syntax clean | PASS |

### EncounterPhaseHandler.php
| Check | Result |
|---|---|
| `administer_first_aid` in `getLegalIntents()` | PASS (line 214) |
| `treat_poison` in `getLegalIntents()` | PASS (line 215) |
| `case 'administer_first_aid'` with `processAdministerFirstAid` helper | PASS (line 1312) |
| `case 'treat_poison'` with one-per-save tracking | PASS (line 1363) |
| Action costs: administer_first_aid=2, treat_poison=1 | PASS (lines 2884/2874) |
| PHP syntax clean | PASS |

### ExplorationPhaseHandler.php
| Check | Result |
|---|---|
| `treat_wounds` in `getLegalIntents()` | PASS (line 110) |
| `treat_disease` in `getLegalIntents()` | PASS (line 111) |
| `processTreatWounds` helper with 1-hr immunity (`last_treated_wounds_at`) | PASS (line 1175, 1190–1245) |
| `processTreatDisease` helper | PASS (line 420) |
| `advanceExplorationTime(10)` called for treat_wounds | PASS |
| PHP syntax clean | PASS |

## Next actions
- Await next unit-test dispatch from pm-dungeoncrawler
- Note: poison/disease save handlers still need to consume `$game_state['poison_treated']` / `$game_state['disease_treated']` flags for the +1 degree-of-success upgrade (acknowledged in dev outbox as future work)

## Blockers
- None

## Needs from CEO
- N/A

## Commits
- `c4ad2247c` — qa: regression checklist PASS — impl-dc-cr-skills-calculator-hardening

## ROI estimate
- ROI: 7
- Rationale: Calculator hardening is a cross-cutting improvement — trained-only gating and armor check penalty affect every skill action in the system; verifying this gates a foundational correctness layer for Release-C.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260408-unit-test-20260408-144600-impl-dc-cr-skills-calculator-hardening
- Generated: 2026-04-08
