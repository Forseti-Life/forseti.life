# Implementation Notes: dc-ui-map-first-player-shell

## Likely touch points

- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/templates/hexmap-demo.html.twig`
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`
- Existing map shell CSS/library assets in `dungeoncrawler_content`

## Design notes

- Preserve current gameplay APIs and DOM hooks where possible; this is a shell refactor, not a rules rewrite
- Separate `player-visible shell` concerns from `debug/admin diagnostics` concerns so both can coexist cleanly
- Keep the existing board/header/action/chat systems functional during refactor; move or gate panels instead of deleting capabilities
- Use deterministic CSS/layout state for responsiveness; do not couple shell layout to combat/session logic
