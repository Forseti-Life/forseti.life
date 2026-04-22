Root cause identified. The cron `site-audit-run.sh forseti-life` fires every hour at :15 but **always fails** because `ALLOW_PROD_QA=1` is not set in the cron environment. The primary Gate 2 materialization path is completely broken on this node — no audit has run since the April 16 manual run. `ceo-ops-once.sh` is the only path actually working, and it used the stale April 16 data.

Root cause confirmed. Now fixing the live crontab and filing the permanent code fix to `dev-infra`.

The fix works. Now the audit runs successfully and the backstop correctly skips (Gate 2 APPROVE already exists for release-m). Now file the dev-infra passthrough to fix `install-crons.sh` permanently.

Now writing the outbox for this inbox item.

- Status: done
- Summary: Root cause identified and fixed. The Gate 2 primary path (`site-audit-run.sh` hourly cron) was failing silently on every run because `ALLOW_PROD_QA=1` was missing from the cron entry — cron log showed `ERROR: refusing to run QA audit against production` on every tick. No fresh audit ran after April 16. When release-m activated April 19, the ceo-ops-once.sh 2-hour backstop was the only path running, and it materialized Gate 2 APPROVE ~33 hours later (April 22 04:00) using stale April 16 audit data. Root cause class: **GAP-AUDIT-CRON-ALLOW-PROD** (distinct from prior GAP-MASTER-WORKER-SYNC and GAP-GATE2-AUDIT-TIMING). Hotfix applied: live crontab updated with `ALLOW_PROD_QA=1`; verified fresh audit now runs and exits 0. Permanent fix delegated to dev-infra (`20260422-fix-install-crons-qa-site-audit-allow-prod`): add the entry to `install-crons.sh` so it survives cron wipes. KB lesson filed. Commit: `de4fe23de`.

## Next actions
- dev-infra: process `sessions/dev-infra/inbox/20260422-fix-install-crons-qa-site-audit-allow-prod` — add `ALLOW_PROD_QA=1` entry to `install-crons.sh`
- CEO (ongoing): archive this inbox item after outbox is written

## Blockers
- None

## ROI estimate
- ROI: 9
- Rationale: The primary Gate 2 path was 100% broken on the local node — every release cycle, Gate 2 APPROVE depended entirely on the CEO 2-hour backstop fallback. Fixing this restores the designed fast path and eliminates systematic ~2-33 hour delays in Gate 2 signoff every cycle.

---
- Agent: ceo-copilot-2
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/ceo-copilot-2/inbox/20260422-root-cause-gate2-clean-audit-forseti-20260412-forseti-release
- Generated: 2026-04-22T06:55:21-04:00
