# JSON Schema Directory

This directory contains JSON Schema definitions for all data structures used in the Dungeon Crawler Content module for Pathfinder 2E.

## Table of Contents

- [Purpose](#purpose)
- [Quick Reference](#quick-reference)
- [Schema Categories](#schema-categories)
  - [Character Schemas](#character-schemas)
  - [Dungeon Schemas](#dungeon-schemas)
- [Schema Standards](#schema-standards)
- [Usage](#usage)
- [Maintenance](#maintenance)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [References](#references)
- [Contributing](#contributing)

## Purpose

JSON Schemas serve multiple purposes:
- **Documentation**: Clear, machine-readable specifications of data structures
- **Validation**: Ensures data integrity when creating/updating records
- **Type Safety**: Provides contract for frontend-backend communication
- **IDE Support**: Enables autocomplete and validation in editors
- **Testing**: Facilitates automated testing of data structures

## Quick Reference

| Schema File | Purpose | Versioned | Lines | Primary Use |
|-------------|---------|-----------|-------|-------------|
| `character.schema.json` | Complete PF2e character | ✓ | 564 | `dc_characters.character_data` |
| `character_options_step[1-8].json` | Character creation wizard | Partial | 298-506 | Character creation UI |
| `campaign.schema.json` | Campaign state & progress | ✓ | 137 | `dc_campaigns.campaign_data` |
| `creature.schema.json` | Monsters, NPCs, beasts | ✓ | 1101 | Entity spawning |
| `dungeon_level.schema.json` | Complete dungeon floor | ✓ | 329 | Level generation |
| `encounter.schema.json` | Combat & initiative | ✓ | 568 | Combat engine |
| `entity_instance.schema.json` | Placed entities (runtime) | ✓ | 289 | Runtime entity management |
| `hazard.schema.json` | Environmental hazards | ✓ | 476 | PF2e hazards |
| `hexmap.schema.json` | Hex-based dungeon map | ✓ | 247 | Map structure |
| `item.schema.json` | Equipment & loot | ✓ | 441 | Inventory system |
| `obstacle.schema.json` | Map obstacles | ✓ | 231 | Traversal blockers |
| `obstacle_object_catalog.schema.json` | Reusable obstacle definitions | ✓ | 224 | Obstacle templates |
| `party.schema.json` | Adventuring party | ✓ | 441 | Party management |
| `room.schema.json` | Individual dungeon rooms | ✓ | 471 | Room generation |
| `trap.schema.json` | Mechanical & magical traps | ✓ | 330 | Trap mechanics |

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
2. **Step 2**: Ancestry & Heritage (v1.0.0)
3. **Step 3**: Background (v1.0.0)
4. **Step 4**: Class (v1.0.0)
5. **Step 5**: Ability Scores
6. **Step 6**: Alignment & Deity (v1.0.0)
7. **Step 7**: Equipment
8. **Step 8**: Finishing Touches (v1.0.0)

**Defines:**
- Available options at each step
- Field types and validation rules
- Help text and examples
- Navigation rules
- Error messages

**Recent improvements to Step 2 (2026-02-17):**
- Added schema versioning (v1.0.0) for migration compatibility
- Added step-level validation object for consistency with other steps
- Expanded heritages_by_ancestry schema to document all 14 PF2e ancestries
- Added comprehensive examples section showing recommended ancestry/heritage combinations
- Enhanced documentation noting which ancestries have implemented heritages vs placeholders
- Improved consistency with other character creation step schemas

**Recent improvements to Step 4 (2026-02-17):**
- Added schema versioning (v1.0.0) for migration compatibility
- Added root-level `additionalProperties: false` for strict validation
- Added `additionalProperties: false` to navigation, validation, and boost_sources_produced objects
- Improved schema consistency and validation strictness across character creation steps

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
- Added `additionalProperties: false` to 13 object definitions for stricter validation
- Enhanced numeric constraints on movement speeds (land, fly, swim, climb, burrow)
- Added constraints on skills (modifier range -10 to +50)
- Added constraints on spells (DC 1-50, attack modifier -5 to +40)
- Added constraints on lifecycle (wander_radius_rooms 0-50)
- Added required fields to nested objects (perception, skills, spell_slots, random loot)
- Added string minLength validation to prevent empty strings in arrays
- Added missing optional fields (description, source) for real-world data compatibility
- Enhanced 25+ property descriptions for clarity
- Validated against existing creature data (goblin_warrior.json)

**Further improved (2026-02-17, DCC-0016):**
- Added `uniqueItems: true` to 9 arrays (traits, immunities, senses, languages, spells, catchphrases, preferred_targets, patrol_route, attack/ability traits)
- Added `minLength: 1` to 24 string properties to prevent empty strings
- Enhanced enum descriptions for 7 fields (rarity, spell tradition/type, memory sentiment, proficiency, attack type, action cost, loot rarity)
- Added maximum constraints to 10 numeric fields (HP max/current/temp/hardness, reach, XP, loot quantity, currency ranges)
- Improved documentation with realistic value ranges and PF2e context
- All changes fully backward compatible
- Total: 50 targeted validation improvements
- See: `REVIEW_SUMMARY_DCC-0016.md`

#### `dungeon_level.schema.json`
Entire dungeon floor with hexmap, rooms, and encounters.

**Recently improved (2026-02-17):**
- Added schema versioning for migration compatibility
- Enhanced validation with comprehensive numeric constraints (20+ min/max pairs)
- Added timestamp tracking (created_at, updated_at)
- Extracted reusable definitions (hex_coordinate, stairway)
- Added additionalProperties constraints throughout for stricter validation
- Added required fields to nested objects (stairway, environmental effects)
- Added uniqueItems constraints to prevent duplicate array entries
- Improved PF2e rule alignment for party levels, creature levels, and DCs
- **DCC-0017 (2026-02-17):** Additional validation improvements:
  - Added `uniqueItems: true` to 9 arrays (rooms, entities, creatures, items, traps, hazards, obstacles, active_encounters, stairways)
  - Added `minLength: 1` to string fields (name, flavor_text) to prevent empty strings
  - Added required fields ["min", "max"] to range objects (room_count, secret_rooms, creature_level_range)
- **DCC-0017 (2026-02-18):** Comprehensive constraint improvements (16 additions):
  - **String constraints**: Added `maxLength` to 6 string fields (name: 200, flavor_text: 2000, custom_theme: 500, environmental effect properties: 100-1000, creature type/pool items: 100)
  - **Array constraints**: Added `maxItems` to 12 arrays (rooms: 100, entities: 500, creatures: 200, items: 200, traps: 50, hazards: 50, obstacles: 100, active_encounters: 20, stairways: 10, creature_types_allowed: 30, environmental_effects: 10, creature_pool: 50)
  - **Numeric bounds**: Added `maximum` to 8 numeric fields (depth: 100, room_count min/max: 100, secret_rooms min/max: 20, check_interval_minutes: 1440, rooms_generated/explored: 100, destination_level: 100, times_visited: 10000)
  - All changes maintain backward compatibility; existing valid data remains valid

Canonical runtime placement in this schema is `entities[]` via `entity_instance.schema.json`.

#### `entity_instance.schema.json`
**Primary Runtime Entity Representation**: Unified placed-entity runtime instance (`creature`, `item`, `obstacle`) with placement and mutable state.

**Purpose**: This is the canonical data structure for all placed entities in dungeon levels at runtime, referenced by `dungeon_level.schema.json` in the `entities[]` array. Provides consistent interface for entity lifecycle management (spawn, move, despawn).

**Key Features**:
- **Unified Entity Model**: Single schema handles creatures, items, and obstacles
- **Content Reference System**: `entity_ref` links to base definitions (creature.schema.json, item.schema.json, etc.)
- **Runtime State Tracking**: Mutable `state` object tracks lifecycle, combat, and gameplay changes
- **Hex Placement**: Axial coordinate system for precise dungeon map positioning
- **Inventory Support**: Creatures can carry items via inventory references
- **Version Pinning**: Optional version field for deterministic replay

**State Properties**:
- `active`: Currently active in game world
- `destroyed`: Permanently destroyed (killed, consumed, demolished)
- `disabled`: Temporarily disabled (disarmed trap, deactivated hazard)
- `hidden`: Hidden from view (stealthy creature, concealed trap)
- `collected`: Item collected by party (primarily for items)
- `hit_points`: Combat HP tracking (primarily for creatures)
- `inventory`: Carried items (primarily for creatures)
- `metadata`: Extensible storage for entity-specific data

**Examples**: Includes comprehensive examples for creature, item, and obstacle instances with realistic runtime state.

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
PF2e-compatible environmental hazards (simple and complex). Unlike traps, hazards are often ongoing and visible.

**Recently improved (2026-02-17):**
- Added schema versioning for migration compatibility
- Added timestamp tracking (created_at, updated_at)
- Added comprehensive numeric constraints (16 min/max pairs)
- Added string validation (minLength) to prevent empty strings
- **Added maxLength constraints to 9 string fields** (name, description, trigger, routine, custom, immunities items, conditions items, effect.description, reset.conditions)
- **Added maxItems constraint to traits array** (max 10 traits)
- **Added maximum to reset_time_minutes** (10080 minutes = 1 week)
- Added array validation (uniqueItems) to prevent duplicates
- Enhanced validation constraints aligned with PF2e rules (levels -1 to 25, DCs 0-50)
- Improved consistency with trap.schema.json structure
- Structured effect field with attack_bonus, damage, save_dc, area, conditions_applied
- Structured disable field with named skill properties (thievery_dc, arcana_dc, etc.)
- Added is_triggered state tracking field
- Added reusable definitions section (hex_coordinate)
- Added comprehensive examples (simple and complex hazards)
- Capped actions_per_round at 4 for realistic complex hazards
- Enhanced property descriptions for clarity (initiative_modifier, routine, reset_time_minutes)

**Defines:**
- Simple hazards: One-time dangers (falling rocks, collapsing floors)
- Complex hazards: Ongoing threats that act in initiative order
- Physical stats: AC, hardness, HP, saves, immunities, resistances, weaknesses
- Detection and disabling: Stealth DC, structured disable skill DCs
- State tracking: is_active, is_detected, is_triggered, is_disabled, is_destroyed
- Hex placement: Coordinates for map-based hazards via reusable hex_coordinate definition
- Rarity classification: common, uncommon, rare, unique

**Key Features:**
- Structured effect object (attack rolls, damage dice, saving throws, conditions, area of effect)
- Structured disable object with named PF2e skills (Thievery, Athletics, Arcana, Religion, Crafting)
- Supports both string and structured object format for reset mechanics
- Optional initiative_modifier and routine for complex hazards
- Full PF2e save support (Fortitude, Reflex, Will)
- Strict validation with additionalProperties: false
- Comprehensive constraints aligned with PF2e rules
- Complete examples demonstrating simple and complex hazard patterns
- Comprehensive string length bounds to prevent storage/rendering issues


#### `hexmap.schema.json`
Hex-based dungeon map with fog of war and terrain using axial coordinates (q, r) for flat-top hex positioning.

**Defines:**
- Hex grid configuration (orientation, size, origin)
- Depth tiers (shallow_halls → the_abyss)
- Individual hex properties (terrain, elevation, visibility)
- Fog of war state (explored, visible, hidden)
- Connections between hexes (doors, passages)
- PF2e compatibility (5ft hexes = 1 PF2e square)

**Key Features:**
- Schema versioning (v1.0.0)
- Strict validation with additionalProperties: false
- Default flat-top orientation for dungeon crawls
- Supports dynamic terrain and elevation rules

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
Unified traversal/combat obstacles (non-container blockers/modifiers). PF2e-compatible obstacles that affect movement, provide cover, or deal damage.

**Recently improved (2026-02-17):**
- Added schema versioning for migration compatibility
- Added PF2e level field for appropriate challenge scaling
- Added rarity and traits fields for PF2e classification
- Enhanced validation with numeric constraints on DCs (1-50)
- Added uniqueItems constraint to hexes array
- Added minLength validation to prevent empty strings
- Added xp_reward field aligned with trap/hazard patterns
- Enhanced descriptions with practical examples
- Improved consistency with trap.schema.json and hazard.schema.json
- Added top-level additionalProperties: false for stricter validation
- Added additionalProperties: false to state object for controlled runtime state
- Simplified damage pattern to match standard PF2e dice notation (XdY+Z format only)
- Added comprehensive examples demonstrating barricade and magical barrier patterns

**Defines:**
- Obstacle metadata (name, level, type, rarity, traits)
- Movement rules (passable, cost multiplier, skill checks)
- Combat effects (cover, damage on enter, saves)
- Runtime state (active, disabled, destroyed)
- Optional source reference to underlying trap/hazard
- XP rewards for overcoming obstacles
- Hex placement for map positioning

**Key Features:**
- Full PF2e integration with level-based DCs
- Movement cost multipliers for difficult terrain
- Combat mechanics (cover bonuses, damage, saves)
- Flexible skill check requirements (Athletics, Acrobatics, etc.)
- State tracking for runtime obstacle management
- Links to underlying trap/hazard definitions
- Strict validation with additionalProperties: false throughout

#### `obstacle_object_catalog.schema.json`
Reusable obstacle object definitions (label, movable, stackable, movement flags) used by placed obstacle instances.

**Recently improved (2026-02-17):**
- Added schema versioning for migration compatibility (schema_version now required)
- Added `$defs` section with reusable `movement_config` definition for better schema organization
- Enhanced description field with clearer guidance and additional examples
- Added optional enrichment fields for more detailed obstacle definitions:
  - `size`: PF2e size category (tiny, small, medium, large, huge, gargantuan)
  - `weight`: PF2e Bulk value for weight/portability (L or numeric)
  - `interaction`: Mechanics for opening, closing, skill DCs (Athletics, Thievery)
  - `visual`: Rendering metadata (sprite_id, color, rotation)
- Enhanced validation with improved constraints:
  - Added minLength: 1 to object_id and description to prevent empty values
  - Added maximum: 999 to cost_multiplier for reasonable upper bound
  - Added uniqueItems: true to tags array to prevent duplicate tags
  - Added minLength: 1 to tag items to prevent empty strings
- All new fields are optional, maintaining full backward compatibility
- Validates successfully with existing example data (tavern-obstacle-objects.json)


#### `party.schema.json`
Adventuring party with shared resources and exploration state.

**Recent Improvements (v1.0.0):**
- Added `schema_version` for migration compatibility
- Renamed `last_active` → `updated_at` for timestamp consistency
- Added `definitions` section with reusable components: `hex_position`, `condition`, `currency`
- Enhanced validation constraints throughout (minLength, maxLength, minimum values)
- Added `additionalProperties: false` for stricter validation
- Added `uniqueItems: true` to 4 arrays (watch_order, revealed_hexes, revealed_rooms, revealed_connections)
- Enhanced `spell_slots_remaining` with strict pattern validation for ranks 0-10
- Added comprehensive examples for shared_inventory, encounter_log, fog_of_war notes, and exploration_activity
- Improved documentation with comprehensive descriptions
- Validates successfully with test data

**Defines:**
- Party metadata (name, owner, timestamps)
- Party members with PF2e conditions, spell slots, hero points, exploration activities
- Shared inventory and currency (cp, sp, gp, pp)
- Exploration state (mode, lighting, movement speed, rest tracking)
- Fog of war tracking (revealed hexes/rooms/connections, player notes)
- Encounter history log
- Cumulative dungeon statistics

#### `room.schema.json`
Individual dungeon rooms that occupy one or more hexes. AI-generated on first entry and permanent thereafter.

**Recently improved (2026-02-17):**
- Added schema versioning for migration compatibility
- Added timestamp tracking (created_at, updated_at)
- Added uniqueItems constraints to 11 arrays for data integrity
- Added minLength validation to prevent empty strings (10+ fields)
- Added maxLength constraints to name fields for UI compatibility
- Extracted reusable hex_coordinate definition to avoid duplication
- Enhanced property descriptions for clarity
- Added comprehensive room example with realistic data
- Improved consistency with trap.schema.json, hazard.schema.json, and obstacle.schema.json

**Defines:**
- Room metadata (UUID, name, AI descriptions, GM notes)
- Multi-hex occupation with per-hex terrain overrides
- Lighting conditions (bright, dim, darkness, magical darkness)
- Room state (explored, active, cleared)
- Environmental effects (temperature, hazards, magic auras)
- Connections to other rooms via doors/passages
- Contained entities (creatures, items, obstacles, traps)
- Terrain types (stone, dirt, water, etc.)

**Key Features:**
- AI-generated narrative descriptions for read-aloud text
- Per-hex elevation tracking for PF2e rules
- Support for furniture and hex-specific objects
- Hidden vs visible room features
- Persistent state after first exploration
- Strict validation with uniqueItems and minLength constraints

#### `trap.schema.json`
PF2e-compatible traps and snares (simple and complex). Traps are hidden threats that trigger when activated.

**Recently improved (2026-02-17):**
- Added `definitions` section with reusable hex_coordinate component
- Referenced hex_coordinate definition in hexes_affected array for consistency
- Added string validation (minLength: 1) to traits array items to prevent empty strings
- Added maximum value constraints to resistances/weaknesses (max: 30) aligned with hazard.schema
- Enhanced damage_type description to clarify support for multiple damage types
- Added comprehensive examples section with simple and complex trap patterns
- Improved consistency with hazard.schema.json structure and validation patterns

**Defines:**
- Simple traps: One-time dangers (dart trap, pit trap)
- Complex traps: Ongoing threats that act in initiative order
- Physical stats: AC, hardness, HP, immunities, resistances, weaknesses
- Detection and disabling: Stealth DC, multiple skill disable DCs
- Trigger/effect system: Attack rolls, saving throws, damage, conditions
- Area of effect: Single hex, burst, emanation, cone, line
- Reset mechanics: Automatic or manual with timing
- State tracking: Runtime flags for detection, disabling, triggering, destruction

**Key Features:**
- Reusable definitions section (hex_coordinate) for schema consistency
- Supports both string and structured object format for reset mechanics
- Multiple skill options for disabling (Thievery, Athletics, Arcana, Religion, Crafting)
- Pattern validation for damage dice notation
- Full PF2e trait system support
- Strict validation with additionalProperties: false
- Comprehensive examples demonstrating simple and complex trap patterns
- Maximum value constraints aligned with hazard.schema (resistances/weaknesses max: 30)
- Enhanced damage_type field to support multiple damage types (e.g., "piercing, poison")

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

### PHP Integration (Drupal)

#### Using SchemaLoader Service

The module provides a `SchemaLoader` service for loading and validating schemas:

```php
// Inject the service
$schemaLoader = \Drupal::service('dungeoncrawler_content.schema_loader');

// Load specific schemas
$characterSchema = $schemaLoader->loadCharacterSchema();
$campaignSchema = $schemaLoader->loadCampaignSchema();
$stepSchema = $schemaLoader->loadStepSchema(1); // Character creation step 1

// Validate campaign data
$validationResult = $schemaLoader->validateCampaignData($campaignData);
if ($validationResult['valid']) {
  // Data is valid
} else {
  // Handle validation errors
  foreach ($validationResult['errors'] as $error) {
    // Process error
  }
}
```

#### Manual Validation with justinrainbow/json-schema

```php
use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

// Load schema and data
$validator = new Validator();
$data = json_decode($character->character_data);
$schemaPath = __DIR__ . '/schemas/character.schema.json';
$schema = json_decode(file_get_contents($schemaPath));

// Validate with coercion for type flexibility
$validator->validate($data, $schema, Constraint::CHECK_MODE_COERCE_TYPES);

if ($validator->isValid()) {
  // Data is valid
  \Drupal::logger('dungeoncrawler_content')->info('Character data validated successfully');
} else {
  // Handle validation errors
  foreach ($validator->getErrors() as $error) {
    // Process error: $error['property'], $error['message']
    \Drupal::logger('dungeoncrawler_content')->error(
      'Validation error in @property: @message',
      ['@property' => $error['property'], '@message' => $error['message']]
    );
  }
}
```

### Validation in JavaScript
```javascript
import Ajv from 'ajv';
import addFormats from 'ajv-formats';
import characterSchema from './schemas/character.schema.json';

// Initialize Ajv with format support
const ajv = new Ajv({ allErrors: true });
addFormats(ajv);

const validate = ajv.compile(characterSchema);

if (validate(characterData)) {
  // Data is valid
  console.log('Character data is valid');
} else {
  // Log validation errors with details
  console.error('Validation failed:', validate.errors);
  validate.errors.forEach(error => {
    console.error(`  ${error.instancePath}: ${error.message}`);
  });
}
```

### VS Code Integration
Add to `.vscode/settings.json` in your workspace:
```json
{
  "json.schemas": [
    {
      "fileMatch": ["**/character_data/*.json"],
      "url": "./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/schemas/character.schema.json"
    },
    {
      "fileMatch": ["**/campaign_data/*.json"],
      "url": "./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/schemas/campaign.schema.json"
    },
    {
      "fileMatch": ["**/creatures/*.json"],
      "url": "./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/schemas/creature.schema.json"
    }
  ]
}
```

This enables IDE autocomplete, validation, and inline documentation for JSON data files.

## Maintenance

### Directory Cleanup Note

This directory previously contained historical completion/summary markdown files (e.g., `DCC-XXXX_COMPLETION.md`, `REVIEW_SUMMARY_DCC-XXXX.md`). These legacy work-tracking documents have been removed in accordance with the repository's Work Tracking and Status Policy (see `.github/instructions/instructions.md`). Per policy, implementation status should be tracked in GitHub Issues, not in separate markdown files. This directory now focuses solely on JSON schema files and this README documentation.

### Schema Versioning Status

Schemas with `schema_version` field (migration-ready):
- ✓ `campaign.schema.json`
- ✓ `character.schema.json`
- ✓ `character_options_step3.json`
- ✓ `character_options_step6.json`
- ✓ `character_options_step8.json`
- ✓ `creature.schema.json`
- ✓ `dungeon_level.schema.json`
- ✓ `encounter.schema.json`
- ✓ `entity_instance.schema.json`
- ✓ `hazard.schema.json`
- ✓ `hexmap.schema.json`
- ✓ `item.schema.json`
- ✓ `obstacle.schema.json`
- ✓ `obstacle_object_catalog.schema.json`
- ✓ `party.schema.json`
- ✓ `room.schema.json`
- ✓ `trap.schema.json`

Schemas with versioning (recently added):
- ✓ `obstacle.schema.json`
- ✓ `obstacle_object_catalog.schema.json`
- ✓ `room.schema.json`

Schemas pending versioning:
- `character_options_step[1-2,4-5,7].json` (UI-only schemas - lower priority)
- `obstacle.schema.json` (needs versioning for production use)
- `obstacle_object_catalog.schema.json` (needs versioning for production use)
- `room.schema.json` (needs versioning for production use)

### Adding New Properties
1. Update the appropriate schema file
2. Add description and validation rules
3. Include default value if applicable
4. Test with sample data
5. Update this README if adding new schema category
6. Update the Quick Reference table with accurate line counts

### Breaking Changes
When making breaking changes:
1. Increment the schema version (following semantic versioning)
2. Document migration path in schema and README
3. Update SchemaLoader service if needed
4. Support both old and new formats during transition period
5. Update all references in code
6. Test with existing data to ensure backward compatibility

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

## Testing

### Validating Schema Files

Use online validators to ensure schemas are valid JSON Schema Draft 07:
- [JSON Schema Validator](https://www.jsonschemavalidator.net/)
- [JSONLint](https://jsonlint.com/) for JSON syntax validation

### Testing with Sample Data

```bash
# Using ajv-cli (install globally: npm install -g ajv-cli)
ajv validate -s character.schema.json -d sample_character.json

# Using PHP from Drupal module
drush php-eval "
  \$loader = \Drupal::service('dungeoncrawler_content.schema_loader');
  \$result = \$loader->validateCampaignData(\$sampleData);
  print_r(\$result);
"
```

### PHPUnit Tests

Schema validation is tested in the module's unit tests:
- `tests/src/Unit/Service/SchemaLoaderTest.php` (if exists)
- Character creation controllers validate against step schemas
- Campaign forms validate against campaign schema

## Troubleshooting

### Common Issues

**Issue: "Schema file not found"**
- **Cause**: Incorrect schema path or missing file
- **Solution**: Check that schema file exists in `config/schemas/` directory
- **Check logs**: `drush watchdog:show --type=dungeoncrawler_content`

**Issue: "Invalid JSON in schema"**
- **Cause**: Syntax error in schema file
- **Solution**: Use JSONLint to validate JSON syntax
- **Common errors**: Missing commas, unclosed brackets, trailing commas

**Issue: "Data validation fails unexpectedly"**
- **Cause**: Schema rules too strict or data format mismatch
- **Solution**: Check validation error messages for specific property failures
- **Debug**: Use `json_encode()` to see actual data structure

**Issue: "Schema version mismatch"**
- **Cause**: Data created with older schema version
- **Solution**: Implement migration logic or support multiple versions
- **Example**: Check `schema_version` field and apply appropriate validation

### Debugging Validation Errors

```php
// Get detailed validation errors
$validator = new \JsonSchema\Validator();
$validator->validate($data, $schema);

if (!$validator->isValid()) {
  foreach ($validator->getErrors() as $error) {
    echo sprintf("[%s] %s\n", $error['property'], $error['message']);
    // Example output: [campaign_data.active_hex] Does not match pattern: ^q-?\d+r-?\d+$
  }
}
```

## References

- [JSON Schema Specification](https://json-schema.org/)
- [JSON Schema Draft 07 Specification](https://json-schema.org/draft-07/json-schema-release-notes.html)
- [Pathfinder 2E Rules (Archives of Nethys)](https://2e.aonprd.com/)
- [Drupal Field Types](https://www.drupal.org/docs/drupal-apis/entity-api/fieldtypes-fieldwidgets-and-fieldformatters)
- [justinrainbow/json-schema (PHP)](https://github.com/justinrainbow/json-schema)
- [Ajv JSON Schema Validator (JavaScript)](https://ajv.js.org/)
- [Module Documentation](../README.md)
- [SchemaLoader Service](../../src/Service/SchemaLoader.php)

## Contributing

When contributing new schemas:
1. Follow existing naming conventions
2. Include comprehensive descriptions
3. Add validation rules appropriate for PF2e rules
4. Test with real data
5. Update this README
6. Add examples where helpful
