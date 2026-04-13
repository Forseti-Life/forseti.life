# Implementation Notes: dc-ui-token-readability

## Likely touch points

- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`
- `RenderSystem`
- Entity render metadata supplied by `HexMapController`

## Candidate markers

- Team ring / fill
- HP bar
- Current-turn glow
- Selected-token outline
- Condition badges
- Interaction / quest marker

## Design notes

- Use iconography and color intentionally; avoid text-heavy markers on the board
- Token readability should degrade gracefully at low zoom
- Badge rendering should be decoupled from the detailed entity inspector
