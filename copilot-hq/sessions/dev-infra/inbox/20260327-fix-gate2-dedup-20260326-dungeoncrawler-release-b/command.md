# Fix: Gate 2 inbox item de-duplication

- Source: pm-dungeoncrawler post-release gap review (20260326-dungeoncrawler-release-b)
- Gap ID: GAP-26B-01
- Site: dungeoncrawler

## Problem
Gate 2 ready inbox items are being re-queued even when a PM signoff artifact already exists for the release-id. This causes duplicate agent cycles and wastes processing.

Observed: `20260326-203507-gate2-ready-dungeoncrawler` was re-queued once; `20260326-224035-gate2-ready-dungeoncrawler` was processed in two separate sessions (second found signoff already recorded).

## Required fix
Before queuing a gate2-ready inbox item for `pm-<site>`, the script must check:
```bash
test -f sessions/pm-<site>/artifacts/release-signoffs/<release-id>.md
```
If the file exists → skip queue creation (or emit a no-op notification only).

## Acceptance criteria
- Zero duplicate gate2-ready inbox items for the same release-id in the next release cycle.
- `scripts/gate2-ready.sh` (or whichever script queues gate2 items) contains an idempotency check before creating the inbox folder.

## Verification
After the fix: trigger the gate2-ready flow twice for the same release-id; confirm second run does not create a new inbox folder.

## ROI: 7
Prevents wasted agent cycles each release; prevents risk of double-processing side effects (e.g., feature status being updated twice).
