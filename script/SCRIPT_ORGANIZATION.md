# Script Directory Organization - Updated December 2024

## Overview

The `/script` directory contains setup and maintenance scripts for the Forseti project, including multi-site Drupal development and React Native mobile app development.

## Current Script Structure

```
script/
├── README.md                          # Main documentation
├── MOBILE_SCRIPTS_MIGRATION.md        # Migration guide (NEW)
│
├── Core Setup Scripts
├── complete-setup.sh                  # Multi-site Drupal setup
├── quick-start.sh                     # Quick service restart
├── verify-setup.sh                    # Environment verification
│
├── Mobile Development (NEW)
├── setup-forseti-mobile-dev.sh        # ⭐ CONSOLIDATED mobile setup
│
├── Database Scripts
├── database/
│   ├── setup_consolidated.sh          # H3 database setup
│   ├── DATABASE_CONSOLIDATION.md      # Database docs
│   └── legacy/                        # Old database scripts
│
├── Utilities
├── comprehensive-cache-disable.sh     # Drupal cache management
│
└── Archive
    ├── archive/                       # Legacy Drupal scripts
    │   ├── setup-environment.sh
    │   ├── install-drupal.sh
    │   └── configure-development.sh
    │
    └── archive/mobile-legacy/         # Legacy mobile scripts (NEW)
        ├── README.md                  # Archive explanation
        ├── setup-mobile.sh
        ├── setup-mobile-web.sh
        └── setup-android-build.sh
```

## Script Categories

### 1. Multi-Site Drupal Setup

**Primary Scripts:**
- `complete-setup.sh` - Full multi-site Drupal environment
- `quick-start.sh` - Restart services after workspace reboot
- `verify-setup.sh` - Verify Drupal installation

**Status:** ✅ Production-ready, actively maintained

### 2. Forseti Mobile Development (Updated!)

**Current Script:**
- `setup-forseti-mobile-dev.sh` ⭐ **NEW - USE THIS**
  - Replaces 3 old scripts with one comprehensive solution
  - Installs: React Native, ESLint, Prettier, Jest, TypeScript
  - Optional: Android SDK, Web preview
  - Verifies: All configuration files
  - Features: Progress indicators, error handling, flexible options

**Archived Scripts:**
- `archive/mobile-legacy/setup-mobile.sh` ❌ Deprecated
- `archive/mobile-legacy/setup-mobile-web.sh` ❌ Deprecated  
- `archive/mobile-legacy/setup-android-build.sh` ❌ Deprecated

**Status:** ✅ Consolidated and improved

### 3. Database Management

**Scripts:**
- `database/setup_consolidated.sh` - H3 geolocation database setup

**Status:** ✅ Stable, documented

### 4. Utilities

**Scripts:**
- `comprehensive-cache-disable.sh` - Drupal cache management

**Status:** ✅ Stable

## Usage Guide

### First-Time Setup

#### For Multi-Site Drupal:
```bash
cd /home/keithaumiller/forseti.life/script
./complete-setup.sh
```

#### For Forseti Mobile Development:
```bash
cd /home/keithaumiller/forseti.life/script
./setup-forseti-mobile-dev.sh

# With options:
./setup-forseti-mobile-dev.sh --skip-android    # Skip Android SDK
./setup-forseti-mobile-dev.sh --skip-web        # Skip web preview
./setup-forseti-mobile-dev.sh --quick           # Essential only
```

### After Workspace Restart

#### For Drupal sites:
```bash
./quick-start.sh
```

#### For mobile development:
```bash
cd /home/keithaumiller/forseti.life/forseti-mobile
npm start                    # Start Metro bundler
npm run web                  # Start web server
```

### Verification

#### Verify Drupal setup:
```bash
./verify-setup.sh
```

#### Verify mobile setup:
```bash
cd /home/keithaumiller/forseti.life/forseti-mobile
npm run lint                 # Check code quality
npm test                     # Run tests
npm run type-check           # TypeScript validation
```

## Recent Changes (December 2024)

### What Changed

1. **Consolidated Mobile Scripts** ✅
   - Combined 3 scripts into 1: `setup-forseti-mobile-dev.sh`
   - Added comprehensive development tools setup
   - Improved error handling and user feedback
   - Added flexible command-line options

2. **Moved Old Scripts to Archive** ✅
   - `archive/mobile-legacy/` contains deprecated mobile scripts
   - Added README explaining why they were replaced
   - Old scripts preserved for reference

3. **Updated Documentation** ✅
   - Created `MOBILE_SCRIPTS_MIGRATION.md` migration guide
   - Updated `README.md` with new script organization
   - Created `SCRIPT_ORGANIZATION.md` (this file)

### What Stayed the Same

✅ Drupal setup scripts unchanged
✅ Database management scripts unchanged
✅ Utility scripts unchanged
✅ Quick start script unchanged

## Maintenance Notes

### Scripts Needing Updates

None currently. All scripts are up-to-date and functional.

### Scripts Marked for Future Removal

The following archived scripts may be deleted in future cleanup:
- `archive/mobile-legacy/setup-mobile.sh`
- `archive/mobile-legacy/setup-mobile-web.sh`
- `archive/mobile-legacy/setup-android-build.sh`

**Timeline:** Can be deleted after 3-6 months if no issues reported.

### Scripts Needing Review

None currently.

## Documentation Index

### Setup Documentation
- `README.md` - Main script directory documentation
- `SETUP_DOCUMENTATION.md` - Detailed setup guides
- `MOBILE_SCRIPTS_MIGRATION.md` - Mobile scripts migration guide
- `archive/mobile-legacy/README.md` - Archived scripts explanation

### Database Documentation
- `database/DATABASE_CONSOLIDATION.md` - Database setup details
- H3 geolocation processing documentation

### Mobile App Documentation
- `../forseti-mobile/CRITICAL_FIXES_SUMMARY.md` - Complete mobile setup details
- `../forseti-mobile/QUICK_REFERENCE.md` - Quick command reference
- `../forseti-mobile/ENV_VARIABLES.md` - Environment configuration
- `../forseti-mobile/BEST_PRACTICES_REVIEW.md` - Code quality guidelines

## Best Practices

### When Adding New Scripts

1. **Naming Convention:**
   - Use descriptive names: `setup-<component>-<purpose>.sh`
   - Use hyphens, not underscores: `setup-mobile-dev.sh` ✅ not `setup_mobile_dev.sh` ❌

2. **Script Structure:**
   ```bash
   #!/bin/bash
   # Description of what the script does
   # Usage: ./script-name.sh [OPTIONS]
   
   set -e  # Exit on error
   
   # Color definitions
   # Helper functions
   # Main script logic
   # Summary/next steps
   ```

3. **Make Executable:**
   ```bash
   chmod +x new-script.sh
   ```

4. **Document in README:**
   - Add to appropriate section
   - Include description and usage

5. **Test Thoroughly:**
   - Test on fresh environment
   - Test with and without options
   - Verify error handling

### When Deprecating Scripts

1. **Move to Archive:**
   ```bash
   mkdir -p archive/<category>
   mv old-script.sh archive/<category>/
   ```

2. **Add Archive README:**
   - Explain why deprecated
   - Point to replacement
   - Include migration notes

3. **Update Main README:**
   - Move from active to archived section
   - Add deprecation notice

4. **Create Migration Guide:**
   - Document differences
   - Provide migration steps
   - Include comparison matrix

## Quick Reference

### Most Used Commands

```bash
# Drupal quick start
./quick-start.sh

# Complete mobile setup
./setup-forseti-mobile-dev.sh

# Mobile setup without Android
./setup-forseti-mobile-dev.sh --skip-android

# Verify everything
./verify-setup.sh
```

### Script Execution Patterns

```bash
# Make script executable
chmod +x script-name.sh

# Run script
./script-name.sh

# Run with options
./script-name.sh --option1 --option2

# View help
./script-name.sh --help

# Run in background
nohup ./script-name.sh > output.log 2>&1 &
```

## Support

### If a Script Fails

1. **Check the error message** - Scripts provide detailed feedback
2. **Review documentation** - Check README and related docs
3. **Verify prerequisites** - Node.js, disk space, permissions
4. **Check file paths** - Ensure working directory is correct
5. **Review recent changes** - Check git log for recent updates

### Common Issues

**"Permission denied"**
```bash
chmod +x script-name.sh
```

**"Directory not found"**
```bash
# Check you're in the right directory
pwd
ls -la
```

**"Command not found"**
```bash
# Install missing dependency
sudo apt-get install <package>
```

**npm errors**
```bash
# Try with legacy peer deps
npm install --legacy-peer-deps
```

## Summary

The script directory is now well-organized with:
✅ Clear separation between active and archived scripts
✅ Consolidated mobile development setup
✅ Comprehensive documentation
✅ Best practices established
✅ Migration guides for deprecated scripts

**Recommendation:** Use the new consolidated scripts for all new setups. Old archived scripts are preserved for reference but should not be used.
