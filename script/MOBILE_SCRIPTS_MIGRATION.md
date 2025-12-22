# Forseti Mobile Development Scripts Migration Guide

## Overview

The Forseti Mobile development scripts have been consolidated into a single, comprehensive setup script that handles all aspects of React Native development environment configuration.

## What Changed?

### Before (3 separate scripts)
- `setup-mobile.sh` - Basic React Native setup (pointed to old directory)
- `setup-mobile-web.sh` - React Native Web configuration
- `setup-android-build.sh` - Android SDK installation

### After (1 consolidated script)
- `setup-forseti-mobile-dev.sh` - **All-in-one development environment setup**

## New Script Features

### Comprehensive Setup
✅ React Native core dependencies (1600+ packages)
✅ Code quality tools (ESLint + Prettier with React Native rules)
✅ Testing framework (Jest + React Native Testing Library)
✅ TypeScript support with strict configuration
✅ Environment variable management (react-native-dotenv)
✅ VS Code debugging configuration verification
✅ Android SDK installation (optional)
✅ React Native Web setup (optional)
✅ Configuration file verification

### Command Line Options
```bash
./setup-forseti-mobile-dev.sh                    # Full setup (recommended)
./setup-forseti-mobile-dev.sh --skip-android     # Skip Android SDK
./setup-forseti-mobile-dev.sh --skip-web         # Skip web preview
./setup-forseti-mobile-dev.sh --quick            # Quick mode (essential only)
./setup-forseti-mobile-dev.sh --help             # Show help
```

### What It Installs

#### Core Dependencies (~1600 packages)
- react-native
- react-navigation (bottom-tabs, stack, native)
- react-native-web
- h3-js (geospatial)
- axios (API client)
- @react-native-async-storage/async-storage

#### Development Tools (~400 packages)
- eslint@8.57.0 (with React Native plugin)
- prettier (code formatting)
- @typescript-eslint/* (TypeScript linting)
- jest (testing framework)
- @testing-library/react-native (component testing)

#### Build Tools (~200 packages)
- webpack 5 + webpack-dev-server
- babel-loader
- html-webpack-plugin

#### Total: ~2200 packages, ~500MB

### What It Verifies

Configuration files checked:
- `.eslintrc.js` - ESLint configuration
- `.prettierrc` - Prettier rules
- `jest.config.js` - Jest configuration
- `babel.config.js` - Babel + react-native-dotenv
- `tsconfig.json` - TypeScript configuration
- `.vscode/launch.json` - Debugging configs
- `.vscode/settings.json` - Format on save, auto-fix
- `.vscode/tasks.json` - Build tasks
- `.env` - Environment variables

### Android SDK Setup

If you choose to install the Android SDK:
- Downloads Android command-line tools (~150MB)
- Installs platform-tools, Android API 33, build-tools 33.0.0 (~500MB)
- Creates `android/local.properties` with SDK path
- Generates `android-env.sh` for environment variables
- Total: ~650MB

To use Android SDK after setup:
```bash
source android-env.sh                           # Load environment
cd android && ./gradlew assembleDebug           # Build debug APK
cd android && ./gradlew assembleRelease         # Build release APK
```

### Web Preview Setup

If you choose web preview:
- Verifies webpack, webpack-dev-server, babel-loader
- Checks for `webpack.config.js` and `index.web.js`
- Enables browser-based testing at http://localhost:3000

To start web server:
```bash
npm run web
```

## Migration Instructions

### For Existing Developers

If you previously ran the old scripts:

1. **Review your current setup:**
   ```bash
   cd /home/keithaumiller/forseti.life/forseti-mobile
   ls -la .vscode/          # Check VS Code configs
   ls -la .env*             # Check environment files
   npm run lint --version   # Check if ESLint installed
   ```

2. **Run the new consolidated script:**
   ```bash
   cd /home/keithaumiller/forseti.life/script
   ./setup-forseti-mobile-dev.sh --quick
   ```

3. **Verify everything works:**
   ```bash
   cd /home/keithaumiller/forseti.life/forseti-mobile
   npm run lint           # Check linting works
   npm test               # Run tests
   npm run web            # Start web server
   ```

### For Fresh Setup

If setting up on a new machine:

1. **Clone the repository:**
   ```bash
   git clone <repo-url> forseti.life
   cd forseti.life
   ```

2. **Run the setup script:**
   ```bash
   cd script
   ./setup-forseti-mobile-dev.sh
   ```
   
   This will:
   - Check system requirements
   - Install all dependencies
   - Set up Android SDK (if you choose)
   - Configure web preview
   - Verify all configuration files
   - Display next steps

3. **Copy environment template:**
   ```bash
   cd ../forseti-mobile
   cp .env.development .env
   # Edit .env with your API keys
   ```

4. **Start developing:**
   ```bash
   npm run web            # Browser preview
   # or
   npm run android        # Android device/emulator
   ```

## Comparison Matrix

| Feature | Old Scripts | New Script |
|---------|------------|------------|
| React Native core | ✅ setup-mobile.sh | ✅ Included |
| Android SDK | ✅ setup-android-build.sh | ✅ Optional |
| Web preview | ✅ setup-mobile-web.sh | ✅ Optional |
| ESLint + Prettier | ❌ Manual | ✅ Automatic |
| Jest testing | ❌ Manual | ✅ Automatic |
| TypeScript | ❌ Manual | ✅ Automatic |
| Environment vars | ❌ Manual | ✅ Automatic |
| VS Code debugging | ❌ Manual | ✅ Verified |
| Config verification | ❌ None | ✅ Comprehensive |
| Command options | ❌ None | ✅ Multiple flags |
| Progress indicators | ⚠️ Basic | ✅ Detailed |
| Error handling | ⚠️ Basic | ✅ Comprehensive |
| Documentation | ⚠️ Scattered | ✅ Integrated |

## What Happens to Old Scripts?

The old scripts are **deprecated but not deleted**:
- `setup-mobile.sh` - Points to wrong directory, needs update
- `setup-mobile-web.sh` - Functionality moved to consolidated script
- `setup-android-build.sh` - Functionality moved to consolidated script

**Recommendation:** Archive old scripts or update them to call the new script.

## Troubleshooting

### "npm install" fails
```bash
# Try with legacy peer deps flag
npm install --legacy-peer-deps
```

### ESLint errors after setup
```bash
# Auto-fix most issues
npm run lint:fix
npm run format
```

### Android SDK not found
```bash
# Re-run setup with Android option
./setup-forseti-mobile-dev.sh --skip-web
```

### Web server won't start
```bash
# Verify webpack config exists
ls -la webpack.config.js index.web.js

# Try reinstalling web deps
npm install --save-dev --legacy-peer-deps webpack webpack-dev-server
```

### VS Code debugging not working
```bash
# Verify debug config exists
ls -la .vscode/launch.json

# Check Node.js path in launch.json
cat .vscode/launch.json | grep node
```

## Benefits of Consolidated Script

### For Developers
✅ Single command to set up everything
✅ Consistent environment across team
✅ All best practices applied automatically
✅ Clear progress indicators
✅ Comprehensive error checking
✅ Flexible options for different needs

### For Project Maintainers
✅ One script to maintain instead of three
✅ Standardized setup process
✅ Better documentation integration
✅ Easier to add new features
✅ Reduced support burden

### For CI/CD
✅ Single script for pipeline setup
✅ Flags for headless operation
✅ Exit codes for error detection
✅ Faster setup with --quick flag

## Next Steps

After running the setup script:

1. **Review configuration files:**
   - Read `CRITICAL_FIXES_SUMMARY.md` for details
   - Check `QUICK_REFERENCE.md` for commands
   - Review `ENV_VARIABLES.md` for environment setup

2. **Set up your environment:**
   ```bash
   cp .env.development .env
   # Edit .env with your settings
   ```

3. **Verify everything works:**
   ```bash
   npm run lint           # Should show issues
   npm run lint:fix       # Auto-fix issues
   npm test               # Run tests
   ```

4. **Start developing:**
   ```bash
   # Web preview
   npm run web
   
   # Android device
   npm run android
   
   # Debugging
   code .                 # Open in VS Code
   # Press F5 to start debugging
   ```

## Questions?

See the comprehensive documentation:
- `forseti-mobile/CRITICAL_FIXES_SUMMARY.md` - Complete setup details
- `forseti-mobile/QUICK_REFERENCE.md` - Quick command reference
- `forseti-mobile/ENV_VARIABLES.md` - Environment configuration
- `forseti-mobile/BEST_PRACTICES_REVIEW.md` - Code quality guidelines

Or review the script source:
- `script/setup-forseti-mobile-dev.sh` - Well-commented, easy to understand
