#!/bin/bash

# Multi-Site Setup Verification Script
# Verifies that both Drupal sites are properly configured and accessible

set -e

echo "=== Multi-Site Drupal Setup Verification ==="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[i]${NC} $1"
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Check directory structure
print_info "Checking directory structure..."
if [ -d "/workspaces/stlouisintegration.com/sites/stlouisintegration" ]; then
    print_status "St. Louis Integration site directory exists"
else
    print_error "St. Louis Integration site directory missing"
fi

if [ -d "/workspaces/stlouisintegration.com/sites/theoryofconspiracies" ]; then
    print_status "Theory of Conspiracies site directory exists"
else
    print_error "Theory of Conspiracies site directory missing"
fi

# Check Apache configuration
print_info "Checking Apache configuration..."
if sudo apache2ctl configtest 2>&1 | grep -q "Syntax OK"; then
    print_status "Apache configuration is valid"
else
    print_error "Apache configuration has syntax errors"
fi

# Check if Apache is listening on both ports
if sudo netstat -tlnp | grep -q ":80 "; then
    print_status "Apache listening on port 80"
else
    print_warning "Apache not listening on port 80"
fi

if sudo netstat -tlnp | grep -q ":8080 "; then
    print_status "Apache listening on port 8080"
else
    print_warning "Apache not listening on port 8080"
fi

# Check database connections
print_info "Checking database connections..."
if mysql -u drupal_user -pdrupal_secure_password -h 127.0.0.1 -e "USE stlouisintegration_dev; SELECT 1;" >/dev/null 2>&1; then
    print_status "St. Louis Integration database accessible"
else
    print_error "St. Louis Integration database connection failed"
fi

if mysql -u drupal_user -pdrupal_secure_password -h 127.0.0.1 -e "USE theoryofconspiracies_dev; SELECT 1;" >/dev/null 2>&1; then
    print_status "Theory of Conspiracies database accessible"
else
    print_error "Theory of Conspiracies database connection failed"
fi

# Check website accessibility
print_info "Checking website accessibility..."
if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|302\|301"; then
    print_status "St. Louis Integration site (http://localhost) is accessible"
else
    print_warning "St. Louis Integration site may not be accessible"
fi

if curl -s -o /dev/null -w "%{http_code}" "http://localhost:8080" | grep -q "200\|302\|301"; then
    print_status "Theory of Conspiracies site (http://localhost:8080) is accessible"
else
    print_warning "Theory of Conspiracies site may not be accessible"
fi

# Check Drush functionality
print_info "Checking Drush functionality..."
cd /workspaces/stlouisintegration.com/sites/stlouisintegration
if [ -f "vendor/bin/drush" ]; then
    if ./vendor/bin/drush status --fields=bootstrap 2>/dev/null | grep -q "Successful"; then
        print_status "St. Louis Integration Drush working"
    else
        print_warning "St. Louis Integration Drush may have issues"
    fi
else
    print_error "St. Louis Integration Drush not found"
fi

cd /workspaces/stlouisintegration.com/sites/theoryofconspiracies
if [ -f "vendor/bin/drush" ]; then
    if ./vendor/bin/drush status --fields=bootstrap 2>/dev/null | grep -q "Successful"; then
        print_status "Theory of Conspiracies Drush working"
    else
        print_warning "Theory of Conspiracies Drush may have issues"
    fi
else
    print_error "Theory of Conspiracies Drush not found"
fi

# Check custom modules on primary site
print_info "Checking custom modules on St. Louis Integration..."
cd /workspaces/stlouisintegration.com/sites/stlouisintegration
CUSTOM_MODULES=("professional_website_content" "ai_conversation" "stli_site_customizations" "job_application_automation" "resume_tailoring")
for module in "${CUSTOM_MODULES[@]}"; do
    if [ -d "web/modules/custom/$module" ]; then
        if ./vendor/bin/drush pm:list --status=enabled 2>/dev/null | grep -q "$module"; then
            print_status "Custom module '$module' is enabled"
        else
            print_warning "Custom module '$module' exists but not enabled"
        fi
    else
        print_warning "Custom module '$module' directory not found"
    fi
done

# Check custom theme
if [ -d "web/themes/custom/stlouisintegration" ]; then
    if ./vendor/bin/drush pm:list --type=theme --status=enabled 2>/dev/null | grep -q "stlouisintegration"; then
        print_status "Custom theme 'stlouisintegration' is enabled"
    else
        print_warning "Custom theme 'stlouisintegration' exists but not enabled"
    fi
else
    print_warning "Custom theme 'stlouisintegration' directory not found"
fi

# Check OpenClaw runtime integration
print_info "Checking OpenClaw runtime..."
if [ -x "$SCRIPT_DIR/verify-openclaw.sh" ]; then
    if "$SCRIPT_DIR/verify-openclaw.sh" >/dev/null 2>&1; then
        print_status "OpenClaw runtime verification passed"
    else
        print_warning "OpenClaw runtime verification failed (run ./script/verify-openclaw.sh for details)"
    fi
else
    print_warning "OpenClaw verification script not found at $SCRIPT_DIR/verify-openclaw.sh"
fi

print_info "Verification complete!"
echo "========================="
echo "Site URLs:"
echo "- St. Louis Integration: http://localhost"
echo "- Theory of Conspiracies: http://localhost:8080"
echo "========================="