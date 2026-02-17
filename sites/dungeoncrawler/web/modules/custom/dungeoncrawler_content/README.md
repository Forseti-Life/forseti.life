# Dungeon Crawler Content Module

**Module Name**: dungeoncrawler_content  
**Version**: 1.0.0  
**Drupal**: 10.3+ || 11.x  
**Package**: Dungeon Crawler

## Overview

Core content module for the AI-generated living dungeon crawler RPG. Provides character management, game content types, and navigation structure for Dungeon Crawler Life.

## Features

### Character Management System
- **Character CRUD Operations**: Create, read, update, delete player characters
- **Character Service**: `dungeoncrawler_content.character_manager` - Database operations for character data
- **Character Routes**:
  - `/characters` - List all user's characters
  - `/characters/create` - Create new character
  - `/characters/{id}` - View character sheet
  - `/characters/{id}/edit` - Edit character
  - `/characters/{id}/delete` - Delete character

### Campaign Management System
- **Campaign-first entry flow**: Start adventure by creating a campaign, then select or create a character
- **Centralized page wrapper for management forms**: `management_form_page` template for themed create/edit pages
- **Tavern entrance launch flow**: After creating a campaign, route to campaign tavern entrance to select character and launch hexmap
- **Campaign Routes**:
   - `/campaigns` - List your campaigns (returning-user hub)
   - `/campaigns/create` - Create a campaign
   - `/campaigns/{campaign_id}/tavernentrance` - Select character and launch campaign into hexmap
   - `/campaigns/{campaign_id}/select-character/{character_id}` - Bind character to campaign and launch
- **Campaign Context Flow**:
   - `/characters?campaign_id={id}` switches My Characters into campaign selection mode
   - Character creation preserves `campaign_id` through step redirects

### Information Pages
- **World Lore** (`/world`) - Living dungeon background and lore
- **How to Play** (`/how-to-play`) - Game mechanics and tutorial
- **About** (`/about`) - Game information and technology

### Navigation Structure

#### Main Navigation Menu
Located in `navbar_left` region. Menu items (in order):
1. **Play** - Homepage/game start
2. **Characters** - Character management (`/characters`)
3. **World** - Lore and world information (`/world`)
4. **How to Play** - Game mechanics guide (`/how-to-play`)
5. **About** - About the game (`/about`)

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
│   │   ├── HowToPlayController.php
│   │   └── WorldController.php
│   ├── Form/
│   │   ├── CampaignCreateForm.php
│   │   ├── CharacterCreateForm.php
│   │   ├── CharacterDeleteForm.php
│   │   └── DungeonCrawlerSettingsForm.php
│   └── Service/
│       ├── CharacterManager.php
│       └── GameContentManager.php
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

### Character Table: `dc_characters`
- `id` (int, primary key) - Character ID
- `uuid` (varchar) - Unique character UUID
- `user_id` (int) - Owner user ID
- `name` (varchar) - Character name
- `class` (varchar) - Character class
- `race` (varchar) - Character race
- `level` (int) - Character level
- `experience` (int) - Experience points
- `character_data` (text) - JSON-encoded character data
- `created` (int) - Creation timestamp
- `changed` (int) - Last modified timestamp

### Campaign Table: `dc_campaigns`
- `id`, `uuid`, `uid`, `name`
- `status` (`draft`, `ready`, `active`, `completed`)
- `theme`, `difficulty`, `active_character_id`
- `campaign_data` (JSON state)
- `created`, `changed`

### Campaign Character Mapping Table: `dc_campaign_characters`
- `campaign_id`, `character_id`, `uid`
- `role`, `is_active`, `joined`

## Routes

### Public Routes
- `/` - Homepage (Play)
- `/world` - World lore page
- `/how-to-play` - Tutorial and game guide
- `/about` - About page

### Authenticated Routes
- `/characters` - Character list
- `/characters/create` - Character creation
- `/characters/{id}` - Character sheet
- `/characters/{id}/edit` - Edit character
- `/characters/{id}/delete` - Delete character
- `/campaigns` - Campaign list
- `/campaigns/create` - Campaign creation
- `/campaigns/{campaign_id}/select-character/{character_id}` - Select character for campaign

### Admin Routes
- `/admin/config/content/dungeoncrawler` - Module settings
- `/admin/content/dungeoncrawler` - Game content dashboard

## Permissions

- `access content overview` - Required for dashboard access
- `administer site configuration` - Required for settings

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

## Development

### Adding New Routes
1. Define route in `dungeoncrawler_content.routing.yml`
2. Create controller in `src/Controller/`
3. Add menu link in `dungeoncrawler_content.links.menu.yml` (if needed)

### Creating New Services
1. Define service in `dungeoncrawler_content.services.yml`
2. Create class in `src/Service/`
3. Use dependency injection in controllers/forms

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
# Check if character table exists
./vendor/bin/drush sqlq "SHOW TABLES LIKE 'dungeoncrawler_characters';"

# Reinstall module schema
./vendor/bin/drush sql:query "DROP TABLE IF EXISTS dungeoncrawler_characters;"
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

Proprietary - Forseti Life
