- Status: done
- Summary: Added the `qa-site-audit-forseti` cron entry with `ALLOW_PROD_QA=1` to `scripts/install-crons.sh`. This is the permanent fix for GAP-AUDIT-CRON-ALLOW-PROD — the root cause of the >33-hour Gate 2 blockage for forseti release-m. CEO had applied a live hotfix to the crontab; this change ensures the correct entry (with `ALLOW_PROD_QA=1`) is restored automatically after any cron wipe. All four acceptance criteria verified. Commit: `1b9a169ea`.

## Next actions
- None. CEO to push as release operator when ready.

## Blockers
- None.

## Verification
```bash
$ bash -n scripts/install-crons.sh
# syntax OK

$ bash scripts/install-crons.sh
# skip (exists): qa-site-audit-forseti  (entry already present from CEO hotfix)

$ crontab -l | grep qa-site-audit-forseti
15 * * * * ALLOW_PROD_QA=1 /home/keithaumiller/forseti.life/copilot-hq/scripts/site-audit-run.sh forseti-life >> .../qa-site-audit-forseti-cron.log 2>&1 # copilot-sessions-hq:qa-site-audit-forseti

$ ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life
WROTE: findings-summary.md
WROTE: findings-summary.json
OK: forseti-life audit completed at 20260422-083145
# Exit: 0
```

## ROI estimate
- ROI: 8
- Rationale: This was the primary Gate 2 materialization blocker for >33 hours. Permanent fix prevents recurrence on any cron wipe; matches CEO-assigned ROI.
