# Dev Outbox — 20260327-110142-qa-findings-dungeoncrawler-30

- Status: done
- Summary: Confirmed false positive — this inbox item was auto-queued by the site-audit-run.sh script from 30 known false-positive production failures. qa-dungeoncrawler already classified these in outbox `782df1b06` as BLOCKED-PENDING-SCRIPT-FIX and explicitly noted this dev findings item should be fast-exited with CLOSED-INCORRECT-PREMISE. All 30 failures are 404s for dev-only modules not deployed to production: `copilot_agent_tracker` (7 routes under `/admin/reports/copilot-agent-tracker/*`) and `dungeoncrawler_tester` (23 routes: 22 under `/dungeoncrawler/testing/*` and 1 at `/admin/config/development/dungeoncrawler-tester`). No code regression exists. Local dev audit `20260327-105901` is clean at 0 violations, 0 failures. The production 404s are a site-audit-run.sh classification limitation — dev-infra passthrough proposal exists at `sessions/qa-dungeoncrawler/artifacts/20260326-passthrough-dev-infra-route-module-suppression/proposal.md`. No product code changes required or appropriate.

## Pre-QA permission audit
- Local audit result: 0 violations (per qa-dungeoncrawler verification `20260327-105901`)
- Production failures: 30 false positives (dev-only modules absent from production — confirmed identical to `20260322-193507` pattern)

## Root cause
- `site-audit-run.sh` classifies all non-parameterized 404 responses as failures with no module-suppression mechanism
- Two qa-permissions.json coverage gaps remain open (previously proposed in `8555c3127`, not yet applied):
  1. `dungeoncrawler-tester-dev-only` rule: path_regex only covers `/admin/config/development/dungeoncrawler-tester` — missing `/dungeoncrawler/testing/*` (22 routes)
  2. `copilot-agent-tracker-langgraph` rule: expects `deny`/`allow` but production returns 404 (module not deployed)
- Root fix is dev-infra `--ignore-modules` flag (passthrough proposal active)
- Interim fix is qa-permissions.json rule expansion (qa-dungeoncrawler scope — still pending from `8555c3127`)

## Next actions
- No dev-dungeoncrawler code changes needed
- dev-infra: action `--ignore-modules` suppression for site-audit-run.sh (passthrough proposal at `sessions/qa-dungeoncrawler/artifacts/20260326-passthrough-dev-infra-route-module-suppression/proposal.md`)
- qa-dungeoncrawler: apply 2-rule qa-permissions.json fix (previously proposed in outbox `8555c3127`) as interim mitigation

## Blockers
- None for dev-dungeoncrawler

## ROI estimate
- ROI: 2
- Rationale: Fast-exit is correct; no code changes possible or warranted. Dev-infra fix has the real leverage here.
