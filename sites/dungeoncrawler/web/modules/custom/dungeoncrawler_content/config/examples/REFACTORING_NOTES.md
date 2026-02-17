# Example Configuration Refactoring Notes

## Overview

This document describes the refactoring patterns applied to `tavern-entrance-dungeon.json` to improve consistency, reduce file size, and align with best practices.

## Refactoring Principles

### 1. Remove Empty Collections

**Before:**
```json
{
  "environmental_effects": [],
  "creatures": [],
  "items": [],
  "traps": [],
  "hazards": [],
  "obstacles": [],
  "interactables": []
}
```

**After:**
```json
{
  // Empty arrays omitted entirely
}
```

**Rationale:** Empty arrays provide no value and increase file size. The schema should define defaults, and parsers should handle missing fields gracefully.

### 2. Remove Explicit Null Values

**Before:**
```json
{
  "spawn_type": null,
  "explored_by_party": null,
  "explored_at": null,
  "hit_points": null,
  "hazardous_terrain": null
}
```

**After:**
```json
{
  // Null fields omitted
}
```

**Rationale:** Explicit `null` values are redundant. JSON parsers treat missing fields as null/undefined by default.

### 3. Remove Redundant State Booleans

**Before:**
```json
{
  "state": {
    "active": true,
    "destroyed": false,
    "disabled": false,
    "hidden": false,
    "collected": false
  }
}
```

**After:**
```json
{
  "state": {
    "active": true
  }
}
```

**Rationale:** Boolean flags that are always `false` in initial state should be omitted. Only include flags when they differ from defaults.

### 4. Remove Version Strings from Entity References

**Before:**
```json
{
  "entity_ref": {
    "content_type": "obstacle",
    "content_id": "wooden_tavern_door",
    "version": "1.0.0"
  }
}
```

**After:**
```json
{
  "entity_ref": {
    "content_type": "obstacle",
    "content_id": "wooden_tavern_door"
  }
}
```

**Rationale:** Version management should be handled at the schema or catalog level, not repeated in every instance.

### 5. Remove Duplicate Timestamps

**Before:**
```json
{
  "entities": [
    {
      "entity_instance_id": "...",
      "created_at": "2026-02-13T00:00:00Z",
      "updated_at": "2026-02-13T00:00:00Z"
    }
  ]
}
```

**After:**
```json
{
  "created_at": "2026-02-13T00:00:00Z",
  "entities": [
    {
      "entity_instance_id": "..."
      // Timestamps removed from instances
    }
  ]
}
```

**Rationale:** When all entities share the same timestamp as the root level, duplicating them is redundant.

### 6. Align Connection Structure

**Before:**
```json
{
  "connections": [
    {
      "from_room": "room-id-1",
      "to_room": "room-id-2",
      "from_hex": {"q": 4, "r": 0},
      "to_hex": {"q": 5, "r": 0}
    }
  ]
}
```

**After:**
```json
{
  "connections": [
    {
      "from": {"q": 4, "r": 0},
      "to": {"q": 5, "r": 0},
      "type": "door",
      "is_known": true,
      "description": "Heavy wooden door between tavern staging area and dungeon entrance"
    }
  ]
}
```

**Rationale:** Match the structure used in `level-1-goblin-warrens.json` for consistency. Room relationships can be inferred from hex coordinates.

### 7. Simplify Nested Structures

**Before:**
```json
{
  "hex_map": {
    "schema_version": "1.0.0",
    "depth_tier": "shallow_halls",
    "depth_level": 1,
    "hex_grid": {
      "orientation": "flat-top",
      "hex_size_ft": 5,
      "origin": {"q": 0, "r": 0},
      "coordinate_system": "axial"
    }
  }
}
```

**After:**
```json
{
  "hex_map": {
    "hex_size_ft": 5,
    "orientation": "flat-top"
  }
}
```

**Rationale:** Remove unnecessary nesting and redundant metadata. Depth information is available at root level.

### 8. Use Appropriate Theme Values

**Before:**
```json
{
  "theme": "abandoned_mine"
}
```

**After:**
```json
{
  "theme": "custom",
  "custom_theme": "tavern_entrance"
}
```

**Rationale:** Use enum values correctly. When the content doesn't match predefined themes, use `"custom"` with a descriptive `custom_theme`.

## Results

### File Size Reduction

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| **File Size** | 21 KB | 17 KB | 19% reduction |
| **Line Count** | 875 lines | 734 lines | 141 lines removed |
| **Entity Avg Size** | ~70 lines | ~35 lines | 50% reduction |

### Benefits

1. **Smaller file size**: Faster loading and reduced storage
2. **Better readability**: Less noise, easier to understand
3. **Consistency**: Aligns with other example files
4. **Maintainability**: Fewer fields to keep in sync
5. **Schema compliance**: Better adherence to JSON best practices

## Guidelines for Future Examples

When creating new example configurations:

1. ✅ **Omit empty arrays and objects**
2. ✅ **Omit null values** (unless semantically important)
3. ✅ **Use defaults** from schema where applicable
4. ✅ **Remove redundant metadata** (timestamps, versions)
5. ✅ **Match existing patterns** from other examples
6. ✅ **Add meaningful descriptions** for clarity
7. ✅ **Validate against schema** before committing
8. ✅ **Use appropriate theme values** from schema enums

## Validation

After refactoring, always validate:

```bash
# Validate JSON syntax
python3 -m json.tool file.json > /dev/null

# Check schema compliance (if validator available)
# ajv validate -s schema.json -d file.json

# Verify application can load it
# (application-specific test)
```

## See Also

- `dungeon_level.schema.json` - Schema definition
- `level-1-goblin-warrens.json` - Reference example
- `tavern-obstacle-objects.json` - Object catalog structure
