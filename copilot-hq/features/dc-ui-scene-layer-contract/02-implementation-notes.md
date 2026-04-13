# Implementation Notes: dc-ui-scene-layer-contract

## Likely touch points

- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`
- `RenderSystem`
- `SpriteService`
- Scene/entity injection performed by `HexMapController`

## Target stack

1. Background art
2. Terrain/base hexes
3. Grid/measurement
4. Static props
5. Interactive props/hazards
6. Token shadows
7. Tokens/creatures
8. Token badges
9. Templates/overlays
10. Fog/lighting/weather FX
11. Interaction
12. Screen-space HUD

## Design notes

- Keep the layer contract documented close to the runtime code so later feature work does not reintroduce ad hoc z-ordering
- Preserve current container names where practical, but prefer a clearer ownership model over legacy naming
