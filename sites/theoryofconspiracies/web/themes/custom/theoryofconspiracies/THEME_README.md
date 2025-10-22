# Theory of Conspiracies Custom Theme

This custom Drupal theme is based on the St. Louis Integration theme and uses the Radix base theme with Bootstrap 5.

## Overview

The **Theory of Conspiracies** theme is a custom theme designed specifically for the Theory of Conspiracies website. It was created by copying and modifying the St. Louis Integration theme to provide a consistent starting point while allowing for customization specific to this site's content and purpose.

## Theme Structure

```
theoryofconspiracies/
├── build/                      # Compiled assets (generated)
├── components/                 # Component templates
├── config/                     # Theme configuration
├── includes/                   # PHP include files
├── src/                        # Source files
│   ├── assets/
│   │   └── images/            # Theme images
│   ├── js/                    # JavaScript source files
│   └── scss/                  # SASS/SCSS source files
├── templates/                  # Twig templates
├── translations/              # Translation files
├── theoryofconspiracies.info.yml       # Theme definition
├── theoryofconspiracies.libraries.yml  # Asset libraries
├── theoryofconspiracies.theme          # Theme functions
├── package.json               # Node.js dependencies
├── webpack.mix.js            # Build configuration
└── README.md                 # This file
```

## Dependencies

- **Base Theme**: Radix 6.x
- **Framework**: Bootstrap 5.x
- **Build Tools**: Laravel Mix (Webpack)
- **Node.js**: >= 16.0
- **npm**: >= 6.0

## Installation & Setup

### 1. Theme Dependencies
The theme requires the Radix base theme:
```bash
composer require drupal/radix
```

### 2. Enable Themes
```bash
drush theme:enable radix -y
drush theme:enable theoryofconspiracies -y
drush config:set system.theme default theoryofconspiracies -y
```

### 3. Build Assets
```bash
cd web/themes/custom/theoryofconspiracies
npm install
npm run dev
```

## Development

### Build Commands
- `npm run dev` - Build assets for development
- `npm run watch` - Watch files and rebuild on changes  
- `npm run production` - Build optimized assets for production
- `npm run biome:format` - Format code with Biome

### Asset Compilation
The theme uses Laravel Mix for asset compilation:
- SCSS files are compiled to CSS
- JavaScript files are bundled and minified
- Images are copied to the build directory
- Source maps are generated for development

### File Structure
- **Source files**: Located in `src/` directory
- **Compiled assets**: Output to `build/` directory
- **Templates**: Twig templates in `templates/` directory
- **Components**: Reusable components in `components/` directory

## Customization

### Colors & Styling
- Primary SCSS files are in `src/scss/`
- Bootstrap variables can be overridden in `src/scss/variables/_bootstrap-variables.scss`
- Custom component styles in `src/scss/components/`

### JavaScript
- Custom JS files in `src/js/`
- Override core Drupal behaviors in `src/js/overrides/`

### Templates
- Twig templates in `templates/` directory
- Follow Drupal naming conventions for template suggestions

### Components
- Reusable components in `components/` directory
- Use Radix component system for consistency

## Features Inherited from Base Theme

- Professional design elements
- Bootstrap 5 integration
- Responsive design
- Modern build system with Laravel Mix
- Component-based architecture
- Professional breadcrumb styling
- Enhanced form elements
- Dialog and AJAX overrides

## Theme Configuration

The theme is configured through:
- `theoryofconspiracies.info.yml` - Basic theme information and regions
- `theoryofconspiracies.libraries.yml` - Asset libraries and dependencies
- `theoryofconspiracies.theme` - Custom PHP functions and preprocessing

## Customization for Theory of Conspiracies

This theme provides a foundation that can be customized for the specific needs of the Theory of Conspiracies website:

1. **Color Scheme**: Modify Bootstrap variables for site-specific colors
2. **Typography**: Adjust font selections in SCSS variables
3. **Layout**: Customize templates for content presentation
4. **Components**: Add specialized components for conspiracy content
5. **Images**: Replace placeholder images with site-specific graphics

## Performance

- Assets are optimized for production builds
- CSS and JavaScript are minified and bundled
- Images are copied and optimized
- Source maps available for development

## Browser Support

Inherits browser support from Bootstrap 5 and Radix theme:
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile responsive design
- Progressive enhancement approach

## Contributing

When making changes to this theme:

1. Work in the `src/` directory for source files
2. Run `npm run dev` or `npm run watch` during development
3. Test changes across different screen sizes
4. Follow Drupal coding standards
5. Document any significant customizations

## Troubleshooting

### Build Issues
- Ensure Node.js and npm are properly installed
- Clear node_modules and reinstall if needed: `rm -rf node_modules && npm install`
- Check for JavaScript/SCSS syntax errors

### Theme Not Loading
- Clear Drupal cache: `drush cr`
- Verify theme is enabled: `drush pm:list --type=theme`
- Check for PHP errors in logs

### Asset Issues
- Rebuild assets: `npm run dev`
- Check file permissions on build directory
- Verify webpack.mix.js configuration

This theme provides a solid foundation for the Theory of Conspiracies website while maintaining the professional development workflow and component architecture of the original St. Louis Integration theme.