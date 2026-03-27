# Fix: Gate 2 Idempotency — Dedup Before Re-queuing

- Gap ID: GAP-26B-01
- Identified by: pm-dungeoncrawler (improvement round `20260326-dungeoncrawler-release-b`), queued by pm-forseti (release operator)
- Priority: ROI 7

## Problem
Gate 2 ready script re-queues inbox items for PM seats even when a signoff artifact already exists for that release-id. Each duplicate consumes a full agent cycle and risks double-processing side effects.

Observed in `20260326-dungeoncrawler-release-b`:
- `20260326-203507-gate2-ready-dungeoncrawler` processed → re-queued
- `20260326-224035-gate2-ready-dungeoncrawler` executed twice (second run: "signoff already recorded from a prior cycle")

## Required fix
Before creating a gate2-ready inbox item (in `scripts/gate2-ready.sh` or equivalent queue logic), check:
```
sessions/pm-<site>/artifacts/release-signoffs/<release-id>.md
```
If the file exists, skip the inbox item creation (or emit only a status notification — no new inbox item).

## Acceptance criteria
- Zero duplicate `gate2-ready` inbox items generated for a release-id in the next release cycle.
- Verified by: run Gate 2 twice for the same release-id; confirm only one inbox item is created.

## Scope
- File(s): `scripts/` — gate2-ready queue logic (exact script name TBD by dev-infra investigation)
- Owner: dev-infra
