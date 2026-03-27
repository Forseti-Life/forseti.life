# Implementation Request: dc-cr-ancestry-system

**From:** pm-dungeoncrawler  
**To:** dev-dungeoncrawler  
**Date:** 2026-03-27  
**Release:** 20260327-dungeoncrawler-release-b

## Feature
Ancestry System — content type, seed data (core ancestries), character integration, and public API routes

## Artifacts (all present)
- `features/dc-cr-ancestry-system/feature.md` — scope and status
- `features/dc-cr-ancestry-system/01-acceptance-criteria.md` — AC (read before implementing)
- `features/dc-cr-ancestry-system/02-implementation-notes.md` — implementation guidance
- `features/dc-cr-ancestry-system/03-test-plan.md` — 19 test cases (module-test-suite + role-url-audit)

## Definition of done
- All AC in `01-acceptance-criteria.md` satisfied
- All 19 test cases in `03-test-plan.md` pass (module-test-suite + role-url-audit)
- Commit hash(es) and rollback steps documented in your outbox
- No regressions against existing dungeoncrawler site audit

## Verification
- `./vendor/bin/phpunit` — module-test-suite
- `scripts/site-audit-run.sh dungeoncrawler` — role-url-audit (public GET /ancestries routes)
- Run `drush cr` after any module/template/routing changes

## Stage-0 manual confirmation needed (from test plan)
1. Route paths: confirm ancestry routes registered in routing.yml match expected URL structure
2. Storage format: confirm ancestry attribute storage format (boost/flaw arrays) matches AC spec
3. Boost/flaw mechanics: confirm human ancestry free-boost logic handles edge cases per AC
4. Human free-boost API shape: confirm response shape matches documented contract

Please document these 4 confirmations in your implementation notes.

## Notes
- Dungeoncrawler dev environment: `/home/keithaumiller/forseti.life/sites/dungeoncrawler`
- QA will verify after implementation; signal complete via outbox with commit hash + rollback steps
