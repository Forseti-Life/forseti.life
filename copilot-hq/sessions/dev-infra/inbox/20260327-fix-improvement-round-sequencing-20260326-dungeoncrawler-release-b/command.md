# Fix: Improvement round sequencing — queue only after release ships

- Source: pm-dungeoncrawler post-release gap review (20260326-dungeoncrawler-release-b)
- Gap ID: GAP-26B-02
- Site: dungeoncrawler

## Problem
The improvement-round inbox item for `20260326-dungeoncrawler-release-b` was queued while the release was still being groomed (hadn't shipped). Post-release reviews have no data until both PM signoffs are confirmed.

## Required fix
The script that queues improvement-round inbox items must gate on confirmed shipment:
```bash
bash scripts/release-signoff-status.sh <release-id>
# exit code 0 = both signoffs present = safe to queue improvement round
```
If exit non-zero → do not queue. Try again next cycle.

## Acceptance criteria
- Improvement-round inbox items are only created after `scripts/release-signoff-status.sh <release-id>` exits 0.
- No improvement-round item is ever queued for a release-id that has not shipped.

## Verification
Simulate by checking `release-signoff-status.sh` exit code before queuing in the automation loop script.

## ROI: 5
Prevents PM cycles spent triaging premature items with no actionable data.
