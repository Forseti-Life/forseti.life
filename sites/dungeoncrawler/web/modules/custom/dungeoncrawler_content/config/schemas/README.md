# JSON Schema Directory

This directory contains JSON Schema definitions for all data structures used in the Dungeon Crawler Content module for Pathfinder 2E.

## Purpose

JSON Schemas serve multiple purposes:
- **Documentation**: Clear, machine-readable specifications of data structures
- **Validation**: Ensures data integrity when creating/updating records
- **Type Safety**: Provides contract for frontend-backend communication
- **IDE Support**: Enables autocomplete and validation in editors
- **Testing**: Facilitates automated testing of data structures

## Schema Categories

### Character Schemas

#### `character.schema.json`
Complete Pathfinder 2E character data structure stored in the `dc_characters` table's `character_data` JSON column.

**Defines:**
- Character attributes (name, level, abilities)
- Ancestry, heritage, background, class
- Equipment, spells, feats
- Conditions and game state
- Backstory and appearance

#### `character_options_step1.json` - `character_options_step8.json`
Character creation wizard options and validation rules for each of the 8 steps:

1. **Step 1**: Name & Concept
2. **Step 2**: Ancestry & Heritage
3. **Step 3**: Background
4. **Step 4**: Class
5. **Step 5**: Ability Scores
6. **Step 6**: Alignment & Deity
7. **Step 7**: Equipment
8. **Step 8**: Finishing Touches

**Defines:**
- Available options at each step
- Field types and validation rules
- Help text and examples
- Navigation rules
- Error messages

### Dungeon Schemas

#### `campaign.schema.json`
Campaign state payload stored in `dc_campaigns.campaign_data`.

**Defines:**
- Campaign ownership (created_by user ID)
- Campaign status (started flag, progress events)
- Current location (active_hex in axial coordinates)
- Timestamps (created_at, updated_at)
- Custom metadata storage

**Validation:**
- Runtime validation via `SchemaLoader::validateCampaignData()`
- schema_version: Semantic versioning pattern (e.g., "1.0.0")
- active_hex: Axial coordinate format (e.g., "q0r0", "q2r-1")
- progress events: Required type (string) and timestamp (unix epoch)

**Common Progress Event Types:**
- `quest_started`, `quest_completed`
- `location_discovered`
- `combat_won`, `combat_fled`
- `item_acquired`
- `level_up`, `character_death`
- `milestone_reached`

#### `creature.schema.json`
Monsters, NPCs, and beasts with PF2e stats and AI personality.

**Recently improved (2026-02-17):**
- Added schema versioning for migration compatibility
- Enhanced validation with required fields and numeric constraints
- Added timestamp tracking (created_at, updated_at)
- Improved pattern validation for damage formulas
- Added additionalProperties constraints for stricter validation

#### `dungeon_level.schema.json`
Entire dungeon floor with hexmap, rooms, and encounters.

Canonical runtime placement in this schema is `entities[]` via `entity_instance.schema.json`.

#### `entity_instance.schema.json`
Unified placed-entity runtime instance (`creature`, `item`, `obstacle`) with placement and mutable state.

#### `encounter.schema.json`
Combat encounters with creatures, initiative, and tactical state.

**Recent Improvements (v1.0.0):**
- Added `schema_version` for migration compatibility
- Extracted reusable definitions: `hex_position`, `condition`, `roll_result`, `damage_result`
- Added `campaign_id` field to support actual database implementation patterns
- Enhanced documentation clarifying database storage vs schema specification
- Improved validation constraints and examples

**Defines:**
- Encounter metadata (type, status, threat level)
- XP budget thresholds (trivial=40, low=60, moderate=80, severe=120, extreme=160)
- Combatant tracking (initiative, HP, position, conditions)
- Combat state (round, active combatant, action log)
- Terrain effects and hazards
- Rewards (XP, currency, items)
- AI-generated narrative elements

**Note:** Runtime data is stored in relational tables (`combat_encounters`, `combat_participants`, `combat_conditions`, `combat_actions`) while this schema serves as documentation and validation specification.


#### `hazard.schema.json`
Environmental hazards and traps.

#### `hexmap.schema.json`
Hexagonal dungeon map with fog of war and terrain.

#### `item.schema.json`
Equipment and magic items (loot/treasure is represented as items).

**Recently improved (2026-02-17):**
- Added schema versioning for migration compatibility
- Enhanced validation with comprehensive numeric constraints (35+ min/max pairs)
- Added timestamp tracking (created_at, updated_at)
- Improved pattern validation for dice formulas and bulk values
- Added additionalProperties constraints throughout for stricter validation
- Added required fields to nested objects (weapon damage, shield stats, etc.)
- Added uniqueItems constraints to prevent duplicate array entries
- Comprehensive documentation with examples for complex structures


#### `obstacle.schema.json`
Unified traversal/combat obstacles (non-container blockers/modifiers).

#### `obstacle_object_catalog.schema.json`
Reusable obstacle object definitions (label, movable, stackable, movement flags) used by placed obstacle instances.

#### `party.schema.json`
Adventuring party with shared resources and exploration state.

#### `room.schema.json`
Individual dungeon rooms with contents and connections.

#### `trap.schema.json`
Mechanical and magical traps with PF2e difficulty.

## Schema Standards

All schemas follow these conventions:

### Base Properties
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "https://dungeoncrawler.life/schemas/[schema-name].json",
  "title": "Human-readable title",
  "description": "Detailed description of purpose",
  "type": "object"
}
```

### Pathfinder 2E Alignment
- All schemas use official PF2e terminology
- Ability scores: `str`, `dex`, `con`, `int`, `wis`, `cha`
- Proficiency ranks: `untrained`, `trained`, `expert`, `master`, `legendary`
- Standard PF2e levels: 1-20 for characters, -1 to 25 for creatures

### Validation
- Use `enum` for fixed options
- Set `minimum`/`maximum` for numeric ranges
- Use `format` for dates, UUIDs, etc.
- Include descriptive error messages

### Documentation
- Every property has a `description`
- Complex structures include `examples`
- Default values are specified where appropriate

## Usage

### Validation in PHP (Drupal)
```php
use JsonSchema\Validator;

$validator = new Validator();
$data = json_decode($character->character_data);
$schema = json_decode(file_get_contents(__DIR__ . '/schemas/character.schema.json'));

$validator->validate($data, $schema);
if ($validator->isValid()) {
  // Data is valid
} else {
  // Handle validation errors
  foreach ($validator->getErrors() as $error) {
    // Process error
  }
}
```

### Validation in JavaScript
```javascript
import Ajv from 'ajv';
import characterSchema from './schemas/character.schema.json';

const ajv = new Ajv();
const validate = ajv.compile(characterSchema);

if (validate(characterData)) {
  // Data is valid
} else {
  console.error(validate.errors);
}
```

### VS Code Integration
Add to `settings.json`:
```json
{
  "json.schemas": [
    {
      "fileMatch": ["**/character_data/*.json"],
      "url": "./config/schemas/character.schema.json"
    }
  ]
}
```

## Maintenance

### Adding New Properties
1. Update the appropriate schema file
2. Add description and validation rules
3. Include default value if applicable
4. Update this README if adding new schema category
5. Test with sample data

### Breaking Changes
When making breaking changes:
1. Version the schema (add `version` property)
2. Document migration path
3. Support both old and new formats during transition
4. Update all references in code

### Schema Versioning
Consider versioning schemas when:
- Removing required fields
- Changing data types
- Renaming properties
- Changing validation rules significantly

Example:
```json
{
  "schema_version": "2.0.0",
  "deprecated_fields": ["old_field_name"],
  "migration_guide": "..."
}
```

## References

- [JSON Schema Specification](https://json-schema.org/)
- [Pathfinder 2E Rules](https://2e.aonprd.com/)
- [Drupal Field Types](https://www.drupal.org/docs/drupal-apis/entity-api/fieldtypes-fieldwidgets-and-fieldformatters)

## Contributing

When contributing new schemas:
1. Follow existing naming conventions
2. Include comprehensive descriptions
3. Add validation rules appropriate for PF2e rules
4. Test with real data
5. Update this README
6. Add examples where helpful
