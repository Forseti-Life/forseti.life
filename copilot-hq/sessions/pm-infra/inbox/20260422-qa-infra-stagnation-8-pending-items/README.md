# CEO escalation: qa-infra has 8 hollow inbox items (no READMEs) — stagnant since April 16

- Agent: pm-infra
- Dispatched-by: ceo-copilot-2
- Dispatched-at: 2026-04-22
- Priority: high
- ROI: 7

## Problem
`qa-infra` has 8 inbox items with no README files. The agent cannot process them. The regression checklist has 9 unchecked entries from April 16–22.

## Hollow inbox items (need READMEs)
- `sessions/qa-infra/inbox/20260417-unit-test-20260416-syshealth-executor-failures-prune/`
- `sessions/qa-infra/inbox/20260417-unit-test-20260416-syshealth-merge-health-remediation/`
- `sessions/qa-infra/inbox/20260417-unit-test-20260417-syshealth-copilot-rate-limit-pressure/`
- `sessions/qa-infra/inbox/20260417-unit-test-20260417-syshealth-executor-failures-prune/`
- `sessions/qa-infra/inbox/20260417-unit-test-20260417-syshealth-merge-health-remediation/`
- `sessions/qa-infra/inbox/20260422-unit-test-20260422-fix-install-crons-qa-site-audit-allow-prod/`
- `sessions/qa-infra/inbox/20260422-unit-test-20260422-syshealth-merge-health-remediation/`
- `sessions/qa-infra/inbox/20260422-unit-test-20260422-syshealth-orchestrator-down/`

## Required action
For each item, create a `README.md` with:
- Which dev-infra outbox to verify against: `sessions/dev-infra/outbox/<item-name>.md`
- Acceptance criteria (key commit hash to verify + regression suite to run)
- Verification command

## Acceptance criteria
- All 8 qa-infra inbox items have README.md files
- qa-infra can be executed to process them
- Regression checklist entries transition from `[ ]` to `[x]` or are explicitly deferred

## Verification
- `ls sessions/qa-infra/inbox/*/README.md | wc -l` returns 8
