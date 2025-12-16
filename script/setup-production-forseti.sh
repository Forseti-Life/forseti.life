#!/bin/bash

# Forseti.life Production Server Setup Script
# This script sets up a new Drupal 11 site for forseti.life on the existing multi-site server
# Run this script as root on the production server

set -e  # Exit on any error

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
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root"
   exit 1
fi

# Configuration
SITE_NAME="forseti"
DOMAIN_NAME="forseti.life"
DRUPAL_ROOT="/var/www/html/forseti"
DB_NAME="forseti_prod"
DB_USER="drupal_user"
DB_PASSWORD="drupal_secure_password"
ADMIN_USER="admin"
ADMIN_PASSWORD="admin_secure_password"
ADMIN_EMAIL="admin@forseti.life"

print_step "1. CHECKING PREREQUISITES"

# Check if directory already exists
if [ -d "$DRUPAL_ROOT" ]; then
    print_warning "Directory $DRUPAL_ROOT already exists. Aborting to prevent data loss."
    print_warning "Remove it manually if you want to start fresh: rm -rf $DRUPAL_ROOT"
    exit 1
fi

# Verify PHP 8.3
PHP_VERSION=$(php -r "echo PHP_VERSION;")
if [[ ! "$PHP_VERSION" =~ ^8\.3 ]]; then
    print_error "PHP 8.3 required. Current version: $PHP_VERSION"
    exit 1
fi
print_status "✅ PHP version: $PHP_VERSION"

# Verify MySQL is running
if ! mysql -e "SELECT 1;" &>/dev/null; then
    print_error "MySQL is not running or not accessible"
    exit 1
fi
print_status "✅ MySQL is accessible"

# Verify Composer is installed
if ! command -v composer &>/dev/null; then
    print_error "Composer is not installed"
    exit 1
fi
print_status "✅ Composer is installed"

print_step "2. CREATING DATABASE"

# Check if database already exists
if mysql -e "USE $DB_NAME;" 2>/dev/null; then
    print_warning "Database $DB_NAME already exists. Skipping database creation."
else
    print_status "Creating database $DB_NAME..."
    mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    print_status "✅ Database created"
fi

# Grant privileges to drupal_user
print_status "Granting privileges to $DB_USER..."
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';"
mysql -e "FLUSH PRIVILEGES;"
print_status "✅ Database privileges granted"

print_step "3. CREATING DRUPAL DIRECTORY STRUCTURE"

# Create base directory
mkdir -p "$DRUPAL_ROOT"
cd "$DRUPAL_ROOT"

print_status "Creating Drupal 11 project via Composer..."
sudo -u www-data composer create-project drupal/recommended-project:^11.0 . --no-interaction --no-dev

print_status "✅ Drupal core installed"

print_step "4. INSTALLING REQUIRED DRUPAL MODULES"

print_status "Installing essential contrib modules..."
sudo -u www-data composer require \
    drupal/admin_toolbar \
    drupal/pathauto \
    drupal/metatag \
    drupal/bootstrap5 \
    drupal/radix \
    drush/drush \
    --no-interaction --no-dev

print_status "✅ Contrib modules installed"

print_step "5. CONFIGURING FILE PERMISSIONS"

# Create necessary directories
mkdir -p "$DRUPAL_ROOT/web/sites/default/files"
mkdir -p "$DRUPAL_ROOT/config/sync"
mkdir -p "$DRUPAL_ROOT/web/modules/custom"
mkdir -p "$DRUPAL_ROOT/web/themes/custom"

# Set ownership
chown -R www-data:www-data "$DRUPAL_ROOT"

# Set permissions
chmod 755 "$DRUPAL_ROOT/web/sites/default"
chmod -R 775 "$DRUPAL_ROOT/web/sites/default/files"
chmod 755 "$DRUPAL_ROOT/config/sync"

# Copy default settings file
if [ ! -f "$DRUPAL_ROOT/web/sites/default/settings.php" ]; then
    cp "$DRUPAL_ROOT/web/sites/default/default.settings.php" "$DRUPAL_ROOT/web/sites/default/settings.php"
    chmod 664 "$DRUPAL_ROOT/web/sites/default/settings.php"
fi

print_status "✅ File permissions configured"

print_step "6. INSTALLING DRUPAL SITE"

print_status "Installing Drupal with standard profile..."
cd "$DRUPAL_ROOT"
sudo -u www-data ./vendor/bin/drush site:install standard \
    --db-url="mysql://${DB_USER}:${DB_PASSWORD}@localhost:3306/${DB_NAME}" \
    --site-name="Forseti" \
    --account-name="${ADMIN_USER}" \
    --account-pass="${ADMIN_PASSWORD}" \
    --account-mail="${ADMIN_EMAIL}" \
    --yes

print_status "✅ Drupal site installed"

print_step "7. CONFIGURING APACHE VIRTUAL HOST"

# Create Apache virtual host configuration
VHOST_FILE="/etc/apache2/sites-available/${SITE_NAME}.conf"

print_status "Creating Apache virtual host configuration..."
cat > "$VHOST_FILE" <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN_NAME}
    ServerAlias www.${DOMAIN_NAME}
    ServerAdmin webmaster@${DOMAIN_NAME}
    
    DocumentRoot ${DRUPAL_ROOT}/web
    
    <Directory ${DRUPAL_ROOT}/web>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Logging with custom format
    ErrorLog \${APACHE_LOG_DIR}/${SITE_NAME}_error.log
    CustomLog \${APACHE_LOG_DIR}/${SITE_NAME}_access.log cloudflare
    
    # PHP settings
    php_value upload_max_filesize 64M
    php_value post_max_size 64M
    php_value memory_limit 256M
    php_value max_execution_time 300
</VirtualHost>
EOF

print_status "✅ Virtual host configuration created"

# Enable the site
print_status "Enabling Apache site..."
a2ensite "${SITE_NAME}.conf"

# Test Apache configuration
print_status "Testing Apache configuration..."
if apache2ctl configtest; then
    print_status "✅ Apache configuration is valid"
else
    print_error "Apache configuration test failed"
    exit 1
fi

# Reload Apache
print_status "Reloading Apache..."
systemctl reload apache2

print_status "✅ Apache configured and reloaded"

print_step "8. ENABLING ESSENTIAL MODULES"

cd "$DRUPAL_ROOT"
print_status "Enabling admin toolbar and other essential modules..."
sudo -u www-data ./vendor/bin/drush en admin_toolbar admin_toolbar_tools pathauto metatag -y

print_status "✅ Essential modules enabled"

print_step "9. CONFIGURING DRUPAL SETTINGS"

# Add development settings to settings.php
print_status "Adding configuration sync directory to settings.php..."
cat >> "$DRUPAL_ROOT/web/sites/default/settings.php" <<'EOF'

/**
 * Configuration sync directory
 */
$settings['config_sync_directory'] = '../config/sync';

/**
 * Hash salt
 */
$settings['hash_salt'] = file_get_contents('/var/www/html/forseti/web/sites/default/salt.txt');

/**
 * Private file path
 */
$settings['file_private_path'] = '/var/private/forseti';
EOF

# Create hash salt
openssl rand -base64 32 > "$DRUPAL_ROOT/web/sites/default/salt.txt"
chmod 444 "$DRUPAL_ROOT/web/sites/default/salt.txt"
chown www-data:www-data "$DRUPAL_ROOT/web/sites/default/salt.txt"

# Create private files directory
mkdir -p /var/private/forseti
chown -R www-data:www-data /var/private/forseti
chmod -R 775 /var/private/forseti

print_status "✅ Drupal settings configured"

print_step "10. CLEARING CACHE AND FINAL VERIFICATION"

cd "$DRUPAL_ROOT"
sudo -u www-data ./vendor/bin/drush cache:rebuild

print_status "✅ Cache cleared"

# Get one-time login link
print_status "Generating one-time login link..."
LOGIN_LINK=$(sudo -u www-data ./vendor/bin/drush user:login --uri="http://${DOMAIN_NAME}")

echo ""
echo "════════════════════════════════════════════════════════════════"
echo "🎉 FORSETI.LIFE PRODUCTION SETUP COMPLETED SUCCESSFULLY!"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "📋 Site Information:"
echo "  Site Name: Forseti"
echo "  Domain: ${DOMAIN_NAME}"
echo "  Drupal Root: ${DRUPAL_ROOT}"
echo "  Database: ${DB_NAME}"
echo ""
echo "👤 Admin Credentials:"
echo "  Username: ${ADMIN_USER}"
echo "  Password: ${ADMIN_PASSWORD}"
echo "  Email: ${ADMIN_EMAIL}"
echo ""
echo "🔗 One-time Login Link:"
echo "  ${LOGIN_LINK}"
echo ""
echo "📁 Important Directories:"
echo "  Drupal Root: ${DRUPAL_ROOT}"
echo "  Web Root: ${DRUPAL_ROOT}/web"
echo "  Config Sync: ${DRUPAL_ROOT}/config/sync"
echo "  Custom Modules: ${DRUPAL_ROOT}/web/modules/custom"
echo "  Custom Themes: ${DRUPAL_ROOT}/web/themes/custom"
echo "  Private Files: /var/private/forseti"
echo ""
echo "📝 Apache Configuration:"
echo "  Config File: ${VHOST_FILE}"
echo "  Error Log: /var/log/apache2/${SITE_NAME}_error.log"
echo "  Access Log: /var/log/apache2/${SITE_NAME}_access.log"
echo ""
echo "🔧 Next Steps:"
echo "  1. Point DNS for ${DOMAIN_NAME} to this server"
echo "  2. Configure SSL certificate (certbot --apache -d ${DOMAIN_NAME})"
echo "  3. Update GitHub secrets for automated deployment:"
echo "     - HOST: [server IP]"
echo "     - USERNAME: ubuntu (or your SSH user)"
echo "     - PRIVATE_KEY: [SSH private key]"
echo "     - GITHUB_PAT: [GitHub Personal Access Token]"
echo "  4. Push code to main branch to trigger deployment"
echo ""
echo "🚀 Site is ready for deployment from GitHub!"
echo "════════════════════════════════════════════════════════════════"
echo ""
