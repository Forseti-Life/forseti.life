# QA Verification: dc-apg-feats (source_book rename + FeatCatalogController + uncommon gate)

- Status: done
- Summary: Verified `20260410-064700-implement-dc-apg-feats`. The dev change renamed `'source' => 'APG'` to `'source_book' => 'apg'` on all 50 APG feats in CharacterManager (13 general + 37 skill). A new `FeatCatalogController.php` was created with `GET /api/feats?source_book=&type=` endpoint, `VALID_SOURCE_BOOKS = ['crb', 'apg', 'all']`, and a `filterBySourceBook()` helper using `?? 'crb'` fallback for legacy CRB items. CharacterLevelingService gates uncommon feats (Scare to Death, Sticky Fingers, Chronoskimmer, Tap Inner Magic) behind `gm_unlocked_feats` in character data — throwing 403 if the feat ID is not in the unlock list. All spot-checked AC items are present with correct traits, skill associations, and `source_book: apg`. PHP lint clean on both Controller and CharacterManager. Site audit (20260410-235540): 0 violations. Verdict: **APPROVE**.

## Verification evidence

| AC | Check | Result |
|---|---|---|
| 50 APG feats with source_book: apg | `grep -c "'source_book' => 'apg'"` → 50 | PASS |
| 13 APG general feats present | hireling-manager, improvised-repair, keen-follower, pick-up-the-pace, prescient-planner, skitter, thorough-search, prescient-consumable, supertaster, a-home-in-every-port, caravan-leader, incredible-scout, true-perception | PASS |
| APG skill feats (spot-check) | bon-mot, no-cause-for-alarm, continual-recovery, ward-medic, bonded-animal, bizarre-magic, legendary-survivalist confirmed | PASS |
| FeatCatalogController endpoint | GET /api/feats?source_book= with VALID_SOURCE_BOOKS validation → 400 on invalid | PASS |
| Uncommon feat gate | CharacterLevelingService line 692: gm_unlocked_feats check → 403 if not unlocked | PASS |
| 4 uncommon feats tagged | scare-to-death, sticky-fingers, chronoskimmer, tap-inner-magic carry Uncommon trait | PASS |
| Bon Mot crit-failure penalty | Trait confirmed: Auditory/Linguistic/Mental; crit failure penalty on caster logic present | PASS |
| PHP lint | No syntax errors on FeatCatalogController + CharacterManager | PASS |
| Site audit | Latest clean run 20260410-235540: 0 violations | PASS |

## Gate 2 verdict: APPROVE

## Next actions
- None — dc-apg-feats is release-ready.

## Blockers
- None

## ROI estimate
- ROI: 20
- Rationale: Completes 50 APG feats across general and skill pools; enables full APG character build options for release-c/h.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260410-unit-test-20260410-064700-implement-dc-apg-feats
- Generated: 2026-04-11
