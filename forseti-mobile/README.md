# Forseti Mobile Application - Complete Documentation

**Version**: 1.0.2  
**Status**: 🟢 Active Development  
**Platform**: React Native 0.76.9 (iOS & Android)  
**Last Updated**: December 18, 2025

A cross-platform mobile application for hyperlocal crime safety awareness built with React Native. Integrates with the Forseti API (forseti.life) for real-time crime data visualization, z-score risk assessment, and continuous background monitoring with proactive alerts.

---

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [Application Architecture](#application-architecture)
3. [Features & Implementation Status](#features--implementation-status)
4. [Build & Deployment](#build--deployment)
5. [Background Monitoring System](#background-monitoring-system)
6. [API Integration](#api-integration)
7. [Screen-by-Screen Guide](#screen-by-screen-guide)
8. [Known Issues & Troubleshooting](#known-issues--troubleshooting)
9. [Development Workflow](#development-workflow)
10. [Branding & Assets](#branding--assets)

---

## Quick Start

### Prerequisites

- Node.js 16+
- React Native CLI
- Android Studio (for Android builds)
- Xcode (for iOS builds, Mac only)
- ImageMagick (for icon generation)

### Installation

```bash
cd forseti-mobile
npm install

# iOS only
cd ios && pod install && cd ..
```

### Development

```bash
# Start Metro bundler
npm start

# Run on Android
npm run android

# Run on iOS
npm run ios
```

### Production Build

```bash
# Android APK
cd android
./gradlew clean assembleRelease

# Output: android/app/build/outputs/apk/release/app-release.apk
```

---

## Application Architecture

### System Overview

The Forseti mobile application operates on a three-tier architecture:

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│   Mobile App    │◄──►│   Drupal API     │◄──►│  H3 Database        │
│  React Native   │    │  forseti.life    │    │  MySQL + H3 Index   │
│                 │    │                  │    │                     │
│ • GPS Tracking  │    │ • Authentication │    │ • 3.4M+ Incidents   │
│ • Notifications │    │ • Spatial Queries│    │ • 413K+ Hexagons    │
│ • Risk Display  │    │ • Risk Calc      │    │ • Multi-Resolution  │
│ • Offline Cache │    │ • User Mgmt      │    │ • Z-Score Analytics │
└─────────────────┘    └──────────────────┘    └─────────────────────┘
```

### Technology Stack

**Frontend (Mobile)**:

- React Native 0.76.9
- TypeScript 5.x
- React Navigation 6.x
- H3-js 4.1.0 (Uber's geospatial library)
- react-native-maps (Google Maps)
- AsyncStorage (local persistence)
- Hermes JavaScript engine

**Backend (API)**:

- Drupal 9/10/11 custom modules
- RESTful JSON APIs
- Session-based authentication
- CSRF token protection

**Database**:

- MySQL 8.0+ with H3 spatial indexing
- Bronze → Silver → Gold data warehouse layers
- Pre-computed aggregations for performance

### Project Structure

```
forseti-mobile/
├── android/              # Android native code
├── ios/                  # iOS native code
├── src/
│   ├── components/       # Reusable UI components
│   ├── hooks/            # Custom React hooks
│   ├── screens/          # Screen components
│   │   ├── Home/
│   │   ├── Map/
│   │   ├── Chat/
│   │   ├── Community/
│   │   ├── SafetyFactors/
│   │   └── Profile/
│   ├── services/         # API & business logic
│   │   ├── location/
│   │   ├── notifications/
│   │   └── storage/
│   └── utils/            # Helper functions & theme
├── App.tsx               # Main application entry
├── app.json              # App configuration
└── package.json          # Dependencies
```

### Data Flow Architecture

**Location Tracking Flow**:

```
GPS Update → H3 Calculation → Index Comparison → Risk Query → Notification
     ↓              ↓              ↓              ↓            ↓
  Lat/Lng    H3 Level 11    Changed Hex?    API Request   Alert User
   60s or    (~700m hex)     If yes, call   /aggregated   if z≥2.0
  50m moved                   API
```

**H3 Resolution Strategy**:

| Resolution | Area Coverage | Use Case               | Update Frequency |
| ---------- | ------------- | ---------------------- | ---------------- |
| 5          | 251.1 km²     | Citywide statistics    | Daily            |
| 8          | 0.7 km²       | Neighborhood context   | Hourly           |
| 10         | 15,047 m²     | Block-level awareness  | Every 15 min     |
| 11         | ~700 m²       | Background monitoring  | Real-time        |
| 13         | 44 m²         | User position tracking | Real-time        |

---

## Features & Implementation Status

### ✅ Fully Implemented & Tested

**Core Application**:

- ✅ Bottom tab navigation (6 screens)
- ✅ Stack navigation for auxiliary screens
- ✅ TypeScript support throughout
- ✅ Theme system with Forseti branding
- ✅ Dark mode support (system-based)

**Location Services**:

- ✅ GPS tracking via react-native-geolocation-service
- ✅ Foreground + background location permissions
- ✅ H3 geospatial indexing (h3-js integration)
- ✅ Location history tracking (last 100 locations)
- ✅ Auto-restore monitoring on app restart

**Background Monitoring**:

- ✅ Continuous H3 hexagon change detection
- ✅ API queries only when user moves to new hexagon
- ✅ Z-score threshold monitoring (configurable 1.0-3.0)
- ✅ Notification cooldown system (1-30 minutes)
- ✅ Deep linking to safety map from notifications
- ✅ State persistence via AsyncStorage

**Map Features**:

- ✅ Interactive Google Maps with H3 hexagons
- ✅ Z-score color gradient (18 levels)
- ✅ Hexagon press for detailed info
- ✅ Real-time data updates on zoom/pan
- ✅ Filter system (crime type, district, date, time)
- ✅ Statistics dashboard
- ✅ User location marker

**Data & Storage**:

- ✅ AsyncStorage wrapper (StorageService)
- ✅ User preferences persistence
- ✅ Location history management
- ✅ Session token storage
- ✅ Monitoring state tracking

**Authentication**:

- ✅ Drupal session-based auth
- ✅ CSRF token management
- ✅ Auto-login on app launch
- ✅ Secure token storage
- ✅ Demo mode for development

### ⚠️ Temporarily Disabled

**Notification System**:

- ⚠️ NotificationService code complete (401 lines)
- ⚠️ Package missing: react-native-push-notification
- ⚠️ Will be re-enabled after package install
- ⚠️ Local notifications ready
- ⚠️ Deep linking configured

### 🔄 In Progress

**Content Parity**:

- 🔄 About screen content from website
- 🔄 How It Works screen updates
- 🔄 Privacy screen content
- 🔄 Community screen integration

**UI Polish**:

- 🔄 Loading states and error handling
- 🔄 Empty states for screens
- 🔄 Onboarding tutorial

### ❌ Future Enhancements

- ❌ Offline mode with cached hexagon data
- ❌ Route planning with safe routes
- ❌ User-submitted community reports
- ❌ Social features (share alerts)
- ❌ Wearable integration (Apple Watch, Wear OS)
- ❌ Heatmap visualization mode
- ❌ Individual incident points mode
- ❌ Push notifications (APNs/FCM)

---

## Build & Deployment

### Current Build Status

**Latest Build**: v1.0.2

- **Date**: December 18, 2025
- **Size**: 24MB (up from 23MB v1.0.1)
- **Hash**: 2d55c0cb3dc5799d9f794d4075cced01
- **Engine**: Hermes JavaScript bytecode v94
- **Status**: Fixed App.js/App.tsx conflict

### Android Build Process

**Prerequisites**:

```bash
export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
export ANDROID_HOME=~/Android
```

**Clean Build**:

```bash
cd forseti-mobile/android
./gradlew clean assembleRelease
```

**Output**:

```
APK: android/app/build/outputs/apk/release/app-release.apk
Size: ~24MB
Target: Android 5.0+ (API 21)
Architecture: ARM64-v8a
```

**Build Configuration**:

- Min SDK: Android 5.0 (API 21)
- Target SDK: Android 13 (API 33)
- Package: com.stlouisintegration.forseti
- Hermes: Enabled (optimized)
- ProGuard: Disabled (for now)

### iOS Build Process

**Prerequisites**:

- macOS with Xcode
- Apple Developer account
- CocoaPods installed

**Build**:

```bash
cd forseti-mobile/ios
pod install
open AmISafeTempInit.xcworkspace

# In Xcode:
# Product → Archive
# Distribute App → Ad Hoc or App Store
```

### Icon Generation

**Android Icons** (5 densities):

```bash
./generate-icons.sh
```

Generates:

- mdpi: 48x48px
- hdpi: 72x72px
- xhdpi: 96x96px
- xxhdpi: 144x144px
- xxxhdpi: 192x192px

**iOS Icons** (9 sizes):

```bash
./generate-ios-icons.sh
```

Generates all required iOS icon sizes from 40x40 to 1024x1024.

**Source Image**: `sites/forseti/web/themes/custom/forseti/images/logos/originals/forseti_safe.png`

### Deployment Workflow

**Git Repository Structure**:

```
forseti.life/
├── forseti-mobile/              # React Native code
└── sites/forseti/web/sites/default/files/forseti/mobile/
    └── Forseti-latest.apk       # Production APK
```

**Automated Deployment** (via GitHub Actions):

1. Commit APK to `sites/forseti/.../mobile/Forseti-latest.apk`
2. Push to main branch
3. deploy.yml workflow triggers
4. Copies APK to production `/var/www/html/forseti/.../mobile/`
5. Sets permissions (www-data:www-data, 644)

**Manual Deployment**:

```bash
# Build APK
cd forseti-mobile/android
./gradlew assembleRelease

# Copy to git repo
cp app/build/outputs/apk/release/app-release.apk \
   ../../sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk

# Commit and push
git add sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk
git commit -m "Deploy Forseti Mobile v1.0.X"
git push
```

**Production URL**: https://forseti.life/sites/default/files/forseti/mobile/Forseti-latest.apk

---

## Background Monitoring System

### Overview

Provides continuous, real-time safety monitoring by tracking GPS location, calculating H3 hexagon position, querying the Forseti API for crime data, and sending notifications when entering high-risk areas.

### Architecture

**12-Step Process Flow**:

1. **App Initialization**: Check StorageService for previous monitoring state
2. **User Enables**: Toggle "Enable Protection" in Settings screen
3. **Permission Requests**: Foreground + background location permissions
4. **Start Tracking**: Geolocation.watchPosition() with 60s interval, 50m filter
5. **Location Update**: GPS coordinates received every 60s or 50m movement
6. **H3 Calculation**: Convert lat/lng to H3 index (resolution 11, ~700m hexagons)
7. **Hexagon Detection**: Compare with previous index, skip if same hexagon
8. **Cooldown Check**: Skip if < 5 minutes since last notification
9. **API Query**: GET `/api/amisafe/aggregated` with H3 index and resolution
10. **Z-Score Evaluation**: Compare incident_z_score with threshold (default 2.0)
11. **Send Notification**: If z≥2.0, trigger high-priority local notification
12. **State Persistence**: Save monitoring state, location history to AsyncStorage

### Key Components

**BackgroundLocationService.ts** (369 lines):

- Main monitoring service
- GPS tracking wrapper
- H3 calculation
- API communication
- Notification triggering
- State management

**useBackgroundMonitoring.ts**:

- React hook for UI
- Permission handling
- Start/stop controls
- State restoration

**NotificationService.ts** (401 lines):

- Local notification delivery
- Channel management (Android)
- Deep linking configuration
- Priority settings

**StorageService.ts**:

- AsyncStorage wrapper
- Monitoring state tracking
- Location history (last 100)
- User preferences

### Configuration

**Default Settings**:

```typescript
H3_RESOLUTION = 11; // ~700m hexagons
Z_SCORE_THRESHOLD = 2.0; // Alert when z≥2.0
UPDATE_INTERVAL = 60000; // 60 seconds
DISTANCE_FILTER = 50; // 50 meters
NOTIFICATION_COOLDOWN = 300000; // 5 minutes
```

**User Configurable** (Settings Screen):

- Enable/disable monitoring toggle
- Z-score threshold: 1.0 - 3.0 (sensitivity)
- Cooldown period: 1-30 minutes
- View location history (last 100)
- Clear location history

### API Integration

**Endpoint**: `GET https://forseti.life/api/amisafe/aggregated`

**Parameters**:

```
resolution=11
h3_index=8b283082d7dffff
format=json
```

**Response**:

```json
{
  "hexagons": [
    {
      "h3_index": "8b283082d7dffff",
      "incident_count": 145,
      "incident_z_score": 2.34,
      "risk_level": "HIGH",
      "last_updated": "2025-12-18T10:30:00Z"
    }
  ]
}
```

**Z-Score Interpretation**:

- `< 1.0`: Below average crime (safe)
- `1.0-2.0`: Normal range (typical)
- `2.0-3.0`: HIGH risk (2 std deviations above mean)
- `> 3.0`: CRITICAL risk (3+ std deviations)

### Notification Example

**Title**: "⚠️ High Crime Area Alert"  
**Message**: "You are entering a potentially dangerous area. 145 incidents reported here (Risk: HIGH, Z-Score: 2.3)"  
**Action**: Tap to view safety map at your location  
**URL**: `https://forseti.life/safety-map?lat=39.9526&lng=-75.1652`

---

## API Integration

### Base URL

**Production**: `https://forseti.life`  
**Authentication**: Session-based with CSRF tokens

### Endpoints

#### 1. Aggregated Hexagon Data

```http
GET /api/amisafe/aggregated?resolution=11&h3_index=8b283082d7dffff&format=json
```

**Response**:

```json
{
  "hexagons": [{
    "h3_index": "8b283082d7dffff",
    "incident_count": 145,
    "incident_z_score": 2.34,
    "risk_level": "HIGH",
    "crime_types": {...},
    "time_distribution": {...}
  }],
  "analytics": {
    "z_scores": {...}
  }
}
```

#### 2. Individual Incidents

```http
GET /api/amisafe/incidents?limit=1000&format=json
```

#### 3. Citywide Statistics

```http
GET /api/amisafe/citywide-stats?format=json
```

#### 4. Authentication

```http
GET /session/token
POST /user/login
```

### Service Classes

**DrupalCrimeService.js**:

- Complete API client for crime data
- 7+ production endpoints
- Axios-based HTTP client
- Error handling and retry logic

**DrupalAuthService.js**:

- Session-based authentication
- CSRF token management
- Auto-login support
- Demo mode fallback

**H3LocationService.js**:

- H3 spatial calculations
- Resolution mapping
- Coordinate conversions

---

## Screen-by-Screen Guide

### Home Screen

**File**: `src/screens/Home/HomeScreen.tsx`

**Features**:

- Current location display
- Real-time safety score
- Quick statistics (incidents, alerts)
- Quick action buttons:
  - View Safety Map
  - How It Works
  - Emergency 911
  - Community
  - About Forseti

**API Calls**:

- Location service for current position
- Mock safety score (will integrate real API)

### Map Screen

**File**: `src/screens/CrimeMapScreen.js`

**Features**:

- Interactive Google Maps
- H3 hexagon overlays with z-score colors
- Tap hexagon for details
- Filter panel (crime type, district, date, time)
- Statistics display
- Zoom-based resolution switching

**API Calls**:

- `/api/amisafe/aggregated` - Main hexagon data
- `/api/amisafe/citywide-stats` - Statistics

### Chat Screen

**File**: `src/screens/Chat/ChatScreen.js`

**Features**:

- AI conversation with Forseti
- Message history
- Connects to Drupal AI backend
- Save conversations (authenticated users)

**API Calls**:

- `/api/amisafe/chat` - AI conversation endpoint

### Community Screen

**File**: `src/screens/Community/CommunityScreen.tsx`

**Features**:

- Community guidelines
- Safety tips
- Links to website resources
- Get Forseti Mobile download info

### SafetyFactors Screen

**File**: `src/screens/SafetyFactors/SafetyFactorsScreen.tsx`

**Features**:

- Explanation of 7-dimension safety framework
- How safety scores are calculated
- Factor definitions (Safe, Energized, Connected, etc.)
- Links to website for detailed info

### Profile Screen

**File**: `src/screens/Profile/ProfileScreen.tsx`

**Features**:

- Login/logout
- User profile information
- Settings access
- Conversation history
- About/Privacy/Contact links

### Settings Screen

**File**: `src/screens/Settings/SettingsScreen.tsx`

**Features**:

- Background monitoring toggle
- Z-score threshold slider
- Cooldown period selector
- Location history viewer
- Clear history button
- About/How It Works/Privacy navigation

---

## Known Issues & Troubleshooting

### Current Issues

**1. App Crashes on Launch** ⚠️

- **Status**: Under investigation
- **Symptoms**: "Forseti keeps stopping" message
- **Possible Causes**:
  - LocationService.initialize() failing
  - StorageService.initialize() failing
  - Missing permissions
  - Uncaught promise rejection

**2. NotificationService Disabled** ⚠️

- **Status**: Temporarily commented out
- **Reason**: `react-native-push-notification` package not installed
- **Solution**: Install package and rebuild
- **Code**: Complete but inactive

**3. Old App.js Conflict** ✅ FIXED

- **Issue**: Both App.js and App.tsx existed
- **Impact**: Metro bundled old AmISafe code instead of new Forseti code
- **Resolution**: Renamed App.js to App.js.old
- **Version**: Fixed in v1.0.2

### Troubleshooting Guide

**Build Failures**:

```bash
# Clean everything
cd android
./gradlew clean
cd ..
rm -rf node_modules
rm -rf android/.gradle
npm install
cd android && ./gradlew assembleRelease
```

**Metro Bundler Issues**:

```bash
# Clear Metro cache
npm start -- --reset-cache

# Or
rm -rf $TMPDIR/metro-*
rm -rf $TMPDIR/react-*
```

**Permission Errors**:

```bash
# Android: Check AndroidManifest.xml has:
# - ACCESS_FINE_LOCATION
# - ACCESS_COARSE_LOCATION
# - INTERNET

# iOS: Check Info.plist has:
# - NSLocationWhenInUseUsageDescription
# - NSLocationAlwaysUsageDescription
```

**APK Installation Fails**:

```bash
# Check APK signature
keytool -printcert -jarfile app-release.apk

# Uninstall old version first
adb uninstall com.stlouisintegration.forseti
adb install app-release.apk
```

---

## Development Workflow

### Development Environment

**Requirements**:

- Node.js 16+ (18+ recommended)
- npm or yarn
- React Native CLI (`npm install -g react-native-cli`)
- Android Studio with SDK 33
- Java 17 (for Android builds)

**IDE Recommendations**:

- VS Code with React Native Tools extension
- Android Studio for Android-specific debugging
- Xcode for iOS (Mac only)

### Running in Development

**Start Metro Bundler**:

```bash
npm start
```

**Run on Android Emulator**:

```bash
npm run android
```

**Run on iOS Simulator** (Mac only):

```bash
npm run ios
```

**Run on Physical Device**:

```bash
# Android
adb devices
npm run android

# iOS
# Open Xcode, select your device, click Run
```

### Debugging

**React Native Debugger**:

- Shake device or press Cmd+D (iOS) / Cmd+M (Android)
- Select "Debug" to open Chrome DevTools

**Console Logs**:

```bash
# Android
adb logcat | grep ReactNativeJS

# iOS
react-native log-ios
```

**Network Debugging**:

- React Native Debugger has Network tab
- Or use Flipper (Facebook's mobile debugging tool)

### Testing

**Manual Testing Checklist**:

- [ ] App launches without crashes
- [ ] Location permissions requested and granted
- [ ] Map loads with hexagons
- [ ] Background monitoring toggles on/off
- [ ] Settings persist after app restart
- [ ] Login/logout works
- [ ] Chat sends and receives messages
- [ ] All navigation works

**Automated Tests** (TODO):

```bash
npm test
```

### Code Style

**Linting**:

```bash
npm run lint
```

**Format**:

```bash
npm run format
```

**TypeScript Check**:

```bash
tsc --noEmit
```

---

## Branding & Assets

### Application Names

**Current Status**:

- **Display Name**: "Forseti" ✅
- **Android Package**: com.stlouisintegration.forseti
- **iOS Bundle**: AmISafeTempInit (needs update to Forseti)

### App Icons

**Source Image**: `forseti_safe.png`

- Location: `sites/forseti/web/themes/custom/forseti/images/logos/originals/`
- Dimensions: 407 x 462 pixels
- Format: PNG with transparency

**Android Icons** (5 densities):

- ✅ mdpi, hdpi, xhdpi, xxhdpi, xxxhdpi
- ✅ Round variants for all densities
- Location: `android/app/src/main/res/mipmap-*/`

**iOS Icons** (9 sizes):

- ✅ All required sizes (40x40 to 1024x1024)
- Location: `ios/AmISafeTempInit/Images.xcassets/AppIcon.appiconset/`

**Icon Update**: December 18, 2025

- Changed from default React Native icon
- Now uses forseti_safe.png (less whitespace)
- Generated via ImageMagick scripts

### Colors

**Forseti Brand Colors**:

```typescript
primary: '#1E40AF'; // Forseti blue
secondary: '#10B981'; // Success green
accent: '#F59E0B'; // Warning amber
danger: '#EF4444'; // Error red
background: '#F9FAFB'; // Light gray
text: '#111827'; // Dark gray
```

**Previous (AmISafe)**:

- ❌ Neon green (#00FF00) - removed
- ❌ Bright orange accents - removed

### Links to Website

**All External Links Point to forseti.life** ✅:

- Safety Map: https://forseti.life/safety-map
- How It Works: https://forseti.life/how-it-works
- About: https://forseti.life/about
- Community: https://forseti.life/community
- Privacy: https://forseti.life/privacy
- Contact: https://forseti.life/contact

---

## Appendix

### Version History

**v1.0.2** (December 18, 2025):

- Fixed App.js/App.tsx conflict
- Removed old AmISafe single-file app
- Temporarily disabled NotificationService
- Updated icons to forseti_safe.png
- Simplified APK deployment (no versioning/symlinks)

**v1.0.1** (December 18, 2025):

- Updated content parity with website
- Enhanced HowItWorks screen
- Updated SafetyFactors screen
- Icon updates
- Symlink deployment strategy

**v1.0.0** (December 13, 2024):

- Initial Forseti rebranding
- Tab navigation implementation
- Background monitoring system
- H3 geospatial integration
- Authentication system

### Dependencies

**Core**:

- react: 18.2.0
- react-native: 0.76.9
- @react-navigation/native: 6.x
- @react-navigation/bottom-tabs: 6.x
- @react-navigation/stack: 6.x

**Location & Maps**:

- react-native-geolocation-service: 5.3.1
- react-native-maps: 1.7.1
- h3-js: 4.1.0

**Storage & State**:

- @react-native-async-storage/async-storage: 1.19.5
- axios: 1.6.0

**UI**:

- react-native-vector-icons: 10.x
- react-native-gesture-handler: 2.x
- react-native-safe-area-context: 4.x
- react-native-screens: 3.x

**Development**:

- typescript: 5.x
- @types/react: 18.x
- @types/react-native: 0.72.x

### Contact & Support

**Project Repository**: https://github.com/keithaumiller/forseti.life  
**Website**: https://forseti.life  
**Developer**: Keith Aumiller

---

_Last Updated: December 18, 2025_
_Document Version: 1.0_
