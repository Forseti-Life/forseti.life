- Status: done
- Summary: Implemented full cron resilience self-healing for GAP-CRON-RESILIENCE-01. Created `scripts/hq-health-heartbeat.sh` (checks orchestrator-loop and agent-exec-loop via verify subcommand, attempts restart on failure, logs to `/tmp/hq-health-heartbeat.log` and `/tmp/hq-health-alert.log`). Created `scripts/install-crons.sh` — idempotent installer for all 8 required HQ cron entries using comment-tag keyed deduplication; confirmed: first run added 5 entries, second run skipped all 8 with zero duplicates. Created `runbooks/cron-recovery.md` covering detection, restore, and verify steps. Deduplicated stale pre-migration cron entries (old tags `copilot-sessions-hq:orchestrator` and `:agent-exec` that had been added without suffix before this item). Heartbeat cron entry is live. Committed as `bbed5621`.

## Next actions
- No further action required on this item.
- `20260405-hq-script-path-migration` — remaining stale `/home/keithaumiller` path audit

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 16
- Rationale: GAP-CRON-RESILIENCE-01 caused 20 SLA breaches and 3 days of silent org downtime. The heartbeat reduces recovery from hours (manual discovery) to 2 minutes (auto-restart on next tick); the idempotent installer reduces post-migration restoration from an error-prone manual process to a single command.

---

## Verification evidence

```
$ bash -n scripts/hq-health-heartbeat.sh     # → heartbeat: syntax ok
$ bash -n scripts/install-crons.sh           # → install-crons: syntax ok

$ bash scripts/hq-health-heartbeat.sh
[2026-04-05T21:44:32+00:00] ok: orchestrator-loop
[2026-04-05T21:44:32+00:00] ok: agent-exec-loop
[2026-04-05T21:44:32+00:00] ok: publisher (pid 1360995)
[2026-04-05T21:44:32+00:00] heartbeat: all loops healthy
Exit code: 0

$ bash scripts/install-crons.sh              # first run
added: orchestrator-reboot, orchestrator-watchdog, agent-exec-reboot,
       agent-exec-watchdog, hq-health-heartbeat (5 added, 3 already present)

$ bash scripts/install-crons.sh              # second run (idempotency)
install-crons: all entries already present (8 entries). No changes made.

$ crontab -l | grep hq-health-heartbeat
*/2 * * * * .../scripts/hq-health-heartbeat.sh >> /tmp/hq-health-heartbeat.log 2>&1 # copilot-sessions-hq:hq-health-heartbeat
```

## Implementation summary

### scripts/hq-health-heartbeat.sh
- `check_and_restart_loop` helper: calls `script verify`; on failure, calls `script start`, rechecks, alerts on persistent failure
- `check_publisher`: reads `.publish-forseti-agent-tracker-loop.pid`, reports stale PID without restart attempt (publisher is on-demand, not a persistent loop)
- Alert log: `/tmp/hq-health-alert.log` — CEO can `cat` to see any WARN-level events
- Heartbeat log: `/tmp/hq-health-heartbeat.log` — full timestamped record

### scripts/install-crons.sh
- 8 entries with unique `# copilot-sessions-hq:<tag>` markers
- Idempotency: `grep -qF "$marker"` against current crontab before adding
- Re-running writes no duplicates; only missing entries are added

### runbooks/cron-recovery.md
- Detection symptoms: stale exec log, verify fail, missing crontab entries, alert log
- Restore: `bash scripts/install-crons.sh` (single command)
- Verify: `bash scripts/hq-health-heartbeat.sh` → exit 0
- Post-migration checklist
- Root cause documentation (GAP-CRON-RESILIENCE-01)

### Crontab cleanup
Removed legacy pre-migration entries with old tag names (`copilot-sessions-hq:orchestrator` and `copilot-sessions-hq:agent-exec` without `-reboot`/`-watchdog` suffixes) which caused duplicate @reboot and */5 entries after the first install run.

## Commit
- `bbed5621` — feat(dev-infra): cron resilience self-healing (GAP-CRON-RESILIENCE-01)
