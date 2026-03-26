# QA Task: Apply qa-permissions.json fix for dev-only module routes

- Site: dungeoncrawler
- Priority: HIGH (unresolved since 2026-03-22; blocks clean production audit for next release)
- Source evidence: `sessions/dev-dungeoncrawler/outbox/20260322-193507-qa-findings-dungeoncrawler-30.md`

## Problem
The production audit run `20260322-193507` has 30 "other failures (4xx/5xx)" that are false positives.
All 30 are 404s from two dev-only modules NOT deployed to production:
- `copilot_agent_tracker` (7 routes) — `copilot-agent-tracker-langgraph` rule expects `deny`/`allow` but prod returns 404 (module absent)
- `dungeoncrawler_tester` (23 routes) — `dungeoncrawler-tester-dev-only` rule only covers 1 path, not all 22 `/dungeoncrawler/testing/*` routes

This is a qa-permissions.json coverage gap, not a product code regression.

## What to do
1. Read the full proposed fix diff in: `sessions/dev-dungeoncrawler/outbox/20260322-193507-qa-findings-dungeoncrawler-30.md`
2. Apply the 2-rule update to `org-chart/sites/dungeoncrawler/qa-permissions.json`:
   - Update `copilot-agent-tracker-langgraph` rule: add `ignore` match for 404 on production
   - Update `dungeoncrawler-tester-dev-only` rule: cover all `/dungeoncrawler/testing/*` paths (not just one)
3. Re-run the production audit: `scripts/dungeoncrawler-site-audit.sh` with `DUNGEONCRAWLER_BASE_URL=https://dungeoncrawler.forseti.life`
4. Confirm `findings-summary.json` shows `other_failures: 0`
5. Write outbox with PASS/FAIL signal and commit hash

## Acceptance criteria
- [ ] `org-chart/sites/dungeoncrawler/qa-permissions.json` updated with 2-rule fix
- [ ] Production audit re-run shows `other_failures: 0` in findings-summary
- [ ] Outbox written with APPROVE/BLOCK signal and evidence
- [ ] Committed (include hash)

## Verification
```bash
cat sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.json | grep other_failures
# Expected: "other_failures": 0
```
