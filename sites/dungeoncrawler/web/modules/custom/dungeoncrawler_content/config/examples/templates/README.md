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

Each table directory includes default JSON files with template rows to provide a starter library for generation, testing, and encounter design.

### Comprehensive PF2E Item Library

**As of February 18, 2026**, this library includes a **complete extraction of all Pathfinder 2E equipment items** from 6 official source books:

**Source Coverage:**
- Core Rulebook (4th Printing): 279 items
- Guns & Gears: 57 items
- Secrets of Magic: 55 items
- Advanced Player's Guide: 35 items
- Gods & Magic: 3 items
- Gamemastery Guide: 2 items

**Total: 431 items**

**Item Breakdown:**
- Weapons: 93
- Armor: 51
- Magic Items: 44
- Adventuring Gear: 243

**Key Features:**
- Full source book attribution for every item
- Price information extracted from source text (when available)
- Item type classification (weapon/armor/magic_item/adventuring_gear)
- Reference traceability with line numbers and extraction methods
- Ready for character creation, shopping, and loot generation

**Files:**
- `dungeoncrawler_content_registry/default_registry_examples.json` - 431 item definitions

### Comprehensive PF2E Creature Library

**As of February 19, 2026**, this library includes **creatures from 3 Pathfinder 2E Bestiary books**:

**Source Coverage:**
- Bestiary 1: 20 creatures
- Bestiary 2: 32 creatures
- Bestiary 3: 21 creatures

**Total: 73 creatures**

**Creature Breakdown:**
- Generic creatures: 61
- Fiends (demons/devils): 4
- Undead: 2
- Plants: 2
- Oozes: 2
- Celestials: 1
- Dragons: 1

**Key Features:**
- Creature type classification
- Level ranges from 0 to 18
- Source book attribution
- Trait information when available
- Ready for encounter generation

**Files:**
- `dungeoncrawler_content_registry/default_registry_examples.json` - 73 creature definitions (appended to items)

**Note**: This is an initial creature extraction. The Bestiary books contain hundreds more creatures that can be added through refined extraction methods.

### Production Deployment

Templates are automatically imported to production via **update hook 10012** in `dungeoncrawler_content.install`. When code is deployed:

1. GitHub Actions pushes new code to production
2. `drush updatedb` runs automatically
3. Update hook 10012 imports all templates
4. Production database has complete PF2E item library + creature library

The import is idempotent - running multiple times is safe.

### Other Template Tables

All other template tables include **10 baseline rows** each:
- Campaigns, Characters, Dungeons, Rooms, Encounters
- Loot Tables, Room States, Logs, Item Instances

## Pathfinder Reference Alignment

These templates are Pathfinder 2e-inspired starter references for internal library seeding, aligned to content categories and encounter pacing in:

- Core Rulebook (encounter budgeting concepts and action economy patterns)
- Bestiary volumes (creature role inspiration and level progression)
- Gamemastery Guide (hazards, dungeon pacing, and encounter composition)
- Secrets of Magic / Guns & Gears (theme flavor for arcane and construct encounters)

The files intentionally store concise, system-friendly metadata (IDs, tags, structured payloads) rather than verbatim rules text.
