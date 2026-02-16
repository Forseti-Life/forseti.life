#!/usr/bin/env bash
#
# Setup script for Dungeon Crawler functional test environment.
#
# This script ensures that all necessary directories and permissions
# are in place before running PHPUnit functional tests.
#
# Usage:
#   ./tests/setup-test-environment.sh

set -e

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Setting up Dungeon Crawler test environment...${NC}"

# Create temporary file storage directory for simpletest
SIMPLETEST_DIR="/tmp/dungeoncrawler-simpletest"
BROWSER_OUTPUT_DIR="/tmp/dungeoncrawler-simpletest/browser_output"

echo -e "${YELLOW}Creating simpletest directories...${NC}"
mkdir -p "$BROWSER_OUTPUT_DIR"
chmod -R 777 "$SIMPLETEST_DIR"
echo -e "${GREEN}✓${NC} Created and configured: $SIMPLETEST_DIR"

# Ensure sites/simpletest directory exists and is writable
SITES_SIMPLETEST="web/sites/simpletest"
if [ ! -d "$SITES_SIMPLETEST" ]; then
    echo -e "${YELLOW}Creating $SITES_SIMPLETEST directory...${NC}"
    mkdir -p "$SITES_SIMPLETEST"
fi

chmod -R 775 "$SITES_SIMPLETEST"
echo -e "${GREEN}✓${NC} Configured permissions for: $SITES_SIMPLETEST"

# Clean up old test sites (optional, uncomment if needed)
# echo -e "${YELLOW}Cleaning up old test sites...${NC}"
# find "$SITES_SIMPLETEST" -mindepth 1 -maxdepth 1 -type d -mtime +7 -exec rm -rf {} \;
# echo -e "${GREEN}✓${NC} Cleaned up old test sites"

echo -e "${GREEN}Test environment setup complete!${NC}"
echo ""
echo -e "You can now run tests with:"
echo -e "  ${YELLOW}./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml${NC}"
