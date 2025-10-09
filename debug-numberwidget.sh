#!/bin/bash

# Script to debug and fix NumberWidget configuration issues on production
# Run this on the production server to identify and fix the field causing problems

echo "=== Drupal NumberWidget Debug Script ==="
echo "Date: $(date)"
echo ""

# Clear all caches first
echo "1. Clearing Drupal caches..."
drush cache:rebuild

echo ""
echo "2. Checking current form display configuration..."
drush config:get core.entity_form_display.profile.job_seeker.default

echo ""
echo "3. Looking for integer fields without proper widget configuration..."

# Check if configuration import is needed
echo ""
echo "4. Checking if configuration sync is needed..."
drush config:status

echo ""
echo "5. Importing latest configuration..."
drush config:import -y

echo ""
echo "6. Clearing caches again after config import..."
drush cache:rebuild

echo ""
echo "7. Final configuration check..."
drush config:get core.entity_form_display.profile.job_seeker.default --include-overridden

echo ""
echo "=== Debug script completed ==="
echo "Now test the job seeker profile page: /user/1/job_seeker"