#!/bin/bash

# Comprehensive Cache Disabling Script for Theory of Conspiracies Drupal 11 Site
# This script will disable EVERY possible cache mechanism

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

# Change to the site directory
cd /workspaces/stlouisintegration.com/sites/theoryofconspiracies

echo "=== Comprehensive Cache Disabling for Theory of Conspiracies ==="

# Step 1: Create comprehensive settings.local.php
print_step "Creating comprehensive settings.local.php..."

cat > web/sites/default/settings.local.php << 'EOF'
<?php

/**
 * @file
 * Local development override configuration - COMPREHENSIVE CACHE DISABLING
 */

// Disable ALL cache bins
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
$settings['cache']['bins']['discovery'] = 'cache.backend.null';
$settings['cache']['bins']['config'] = 'cache.backend.null';
$settings['cache']['bins']['data'] = 'cache.backend.null';
$settings['cache']['bins']['default'] = 'cache.backend.null';
$settings['cache']['bins']['bootstrap'] = 'cache.backend.null';
$settings['cache']['bins']['container'] = 'cache.backend.null';
$settings['cache']['bins']['entity'] = 'cache.backend.null';
$settings['cache']['bins']['menu'] = 'cache.backend.null';
$settings['cache']['bins']['toolbar'] = 'cache.backend.null';
$settings['cache']['bins']['migrate'] = 'cache.backend.null';
$settings['cache']['bins']['form'] = 'cache.backend.null';
$settings['cache']['bins']['rest'] = 'cache.backend.null';
$settings['cache']['bins']['jsonapi_normalizations'] = 'cache.backend.null';
$settings['cache']['bins']['jsonapi_resource_types'] = 'cache.backend.null';

// Disable CSS and JS aggregation
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
$config['system.performance']['cache']['page']['max_age'] = 0;

// Twig debugging and cache disabling
$settings['twig_debug'] = TRUE;
$settings['twig_auto_reload'] = TRUE;
$settings['twig_cache'] = FALSE;

// Additional development settings
$settings['extension_discovery_scan_tests'] = FALSE;
$settings['rebuild_access'] = TRUE;
$settings['skip_permissions_hardening'] = TRUE;

// Views caching
$config['views.settings']['ui']['always_live_preview'] = TRUE;
$config['views.settings']['ui']['exposed_filter_any_label'] = 'new_any';

// Block caching
$config['block.settings']['cache']['max_age'] = 0;

// Node caching
$config['node.settings']['use_admin_theme'] = TRUE;

// System caching
$config['system.site']['page']['front'] = '/node';

// Additional cache bins that might exist
$settings['cache']['bins']['library_info'] = 'cache.backend.null';
$settings['cache']['bins']['theme_suggestions'] = 'cache.backend.null';
$settings['cache']['bins']['render_array'] = 'cache.backend.null';

// Development settings
$settings['hash_salt'] = 'development-hash-salt-not-for-production';
$settings['update_free_access'] = FALSE;
$settings['allow_authorize_operations'] = FALSE;

// Disable internal page cache
$settings['omit_vary_cookie'] = TRUE;

// Disable BigPipe if it exists
$config['big_pipe.settings']['enabled'] = FALSE;

print_status("Comprehensive settings.local.php created with ALL cache bins disabled");
EOF

# Step 2: Ensure settings.php includes settings.local.php
print_step "Ensuring settings.php includes settings.local.php..."
if ! grep -q "settings.local.php" web/sites/default/settings.php; then
    echo "" >> web/sites/default/settings.php
    echo "// Include local development settings" >> web/sites/default/settings.php
    echo "if (file_exists(\$app_root . '/' . \$site_path . '/settings.local.php')) {" >> web/sites/default/settings.php
    echo "  include \$app_root . '/' . \$site_path . '/settings.local.php';" >> web/sites/default/settings.php
    echo "}" >> web/sites/default/settings.php
    print_status "Added settings.local.php include to settings.php"
else
    print_status "settings.local.php include already exists in settings.php"
fi

# Step 3: Disable all performance optimizations via drush
print_step "Disabling performance optimizations via drush..."

vendor/bin/drush config:set system.performance cache.page.max_age 0 -y
vendor/bin/drush config:set system.performance css.preprocess false -y
vendor/bin/drush config:set system.performance js.preprocess false -y

print_status "Performance optimizations disabled"

# Step 4: Force delete all cache files
print_step "Force deleting all cache files..."

sudo rm -rf web/sites/default/files/css/* 2>/dev/null || true
sudo rm -rf web/sites/default/files/js/* 2>/dev/null || true
sudo rm -rf web/sites/default/files/php/twig/* 2>/dev/null || true
sudo rm -rf web/sites/default/files/php/* 2>/dev/null || true

print_status "All cache files deleted"

# Step 5: Clear all cache bins individually
print_step "Clearing all cache bins individually..."

cache_bins=(
    "render"
    "page" 
    "dynamic_page_cache"
    "discovery"
    "config"
    "data"
    "default"
    "bootstrap"
    "container"
    "entity"
    "menu"
    "toolbar"
    "migrate"
    "form"
    "rest"
)

for bin in "${cache_bins[@]}"; do
    print_check "Clearing $bin cache..."
    vendor/bin/drush cache:clear "$bin" 2>/dev/null || print_warning "Cache bin $bin not found or already empty"
done

# Step 6: Full cache rebuild
print_step "Performing full cache rebuild..."
vendor/bin/drush cache:rebuild --no-interaction || print_error "Cache rebuild failed"

# Step 7: Restart Apache
print_step "Restarting Apache..."
sudo service apache2 restart

# Step 8: Verification - check current settings
print_step "Verifying cache settings..."

echo ""
echo "=== VERIFICATION RESULTS ==="

# Check page cache
page_cache=$(vendor/bin/drush config:get system.performance cache.page.max_age 2>/dev/null | cut -d' ' -f2)
if [ "$page_cache" = "0" ]; then
    print_status "✅ Page cache max age: DISABLED ($page_cache)"
else
    print_error "❌ Page cache max age: ENABLED ($page_cache)"
fi

# Check CSS aggregation
css_preprocess=$(vendor/bin/drush config:get system.performance css.preprocess 2>/dev/null | cut -d' ' -f2)
if [ "$css_preprocess" = "false" ]; then
    print_status "✅ CSS aggregation: DISABLED"
else
    print_error "❌ CSS aggregation: ENABLED ($css_preprocess)"
fi

# Check JS aggregation
js_preprocess=$(vendor/bin/drush config:get system.performance js.preprocess 2>/dev/null | cut -d' ' -f2)
if [ "$js_preprocess" = "false" ]; then
    print_status "✅ JS aggregation: DISABLED"
else
    print_error "❌ JS aggregation: ENABLED ($js_preprocess)"
fi

# Check if settings.local.php exists and has cache disabling
if [ -f "web/sites/default/settings.local.php" ]; then
    print_status "✅ settings.local.php: EXISTS"
    if grep -q "cache.backend.null" web/sites/default/settings.local.php; then
        print_status "✅ Cache backends: DISABLED"
    else
        print_error "❌ Cache backends: NOT PROPERLY DISABLED"
    fi
else
    print_error "❌ settings.local.php: MISSING"
fi

# Test template caching by checking if changes take effect
print_step "Testing template responsiveness..."
sleep 2
response=$(curl -s "http://localhost:8080/story/act-i" | grep -c "World Building Elements" || echo "0")
if [ "$response" -gt "0" ]; then
    print_status "✅ Template is loading"
else
    print_error "❌ Template not loading properly"
fi

echo ""
echo "=== SUMMARY ==="
print_status "ALL caching mechanisms have been disabled for Theory of Conspiracies site"
print_status "Template changes should now take effect immediately"
print_status "CSS/JS changes should take effect immediately"
print_status "Configuration changes should take effect immediately"

echo ""
print_step "Script complete! Test your template changes now:"
echo "curl -s \"http://localhost:8080/story/act-i\" | grep -A 10 \"World Building\""