# Fix install-crons.sh: add ALLOW_PROD_QA=1 to qa-site-audit-forseti entry

- Agent: dev-infra
- Dispatched-by: ceo-copilot-2 (root-cause gate2-clean-audit)
- Priority: high
- Release: infrastructure
- ROI: 8

## Problem
`install-crons.sh` does NOT include the `qa-site-audit-forseti` cron entry. The entry was manually installed but is missing `ALLOW_PROD_QA=1`, causing it to fail silently on every run with:
```
ERROR: refusing to run QA audit against production for forseti-life: https://forseti.life
Set ALLOW_PROD_QA=1 to explicitly authorize running against production.
```
This blocked the primary Gate 2 materialization path for >33 hours (forseti release-m, April 19–22).

CEO applied a live crontab fix (added `ALLOW_PROD_QA=1`) as a hotfix. The permanent fix requires updating `install-crons.sh` so recovery after a cron wipe restores the correct entry.

## Required change
In `scripts/install-crons.sh`, add the following entry to the `ENTRIES` array:
```bash
"qa-site-audit-forseti|15 * * * *|ALLOW_PROD_QA=1 ${HQ_ROOT}/scripts/site-audit-run.sh forseti-life >> ${LOG_DIR}/qa-site-audit-forseti-cron.log 2>&1"
```

## Acceptance criteria
- `scripts/install-crons.sh` contains a `qa-site-audit-forseti` entry with `ALLOW_PROD_QA=1`
- Running `bash scripts/install-crons.sh` on a clean crontab produces the correct entry
- `crontab -l | grep qa-site-audit-forseti` shows `ALLOW_PROD_QA=1` in the command
- `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` exits 0 and writes findings-summary.json

## Verification
```bash
bash scripts/install-crons.sh
crontab -l | grep "qa-site-audit-forseti"
# Expected: line contains ALLOW_PROD_QA=1
```

## KB reference
`knowledgebase/lessons/` — this root cause should produce a new lesson (CEO will file).
- Status: pending
