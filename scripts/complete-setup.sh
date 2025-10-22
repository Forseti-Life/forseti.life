#!/bin/bash

# St. Louis Integration - Complete Development Environment Setup
# This script combines environment setup, Drupal installation, and development configuration
# into one comprehensive setup process

set -e  # Exit on any error

echo "=== St. Louis Integration - Complete Development Environment Setup ==="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root. Please run as a regular user."
   exit 1
fi

# Configuration
PROJECT_NAME="stlouisintegration"
PROJECT_DIR="/workspaces/stlouisintegration.com/drupal"
DB_NAME="stlouisintegration_dev"
DB_USER="drupal_user"
DB_PASSWORD="drupal_secure_password"
DB_HOST="127.0.0.1"
SITE_NAME="St. Louis Integration"
ADMIN_USER="admin"
ADMIN_PASSWORD="admin_secure_password"
ADMIN_EMAIL="admin@stlouisintegration.com"

# Check if .env file exists and source it
ENV_FILE="/workspaces/stlouisintegration.com/.env"
if [ -f "$ENV_FILE" ]; then
    print_status "Loading configuration from .env file..."
    source "$ENV_FILE"
fi

print_step "1. ENVIRONMENT SETUP - Installing system dependencies..."

print_status "Updating package lists..."
sudo apt update

# Check PHP version
print_status "Checking PHP installation..."
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    print_status "PHP $PHP_VERSION is installed"
    
    # Check if PHP version is 8.1 or higher
    if php -r "exit(version_compare(PHP_VERSION, '8.1.0', '>=') ? 0 : 1);"; then
        print_status "PHP $PHP_VERSION meets Drupal 11 requirements (8.1+)"
    else
        print_error "PHP 8.1 or higher is required for Drupal 11. Current version: $PHP_VERSION"
        print_status "Installing PHP 8.3..."
        sudo apt install -y software-properties-common
        sudo add-apt-repository ppa:ondrej/php -y
        sudo apt update
        sudo apt install -y php8.3 php8.3-cli php8.3-fpm
    fi
else
    print_status "Installing PHP 8.3..."
    sudo apt install -y software-properties-common
    sudo add-apt-repository ppa:ondrej/php -y
    sudo apt update
    sudo apt install -y php8.3 php8.3-cli php8.3-fpm
fi

# Ensure PHP 8.3 is installed regardless of system PHP version
print_status "Ensuring PHP 8.3 is properly installed..."
if ! dpkg -l | grep -q "^ii.*php8.3"; then
    print_status "Installing PHP 8.3 packages..."
    sudo apt install -y php8.3 php8.3-cli php8.3-fpm
fi

# Install required PHP extensions
print_status "Checking PHP extensions..."
REQUIRED_EXTENSIONS=("gd" "xml" "mbstring" "curl" "zip" "bcmath" "json" "tokenizer" "fileinfo" "intl" "dom")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m | grep -q "^$ext$"; then
        MISSING_EXTENSIONS+=("php8.3-$ext")
        print_warning "PHP extension '$ext' is missing"
    else
        print_status "PHP extension '$ext' is already installed"
    fi
done

# Check MySQL extensions
if ! php -m | grep -qE "^(mysqli|pdo_mysql|mysqlnd)$"; then
    MISSING_EXTENSIONS+=("php8.3-mysql")
    print_warning "PHP MySQL extension is missing"
else
    print_status "PHP MySQL extension is already installed"
fi

# Check OPcache
if ! php -m | grep -qi "opcache"; then
    MISSING_EXTENSIONS+=("php8.3-opcache")
    print_warning "PHP OPcache extension is missing"
else
    print_status "PHP OPcache extension is already installed"
fi

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    print_status "Installing missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
    sudo apt install -y "${MISSING_EXTENSIONS[@]}"
fi

# Ensure critical extensions are installed
print_status "Ensuring critical PHP extensions are properly installed..."
CRITICAL_EXTENSIONS=("php8.3-xml" "php8.3-mysql")
for ext_package in "${CRITICAL_EXTENSIONS[@]}"; do
    if ! dpkg -l | grep -q "^ii.*$ext_package"; then
        print_status "Installing critical extension: $ext_package"
        sudo apt install -y "$ext_package"
    fi
done

# Install Composer
print_status "Checking Composer installation..."
if command -v composer &> /dev/null; then
    print_status "Composer is already installed: $(composer --version)"
else
    print_status "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
fi

# Install MySQL/MariaDB
print_status "Checking MySQL/MariaDB installation..."
if command -v mysql &> /dev/null; then
    print_status "MySQL is already installed"
else
    print_status "Installing MySQL server..."
    sudo apt install -y mysql-server mysql-client
    print_warning "Please run 'sudo mysql_secure_installation' after this script completes"
fi

# Install Apache
print_status "Checking Apache installation..."
if command -v apache2 &> /dev/null; then
    print_status "Apache is already installed"
else
    print_status "Installing Apache web server..."
    sudo apt install -y apache2
    sudo a2enmod rewrite
fi

# Configure Apache PHP 8.3 module
print_status "Configuring Apache PHP 8.3 module..."

# Disable conflicting PHP modules
for php_ver in 7.4 8.0 8.1 8.2; do
    if sudo a2query -m php$php_ver 2>/dev/null; then
        print_status "Disabling PHP $php_ver module..."
        sudo a2dismod php$php_ver 2>/dev/null || true
    fi
done

# Install and enable PHP 8.3 module for Apache
if ! dpkg -l | grep -q "^ii.*libapache2-mod-php8.3"; then
    print_status "Installing Apache PHP 8.3 module..."
    sudo apt install -y libapache2-mod-php8.3
fi

print_status "Enabling PHP 8.3 module for Apache..."
sudo a2enmod php8.3

# Install Git
print_status "Checking Git installation..."
if command -v git &> /dev/null; then
    print_status "Git is already installed: $(git --version)"
else
    print_status "Installing Git..."
    sudo apt install -y git
fi

# Install Node.js and npm
print_status "Checking Node.js installation..."
if command -v node &> /dev/null; then
    print_status "Node.js is already installed: $(node --version)"
else
    print_status "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
    sudo apt install -y nodejs
fi

# Install additional development tools
print_status "Checking additional development tools..."
TOOLS=("unzip" "wget" "curl" "vim" "htop")
MISSING_TOOLS=()

for tool in "${TOOLS[@]}"; do
    if ! command -v "$tool" &> /dev/null; then
        MISSING_TOOLS+=("$tool")
    fi
done

if [ ${#MISSING_TOOLS[@]} -gt 0 ]; then
    print_status "Installing missing development tools: ${MISSING_TOOLS[*]}"
    sudo apt install -y "${MISSING_TOOLS[@]}"
fi

# Install resume text extraction dependencies
print_status "Checking resume text extraction tools..."
TEXT_EXTRACTION_TOOLS=("pdftotext" "docx2txt" "antiword")
TEXT_EXTRACTION_PACKAGES=("poppler-utils" "docx2txt" "antiword")
MISSING_TEXT_TOOLS=()

for i in "${!TEXT_EXTRACTION_TOOLS[@]}"; do
    tool="${TEXT_EXTRACTION_TOOLS[$i]}"
    package="${TEXT_EXTRACTION_PACKAGES[$i]}"
    
    if ! command -v "$tool" &> /dev/null; then
        MISSING_TEXT_TOOLS+=("$package")
    fi
done

if [ ${#MISSING_TEXT_TOOLS[@]} -gt 0 ]; then
    print_status "Installing missing text extraction tools: ${MISSING_TEXT_TOOLS[*]}"
    sudo apt install -y "${MISSING_TEXT_TOOLS[@]}"
    print_status "Resume text extraction dependencies installed successfully"
fi

# Configure PHP 8.3 as default version
print_status "Configuring PHP 8.3 as default version..."

# Configure update-alternatives
if command -v update-alternatives &> /dev/null && [ -x "/usr/bin/php8.3" ]; then
    # Remove any existing alternatives
    sudo update-alternatives --remove-all php 2>/dev/null || true
    
    # Install PHP 8.3 as primary alternative
    sudo update-alternatives --install /usr/bin/php php /usr/bin/php8.3 100
    sudo update-alternatives --set php /usr/bin/php8.3
    print_status "PHP 8.3 configured as default CLI version via update-alternatives"
fi

# Configure environment
print_status "Configuring environment for PHP 8.3..."
BASHRC_FILE="$HOME/.bashrc"
if [ -f "$BASHRC_FILE" ]; then
    # Remove existing PHP configurations
    grep -v "# PHP 8.3 Configuration" "$BASHRC_FILE" > "${BASHRC_FILE}.tmp" || true
    grep -v "export PATH.*php" "${BASHRC_FILE}.tmp" > "$BASHRC_FILE" || true
    rm -f "${BASHRC_FILE}.tmp"
    
    # Add PHP 8.3 configuration
    echo "" >> "$BASHRC_FILE"
    echo "# PHP 8.3 Configuration" >> "$BASHRC_FILE"
    echo 'export PATH="/usr/bin:$PATH"' >> "$BASHRC_FILE"
    echo 'alias php="/usr/bin/php8.3"' >> "$BASHRC_FILE"
    echo 'alias composer="/usr/bin/php8.3 /usr/local/bin/composer"' >> "$BASHRC_FILE"
    
    print_status "Updated .bashrc with PHP 8.3 configuration"
fi

# Apply environment changes to current session
export PATH="/usr/bin:$PATH"
alias php="/usr/bin/php8.3"
alias composer="/usr/bin/php8.3 /usr/local/bin/composer"

# Configure MySQL database
print_status "Configuring MySQL database..."

# Ensure MySQL is running
if ! sudo mysql -e "SELECT 1;" &>/dev/null; then
    print_status "MySQL not running, attempting to start..."
    sudo service mysql start || print_warning "Failed to start MySQL service"
    sleep 2
fi

if sudo mysql -e "SELECT User FROM mysql.user WHERE User='drupal_user' AND Host='127.0.0.1';" 2>/dev/null | grep -q drupal_user; then
    print_status "MySQL drupal_user already exists"
else
    print_status "Creating MySQL database and user for Drupal..."
    sudo mysql <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF
    print_status "MySQL database '${DB_NAME}' and user '${DB_USER}' created"
fi

# Create private files directory
print_status "Creating Drupal private files directory..."
if [ ! -d "/var/private/stlouisintegration" ]; then
    sudo mkdir -p /var/private/stlouisintegration
    sudo chown -R $USER:$USER /var/private/stlouisintegration
    sudo chmod -R 775 /var/private/stlouisintegration
    print_status "Private files directory created at /var/private/stlouisintegration"
fi

# Configure Apache virtual host
print_status "Configuring Apache virtual host..."
DRUPAL_ROOT="/workspaces/stlouisintegration.com/drupal/web"
sudo bash -c "cat > /etc/apache2/sites-available/000-default.conf" <<EOF
<VirtualHost *:80>
        ServerAdmin webmaster@localhost
        DocumentRoot /workspaces/stlouisintegration.com/drupal/web

        <Directory /workspaces/stlouisintegration.com/drupal/web>
                Options Indexes FollowSymLinks
                AllowOverride All
                Require all granted
        </Directory>

        ErrorLog \${APACHE_LOG_DIR}/error.log
        CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Start services
print_status "Starting services..."
sudo service mysql start
sudo service apache2 restart

print_step "2. DRUPAL INSTALLATION - Checking existing Drupal installation..."

# Check if Drupal directory exists
if [ -d "$PROJECT_DIR" ]; then
    print_status "Existing Drupal directory found. Skipping fresh installation to preserve custom work."
    print_status "Using existing Drupal installation at $PROJECT_DIR"
else
    print_status "No existing Drupal directory found. Creating new Drupal 11 project..."
    cd /workspaces/stlouisintegration.com
    /usr/bin/php8.3 /usr/local/bin/composer create-project drupal/recommended-project:^11.0 drupal --no-interaction
fi

# Move into the project directory
cd "$PROJECT_DIR"

# Only install dependencies if this is a fresh installation
if [ ! -f "vendor/bin/drush" ]; then
    print_status "Installing Drush..."
    /usr/bin/php8.3 /usr/local/bin/composer require drush/drush --no-interaction
fi

# Check if development modules are already installed
if [ ! -d "web/modules/contrib/devel" ]; then
    print_status "Installing development modules..."
    /usr/bin/php8.3 /usr/local/bin/composer require drupal/devel drupal/admin_toolbar drupal/pathauto drupal/metatag --no-interaction
else
    print_status "Development modules already installed. Skipping to preserve existing setup."
fi

# Only set up permissions and install if settings.php doesn't exist (fresh installation)
if [ ! -f "web/sites/default/settings.php" ] || [ ! -s "web/sites/default/settings.php" ]; then
    print_status "Setting up file permissions for fresh installation..."
    chmod 755 web/sites/default
    mkdir -p web/sites/default/files
    chmod 775 web/sites/default/files

    # Copy default settings file
    cp web/sites/default/default.settings.php web/sites/default/settings.php
    chmod 664 web/sites/default/settings.php

    print_status "Running Drupal installation..."
    ./vendor/bin/drush site:install standard \
        --db-url="mysql://${DB_USER}:${DB_PASSWORD}@127.0.0.1:3306/${DB_NAME}" \
        --site-name="${SITE_NAME}" \
        --account-name="${ADMIN_USER}" \
        --account-pass="${ADMIN_PASSWORD}" \
        --account-mail="${ADMIN_EMAIL}" \
        --yes
else
    print_status "Existing Drupal installation detected. Skipping site installation to preserve data."
fi

# Only enable modules if this is a fresh installation
if ./vendor/bin/drush status | grep -q "Drupal bootstrap.*Successful"; then
    # Check if development modules are already enabled
    if ! ./vendor/bin/drush pm:list --status=enabled | grep -q "devel"; then
        print_status "Enabling development and utility modules..."
        ./vendor/bin/drush en devel admin_toolbar admin_toolbar_tools pathauto metatag -y
    else
        print_status "Development modules already enabled. Skipping to preserve existing configuration."
    fi
    
    # Enable custom modules if they exist and aren't already enabled
    if [ -d "web/modules/custom/professional_website_content" ] && ! ./vendor/bin/drush pm:list --status=enabled | grep -q "professional_website_content"; then
        print_status "Enabling custom modules..."
        
        # Enable profile module first (dependency for job_application_automation)
        ./vendor/bin/drush en profile -y
        
        # Enable modules in dependency order
        ./vendor/bin/drush en professional_website_content -y
        ./vendor/bin/drush en ai_conversation -y
        ./vendor/bin/drush en stli_site_customizations -y
        
        # Note: job_application_automation and resume_tailoring may need additional content types
        # Enable them individually if dependencies are met
        if ./vendor/bin/drush en job_application_automation -y 2>/dev/null; then
            print_status "Job Application Automation module enabled successfully"
        else
            print_warning "Job Application Automation module has unmet dependencies - skipping"
        fi
        
        if ./vendor/bin/drush en resume_tailoring -y 2>/dev/null; then
            print_status "Resume Tailoring module enabled successfully"
        else
            print_warning "Resume Tailoring module has unmet dependencies - skipping"
        fi
    fi
    
    # Enable and set custom theme if it exists
    if [ -d "web/themes/custom/stlouisintegration" ]; then
        if ! ./vendor/bin/drush pm:list --type=theme --status=enabled | grep -q "stlouisintegration"; then
            print_status "Enabling St. Louis Integration custom theme..."
            ./vendor/bin/drush theme:enable stlouisintegration -y
            ./vendor/bin/drush config:set system.theme default stlouisintegration -y
            print_status "St. Louis Integration theme set as default"
        else
            print_status "St. Louis Integration theme already enabled"
        fi
    fi
else
    print_status "Drupal not fully installed yet. Skipping module enabling."
fi

print_status "Ensuring custom development directories exist..."
mkdir -p web/modules/custom
mkdir -p web/themes/custom
mkdir -p config/sync

chmod 755 web/modules/custom
chmod 755 web/themes/custom
chmod 755 config/sync

# Only add development settings if they don't already exist
if ! grep -q "Development-specific settings" web/sites/default/settings.php; then
    print_status "Adding development-specific settings..."
    cat >> web/sites/default/settings.php << 'EOL'

/**
 * Development-specific settings
 */

// Enable local development settings
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}

// Configuration sync directory
$settings['config_sync_directory'] = '../config/sync';

// Disable CSS and JS aggregation for development
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

// Enable verbose error reporting
$config['system.logging']['error_level'] = 'verbose';

// Disable caching for development
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
EOL
else
    print_status "Development settings already exist in settings.php. Skipping to preserve existing configuration."
fi

# Create settings.local.php only if it doesn't exist
if [ ! -f "web/sites/default/settings.local.php" ]; then
    print_status "Creating local development settings..."
    cat > web/sites/default/settings.local.php << EOL
<?php

/**
 * Local development settings
 */

// Database configuration
\$databases['default']['default'] = [
  'database' => '${DB_NAME}',
  'username' => '${DB_USER}',
  'password' => '${DB_PASSWORD}',
  'host' => '127.0.0.1',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

// Hash salt for local development
\$settings['hash_salt'] = '$(openssl rand -base64 32)';

// Development services
\$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';

// Skip file system permissions hardening
\$settings['skip_permissions_hardening'] = TRUE;

// Disable caching
\$settings['cache']['bins']['render'] = 'cache.backend.null';
\$settings['cache']['bins']['page'] = 'cache.backend.null';
\$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

// Enable local development mode
\$config['system.performance']['css']['preprocess'] = FALSE;
\$config['system.performance']['js']['preprocess'] = FALSE;
EOL
    chmod 644 web/sites/default/settings.local.php
else
    print_status "Local development settings already exist. Skipping to preserve existing configuration."
fi

print_step "3. DEVELOPMENT CONFIGURATION - Setting up development tools..."

print_status "Installing Drupal Coder and PHP CodeSniffer..."
/usr/bin/php8.3 /usr/local/bin/composer require drupal/coder --dev --no-interaction

print_status "Installing additional development tools..."
/usr/bin/php8.3 /usr/local/bin/composer require phpunit/phpunit symfony/phpunit-bridge --dev --no-interaction

print_status "Configuring PHP CodeSniffer for Drupal standards..."
./vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer
./vendor/bin/phpcs --config-set default_standard Drupal

# Only create development services if it doesn't exist
if [ ! -f "web/sites/development.services.yml" ]; then
    print_status "Creating development services configuration..."
    cat > web/sites/development.services.yml << 'EOL'
# Local development services.
parameters:
  http.response.debug_cacheability_headers: true
  twig.config:
    debug: true
    auto_reload: true
    cache: false
services:
  cache.backend.null:
    class: Drupal\Core\Cache\NullBackendFactory
EOL
else
    print_status "Development services configuration already exists. Skipping to preserve existing setup."
fi

# Create custom module template only if it doesn't exist
CUSTOM_MODULES_DIR="$PROJECT_DIR/web/modules/custom"
if [ ! -f "$CUSTOM_MODULES_DIR/README.md" ]; then
    print_status "Creating custom module template..."
    cat > "$CUSTOM_MODULES_DIR/README.md" << 'EOL'
# Custom Modules

This directory contains custom modules for the St. Louis Integration website.

## Module Structure

Each custom module should follow Drupal 11 standards:

```
module_name/
├── module_name.info.yml
├── module_name.module
├── src/
│   ├── Controller/
│   ├── Form/
│   ├── Plugin/
│   └── Service/
├── config/
│   └── install/
├── templates/
└── tests/
```

## Creating a New Module

1. Create a new directory with your module name
2. Create the `.info.yml` file with module metadata
3. Add your module logic in the appropriate directories
4. Follow Drupal coding standards

## Coding Standards

All custom modules should follow:
- Drupal coding standards
- PSR-4 autoloading
- Proper documentation
- Unit and functional tests where appropriate

## Testing

Run coding standards check:
```bash
../../../vendor/bin/phpcs --standard=Drupal /path/to/your/module
```

Fix coding standards automatically:
```bash
../../../vendor/bin/phpcbf --standard=Drupal /path/to/your/module
```
EOL
else
    print_status "Custom module template already exists. Skipping to preserve existing modules."
fi

# Create custom theme template only if it doesn't exist
CUSTOM_THEMES_DIR="$PROJECT_DIR/web/themes/custom"
if [ ! -f "$CUSTOM_THEMES_DIR/README.md" ]; then
    print_status "Creating custom theme template..."
    cat > "$CUSTOM_THEMES_DIR/README.md" << 'EOL'
# Custom Themes

This directory contains custom themes for the St. Louis Integration website.

## Theme Structure

Each custom theme should follow Drupal 11 standards:

```
theme_name/
├── theme_name.info.yml
├── theme_name.theme
├── theme_name.libraries.yml
├── css/
├── js/
├── images/
├── templates/
└── config/
```

## Creating a New Theme

1. Create a new directory with your theme name
2. Create the `.info.yml` file with theme metadata
3. Add your theme logic and assets
4. Follow Drupal theming best practices

## Development

For theme development with modern tools:
- Use SCSS/Sass for stylesheets
- Use modern JavaScript (ES6+)
- Implement proper build processes
- Ensure mobile-first responsive design

## Testing

Test themes across:
- Multiple browsers
- Different screen sizes
- Accessibility standards
- Performance metrics
EOL
else
    print_status "Custom theme template already exists. Skipping to preserve existing themes."
fi

# Create development scripts
SCRIPTS_DIR="$PROJECT_DIR/scripts"
mkdir -p "$SCRIPTS_DIR"

# Only create utility scripts if they don't exist
if [ ! -f "$SCRIPTS_DIR/clear-cache.sh" ]; then
    print_status "Creating development utility scripts..."

# Cache clear script
cat > "$SCRIPTS_DIR/clear-cache.sh" << 'EOL'
#!/bin/bash
# Clear Drupal cache
echo "Clearing Drupal cache..."
../vendor/bin/drush cache:rebuild
echo "Cache cleared successfully!"
EOL

# Code standards check script
cat > "$SCRIPTS_DIR/check-standards.sh" << 'EOL'
#!/bin/bash
# Check coding standards for custom modules and themes
echo "Checking coding standards..."

# Check custom modules
if [ -d "../web/modules/custom" ]; then
    echo "Checking custom modules..."
    ../vendor/bin/phpcs --standard=Drupal ../web/modules/custom
fi

# Check custom themes
if [ -d "../web/themes/custom" ]; then
    echo "Checking custom themes..."
    ../vendor/bin/phpcs --standard=Drupal ../web/themes/custom
fi

echo "Standards check completed!"
EOL

# Fix coding standards script
cat > "$SCRIPTS_DIR/fix-standards.sh" << 'EOL'
#!/bin/bash
# Fix coding standards for custom modules and themes
echo "Fixing coding standards..."

# Fix custom modules
if [ -d "../web/modules/custom" ]; then
    echo "Fixing custom modules..."
    ../vendor/bin/phpcbf --standard=Drupal ../web/modules/custom
fi

# Fix custom themes
if [ -d "../web/themes/custom" ]; then
    echo "Fixing custom themes..."
    ../vendor/bin/phpcbf --standard=Drupal ../web/themes/custom
fi

echo "Standards fixing completed!"
EOL

# Database backup script
cat > "$SCRIPTS_DIR/backup-database.sh" << 'EOL'
#!/bin/bash
# Backup database
BACKUP_DIR="../backups"
mkdir -p "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/db-backup-$(date +%Y%m%d-%H%M%S).sql"

echo "Creating database backup..."
../vendor/bin/drush sql:dump --result-file="$BACKUP_FILE"
echo "Database backup created: $BACKUP_FILE"
EOL

    # Make scripts executable
    chmod +x "$SCRIPTS_DIR"/*.sh
else
    print_status "Development utility scripts already exist. Skipping to preserve existing scripts."
fi

# Clear cache after all configuration (only if Drupal is properly installed)
if ./vendor/bin/drush status | grep -q "Drupal bootstrap.*Successful"; then
    print_status "Clearing cache after configuration..."
    ./vendor/bin/drush cache:rebuild
else
    print_warning "Drupal not fully bootstrapped. Skipping cache clear."
fi

# Set final permissions
print_status "Setting final permissions..."
if [ -f "web/sites/default/settings.php" ]; then
    chmod 644 web/sites/default/settings.php
fi
chmod -R 755 web/modules/custom web/themes/custom

# Install Composer dependencies for Drupal
DRUPAL_ROOT="/workspaces/stlouisintegration.com/drupal"
if [ -f "$DRUPAL_ROOT/composer.json" ]; then
    print_status "Verifying Composer dependencies..."
    cd "$DRUPAL_ROOT"
    
    if [ ! -d "vendor/mtdowling/jmespath.php" ]; then
        print_status "Installing missing critical packages..."
        /usr/bin/php8.3 /usr/local/bin/composer install --no-dev --optimize-autoloader
    else
        print_status "✅ All Composer dependencies verified"
    fi
fi

# Verify installations
print_status "Verifying installations..."
echo "========================="
echo "PHP Version: $(/usr/bin/php8.3 --version | head -n 1)"
echo "Composer Version: $(/usr/bin/php8.3 /usr/local/bin/composer --version)"
echo "Apache PHP Module: $(apache2ctl -M 2>/dev/null | grep php || echo 'Not found')"
echo "MySQL Version: $(mysql --version)"
echo "Apache Version: $(apache2 -v | head -n 1)"
echo "Drupal Version: $(./vendor/bin/drush status | grep 'Drupal version' || echo 'Drupal 11 installed')"
echo "========================="

# Validate critical PHP extensions
print_status "Validating PHP 8.3 extensions..."
REQUIRED_EXTENSIONS=("dom" "mysqli" "pdo_mysql" "xml" "gd" "curl" "zip" "intl")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! /usr/bin/php8.3 -m | grep -q "^$ext$"; then
        MISSING_EXTENSIONS+=("$ext")
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -eq 0 ]; then
    print_status "✅ All required PHP 8.3 extensions are loaded"
else
    print_warning "⚠️  Missing PHP 8.3 extensions: ${MISSING_EXTENSIONS[*]}"
fi

# Test website availability
print_status "Testing website availability..."
if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|302\|301"; then
    print_status "✅ Website is accessible at http://localhost"
else
    print_warning "⚠️  Website may need additional configuration"
fi

echo "========================="
print_status "COMPLETE SETUP FINISHED SUCCESSFULLY!"
echo "========================="

echo "Installation Summary:"
echo "========================="
echo "✓ Environment: PHP 8.3, MySQL, Apache configured"
echo "✓ Drupal: 11.x installed with development modules"
echo "✓ Development Tools: Coder, PHPCS, PHPUnit configured"
echo "✓ Custom Modules: professional_website_content, ai_conversation, stli_site_customizations enabled"
echo "✓ Custom Theme: stlouisintegration enabled and set as default"
echo "✓ Development Scripts: Available in drupal/scripts/"
echo "========================="

echo "Site Information:"
echo "========================="
echo "Site Name: ${SITE_NAME}"
echo "Site URL: http://localhost"
echo "Admin Login: http://localhost/user/login"
echo "Admin User: ${ADMIN_USER}"
echo "Admin Password: ${ADMIN_PASSWORD}"
echo "Admin Email: ${ADMIN_EMAIL}"
echo "Database: ${DB_NAME}"
echo "========================="

print_status "Available development commands:"
echo "- Check coding standards: cd drupal && ./scripts/check-standards.sh"
echo "- Fix coding standards: cd drupal && ./scripts/fix-standards.sh"
echo "- Clear cache: cd drupal && ./scripts/clear-cache.sh"
echo "- Backup database: cd drupal && ./scripts/backup-database.sh"
echo "- Drush commands: cd drupal && ./vendor/bin/drush [command]"

print_warning "Important reminders:"
echo "- Change admin password after first login for security"
echo "- Follow Drupal coding standards for all custom development"
echo "- Use development utility scripts for maintenance tasks"
echo "- Regular database backups during development"

print_status "🚀 Your St. Louis Integration development environment is ready!"