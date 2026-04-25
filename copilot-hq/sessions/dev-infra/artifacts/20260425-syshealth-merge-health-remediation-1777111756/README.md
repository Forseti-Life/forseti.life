# HQ repo has merge/integration blockers

- Agent: dev-infra
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-25T10:00:31Z
- Source: system health check

## Issue

The HQ repo has merge/integration blockers.

Summary: 160 tracked local change(s), 62 untracked file(s)

Details:
```
Tracked change: copilot-hq/dashboards/FEATURE_PROGRESS.md
Tracked change: copilot-hq/inbox/responses/langgraph-parity-latest.json
Tracked change: copilot-hq/inbox/responses/langgraph-ticks.jsonl
Tracked change: copilot-hq/org-chart/sites/infrastructure/qa-regression-checklist.md
Tracked change: copilot-hq/sessions/accountant-forseti/inbox/20260422-ceo-escalation-unblock-aws-github-access/roi.txt
Tracked change: copilot-hq/sessions/ba-dungeoncrawler/inbox/20260414-ba-refscan-dungeoncrawler-pf2e-bestiary-1-lvl-1-5/roi.txt
Tracked change: copilot-hq/sessions/ba-dungeoncrawler/inbox/20260414-ba-refscan-dungeoncrawler-pf2e-bestiary-2-lvl-1-5/roi.txt
Tracked change: copilot-hq/sessions/ba-dungeoncrawler/inbox/20260414-ba-refscan-dungeoncrawler-pf2e-bestiary-3-lvl-1-5/roi.txt
Tracked change: copilot-hq/sessions/ba-dungeoncrawler/inbox/20260414-ba-refscan-dungeoncrawler-pf2e-core-rulebook-fourth-prin/roi.txt
Tracked change: copilot-hq/sessions/ba-dungeoncrawler/inbox/20260414-ba-refscan-dungeoncrawler-pf2e-gamemastery-guide/roi.txt
Tracked change: copilot-hq/sessions/ba-dungeoncrawler/inbox/20260414-ba-refscan-dungeoncrawler-pf2e-gods-and-magic/roi.txt
Tracked change: copilot-hq/sessions/ba-dungeoncrawler/inbox/20260414-ba-refscan-dungeoncrawler-pf2e-guns-and-gears/roi.txt
Tracked change: copilot-hq/sessions/ba-dungeoncrawler/inbox/20260414-ba-refscan-dungeoncrawler-pf2e-secrets-of-magic/roi.txt
Tracked change: copilot-hq/sessions/ceo-copilot-2/inbox/20260425-syshealth-dead-letter-board-20260422-needs-ceo-copilot-2-20260422-board-escalation-production-outage-homepage-500/README.md
Tracked change: copilot-hq/sessions/ceo-copilot-2/inbox/20260425-syshealth-dead-letter-board-20260422-needs-ceo-copilot-2-20260422-board-escalation-production-outage-homepage-500/roi.txt
Tracked change: copilot-hq/sessions/dev-infra/artifacts/active-inbox-item.json
Tracked change: copilot-hq/sessions/dev-infra/inbox/20260425-syshealth-executor-failures-prune/README.md
Tracked change: copilot-hq/sessions/dev-infra/inbox/20260425-syshealth-executor-failures-prune/roi.txt
Tracked change: copilot-hq/sessions/dev-infra/inbox/20260425-syshealth-merge-health-remediation/.exec-lock/created
Tracked change: copilot-hq/sessions/dev-infra/inbox/20260425-syshealth-merge-health-remediation/.exec-lock/owner
Additional tracked changes: 140
Additional untracked files: 42
```

Inspect:
```bash
git status --short --branch
```
If a merge is in progress and should be abandoned:
```bash
git merge --abort
```
If a rebase/cherry-pick/revert is in progress, finish or abort it. If local tracked changes are pending, checkpoint/stash/clean them before the next merge or pull.

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
