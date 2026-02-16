#!/bin/bash
# Setup script for Dungeon Crawler tests
# This script prepares the environment for running PHPUnit tests

set -e

# Get the script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "Setting up test environment for Dungeon Crawler..."

# Ensure simpletest directory exists and is writable
SIMPLETEST_DIR="$PROJECT_ROOT/web/sites/simpletest"
if [ ! -d "$SIMPLETEST_DIR" ]; then
    echo "Creating simpletest directory: $SIMPLETEST_DIR"
    mkdir -p "$SIMPLETEST_DIR"
fi

# Ensure it's writable
chmod 775 "$SIMPLETEST_DIR"
echo "✓ Simpletest directory is ready: $SIMPLETEST_DIR"

# Ensure vendor dependencies are installed
if [ ! -d "$PROJECT_ROOT/vendor" ]; then
    echo "Installing composer dependencies..."
    cd "$PROJECT_ROOT"
    composer install --no-interaction
fi

echo "✓ Test environment setup complete!"
echo ""
echo "You can now run tests with:"
echo "  cd $PROJECT_ROOT"
echo "  ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml"
