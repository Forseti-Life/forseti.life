# Dungeon Crawler Life Theme

**Last Updated:** June 2025

## Overview

The **Dungeon Crawler** theme is a custom Drupal 11 theme for Dungeon Crawler Life — an AI-generated, procedurally growing dungeon crawl where every room is permanent, every monster has an AI-driven personality, and adventuring parties shape a living world.

Built on the Radix base theme with Bootstrap 5, it provides a dark dungeon RPG aesthetic with torchlight gold accents.

## Game Concept

- **AI-Generated Rooms** — Rooms are procedurally generated the first time they are entered and become permanent fixtures of the world
- **Living Monsters** — Creatures have AI-powered personalities, goals, and motivations. Some respawn, some die permanently
- **Adventuring Parties** — Players form parties to explore the ever-growing dungeon together
- **Persistent World** — Every action permanently shapes the dungeon for all who follow

## Technical Details

### Base Theme
- **Base:** Radix 6.0.2
- **Drupal Version:** ^10.3 || ^11
- **Engine:** Twig
- **Type:** Custom starterkit theme

### Build System
- **Build Tool:** Webpack (via Laravel Mix)
- **CSS Preprocessor:** Sass/SCSS
- **JavaScript:** Modern ES6+ with Babel transpilation
- **Package Manager:** npm

## Installation

### Prerequisites
- Node.js and npm installed
- Radix base theme installed (`composer require drupal/radix`)

### Setup
```bash
cd sites/dungeoncrawler/web/themes/custom/dungeoncrawler
npm install
npm run build
```

### Development
```bash
npm run watch    # Watch for changes and rebuild
npm run dev      # Development build
npm run build    # Production build
```

### Enable Theme
```bash
drush theme:enable dungeoncrawler -y
drush config-set system.theme default dungeoncrawler -y
drush cr
```

## Color Palette

| Color | Hex | Usage |
|-------|-----|-------|
| Dungeon Stone | `#2d2d3d` | Card backgrounds, panels |
| Torch Gold | `#f59e0b` | Primary accent, headings |
| Mystic Purple | `#7c3aed` | Arcane/magic elements |
| Blood Red | `#dc2626` | Danger, combat, health |
| Deep Cavern | `#1a1a2e` | Body background |
| Abyss Dark | `#0f0f1a` | Deepest dark, navbar |
| Parchment | `#fef3c7` | Scroll/parchment elements |

### Rarity Colors
| Rarity | Color |
|--------|-------|
| Common | `#9ca3af` |
| Uncommon | `#22c55e` |
| Rare | `#3b82f6` |
| Epic | `#a855f7` |
| Legendary | `#f59e0b` |

## Theme Structure

```
dungeoncrawler/
├── dungeoncrawler.info.yml       # Theme configuration
├── dungeoncrawler.theme          # PHP theme hooks
├── dungeoncrawler.libraries.yml  # Asset libraries
├── dungeoncrawler.breakpoints.yml
├── manifest.json
├── src/
│   └── scss/
│       └── base/
│           └── _variables.scss   # RPG color variables
├── templates/
│   ├── page/                     # Page templates (front, 403, 404)
│   ├── user/                     # Login, register, password, profile
│   ├── block/                    # Footer, navigation blocks
│   ├── content/                  # Node templates
│   ├── form/                     # Form element overrides
│   ├── navigation/               # Breadcrumbs, menus, pagination
│   ├── system/                   # html.html.twig
│   └── webform/                  # Contact form
├── images/
│   └── logos/                    # Brand logos & favicons
└── build/                        # Compiled assets
```

## Regions

| Region | Description |
|--------|-------------|
| `navbar_branding` | Site logo and name |
| `navbar_left` | Left navigation menu |
| `navbar_right` | Right navigation (login/account) |
| `header` | Page header area |
| `breadcrumb` | Breadcrumb navigation |
| `tabs` | Local task tabs |
| `content` | Main page content |
| `footer` | Site footer |

## Typography

- **Headings:** Cinzel (serif, RPG aesthetic)
- **Body:** Inter (clean, readable)

## License

Proprietary - Dungeon Crawler Life
