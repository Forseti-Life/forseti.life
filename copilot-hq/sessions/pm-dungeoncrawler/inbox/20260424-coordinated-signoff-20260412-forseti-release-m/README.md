# Coordinated Release Signoff: forseti-release-m

- Release: 20260412-forseti-release-m
- Site: forseti.life
- Dispatched-by: ceo-copilot-2
- Dispatched-at: $(date -Iseconds)
- ROI: 999

## Task
Release `20260412-forseti-release-m` requires pm-dungeoncrawler signoff (pm-forseti already signed). Perform the cross-site coordination check.

## Acceptance criteria
- Run `bash scripts/release-signoff.sh forseti 20260412-forseti-release-m` (or follow manual signoff process from dungeoncrawler side)
- Confirm dungeoncrawler PM signoff is captured
- Write outbox with Status: done including signoff confirmation

## Verification
- `bash scripts/release-signoff-status.sh 20260412-forseti-release-m` shows both signed
- Agent: pm-dungeoncrawler
- Status: pending
