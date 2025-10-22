# Theory of Conspiracies Website

This is a Drupal 11 website for theoryofconspiracies.com.

## Site Information

- **Site Name**: Theory of Conspiracies
- **Site URL**: http://localhost:8080
- **Admin Login**: http://localhost:8080/user/login
- **Admin User**: admin
- **Admin Password**: admin_secure_password
- **Admin Email**: admin@theoryofconspiracies.com
- **Database**: theoryofconspiracies_dev

## Development

This site is set up with:
- Drupal 11.2.5
- Development modules: Devel, Admin Toolbar, Pathauto, Metatag
- Custom modules directory: `web/modules/custom/`
- Custom themes directory: `web/themes/custom/`
- Configuration sync directory: `config/sync/`

## Available Commands

```bash
# Clear cache
./vendor/bin/drush cache:rebuild

# Enable modules
./vendor/bin/drush en [module_name] -y

# Check site status
./vendor/bin/drush status

# Generate one-time login link
./vendor/bin/drush user:login
```

## Multi-Site Setup

This site is part of a multi-site workspace:
- **St. Louis Integration**: http://localhost:80 (`/sites/stlouisintegration/`)
- **Theory of Conspiracies**: http://localhost:8080 (`/sites/theoryofconspiracies/`)

## Apache Configuration

Both sites are served by Apache:
- Port 80: St. Louis Integration
- Port 8080: Theory of Conspiracies

## Theme Development

To create a custom theme:
1. Navigate to `web/themes/custom/`
2. Create your theme directory
3. Follow Drupal 11 theming best practices

## Module Development

To create a custom module:
1. Navigate to `web/modules/custom/`
2. Create your module directory
3. Follow Drupal 11 module development standards