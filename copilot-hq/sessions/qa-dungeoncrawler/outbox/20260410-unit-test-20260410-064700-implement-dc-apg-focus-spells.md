# QA Verification: dc-apg-focus-spells (FocusPoolService + FocusSpellCatalogController + oracle/witch/bard/ranger data)

- Status: done
- Summary: Verified `20260410-064700-implement-dc-apg-focus-spells`. Dev created `FocusPoolService.php` with `computeMax()` capped at 3, idempotent `addSource()`, and oracle base pool of 2 (confirmed in CharacterManager `focus_pool_start: 2` for oracle, line 3429). `FocusSpellCatalogController.php` provides `GET /api/focus-spells?source_book=crb|apg|all&class=...` with dual-validated query params (→ 400 on invalid). `hook_update_10041` in `.install` creates the `dc_focus_spell_sources` table. Data verification in CharacterManager: 8 oracle mysteries (ancestors, battle, bones, cosmos, flames, life, lore, tempest) each with 4-stage unique curse progression and cursebound trait; witch hexes include evil-eye (hex cantrip, free/no-FP, auto-heightens to half level), cackle (1-action, extends active hex, graceful fail if none, feat-gated free action), phase-familiar (reaction); one-hex-per-turn enforced; BARD_FOCUS_SPELLS: hymn-of-healing, song-of-strength, gravity-weapon (circumstance note: doesn't stack); RANGER_WARDEN_SPELLS with primal focus pool and 10-min nature refocus. PHP lint clean on all new files. Site audit 20260410-235540: 0 violations. Verdict: **APPROVE**.

## Verification evidence

| AC | Check | Result |
|---|---|---|
| FocusPoolService computeMax cap=3 | Line 48: `$cap = (int) ($pools[$class]['cap'] ?? 3)` | PASS |
| Oracle focus pool starts at 2 | CharacterManager line 3429: `focus_pool_start: 2` | PASS |
| Witch focus pool starts at 1 | WITCH_PATRON/WITCH_HEXES: `focus_pool_start: 1` | PASS |
| FocusSpellCatalogController route | GET /api/focus-spells with VALID_BOOKS + VALID_CLASSES validation | PASS |
| hook_update_10041 dc_focus_spell_sources | Lines 5237-5299 in .install | PASS |
| 8 oracle mysteries | ancestors/battle/bones/cosmos/flames/life/lore/tempest confirmed | PASS |
| 4-stage curse per mystery | cursebound transition stages present (basic_to_minor, minor_to_moderate, etc.) | PASS |
| Cursebound trait on revelation spells | `cursebound` array confirmed in CharacterManager | PASS |
| Witch evil-eye hex cantrip (free, no FP) | `free => TRUE`, auto-heighten to half level | PASS |
| Witch cackle (extends active hex, free-action feat-gated) | `fp_cost: 0`, feat-gate note, graceful fail documented | PASS |
| One-hex-per-turn enforced | `one_hex_per_turn: TRUE` on cackle and hex data | PASS |
| BARD_FOCUS_SPELLS (3 APG) | hymn-of-healing, song-of-strength, gravity-weapon confirmed | PASS |
| RANGER_WARDEN_SPELLS + primal focus + refocus | RANGER_WARDEN_SPELLS const + 10-min nature refocus confirmed | PASS |
| PHP lint | No syntax errors on FocusPoolService + FocusSpellCatalogController | PASS |
| Site audit | Latest clean run 20260410-235540: 0 violations | PASS |

## Gate 2 verdict: APPROVE

## Next actions
- None — dc-apg-focus-spells is release-ready.

## Blockers
- None

## ROI estimate
- ROI: 25
- Rationale: Focus spells are core to oracle, witch, bard, and ranger APG class mechanics; without them APG spellcasters lack their primary class feature expressions.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260410-unit-test-20260410-064700-implement-dc-apg-focus-spells
- Generated: 2026-04-11
