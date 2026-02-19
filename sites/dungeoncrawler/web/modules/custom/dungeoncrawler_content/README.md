# Dungeon Crawler Content Module

**Module Name**: dungeoncrawler_content  
**Version**: 1.0.0  
**Drupal**: 10.3+ || 11.x  
**Package**: Dungeon Crawler

## Overview

Core content module for the living dungeon crawler RPG. Provides character management, game content types, and navigation structure for the Dungeoncrawler universe.

### Player-Facing Positioning (2026-02-18)

Current game-facing messaging is intentionally tuned for former tabletop/classic RPG players who want:
- A persistent campaign home rather than disposable one-off sessions
- Long-term character continuity across adventures
- A clear path for character retirement and successor roster play
- Participation in a constantly growing Dungeon Crawler Forseti Life universe

## Features

### Character Management System
- **Character CRUD Operations**: Create, read, update, delete player characters
- **Character Service**: `dungeoncrawler_content.character_manager` - Database operations for character data
- **Template-backed starting equipment**: Character creation step 7 now loads purchase options from `dungeoncrawler_content_item_instances` joined with `dungeoncrawler_content_registry` (with static fallback if template tables are unavailable)
- **Schema-driven validation**: Character creation validation is centralized server-side via `SchemaLoader::validateStepData` with schema rules; client-side JavaScript is kept to UI state as constraints are standardized.
- **Portrait generation on completion**: Step 8 can trigger an AI portrait using character attributes plus an optional user prompt, then links the result to `dc_campaign_characters` in generated-image tables.
- **Character Routes**:
  - `/characters` - List all user's characters
  - `/characters/create` - Create new character
  - `/characters/{id}` - View character sheet
  - `/characters/{id}/edit` - Edit character
  - `/characters/{id}/delete` - Delete character

### Validation Standardization TODO
- Remove remaining client-side validation from steps 5, 7, and 8 (plus ability boost selector) so server-side schema validation is authoritative.
- Ensure character_options_step3-8 schemas define min_length, max_length, pattern, and error_messages for fields that need them.
- Keep Playwright workflow tests aligned with localhost-based URLs once access is verified.

### Campaign Management System
- **Campaign-first entry flow**: Start adventure by creating a campaign, then select or create a character
- **Default tavern dungeon backfill**: Campaigns without dungeon rows auto-seed a Tavern Entrance dungeon into `dc_campaign_dungeons` when opening dungeon selection
- **Centralized page wrapper for management forms**: `management_form_page` template for themed create/edit pages
- **Tavern entrance launch flow**: After creating a campaign, route to campaign tavern entrance to select character and launch hexmap
- **Archive flow with confirmation checkbox**: Campaign archive requires checking a confirmation box and hides campaigns from the active list without deleting them
- **Archived campaign section**: `/campaigns` includes a compact archived campaigns section with unarchive actions
- **Status restore on unarchive**: Unarchiving restores the campaign to its previous pre-archive status when available
- **Campaign Routes**:
   - `/campaigns` - List your campaigns (returning-user hub)
   - `/campaigns/create` - Create a campaign
   - `/campaigns/{campaign_id}/archive` - Archive campaign (hide from list, non-destructive)
   - `/campaigns/{campaign_id}/unarchive` - Unarchive campaign (show on list again)
   - `/campaigns/{campaign_id}/dungeons` - Dungeon selection page listing campaign dungeon records from the database
   - `/campaigns/{campaign_id}/tavernentrance` - Select character and launch campaign into hexmap
   - `/campaigns/{campaign_id}/select-character/{character_id}` - Bind character to campaign and launch
- **Campaign Context Flow**:
   - `/characters?campaign_id={id}` switches My Characters into campaign selection mode
   - Character creation preserves `campaign_id` through step redirects
   - Campaign character selection resolves hexmap launch context (`dungeon_level_id`, `map_id`, `room_id`, `next_room_id`) from the latest campaign dungeon record instead of static IDs
   - `/hexmap` now receives a launch-character summary from campaign context and uses it to hydrate the bottom character sheet immediately on initial load (before entity selection/combat turn hydration)

### Game Object Management
- **Table inventory interface**: Admin page inventories all Dungeon Crawler custom tables (`dc_*` and `dungeoncrawler_content_*`) and summarizes what objects they store.
- **Inventory service layer**: Table discovery, object classification, and row loading are centralized in `dungeoncrawler_content.game_object_inventory` (`Drupal\\dungeoncrawler_content\\Service\\GameObjectInventoryService`).
- **Filterable inventory controls**: Filter inventory by `Schema`, `Table`, and `Object Type`, plus a separate `Object Name Contains` free-text filter.
- **Template vs campaign delineation**: Inventory is split into explicit `Template Objects` and `Active Campaign Objects` sections with an `Object Type` classifier column.
- **Section count summary**: Inventory delineation includes per-section table counts for `Template`, `Active Campaign`, and `Fact` groups.
- **Collapsible section UI**: Group tables are rendered as a collapsed-by-default accordion (`Template`, `Active Campaign`, `Fact`) for easier scanning.
- **Field inventory**: Per-table field/type/index listing for complete schema visibility.
- **Row browser and editor**: Browse stored rows, search within selected table rows (`Row Contains`), and edit all row fields directly from `/dungeoncrawler/objects`.
- **Generated image visibility in object manager**: Row browser includes `Image Links` counts for linkable object tables, and row edit view shows a linked-image summary card with UUID, slot, provider, visibility, and preview link.
- **Full-row JSON editing**: Row editor includes an advanced JSON payload editor for object-level updates in one JSON object, activated explicitly via `Use JSON editor for this update`.
- **Row search efficiency**: `Row Contains` filtering executes at database query level across table columns before row cap is applied.
- **JSON editor guardrails**: JSON mode accepts object-style payloads only and validates keys against real table columns.
- **Context-preserving edit workflow**: Saving a row returns to the same filtered table/search view used when opening the editor.
- **Template import action**: `/dungeoncrawler/objects` includes an `Import templates` button that loads file-based examples into template tables.
- **Table-organized template examples**: Template import files are stored in `config/examples/templates/{table_name}/` and can grow over time as default examples expand.
- **Theme-safe table contrast**: Dashboard tables explicitly set Bootstrap table variables so dark-theme text/background remains readable, including empty-state rows.
- **Object Management Route**:
   - `/dungeoncrawler/objects` - Review objects and attributes

### Information Pages
- **World Lore** (`/world`) - Living dungeon background and lore
- **How to Play** (`/how-to-play`) - Game mechanics and tutorial
- **About** (`/about`) - Game information, technology, and legacy-world framing for long-term character play

### AI Image Generation Integration (Gemini + Vertex)
- **Dashboard panel**: `/admin/content/dungeoncrawler` includes an **AI Image Generation (Gemini + Vertex)** panel.
- **Image generation interface**: `/admin/content/dungeoncrawler/image-generation` provides a dedicated prompt → image UI with inline preview.
- **Legacy URL compatibility**: `/admin/content/dungeoncrawler/gemini-image` remains available and maps to the same interface.
- **Provider-aware request form**: Captures provider (Gemini/Vertex), prompt, style, aspect ratio, negative prompt, and campaign context.
- **Integration layer**: `dungeoncrawler_content.image_generation_integration` routes generation requests to provider services.
- **Provider services**:
   - `dungeoncrawler_content.gemini_image_generator`
   - `dungeoncrawler_content.vertex_image_generator`
- **Admin settings**: Configure under `/admin/config/content/dungeoncrawler`:
   - Default provider (`generated_image_provider`)
   - Gemini live settings + key (`GEMINI_API_KEY` or config)
   - Gemini system context prompt (`gemini_system_context_prompt`) for automatic prompt wrapping
   - Vertex live settings + key (`VERTEX_API_KEY` or config)
- **Operator setup aid**: Dashboard includes copy-ready Linux/Apache env export snippet for both providers plus reload/cache steps.
- **Storage design reference**: See `GENERATED_IMAGE_STORAGE_DESIGN.md` for object-table review and proposed generated-image persistence model.
- **Phase 1 storage tables**: `dc_generated_images` (asset metadata) and `dc_generated_image_links` (object-slot links) via update hook `10010`.
- **Storage write behavior**: Base64 image output is written to `public://generated-images/YYYY/MM` when available; environments without writable public files fallback to `temporary://generated-images/YYYY/MM` so persistence still succeeds.
- **Phase 1 read APIs**:
   - `GET /api/image/{image_uuid}`
   - `GET /api/images/object/{table_name}/{object_id}?campaign_id=&slot=&variant=`
   - `GET /api/campaign/{campaign_id}/images?table_name=&object_id=&slot=`
- **Canonical prompt reference**: `GEMINI_IMAGE_PROMPTS.md` in this module root contains the system prompt + runtime payload template for hexmap token generation.

### Navigation Structure

#### Main Navigation Menu
Located in `navbar_left` region. Menu items (in order):
1. **Play** - Homepage/game start
2. **Characters** - Character management (`/characters`)
3. **World** - Lore and world information (`/world`)
4. **How to Play** - Game mechanics guide (`/how-to-play`)
5. **About** - About the game (`/about`)
6. **DC Administration** - Admin navigation group for Dungeon Crawler management routes (includes **Game Dashboard**, **Game Objects**, **Image Generation**, **Dungeon Settings**, **Testing Dashboard**, **Controller Architecture**, and **Encounter AI Integration**)

#### Footer Menu
Located in `footer` region. Menu items (in order):
1. **About** - Game information
2. **How to Play** - Tutorial
3. **World Lore** - Dungeon background
4. **Privacy Policy** - Privacy information
5. **Terms of Service** - Terms and conditions

### Services

#### Character Manager
**Service ID**: `dungeoncrawler_content.character_manager`  
**Class**: `Drupal\dungeoncrawler_content\Service\CharacterManager`

Handles all character-related database operations:
- Character creation with UUID generation
- Character retrieval by ID and user
- Character updates
- Character deletion
- List characters by user

#### Game Content Manager
**Service ID**: `dungeoncrawler_content.game_manager`  
**Class**: `Drupal\dungeoncrawler_content\Service\GameContentManager`

Manages game content and procedural generation integration.

#### Number Generation Service
**Service ID**: `dungeoncrawler_content.number_generation`  
**Class**: `Drupal\dungeoncrawler_content\Service\NumberGenerationService`

Provides Pathfinder-compatible dice and number generation:
- Pathfinder dice: `d4`, `d6`, `d8`, `d10`, `d12`, `d20`, `d100`
- Percentile roll helper (`1-100`)
- Generic `1-100` die-side support for multiple dice
- Dice notation parsing for formats like `1d20`, `2d6+3`, `4d8-1`

## File Structure

```
dungeoncrawler_content/
├── config/
│   ├── examples/          # Configuration examples
│   └── schemas/           # Configuration schemas
├── css/
│   ├── character-sheet.css
│   ├── dungeoncrawler-content.css
│   ├── game-cards.css
│   └── hexmap.css         # Hex map display styles (refactored with design tokens)
├── js/
│   ├── character-sheet.js
│   └── game-cards.js
├── src/
│   ├── Controller/
│   │   ├── AboutController.php
│   │   ├── CampaignController.php
│   │   ├── CharacterListController.php
│   │   ├── CharacterViewController.php
│   │   ├── DashboardController.php
│   │   ├── GameObjectsController.php
│   │   ├── HowToPlayController.php
│   │   └── WorldController.php
│   ├── Form/
│   │   ├── CampaignCreateForm.php
│   │   ├── CharacterCreateForm.php
│   │   ├── CharacterDeleteForm.php
│   │   ├── DungeonCrawlerSettingsForm.php
│   │   └── GeminiImageGenerationStubForm.php
│   └── Service/
│       ├── CharacterManager.php
│       ├── GameContentManager.php
│       └── GeminiImageGenerationService.php
├── templates/
│   ├── character-class-card.html.twig
│   ├── character-list.html.twig
│   ├── character-sheet.html.twig
│   ├── dungeon-card.html.twig
│   └── item-card.html.twig
├── dungeoncrawler_content.info.yml
├── dungeoncrawler_content.install
├── dungeoncrawler_content.libraries.yml
├── dungeoncrawler_content.links.menu.yml
├── dungeoncrawler_content.module
├── dungeoncrawler_content.routing.yml
├── dungeoncrawler_content.services.yml
└── README.md
```

## Installation

1. Enable the module:
   ```bash
   cd /home/keithaumiller/forseti.life/sites/dungeoncrawler
   ./vendor/bin/drush en dungeoncrawler_content -y
   ```

2. Clear cache:
   ```bash
   ./vendor/bin/drush cr
   ```

3. Verify blocks are placed:
   - Navigate to `/admin/structure/block`
   - Confirm "Main navigation" is in "Navbar left" region
   - Confirm "Footer menu" is in "Footer" region

## Configuration

### Module Settings
Access at: `/admin/config/content/dungeoncrawler`

Settings include:
- AI generation parameters
- Game mechanics configuration
- Character creation options

### Menu Management
- **Main Menu**: `/admin/structure/menu/manage/main`
- **Footer Menu**: `/admin/structure/menu/manage/footer`

Menu links are automatically created by the module via `dungeoncrawler_content.links.menu.yml`.

## Dependencies

- drupal:node
- drupal:field
- drupal:text
- drupal:image
- drupal:link
- drupal:menu_ui
- drupal:taxonomy

## Theme Integration

This module integrates with the **dungeoncrawler** theme. The theme provides:
- Block placements for navigation and footer
- Custom templates for game content
- Bootstrap 5 styling for cards and forms

### Block Configuration
Theme blocks (in `/themes/custom/dungeoncrawler/config/optional/`):
- `block.block.dungeoncrawler_main_menu.yml` - Main navigation (navbar_left)
- `block.block.dungeoncrawler_footer.yml` - Footer menu (footer)

Both blocks are configured as `status: true` and will be automatically placed when the theme is enabled.

## Database Schema

### Data Modeling Approach (Canonical)

This module uses a **metadata-driven hybrid model** (EAV-inspired, not strict row-per-attribute EAV):

- **Template Objects**: Reusable content definitions (`dungeoncrawler_content_*` tables).
- **Active Campaign Objects**: Runtime state tied to `campaign_id` (`dc_campaigns` and all `dc_campaign_*` tables).
- **Fact Objects**: Durable reference/source-of-truth records reused across templates and campaigns (stored in `dc_campaign_characters` with `campaign_id = 0`).

#### Character Table Roles

- **`dc_campaign_characters` (Fact + Active Campaign)**: Unified table for canonical character library/source records and campaign-scoped runtime instances.
- **`dungeoncrawler_content_characters` (Template)**: Reusable character template mappings used by template import/pairing.

This enables promoting a campaign character into reusable forms:
- To a durable character record (`dc_campaign_characters` where `campaign_id = 0`) for future campaigns.
- To template-layer mappings (`dungeoncrawler_content_characters`) for template workflows.

### Character Table: `dc_campaign_characters` (Unified)
- `id` (int, primary key) - Character ID
- `uuid` (varchar) - Unique character UUID (canonical/library rows)
- `uid` (int) - Owner user ID
- `campaign_id` (int) - `0` for library records, `>0` for campaign-scoped runtime rows
- `character_id` (int) - Optional link/reference for campaign instances
- `instance_id` (varchar) - Runtime instance identifier (campaign-scoped uniqueness)
- `name` (varchar) - Character name
- `class` (varchar) - Character class
- `ancestry` (varchar) - Character ancestry
- `level` (int) - Character level
- `character_data` (text) - JSON-encoded character data
- `state_data` (text) - Campaign runtime state JSON payload
- **Hot gameplay columns (hybrid model):** `hp_current`, `hp_max`, `armor_class`, `experience_points`, `position_q`, `position_r`, `last_room_id`
- `created` (int) - Creation timestamp
- `changed` (int) - Last modified timestamp

### Hybrid Columnar Storage
- Use relational columns for high-frequency gameplay reads/writes (HP, AC, XP, position).
- Keep `character_data`/`state_data` JSON for flexible or lower-frequency payloads (inventory, appearance, buffs, and nested schema structures).
- Character creation and campaign-selection flows now populate both hot columns and JSON payloads.

### Character Template Table: `dungeoncrawler_content_characters`
- `character_id`, `instance_id`, `uid`
- `type`, `role`, `is_active`
- `state_data`, `location_type`, `location_ref`

## Routes

### Public Routes
- `/` - Homepage (Play)
- `/world` - World lore page
- `/how-to-play` - Tutorial and game guide
- `/about` - About page

### Authenticated Routes
- `/hexmap` - Hex map page with campaign-backed combat API integration when launched with campaign context
- `/characters` - Character list
- `/characters/create` - Character creation
- `/characters/{id}` - Character sheet
- `/characters/{id}/edit` - Edit character
- `/characters/{id}/delete` - Delete character
- `/campaigns` - Campaign list
- `/campaigns/create` - Campaign creation
- `/campaigns/{campaign_id}/archive` - Campaign archive confirmation (checkbox)
- `/campaigns/{campaign_id}/unarchive` - Campaign unarchive confirmation
- `/campaigns/{campaign_id}/dungeons` - Campaign dungeon selection page
- `/campaigns/{campaign_id}/select-character/{character_id}` - Select character for campaign

### Admin Routes
- `/admin/config/content/dungeoncrawler` - Module settings
- `/admin/content/dungeoncrawler` - Game content dashboard

### Management Routes
- `/dungeoncrawler/objects` - Game object manager (object/attribute review)

### Architecture Routes
- `/architecture/controllers` - Controller architecture reference page
- `/architecture/encounter-ai-integration` - Encounter AI integration blueprint status page

## Permissions

The module defines a hierarchical permission system for character and content management:

### Admin Permissions (restrict access: true)
These permissions grant full control and should only be assigned to administrators:

- **`administer dungeoncrawler content`** - Full administrative access to game content, settings, and all characters regardless of ownership. Required for module configuration (`/admin/config/content/dungeoncrawler`) and testing dashboard (`/admin/dungeoncrawler/testing`).
- **`edit any dungeoncrawler characters`** - Edit any character regardless of ownership. Allows administrators to modify characters for support or testing purposes.
- **`delete any dungeoncrawler characters`** - Delete any character regardless of ownership. Use with caution as this allows permanent removal of user data.

### User Permissions (restrict access: false)
Standard permissions for authenticated users to access and manage their own content:

- **`access dungeoncrawler characters`** - View character lists, campaigns, and access game features. Required for basic gameplay and character management. Does not grant access to other users' characters.

### Hexmap Combat API Context
- Server-authoritative combat endpoints (`/api/combat/*`) require authenticated gameplay context and `access dungeoncrawler characters` permission.
- Hexmap client only auto-starts server combat when a valid `campaign_id` launch context is present.
- Direct `/hexmap` demo access without campaign context remains available for map/UI exploration, but does not auto-initiate server combat encounters.
- Hexmap action rail includes streamlined player actions (`Move`, `Attack`, `Interact`, `Talk`, `End Turn`) with disabled-state semantics synchronized to turn/action availability.
- Interact mode supports adjacent object interactions (door-like obstacle opening, movable obstacle push/reposition, blocked room-connection opening) using 1-action economy rules.
- `/api/combat/action` is backend-authoritative for non-attack actions: only `interact` and `talk` are accepted, with server-enforced costs (`interact=1`, `talk=0`) regardless of client payload.
- Successful interact actions now persist canonical world mutations into campaign dungeon payload (`dc_campaign_dungeons.dungeon_data`) and return a `world_delta` consumed by the hexmap client.
- Fog-of-war can be toggled in the header controls; visibility uses player-centered vision range plus line-of-sight checks against impassable obstacles.
- Read-only encounter AI recommendation preview endpoint (`POST /api/combat/recommendation-preview`) is admin-only and does not mutate encounter state.
- NPC auto-play can use validated encounter AI recommendations only when `encounter_ai_npc_autoplay_enabled` is enabled in Dungeon Settings; default remains deterministic fallback behavior.
- Encounter narration timeline logging is available when `encounter_ai_narration_enabled` is enabled; narration events are persisted as `ai_narration` rows in `combat_actions`.

### Mechanics Review Notes (Phase 4: Deterministic Dungeon Targeting)
- `Interact`/`Talk` are validated server-side for turn ownership and action economy; interact world changes are now persisted server-side and mirrored via `world_delta`.
- **Phase 4 Complete (2026-02-19):** Encounter `map_id` linkage is now captured from `startCombat` API response and passed to all combat action payloads. The `/api/combat/action` handler uses deterministic `(campaign_id, dungeon_id=mapId)` compound key for dungeon-row mutations when available, with fallback to most-recent when `mapId` is unavailable.
- Frontend captures `map_id` from `startCombat` response and stores in `stateManager`, ensuring all subsequent action payloads include the correct dungeon identifier.
- Deterministic targeting eliminates the prior constraint where multiple active dungeons per campaign could cause mutations to target the wrong row.
- **`create dungeoncrawler characters`** - Create new characters in the character creation wizard. Includes access to character step forms and save operations.
- **`edit own dungeoncrawler characters`** - Edit own characters. Access is further restricted by the `CharacterAccessCheck` service to ensure users can only modify their own characters.
- **`delete own dungeoncrawler characters`** - Delete own characters. Access is further restricted by the `CharacterAccessCheck` service to prevent deletion of characters owned by other users.

### External Permissions
Required from core Drupal or other modules:

- **`access content overview`** - Required for dashboard access at `/admin/content/dungeoncrawler`
- **`administer site configuration`** - Required for module settings at `/admin/config/content/dungeoncrawler`

### Access Control Services
The module implements custom access checkers for ownership-based permissions:

- **`CharacterAccessCheck`** - Validates character ownership for view/edit/delete operations
- **`CampaignAccessCheck`** - Validates campaign ownership for campaign-related operations

These services work in conjunction with the permission system to ensure users can only access their own resources, unless they have admin permissions.

## Styling

The module includes CSS libraries:
1. **dungeoncrawler-content** - Base module styles
2. **game-cards** - Card-based UI components (refactored 2026-02-17)
3. **character-sheet** - Character sheet display
4. **character-step-base** - Shared character creation step library (refactored 2026-02-17)
5. **hexmap** - Hex-based game map with PixiJS rendering
6. **credits** - Credits page styling

### Libraries Architecture (DCC-0042)

The `dungeoncrawler_content.libraries.yml` file was refactored on 2026-02-17 to eliminate duplication and improve performance:

- **character-step-base**: New base library containing shared `character-steps.css` and dependencies
- All 8 character step libraries (`character-step-1` through `character-step-8`) now depend on `character-step-base`
- Eliminated 8 duplicate CSS file references (reduced from 8x loading to 1x loading)
- Removed 56 lines of repeated dependencies
- ES6 modules in hexmap properly configured with `type: module` (automatically deferred by browsers)
- Standardized CSS categories to `theme` for module-specific stylesheets

### Character Steps CSS

The `character-steps.css` file uses CSS custom properties (variables) for consistent styling:
- **Colors**: `--dc-primary`, `--dc-secondary`, `--dc-danger`, `--dc-warning`, `--dc-success`
- **Spacing**: `--dc-space-xs` through `--dc-space-xl`
- **Border Radius**: `--dc-radius-sm` through `--dc-radius-xl`
- **Transitions**: `--dc-transition`, `--dc-transition-fast`

Legacy class names maintained for backward compatibility:
- `.backgrounds-grid` → use `.background-grid`
- `.classes-grid` → use `.class-grid`
- `.abilities-grid` → use `.ability-grid`

All use Bootstrap 5 dark theme styling with fantasy RPG aesthetics. The hexmap.css file has been refactored to use a comprehensive design token system including colors, spacing (8-point grid), typography, shadows, and animations.

### character-step-1.js Improvements (DCC-0055)

The `character-step-1.js` file has been refactored to improve code quality and maintainability:

- **Configuration Constants**: Extracted magic strings and values into CONFIG object (minimum name length, error messages, button text)
- **JSDoc Documentation**: Added comprehensive function documentation for better code understanding
- **Helper Functions**: Extracted reusable functions (isValidName, updateSubmitButton, handleAjaxError) 
- **Defensive Programming**: Added null checks and guard clauses for missing DOM elements
- **Improved Error Handling**: Consistent with other character steps, includes server error message extraction
- **Better AJAX Handling**: Added `dataType: 'json'` and safer response validation
- **Context-aware DOM Selection**: Fixed jQuery selectors to use context parameter for better Drupal integration

The refactoring maintains 100% functional compatibility while improving:
- Code readability and maintainability
- Error handling consistency across character creation steps
- Documentation for future developers
- Defensive programming practices

### character-step-3.js Improvements (DCC-0231)

The `character-step-3.js` file has been refactored to align with patterns established in step-1 and step-2, addressing schema conformance and code quality issues:

- **Configuration Constants**: Extracted magic strings and values into comprehensive CONFIG object (maxBoosts, selectors, cssClasses, messages, buttonText)
- **State Management**: Replaced global variables (`selectedBackground`, `selectedBoosts`) with local state object for better encapsulation
- **JSDoc Documentation**: Added comprehensive function documentation for all functions with parameter and return type information
- **Helper Functions**: Extracted validation logic into `validateForm()` and error handling into `handleAjaxError()` functions
- **Defensive Programming**: Added guard clauses for missing DOM elements and null checks in all helper functions
- **Consistent Error Handling**: Aligned with step-1 and step-2 patterns, including server error message extraction from `xhr.responseJSON`
- **Better AJAX Handling**: Added `dataType: 'json'`, safer response validation, and proper state management
- **Form Initialization**: Added guard clause and proper element validation before attaching event handlers

**Schema Conformance Notes**:
The refactoring ensures alignment with the module's unified JSON/hot-column architecture:
- Hot columns (`hp_current`, `hp_max`, `armor_class`, etc.) for high-frequency gameplay queries
- JSON payloads (`character_data`, `state_data`) for flexible character details
- Background and ability boost selections feed into `character_data` JSON structure during character creation

The refactoring maintains 100% functional compatibility while improving:
- Code consistency across all character creation steps
- Maintainability through better organization and documentation
- Error handling and defensive programming
- State management and encapsulation
- Alignment with established module patterns

### game-cards.css Improvements (DCC-0038)

The `game-cards.css` file has been refactored to improve maintainability and consistency:

- **CSS Custom Properties**: All colors and dimensions now use CSS variables (`:root` namespace)
- **Reduced Duplication**: Common card styles consolidated into shared base classes
- **Better Organization**: Clear section headers and logical grouping
- **Enhanced Documentation**: Comprehensive comments explaining each component
- **Theme Alignment**: Variables match SCSS theme variables in `_variables.scss`

The refactoring maintains 100% visual consistency while improving code quality:
- 19 CSS custom properties for colors and dimensions
- 35 rule blocks organized by component type
- Shared base styles reduce duplication by ~90 lines
- All hardcoded colors replaced with semantic variable names

### game-cards.js Schema Conformance (DCC-0253)

The `game-cards.js` file has been reviewed and updated for schema conformance and documentation accuracy:

- **Schema Documentation**: Enhanced file header now clearly documents the data flow from database to UI
- **Architecture Alignment**: Documentation explicitly references the module's unified JSON/hot-column architecture
- **Data Flow Clarity**: Added numbered steps explaining how rarity data flows from `dungeoncrawler_content_registry.rarity` through templates to JavaScript
- **Pattern Consistency**: File header now matches documentation patterns established in `character-sheet.js`

**Key clarifications**:
- Rarity data originates from `dungeoncrawler_content_registry.rarity` field (varchar, values: common, uncommon, rare, epic, legendary)
- Templates pre-render rarity as CSS class modifiers (`item-card--{rarity}`)
- JavaScript provides enhanced interactive effects (legendary glow on hover), not database queries
- This follows the module's pattern where frequently-accessed display properties are template-rendered rather than client-side queried

The review maintains 100% functional compatibility while improving:
- Documentation accuracy regarding database schema references
- Clarity on the relationship between database, templates, CSS, and JavaScript
- Alignment with module-wide architectural patterns
- Developer understanding of the complete data flow

## Development

### Architecture and Design Docs

- `HEXMAP_ARCHITECTURE.md` - Hexmap and schema architecture reference
- `ENHANCED_CHARACTER_SHEET_STUBS.md` - Character sheet implementation status and gaps
- `AI_ENCOUNTER_INTEGRATION.md` - Encounter AI integration blueprint and phased implementation plan

### Encounter AI Services (Phase 1)

- `EncounterAiProviderInterface` defines provider contract for NPC recommendation and narration.
- `AiConversationEncounterAiProvider` routes encounter recommendation/narration calls through `ai_conversation.ai_api_service` (`AIApiService::invokeModelDirect`).
- `StubEncounterAiProvider` remains registered as deterministic fallback when model calls fail or return invalid payloads.
- `EncounterAiIntegrationService` builds context, requests recommendation, and validates recommendation against turn/action constraints.

### Encounter AI Integration (Phase 2 Guarded)

- `CombatEncounterApiController` NPC turn loop now supports AI-driven recommendation execution behind config gate `encounter_ai_npc_autoplay_enabled`.
- Invalid/unavailable recommendation paths use deterministic fallback targeting (first alive player).
- Toggle is available at `/admin/config/content/dungeoncrawler` under AI Generation Settings.
- Bedrock invocation resilience is configurable with `encounter_ai_retry_attempts`, `encounter_ai_recommendation_max_tokens`, and `encounter_ai_narration_max_tokens`.

### Encounter Narration Integration (Phase 3 Guarded)

- NPC turn loop can persist narration timeline events when `encounter_ai_narration_enabled` is enabled.
- Narration entries are stored in `combat_actions` with `action_type` set to `ai_narration`.
- Payload includes provider narration content and request metadata for audit/debug context.

### Encounter AI Observability (Phase 4)

- `/architecture/encounter-ai-integration` now includes a last-24h operational metrics panel sourced from `ai_conversation_api_usage`.
- Metrics include tracked requests, tracked attempts, average attempts per request, fallback rate, and recommendation/narration request split.
- Encounter AI requests now emit a shared `request_id` across retries so request-level fallback and attempt aggregation is deterministic.
- Metrics panel and CSV export support query windows via `?window=24h|7d|30d`.
- Export endpoint: `/architecture/encounter-ai-integration/metrics.csv?window=24h` (or `7d`, `30d`).


### Adding New Routes
1. Define route in `dungeoncrawler_content.routing.yml`
2. Create controller in `src/Controller/`
3. Add menu link in `dungeoncrawler_content.links.menu.yml` (if needed)

### Creating New Services
1. Define service in `dungeoncrawler_content.services.yml`
   - Add to appropriate section (Content Management, Access Control, Combat, etc.)
   - Use proper dependency injection for all dependencies
   - Follow YAML formatting standards (proper spacing, multi-line for 3+ arguments)
2. Create class in `src/Service/` or `src/Access/`
   - Inject dependencies via constructor
   - Avoid using `\Drupal::` static calls
3. Use dependency injection in controllers/forms

**Service File Structure**:
- Header comment explaining purpose and linking to Drupal docs
- Grouped sections: Content Management, Access Control, Game Content, Combat, Campaign State
- Proper YAML formatting with consistent indentation

### Custom Templates
Place in `templates/` directory following Drupal naming conventions.

## Troubleshooting

### Menu Items Not Appearing
```bash
# Rebuild menu cache
./vendor/bin/drush cr menu

# Verify module is enabled
./vendor/bin/drush pm:list | grep dungeoncrawler_content
```

### Blocks Not Showing
```bash
# Check block placement
./vendor/bin/drush config:get block.block.dungeoncrawler_main_menu region
./vendor/bin/drush config:get block.block.dungeoncrawler_footer region

# Verify blocks are enabled
./vendor/bin/drush config:get block.block.dungeoncrawler_main_menu status
./vendor/bin/drush config:get block.block.dungeoncrawler_footer status
```

### Character Database Issues
```bash
# Check if character table exists (unified table for library and campaign characters)
./vendor/bin/drush sqlq "SHOW TABLES LIKE 'dc_campaign_characters';"

# Reinstall module schema (WARNING: This will delete all character data)
./vendor/bin/drush sql:query "DROP TABLE IF EXISTS dc_campaign_characters;"
./vendor/bin/drush en dungeoncrawler_content -y
```

## Test Coverage

### Overview

The dungeoncrawler_content module maintains comprehensive test coverage across unit, kernel, and functional test suites. All tests follow Drupal testing standards and include both positive (happy path) and negative (error/deny) scenarios.

### Running Tests

```bash
cd sites/dungeoncrawler
./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml

# Run specific test suite
./vendor/bin/phpunit --testsuite=functional
./vendor/bin/phpunit --testsuite=unit
./vendor/bin/phpunit --testsuite=kernel

# Run specific test file
./vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Functional/Routes/CampaignRoutesTest.php
```

### Hexmap UI Review Harness (Playwright Screenshots)

Use this workflow to generate repeatable desktop/mobile screenshots for `/hexmap` UI reviews and before/after comparisons.

```bash
# From repository root
cd /home/keithaumiller/forseti.life

# Install JS dependencies and Chromium once
npm install
npm run hexmap:review:install

# Clear Drupal cache before each UI review pass (from site root)
cd sites/dungeoncrawler
./vendor/bin/drush cr

# Capture default review state (from repository root)
cd /home/keithaumiller/forseti.life
npm run hexmap:review
```

Default capture target is:

```text
http://penguin.linux.test:8080/hexmap?campaign_id=2&character_id=2&dungeon_level_id=f8c6b8f1-2df9-469f-9fd5-67a59f120001&map_id=0b7e3d2f-8f7c-4ae0-8f72-9e99e0800001&room_id=7f2f1051-5f88-45a2-a66a-0f7063900001&next_room_id=7f2f1051-5f88-45a2-a66a-0f7063900002&start_q=0&start_r=0
```

Override target URL when needed:

```bash
HEXMAP_REVIEW_URL='http://penguin.linux.test:8080/hexmap?...' npm run hexmap:review
```

Artifacts are written to:

- `testing/results/hexmap-ui-review/<timestamp>/desktop-1440x900.png`
- `testing/results/hexmap-ui-review/<timestamp>/mobile-pixel-7.png`
- `testing/results/hexmap-ui-review/<timestamp>/summary.json`

Each capture also writes per-viewport JSON metadata (final URL, page title, browser console/page errors) to support control-behavior debugging.

### Test Inventory

#### Unit Tests
Located in `tests/src/Unit/`

| Test Class | Coverage | Status |
|------------|----------|--------|
| `CharacterCalculatorTest` | Character stat calculations, HP, AC, modifiers | ✅ Complete |
| `CombatCalculatorTest` | Combat mechanics, attack rolls, damage | ✅ Complete |

**Focus**: Business logic, calculations, and service layer functionality without Drupal bootstrap.

#### Functional Tests - Routes
Located in `tests/src/Functional/Routes/`

| Test Class | Positive Tests | Negative Tests | Total |
|------------|----------------|----------------|-------|
| `CampaignRoutesTest` | 5 | 7 | 12 |
| `CharacterRoutesTest` | 6 | 8 | 14 |
| `AdminRoutesTest` | 2 | 5 | 7 |
| `ApiRoutesTest` | 10 | 10 | 20 |
| `PublicRoutesTest` | 6 | 1 | 7 |
| `DemoRoutesTest` | 1 | 1 | 2 |

**Total Route Tests**: 62 methods

**Coverage includes:**
- ✅ Permission-based access control (403 Forbidden)
- ✅ Invalid resource IDs (404 Not Found)
- ✅ Wrong HTTP methods (405 Method Not Allowed)
- ✅ Anonymous user access denial
- ✅ Authenticated user success paths

#### Functional Tests - Controllers
Located in `tests/src/Functional/Controller/`

| Test Class | Positive Tests | Negative Tests | Focus Area |
|------------|----------------|----------------|------------|
| `CampaignControllerTest` | 2 | 6 | Campaign ownership & access |
| `CharacterViewControllerTest` | 1 | 2 | Character viewing permissions |
| `CharacterListControllerTest` | 1 | 1 | Character listing |
| `DashboardControllerTest` | 1 | 2 | Admin dashboard access |
| `HomeControllerTest` | 1 | 0 | Public homepage |
| `AboutControllerTest` | 1 | 0 | Public about page |
| `WorldControllerTest` | 1 | 0 | Public world lore |
| `HowToPlayControllerTest` | 1 | 0 | Public tutorial |
| `CreditsControllerTest` | 1 | 0 | Public credits |

**Total Controller Tests**: 40+ methods

**Coverage includes:**
- ✅ Resource ownership validation
- ✅ Cross-user access prevention
- ✅ Non-existent resource handling
- ✅ Permission boundary enforcement

#### Functional Tests - Advanced
Located in `tests/src/Functional/`

| Test Class | Methods | Purpose |
|------------|---------|---------|
| `CampaignStateAccessTest` | 3 | Campaign state API ownership (owner, non-owner, admin) |
| `CampaignStateValidationTest` | Multiple | State schema validation, version control |
| `EntityLifecycleTest` | Multiple | Entity spawn/move/despawn with validation |
| `CharacterCreationWorkflowTest` | Multiple | End-to-end character creation flow |

**Focus**: Complex workflows, state management, and integration scenarios.

### Coverage by Concern

#### ✅ Authorization & Ownership
- Campaign ownership checks (tavern entrance, select character)
- Character ownership checks (view, edit, delete)
- API endpoint ownership validation (load, state, summary)
- Admin permission boundaries (dashboard, settings)
- Cross-user access denial scenarios

#### ✅ HTTP Status Codes
- **200 OK**: Successful operations
- **403 Forbidden**: Permission denied, ownership violations
- **404 Not Found**: Invalid IDs, non-existent resources
- **405 Method Not Allowed**: Wrong HTTP methods on API endpoints

#### ✅ Edge Cases & Negative Flows
- Non-existent campaign/character IDs
- Invalid (non-numeric) ID parameters
- Missing required permissions
- Anonymous user access attempts
- Selecting other user's characters for campaigns
- Editing/deleting other user's characters
- API operations on other user's resources

#### ✅ API Method Enforcement
All POST-only API endpoints verified to reject GET requests with 405:
- `/api/character/save` (POST only)
- `/api/character/{id}/update` (POST only)
- `/api/combat/start` (POST only)
- `/api/combat/end-turn` (POST only)
- `/api/combat/end` (POST only)
- `/api/combat/attack` (POST only)

All GET-only API endpoints verified to reject POST requests with 405:
- `/api/character/load/{id}` (GET only)
- `/api/character/{id}/state` (GET only)
- `/api/character/{id}/summary` (GET only)

### Test Patterns & Conventions

#### Positive Test Pattern
```php
public function testFeaturePositive(): void {
  $user = $this->drupalCreateUser(['required permission']);
  $this->drupalLogin($user);
  
  $this->drupalGet('/route');
  $this->assertSession()->statusCodeEquals(200);
  $this->assertSession()->pageTextContains('Expected Content');
}
```

#### Negative Test Pattern - Permission Denied
```php
public function testFeatureNegativeNoPermission(): void {
  $user = $this->drupalCreateUser([]); // No permissions
  $this->drupalLogin($user);
  
  $this->drupalGet('/route');
  $this->assertSession()->statusCodeEquals(403);
}
```

#### Negative Test Pattern - Ownership Violation
```php
public function testFeatureOwnershipDenied(): void {
  $owner = $this->drupalCreateUser(['permission']);
  $other_user = $this->drupalCreateUser(['permission']);
  
  // Create resource owned by $owner
  $resource_id = $this->createResource($owner);
  
  // Try to access as $other_user
  $this->drupalLogin($other_user);
  $this->drupalGet("/resource/{$resource_id}");
  $this->assertSession()->statusCodeEquals(403);
}
```

### Coverage Metrics

| Test Type | Count | Coverage Area |
|-----------|-------|---------------|
| **Route Tests** | 62 | HTTP routing, permissions, method validation |
| **Controller Tests** | 40+ | Business logic, access control, data flow |
| **Advanced Tests** | 20+ | Workflows, state management, integration |
| **Unit Tests** | 15+ | Calculations, services, utilities |
| **Total Tests** | 137+ | Comprehensive module coverage |

### Test Groups

Tests are organized with PHPUnit groups for selective execution:

```bash
# Run specific groups
./vendor/bin/phpunit --group routes
./vendor/bin/phpunit --group controller
./vendor/bin/phpunit --group api
./vendor/bin/phpunit --group character-creation
```

Available groups:
- `dungeoncrawler_content` - All module tests
- `routes` - Route configuration tests
- `controller` - Controller functionality tests
- `api` - API endpoint tests
- `character-creation` - Character creation workflow
- `pf2e-rules` - Pathfinder 2e rules validation

### Continuous Integration

All tests run automatically on:
- Pull requests to `main` and `develop` branches
- Direct pushes to protected branches
- Manual workflow triggers via GitHub Actions

Test results are visible in the GitHub Actions tab of each PR.

## Future Enhancements

- [ ] Procedural dungeon generation integration
- [ ] AI-powered NPC dialogue system
- [ ] Real-time combat mechanics
- [ ] Multiplayer party system
- [ ] Achievement and quest tracking
- [ ] Inventory management system
- [ ] Spell and ability customization

## Support

For issues or questions:
1. Check Drupal logs: `/admin/reports/dblog`
2. Review module configuration: `/admin/config/content/dungeoncrawler`
3. Verify database schema is properly installed

## License

Proprietary - Dungeon Crawler Life
