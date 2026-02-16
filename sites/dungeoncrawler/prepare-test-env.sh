#!/bin/bash
# Script to prepare the test environment for PHPUnit functional tests
# This ensures proper directory structure and permissions

set -e

echo "Preparing test environment for Dungeon Crawler tests..."

# Ensure simpletest directory exists with proper permissions
SIMPLETEST_DIR="web/sites/simpletest"
if [ ! -d "$SIMPLETEST_DIR" ]; then
  echo "Creating $SIMPLETEST_DIR directory..."
  mkdir -p "$SIMPLETEST_DIR"
fi

echo "Setting permissions on $SIMPLETEST_DIR to 777..."
chmod 777 "$SIMPLETEST_DIR"

# Clean any stale test directories
echo "Cleaning stale test directories..."
rm -rf "$SIMPLETEST_DIR"/*

# Ensure /tmp/dungeoncrawler-simpletest exists
TMP_DIR="/tmp/dungeoncrawler-simpletest"
if [ ! -d "$TMP_DIR" ]; then
  echo "Creating $TMP_DIR directory..."
  mkdir -p "$TMP_DIR"
fi
echo "Setting permissions on $TMP_DIR to 777..."
chmod 777 "$TMP_DIR"

# Create browser_output directory
BROWSER_OUTPUT="$TMP_DIR/browser_output"
if [ ! -d "$BROWSER_OUTPUT" ]; then
  echo "Creating $BROWSER_OUTPUT directory..."
  mkdir -p "$BROWSER_OUTPUT"
fi
echo "Setting permissions on $BROWSER_OUTPUT to 777..."
chmod 777 "$BROWSER_OUTPUT"

echo "✅ Test environment is ready!"
echo ""
echo "You can now run tests with:"
echo "  cd sites/dungeoncrawler"
echo "  ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml"
