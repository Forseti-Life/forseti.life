# System Requirements

## Minimum Requirements for Drupal 11

### PHP Requirements
- **PHP Version**: 8.1 or higher (8.3 recommended)
- **Required PHP Extensions**:
  - gd or imagick
  - json
  - mbstring
  - pdo_mysql or pdo_pgsql
  - xml
  - curl
  - openssl
  - tokenizer
  - ctype
  - fileinfo
  - zip

### Database Requirements
- **MySQL**: 5.7.8+ or 8.0+ (recommended)
- **MariaDB**: 10.3.7+ (alternative)
- **PostgreSQL**: 12+ (alternative)

### Web Server
- **Apache**: 2.4+ with mod_rewrite
- **Nginx**: 1.18+ (alternative)
- **PHP Built-in Server**: For development only

### Composer
- **Version**: 2.0+
- Required for dependency management and Drupal installation

### Development Tools
- **Drush**: Drupal command line tool
- **Git**: Version control
- **Node.js & npm**: For theme compilation (if using modern themes)

## Ubuntu/Debian Installation Commands

```bash
# Update package list
sudo apt update

# Install PHP 8.3 and required extensions
sudo apt install -y php8.3 php8.3-cli php8.3-common php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring php8.3-curl php8.3-xml php8.3-bcmath php8.3-tokenizer php8.3-json php8.3-fileinfo

# Install MySQL/MariaDB
sudo apt install -y mysql-server mysql-client

# Install Apache web server
sudo apt install -y apache2

# Enable Apache modules
sudo a2enmod rewrite
sudo systemctl restart apache2

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Install Git (if not already installed)
sudo apt install -y git

# Install Node.js and npm (for theme development)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

## Memory and Performance Settings

### PHP Configuration (php.ini)
```ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 64M
post_max_size = 64M
```

### MySQL Configuration
```sql
-- Recommended MySQL settings for Drupal
[mysqld]
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
max_allowed_packet = 64M
```

## Development Environment Specifics

### File Permissions
```bash
# Drupal files directory
chmod 755 sites/default/files
chown -R www-data:www-data sites/default/files

# Settings file
chmod 644 sites/default/settings.php
```

### Apache Virtual Host Example
```apache
<VirtualHost *:80>
    ServerName stlouisintegration.local
    DocumentRoot /path/to/drupal/web
    
    <Directory /path/to/drupal/web>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/stlouisintegration_error.log
    CustomLog ${APACHE_LOG_DIR}/stlouisintegration_access.log combined
</VirtualHost>
```

## Verification Commands

```bash
# Check PHP version and extensions
php --version
php -m | grep -E "(gd|mysql|mbstring|xml|curl|json|zip)"

# Check Composer
composer --version

# Check MySQL
mysql --version
sudo systemctl status mysql

# Check Apache
apache2 -v
sudo systemctl status apache2

# Test PHP with Apache
echo "<?php phpinfo(); ?>" | sudo tee /var/www/html/info.php
# Visit http://localhost/info.php
```

## Next Steps

After verifying all requirements are met:

1. Run `./setup-environment.sh` to install any missing dependencies
2. Run `./install-drupal.sh` to create the Drupal project
3. Run `./configure-development.sh` to set up development tools