# Implementation Notes: dc-ui-sidebar-drawers

## Likely touch points

- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/templates/hexmap-demo.html.twig`
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/js/hexmap.js`

## Design notes

- Preserve current sidebar data flows; reorganize shell behavior first
- Distinguish the main right drawer from chat docking so both remain useful
- Inventory sync-on-open behavior should survive the drawer conversion
- Use persisted UI state cautiously so mobile/desktop layouts do not fight each other
