#!/bin/bash

# St. Louis Integration - Environment Setup Script
# This script installs all necessary dependencies for Drupal 11 development

set -e  # Exit on any error

echo "=== St. Louis Integration - Setting up Development Environment ==="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root. Please run as a regular user."
   exit 1
fi

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

# Install required PHP extensions
print_status "Checking PHP extensions..."
REQUIRED_EXTENSIONS=("gd" "xml" "mbstring" "curl" "zip" "bcmath" "json" "tokenizer" "fileinfo" "intl")
MISSING_EXTENSIONS=()

# Check standard extensions
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m | grep -q "^$ext$"; then
        MISSING_EXTENSIONS+=("php8.3-$ext")
        print_warning "PHP extension '$ext' is missing"
    else
        print_status "PHP extension '$ext' is already installed"
    fi
done

# Check MySQL extensions (mysqli, pdo_mysql, or mysqlnd)
if ! php -m | grep -qE "^(mysqli|pdo_mysql|mysqlnd)$"; then
    MISSING_EXTENSIONS+=("php8.3-mysql")
    print_warning "PHP MySQL extension is missing"
else
    print_status "PHP MySQL extension is already installed"
fi

# Check OPcache (shows as "Zend OPcache")
if ! php -m | grep -qi "opcache"; then
    MISSING_EXTENSIONS+=("php8.3-opcache")
    print_warning "PHP OPcache extension is missing"
else
    print_status "PHP OPcache extension is already installed"
fi

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    print_status "Installing missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
    sudo apt install -y "${MISSING_EXTENSIONS[@]}"
else
    print_status "All required PHP extensions are already installed"
fi

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
    
    # Secure MySQL installation
    print_warning "Please run 'sudo mysql_secure_installation' after this script completes"
fi

# Install Apache (optional, for local development)
print_status "Checking Apache installation..."
if command -v apache2 &> /dev/null; then
    print_status "Apache is already installed"
else
    print_status "Installing Apache web server..."
    sudo apt install -y apache2
    sudo a2enmod rewrite
    sudo systemctl enable apache2
fi

# Install Git
print_status "Checking Git installation..."
if command -v git &> /dev/null; then
    print_status "Git is already installed: $(git --version)"
else
    print_status "Installing Git..."
    sudo apt install -y git
fi

# Install Node.js and npm (for theme development)
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
        print_warning "Tool '$tool' is missing"
    else
        print_status "Tool '$tool' is already installed"
    fi
done

if [ ${#MISSING_TOOLS[@]} -gt 0 ]; then
    print_status "Installing missing development tools: ${MISSING_TOOLS[*]}"
    sudo apt install -y "${MISSING_TOOLS[@]}"
else
    print_status "All development tools are already installed"
fi

# Set up PHP configuration for development
print_status "Configuring PHP for development..."
PHP_INI_DIR=$(php --ini | grep "Configuration File" | awk '{print $4}' | xargs dirname)
PHP_INI_FILE="$PHP_INI_DIR/php.ini"

if [ -f "$PHP_INI_FILE" ]; then
    # Backup original php.ini
    sudo cp "$PHP_INI_FILE" "$PHP_INI_FILE.backup"
    
    # Update PHP settings for Drupal development
    sudo sed -i 's/memory_limit = .*/memory_limit = 256M/' "$PHP_INI_FILE"
    sudo sed -i 's/max_execution_time = .*/max_execution_time = 300/' "$PHP_INI_FILE"
    sudo sed -i 's/upload_max_filesize = .*/upload_max_filesize = 64M/' "$PHP_INI_FILE"
    sudo sed -i 's/post_max_size = .*/post_max_size = 64M/' "$PHP_INI_FILE"
    
    print_status "PHP configuration updated for Drupal development"
fi

# Start services
print_status "Starting services..."
if command -v systemctl &> /dev/null && systemctl is-system-running &> /dev/null; then
    sudo systemctl start mysql
    sudo systemctl start apache2
    
    print_status "Enabling services to start on boot..."
    sudo systemctl enable mysql
    sudo systemctl enable apache2
else
    print_status "Using service command (systemd not available)..."
    sudo service mysql start
    sudo service apache2 start
    print_status "Services started (auto-enable not available in container)"
fi

# Verify installations
print_status "Verifying installations..."
echo "========================="
echo "PHP Version: $(php --version | head -n 1)"
echo "Composer Version: $(composer --version)"
echo "MySQL Version: $(mysql --version)"
echo "Apache Version: $(apache2 -v | head -n 1)"
echo "Git Version: $(git --version)"
echo "Node.js Version: $(node --version)"
echo "npm Version: $(npm --version)"
echo "========================="

print_status "Environment setup completed successfully!"
print_status "Next steps:"
echo "1. Run './install-drupal.sh' to create the Drupal project"
echo "2. Configure your database settings"
echo "3. Run './configure-development.sh' to set up development tools"

print_warning "Remember to:"
echo "- Run 'sudo mysql_secure_installation' to secure MySQL"
echo "- Configure your database credentials"
echo "- Set up your development domain in /etc/hosts if needed"

# Test website availability
print_status "Testing website availability..."
if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|302\|301"; then
    print_status "✅ Website is accessible at http://localhost"
    print_status "🌐 Homepage response: $(curl -s -w "HTTP %{http_code}" "http://localhost" | tail -n1)"
else
    print_warning "⚠️  Website may not be fully configured yet"
    print_status "📝 Check Apache configuration and Drupal setup"
fi