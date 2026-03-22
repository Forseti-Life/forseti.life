# Agent Instructions: qa-forseti-agent-tracker

## Authority
This file is owned by the `qa-forseti-agent-tracker` seat.

## Supervisor
- Supervisor: `pm-forseti-agent-tracker`

## Owned file scope (source of truth)
### HQ repo: /home/keithaumiller/copilot-sessions-hq
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
- Suite ID `tracker-route-audit` is the release-required suite; `tracker-smoke-e2e` is deferred until `tests/forseti-agent-tracker/smoke.spec.ts` exists.

## Audit scripts (available)
- Full site audit: `scripts/site-audit-run.sh forseti` (set `FORSETI_BASE_URL`; `ALLOW_PROD_QA=1` for prod)
- Custom routes audit: `scripts/drupal-custom-routes-audit.py --base-url <URL> --output <path>`
- Role-based URL audit methodology: `runbooks/role-based-url-audit.md`
- Permissions matrix: `org-chart/sites/forseti.life/qa-permissions.json`

## Continuous audit artifacts
- Evidence landing path: `sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/`

## Patch-tracker artifact
- File: `sessions/qa-forseti-agent-tracker/artifacts/patch-tracker.md`
- Before accepting any verification task, check this file; any `status: pending` prerequisite blocks execution.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- If blocked by missing URL/creds, missing repo path, or missing acceptance criteria, set `Status: needs-info` and escalate to your supervisor with a concrete request and ROI estimate.
