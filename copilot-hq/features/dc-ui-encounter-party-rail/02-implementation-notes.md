# Implementation Notes: dc-ui-encounter-party-rail

## Likely touch points

- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`
- `UIManager.updateInitiativeTracker()`
- Turn/round/combat state wiring already driven by ECS systems

## Design notes

- Reuse the existing turn-order source of truth; enrich the rendered card model instead of inventing a second combat state system
- Distinguish `player-facing rail` from any future GM-only controls
- Keep click-to-select behavior aligned with existing entity inspector behavior
- Prefer a compact card model that can collapse cleanly on mobile/tablet
