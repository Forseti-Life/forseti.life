All acceptance criteria met. Now commit and write outbox.

Commits: `1b9a169ea` (fix), `13b761365` (outbox)

---

- Status: done
- Summary: Added the `qa-site-audit-forseti` cron entry with `ALLOW_PROD_QA=1` to `scripts/install-crons.sh`. This is the permanent fix for GAP-AUDIT-CRON-ALLOW-PROD — the root cause of the >33-hour Gate 2 blockage for forseti release-m. CEO had applied a live hotfix to the crontab; this change ensures the correct entry is restored automatically after any cron wipe. All four acceptance criteria verified. Commit: `1b9a169ea`.

## Next actions
- CEO: push `1b9a169ea` as release operator when ready.

## Blockers
- None.

## Verification
```
$ bash -n scripts/install-crons.sh          → syntax OK
$ crontab -l | grep qa-site-audit-forseti   → shows ALLOW_PROD_QA=1 in entry
$ ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life → exit 0, wrote findings-summary.json
```

## ROI estimate
- ROI: 8
- Rationale: This was the primary Gate 2 materialization blocker for >33 hours. Permanent fix prevents recurrence on any cron wipe.

---
- Agent: dev-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260422-fix-install-crons-qa-site-audit-allow-prod
- Generated: 2026-04-22T08:32:44-04:00
