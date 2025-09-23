# Development Environment Setup Scripts

This directory contains scripts and documentation for setting up the St. Louis Integration website development environment.

## Quick Start

Run the setup scripts in order:

1. `./setup-environment.sh` - Install system dependencies
2. `./install-drupal.sh` - Create and install Drupal 11
3. `./configure-development.sh` - Set up development tools and custom modules

## Files

- **setup-environment.sh** - Installs PHP, Composer, MySQL, and other system dependencies
- **install-drupal.sh** - Creates Drupal 11 project and runs installation
- **configure-development.sh** - Sets up development tools, custom modules directory, and coding standards
- **database-setup.sql** - SQL commands for creating the development database
- **requirements.md** - Detailed system requirements and dependencies
- **troubleshooting.md** - Common issues and solutions

## Manual Setup

If you prefer to set up manually, follow the documentation in `requirements.md` for step-by-step instructions.

## Environment Variables

Create a `.env` file in the project root with:

```
DB_NAME=stlouisintegration_dev
DB_USER=drupal_user
DB_PASSWORD=your_secure_password
DB_HOST=localhost
DB_PORT=3306
DRUPAL_ADMIN_USER=admin
DRUPAL_ADMIN_PASSWORD=your_admin_password
DRUPAL_ADMIN_EMAIL=admin@stlouisintegration.com
```

## Production Deployment

The deployment workflow is handled by `.github/workflows/deploy.yml` which automatically deploys to the production server when changes are pushed to the main branch.