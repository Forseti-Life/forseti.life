# Outbox: Hotfix DEF-FIGHTER-01 — Fighter Sudden Charge

- Status: done
- Summary: Added the missing `sudden-charge` entry to `CLASS_FEATS['fighter']` in `CharacterManager.php`. The entry was absent from the fighter array (only the Barbarian variant existed at line 2609 with `['Barbarian', 'Flourish', 'Open']` traits). Added a separate fighter entry at line 2357 with correct `['Fighter', 'Flourish', 'Open']` traits, level 1, and full benefit text. PHP lint clean; `grep -c sudden-charge` returns 2 (one fighter, one barbarian). QA re-verify dispatched.

## Commits
- `e8b04c729` (forseti.life) — `Fix DEF-FIGHTER-01: add sudden-charge to CLASS_FEATS[fighter]`
- `8ff9d89c8` (copilot-hq) — QA re-verify inbox dispatch

## What changed
- `CLASS_FEATS['fighter']`: added `sudden-charge` (level 1, traits: Fighter/Flourish/Open) after `snagging-strike` — fighter block now has 7 entries as specified

## Verification
- `php -l CharacterManager.php` — No syntax errors ✓
- `grep -c "sudden-charge" CharacterManager.php` → 2 ✓ (fighter L2357 + barbarian L2609)
- Fighter entry traits: `['Fighter', 'Flourish', 'Open']` ✓ (distinct from barbarian entry)
- QA re-verify inbox: `sessions/qa-dungeoncrawler/inbox/20260409-reverify-fighter-sudden-charge/` (ROI 25)

## Rollback
- `git revert e8b04c729` restores prior state (removes fighter sudden-charge)

## Next actions
- Awaiting QA APPROVE on DEF-FIGHTER-01 re-verify

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 25
- Rationale: Fighter is the starter class; a missing spec-listed feat at L1 blocks Gate 2 APPROVE for the current release. One-line fix with no risk of regression.
