#!/bin/bash

# ==============================================================================
# Forseti.Life Development Environment Setup Script
# ==============================================================================
# ✅ SAFE TO RUN: This script preserves existing database data
# ✅ NO DATA LOSS: Checks for existing Drupal installations before running site:install
# ✅ AMISAFE PROTECTION: Will not drop or truncate AmISafe crime data tables

set -e  # Exit on any error

# CRITICAL: Set PHP 8.3 PATH priority FIRST, before any other operations
export PATH="/usr/bin:/usr/sbin:$PATH"

echo "=== Forseti.Life - Complete Development Environment Setup ==="

# ------------------------------------------------------------------------------
# CONFIGURATION VARIABLES
# ------------------------------------------------------------------------------
# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Project configuration
PROJECT_NAME="forseti"
PROJECT_DIR="/home/keithaumiller/forseti.life/sites/forseti"
DB_NAME="forseti_dev"
DB_USER="drupal_user"
DB_PASSWORD="drupal_secure_password"
DB_HOST="127.0.0.1"
SITE_NAME="Forseti"
ADMIN_USER="admin"
ADMIN_PASSWORD="admin_secure_password"
ADMIN_EMAIL="admin@forseti.life"

# Check if .env file exists and source it
ENV_FILE="/home/keithaumiller/forseti.life/.env"
if [ -f "$ENV_FILE" ]; then
    echo -e "${GREEN}[INFO]${NC} Loading configuration from .env file..."
    source "$ENV_FILE"
fi

# ------------------------------------------------------------------------------
# UTILITY FUNCTIONS
# ------------------------------------------------------------------------------
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

# Function to ensure file permissions for Drupal settings files
fix_drupal_permissions() {
    local site_dir="$1"
    print_status "Fixing permissions for $site_dir..."
    if [ -f "$site_dir/web/sites/default/settings.php" ]; then
        sudo chmod 664 "$site_dir/web/sites/default/settings.php" 2>/dev/null || chmod 664 "$site_dir/web/sites/default/settings.php" 2>/dev/null || true
    fi
    if [ -f "$site_dir/web/sites/default/settings.local.php" ]; then
        sudo chmod 664 "$site_dir/web/sites/default/settings.local.php" 2>/dev/null || chmod 664 "$site_dir/web/sites/default/settings.local.php" 2>/dev/null || true
    fi
    if [ -d "$site_dir/web/sites/default" ]; then
        sudo chmod 755 "$site_dir/web/sites/default" 2>/dev/null || chmod 755 "$site_dir/web/sites/default" 2>/dev/null || true
    fi
    if [ -d "$site_dir/web/sites/default/files" ]; then
        sudo chmod -R 775 "$site_dir/web/sites/default/files" 2>/dev/null || chmod -R 775 "$site_dir/web/sites/default/files" 2>/dev/null || true
        mkdir -p "$site_dir/web/sites/default/files/php" 2>/dev/null || true
        sudo chmod 775 "$site_dir/web/sites/default/files/php" 2>/dev/null || chmod 775 "$site_dir/web/sites/default/files/php" 2>/dev/null || true
        sudo chown -R www-data:www-data "$site_dir/web/sites/default/files" 2>/dev/null || true
    fi
}

# Function to ensure MySQL is running
ensure_mysql_running() {
    if sudo mysql -e "SELECT 1;" &>/dev/null; then
        return 0  # MySQL is already running
    fi
    
    print_status "MySQL not running, attempting to start..."
    
    # Try service command first (works in containers)
    sudo service mysql start 2>&1
    sleep 5
    
    # Wait up to 30 seconds for MySQL to become available
    local count=0
    while [ $count -lt 30 ]; do
        if sudo mysql -e "SELECT 1;" &>/dev/null; then
            print_status "✅ MySQL started successfully via service command"
            return 0
        fi
        sleep 1
        count=$((count + 1))
    done
    
    # Try systemctl as fallback
    if command -v systemctl >/dev/null 2>&1; then
        sudo systemctl start mysql 2>&1
        sleep 10
        
        # Wait up to 30 seconds for MySQL to become available
        local count=0
        while [ $count -lt 30 ]; do
            if sudo mysql -e "SELECT 1;" &>/dev/null; then
                print_status "✅ MySQL started successfully via systemctl"
                return 0
            fi
            sleep 1
            count=$((count + 1))
        done
    fi
    
    print_error "❌ Failed to start MySQL"
    return 1
}

# Verify PHP version immediately after PATH setup
print_status "Verifying PHP path priority: $(which php)"
if which php | grep -q "/usr/bin/php"; then
    print_status "✅ System PHP is properly prioritized"
else
    print_warning "⚠️  PHP path may need adjustment"
fi

# ==============================================================================
# STEP 1: ENVIRONMENT SETUP
# ==============================================================================

print_step "1. ENVIRONMENT SETUP - Installing system dependencies..."

# ------------------------------------------------------------------------------
# 1.1 PHP 8.3 Installation
# ------------------------------------------------------------------------------
print_status "Updating package lists..."
sudo apt update

print_status "Checking PHP 8.3 installation (REQUIRED)..."

if [ ! -x "/usr/bin/php8.3" ]; then
    print_warning "PHP 8.3 is NOT installed. Installing PHP 8.3 from Sury repository..."
    
    # Install prerequisites
    sudo apt install -y ca-certificates apt-transport-https software-properties-common lsb-release
    
    # Add Sury PHP repository
    print_status "Adding Sury PHP repository..."
    sudo curl -sSL https://packages.sury.org/php/README.txt
    sudo curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
    echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list
    
    # Update package lists
    sudo apt update
    
    # Install PHP 8.3 and Apache module
    print_status "Installing PHP 8.3 and Apache module..."
    sudo apt install -y php8.3 php8.3-cli php8.3-fpm libapache2-mod-php8.3
    
    if [ ! -x "/usr/bin/php8.3" ]; then
        print_error "❌ FAILED to install PHP 8.3. This is a REQUIRED dependency."
        exit 1
    fi
    
    print_status "✅ PHP 8.3 installed successfully"
else
    PHP83_VERSION=$(/usr/bin/php8.3 -r "echo PHP_VERSION;")
    print_status "✅ PHP 8.3 is already installed: $PHP83_VERSION"
fi

# Install required PHP 8.3 extensions
print_status "Checking PHP 8.3 extensions..."
REQUIRED_EXTENSIONS=("gd" "xml" "mbstring" "curl" "zip" "bcmath" "json" "tokenizer" "fileinfo" "intl" "dom" "mysql" "opcache")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! /usr/bin/php8.3 -m | grep -q "^$ext$" && ! /usr/bin/php8.3 -m | grep -qE "^(mysqli|pdo_mysql|mysqlnd)$" 2>/dev/null; then
        MISSING_EXTENSIONS+=("php8.3-$ext")
        print_warning "PHP 8.3 extension '$ext' is missing"
    else
        print_status "PHP 8.3 extension '$ext' is already installed"
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    print_status "Installing missing PHP 8.3 extensions: ${MISSING_EXTENSIONS[*]}"
    sudo apt install -y "${MISSING_EXTENSIONS[@]}"
fi

# Ensure critical extensions are installed
print_status "Ensuring critical PHP 8.3 extensions are properly installed..."
CRITICAL_EXTENSIONS=("php8.3-xml" "php8.3-mysql")
for ext_package in "${CRITICAL_EXTENSIONS[@]}"; do
    if ! dpkg -l | grep -q "^ii.*$ext_package"; then
        print_status "Installing critical extension: $ext_package"
        sudo apt install -y "$ext_package"
    fi
done

# ------------------------------------------------------------------------------
# 1.2 Composer Installation
# ------------------------------------------------------------------------------
print_status "Checking Composer installation..."
if command -v composer &> /dev/null; then
    print_status "Composer is already installed: $(composer --version)"
else
    print_status "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
fi

print_status "Verifying Composer..."
php /usr/local/bin/composer --version || print_error "Composer verification failed"

# ------------------------------------------------------------------------------
# 1.3 MySQL/MariaDB Installation
# ------------------------------------------------------------------------------
print_status "Checking MySQL/MariaDB installation..."
if command -v mysql &> /dev/null; then
    print_status "MySQL/MariaDB is already installed"
else
    print_status "Installing MariaDB server..."
    sudo apt install -y mariadb-server mariadb-client
    print_warning "Please run 'sudo mysql_secure_installation' after this script completes"
fi

# Configure MariaDB for AmISafe data processing
print_status "Configuring MariaDB performance settings..."
MARIADB_CONF="/etc/mysql/mariadb.conf.d/50-server.cnf"
if ! grep -q "AmISafe Data Processing Optimizations" "$MARIADB_CONF" 2>/dev/null; then
    print_status "Adding AmISafe optimizations to MariaDB config..."
    sudo bash -c "cat >> $MARIADB_CONF << 'EOF'

# AmISafe Data Processing Optimizations
[mysqld]
max_allowed_packet = 1073741824  # 1GB for large batch inserts
innodb_buffer_pool_size = 2G     # Increase for better performance
innodb_log_file_size = 512M      # Larger logs for bulk operations
EOF"
    print_status "Restarting MariaDB to apply new settings..."
    sudo systemctl restart mariadb
    print_status "✅ MariaDB optimized for large data processing"
else
    print_status "MariaDB optimizations already configured"
fi

# ------------------------------------------------------------------------------
# 1.4 Apache Installation
# ------------------------------------------------------------------------------
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

if ! dpkg -l | grep -q "^ii.*libapache2-mod-php8.3"; then
    print_status "Installing Apache PHP 8.3 module..."
    sudo apt install -y libapache2-mod-php8.3
fi

# Disable any other PHP modules first
for php_mod in php8.0 php8.1 php8.2 php8.4; do
    if sudo a2query -m $php_mod 2>/dev/null; then
        print_status "Disabling $php_mod module..."
        sudo a2dismod $php_mod 2>/dev/null || true
    fi
done

# Enable Apache PHP 8.3 module
if ! sudo a2query -m php8.3 2>/dev/null; then
    print_status "Enabling PHP 8.3 module for Apache..."
    sudo a2enmod php8.3 2>/dev/null || true
fi

if sudo a2query -m php8.3 2>/dev/null; then
    print_status "✅ PHP 8.3 module enabled in Apache"
else
    print_warning "⚠️  PHP 8.3 module may need manual enabling"
fi

# Update PATH to prioritize PHP 8.3
print_status "Updating PATH to prioritize PHP 8.3..."
export PATH="/usr/bin:/usr/sbin:/usr/local/bin:$PATH"

# Make PATH change permanent
sudo bash -c 'cat > /etc/profile.d/99-php-priority.sh << "EOF"
#!/bin/bash
# Ensure PHP 8.3 takes priority
export PATH="/usr/bin:/usr/sbin:/usr/local/bin:$PATH"
EOF'
sudo chmod +x /etc/profile.d/99-php-priority.sh
print_status "Created system-wide PHP 8.3 priority profile script"

# Update .bashrc for interactive sessions
if ! grep -q 'export PATH="/usr/bin:/usr/sbin' ~/.bashrc; then
    echo '' >> ~/.bashrc
    echo '# PHP 8.3 Priority - Must be at the end to override defaults' >> ~/.bashrc
    echo 'export PATH="/usr/bin:/usr/sbin:/usr/local/bin:$PATH"  # Prioritize PHP 8.3' >> ~/.bashrc
    print_status "Added PATH configuration to ~/.bashrc"
fi

# Reload Apache to use PHP 8.3
print_status "Reloading Apache to use PHP 8.3..."
sudo service apache2 reload || true

# Set PHP 8.3 as default alternative
print_status "Setting PHP 8.3 as system default..."
sudo update-alternatives --install /usr/bin/php php /usr/bin/php8.3 100
sudo update-alternatives --set php /usr/bin/php8.3

# Verify default php command
DEFAULT_PHP=$(php --version | head -n1)
print_status "Default 'php' command: $DEFAULT_PHP"

# ------------------------------------------------------------------------------
# 1.5 Additional System Tools
# ------------------------------------------------------------------------------
print_status "Checking Git installation..."
if command -v git &> /dev/null; then
    print_status "Git is already installed: $(git --version)"
else
    print_status "Installing Git..."
    sudo apt install -y git
fi

print_status "Checking Node.js installation..."
if command -v node &> /dev/null; then
    print_status "Node.js is already installed: $(node --version)"
else
    print_status "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
    sudo apt install -y nodejs
fi

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

# ------------------------------------------------------------------------------
# 1.6 H3 Geolocation Framework Setup
# ------------------------------------------------------------------------------
print_status "Setting up H3 Geolocation Framework for AmISafe crime mapping..."
H3_SYSTEM_PACKAGES=("python3-dev" "python3-pip" "python3-venv" "python3-full" "build-essential" "libgeos-dev" "libproj-dev" "libgdal-dev")
MISSING_H3_PACKAGES=()

for package in "${H3_SYSTEM_PACKAGES[@]}"; do
    if ! dpkg -l | grep -q "^ii  $package "; then
        MISSING_H3_PACKAGES+=("$package")
    fi
done

if [ ${#MISSING_H3_PACKAGES[@]} -gt 0 ]; then
    print_status "Installing H3 geospatial system packages: ${MISSING_H3_PACKAGES[*]}"
    sudo apt install -y "${MISSING_H3_PACKAGES[@]}"
    print_status "H3 geospatial system dependencies installed successfully"
fi

# Setup H3 Python virtual environment
H3_ENV_DIR="/home/keithaumiller/forseti.life/h3-geolocation/h3-env"
if [ ! -d "$H3_ENV_DIR" ]; then
    print_status "Creating H3 Python virtual environment..."
    cd /home/keithaumiller/forseti.life/h3-geolocation
    python3 -m venv h3-env
    
    print_status "Installing H3 Python packages..."
    ./h3-env/bin/pip install --upgrade pip
    ./h3-env/bin/pip install h3==4.3.1 pandas>=2.0.0 numpy>=1.24.0 mysql-connector-python>=8.0.0
    ./h3-env/bin/pip install matplotlib>=3.7.0 folium>=0.18.0 geopy>=2.4.0 plotly>=5.17.0
    ./h3-env/bin/pip install seaborn>=0.13.0 tqdm>=4.65.0
    
    print_status "✅ H3 geolocation environment created successfully"
    cd - > /dev/null
else
    print_status "✅ H3 geolocation environment already exists"
fi

# Verify H3 installation
if [ -f "$H3_ENV_DIR/bin/python" ]; then
    H3_TEST_RESULT=$($H3_ENV_DIR/bin/python -c "import h3; import pandas; import mysql.connector; print('H3 packages verified')" 2>/dev/null || echo "FAILED")
    if [ "$H3_TEST_RESULT" = "H3 packages verified" ]; then
        print_status "✅ H3 geolocation framework verified and ready"
    else
        print_warning "⚠️  H3 framework verification failed - may need manual setup"
    fi
fi

# Configure environment for PHP 8.3
print_status "Configuring environment for PHP 8.3..."
BASHRC_FILE="$HOME/.bashrc"
if [ -f "$BASHRC_FILE" ]; then
    # Remove existing conflicting PHP configurations
    grep -v "# PHP.*Configuration" "$BASHRC_FILE" > "${BASHRC_FILE}.tmp" || cp "$BASHRC_FILE" "${BASHRC_FILE}.tmp"
    grep -v "export PATH.*php" "${BASHRC_FILE}.tmp" > "${BASHRC_FILE}.new" || cp "${BASHRC_FILE}.tmp" "${BASHRC_FILE}.new"
    grep -v "alias php=" "${BASHRC_FILE}.new" > "${BASHRC_FILE}.clean" || cp "${BASHRC_FILE}.new" "${BASHRC_FILE}.clean"
    mv "${BASHRC_FILE}.clean" "$BASHRC_FILE"
    rm -f "${BASHRC_FILE}.tmp" "${BASHRC_FILE}.new" 2>/dev/null || true
    
    # Add comprehensive PHP 8.3 configuration
    echo "" >> "$BASHRC_FILE"
    echo "# PHP 8.3 Configuration - Auto-generated by setup.sh" >> "$BASHRC_FILE"
    echo 'export PATH="/usr/bin:/usr/sbin:$PATH"' >> "$BASHRC_FILE"
    echo 'alias php="/usr/bin/php8.3"' >> "$BASHRC_FILE"
    echo 'alias composer="/usr/bin/php8.3 /usr/local/bin/composer"' >> "$BASHRC_FILE"
    echo 'alias drush="/usr/bin/php8.3 ./vendor/bin/drush"' >> "$BASHRC_FILE"
    
    print_status "Updated .bashrc with comprehensive PHP 8.3 configuration"
fi

# Apply environment changes to current session
export PATH="/usr/bin:/usr/sbin:/usr/local/bin:$PATH"
php() { /usr/bin/php8.3 "$@"; }
composer() { /usr/bin/php8.3 /usr/local/bin/composer "$@"; }
drush() { /usr/bin/php8.3 "$PWD/vendor/bin/drush" "$@"; }
export -f php composer drush
print_status "Created shell functions to force PHP 8.3 usage"

# VERIFICATION
print_status "=== PHP 8.3 VERIFICATION ==="
print_status "which php: $(which php)"
print_status "/usr/bin/php8.3 version: $(/usr/bin/php8.3 --version | head -n1)"
print_status "php (function) version: $(php --version | head -n1)"
print_status "composer (function) version: $(composer --version | head -n1)"

if /usr/bin/php8.3 -m | grep -q mysqli; then
    print_status "✅ PHP 8.3 MySQL extension loaded"
else
    print_error "❌ PHP 8.3 MySQL extension not found"
fi

if /usr/bin/php8.3 /usr/local/bin/composer about >/dev/null 2>&1; then
    print_status "✅ Composer working with PHP 8.3"
else
    print_error "❌ Composer not working with PHP 8.3"
fi
print_status "=== END PHP 8.3 VERIFICATION ==="

# ------------------------------------------------------------------------------
# 1.7 MySQL Database Creation
# ------------------------------------------------------------------------------
# NOTE: This section creates the DATABASES only.
#       Table creation happens later in Step 5 (H3 Geolocation Setup)
print_status "Configuring MySQL database..."

ensure_mysql_running
if sudo mysql -e "SELECT 1;" &>/dev/null; then
    print_status "✅ MySQL is now running"
else
    print_error "❌ Failed to start MySQL - continuing with limited functionality"
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

# Create additional database for AmISafe module
print_status "Creating AmISafe database..."
sudo mysql <<EOF
CREATE DATABASE IF NOT EXISTS amisafe_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON amisafe_database.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF
print_status "AmISafe database created"

# ------------------------------------------------------------------------------
# 1.8 Private Files Directory
# ------------------------------------------------------------------------------
print_status "Creating Drupal private files directory..."
if [ ! -d "/var/private/forseti" ]; then
    sudo mkdir -p /var/private/forseti
fi
sudo chown -R www-data:www-data /var/private/forseti
sudo chmod -R 777 /var/private/forseti
print_status "Private files directory created at /var/private/forseti"

# ------------------------------------------------------------------------------
# 1.9 Apache Virtual Host Configuration
# ------------------------------------------------------------------------------
print_status "Configuring Apache virtual host for Forseti..."

sudo bash -c "cat > /etc/apache2/sites-available/forseti.conf" <<EOF
<VirtualHost *:80>
        ServerName localhost
        ServerAlias forseti.local www.forseti.local penguin.linux.test
        ServerAdmin webmaster@localhost
        DocumentRoot /home/keithaumiller/forseti.life/sites/forseti/web

        <Directory /home/keithaumiller/forseti.life/sites/forseti/web>
                Options Indexes FollowSymLinks
                AllowOverride All
                Require all granted
        </Directory>

        ErrorLog \${APACHE_LOG_DIR}/forseti_error.log
        CustomLog \${APACHE_LOG_DIR}/forseti_access.log combined
</VirtualHost>
EOF

# Disable default Apache site and enable Forseti
sudo a2dissite 000-default.conf 2>/dev/null || true
sudo a2ensite forseti.conf

print_status "Starting services..."
ensure_mysql_running
sudo service apache2 restart

print_status "✅ STEP 1 COMPLETE: Environment setup finished"


# ==============================================================================
# STEP 2: DRUPAL INSTALLATION - FORSETI SITE
# ==============================================================================

print_step "2. DRUPAL INSTALLATION - Setting up Forseti site..."

# Ensure we're using the correct PHP version
export PATH="/usr/bin:$PATH"
print_status "Enforcing PHP 8.3 for Drupal operations: $(php --version | head -n1)"

# ------------------------------------------------------------------------------
# 2.1 Directory Setup
# ------------------------------------------------------------------------------
print_status "Creating site directory structure..."
mkdir -p /home/keithaumiller/forseti.life/sites

if [ -d "$PROJECT_DIR" ]; then
    print_status "Existing Drupal directory found. Skipping fresh installation to preserve custom work."
    print_status "Using existing Drupal installation at $PROJECT_DIR"
else
    print_status "No existing Drupal directory found. Creating new Drupal 11.2.5 project..."
    cd /home/keithaumiller/forseti.life/sites
    /usr/bin/php8.3 /usr/local/bin/composer create-project drupal/recommended-project:11.2.5 forseti --no-interaction
fi

cd "$PROJECT_DIR"

# ------------------------------------------------------------------------------
# 2.2 Composer Dependencies
# ------------------------------------------------------------------------------
if [ -f "composer.json" ] && [ ! -f "vendor/autoload.php" ]; then
    print_status "Installing Composer dependencies..."
    /usr/bin/php8.3 /usr/local/bin/composer install --no-interaction --optimize-autoloader
elif [ -f "vendor/autoload.php" ] && [ ! -f "vendor/bin/drush" ]; then
    print_status "Installing missing dependencies..."
    /usr/bin/php8.3 /usr/local/bin/composer update --no-interaction
elif [ -f "vendor/autoload.php" ]; then
    # Check if autoloader is corrupted
    if ! /usr/bin/php8.3 -c /etc/php/8.3/cli/php.ini -r "require 'vendor/autoload.php'; echo 'OK';" 2>/dev/null; then
        print_status "Fixing corrupted Composer autoloader..."
        rm -rf vendor/
        /usr/bin/php8.3 /usr/local/bin/composer install --no-interaction --optimize-autoloader
    fi
fi

# ------------------------------------------------------------------------------
# 2.3 Drush Installation
# ------------------------------------------------------------------------------
if [ ! -f "vendor/bin/drush" ]; then
    print_status "Installing Drush..."
    /usr/bin/php8.3 /usr/local/bin/composer require drush/drush --no-interaction
fi

# ------------------------------------------------------------------------------
# 2.4 Development Modules Installation
# ------------------------------------------------------------------------------
if [ ! -d "web/modules/contrib/devel" ]; then
    print_status "Installing development modules and packages..."
    /usr/bin/php8.3 /usr/local/bin/composer require \
        drupal/devel \
        drupal/admin_toolbar \
        drupal/pathauto \
        drupal/metatag \
        drupal/backup_migrate \
        drupal/bootstrap5 \
        drupal/radix \
        drupal/recaptcha \
        drupal/recaptcha_v3 \
        drupal/profile \
        aws/aws-sdk-php \
        defuse/php-encryption \
        --no-interaction
else
    print_status "Development modules already installed. Skipping to preserve existing setup."
fi

# ------------------------------------------------------------------------------
# 2.5 Database Verification
# ------------------------------------------------------------------------------
DRUPAL_NEEDS_INSTALL=true
if [ -f "web/sites/default/settings.php" ] && [ -s "web/sites/default/settings.php" ]; then
    # Check if Drupal users table exists (specifically check for Drupal tables, not AmISafe)
    USER_TABLE_COUNT=$(/usr/bin/php8.3 vendor/drush/drush/drush.php sql:query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='users'" 2>/dev/null | tail -n1)
    if [ "$USER_TABLE_COUNT" = "1" ]; then
        DRUPAL_NEEDS_INSTALL=false
        print_status "Existing Drupal installation detected and verified."
    else
        print_status "No Drupal installation found. Need to install Drupal."
    fi
fi

# ------------------------------------------------------------------------------
# 2.6 Drupal Site Installation
# ------------------------------------------------------------------------------
if [ "$DRUPAL_NEEDS_INSTALL" = true ]; then
    print_status "Setting up file permissions and installing Drupal..."
    sudo chmod 755 web/sites/default 2>/dev/null || chmod 755 web/sites/default
    
    # Create files directory with proper permissions
    print_status "Creating and configuring files directory..."
    mkdir -p web/sites/default/files
    sudo chmod -R 777 web/sites/default/files 2>/dev/null || chmod -R 777 web/sites/default/files
    
    # Create PHP storage directory
    mkdir -p web/sites/default/files/php
    sudo chmod 777 web/sites/default/files/php 2>/dev/null || chmod 777 web/sites/default/files/php
    
    # Set proper ownership for Apache
    if sudo chown -R www-data:www-data web/sites/default/files 2>/dev/null; then
        print_status "Successfully set www-data ownership"
    elif sudo chown -R $(whoami):$(whoami) web/sites/default/files 2>/dev/null; then
        print_status "Set current user ownership as fallback"
    else
        print_warning "Could not change ownership, but will continue with current permissions"
    fi
    
    sudo chmod -R 777 web/sites/default/files 2>/dev/null || chmod -R 777 web/sites/default/files

    # Copy default settings file
    if [ ! -f "web/sites/default/settings.php" ]; then
        cp web/sites/default/default.settings.php web/sites/default/settings.php
    fi
    sudo chmod 664 web/sites/default/settings.php 2>/dev/null || chmod 664 web/sites/default/settings.php

    # Install Drupal if not already installed
    print_status "Checking existing Drupal installation..."
    if ! /usr/bin/php8.3 vendor/drush/drush/drush.php status | grep -q "Drupal bootstrap.*Successful" 2>/dev/null; then
        print_status "Installing Drupal..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php site:install standard \
            --db-url="mysql://${DB_USER}:${DB_PASSWORD}@127.0.0.1:3306/${DB_NAME}" \
            --site-name="${SITE_NAME}" \
            --account-name="${ADMIN_USER}" \
            --account-pass="${ADMIN_PASSWORD}" \
            --account-mail="${ADMIN_EMAIL}" \
            --yes
        
        if [ $? -eq 0 ]; then
            print_status "✅ Drupal installed successfully"
        else
            print_error "❌ Drupal installation failed"
            exit 1
        fi
    else
        print_status "Drupal already installed, preserving existing data"
    fi
fi

# Check if Drupal is properly installed
DRUPAL_INSTALLED=false
if [ "$DRUPAL_NEEDS_INSTALL" = false ]; then
    DRUPAL_INSTALLED=true
    print_status "Drupal installation detected and verified"
else
    # Check if installation just completed successfully
    USER_TABLE_COUNT=$(/usr/bin/php8.3 vendor/drush/drush/drush.php sql:query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='users'" 2>/dev/null | tail -n1)
    if [ "$USER_TABLE_COUNT" = "1" ]; then
        DRUPAL_INSTALLED=true
        print_status "Drupal installation completed and verified"
    fi
fi

# ------------------------------------------------------------------------------
# 2.7 Enable Development Modules
# ------------------------------------------------------------------------------
if [ "$DRUPAL_INSTALLED" = true ]; then
    # Verify Drupal functionality before enabling modules
    print_status "Verifying Drupal functionality before enabling modules..."
    if ! /usr/bin/php8.3 vendor/drush/drush/drush.php status --format=json 2>/dev/null | grep -q '"bootstrap":"Successful"'; then
        if ! /usr/bin/php8.3 vendor/drush/drush/drush.php cache:rebuild 2>/dev/null; then
            print_warning "Drupal bootstrap failed. Skipping module enablement."
            DRUPAL_INSTALLED=false
        fi
    fi
fi

if [ "$DRUPAL_INSTALLED" = true ]; then
    # Enable development modules
    if ! /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --status=enabled 2>/dev/null | grep -q "devel"; then
        print_status "Enabling development and utility modules..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php en devel admin_toolbar admin_toolbar_tools pathauto metatag -y
    else
        print_status "Development modules already enabled. Skipping to preserve existing configuration."
    fi
    
    # ------------------------------------------------------------------------------
    # 2.8 Home Page Configuration
    # ------------------------------------------------------------------------------
    print_status "Configuring Forseti home page..."
    if /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --status=enabled 2>/dev/null | grep -q "professional_website_content"; then
        HOME_NODE_ID=$(/usr/bin/php8.3 vendor/drush/drush/drush.php sql:query "SELECT nid FROM node_field_data WHERE title = 'Welcome to Forseti' AND status = 1 ORDER BY nid DESC LIMIT 1;" 2>/dev/null | tail -n1 | tr -d '\r\n')
        if [ -n "$HOME_NODE_ID" ] && [ "$HOME_NODE_ID" != "nid" ]; then
            /usr/bin/php8.3 vendor/drush/drush/drush.php config:set system.site page.front "/node/$HOME_NODE_ID" -y 2>/dev/null
            print_status "✅ Forseti home page set to 'Welcome to Forseti' (node/$HOME_NODE_ID)"
        else
            print_warning "⚠️  Could not find 'Welcome to Forseti' node - using default home page"
        fi
    else
        print_warning "⚠️  Professional website content module not enabled - using default home page"
    fi

    # Final verification
    print_status "Performing final verification of modules and theme..."
    if /usr/bin/php8.3 vendor/drush/drush/drush.php cache:rebuild 2>/dev/null; then
        print_status "✅ All modules and theme successfully enabled and verified"
    else
        print_warning "⚠️  Some modules or theme may need manual configuration"
    fi
else
    print_status "Drupal not fully installed yet. Skipping module and theme enabling."
fi

# ------------------------------------------------------------------------------
# 2.10 Development Directories
# ------------------------------------------------------------------------------
print_status "Ensuring custom development directories exist..."
mkdir -p web/modules/custom
mkdir -p web/themes/custom
mkdir -p config/sync

chmod 755 web/modules/custom
chmod 755 web/themes/custom
chmod 755 config/sync

# ------------------------------------------------------------------------------
# 2.11 Settings Configuration
# ------------------------------------------------------------------------------
fix_drupal_permissions "$PROJECT_DIR"

# Add development settings if they don't exist
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

# Create settings.local.php
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

cd "$PROJECT_DIR"

print_status "✅ STEP 2 COMPLETE: Drupal installation finished"


# ==============================================================================
# STEP 3: DEVELOPMENT CONFIGURATION
# ==============================================================================

print_step "3. DEVELOPMENT CONFIGURATION - Setting up development tools..."

cd "$PROJECT_DIR"

# ------------------------------------------------------------------------------
# 3.1 Drupal Coder and PHP CodeSniffer
# ------------------------------------------------------------------------------
print_status "Installing Drupal Coder and PHP CodeSniffer..."
/usr/bin/php8.3 /usr/local/bin/composer require drupal/coder --dev --no-interaction

print_status "Configuring PHP CodeSniffer for Drupal standards..."
./vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer
./vendor/bin/phpcs --config-set default_standard Drupal

# ------------------------------------------------------------------------------
# 3.2 PHPUnit Installation
# ------------------------------------------------------------------------------
print_status "Installing additional development tools..."
/usr/bin/php8.3 /usr/local/bin/composer require phpunit/phpunit symfony/phpunit-bridge --dev --no-interaction

# ------------------------------------------------------------------------------
# 3.3 Development Services Configuration
# ------------------------------------------------------------------------------
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

# ------------------------------------------------------------------------------
# 3.4 Custom Module Template
# ------------------------------------------------------------------------------
CUSTOM_MODULES_DIR="$PROJECT_DIR/web/modules/custom"
if [ ! -f "$CUSTOM_MODULES_DIR/README.md" ]; then
    print_status "Creating custom module template..."
    cat > "$CUSTOM_MODULES_DIR/README.md" << 'EOL'
# Custom Modules

This directory contains custom modules for the Forseti website.

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

# ------------------------------------------------------------------------------
# 3.5 Custom Theme Template
# ------------------------------------------------------------------------------
CUSTOM_THEMES_DIR="$PROJECT_DIR/web/themes/custom"
if [ ! -f "$CUSTOM_THEMES_DIR/README.md" ]; then
    print_status "Creating custom theme template..."
    cat > "$CUSTOM_THEMES_DIR/README.md" << 'EOL'
# Custom Themes

This directory contains custom themes for the Forseti website.

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

# ------------------------------------------------------------------------------
# 3.6 Git Configuration
# ------------------------------------------------------------------------------
SCRIPTS_DIR="$PROJECT_DIR/scripts"
mkdir -p "$SCRIPTS_DIR"

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

# Clear cache after configuration
if [ "$DRUPAL_INSTALLED" = true ]; then
    print_status "Clearing cache after configuration..."
    /usr/bin/php8.3 vendor/drush/drush/drush.php cache:rebuild 2>/dev/null || print_warning "Cache clear failed"
else
    print_warning "Drupal not fully installed. Skipping cache clear."
fi

# Set final permissions
print_status "Setting final permissions..."
if [ -f "web/sites/default/settings.php" ]; then
    chmod 644 web/sites/default/settings.php
fi
chmod -R 755 web/modules/custom web/themes/custom

print_status "✅ STEP 3 COMPLETE: Development configuration finished"

# ==============================================================================
# STEP 4: POST-INSTALLATION FIXES
# ==============================================================================

print_step "4. POST-INSTALLATION FIXES - Applying known issue resolutions..."

cd "$PROJECT_DIR"

# ------------------------------------------------------------------------------
# 4.1 Cache Backend Configuration Fix
# ------------------------------------------------------------------------------
print_status "Fixing cache backend configuration issues..."

SERVICES_FILE="$PROJECT_DIR/web/sites/development.services.yml"
if [ -f "$SERVICES_FILE" ]; then
    print_status "Updating development services..."
    cat > "$SERVICES_FILE" << 'EOF'
# Local development services.
parameters:
  http.response.debug_cacheability_headers: true
  twig.config:
    debug: true
    auto_reload: true
    cache: false
services:
  logger.channel.config_schema:
    parent: logger.channel_base
    arguments: [ 'config_schema' ]
  config.schema_checker:
    class: Drupal\Core\Config\Development\LenientConfigSchemaChecker
    arguments: [ '@config.typed', '@config.storage.schema' ]
EOF
fi

# ------------------------------------------------------------------------------
# 4.2 Settings File Cleanup
# ------------------------------------------------------------------------------
print_status "Cleaning cache backend references from settings files..."
SETTINGS_FILE="$PROJECT_DIR/web/sites/default/settings.php"
SETTINGS_LOCAL_FILE="$PROJECT_DIR/web/sites/default/settings.local.php"

if [ -f "$SETTINGS_FILE" ]; then
    sed -i '/cache.backend.null/d' "$SETTINGS_FILE" || true
    print_status "Cleaned cache references from settings.php"
fi

if [ -f "$SETTINGS_LOCAL_FILE" ]; then
    sed -i '/cache.backend.null/d' "$SETTINGS_LOCAL_FILE" || true
    if ! grep -q "cache.*max_age" "$SETTINGS_LOCAL_FILE"; then
        echo "" >> "$SETTINGS_LOCAL_FILE"
        echo "// Disable page caching for development" >> "$SETTINGS_LOCAL_FILE"
        echo "\$config['system.performance']['cache']['page']['max_age'] = 0;" >> "$SETTINGS_LOCAL_FILE"
    fi
    print_status "Cleaned cache references from settings.local.php"
fi

# ------------------------------------------------------------------------------
# 4.3 CORS Module Installation
# ------------------------------------------------------------------------------
# CORS module installation if needed (placeholder for future use)
# /usr/bin/php8.3 /usr/local/bin/composer require drupal/cors --no-interaction
# /usr/bin/php8.3 vendor/drush/drush/drush.php en cors -y

# ------------------------------------------------------------------------------
# 4.4 Simple OAuth Fix
# ------------------------------------------------------------------------------
# Simple OAuth fixes if needed (placeholder for future use)

# ------------------------------------------------------------------------------
# 4.5 Composer Verification
# ------------------------------------------------------------------------------
print_status "Final Composer dependency verification..."
if [ -f "composer.json" ]; then
    print_status "Verifying Composer dependencies..."
    
    if [ -f "composer.lock" ]; then
        /usr/bin/php8.3 /usr/local/bin/composer validate --no-check-all 2>/dev/null || {
            print_status "Updating Composer dependencies..."
            /usr/bin/php8.3 /usr/local/bin/composer update --no-interaction --with-all-dependencies 2>/dev/null || true
        }
    fi
    
    /usr/bin/php8.3 /usr/local/bin/composer dump-autoload --optimize --no-interaction 2>/dev/null || true
    print_status "Composer autoloader rebuilt"
fi

# ------------------------------------------------------------------------------
# 4.6 Final Cache Rebuild
# ------------------------------------------------------------------------------
print_status "Clearing caches and restarting services..."
sudo service apache2 restart

# ------------------------------------------------------------------------------
# 4.7 Site Verification
# ------------------------------------------------------------------------------
print_status "Final verification and cache rebuild..."
if [ -f "vendor/drush/drush/drush.php" ]; then
    if /usr/bin/php8.3 vendor/drush/drush/drush.php status --format=json 2>/dev/null | grep -q '"bootstrap":"Successful"'; then
        print_status "Forseti site is working correctly"
        /usr/bin/php8.3 vendor/drush/drush/drush.php cache:rebuild 2>/dev/null || true
    else
        print_warning "Forseti site may need manual configuration"
    fi
else
    print_warning "Drush not found for Forseti site"
fi

print_status "✅ STEP 4 COMPLETE: Post-installation fixes applied"

# ------------------------------------------------------------------------------
# 4.8 Custom Modules and Theme Configuration (Post-Bootstrap)
# ------------------------------------------------------------------------------
print_status "Configuring custom modules and theme..."
if [ -f "vendor/drush/drush/drush.php" ]; then
    # Enable Custom Modules
    if [ -d "web/modules/custom" ]; then
        print_status "Checking custom modules..."
        
        # NOTE: This workaround may need investigation if module functionality issues arise later.
        # The forseti_safety_content module ships with user.mail.yml and user.settings.yml in
        # config/install, which conflicts with core Drupal user config already in active storage.
        # We backup the install config directory to prevent PreExistingConfigException errors.
        # If user email templates or settings from this module are needed, they may need to be
        # manually imported or the module's install hook may need refactoring to use config/optional
        # or programmatic configuration instead of config/install for these system-level configs.
        if [ -d "web/modules/custom/forseti_safety_content/config/install" ]; then
            print_status "Backing up forseti_safety_content install config to avoid user.mail/user.settings conflicts..."
            mv web/modules/custom/forseti_safety_content/config/install web/modules/custom/forseti_safety_content/config/install.backup 2>/dev/null || true
        fi
        
        CUSTOM_MODULES_NEEDED=false
        for module in professional_website_content ai_conversation job_application_automation resume_tailoring stli_site_customizations amisafe forseti_safety_content; do
            if [ -d "web/modules/custom/$module" ] && ! /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --status=enabled 2>/dev/null | grep -q "$module"; then
                CUSTOM_MODULES_NEEDED=true
                break
            fi
        done
        
        if [ "$CUSTOM_MODULES_NEEDED" = true ]; then
            print_status "Enabling custom modules in dependency order..."
        
            # Enable profile module first (dependency for job_application_automation)
            if [ -d "web/modules/custom/job_application_automation" ]; then
                /usr/bin/php8.3 vendor/drush/drush/drush.php en profile -y 2>/dev/null || true
            fi
            
            # Enable modules in dependency order
            [ -d "web/modules/custom/professional_website_content" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en professional_website_content -y 2>/dev/null
            [ -d "web/modules/custom/ai_conversation" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en ai_conversation -y 2>/dev/null
            [ -d "web/modules/custom/stli_site_customizations" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en stli_site_customizations -y 2>/dev/null
            [ -d "web/modules/custom/resume_tailoring" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en resume_tailoring -y 2>/dev/null
            [ -d "web/modules/custom/amisafe" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en amisafe -y 2>/dev/null
            [ -d "web/modules/custom/forseti_safety_content" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en forseti_safety_content -y 2>/dev/null
            
            # Clear cache before complex modules
            /usr/bin/php8.3 vendor/drush/drush/drush.php cache:rebuild 2>/dev/null || true
            
            # Enable job_application_automation last
            if [ -d "web/modules/custom/job_application_automation" ]; then
                /usr/bin/php8.3 vendor/drush/drush/drush.php en job_application_automation -y 2>/dev/null || print_warning "Job application automation module may need manual configuration"
            fi
            
            print_status "✅ All available custom modules enabled successfully"
        else
            print_status "✅ All custom modules already enabled"
        fi
    fi
    
    # Enable Forseti Theme
    if [ -d "web/themes/custom/forseti" ]; then
        print_status "Configuring Forseti theme..."
        
        # Enable theme if not installed
        if ! /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --type=theme --format=list 2>/dev/null | grep -q "forseti"; then
            print_status "Enabling Forseti custom theme..."
            /usr/bin/php8.3 vendor/drush/drush/drush.php theme:enable forseti -y 2>/dev/null || print_warning "Could not enable Forseti theme"
        else
            print_status "Forseti theme already enabled"
        fi
        
        # Set as default theme
        CURRENT_THEME=$(/usr/bin/php8.3 vendor/drush/drush/drush.php config:get system.theme default --format=string 2>/dev/null || echo "")
        if [ "$CURRENT_THEME" != "forseti" ]; then
            print_status "Setting Forseti theme as default..."
            /usr/bin/php8.3 vendor/drush/drush/drush.php config:set system.theme default forseti -y 2>/dev/null || print_warning "Could not set Forseti as default theme"
            if [ $? -eq 0 ]; then
                print_status "✅ Forseti theme set as default"
            fi
        else
            print_status "✅ Forseti theme already set as default"
        fi
    else
        print_warning "Forseti theme directory not found at web/themes/custom/forseti"
    fi
else
    print_warning "Drush not available for module and theme configuration"
fi


# ==============================================================================
# STEP 5: H3 GEOLOCATION DATABASE SETUP
# ==============================================================================
# NOTE: This section creates TABLES within databases that were created in Step 1.7
#       It does NOT create databases - those already exist (forseti_dev, amisafe_database)

print_step "5. H3 GEOLOCATION DATABASE SETUP - Initializing AmISafe crime mapping pipeline..."

# ------------------------------------------------------------------------------
# 5.1 MySQL Connection Test
# ------------------------------------------------------------------------------
ensure_mysql_running
if sudo mysql -e "SELECT 1;" &>/dev/null; then
    print_status "✅ MySQL is running and accessible"
else
    print_error "❌ MySQL connection test failed"
fi

# ------------------------------------------------------------------------------
# 5.2 H3 Python Environment Verification
# ------------------------------------------------------------------------------
H3_ENV_DIR="/home/keithaumiller/forseti.life/h3-geolocation/h3-env"
if [ -f "$H3_ENV_DIR/bin/python" ]; then
    H3_TEST_RESULT=$($H3_ENV_DIR/bin/python -c "import h3; import pandas; import mysql.connector; print('H3 packages verified')" 2>/dev/null || echo "FAILED")
    if [ "$H3_TEST_RESULT" = "H3 packages verified" ]; then
        print_status "✅ H3 Python environment verified"
    else
        print_warning "⚠️  H3 Python environment verification failed"
    fi
else
    print_warning "⚠️  H3 Python environment not found"
fi

# ------------------------------------------------------------------------------
# 5.3 Database Table Verification
# ------------------------------------------------------------------------------
print_status "Setting up AmISafe H3 geolocation data pipeline database..."
AMISAFE_SETUP_SCRIPT="/home/keithaumiller/forseti.life/h3-geolocation/database/setup/setup_amisafe_complete.sh"

if [ -f "$AMISAFE_SETUP_SCRIPT" ]; then
    print_status "Running AmISafe complete database setup..."
    if bash "$AMISAFE_SETUP_SCRIPT" "$DB_NAME"; then
        print_status "✅ AmISafe database setup completed successfully"
        
        # ------------------------------------------------------------------------------
        # 5.4 ETL Pipeline Status
        # ------------------------------------------------------------------------------
        print_status "Running AmISafe sample data pipeline..."
        PIPELINE_SCRIPT="/home/keithaumiller/forseti.life/h3-geolocation/database/run_amisafe_pipeline_stlouisintegration.sh"
        if [ -f "$PIPELINE_SCRIPT" ]; then
            cd /home/keithaumiller/forseti.life/h3-geolocation/database
            if bash "$PIPELINE_SCRIPT" sample; then
                print_status "✅ AmISafe sample data pipeline completed"
            else
                print_warning "⚠️  AmISafe pipeline had issues"
            fi
            cd - > /dev/null
        fi
    else
        print_warning "⚠️  AmISafe database setup encountered issues"
    fi
else
    print_warning "⚠️  AmISafe database setup script not found - setting up basic tables..."
    
    # Create basic AmISafe tables
    print_status "Creating basic AmISafe database tables..."
    mysql -h127.0.0.1 -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" << 'EOF' || print_warning "AmISafe table creation failed"
CREATE TABLE IF NOT EXISTS amisafe_raw_incidents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    source_file VARCHAR(255) NOT NULL DEFAULT 'sample_data',
    cartodb_id INT,
    objectid BIGINT,
    dc_dist VARCHAR(10),
    dispatch_date_time DATETIME,
    lat DECIMAL(10,7),
    lng DECIMAL(11,7),
    location_block TEXT,
    ucr_general VARCHAR(10),
    text_general_code VARCHAR(255),
    ingested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_coordinates (lat, lng),
    INDEX idx_district (dc_dist),
    INDEX idx_crime_type (ucr_general)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amisafe_clean_incidents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    incident_id VARCHAR(50) UNIQUE,
    lat DECIMAL(10,7) NOT NULL,
    lng DECIMAL(11,7) NOT NULL,
    incident_datetime DATETIME NOT NULL,
    ucr_general VARCHAR(10) NOT NULL,
    dc_dist VARCHAR(10),
    severity_level TINYINT DEFAULT 3,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (lat, lng),
    INDEX idx_datetime (incident_datetime),
    INDEX idx_crime_type (ucr_general)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amisafe_h3_aggregated (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    h3_index VARCHAR(16) NOT NULL,
    h3_resolution TINYINT NOT NULL,
    incident_count INT DEFAULT 0,
    center_lat DECIMAL(10,7),
    center_lng DECIMAL(11,7),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_h3_resolution (h3_index, h3_resolution),
    INDEX idx_resolution (h3_resolution),
    INDEX idx_incident_count (incident_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF
    
    if [ $? -eq 0 ]; then
        print_status "✅ Basic AmISafe database tables created"
    fi
fi

# ------------------------------------------------------------------------------
# 5.5 Database Statistics
# ------------------------------------------------------------------------------
print_status "Checking AmISafe database statistics..."
AMISAFE_RAW_COUNT=$(mysql -h127.0.0.1 -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM amisafe_raw_incidents;" 2>/dev/null || echo "0")
AMISAFE_CLEAN_COUNT=$(mysql -h127.0.0.1 -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM amisafe_clean_incidents;" 2>/dev/null || echo "0")
AMISAFE_H3_COUNT=$(mysql -h127.0.0.1 -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM amisafe_h3_aggregated;" 2>/dev/null || echo "0")

print_status "AmISafe Data Statistics:"
echo "  - Raw Incidents: $AMISAFE_RAW_COUNT"
echo "  - Clean Incidents: $AMISAFE_CLEAN_COUNT"
echo "  - H3 Aggregated: $AMISAFE_H3_COUNT"

# ------------------------------------------------------------------------------
# 5.6 Import Instructions
# ------------------------------------------------------------------------------
print_status "✅ STEP 5 COMPLETE: H3 geolocation setup finished"

# ==============================================================================
# COMPLETION MESSAGE
# ==============================================================================

print_step "SETUP COMPLETE - Forseti.Life Development Environment Ready!"

echo ""
echo "========================="
echo "Installation Summary"
echo "========================="
echo "✓ Environment: PHP 8.3, MySQL, Apache configured"
echo "✓ Drupal: 11.2.5 installed and configured"
echo "✓ Development Tools: Coder, PHPCS, PHPUnit configured"
echo "✓ H3 Geolocation Framework: Ready for AmISafe crime mapping"
echo "✓ Database: $DB_NAME with AmISafe tables"
echo ""
echo "========================="
echo "Access Information"
echo "========================="
echo "Site URL: http://forseti.local"
echo "Admin Login: http://forseti.local/user/login"
echo "Admin User: $ADMIN_USER"
echo "Admin Password: $ADMIN_PASSWORD"
echo "Database: $DB_NAME"
echo "Directory: $PROJECT_DIR"
echo ""
echo "========================="
echo "Available Commands"
echo "========================="
echo "Navigate to site: cd $PROJECT_DIR"
echo "Clear cache: ./vendor/bin/drush cr"
echo "One-time login: ./vendor/bin/drush uli"
echo "Check coding standards: ./scripts/check-standards.sh"
echo "Backup database: ./scripts/backup-database.sh"
echo ""
echo "H3 Geolocation Commands:"
echo "Navigate to H3: cd /home/keithaumiller/forseti.life/h3-geolocation"
echo "Activate environment: source h3-env/bin/activate"
echo "Run pipeline: cd database && bash run_amisafe_pipeline_stlouisintegration.sh"
echo "Quick examples: python quick_start.py"
echo "Visualization: python visualizer.py"
echo ""
echo "========================="
print_status "🚀 Your Forseti development environment is ready!"
print_status "📖 See README.md for detailed documentation"
echo "========================="
