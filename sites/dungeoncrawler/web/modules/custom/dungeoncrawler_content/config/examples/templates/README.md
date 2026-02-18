# Template Import Examples

This directory stores file-based template imports for `/dungeoncrawler/objects`.

## Structure

Organize examples by **destination table**:

**Current table directories:**
- `dungeoncrawler_content_campaigns/`
- `dungeoncrawler_content_characters/`
- `dungeoncrawler_content_rooms/`
- `dungeoncrawler_content_dungeons/`
- `dungeoncrawler_content_encounter_instances/`
- `dungeoncrawler_content_room_states/`
- `dungeoncrawler_content_item_instances/`
- `dungeoncrawler_content_log/`
- `dungeoncrawler_content_registry/`
- `dungeoncrawler_content_loot_tables/`
- `dungeoncrawler_content_encounter_templates/`

Each JSON file may contain:

1. A single object row
2. An array of row objects
3. An object with a `rows` array

## Import Workflow

Use the **Import templates** button on `/dungeoncrawler/objects`.

Import loads and upserts rows from this directory into matching template tables.

Rows are merged using each table's unique keys (or primary key fallback).
