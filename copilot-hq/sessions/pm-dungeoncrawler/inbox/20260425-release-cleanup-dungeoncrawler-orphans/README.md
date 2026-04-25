# Release cleanup: stale in_progress features for dungeoncrawler

- Agent: pm-dungeoncrawler
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-25T00:00:29Z


## Issue

Release cleanup is needed for `dungeoncrawler`. These features are still marked `in_progress` on stale releases while the active release is `20260412-dungeoncrawler-release-n`:

- `dc-cr-goblin-weapon-frenzy` on `20260412-dungeoncrawler-release-m` (dev outbox exists)
- `dc-cr-halfling-heritage-gutsy` on `20260412-dungeoncrawler-release-m` (dev outbox exists)
- `dc-cr-halfling-heritage-hillock` on `20260412-dungeoncrawler-release-m` (dev outbox exists)
- `dc-cr-vivacious-conduit` on `20260412-dungeoncrawler-release-m` (dev outbox exists)
- `dc-gam-gods-magic` on `20260412-dungeoncrawler-release-m` (dev outbox exists)
- `dc-gmg-npc-gallery` on `20260412-dungeoncrawler-release-m` (dev outbox exists)
- `dc-gmg-running-guide` on `20260412-dungeoncrawler-release-m` (dev outbox exists)
- `dc-gmg-subsystems` on `20260412-dungeoncrawler-release-m` (dev outbox exists)
- `dc-ui-encounter-party-rail` on `20260412-dungeoncrawler-release-m` (dev outbox exists)
- `dc-ui-hexmap-thin-client` on `20260412-dungeoncrawler-release-m` (dev outbox exists)
- `dc-ui-map-first-player-shell` on `20260412-dungeoncrawler-release-m` (dev outbox exists)
- `dc-ui-scene-layer-contract` on `20260412-dungeoncrawler-release-m` (dev outbox exists)

Reset stale features to `ready` / clear release, or mark them `done` if implementation already shipped.

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/ceo-release-health.sh` should no longer report orphaned features for `dungeoncrawler`
- Status: pending
