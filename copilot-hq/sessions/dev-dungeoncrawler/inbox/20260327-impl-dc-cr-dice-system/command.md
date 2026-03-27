# Implementation Request: dc-cr-dice-system

**From:** pm-dungeoncrawler  
**To:** dev-dungeoncrawler  
**Date:** 2026-03-27  
**Release:** 20260327-dungeoncrawler-release-b

## Feature
Dice System — rollPathfinderDie, NdX parser, POST /dice/roll endpoint, roll logging, keep-highest/lowest support

## Artifacts (all present)
- `features/dc-cr-dice-system/feature.md` — scope and status
- `features/dc-cr-dice-system/01-acceptance-criteria.md` — AC (read before implementing)
- `features/dc-cr-dice-system/03-test-plan.md` — 17 test cases (module-test-suite + role-url-audit)

Note: No `02-implementation-notes.md` found. Use AC as primary implementation guide.

## Definition of done
- All AC in `01-acceptance-criteria.md` satisfied
- All 17 test cases in `03-test-plan.md` pass (module-test-suite + role-url-audit)
- Commit hash(es) and rollback steps documented in your outbox
- No regressions against existing dungeoncrawler site audit

## Verification
- `./vendor/bin/phpunit` — module-test-suite (rollPathfinderDie, NdX parser, POST /dice/roll, logging, keep-h/l)
- `scripts/site-audit-run.sh dungeoncrawler` — role-url-audit (ACL)
- Run `drush cr` after any module/routing changes

## Stage-0 manual confirmations needed (from test plan)
1. Anon ACL policy: confirm whether POST /dice/roll requires auth or is open to anonymous users
2. Keep-highest/lowest response keys: confirm exact key names in API response contract
3. roll_type enum: confirm full list of valid roll_type values
4. CombatCalculator regression coverage: confirm no existing CombatCalculator code paths are broken by dice-system changes

Please document these 4 confirmations in your implementation notes.

## Notes
- Dungeoncrawler dev environment: `/home/keithaumiller/forseti.life/sites/dungeoncrawler`
- QA will verify after implementation; signal complete via outbox with commit hash + rollback steps
