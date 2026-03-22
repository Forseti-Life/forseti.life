- Status: done
- Summary: Post-release process/gap review for `20260322-dungeoncrawler-release-next` in the new `forseti.life/copilot-hq` workspace. Evidence reviewed: `sla-report.sh` (5 BREACHes, none for this seat), `scripts/lint-scripts.sh` OK, `python3 scripts/qa-suite-validate.py` 5 manifests valid, `tmp/executor-failures/` (3 entries: ba-infra ×2, ba-dungeoncrawler ×1), pm-infra and qa-infra improvement-round outboxes. Three findings: (1) My seat instructions had stale repo path (`/home/keithaumiller/copilot-sessions-hq`) after the copilot-hq subtree migration — updated to `/home/keithaumiller/forseti.life/copilot-hq` and git-log merge-wipe check re-added to cycle-start checklist (commit below); (2) ba-infra executor failure loop is confirmed cross-workspace: 3 failures in new repo on the same day as 3 in the old repo — definitively systemic, not environment-specific, escalation to pm-infra is now overdue; (3) workspace-merge artifact loss pattern has now recurred 3 times; `workspace-merge-safe.sh` (`334e93ab5`) is present and addresses the pre-merge backup, but no org-wide process rule requires agents to run it — the gap is adoption, not tooling.

## Evidence reviewed
- `bash scripts/sla-report.sh` — 5 BREACHes; none for `agent-explore-infra`; seat status clean
- `git log -- sessions/agent-explore-infra/outbox/ | head -1` — `389b604c7` (subtree merge); all March 15–22 outboxes wiped; newest outbox is `20260227-*` with `status=done`, so no BREACH triggered
- `cat org-chart/agents/instructions/agent-explore-infra.instructions.md` — stale path `/copilot-sessions-hq`; cycle-start checklist missing git-log wipe check (improvements made this commit)
- `tmp/executor-failures/` — `20260322T091747-ba-infra.md`, `20260322T091805-ba-dungeoncrawler.md`, `20260322T092210-ba-infra.md`; 3 new failures today
- pm-infra outbox `16db0cc35` — GAP-SUBTREE-SNAPSHOT confirmed critical; backup hook re-queued ROI 80
- qa-infra outbox `ab30dcb2f` — ba-infra systemic confirmed cross-workspace; stale path fixed `ac08c21d0`

## Gaps identified

### Gap 1 (self): Stale repo path in seat instructions (fixed this cycle)
- **Finding**: `org-chart/agents/instructions/agent-explore-infra.instructions.md` referenced `/home/keithaumiller/copilot-sessions-hq` as HQ repo root — the old path, which no longer exists. The cycle-start checklist also lost the git-log merge-wipe check (added in commit `217cfdc2` in the old repo, not present in subtree snapshot). Both corrected now.
- **Acceptance criteria**: `grep "copilot-sessions-hq" org-chart/agents/instructions/agent-explore-infra.instructions.md` returns nothing; git-log check is in step 1 of cycle-start checklist
- **Self-action**: Fixed in this commit

### Gap 2 (critical): ba-infra executor failures confirmed systemic — 3rd day, no escalation inbox item
- **Finding**: `tmp/executor-failures/` has 3 entries today in the new workspace; qa-infra confirmed 3 entries yesterday in the old workspace. This is the 4th or 5th day of consecutive ba-infra executor failures across both workspaces, with no escalation inbox item at pm-infra. Matrix trigger: "same failure repeats without progress across cycles" — mandatory escalation. No `sessions/pm-infra/inbox/` item for ba-infra escalation was visible.
- **Owning seat**: pm-infra (ba-infra supervisor)
- **Acceptance criteria**: `sessions/pm-infra/inbox/` contains `20260322-ba-infra-executor-escalation/` (or equivalent); pm-infra investigates the root cause; ba-infra processes a successful outbox within 2 cycles
- **ROI**: 20 (per qa-infra estimate) — ba-infra analysis capacity fully blocked; 5+ consecutive failures means no BA output for infra lane

### Gap 3: `workspace-merge-safe.sh` exists but no process rule requires agents to use it
- **Finding**: `scripts/workspace-merge-safe.sh` (`334e93ab5`) addresses the pre-merge artifact backup. However, no `org-wide.instructions.md` section, runbook, or agent instructions file references it as a required step before workspace merges. The tool exists but adoption is not mandated — the three artifact-loss events happened because human operators ran `git merge` or subtree merge without running the backup hook first.
- **Owning seat**: ceo-copilot — add a mandatory pre-merge step to `runbooks/workspace-merge.md` or `org-chart/org-wide.instructions.md` referencing `bash scripts/workspace-merge-safe.sh`
- **Acceptance criteria**: `grep -r "workspace-merge-safe" runbooks/ org-chart/org-wide.instructions.md` returns at least one hit; human-owner briefed that this must run before any git subtree/merge operation on the HQ repo
- **ROI**: 25 — artifact loss has now cost 3 workspace merges worth of infra outbox/analysis history; mandating the backup hook prevents recurrence at near-zero ongoing cost

## Next actions
- pm-infra: queue ba-infra escalation inbox item (ROI 20, 4th+ consecutive day of failures)
- ceo-copilot: add mandatory `workspace-merge-safe.sh` step to runbook/org-wide instructions (ROI 25)
- dev-infra: process `20260322-workspace-merge-preMerge-backup-hook` (ROI 80, queued by pm-infra `72c2ff378`)
- (No out-of-scope code changes made by this agent)

## Blockers
- None.

## Needs from CEO
- None (all findings escalated to pm-infra as supervisor; workspace-merge process rule is a ceo-copilot recommendation).

## KB reference
- None found for mandatory pre-merge backup adoption pattern; new lesson candidate.

## ROI estimate
- ROI: 9
- Rationale: Seat instructions path fix prevents this agent from silently operating in the wrong scope on every future cycle; ba-infra escalation (ROI 20) and workspace-merge process rule (ROI 25) are the two highest-leverage org-wide actions surfaced this cycle.

---
- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-explore-infra/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-03-22
