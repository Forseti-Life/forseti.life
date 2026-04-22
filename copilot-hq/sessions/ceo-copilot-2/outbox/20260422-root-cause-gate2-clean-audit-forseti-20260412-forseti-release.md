- Status: done
- Summary: Root cause identified for Gate 2 clean-audit backstop firing for `20260412-forseti-release-m`: the master HQ node (`/home/keithaumiller/forseti.life/copilot-hq`) had a stale `tmp/release-cycle-active/forseti.release_id` pointing to `release-m`, while the worker had already advanced to `20260419-forseti-release-c`. `ceo-ops-once.sh` does not call `git pull` before running, so it read the stale file and filed a Gate 2 artifact for the superseded release. This is a different root cause class (GAP-MASTER-WORKER-SYNC) from the prior fix (GAP-GATE2-AUDIT-TIMING). Fix applied: added `git fetch` + `git pull --rebase origin main` to `scripts/ceo-ops-once.sh` before the backstop section. KB lesson written. Commits: see below.

## Root cause
- **Class:** GAP-MASTER-WORKER-SYNC
- `ceo-ops-once.sh` runs on the master cron node without pulling latest — reads stale runtime state files
- Worker commits (including release-cycle advancement from release-m → release-c) were not in master at 04:00 Apr 22
- The Gate 2 artifact filed is harmless (targets a superseded release) but spawns a CEO root-cause review item

## Fixes applied
1. `scripts/ceo-ops-once.sh`: added `git fetch origin --prune` + `git pull --rebase origin main` before backstop section (GAP-MASTER-WORKER-SYNC)
2. `knowledgebase/lessons/20260422-gate2-backstop-stale-master-release-id.md`: KB lesson documenting the class and fix

## Blocker class removed
- GAP-MASTER-WORKER-SYNC: `ceo-ops-once.sh` reading stale release_id from unsynced master node

## Next actions
- None required; fix is in place and KB lesson recorded

## Blockers
- None

## ROI estimate
- ROI: 8
- Rationale: Eliminates a recurring Gate 2 noise class (stale-release backstop triggers) that creates unnecessary CEO inbox items and false Gate 2 artifacts each release cycle.
