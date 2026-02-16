#!/bin/bash
#
# Setup script for DungeonCrawler PHPUnit tests
# This script prepares the test environment by creating necessary directories
# and setting proper permissions.
#
# Usage: ./tests/setup-test-environment.sh

set -e

echo "Setting up test environment..."

# Create temporary directories for simpletest
echo "Creating temporary test directories..."
mkdir -p /tmp/dungeoncrawler-simpletest/browser_output
chmod -R 777 /tmp/dungeoncrawler-simpletest

# Ensure simpletest directory in web root exists and is writable
echo "Setting permissions on simpletest directory..."
mkdir -p web/sites/simpletest
chmod 777 web/sites/simpletest

# Create default site directories if they don't exist
echo "Ensuring default site directories exist..."
mkdir -p web/sites/default/files
chmod 777 web/sites/default/files

echo "Test environment setup complete!"
echo ""
echo "You can now run PHPUnit tests:"
echo "  cd sites/dungeoncrawler"
echo "  ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml"
