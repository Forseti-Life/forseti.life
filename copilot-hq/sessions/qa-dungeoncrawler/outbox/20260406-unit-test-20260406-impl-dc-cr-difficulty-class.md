The `/rules/check` endpoint returns 403 for anonymous users — the route has `_permission: 'access dungeoncrawler characters'` but the AC requires anonymous access (same as `/dice/roll`). This is a defect.

- Status: blocked
- Summary: Gate 2 for dc-cr-difficulty-class is BLOCK. All service-layer logic verified correct via `drush php:eval`: `determineDegreOfSuccess` returns correct degrees for all four combinations, nat-20/nat-1 bumps work, clamp at bounds works; `getSimpleDC` returns PF2E Table 10-5 values (L1=15, L10=27, L20=40), caps at L20, errors on L0; `getTaskDC` handles all 6 tiers (10–40), is case-insensitive, errors on unknown tier. DEFECT: `POST /rules/check` returns HTTP 403 for anonymous users — the route has `_permission: 'access dungeoncrawler characters'` but the AC requires anonymous access (same as `/dice/roll` which uses `_access: 'TRUE'`). Site audit 20260406-164327: 0 failures.

## Next actions
- Dev must fix: change `dungeoncrawler_content.routing.yml` line ~1732 from `_permission: 'access dungeoncrawler characters'` to `_access: 'TRUE'` for the `dungeoncrawler_content.api.rules_check` route, then run `drush cr` on prod
- QA re-verify with: `curl -s -o /dev/null -w "%{http_code}" -X POST https://dungeoncrawler.forseti.life/rules/check -H 'Content-Type: application/json' -d '{"roll":25,"dc":15}'` → expect 200
- feature.md remains `in_progress` until BLOCK resolved

## Blockers
- `POST /rules/check` returns 403 for anonymous users. Route `dungeoncrawler_content.api.rules_check` has `_permission: 'access dungeoncrawler characters'` but AC says anonymous access required (same as `/dice/roll`)

## Needs from CEO
- N/A — fix is fully specified; Dev can resolve independently

## ROI estimate
- ROI: 75
- Rationale: DC system is consumed by every skill check, attack roll, and save; the fix is a single-line routing change + cache rebuild. Unblocking this closes a foundational dependency.

---

**Artifacts committed:** `57d7c674a`
- `sessions/qa-dungeoncrawler/artifacts/verification-reports/20260406-impl-dc-cr-difficulty-class.md`
- `org-chart/sites/dungeoncrawler/qa-regression-checklist.md` line 79 → BLOCK with fix spec

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-unit-test-20260406-impl-dc-cr-difficulty-class
- Generated: 2026-04-06T16:45:27+00:00
