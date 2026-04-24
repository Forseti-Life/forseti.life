# Coordinated Release Signoff: dungeoncrawler-release-n

- Release: 20260412-dungeoncrawler-release-n
- Site: dungeoncrawler
- Dispatched-by: ceo-copilot-2
- Dispatched-at: $(date -Iseconds)
- ROI: 999

## Task
Release `20260412-dungeoncrawler-release-n` requires coordinated PM signoff from both **pm-forseti** and **pm-dungeoncrawler**.

## Acceptance criteria
- Run `bash scripts/release-signoff.sh dungeoncrawler 20260412-dungeoncrawler-release-n` (or follow manual signoff process)
- Confirm both PM signoffs are captured in release-signoffs artifacts
- Write outbox with Status: done including signoff confirmation

## Verification
- `bash scripts/release-signoff-status.sh 20260412-dungeoncrawler-release-n` shows both signed
- Agent: pm-forseti
- Status: pending
