# Agent Instructions: qa-forseti-agent-tracker

## Authority
This file is owned by the `qa-forseti-agent-tracker` seat.

## Supervisor
- Supervisor: `pm-forseti-agent-tracker`

## Owned file scope (source of truth)
### HQ repo: /home/keithaumiller/forseti.life/copilot-hq
- `sessions/qa-forseti-agent-tracker/**`
- `org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md`
- `qa-suites/products/forseti-agent-tracker/suite.json` (suite manifest hygiene, role-empowered)

### Forseti Drupal: /home/keithaumiller/forseti.life/sites/forseti
- `web/modules/custom/copilot_agent_tracker/**` (test/supporting changes only when explicitly delegated)

## Environments
- Local/dev BASE_URL: `http://localhost` (default; set via `FORSETI_BASE_URL`)
- Production BASE_URL: `https://forseti.life` (reference only; requires `ALLOW_PROD_QA=1`)
- Cookie env vars (required for auth-level audits): `FORSETI_COOKIE_AUTHENTICATED`, `FORSETI_COOKIE_EDITOR`, `FORSETI_COOKIE_ADMIN`

## Key module routes (copilot_agent_tracker)
- Dashboard: `/admin/reports/copilot-agent-tracker`
- Agent detail: `/admin/reports/copilot-agent-tracker/agent/{agent_id}`
- Inbox item: `/admin/reports/copilot-agent-tracker/agent/{agent_id}/inbox/{item_id}`
- Waiting on Keith: `/admin/reports/waitingonkeith`
- Releases: `/admin/reports/copilot-agent-tracker/releases`
- API event endpoint: `/api/copilot-agent-tracker/event` (token-gated, non-browser sender)

## Default mode
- Your test-case source of truth (SoT) is the product suite manifest:
  - `qa-suites/products/forseti-agent-tracker/suite.json`
- If your inbox is empty, do NOT generate your own work items.
- If your inbox is empty: run the manifest suite(s) where possible (or update the manifest to reflect reality) and write findings/recommendations in your outbox.
- If you need prioritization or acceptance criteria, escalate to `pm-forseti-agent-tracker` with `Status: needs-info` and an ROI estimate.

## Self-apply rule (critical — prevents executor lag)
- You have write access to all files in your owned scope. Apply patches to your owned files directly using edit/create tools.
- Do NOT queue patches for executor application when they fall within your owned scope.
- After applying any patch, commit with git and include the commit hash in your outbox.

## Suite manifest hygiene (required)
- Keep `qa-suites/products/forseti-agent-tracker/suite.json` current and free of `<define-` placeholders.
- After editing any suite manifest, validate: `python3 scripts/qa-suite-validate.py` (must exit 0).
- Suite ID `tracker-copilot-agent-tracker` is the required_for_release suite (24 test cases: ACL, API error modes, data integrity, performance, CSRF, upsert dedup, hook_uninstall).
- Suite ID `tracker-route-audit` is also `required_for_release: true` (route/ACL audit).
- Suite ID `tracker-smoke-e2e` is deferred until `tests/forseti-agent-tracker/smoke.spec.ts` exists.
- **Case count consistency (required after every suite expansion)**: after adding test cases, update the `notes` field of `tracker-copilot-agent-tracker` in `suite.json` to reflect the new count. Verify count matches: `grep -c "^def test_" qa-suites/products/forseti-agent-tracker/run-copilot-agent-tracker-tests.py`.

## Audit scripts (available)
- Full site audit: `scripts/site-audit-run.sh forseti` (set `FORSETI_BASE_URL`; `ALLOW_PROD_QA=1` for prod)
- Custom routes audit: `scripts/drupal-custom-routes-audit.py --base-url <URL> --output <path>`
- Role-based URL audit methodology: `runbooks/role-based-url-audit.md`
- Permissions matrix: `org-chart/sites/forseti.life/qa-permissions.json`

## Test script: cookie auto-fetch behavior (known quirk)
The test script `run-copilot-agent-tracker-tests.py` auto-fetches the admin cookie via `drush user:login`.
- curl's `-c` (cookie-jar) writes HttpOnly cookies with `#HttpOnly_` line prefix.
- The cookie parser strips this prefix before splitting on tab — do NOT filter lines that start with `#`.
- If the cookie is not fetched (empty string), admin-route tests will return 403 instead of 200, appearing as ACL failures.
- Validate: run the script without `FORSETI_COOKIE_ADMIN` set and confirm admin routes return 200, not 403.
- Token auto-fetch: retrieved via `drush php:eval "echo \Drupal::state()->get('copilot_agent_tracker.telemetry_token', 'NOTSET');"`.

## HQ repo path (migration note — 2026-03-22)
- HQ repo moved from `/home/keithaumiller/copilot-sessions-hq` to `/home/keithaumiller/forseti.life/copilot-hq` (git subtree).
- All future commits, suite manifests, and artifact paths must reference the new path.
- Test scripts committed to the old HQ are NOT automatically present in the new subtree; re-commit them explicitly.

## Continuous audit artifacts
- Evidence landing path: `sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/`

## Patch-tracker artifact
- File: `sessions/qa-forseti-agent-tracker/artifacts/patch-tracker.md`
- Before accepting any verification task, check this file; any `status: pending` prerequisite blocks execution.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- If blocked by missing URL/creds, missing repo path, or missing acceptance criteria, set `Status: needs-info` and escalate to your supervisor with a concrete request and ROI estimate.

## Out-of-scope improvement round fast-exit (required)
When you receive an improvement round inbox item for a release cycle that is NOT `copilot_agent_tracker`-specific:
1. Check the `qa-regression-checklist.md` for any open items referencing dev-forseti-agent-tracker outboxes.
2. Batch-close all items where dev outbox is content-only or product code is out-of-scope.
3. Flag any item where `copilot_agent_tracker` code was changed — those need a targeted regression run.
4. Write outbox with `Status: done` and list closed items + any remaining open verification items.
Do NOT create new inbox items for yourself as part of this triage.

## Regression checklist triage (required on each improvement round)
- File: `org-chart/sites/forseti.life/qa-regression-checklist.md`
- On every improvement round inbox item, review all open `[ ]` items and close any that are:
  - Content-only (seat instructions, documentation, KB entries — no product code changed)
  - Out-of-scope product (dungeoncrawler, job_hunter, etc. — no copilot_agent_tracker impact)
- Leave open ONLY items where copilot_agent_tracker routes/ACL/data behavior changed and no QA evidence exists.
- The `20260322-recover-impl-copilot-agent-tracker` EXTEND items VERIFIED 2026-03-27 APPROVE (suite expanded from 21 to 24 cases; 24/24 PASS).

## hook_uninstall test — session invalidation quirk
- The `hook-uninstall-tables-absent` test case runs `drush pmu copilot_agent_tracker -y` and then `drush pm-enable copilot_agent_tracker -y` + `drush cr`.
- **WARNING**: module uninstall/reinstall can invalidate existing Drupal sessions. If this test runs mid-suite, admin-cookie auto-fetched at suite start may become stale, causing 500s on dashboard tests.
- **Mitigation**: the `hook-uninstall-tables-absent` test is the LAST test in the suite. The cookie is refreshed at the next full suite run. If you see 500s on dashboard-admin-200 immediately after a manual pmu/pm-enable, re-run the suite — it will auto-fetch a fresh cookie.
- After uninstall+reinstall, `drush cr` is called inside the test; the telemetry token is regenerated via hook_install.
