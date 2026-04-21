# Lesson: Gate 2 clean-audit backstop delayed by audit timing gap

- Date: 2026-04-21
- Site: forseti
- Release: 20260419-forseti-release-c
- Root cause class: GAP-GATE2-AUDIT-TIMING

## What happened
Gate 2 APPROVE for `20260419-forseti-release-c` was filed by the CEO backstop
(`ceo-ops-once.sh`) at 2026-04-21T00:00:30 — approximately 17h after the release
cycle activated. The normal path (`site-audit-run.sh` → `gate2-clean-audit-backstop.py`)
never fired for this release because:

1. The last site audit ran April 19 at 10:04 AM — before the release cycle started
   (release activated April 20 at 06:49 AM).
2. The site-audit cron (`install-cron-site-audit-forseti.sh`) was **not installed**,
   so no hourly audit ran after the release started.
3. `release-cycle-start.sh` queues QA preflight tasks but does not trigger a fresh
   site audit on activation.

## Why the primary path didn't help
When the April 19 audit ran, `20260419-forseti-release-c` was not the active release
yet. The backstop checks `tmp/release-cycle-active/<team>.release_id` at audit time,
so it had a different (or no) release to target. No subsequent audit ran to re-trigger it.

## Fix applied (2026-04-21)
1. **Installed the hourly audit cron** via `bash scripts/install-cron-site-audit-forseti.sh`.
   The cron now runs `site-audit-run.sh forseti-life` at :15 every hour.
2. **Added site audit trigger to `release-cycle-start.sh`** (immediately before the
   final echo) so a fresh audit runs the moment a new release cycle activates.
   This closes the timing window regardless of cron schedule.

## Prevention going forward
- The site audit cron is now installed: `15 * * * * scripts/site-audit-run.sh forseti-life`
- Release cycle activation now triggers an immediate audit
- The 2-hour CEO backstop remains as a final safety net

## Files changed
- `scripts/release-cycle-start.sh` — added `bash scripts/site-audit-run.sh ${team_id}` at activation
- Crontab updated — `install-cron-site-audit-forseti.sh` installed the hourly entry
