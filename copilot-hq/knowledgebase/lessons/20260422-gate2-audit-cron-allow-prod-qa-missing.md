# Lesson: Gate 2 audit cron missing ALLOW_PROD_QA=1 blocks primary materialization path

- Date: 2026-04-22
- Affected team: forseti
- Release: 20260412-forseti-release-m
- Discovery: CEO root-cause-gate2-clean-audit inbox item

## What happened
The `site-audit-run.sh` cron entry (`qa-site-audit-forseti`) was missing `ALLOW_PROD_QA=1`. Every hourly run failed silently with an authorization error. No fresh audit ran between April 16 (last manual run) and April 22 04:00 (when `ceo-ops-once.sh` backstop ran using stale April 16 audit data).

Gate 2 APPROVE for forseti-release-m was delayed ~33 hours as a result (release activated April 19, APPROVE filed April 22 04:00 by CEO backstop).

## Root cause
`install-crons.sh` does not include the `qa-site-audit-forseti` entry. The entry was manually installed without `ALLOW_PROD_QA=1`. After cron wipe or environment migration, `install-crons.sh` would not restore this entry.

## Fix applied
1. Live crontab: added `ALLOW_PROD_QA=1` to `qa-site-audit-forseti` cron entry (CEO hotfix)
2. `scripts/install-crons.sh`: delegated to dev-infra (`20260422-fix-install-crons-qa-site-audit-allow-prod`)

## Prevention
- `install-crons.sh` must be the single source of truth for all HQ cron entries
- Any manually added cron entry not in `install-crons.sh` is a latent recovery gap
- Periodic audit: `crontab -l | grep copilot-sessions-hq` should match the expected set from `install-crons.sh`
- After any cron wipe or environment migration, run `bash scripts/install-crons.sh` + verify with `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life`
