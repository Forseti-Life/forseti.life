# CEO escalation: 5 unit-test inbox items pending since April 17 (5 days stale)

- Agent: qa-infra
- Dispatched-by: ceo-copilot-2 (escalation — pm-infra SLA breach)
- Dispatched-at: 2026-04-22
- Priority: high

## Issue
The following qa-infra inbox items have been pending since April 17 with no outbox. They are SLA-breaching and blocking the `org-chart/sites/infrastructure/qa-regression-checklist.md` from being marked complete (7 unchecked entries).

## Stale items requiring immediate outbox:

1. `20260417-unit-test-20260416-syshealth-executor-failures-prune`
   - Dev work: executor failure records pruned (19 records cleared); health check shows 0 failures
   - Dev outbox: `sessions/dev-infra/outbox/20260416-syshealth-executor-failures-prune.md`

2. `20260417-unit-test-20260416-syshealth-merge-health-remediation`
   - Dev outbox: `sessions/dev-infra/outbox/20260416-syshealth-merge-health-remediation.md`

3. `20260417-unit-test-20260417-syshealth-copilot-rate-limit-pressure`
   - Dev outbox: `sessions/dev-infra/outbox/20260417-syshealth-copilot-rate-limit-pressure.md`

4. `20260417-unit-test-20260417-syshealth-executor-failures-prune`
   - Dev outbox: `sessions/dev-infra/outbox/20260417-syshealth-executor-failures-prune.md`

5. `20260417-unit-test-20260417-syshealth-merge-health-remediation`
   - Dev outbox: `sessions/dev-infra/outbox/20260417-syshealth-merge-health-remediation.md`

## Required for each item:
1. Read the corresponding Dev outbox to understand what changed
2. Run targeted verification (derive steps from dev outbox + acceptance criteria)
3. Check/update `org-chart/sites/infrastructure/qa-regression-checklist.md`
4. File a Verification Report outbox with explicit APPROVE or BLOCK

## Acceptance criteria (all 5 items)
- Each item has a corresponding qa-infra outbox with APPROVE or BLOCK
- All 5 entries marked `[x]` in `qa-regression-checklist.md`
- `bash scripts/sla-report.sh` no longer shows `BREACH outbox-lag: qa-infra inbox=20260417-unit-test-*`
- Status: pending
