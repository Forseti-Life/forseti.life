# Implementation Request: dc-cr-difficulty-class

**From:** pm-dungeoncrawler  
**To:** dev-dungeoncrawler  
**Date:** 2026-03-27  
**Release:** 20260327-dungeoncrawler-release-b

## Feature
Difficulty Class — degree-of-success logic matrix, nat20/nat1 bump clamps, Simple DC table, task DC tiers, POST /rules/check endpoint, MAP regression

## Artifacts (all present)
- `features/dc-cr-difficulty-class/feature.md` — scope and status
- `features/dc-cr-difficulty-class/01-acceptance-criteria.md` — AC (read before implementing)
- `features/dc-cr-difficulty-class/03-test-plan.md` — 17 test cases (module-test-suite + role-url-audit)

Note: No `02-implementation-notes.md` found. Use AC as primary implementation guide.

## Definition of done
- All AC in `01-acceptance-criteria.md` satisfied
- All 17 test cases in `03-test-plan.md` pass (module-test-suite + role-url-audit)
- Commit hash(es) and rollback steps documented in your outbox
- No regressions against existing dungeoncrawler site audit

## Verification
- `./vendor/bin/phpunit` — module-test-suite (degree-of-success matrix, nat20/nat1 clamps, Simple/Task DC tables, POST /rules/check, MAP regression)
- `scripts/site-audit-run.sh dungeoncrawler` — role-url-audit (ACL)
- Run `drush cr` after any module/routing changes

## Stage-0 manual confirmations needed (from test plan)
1. Simple DC values: confirm the exact numeric values for the Simple DC table per AC spec
2. Task DC values: confirm numeric values for task DC tiers per AC spec
3. Difficulty case-sensitivity: confirm whether difficulty string matching is case-sensitive or normalized
4. Anon ACL policy: confirm whether POST /rules/check requires auth (same policy question as dice rolls — coordinate with dice-system implementation)

Please document these 4 confirmations in your implementation notes.

## Notes
- Dungeoncrawler dev environment: `/home/keithaumiller/forseti.life/sites/dungeoncrawler`
- Anon ACL policy for /rules/check is the same open question as /dice/roll — resolve together if possible
- QA will verify after implementation; signal complete via outbox with commit hash + rollback steps
- This is the final feature in the 20260327-dungeoncrawler-release-b scope
