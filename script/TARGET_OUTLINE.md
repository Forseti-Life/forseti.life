# Target setup.sh Script Outline

## Overview
Clean, focused setup script for forseti.life website only.

## Structure

### STEP 1: ENVIRONMENT SETUP
**Purpose:** Install all system dependencies and configure the development environment

- Update package lists
- PHP 8.3 installation and configuration (REQUIRED)
  - Install from Sury repository if needed
  - Install required PHP 8.3 extensions (gd, xml, mbstring, curl, zip, bcmath, json, mysql, etc.)
  - Configure as default PHP version
  - Set up PATH priority
- Composer installation and verification
- MySQL/MariaDB installation
  - Configure performance settings for data processing
- Apache installation and configuration
  - Enable PHP 8.3 module
  - Enable mod_rewrite
- Git installation
- Node.js and npm installation
- Development tools (unzip, wget, curl, vim, htop)
- Resume text extraction tools (poppler-utils, docx2txt, antiword)
- H3 Geolocation Framework setup
  - System packages (python3-dev, build-essential, libgeos-dev, etc.)
  - Python virtual environment at h3-geolocation/h3-env
  - H3 Python packages (h3, pandas, numpy, mysql-connector-python, etc.)
- Configure environment variables and PATH
- MySQL database creation (forseti_dev only)
- Private files directory creation (/var/private/forseti only)
- Apache virtual host configuration for forseti.local

### STEP 2: DRUPAL INSTALLATION - Forseti Site
**Purpose:** Set up the Forseti Drupal website

- Ensure sites directory exists
- Check if Forseti directory exists at /sites/forseti
  - If not exists: Create new Drupal 11.2.5 project
  - If exists: Use existing installation
- Move into project directory
- Fix/install Composer dependencies if needed
- Install Drush if not present
- Install development modules:
  - drupal/devel
  - drupal/admin_toolbar
  - drupal/pathauto
  - drupal/metatag
  - drupal/backup_migrate
  - drupal/bootstrap5
  - drupal/radix
  - drupal/recaptcha
  - drupal/recaptcha_v3
  - drupal/profile
  - aws/aws-sdk-php
  - defuse/php-encryption
- Check if Drupal is installed
  - Verify database tables exist
- If not installed:
  - Set up file permissions
  - Create files directory with proper permissions
  - Copy default settings.php
  - Run site:install (preserving existing data)
- Enable development modules (if Drupal is properly installed)
- Enable custom modules if they exist
- Enable and set custom theme (forseti) if it exists
- Configure Forseti home page
- Create development directories (modules/custom, themes/custom, config/sync)
- Add development-specific settings to settings.php
- Create settings.local.php with database configuration

### STEP 3: DEVELOPMENT CONFIGURATION
**Purpose:** Set up development tools and configurations

- Install Drupal Coder and PHP CodeSniffer
- Install additional development tools (phpunit, symfony-phpunit-bridge)
- Configure PHP CodeSniffer for Drupal standards
- Create development services configuration (development.services.yml)
- Create custom module template README
- Git ignore configuration
- Verify all installations

### STEP 4: POST-INSTALLATION FIXES
**Purpose:** Apply known issue resolutions and optimizations

- Fix Drupal installation issues (for forseti site only)
- Install and configure CORS module (if needed)
- Fix Simple OAuth module issues (if module exists)
- Clear caches
- Verify site is accessible
- Fix any permission issues
- Apply security configurations

### STEP 5: H3 GEOLOCATION DATABASE SETUP
**Purpose:** Initialize AmISafe crime mapping pipeline

- Test MySQL connection
- Verify H3 Python environment
- Check database tables (amisafe schema)
- Verify ETL pipeline status
- Show database statistics
- Provide instructions for data import

### COMPLETION MESSAGE
**Purpose:** Provide user with next steps and access information

- Summary of what was installed
- Access URLs and credentials
- Common commands for development
- Troubleshooting tips
- Next steps

## Key Differences from Current Script

1. **Single Site Focus:** Only forseti.life, no other sites
2. **No Duplication:** Each step appears only once
3. **Clear Step Numbering:** Steps 1-5 in logical order
4. **Consistent Naming:** Always "Forseti" or "forseti", never "stlouisintegration"
5. **Single Database:** Only forseti_dev database
6. **Single Private Directory:** Only /var/private/forseti
7. **No Multi-Site Loops:** All operations target forseti site only
8. **Clean Variables:** PROJECT_DIR always points to /sites/forseti

## Variables Used

- PROJECT_NAME="forseti"
- PROJECT_DIR="/home/keithaumiller/forseti.life/sites/forseti"
- DB_NAME="forseti_dev"
- DB_USER="drupal_user"
- DB_PASSWORD="drupal_secure_password"
- DB_HOST="127.0.0.1"
- SITE_NAME="Forseti"
- ADMIN_USER="admin"
- ADMIN_PASSWORD="admin_secure_password"
- ADMIN_EMAIL="admin@forseti.life"
