#!/bin/bash

# Disable All Caching Script for Multi-Site Drupal
# This script checks and disables every possible caching mechanism for both sites

set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_check() {
    echo -e "${CYAN}[CHECK]${NC} $1"
}

# Function to check and disable Drupal config settings
check_and_disable_config() {
    local site_dir=$1
    local site_name=$2
    
    print_step "Processing $site_name..."
    
    cd "/workspaces/stlouisintegration.com/sites/$site_dir"
    
    # System Performance Settings
    print_check "Checking system performance settings..."
    
    # Page cache max age
    current_page_cache=$(vendor/bin/drush config:get system.performance cache.page.max_age 2>/dev/null | cut -d' ' -f2 || echo "error")
    if [ "$current_page_cache" != "0" ]; then
        print_warning "Setting page cache max age to 0..."
        vendor/bin/drush config:set system.performance cache.page.max_age 0 -y
        echo "  - Page cache max age: DISABLED (was: $current_page_cache)" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    else
        echo "  - Page cache max age: ALREADY DISABLED" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    fi
    
    # CSS aggregation
    current_css=$(vendor/bin/drush config:get system.performance css.preprocess 2>/dev/null | cut -d' ' -f2 || echo "error")
    if [ "$current_css" != "false" ]; then
        print_warning "Disabling CSS aggregation..."
        vendor/bin/drush config:set system.performance css.preprocess false -y
        echo "  - CSS aggregation: DISABLED (was: $current_css)" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    else
        echo "  - CSS aggregation: ALREADY DISABLED" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    fi
    
    # JS aggregation
    current_js=$(vendor/bin/drush config:get system.performance js.preprocess 2>/dev/null | cut -d' ' -f2 || echo "error")
    if [ "$current_js" != "false" ]; then
        print_warning "Disabling JS aggregation..."
        vendor/bin/drush config:set system.performance js.preprocess false -y
        echo "  - JS aggregation: DISABLED (was: $current_js)" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    else
        echo "  - JS aggregation: ALREADY DISABLED" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    fi
    
    # Check if settings.local.php exists and has proper cache disabling
    if [ -f "web/sites/default/settings.local.php" ]; then
        echo "  - settings.local.php: EXISTS" >> /workspaces/stlouisintegration.com/Cachingsettings.md
        if grep -q "cache.backend.null" web/sites/default/settings.local.php; then
            echo "  - Cache backends: DISABLED" >> /workspaces/stlouisintegration.com/Cachingsettings.md
        else
            echo "  - Cache backends: NOT PROPERLY DISABLED" >> /workspaces/stlouisintegration.com/Cachingsettings.md
        fi
    else
        echo "  - settings.local.php: MISSING" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    fi
    
    # Clear all caches
    print_status "Clearing all caches for $site_name..."
    vendor/bin/drush cache:rebuild || print_warning "Cache rebuild failed for $site_name"
    
    # Clear specific cache bins
    for cache_bin in render page dynamic_page_cache discovery config data default bootstrap container; do
        vendor/bin/drush cache:clear $cache_bin 2>/dev/null || true
    done
    
    # Clear Twig cache manually
    if [ -d "web/sites/default/files/php/twig" ]; then
        sudo rm -rf web/sites/default/files/php/twig/* 2>/dev/null || true
        echo "  - Twig cache files: CLEARED" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    fi
    
    echo "" >> /workspaces/stlouisintegration.com/Cachingsettings.md
}

# Function to create/update settings.local.php
create_settings_local() {
    local site_dir=$1
    local site_name=$2
    
    print_step "Creating/updating settings.local.php for $site_name..."
    
    local settings_local="/workspaces/stlouisintegration.com/sites/$site_dir/web/sites/default/settings.local.php"
    
    cat > "$settings_local" << 'EOF'
<?php

/**
 * @file
 * Local development override configuration feature.
 */

// Disable all caching.
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
$settings['cache']['bins']['discovery'] = 'cache.backend.null';
$settings['cache']['bins']['config'] = 'cache.backend.null';
$settings['cache']['bins']['data'] = 'cache.backend.null';
$settings['cache']['bins']['default'] = 'cache.backend.null';
$settings['cache']['bins']['bootstrap'] = 'cache.backend.null';
$settings['cache']['bins']['container'] = 'cache.backend.null';

// Disable CSS and JS aggregation.
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

// Enable twig debugging and disable caching
$settings['twig_debug'] = TRUE;
$settings['twig_auto_reload'] = TRUE;
$settings['twig_cache'] = FALSE;

// Allow test modules and themes.
$settings['extension_discovery_scan_tests'] = FALSE;

// Enable access to rebuild.php.
$settings['rebuild_access'] = TRUE;

// Skip file system permissions hardening.
$settings['skip_permissions_hardening'] = TRUE;

// Disable entity/field caching
$settings['cache']['bins']['entity'] = 'cache.backend.null';
$settings['cache']['bins']['menu'] = 'cache.backend.null';
$settings['cache']['bins']['toolbar'] = 'cache.backend.null';

// Disable Views caching
$config['views.settings']['ui']['always_live_preview'] = TRUE;
$config['views.settings']['ui']['exposed_filter_any_label'] = 'new_any';

// Disable migration caching
$settings['cache']['bins']['migrate'] = 'cache.backend.null';

// Additional development settings
$settings['hash_salt'] = 'development-hash-salt-not-for-production';
$settings['update_free_access'] = FALSE;
$settings['allow_authorize_operations'] = FALSE;
EOF

    # Ensure settings.php includes settings.local.php
    local settings_php="/workspaces/stlouisintegration.com/sites/$site_dir/web/sites/default/settings.php"
    if ! grep -q "settings.local.php" "$settings_php"; then
        echo "" >> "$settings_php"
        echo "// Include local development settings" >> "$settings_php"
        echo "if (file_exists(\$app_root . '/' . \$site_path . '/settings.local.php')) {" >> "$settings_php"
        echo "  include \$app_root . '/' . \$site_path . '/settings.local.php';" >> "$settings_php"
        echo "}" >> "$settings_php"
        print_status "Added settings.local.php include to settings.php for $site_name"
    fi
}

# Function to check Apache caching
check_apache_caching() {
    print_step "Checking Apache caching settings..."
    
    echo "## Apache Caching Status" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    
    # Check if mod_expires is enabled
    if apache2ctl -M 2>/dev/null | grep -q expires; then
        echo "- mod_expires: ENABLED" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    else
        echo "- mod_expires: DISABLED" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    fi
    
    # Check if mod_headers is enabled
    if apache2ctl -M 2>/dev/null | grep -q headers; then
        echo "- mod_headers: ENABLED" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    else
        echo "- mod_headers: DISABLED" >> /workspaces/stlouisintegration.com/Cachingsettings.md
    fi
    
    # Check for .htaccess files
    for site in stlouisintegration theoryofconspiracies; do
        htaccess_file="/workspaces/stlouisintegration.com/sites/$site/web/.htaccess"
        if [ -f "$htaccess_file" ]; then
            if grep -q "ExpiresActive\|Cache-Control\|mod_expires" "$htaccess_file"; then
                echo "- $site .htaccess caching: FOUND" >> /workspaces/stlouisintegration.com/Cachingsettings.md
            else
                echo "- $site .htaccess caching: NOT FOUND" >> /workspaces/stlouisintegration.com/Cachingsettings.md
            fi
        else
            echo "- $site .htaccess: MISSING" >> /workspaces/stlouisintegration.com/Cachingsettings.md
        fi
    done
    
    echo "" >> /workspaces/stlouisintegration.com/Cachingsettings.md
}

# Main execution
echo "=== Disable All Caching Script ==="

# Initialize the results file
cat > /workspaces/stlouisintegration.com/Cachingsettings.md << 'EOF'
# Drupal and Apache Caching Settings - Current Status

## Site Caching Status (Updated: $(date))

EOF

echo "## St. Louis Integration Site" >> /workspaces/stlouisintegration.com/Cachingsettings.md

# Create settings.local.php for both sites
create_settings_local "stlouisintegration" "St. Louis Integration"
create_settings_local "theoryofconspiracies" "Theory of Conspiracies"

# Check and disable caching for both sites
check_and_disable_config "stlouisintegration" "St. Louis Integration"

echo "## Theory of Conspiracies Site" >> /workspaces/stlouisintegration.com/Cachingsettings.md
check_and_disable_config "theoryofconspiracies" "Theory of Conspiracies"

# Check Apache settings
check_apache_caching

# Restart Apache to ensure all settings take effect
print_step "Restarting Apache to ensure all settings take effect..."
sudo service apache2 restart

print_step "Script complete!"
print_status "Results have been written to Cachingsettings.md"
print_status "Both sites should now have all caching disabled"

echo ""
echo "=== Summary ==="
echo "- All Drupal cache bins disabled"
echo "- CSS/JS aggregation disabled"
echo "- Twig caching disabled"
echo "- settings.local.php created/updated for both sites"
echo "- Apache restarted"
echo ""
echo "Test your changes now!"