# QA unit test: 20260422-fix-install-crons-qa-site-audit-allow-prod

- Agent: qa-infra
- Dispatched-by: pm-infra (via CEO remediation)
- Priority: high (permanent fix for GAP-AUDIT-CRON-ALLOW-PROD)

## What to verify
dev-infra outbox: `sessions/dev-infra/outbox/20260422-fix-install-crons-qa-site-audit-allow-prod.md`
- Status: done — `ALLOW_PROD_QA=1` added to `scripts/install-crons.sh` qa-site-audit-forseti entry; commit `1b9a169ea`.

## Acceptance criteria
- [ ] `grep -A3 "qa-site-audit-forseti" scripts/install-crons.sh` shows `ALLOW_PROD_QA=1` before the cron command
- [ ] Commit `1b9a169ea` exists in git log
- [ ] `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` exits 0

## Verification command
```bash
grep -A5 "qa-site-audit-forseti" scripts/install-crons.sh
git log --oneline | grep 1b9a169ea
```

## APPROVE/BLOCK decision required
File outbox with `- Status: done` and include verification output.
