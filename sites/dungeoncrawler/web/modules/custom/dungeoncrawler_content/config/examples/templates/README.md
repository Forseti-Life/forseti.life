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

## Library Baseline (2026-02-18)

Each table directory now includes a single default JSON file with **10 template rows** to provide a larger starter library for generation, testing, and encounter design.

Expanded tables:
- `dungeoncrawler_content_campaigns`
- `dungeoncrawler_content_characters`
- `dungeoncrawler_content_dungeons`
- `dungeoncrawler_content_encounter_instances`
- `dungeoncrawler_content_encounter_templates`
- `dungeoncrawler_content_item_instances`
- `dungeoncrawler_content_log`
- `dungeoncrawler_content_loot_tables`
- `dungeoncrawler_content_registry`
- `dungeoncrawler_content_rooms`
- `dungeoncrawler_content_room_states`

## Pathfinder Reference Alignment

These templates are Pathfinder 2e-inspired starter references for internal library seeding, aligned to content categories and encounter pacing in:

- Core Rulebook (encounter budgeting concepts and action economy patterns)
- Bestiary volumes (creature role inspiration and level progression)
- Gamemastery Guide (hazards, dungeon pacing, and encounter composition)
- Secrets of Magic / Guns & Gears (theme flavor for arcane and construct encounters)

The files intentionally store concise, system-friendly metadata (IDs, tags, structured payloads) rather than verbatim rules text.
