# Setup.sh Complete Documentation & Status Tracking
**Generated:** December 17, 2025  
**Purpose:** Comprehensive documentation combining current state, target state, and action plan for forseti.life setup script

---

## Table of Contents
1. [Executive Summary](#executive-summary)
2. [Current State Analysis](#current-state-analysis)
3. [Target State Definition](#target-state-definition)
4. [Dependency Order & Best Practices](#dependency-order--best-practices)
5. [Detailed Section Status](#detailed-section-status)
6. [Action Plan](#action-plan)

---

## Executive Summary

### Script Purpose
Complete development environment setup for forseti.life Drupal website, including:
- System dependencies (PHP 8.3, MySQL, Apache, etc.)
- Drupal 11.2.5 installation
- Development tools and configurations
- H3 Geolocation Framework for AmISafe crime mapping
- Database setup and optimization

### Current Issues
- **Duplicate Sections:** Step 3 appears twice, Step 2.6 duplicates Step 2
- **Corrupted Code:** Theory of Conspiracies installation code mixed into Step 3
- **Multi-Site References:** References to stlouisintegration and theoryofconspiracies throughout
- **Line Count:** 2,256 lines (should be ~1,800 after cleanup)

### Target Goal
- Single-site focus on forseti.life only
- Clean, linear execution (Steps 1-5)
- No duplications or corrupted sections
- Consistent variable usage and naming

---

## Current State Analysis

### File Statistics
- **Total Lines:** 2,256
- **Clean Sections:** 2 of 5 (Sections 0, 2)
- **Corrupted Sections:** 1 (Section 3 First Instance)
- **Duplicate Sections:** 2 (Section 2.6, Section 3 Second Instance)
- **Sections Needing Updates:** 2 (Sections 4, 5)

### Structure Overview

#### STEP 1: ENVIRONMENT SETUP (Line 147)
**Status:** ✅ MOSTLY CLEAN  
**Contents:**
- System dependencies installation
- PHP 8.3 installation and configuration
- Composer installation
- MySQL/MariaDB installation and configuration
- Apache installation and configuration
- Git, Node.js, npm installation
- Resume text extraction tools
- H3 Geolocation Framework setup
- MySQL database creation (forseti_dev)
- Private files directory creation (/var/private/forseti)
- Apache virtual host configuration

#### STEP 2: DRUPAL INSTALLATION - Forseti site (Line 573)
**Status:** ✅ CLEAN  
**Contents:**
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

#### STEP 3: DEVELOPMENT CONFIGURATION - First Instance (Line 916)
**Status:** ❌ CORRUPTED  
**Issue:** Contains Theory of Conspiracies installation code (lines 955-1204)  
**Expected Contents:**
- Installing Drupal Coder and PHP CodeSniffer
- Installing additional development tools (phpunit, symfony)
- Configuring PHP CodeSniffer for Drupal standards
- Creating development services configuration
- Creating custom module template

#### STEP 2.6: FORSETI SITE SETUP (Line 1202)
**Status:** ❌ DUPLICATE - SHOULD BE REMOVED  
**Issue:** Exact duplicate of Step 2 content

#### STEP 3: DEVELOPMENT CONFIGURATION - Second Instance (Line 1460)
**Status:** ⚠️ DUPLICATE BUT CLEAN  
**Issue:** Duplicate of Step 3, but this version is correct  
**Action:** Keep this version, remove first instance

#### STEP 4: POST-INSTALLATION FIXES (Line 1918)
**Status:** ⚠️ NEEDS CLEANUP  
**Issues:**
- Contains references to theoryofconspiracies and stlouisintegration
- Loops through multiple sites
- Multi-site Composer verification
- Multi-site cache rebuilds

#### STEP 5: H3 GEOLOCATION DATABASE SETUP (Line 2109)
**Status:** ⚠️ NEEDS REVIEW  
**Issues:**
- May contain references to other databases
- May contain multi-site completion messages

### Problem Lines Identified
- **Lines 955-1204:** Theory of Conspiracies installation (CORRUPTED SECTION)
- **Lines 1202-1462:** Duplicate Forseti setup (Step 2.6)
- **Lines 1463-1920:** Duplicate Step 3 (but clean version to keep)
- **Lines 1924+:** Multi-site loops in Step 4
- **Lines 2038+:** Multi-site Composer verification
- **Lines 2066+:** References to stlouisintegration
- **Lines 2244+:** Multi-database references

---

## Target State Definition

### Goal
Clean, focused setup script for forseti.life website only, with proper dependency ordering and no duplication.

### Target Structure

#### STEP 1: ENVIRONMENT SETUP
**Purpose:** Install all system dependencies and configure the development environment

**Components:**
- PHP 8.3 from Sury repository
- PHP extensions (gd, xml, mbstring, curl, zip, bcmath, json, mysql, etc.)
- Composer with PHP 8.3 verification
- MySQL/MariaDB with performance tuning
- Apache with PHP 8.3 module
- Git, Node.js, npm
- Development tools (unzip, wget, curl, vim, htop)
- Resume text extraction (poppler-utils, docx2txt, antiword)
- H3 Geolocation Framework:
  - System packages (python3-dev, build-essential, libgeos-dev, libproj-dev, libgdal-dev)
  - Python virtual environment (h3-geolocation/h3-env)
  - H3 packages (h3, pandas, numpy, mysql-connector-python, matplotlib, folium, etc.)
- MySQL database (forseti_dev ONLY)
- Private files directory (/var/private/forseti ONLY)
- Apache virtual host (forseti.local)

#### STEP 2: DRUPAL INSTALLATION - Forseti Site
**Purpose:** Set up the Forseti Drupal website

**Components:**
- Create/verify /sites/forseti directory
- Install Composer dependencies
- Install Drush
- Install development modules:
  - drupal/devel
  - drupal/admin_toolbar
  - drupal/pathauto
  - drupal/metatag
  - drupal/backup_migrate
  - drupal/bootstrap5
  - drupal/radix
  - drupal/recaptcha
  - drupal/recaptcha_v3
  - drupal/profile
  - aws/aws-sdk-php
  - defuse/php-encryption
- Database verification
- Drupal site:install (if needed)
- Enable development modules
- Enable custom modules
- Enable and set custom theme (forseti)
- Configure home page
- Create development directories
- Configure settings.php and settings.local.php

#### STEP 3: DEVELOPMENT CONFIGURATION
**Purpose:** Set up development tools and configurations

**Components:**
- Drupal Coder and PHP CodeSniffer
- PHPUnit and Symfony PHPUnit Bridge
- PHP CodeSniffer configuration for Drupal standards
- Development services (development.services.yml)
- Custom module template README (Forseti branding)
- Custom theme template README (Forseti branding)
- Git ignore configuration
- Verification

#### STEP 4: POST-INSTALLATION FIXES
**Purpose:** Apply known issue resolutions (FORSETI ONLY)

**Components:**
- Fix cache backend configuration
- Clean cache references from settings
- Install CORS module (if needed)
- Fix Simple OAuth (if exists)
- Composer verification (forseti site only)
- Final cache rebuild
- Site verification

#### STEP 5: H3 GEOLOCATION DATABASE SETUP
**Purpose:** Initialize AmISafe crime mapping pipeline

**Components:**
- MySQL connection test
- H3 Python environment verification
- Database table verification (amisafe schema)
- ETL pipeline status
- Database statistics
- Import instructions

#### COMPLETION MESSAGE
**Purpose:** Provide next steps

**Components:**
- Installation summary
- Access URLs: http://forseti.local
- Credentials
- Common commands
- Troubleshooting tips

### Target Variables
```bash
PROJECT_NAME="forseti"
PROJECT_DIR="/home/keithaumiller/forseti.life/sites/forseti"
DB_NAME="forseti_dev"
DB_USER="drupal_user"
DB_PASSWORD="drupal_secure_password"
DB_HOST="127.0.0.1"
SITE_NAME="Forseti"
ADMIN_USER="admin"
ADMIN_PASSWORD="admin_secure_password"
ADMIN_EMAIL="admin@forseti.life"
```

---

## Dependency Order & Best Practices

### Correct Dependency Chain

#### Phase 1: System Dependencies (Step 1)
```
1. PHP 8.3 → 2. MySQL → 3. Apache → 4. Composer
```
**Critical:** Each depends on the previous being installed and configured.

#### Phase 2: Database Setup (Step 1 continued)
```
1. MySQL running → 2. Create forseti_dev database → 3. Create drupal_user
```
**Critical:** Database must exist before Drupal installation.

#### Phase 3: Drupal Installation (Step 2)
```
1. Composer dependencies → 2. Drush → 3. Drupal core → 4. Database verification
```
**Critical:** Verify database tables exist before enabling modules.

#### Phase 4: Module Enablement (Step 2 continued)
**Order is critical:**
```
1. Drupal bootstrap verification
   ↓
2. Development modules (devel, admin_toolbar, pathauto, metatag)
   ↓
3. Development module verification
   ↓
4. Dependency modules (profile, entity)
   ↓
5. Custom modules in dependency order
   ↓
6. Cache rebuild before complex modules
   ↓
7. Complex modules last
```

#### Phase 5: Theme Setup (Step 2 final)
**Only after modules work:**
```
1. Enable theme → 2. Set as default → 3. Configure → 4. Final cache rebuild
```

### Safety Mechanisms

**Bootstrap Verification:**
```bash
if drush status | grep -q "Drupal bootstrap.*Successful"; then
    # Proceed with module enablement
fi
```

**Database Table Verification:**
```bash
if drush sql:query "SHOW TABLES LIKE 'users'" | grep -q "users"; then
    # Drupal is installed
fi
```

**Development Module Verification:**
```bash
if drush pm:list --status=enabled | grep -q "devel"; then
    # Prerequisites met, enable custom modules
fi
```

**Error Handling:**
```bash
operation 2>/dev/null || true  # For non-critical operations
```

**Strategic Cache Rebuilds:**
- After enabling development modules
- Before enabling complex modules
- After theme changes
- Final verification rebuild

### Conditional Logic Pattern
```bash
if [ "$DRUPAL_INSTALLED" = true ]; then
    if drush status shows successful bootstrap; then
        if development modules enabled successfully; then
            # Enable custom modules in order
            # Enable theme
            # Final verification
        fi
    fi
fi
```

---

## Detailed Section Status

### Section 0: Header & Configuration (Lines 1-146)
**Status:** ✅ CLEAN  
**Purpose:** Script initialization, variable declarations, utility functions  
**Contents:**
- Shebang and script description
- Color definitions for output
- Utility functions (print_status, print_warning, print_error, print_step)
- fix_drupal_permissions function
- Configuration variables
- ensure_mysql_running function  
**Issues:** None  
**Action Required:** None

---

### Section 1: Environment Setup (Lines 147-572)
**Status:** ✅ MOSTLY CLEAN  
**Purpose:** Install system dependencies  
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
**Issues:** Need to verify no lingering multi-site references  
**Action Required:** Final verification pass

---

### Section 2: Drupal Installation - Forseti (Lines 573-915)
**Status:** ✅ CLEAN  
**Purpose:** Set up the Forseti Drupal website  
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
**Issues:** None  
**Action Required:** None

---

### Section 3: Development Configuration - FIRST INSTANCE (Lines 916-1204)
**Status:** ❌ CORRUPTED  
**Purpose:** Set up development tools  
**Expected Contents:**
- Install Drupal Coder and PHP CodeSniffer
- Install phpunit and symfony
- Configure PHP CodeSniffer
- Create development.services.yml
- Create custom module template README  
**Actual Contents:**
- Lines 916-954: Correct start of Step 3
- **Lines 955-1204: CORRUPTED - Theory of Conspiracies installation section**
  - Composer autoloader fixes
  - Site installation for theoryofconspiracies
  - Development modules installation
  - Custom modules enabling (ai_conversation, theory_content, amisafe)
  - Theme installation (theoryofconspiracies)
  - Home page configuration
  - Settings file creation  
**Action Required:** **DELETE lines 955-1204 entirely**

---

### Section 2.6: Duplicate Forseti Setup (Lines 1205-1462)
**Status:** ❌ DUPLICATE  
**Purpose:** NONE - Should not exist  
**Contents:**
- Exact duplicate of Section 2 (Drupal Installation)
- Configuration for Forseti site (duplicate variables)
- Same installation process  
**Action Required:** **DELETE lines 1205-1462 entirely**

---

### Section 3: Development Configuration - SECOND INSTANCE (Lines 1463-1920)
**Status:** ⚠️ CLEAN BUT NEEDS MINOR UPDATES  
**Purpose:** Set up development tools (LEGITIMATE VERSION)  
**Contents:**
- Install Drupal Coder and PHP CodeSniffer
- Install phpunit and symfony
- Configure PHP CodeSniffer
- Create development.services.yml
- Create custom module template README (references "St. Louis Integration")
- Create custom theme template README (references "St. Louis Integration")
- Git ignore configuration
- Completion message (multi-site references)  
**Issues:**
- Line ~1503: "St. Louis Integration" should be "Forseti"
- Line ~1558: "St. Louis Integration" should be "Forseti"
- Lines 1900-1916: Multi-site completion messages  
**Action Required:** 
1. Replace "St. Louis Integration" → "Forseti" in README templates
2. Update completion message to single-site

---

### Section 4: Post-Installation Fixes (Lines 1921-2107)
**Status:** ⚠️ PARTIALLY CLEAN  
**Purpose:** Apply known issue resolutions  
**Contents:**
- Fix cache backend configuration
- Clean cache references from settings files
- Install CORS module
- Fix Simple OAuth issues
- Final Composer verification
- Cache rebuilds  
**Issues:**
- Line ~1925: May have multi-site loop for services files
- Line ~1948: Settings file cleaning may have old site_dir variable
- Line ~2038: Multi-site loop for Composer verification
- Line ~2066: References to stlouisintegration site
- Verification sections reference stlouisintegration  
**Action Required:**
1. Replace multi-site loops with forseti-only operations
2. Update all site references to PROJECT_DIR
3. Remove stlouisintegration verification

---

### Section 5: H3 Geolocation Setup (Lines 2108-2255)
**Status:** ⚠️ NEEDS REVIEW  
**Purpose:** Initialize AmISafe crime mapping pipeline  
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
- Pipeline scripts may reference wrong directories  
**Action Required:**
1. Review database references
2. Update script paths to forseti only
3. Update completion message

---

## Action Plan

### Priority 1: Remove Corrupted/Duplicate Code (CRITICAL)

#### Action 1.1: Fix Section 3 First Instance
**Target:** Lines 955-1204  
**Action:** DELETE entire Theory of Conspiracies section  
**Method:** Replace corrupted section with proper continuation of Step 3

#### Action 1.2: Remove Section 2.6 Duplicate
**Target:** Lines 1205-1462  
**Action:** DELETE entire duplicate Forseti setup section  
**Method:** Direct deletion of duplicate Step 2.6

**Expected Result:** Reduce file from 2,256 lines to ~1,700 lines

---

### Priority 2: Update Branding References (MEDIUM)

#### Action 2.1: Fix Section 3 Second Instance
**Target:** Lines 1463-1920 (will shift after deletions)  
**Changes:**
1. Line ~1503: Custom module README "St. Louis Integration" → "Forseti"
2. Line ~1558: Custom theme README "St. Louis Integration" → "Forseti"
3. Lines 1900-1916: Update completion message to single-site

---

### Priority 3: Remove Multi-Site Operations (MEDIUM)

#### Action 3.1: Fix Section 4 - Cache Backend Configuration
**Target:** Lines ~1925  
**Change:** Replace multi-site loop with forseti-only operation

#### Action 3.2: Fix Section 4 - Settings File Cleaning
**Target:** Lines ~1948  
**Change:** Use PROJECT_DIR instead of site_dir variable

#### Action 3.3: Fix Section 4 - Composer Verification
**Target:** Lines ~2038  
**Change:** Replace multi-site loop with forseti-only operation

#### Action 3.4: Fix Section 4 - Site Verification
**Target:** Lines ~2066  
**Change:** Remove stlouisintegration references, use PROJECT_DIR

---

### Priority 4: Clean Section 5 (LOW)

#### Action 4.1: Review Database References
**Target:** Section 5 (Lines 2108-2255)  
**Action:** Replace any theoryofconspiracies_dev references with forseti_dev only

#### Action 4.2: Update Script Paths
**Target:** Section 5  
**Action:** Update any pipeline script references to forseti site

#### Action 4.3: Update Completion Message
**Target:** Section 5 end  
**Action:** Single-site completion message

---

### Priority 5: Final Verification (REQUIRED)

#### Action 5.1: Variable Consistency Check
**Verify:**
- All references use PROJECT_DIR
- All references use DB_NAME="forseti_dev"
- No hardcoded paths to other sites
- No loops over multiple sites

#### Action 5.2: Functional Test
**Test:**
1. Syntax check: `bash -n setup.sh`
2. Review all print_status messages for branding
3. Review all database operations
4. Review all directory operations

---

## Execution Order

1. **Session 1:** Remove corrupted code
   - Delete lines 955-1204 (Theory of Conspiracies)
   - Delete lines 1205-1462 (Duplicate Step 2.6)
   - Verify file integrity

2. **Session 2:** Update branding
   - Fix Section 3 README templates
   - Update completion messages

3. **Session 3:** Remove multi-site operations
   - Fix Section 4 loops
   - Update variable usage

4. **Session 4:** Clean Section 5
   - Review database references
   - Update script paths

5. **Session 5:** Final verification
   - Variable consistency
   - Syntax check
   - Functional review

---

## Success Criteria

- [ ] File reduced to ~1,800 lines
- [ ] No duplicate sections
- [ ] No corrupted code
- [ ] All references to "forseti" or "Forseti"
- [ ] Single database: forseti_dev
- [ ] Single site directory: /sites/forseti
- [ ] No multi-site loops
- [ ] Consistent variable usage
- [ ] Passes bash syntax check
- [ ] All print messages use correct branding

---

## Notes

- Always test after each major change
- Keep backups before large deletions
- Verify line numbers shift after deletions
- Use consistent spacing and formatting
- Follow existing code style
- Add comments for clarity where needed

---

**Last Updated:** December 17, 2025  
**Status:** Documentation Complete - Ready for Systematic Cleanup
