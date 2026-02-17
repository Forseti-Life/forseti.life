# Example Configuration Files

This directory contains example configuration files demonstrating the JSON schema structures used in the Dungeon Crawler Content module.

## Files

### tavern-obstacle-objects.json
Obstacle object catalog defining reusable obstacle types (furniture, fixtures, etc.) with movement properties. These definitions are referenced by entity instances in dungeon levels.

**Schema:** `obstacle_object_catalog.schema.json`

**Usage:** Loaded by `HexMapController` to provide object definitions for obstacle entities.

**Key Properties:**
- `movable`: Whether the object can be pushed/moved by players
- `stackable`: Whether multiple instances can occupy the same hex
- `movement.passable`: Whether entities can share the hex with the object
- `movement.blocks_movement`: Whether pathfinding treats the hex as blocked
- `movement.cost_multiplier`: Movement cost when entering the hex (1 = normal, higher = slower)

**Cost Multiplier Guide:**
- `1.0`: Normal speed (open doors, passable obstacles)
- `1.5`: Slight impediment (stool stacks, light debris)
- `2.0-3.0`: Moderate impediment (movable tables, crates)
- `999`: Effectively impassable (fixed bar counters, walls)

### tavern-entrance-dungeon.json
Complete dungeon level example featuring a tavern entrance area with bar, furniture, and transition to a dungeon room.

**Schema:** `dungeon_level.schema.json`

**Usage:** Demo dungeon level loaded by `HexMapController` when no campaign data is available.

### level-1-goblin-warrens.json
Multi-room dungeon level example with goblin-themed rooms, creatures, and environmental features.

**Schema:** `dungeon_level.schema.json`

## Schema Relationships

```
dungeon_level.schema.json
  ├── rooms[] (room.schema.json)
  │   ├── creatures[] (references creature definitions)
  │   ├── items[] (references item definitions)
  │   └── obstacles[] (references object catalog)
  └── entities[] (entity_instance.schema.json)
      └── entity_ref.content_id → obstacle_object_catalog.objects[].object_id
```

## Validation

All JSON files in this directory should validate against their respective schemas. The `$schema` property at the top of each file indicates which schema to use for validation.

## Adding New Examples

1. Ensure your JSON validates against the appropriate schema
2. Include the `$schema` reference at the top of the file
3. Add descriptive documentation in this README
4. Follow existing naming conventions (lowercase with hyphens)

## See Also

- `/config/schemas/README.md` - Complete schema documentation
- `API_DOCUMENTATION.md` - Runtime API documentation
- `ARCHITECTURE.md` - System architecture overview
