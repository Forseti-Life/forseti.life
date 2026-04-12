# Code Review: dungeoncrawler 20260411-dungeoncrawler-release-b

- Status: done
- Summary: Pre-ship code review for dungeoncrawler `20260411-dungeoncrawler-release-b` is APPROVE. One commit in scope (`616f1547c`, 02:29): feat(dc-cr-feats-ch05) — Ch5 general/skill feat system touching four service files (CharacterManager, CharacterLevelingService, FeatEffectManager, EncounterPhaseHandler). No new routes, controllers, schema changes, or HTTP surface. All changes are pure service-layer game logic. No CSRF, authz bypass, schema hook pairing, hardcoded path, or stale-duplicate findings. All checklist items pass.

## Verdict: APPROVE

**Product:** dungeoncrawler
**Release:** `20260411-dungeoncrawler-release-b`
**Release start:** `2026-04-12T01:25:21+00:00`

**Commits in scope (sites/dungeoncrawler/web/modules/custom/):**
- `616f1547c` (02:29) — feat(dc-cr-feats-ch05): implement Ch5 general/skill feat system

## Checklist

| Check | Result | Notes |
|---|---|---|
| New POST routes with CSRF | N/A | No new routes or controllers introduced |
| Authorization bypass on new code paths | PASS | `EncounterPhaseHandler::processIntent()` entry point already takes `$campaign_id` and is called from controllers that enforce `validateCampaignAccess()`. New `battle_medicine` case inherits that gate; no bypass path introduced |
| Schema hook pairing (hook_schema + hook_update_N) | N/A | No `.install` or schema changes in this commit |
| Stale private duplicates of canonical data | PASS | `assurance` removed from `$skill_mod_map` in `applyBulkFirstPassFeat()` (stale first-pass entry) now that assurance has a dedicated `feat_overrides` case — removal is correct cleanup, not duplication |
| Hardcoded absolute paths | PASS | No `/var/`, `/home/`, URL strings, or filesystem paths introduced |

## Findings
- None

## Detail: Ch5 feat system changes

**CharacterManager:** `armor-proficiency` and `weapon-proficiency` marked `repeatable: TRUE, repeatable_max: 3`; `assurance` marked `assurance_per_skill: TRUE`; `battle-medicine` added to `SKILL_FEATS` with correct traits (`General, Healing, Manipulate, Skill`) and prerequisite annotation.

**CharacterLevelingService:**
- `submitFeat()` gains optional `$feat_params` arg (stored in character data — used for assurance skill selection)
- `validateFeat()` gains `skill_feat` trait gate (AC-006): rejects feats without `Skill` trait in a `skill_feat` slot
- Repeatable max enforcement: counts owned instances, throws on `>= repeatable_max`
- Assurance per-skill duplicate guard: checks `feat_params.skill` against existing owned assurance instances

**FeatEffectManager:**
- `assurance` promoted from bulk first-pass to dedicated `feat_overrides` case: `fixed_result = 10 + proficiency_bonus`; stale `$skill_mod_map` entries for `assurance` and `specialty-crafting` correctly removed
- `specialty-crafting` and `virtuosic-performer` now rank-scale (+1/+2 at Master) instead of flat +1
- `trick-magic-item` gains tradition→skill map and crit-fail lockout flag (no new DB writes)
- `battle-medicine` added as `at_will` action entry with DC/HP table, per-healer immunity flag
- `recognize-spell` gains threshold table and crit outcome descriptors

**EncounterPhaseHandler:**
- `battle_medicine` added to `getLegalIntents()` whitelist
- `case 'battle_medicine'`: Trained Medicine gate, healer's tools gate, DC/HP table matching Treat Wounds, per-healer/per-target 1-day immunity key tracked in `$game_state['battle_medicine_immune']`. Immunity is session-scoped game state (not DB write directly), consistent with existing pattern.

## Next actions
- Release operator (pm-dungeoncrawler or pm-forseti coordinating) may proceed with DC release-b gate signoff

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 12
- Rationale: APPROVE unblocks DC release-b gate; feat system is a key gameplay feature enabling downstream Ch5 content to ship.
