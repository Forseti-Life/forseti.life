# Code Review: DungeonCrawler release-g (substantive)

- Status: done
- Summary: Reviewed all 7 commits in release-g (base `96fd33c7a` → head `4f612f4a3`), covering Investigator, Oracle, Ranger, Rogue, Swashbuckler, Fighter, and Wizard class mechanics in `CharacterManager.php`. Data-only fast-path applies — no routing, controller, schema hook, or `VALID_TYPES` changes in any commit. No CRITICAL or HIGH findings identified. Two corrective commits were confirmed correct: the Swashbuckler duplicate L11 `CLASS_ADVANCEMENT` key removal (`2a8d950ea`) and the Fighter `sudden-charge` L1 feat addition (`e8b04c729`). New constants `ARCANE_SCHOOLS` (9 schools) and `SORCERER_BLOODLINES` (11 bloodlines) are PF2E-compliant. `CharacterCreationController` class description strings for these classes are cosmetic only — no stale mechanic duplicates found for the 7 new class data entries. Verdict: **APPROVE**.

## Review scope

- Release: `dungeoncrawler-release-g`
- Base SHA: `96fd33c7a`
- Head SHA: `4f612f4a3`
- Commits reviewed: 7
  - `2948d6c31` — Investigator class data (CLASS_FEATS + CLASS_ADVANCEMENT)
  - `f48f65c43` — Oracle class data
  - `3f0f6dc52` — Ranger class data
  - `5c5e12a22` — Rogue class data (incl. sneak attack progression, rackets, debilitating strike)
  - `2a8d950ea` — Swashbuckler fix: remove duplicate L11 key in CLASS_ADVANCEMENT
  - `e8b04c729` — Fighter fix: add sudden-charge to CLASS_FEATS
  - `4f612f4a3` — Wizard class data + ARCANE_SCHOOLS + SORCERER_BLOODLINES constants
- Files changed: `CharacterManager.php` only (all commits)
- Fast-path: Data-only confirmed

## Findings

| Severity | Finding | Disposition |
|---|---|---|
| None | No CSRF/authz/schema/routing/VALID_TYPES changes | N/A |
| None | No new controller methods or routing endpoints | N/A |
| None | No hardcoded paths, getenv, exec constructs | N/A |
| Correctness ✓ | Swashbuckler duplicate L11 key removed (`2a8d950ea`) | Confirmed fix — PHP silently overwrites duplicate keys; removal is correct |
| Correctness ✓ | Fighter sudden-charge added to CLASS_FEATS[fighter] L1 (`e8b04c729`) | Confirmed correct, PF2E traits: Fighter/Flourish/Open |

## PF2E compliance spot-checks

- Rogue sneak attack: `1d6/2d6/3d6/4d6` at L1/5/11/17 — correct
- Rogue debilitating strike: `level_gained => 9` — correct
- ARCANE_SCHOOLS: 9 schools including universalist with `no_extra_slot => TRUE` — correct
- SORCERER_BLOODLINES: 11 bloodlines; genie entry has `subtype_required => TRUE` with subtypes array — correct
- Investigator Keen Recollection at L3, Vigilant Senses (Master Perception) at L7, Deductive Improvisation at L11 — correct

## Verdict

**APPROVE** — no dispatches required.

## Next actions
- None for this release. Release-g may proceed to shipping gate.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 18
- Rationale: Release-g is a substantial class mechanic expansion (7 classes) directly required for dungeoncrawler character creation. Clean APPROVE clears the release gate with no rework. High leverage since no dispatch required.
