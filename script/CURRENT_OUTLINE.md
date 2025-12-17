# Current setup.sh Script Outline

## Overview
The current script has significant duplication and references to multiple sites that should be removed.

## Structure

### STEP 1: ENVIRONMENT SETUP (Line 147)
- System dependencies installation
- PHP 8.3 installation and configuration
- Composer installation
- MySQL/MariaDB installation and configuration
- Apache installation and configuration
- Git, Node.js, npm installation
- Resume text extraction tools
- H3 Geolocation Framework setup
- PHP 8.3 as default version
- MySQL database creation (forseti_dev)
- Private files directory creation (/var/private/forseti)
- Apache virtual host configuration

### STEP 2: DRUPAL INSTALLATION - Forseti site (Line 573)
- Check if Forseti directory exists at /sites/forseti
- Install/repair Composer dependencies
- Install Drush
- Install development modules (devel, admin_toolbar, pathauto, metatag, etc.)
- Check if Drupal is installed
- Install Drupal if needed
- Enable development modules
- Enable custom modules if they exist
- Enable and set custom theme (forseti)
- Configure home page
- Create development directories
- Add development settings to settings.php
- Create settings.local.php

### STEP 3: DEVELOPMENT CONFIGURATION (Line 916) **DUPLICATE AT LINE 1460**
- Installing Drupal Coder and PHP CodeSniffer
- Installing additional development tools (phpunit, symfony)
- Configuring PHP CodeSniffer for Drupal standards
- Creating development services configuration
- Creating custom module template

### STEP 2.6: FORSETI SITE SETUP (Line 1202) **DUPLICATE - SHOULD BE REMOVED**
- Exact duplicate of Step 2 content
- Configuration for Forseti site (duplicate variables)
- Same installation process as Step 2
- Should be completely removed

### STEP 3: DEVELOPMENT CONFIGURATION (Line 1460) **DUPLICATE**
- Exact duplicate of earlier Step 3
- Should be removed

### STEP 4: POST-INSTALLATION FIXES (Line 1918)
- **Contains references to theoryofconspiracies and stlouisintegration**
- Loop through multiple sites: "stlouisintegration", "forseti", "theoryofconspiracies"
- Drupal installation fixes
- CORS module installation
- Simple OAuth fixes
- Cache rebuilds for multiple sites
- **NEEDS CLEANUP: Remove references to other sites**

### STEP 5: H3 GEOLOCATION DATABASE SETUP (Line 2109)
- AmISafe crime mapping pipeline initialization
- MySQL connection testing
- H3 Python environment verification
- Database table verification
- ETL pipeline status
- **MAY contain references to other sites - needs review**

## Issues Found

1. **Duplicate Sections:**
   - Step 3 appears twice (lines 916 and 1460)
   - Step 2.6 is a complete duplicate of Step 2 (line 1202)

2. **References to Other Sites:**
   - Theory of Conspiracies site references throughout
   - stlouisintegration references in loops and directory checks
   - Database: theoryofconspiracies_dev references

3. **Structural Issues:**
   - Step numbering is confusing (2, 3, 2.6, 3 again)
   - Multiple site loops in Step 4

## Lines with Other Site References
- Line 1075-1120: Theory of Conspiracies theme and site creation (in TOC section)
- Line 1202-1459: Entire Step 2.6 (duplicate Forseti setup)
- Line 1460-onwards: Duplicate Step 3
- Line 1716-1873: Theory of Conspiracies references in Step 4
- Line 1924, 1950, 2008, 2040, 2092: Loops over multiple sites including theoryofconspiracies
