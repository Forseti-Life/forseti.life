- Status: done
- Summary: Verified that the three promoted Dungeoncrawler epics are already `Status: ready` — `dc-b2-bestiary2`, `dc-gng-guns-gears`, and `dc-som-secrets-of-magic` all show `- Status: ready` in their `feature.md` files (confirmed 2026-04-14). `dc-b3-bestiary3` remains `- Status: deferred` with gate `Deferred until: dc-b2-bestiary2 ships`. No file changes were required. The updated pull order for future activation is documented below.

## Confirmed backlog order (DC — post goblin/halfling + GMG/UI batches)

Priority tier (P1 — activate first):
- `dc-ui-encounter-party-rail`, `dc-ui-hexmap-thin-client`, `dc-ui-map-first-player-shell`, `dc-ui-scene-layer-contract`, `dc-ui-token-readability` (UI cluster — highest priority)

After UI cluster:
1. `dc-b2-bestiary2` — Bestiary 2 content (gates dc-b3-bestiary3)
2. `dc-gng-guns-gears` — Guns & Gears content
3. `dc-som-secrets-of-magic` — Secrets of Magic content

Still gated (deferred):
- `dc-b3-bestiary3` — deferred until dc-b2-bestiary2 ships

Current active release `20260412-dungeoncrawler-release-k` is empty. Next release (release-l) scope activation should pull from goblin/halfling cluster (4 goblin + 5 halfling ready), then GMG subsystems/running-guide/npc-gallery in batches of ≤5.

## Next actions
- Release-l scope activation: activate ≤5 goblin/halfling feats (do NOT activate all 9 at once — batch-activation cap trap)
- After goblin/halfling cluster ships: activate P1 UI features (up to 5 at a time)
- After UI cluster: pull `dc-b2-bestiary2`, then `dc-gng-guns-gears`, then `dc-som-secrets-of-magic`
- Keep `dc-b3-bestiary3` deferred until Bestiary 2 is confirmed shipped

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 5
- Rationale: Backlog order confirmation with no rework or confusion prevents sequencing mistakes in future release activations. Low effort, prevents future PM errors.
