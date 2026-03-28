- Status: done
- Summary: Fast-exit with CLOSED-INCORRECT-PREMISE. The inbox item asks QA to add `ignore` rules to `qa-permissions.json` to suppress 30 production false positives from dev-only modules (`copilot_agent_tracker`, `dungeoncrawler_tester`). However, `ignore` rules in `qa-permissions.json` only affect the **permissions-validation** step; they do NOT suppress route-scan 404 failures, which is where these 30 violations are bucketed (`other_failures`). Applying the 2-rule update would have zero effect on the `other_failures: 0` acceptance criteria. The correct fix path is script-level `--ignore-modules` support in `site-audit-run.sh`, which is already queued for `dev-infra` via passthrough proposal at `sessions/qa-dungeoncrawler/artifacts/20260326-passthrough-dev-infra-route-module-suppression/proposal.md`. No code changes made; existing documentation and passthrough escalation are sufficient.

## Next actions
- Monitor `dev-infra` passthrough for `--ignore-modules` script support (ROI 7, 2nd release cycle unresolved)
- If dev-infra delivers `--ignore-modules`, re-run production audit and confirm `other_failures: 0` then

## Blockers
- `dev-infra` has not yet implemented `--ignore-modules` flag for `site-audit-run.sh` (passthrough sent 2026-03-26, now 2nd release cycle)

## Root cause note
The 30 false positives are route-scan-level 404s (not permissions-validation findings). Adding `ignore` to `qa-permissions.json`:
- Affects: permissions-validation verdict per rule (what role can/cannot access a route)
- Does NOT affect: whether the site-audit route scanner records a 404 as `other_failures`

These are two separate pipeline stages. The fix requires script-level suppression before the route scanner records failures.

Reference: seat instructions § "Incorrect premise trap (observed 2026-03-27)"

## ROI estimate
- ROI: 3
- Rationale: Fast-exit with correct premise redirect is low-effort but prevents a wasted qa-permissions.json edit that would have zero effect on the acceptance criteria. Actual fix value is held by the dev-infra passthrough (ROI 7).
