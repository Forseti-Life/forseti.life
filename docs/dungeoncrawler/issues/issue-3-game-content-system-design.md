# Issue #3: Game Content System - Design Document

## Overview
Design the system for loading, managing, and accessing game content (creatures, items, traps) from JSON schemas into a queryable format for procedural generation and gameplay.

## Architecture Goals

1. **Schema-Driven**: All content defined in JSON schemas
2. **Lazy Loading**: Load content only when needed
3. **Caching**: Cache parsed content for performance
4. **Query Interface**: Filter/search content by criteria
5. **Validation**: Ensure content matches schemas
6. **Extensibility**: Easy to add new content types

## Data Flow Design

```
JSON Schema Files
    ↓
ContentLoader Service
    ↓
Parse & Validate
    ↓
Cache in Memory/DB
    ↓
ContentQuery Service
    ↓
Filter by level, type, tags
    ↓
Return content for generation
```

## Database Architecture

### content_registry Table
```sql
CREATE TABLE dungeoncrawler_content_registry (
  id INT PRIMARY KEY AUTO_INCREMENT,
  content_type VARCHAR(50) NOT NULL,     -- 'creature', 'item', 'trap', 'hazard'
  content_id VARCHAR(100) NOT NULL,      -- e.g. 'goblin_warrior'
  name VARCHAR(255) NOT NULL,            -- Display name
  level INT,                             -- Challenge level (for creatures/traps)
  rarity VARCHAR(20),                    -- 'common', 'uncommon', 'rare', 'unique'
  tags JSON,                             -- ['undead', 'goblinoid', 'small']
  schema_data JSON NOT NULL,             -- Full schema content
  source_file VARCHAR(255),              -- Path to JSON file
  version VARCHAR(20),                   -- Content version
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_content (content_type, content_id),
  INDEX idx_type_level (content_type, level),
  INDEX idx_rarity (rarity),
  INDEX idx_tags (tags) USING GIN       -- For tag searches
)
```

### content_loot_tables Table
```sql
CREATE TABLE dungeoncrawler_content_loot_tables (
  id INT PRIMARY KEY AUTO_INCREMENT,
  table_id VARCHAR(100) NOT NULL UNIQUE, -- 'goblin_common', 'treasure_hoard_5'
  name VARCHAR(255) NOT NULL,
  description TEXT,
  level_range VARCHAR(20),               -- '1-3', '4-7', etc
  entries JSON NOT NULL,                 -- Array of loot entries with weights
  /*
  entries structure:
  [
    {
      "item_id": "gold_piece",
      "quantity": "1d20",
      "weight": 50,
      "condition": ""
    },
    {
      "table_ref": "common_weapons",
      "weight": 30
    }
  ]
  */
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_level (level_range)
)
```

### content_encounter_templates Table
```sql
CREATE TABLE dungeoncrawler_content_encounter_templates (
  id INT PRIMARY KEY AUTO_INCREMENT,
  template_id VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  level INT NOT NULL,                    -- Average party level
  xp_budget INT NOT NULL,                -- Total XP for encounter
  threat_level VARCHAR(20),              -- 'trivial', 'low', 'moderate', 'severe', 'extreme'
  creature_slots JSON NOT NULL,          -- Array of creature requirements
  /*
  creature_slots structure:
  [
    {
      "quantity": 4,
      "level_offset": 0,
      "tags_required": ["goblinoid"],
      "tags_excluded": ["boss"],
      "role": "minion"
    },
    {
      "quantity": 1, 
      "level_offset": 2,
      "tags_required": ["goblinoid", "leader"],
      "role": "boss"
    }
  ]
  */
  environment_tags JSON,                 -- ['dungeon', 'underground', 'cramped']
  INDEX idx_level_threat (level, threat_level)
)
```

## Service Layer Design

### ContentRegistry Service
```php
class ContentRegistry {
  
  /**
   * Load all content from JSON files into database
   * Should be run during module installation/update
   * 
   * @param string|null $content_type - Load specific type or all
   * @return int - Number of items loaded
   */
  public function importContentFromJson(string $content_type = NULL): int
  
  /**
   * Get content by ID and type
   * 
   * @param string $content_type - 'creature', 'item', 'trap', 'hazard'
   * @param string $content_id - Unique identifier
   * @return array|null - Full schema data or null if not found
   */
  public function getContent(string $content_type, string $content_id): ?array
  
  /**
   * Validate content against schema
   * 
   * @param string $content_type
   * @param array $content_data
   * @return array - ['valid' => bool, 'errors' => array]
   */
  public function validateContent(string $content_type, array $content_data): array
  
  /**
   * Update content in registry
   * 
   * @param string $content_type
   * @param string $content_id
   * @param array $content_data
   * @return bool - Success
   */
  public function updateContent(string $content_type, string $content_id, array $content_data): bool
}
```

### ContentQuery Service
```php
class ContentQuery {
  
  /**
   * Query creatures by filters
   * 
   * @param array $filters
   *   - level_min: int
   *   - level_max: int
   *   - tags_include: array
   *   - tags_exclude: array
   *   - rarity: array
   *   - size: string
   *   - alignment: string
   * @param int $limit
   * @return array - Array of creature data
   */
  public function queryCreatures(array $filters, int $limit = 10): array
  
  /**
   * Query items by filters
   * 
   * @param array $filters
   *   - item_type: string ('weapon', 'armor', 'consumable', 'treasure')
   *   - level_min: int
   *   - level_max: int
   *   - tags: array
   *   - rarity: array
   * @param int $limit
   * @return array - Array of item data
   */
  public function queryItems(array $filters, int $limit = 10): array
  
  /**
   * Get random content matching criteria
   * 
   * @param string $content_type
   * @param array $filters
   * @param int $count - Number of items to return
   * @return array - Random selection
   */
  public function getRandomContent(string $content_type, array $filters, int $count = 1): array
  
  /**
   * Get loot table and roll for items
   * 
   * @param string $table_id
   * @return array - Array of rolled items with quantities
   *   [
   *     ['item_id' => 'gold_piece', 'quantity' => 15],
   *     ['item_id' => 'healing_potion', 'quantity' => 1]
   *   ]
   */
  public function rollLootTable(string $table_id): array
  
  /**
   * Build encounter from template
   * 
   * @param string $template_id
   * @param int $party_level
   * @return array - Complete encounter data
   *   [
   *     'creatures' => [...],
   *     'xp_total' => 120,
   *     'threat_level' => 'moderate'
   *   ]
   */
  public function buildEncounterFromTemplate(string $template_id, int $party_level): array
}
```

### ContentGenerator Service
```php
class ContentGenerator {
  
  /**
   * Generate appropriate content for dungeon level
   * 
   * @param int $dungeon_level
   * @param string $theme - 'goblin_warrens', 'undead_crypt', etc
   * @param string $room_type - 'combat', 'treasure', 'trap', 'empty'
   * @return array - Generated content
   *   [
   *     'creatures' => [...],
   *     'items' => [...],
   *     'traps' => [...],
   *     'hazards' => [...]
   *   ]
   */
  public function generateRoomContent(int $dungeon_level, string $theme, string $room_type): array
  
  /**
   * Populate creature with AI personality
   * 
   * @param array $creature_data - Base creature from schema
   * @return array - Creature with generated personality
   */
  public function generateCreaturePersonality(array $creature_data): array
  
  /**
   * Generate treasure hoard for level
   * 
   * @param int $level
   * @param string $hoard_type - 'minor', 'moderate', 'major'
   * @return array - Array of items
   */
  public function generateTreasureHoard(int $level, string $hoard_type): array
}
```

## Content File Structure

```
sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/
  content/
    creatures/
      goblinoids/
        goblin_warrior.json
        goblin_commando.json
        hobgoblin_general.json
      undead/
        skeleton.json
        zombie.json
        wraith.json
    items/
      weapons/
        longsword.json
        shortbow.json
      armor/
        leather_armor.json
        chainmail.json
      consumables/
        healing_potion.json
        antidote.json
      treasure/
        gold_piece.json
        silver_piece.json
        gem_ruby.json
    traps/
      arrow_trap.json
      pit_trap.json
      poison_needle.json
    hazards/
      toxic_mold.json
      unstable_floor.json
    loot_tables/
      goblin_common.json
      treasure_hoard_level_1.json
    encounter_templates/
      goblin_patrol_easy.json
      goblin_camp_moderate.json
```

## API Endpoints

### GET /api/content/query
```
Request:
{
  "content_type": "creature",
  "filters": {
    "level_min": 1,
    "level_max": 3,
    "tags_include": ["goblinoid"],
    "tags_exclude": ["boss"]
  },
  "limit": 10,
  "random": true
}

Response:
{
  "results": [
    {
      "content_id": "goblin_warrior",
      "name": "Goblin Warrior",
      "level": 1,
      "data": { /* full schema data */ }
    }
  ],
  "count": 5,
  "filters_applied": { /* echo filters */ }
}
```

### POST /api/content/generate-encounter
```
Request:
{
  "dungeon_level": 1,
  "party_level": 2,
  "party_size": 4,
  "theme": "goblin_warrens",
  "threat_level": "moderate"
}

Response:
{
  "encounter": {
    "xp_budget": 80,
    "threat_level": "moderate",
    "creatures": [
      {
        "creature_id": "goblin_warrior",
        "count": 3,
        "total_xp": 36
      },
      {
        "creature_id": "goblin_commando",
        "count": 1,
        "total_xp": 44
      }
    ]
  }
}
```

### POST /api/content/roll-loot
```
Request:
{
  "table_id": "goblin_common",
  "quantity": 1
}

Response:
{
  "items": [
    {
      "item_id": "gold_piece",
      "quantity": 12
    },
    {
      "item_id": "leather_armor",
      "quantity": 1,
      "condition": "worn"
    }
  ]
}
```

## Admin Interface Design

### Content Browser (/admin/content/dungeoncrawler/browse)
- Filterable list of all content
- Search by name, type, level, tags
- View/edit content
- Import new content from JSON
- Validate existing content

### Content Editor (/admin/content/dungeoncrawler/edit/{type}/{id})
- JSON editor with schema validation
- Preview rendered content
- Test queries/generation

### Loot Table Editor (/admin/content/dungeoncrawler/loot-tables)
- Manage loot tables
- Test table rolls (simulate drops)
- Weight balancing tools

## Content Validation Rules

1. **Creatures**:
   - Must have valid PF2e stat block
   - Level matches CR/difficulty
   - AI personality fields complete
   - Loot table references exist

2. **Items**:
   - Level appropriate for rarity
   - Valid item type
   - Stats match PF2e rules
   - Bulk calculations correct

3. **Traps**:
   - DC appropriate for level
   - Damage scaled correctly
   - Disable mechanics valid

## Performance Considerations

1. **Cache Strategy**:
   - Cache parsed JSON in memory
   - Invalidate on content update
   - Pre-load common queries

2. **Query Optimization**:
   - Index on level, type, tags
   - Limit result sets
   - Batch queries when possible

3. **Lazy Loading**:
   - Load full content only when needed
   - Store metadata for queries
   - Stream large result sets

## Testing Scenarios

1. Load 100 creatures from JSON files
2. Query creatures level 1-3 with "goblinoid" tag
3. Generate encounter for party of 4, level 2
4. Roll loot table 1000 times (distribution test)
5. Validate all content against schemas
6. Performance test with 10,000+ content items

## Implementation Phases

**Phase 1**: Database schema and ContentRegistry service
**Phase 2**: ContentQuery service with basic filters
**Phase 3**: Content import from JSON files
**Phase 4**: ContentGenerator service
**Phase 5**: API endpoints
**Phase 6**: Admin interface
**Phase 7**: Caching and optimization
