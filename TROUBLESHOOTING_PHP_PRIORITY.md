# Troubleshooting Guide - PHP Version Priority in Codespaces

## Issue Summary
When running the `complete-setup.sh` script, the environment appears to work correctly during execution, but manual commands afterward still use PHP 8.0.30 instead of PHP 8.3, leading to Composer dependency failures.

## Root Cause Analysis

### The Problem
GitHub Codespaces automatically configures PHP 8.0.30 and places it in the PATH at `/home/codespace/.php/current/bin`, which takes precedence over system PHP 8.3 even after our script runs.

### Why Standard Solutions Don't Work
1. **PATH modifications** are overridden by Codespace environment reloading
2. **update-alternatives** works but doesn't affect the Codespace PHP symlink
3. **~/.bashrc modifications** load after Codespace's own environment setup

## Solution Implemented

### 1. Codespace PHP Symlink Override
```bash
# Redirect Codespace's PHP symlink to system path
sudo rm -f /home/codespace/.php/current
sudo ln -sf /usr /home/codespace/.php/current
```

### 2. High-Priority Profile Script
Created `/etc/profile.d/00-php83-priority.sh` (note the `00-` prefix for early loading):
- Exports proper PATH with `/usr/bin` first
- Reapplies the symlink override if needed

### 3. Environment Loader Script
Created `/workspaces/stlouisintegration.com/load-php83-env.sh` that can be sourced for immediate PHP 8.3 access:
- Overrides PATH in current session
- Creates shell functions for `php`, `composer`, `drush`
- Can be run anytime: `source /workspaces/stlouisintegration.com/load-php83-env.sh`

### 4. Updated ~/.bashrc Structure
Instead of appending to ~/.bashrc, the script now:
- Puts PHP 8.3 configuration at the TOP of .bashrc
- Ensures it loads before Codespace defaults
- Creates aliases that always use `/usr/bin/php8.3`

## Manual Troubleshooting Steps

If you encounter PHP version issues after running the setup script:

### Immediate Fix (Current Session)
```bash
source /workspaces/stlouisintegration.com/load-php83-env.sh
```

### Fix Corrupted Composer Dependencies
```bash
cd /workspaces/stlouisintegration.com/sites/[site-name]
source /workspaces/stlouisintegration.com/load-php83-env.sh
rm -rf vendor/
composer install
```

### Verify PHP Environment
```bash
which php                    # Should show /usr/bin/php or function
php --version               # Should show PHP 8.3.x
composer --version          # Should work without errors
```

### New Terminal Sessions
New terminal sessions should automatically use PHP 8.3 due to:
1. The redirected Codespace symlink
2. The `/etc/profile.d/00-php83-priority.sh` script
3. The updated ~/.bashrc configuration

If a new terminal doesn't work, run:
```bash
source /workspaces/stlouisintegration.com/load-php83-env.sh
```

## Prevention Strategy

The updated `complete-setup.sh` script now:
1. **Proactively fixes** the Codespace PHP symlink during setup
2. **Creates multiple layers** of PHP priority enforcement
3. **Provides an immediate loader** for troubleshooting
4. **Tests the environment** at the end of setup

## Verification Commands

Test the fixes work correctly:

```bash
# Check symlink override
readlink /home/codespace/.php/current  # Should show "/usr"

# Check PATH priority
echo $PATH | grep -o "/[^:]*php[^:]*" | head -3

# Check PHP version
php --version | head -1

# Test Composer with PHP 8.3
composer about | head -5
```

## Files Modified

1. **`/workspaces/stlouisintegration.com/scripts/complete-setup.sh`**
   - Added Codespace symlink override
   - Enhanced profile script creation
   - Improved ~/.bashrc modification strategy
   - Added environment loader creation
   - Added final verification steps

2. **`/workspaces/stlouisintegration.com/load-php83-env.sh`** (created)
   - Immediate PHP 8.3 environment loader
   - Can be sourced anytime for troubleshooting

3. **`/etc/profile.d/00-php83-priority.sh`** (created by script)
   - System-wide PHP 8.3 priority enforcement
   - Auto-maintains Codespace symlink override

4. **`~/.bashrc`** (modified by script)
   - PHP 8.3 configuration moved to top
   - Aliases for consistent PHP 8.3 usage

## Success Indicators

After running the updated setup script, you should see:
- ✅ PHP 8.3 version when running `php --version`
- ✅ Composer working without dependency errors
- ✅ Both Drupal sites accessible (ports 80 and 8080)
- ✅ Drush commands working in site directories

## Emergency Recovery

If something goes wrong with the PHP configuration:

```bash
# Restore original Codespace PHP
sudo rm -f /home/codespace/.php/current
sudo ln -sf /opt/php/8.0.30 /home/codespace/.php/current

# Remove our modifications
sudo rm -f /etc/profile.d/00-php83-priority.sh
cp ~/.bashrc.backup ~/.bashrc  # If backup exists

# Then re-run the complete setup
./scripts/complete-setup.sh
```

This approach ensures that PHP 8.3 priority is maintained across Codespace sessions while providing immediate troubleshooting tools when needed.