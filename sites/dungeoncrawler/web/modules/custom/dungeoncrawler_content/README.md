# Dungeon Crawler Content Module

**Module Name**: dungeoncrawler_content  
**Version**: 1.0  
**Drupal**: 11.x  
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
│   └── game-cards.css
├── js/
│   ├── character-sheet.js
│   └── game-cards.js
├── src/
│   ├── Controller/
│   │   ├── AboutController.php
│   │   ├── CharacterListController.php
│   │   ├── CharacterViewController.php
│   │   ├── DashboardController.php
│   │   ├── HowToPlayController.php
│   │   └── WorldController.php
│   ├── Form/
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

### Character Table: `dungeoncrawler_characters`
- `id` (int, primary key) - Character ID
- `uuid` (varchar) - Unique character UUID
- `user_id` (int) - Owner user ID
- `name` (varchar) - Character name
- `class` (varchar) - Character class
- `race` (varchar) - Character race
- `level` (int) - Character level
- `experience` (int) - Experience points
- `data` (text) - JSON-encoded character data
- `created` (int) - Creation timestamp
- `changed` (int) - Last modified timestamp

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
