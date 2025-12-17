# Setup.sh Section Status Report
Generated: December 17, 2025

## Overview
Total Lines: 2,256
Major Issues: File contains duplicate sections and corrupted code from partial edits

## Section Breakdown

### SECTION 0: Header & Configuration (Lines 1-146)
**Purpose:** Script initialization, variable declarations, utility functions
**Status:** ✅ CLEAN - Properly configured for forseti.life
**Contents:**
- Shebang and script description
- Color definitions for output
- Utility functions (print_status, print_warning, print_error, print_step)
- fix_drupal_permissions function
- Configuration variables (PROJECT_DIR, DB_NAME, etc.)
- ensure_mysql_running function
**Issues:** None
**Action Required:** None - section is correct

---

### SECTION 1: Environment Setup (Lines 147-572)
**Purpose:** Install system dependencies (PHP 8.3, Composer, MySQL, Apache, etc.)
**Status:** ✅ MOSTLY CLEAN - Appears functional
**Contents:**
- PHP 8.3 installation from Sury repository
- PHP extensions installation
- Composer installation
- MySQL/MariaDB installation and configuration
- Apache installation and PHP module configuration
- Git, Node.js, npm installation
- Development tools installation
- Resume text extraction tools
- H3 Geolocation Framework setup
- MySQL database creation (forseti_dev only)
- Private files directory creation (/var/private/forseti)
- Apache virtual host configuration (forseti.local)
**Issues:** Need to verify no stlouisintegration or theoryofconspiracies references
**Action Required:** Review for any lingering multi-site references

---

### SECTION 2: Drupal Installation - Forseti (Lines 573-915)
**Purpose:** Set up the Forseti Drupal website
**Status:** ✅ CLEAN - Properly targets forseti site only
**Contents:**
- Check/create Forseti directory at /sites/forseti
- Install Composer dependencies
- Install Drush
- Install development modules
- Check if Drupal is installed
- Run site:install if needed
- Enable development modules
- Enable custom modules
- Enable and set custom theme (forseti)
- Configure Forseti home page
- Create development directories
- Add development settings
- Create settings.local.php
**Issues:** None apparent
**Action Required:** None - section appears correct

---

### SECTION 3: Development Configuration - FIRST INSTANCE (Lines 916-1204)
**Purpose:** Set up development tools
**Status:** ⚠️ CORRUPTED - Contains broken code mixed with Theory of Conspiracies content
**Contents (Expected):**
- Install Drupal Coder and PHP CodeSniffer
- Install phpunit and symfony
- Configure PHP CodeSniffer
- Create development.services.yml
- Create custom module template README
**Issues:**
- Line 947-954: Starts creating custom module template README
- Line 955-1203: CORRUPTED - Contains entire Theory of Conspiracies installation section that should not exist
- Theory of Conspiracies code includes:
  - Composer autoloader fixes
  - Site installation
  - Development modules installation
  - Custom modules enabling
  - Theme installation (theoryofconspiracies)
  - Home page configuration
  - Settings file creation
**Action Required:** DELETE lines 955-1204 entirely (Theory of Conspiracies section)

---

### SECTION 2.6: Duplicate Forseti Setup (Lines 1205-1462)
**Purpose:** DUPLICATE - Should not exist
**Status:** ❌ DUPLICATE SECTION - Must be deleted
**Contents:**
- Exact duplicate of Section 2 (Drupal Installation)
- Sets up Forseti site again (duplicate variables)
- Same installation process
**Issues:** Entire section is a duplicate
**Action Required:** DELETE lines 1205-1462 entirely

---

### SECTION 3: Development Configuration - SECOND INSTANCE (Lines 1463-1920)
**Purpose:** Set up development tools
**Status:** ✅ APPEARS CLEAN - This is the legitimate version
**Contents:**
- Install Drupal Coder and PHP CodeSniffer
- Install phpunit and symfony
- Configure PHP CodeSniffer
- Create development.services.yml
- Create custom module template README (St. Louis Integration reference - needs fix)
- Create custom theme template README (St. Louis Integration reference - needs fix)
- Git ignore configuration
- Completion message
**Issues:**
- References to "St. Louis Integration" in README templates (lines ~1503, 1558)
- Multi-site completion messages (lines 1900-1916)
**Action Required:** Replace "St. Louis Integration" with "Forseti" in README templates

---

### SECTION 4: Post-Installation Fixes (Lines 1921-2107)
**Purpose:** Apply known issue resolutions
**Status:** ⚠️ PARTIALLY CLEAN - Some multi-site loops remain
**Contents:**
- Fix cache backend configuration
- Clean cache references from settings files
- Install CORS module
- Fix Simple OAuth issues
- Final Composer verification
- Cache rebuilds
**Issues:**
- Line ~1925: Originally had multi-site loop (may be partially fixed)
- Line ~1948: Settings file references may still have old site_dir variable
- Line ~2038: Multi-site loop for Composer verification
- Line ~2066: References to stlouisintegration site
- Verification sections reference stlouisintegration
**Action Required:** Replace all multi-site loops with forseti-only operations

---

### SECTION 5: H3 Geolocation Setup (Lines 2108-2255)
**Purpose:** Initialize AmISafe crime mapping pipeline
**Status:** ⚠️ UNKNOWN - Contains multi-site references
**Contents:**
- MySQL connection testing
- H3 Python environment verification
- Database table verification
- ETL pipeline status
- Completion message
**Issues:**
- May contain references to theoryofconspiracies_dev database
- May contain references to stlouisintegration in scripts
- Completion message likely references multiple sites
**Action Required:** Review and replace multi-site references with forseti-only

---

## Summary of Required Actions

### HIGH PRIORITY (Corrupted Code)
1. **DELETE Lines 955-1204:** Entire Theory of Conspiracies section corrupting Step 3
2. **DELETE Lines 1205-1462:** Duplicate Step 2.6 (Forseti setup duplicate)

### MEDIUM PRIORITY (References to Update)
3. **Section 3 (Lines 1463-1920):** Replace "St. Louis Integration" with "Forseti" in README templates
4. **Section 4 (Lines 1921-2107):** Replace multi-site loops with forseti-only operations
5. **Section 5 (Lines 2108-2255):** Remove references to other databases and sites

### LOW PRIORITY (Verification)
6. **Section 1 (Lines 147-572):** Verify no lingering multi-site references
7. **All Sections:** Verify variable consistency (always use PROJECT_DIR, DB_NAME, etc.)

## Recommended Approach

1. Start with Section 3 first instance (lines 916-1204) - Fix the corruption
2. Remove Section 2.6 duplicate (lines 1205-1462)
3. Clean Section 3 second instance (lines 1463-1920) - Update references
4. Clean Section 4 (lines 1921-2107) - Remove multi-site loops
5. Clean Section 5 (lines 2108-2255) - Remove multi-site references
6. Final verification pass through all sections
