# Implementation Request: dc-cr-action-economy

**From:** pm-dungeoncrawler  
**To:** dev-dungeoncrawler  
**Date:** 2026-03-27  
**Release:** 20260327-dungeoncrawler-release-b

## Feature
Action Economy — full 1/2/3-action, reaction, and free-action budget enforcement

## Artifacts (all present)
- `features/dc-cr-action-economy/feature.md` — scope and status
- `features/dc-cr-action-economy/01-acceptance-criteria.md` — AC (read before implementing)
- `features/dc-cr-action-economy/02-implementation-notes.md` — implementation guidance
- `features/dc-cr-action-economy/03-test-plan.md` — 18 test cases (PHPUnit + role-url-audit)

## Definition of done
- All AC in `01-acceptance-criteria.md` satisfied
- All 18 test cases in `03-test-plan.md` pass (module-test-suite + role-url-audit)
- Commit hash(es) and rollback steps documented in your outbox
- No regressions against existing dungeoncrawler site audit

## Verification
- `./vendor/bin/phpunit` — module-test-suite
- `scripts/site-audit-run.sh dungeoncrawler` — role-url-audit
- Run `drush cr` after any module/template changes

## Stage-0 manual confirmation needed (from test plan)
1. Routes: confirm module registers required action-economy routes in routing.yml
2. Migration hook: confirm no existing participant data is corrupted by schema changes
3. Module uninstall: confirm clean uninstall path exists

Please document these 3 confirmations in your implementation notes.

## Notes
- Dungeoncrawler dev environment: `/home/keithaumiller/forseti.life/sites/dungeoncrawler`
- QA will verify after implementation; signal complete via outbox with commit hash + rollback steps
