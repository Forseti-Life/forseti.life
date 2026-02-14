# Dungeon Crawler Tester Module

**Module Name**: dungeoncrawler_tester  
**Purpose**: Holds the testing harness and full functional test suite for the Dungeon Crawler content module.  
**Depends on**: `dungeoncrawler_content`

## What’s inside
- PHPUnit configuration tuned for Drupal functional tests.
- Comprehensive functional test suite (routes + controllers).
- Testing module README with run commands and grouping.
- **Testing Dashboard** - A web-based dashboard for quick access to test documentation, commands, and CI status.

## Testing Dashboard

The testing dashboard provides a centralized location for developers to:
- Access all test documentation and guides
- View and copy test commands for quick execution
- Monitor CI failures and testing-related issues
- Review release testing stagegates

**Access the dashboard:**
- URL: `/dungeoncrawler/testing`
- Permission required: `administer site configuration`
- Menu location: Reports > Dungeon Crawler Testing Dashboard

The dashboard includes:
- **Test Documentation**: Links to all testing READMEs, strategy docs, and issue lists
- **Quick Test Commands**: Copy/paste commands for running different test suites
- **Release Testing Stagegates**: Testing workflow and checklist
- **GitHub Issues**: Live feed of CI failures and testing defects

## Current review status
- First-pass review completed for inventory (unit + functional suites). Functional workflow test remains stubbed.
- Follow-up issues to be opened are staged in [issues_todo.md](../../../issues_todo.md) (workflow implementation, data-backed functional assertions, negative/authorization coverage, shared builders, and content-backed smoke tests).

## Running tests
Use the PHPUnit config shipped with the module:

```bash
cd sites/dungeoncrawler
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

See [tests/TESTING_MODULE_README.md](tests/TESTING_MODULE_README.md) for commands by suite, groups, and file-level runs.

**Quick tip**: Visit the Testing Dashboard at `/dungeoncrawler/testing` for a complete list of test commands with copy/paste functionality.

## Notes
- Tests enable `dungeoncrawler_content`; this module only houses the test code and config.
- No content types, controllers, or assets are defined here—those stay in the main content module.

## File Inventory
| File | Purpose | First pass |
| --- | --- | --- |
| [README.md](README.md) | Module overview and usage notes | Reviewed |
| [dungeoncrawler_tester.info.yml](dungeoncrawler_tester.info.yml) | Module metadata and dependency on dungeoncrawler_content | Reviewed |
| [phpunit.xml](phpunit.xml) | PHPUnit configuration (suites, coverage, env) | Updated |
| [tests/README.md](tests/README.md) | Test suite structure and quick commands | Updated |
| [tests/TESTING_MODULE_README.md](tests/TESTING_MODULE_README.md) | Detailed test instructions and grouping | Updated |
| [tests/fixtures/characters/level_1_fighter.json](tests/fixtures/characters/level_1_fighter.json) | Character fixture: level 1 fighter | Updated |
| [tests/fixtures/characters/level_1_wizard.json](tests/fixtures/characters/level_1_wizard.json) | Character fixture: level 1 wizard | Updated |
| [tests/fixtures/characters/level_5_rogue.json](tests/fixtures/characters/level_5_rogue.json) | Character fixture: level 5 rogue | Updated |
| [tests/fixtures/pf2e_reference/core_mechanics.json](tests/fixtures/pf2e_reference/core_mechanics.json) | PF2e reference data | Reviewed |
| [tests/fixtures/schemas/ancestries_test.json](tests/fixtures/schemas/ancestries_test.json) | Schema fixture: ancestries | Reviewed |
| [tests/fixtures/schemas/backgrounds_test.json](tests/fixtures/schemas/backgrounds_test.json) | Schema fixture: backgrounds | Reviewed |
| [tests/fixtures/schemas/classes_test.json](tests/fixtures/schemas/classes_test.json) | Schema fixture: classes | Reviewed |
| [tests/src/Functional/CampaignStateAccessTest.php](tests/src/Functional/CampaignStateAccessTest.php) | Functional: campaign state access | Reviewed |
| [tests/src/Functional/CampaignStateValidationTest.php](tests/src/Functional/CampaignStateValidationTest.php) | Functional: campaign state validation | Reviewed |
| [tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php](tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php) | Functional: character creation workflow | Reviewed (tests incomplete) |
| [tests/src/Functional/Controller/AboutControllerTest.php](tests/src/Functional/Controller/AboutControllerTest.php) | Functional: About controller | Reviewed |
| [tests/src/Functional/Controller/CampaignControllerTest.php](tests/src/Functional/Controller/CampaignControllerTest.php) | Functional: campaign controller | Reviewed |
| [tests/src/Functional/Controller/CharacterApiControllerTest.php](tests/src/Functional/Controller/CharacterApiControllerTest.php) | Functional: character API controller | Reviewed |
| [tests/src/Functional/Controller/CharacterCreationControllerTest.php](tests/src/Functional/Controller/CharacterCreationControllerTest.php) | Functional: character creation controller | Reviewed |
| [tests/src/Functional/Controller/CharacterCreationStepControllerTest.php](tests/src/Functional/Controller/CharacterCreationStepControllerTest.php) | Functional: character creation step controller | Reviewed |
| [tests/src/Functional/Controller/CharacterListControllerTest.php](tests/src/Functional/Controller/CharacterListControllerTest.php) | Functional: character list controller | Reviewed |
| [tests/src/Functional/Controller/CharacterStateControllerTest.php](tests/src/Functional/Controller/CharacterStateControllerTest.php) | Functional: character state controller | Reviewed |
| [tests/src/Functional/Controller/CharacterViewControllerTest.php](tests/src/Functional/Controller/CharacterViewControllerTest.php) | Functional: character view controller | Reviewed |
| [tests/src/Functional/Controller/CombatActionControllerTest.php](tests/src/Functional/Controller/CombatActionControllerTest.php) | Functional: combat actions controller | Reviewed |
| [tests/src/Functional/Controller/CombatApiControllerTest.php](tests/src/Functional/Controller/CombatApiControllerTest.php) | Functional: combat API controller | Reviewed |
| [tests/src/Functional/Controller/CombatControllerTest.php](tests/src/Functional/Controller/CombatControllerTest.php) | Functional: combat controller | Reviewed |
| [tests/src/Functional/Controller/CombatEncounterApiControllerTest.php](tests/src/Functional/Controller/CombatEncounterApiControllerTest.php) | Functional: combat encounter API controller | Reviewed |
| [tests/src/Functional/Controller/CreditsControllerTest.php](tests/src/Functional/Controller/CreditsControllerTest.php) | Functional: credits controller | Reviewed |
| [tests/src/Functional/Controller/DashboardControllerTest.php](tests/src/Functional/Controller/DashboardControllerTest.php) | Functional: dashboard controller | Reviewed |
| [tests/src/Functional/Controller/DungeonControllerTest.php](tests/src/Functional/Controller/DungeonControllerTest.php) | Functional: dungeon controller | Reviewed |
| [tests/src/Functional/Controller/HexMapControllerTest.php](tests/src/Functional/Controller/HexMapControllerTest.php) | Functional: hex map controller | Reviewed |
| [tests/src/Functional/Controller/HomeControllerTest.php](tests/src/Functional/Controller/HomeControllerTest.php) | Functional: home controller | Reviewed |
| [tests/src/Functional/Controller/HowToPlayControllerTest.php](tests/src/Functional/Controller/HowToPlayControllerTest.php) | Functional: how-to-play controller | Reviewed |
| [tests/src/Functional/Controller/TestingPageControllerTest.php](tests/src/Functional/Controller/TestingPageControllerTest.php) | Functional: testing page controller | Reviewed |
| [tests/src/Functional/Controller/WorldControllerTest.php](tests/src/Functional/Controller/WorldControllerTest.php) | Functional: world controller | Reviewed |
| [tests/src/Functional/EntityLifecycleTest.php](tests/src/Functional/EntityLifecycleTest.php) | Functional: entity lifecycle | Reviewed |
| [tests/src/Functional/Routes/AdminRoutesTest.php](tests/src/Functional/Routes/AdminRoutesTest.php) | Functional: admin routes | Reviewed |
| [tests/src/Functional/Routes/ApiRoutesTest.php](tests/src/Functional/Routes/ApiRoutesTest.php) | Functional: API routes | Reviewed |
| [tests/src/Functional/Routes/CampaignRoutesTest.php](tests/src/Functional/Routes/CampaignRoutesTest.php) | Functional: campaign routes | Reviewed |
| [tests/src/Functional/Routes/CharacterRoutesTest.php](tests/src/Functional/Routes/CharacterRoutesTest.php) | Functional: character routes | Reviewed |
| [tests/src/Functional/Routes/DemoRoutesTest.php](tests/src/Functional/Routes/DemoRoutesTest.php) | Functional: demo routes | Reviewed |
| [tests/src/Functional/Routes/PublicRoutesTest.php](tests/src/Functional/Routes/PublicRoutesTest.php) | Functional: public routes | Reviewed |
| [tests/src/Unit/Service/CharacterCalculatorTest.php](tests/src/Unit/Service/CharacterCalculatorTest.php) | Unit: character calculator | Updated |
| [tests/src/Unit/Service/CombatCalculatorTest.php](tests/src/Unit/Service/CombatCalculatorTest.php) | Unit: combat calculator | Updated |
| [tests/src/Unit/Traits/FixtureLoaderTrait.php](tests/src/Unit/Traits/FixtureLoaderTrait.php) | Shared fixture helper trait | Updated |

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

The module includes three CSS libraries:
1. **dungeoncrawler-content** - Base module styles
2. **game-cards** - Card-based UI components
3. **character-sheet** - Character sheet display

All use Bootstrap 5 dark theme styling with fantasy RPG aesthetics.

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
