# Forseti CEO ops cadence

## Every 5 minutes (iterative cycle)
- Snapshot org health: active vs idle, queue sizes, blockers.
- Ensure blockers are escalated to the right supervisor.
- Ensure deprioritized work is explicitly documented.

Implementation:
- `scripts/ceo-ops-once.sh` (single cycle)
- `scripts/install-cron-ceo-ops.sh` installs cron `*/5` to run `ceo-ops-once.sh`

Logs:
- `inbox/responses/ceo-ops-cron.log`

## Every 2 hours
- Auto checkpoint push runs via cron (already installed): `scripts/auto-checkpoint.sh`
- Review priority rankings and adjust resource focus if ROI changes.
