# DCC-0020: Hazard Schema Review and Improvement Summary

## Overview
Comprehensive review and refactoring of `hazard.schema.json` to improve validation, documentation, and alignment with PF2e standards and other schemas in the codebase.

## Files Modified
1. `/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/schemas/hazard.schema.json`
2. `/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/schemas/README.md`

## Improvements Implemented

### 1. Strict Validation
- **Added**: `"additionalProperties": false` at root level
- **Added**: `"additionalProperties": false` in nested objects (saves, resistances, weaknesses, hexes_affected, reset object)
- **Impact**: Prevents typos and unexpected fields, improving data integrity

### 2. Enhanced Field Validation
- **Added**: `"required": ["q", "r"]` for hex coordinate objects
- **Added**: `"required": ["type", "value"]` for resistance/weakness objects
- **Added**: `"minimum": 0` constraint for `actions_per_round`
- **Impact**: Ensures coordinate and defense data is complete

### 3. Rarity System
- **Added**: `rarity` field with enum ["common", "uncommon", "rare", "unique"]
- **Default**: "common"
- **Impact**: Aligns with PF2e standards and other schemas (creature, item, trap)

### 4. Complete Save System
- **Added**: `will` save to the saves object (previously only fortitude/reflex)
- **Impact**: Full PF2e save coverage for hazards affected by mental effects

### 5. Defense Mechanics
- **Added**: `resistances` array with structured validation
  - Required fields: type, value
  - Descriptions for each property
- **Added**: `weaknesses` array with structured validation
  - Required fields: type, value
  - Descriptions for each property
- **Added**: `broken_threshold` field for HP management
- **Impact**: Complete PF2e defense system matching creature schema

### 6. State Management
- **Added**: `is_detected` boolean (default: false)
- **Added**: `is_disabled` boolean (default: false)
- **Added**: `is_destroyed` boolean (default: false)
- **Impact**: Runtime state tracking similar to trap schema

### 7. Flexible Reset Mechanics
- **Enhanced**: `reset` field now supports both formats:
  - Simple string: "The trap resets after 1d4 hours"
  - Structured object with automatic, reset_time_minutes, conditions
- **Using**: `oneOf` to allow either format
- **Impact**: Backward compatible while enabling structured reset automation

### 8. Comprehensive Documentation
- **Added**: Descriptions to ALL fields (previously many lacked descriptions)
- **Added**: Examples for name field (e.g., "Collapsing Ceiling", "Poison Gas Trap")
- **Enhanced**: More detailed descriptions explaining PF2e mechanics
- **Impact**: Better developer experience and IDE autocomplete support

### 9. README Documentation
- **Updated**: hazard.schema.json section with comprehensive details
- **Added**: Key features list
- **Added**: Simple vs complex hazard distinction
- **Added**: State tracking explanation
- **Impact**: Better documentation for developers using the schema

## Backward Compatibility

### Maintained
- All required fields unchanged: `hazard_id`, `name`, `level`, `complexity`
- All existing fields remain optional if not required
- String format for `reset` still supported via oneOf
- Default values provided for new boolean fields

### Testing
Created test fixtures to verify:
1. ✅ Minimal hazard (only required fields) - Valid
2. ✅ Simple hazard with basic fields - Valid
3. ✅ Complex hazard with all new features - Valid
4. ✅ Invalid hazard with unknown fields - Properly rejected
5. ✅ Invalid hazard with out-of-range level - Properly rejected

## Alignment with Codebase Standards

### Consistency with Other Schemas
- **Rarity system**: Matches creature.schema.json, item.schema.json
- **Resistance/weakness structure**: Matches creature.schema.json
- **State flags**: Similar to trap.schema.json (is_detected, is_disabled)
- **Coordinate structure**: Matches obstacle.schema.json hexes pattern
- **Documentation level**: Matches comprehensive style of creature.schema.json

### PF2e Compliance
- ✅ Level range: -1 to 25 (standard PF2e range)
- ✅ Saves: Fortitude, Reflex, Will (complete PF2e save trio)
- ✅ Complexity: Simple vs Complex (official PF2e hazard categories)
- ✅ Rarity: Common, Uncommon, Rare, Unique (PF2e rarity system)
- ✅ Traits: Array of strings for PF2e trait tags

## Technical Validation

### JSON Schema Compliance
- ✅ Valid JSON syntax
- ✅ Draft-07 JSON Schema compliant
- ✅ All required properties defined
- ✅ Proper use of oneOf for reset field
- ✅ Format validation (uuid for hazard_id)

### Testing Results
```
✓ JSON is valid
✓ Minimal hazard (backward compatibility) is valid
✓ Simple hazard test is valid
✓ Complex hazard test is valid
✓ Invalid hazard properly rejected: Additional properties are not allowed
```

## Benefits

### For Developers
1. Better IDE autocomplete with comprehensive descriptions
2. Catch errors earlier with strict validation
3. Clear examples of valid hazard definitions
4. Consistent patterns across all schemas

### For Data Integrity
1. Prevent typos with additionalProperties: false
2. Ensure complete coordinate data with required fields
3. Validate resistance/weakness structure
4. Maintain PF2e compliance

### For Game System
1. Full save system support (Fortitude, Reflex, Will)
2. Complete defense mechanics (resistances, weaknesses, broken threshold)
3. Runtime state tracking (detected, disabled, destroyed)
4. Flexible reset system (manual description or automated timer)
5. Rarity classification for encounter balancing

## Code Quality

### Security
- No security vulnerabilities introduced (JSON schema file)
- Strict validation prevents injection of unexpected data
- CodeQL: N/A (JSON files not analyzed)

### Maintainability
- Clear documentation makes future updates easier
- Consistent structure with other schemas
- Examples guide proper usage
- README provides context and usage guidance

## Conclusion

Successfully improved hazard.schema.json with:
- 8 new fields (rarity, will save, resistances, weaknesses, broken_threshold, 3 state flags)
- Enhanced validation (additionalProperties: false, required fields, constraints)
- Comprehensive documentation (descriptions on all fields, examples)
- Maintained 100% backward compatibility
- Aligned with codebase standards and PF2e rules

The schema is now more robust, better documented, and fully aligned with both the Pathfinder 2E game system and the codebase's schema patterns.
