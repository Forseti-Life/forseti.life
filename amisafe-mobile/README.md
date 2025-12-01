# AmISafe Mobile Application

A cross-platform mobile application for crime safety awareness, built with React Native for both iOS and Android platforms. Integrates with the Drupal-based AmISafe API for real-time crime data and user management.

## 🚦 Current Implementation Status

### ✅ Fully Implemented
- **Authentication System**: Basic Drupal session-based authentication with CSRF token management
- **Background Location Monitoring**: GPS tracking with H3 index calculation and z-score risk assessment
- **Navigation Structure**: 5-screen bottom tab navigation (Home, Map, Safety, Statistics, Profile)
- **Location Services**: GPS tracking with H3 geospatial conversion (Resolution 11-13)
- **API Integration**: DrupalCrimeService configured for all AmISafe endpoints
- **Storage Layer**: AsyncStorage for persistent user sessions and preferences
- **Permissions**: Location and notification permission handling (iOS/Android)
- **Settings Management**: User-configurable monitoring settings and thresholds

### 🔄 Partially Implemented (Using Mock Data)
- **Home Dashboard**: Safety scores and quick stats display (mock data)
- **Crime Map**: Map framework exists, needs H3 hexagon overlay integration
- **Safety Screen**: Risk assessment UI present, needs real-time data connection
- **Statistics Screen**: Analytics framework, needs data visualization
- **Notifications**: Service framework exists, not actively pushing alerts

### ❌ Not Yet Implemented
- Native Android/iOS directories (requires `npx react-native init`)
- Real-time safety alerts and push notifications (requires `react-native-push-notification`)
- H3 hexagon crime visualization on maps
- Offline data caching
- Community reporting features
- Safe route planning
- Personal safety analytics dashboard

## 🛡️ Authentication System

### Basic Drupal Integration
The app uses **session-based authentication** with standard Drupal user management (no OAuth complexity):

```javascript
// Simple login method
const result = await drupalAuthService.login(username, password);

// User registration
const regResult = await drupalAuthService.register({
  username: 'newuser',
  email: 'user@example.com', 
  password: 'securepass'
});

// Authentication state
const isLoggedIn = drupalAuthService.isAuthenticated();
const currentUser = drupalAuthService.getCurrentUser();
```

**Key Features:**
- **CSRF Token Management**: Proper security token handling for API requests
- **Demo Mode Fallback**: Development-friendly authentication for testing
- **Persistent Sessions**: User login state preserved across app restarts
- **Session Storage**: Persistent authentication across app sessions

**Authentication Flow:**
1. CSRF Token retrieved from `/session/token`
2. Session Login via form-based authentication with Drupal
3. Session Storage for persistence
4. API Access with authenticated requests to AmISafe endpoints
5. Demo Fallback for development mode testing

## 📍 Background Location Monitoring

### Core Services

**BackgroundLocationService.ts**
- GPS tracking via react-native-geolocation-service
- H3 index calculation at resolution 11 (~700m hexagons)
- Z-score checking via AmISafe API
- Notification triggering when z-score >= 2.0
- Location history tracking (last 100 locations)
- State persistence via AsyncStorage
- Auto-restore on app restart

**useBackgroundMonitoring.ts** (React Hook)
- Permission management (foreground + background location)
- Start/stop monitoring controls
- State management
- Auto-restore monitoring on app launch

**SettingsScreen.tsx**
- Enable/disable monitoring toggle
- Z-score threshold selector (1.0 - 3.0)
- Notification cooldown selector (1-30 minutes)
- Location history viewer
- Clear history function

### Required Next Steps

**1. Install Missing Packages**
```bash
cd /home/keithaumiller/stlouisintegration.com/amisafe-mobile

# Install push notification library
npm install react-native-push-notification

# iOS only
npm install @react-native-community/push-notification-ios

# Link native dependencies
npx pod-install ios  # iOS
```

**2. Initialize Native Directories**

The app requires Android and iOS native folders:
```bash
# Option 1: Create new React Native app structure
npx react-native init amisafe-mobile

# Option 2: Add to existing setup
npx @react-native-community/cli init --skip-install
```

**3. Android Native Configuration**

See `docs/ARCHITECTURE.md` for complete Android setup including:
- BootReceiver.java (auto-start on device boot)
- LocationMonitoringService.java (foreground service)
- AndroidManifest.xml permissions and service registration

**4. iOS Configuration**

See `docs/ARCHITECTURE.md` for complete iOS setup including:
- Info.plist location permission descriptions
- Background modes configuration
- AppDelegate.m modifications

## 🚀 Features

### Implemented Core Features
- 📍 **Location Tracking**: GPS-based location services with H3 hexagon conversion
- 🗺️ **Interactive Crime Map**: React Native Maps with crime data overlay framework
- 🔐 **User Authentication**: Session-based Drupal authentication
- 💾 **Local Storage**: Persistent user sessions and app preferences
- 📱 **Cross-Platform**: iOS and Android support via React Native 0.72.6
- 🎯 **Risk Assessment**: Z-score based crime risk evaluation
- 🔔 **Alert Framework**: Notification service ready for push alerts

### Planned Advanced Features
- 🎯 **Predictive Safety Scoring**: AI-powered risk assessment (API ready, UI integration pending)
- 🔔 **Real-time Alerts**: Push notifications for high-crime areas (framework exists)
- 🔄 **Offline Capability**: Cached data for areas without internet (not implemented)
- 👥 **Community Reporting**: User-submitted safety reports (not implemented)
- 📈 **Personal Dashboard**: Customized insights and recommendations (partially implemented)
- 🌙 **Night Mode**: Optimized interface for low-light conditions (not implemented)

## 📱 Platform Support

### iOS Requirements
- iOS 12.0 or later
- iPhone 6s or newer
- Location services enabled
- Push notification permissions

### Android Requirements
- Android 7.0 (API level 24) or later
- GPS and network location enabled
- Storage permissions for offline maps
- Notification permissions

## 🏗️ Architecture

### Technology Stack
- **Framework**: React Native 0.72.6 ✅
- **Language**: TypeScript (screens) + JavaScript (services) ✅
- **Navigation**: React Navigation 6 with bottom tabs ✅
- **Maps**: React Native Maps v1.26.18 ✅
- **Geospatial**: h3-js v4.1.0 for H3 hexagon calculations ✅
- **HTTP Client**: Axios v1.6.0 with CSRF token handling ✅
- **Storage**: @react-native-async-storage/async-storage v1.19.5 ✅
- **State Management**: React Context + Hooks ✅

### Project Structure
```
amisafe-mobile/
├── src/
│   ├── components/          # Reusable UI components
│   │   └── InteractiveCrimeMap.js
│   ├── screens/            # Application screens
│   │   ├── Auth/           # LoginScreen.tsx
│   │   ├── Home/           # HomeScreen.tsx
│   │   ├── Map/            # MapScreen.tsx, CrimeMapScreen.js
│   │   ├── Profile/        # ProfileScreen.tsx
│   │   ├── Safety/         # SafetyScreen.tsx
│   │   ├── Settings/       # SettingsScreen.tsx
│   │   └── Statistics/     # StatisticsScreen.tsx
│   ├── services/           # API and data services
│   │   ├── DrupalAuthService.js      # Authentication
│   │   ├── DrupalCrimeService.js     # Crime data API
│   │   ├── GPSLocationService.js     # GPS tracking
│   │   ├── H3LocationService.js      # H3 geospatial
│   │   ├── location/
│   │   │   ├── BackgroundLocationService.ts
│   │   │   ├── LocationService.ts
│   │   │   └── PlatformConfiguration.ts
│   │   ├── notifications/
│   │   │   └── NotificationService.ts
│   │   └── storage/
│   │       └── StorageService.ts
│   ├── hooks/
│   │   └── useBackgroundMonitoring.ts
│   └── utils/
│       ├── colors.ts
│       └── permissions.ts
├── docs/                   # Complete documentation
│   ├── README.md          # Developer guide
│   └── ARCHITECTURE.md    # System architecture
├── android/                # Android native (not initialized yet)
├── ios/                    # iOS native (not initialized yet)
├── App.js                  # Main app entry
├── App.tsx                # TypeScript app entry
└── package.json           # Dependencies
```

## 🔧 Development Setup

### Prerequisites
```bash
# Node.js and npm
node --version  # Should be >= 16
npm --version

# React Native CLI
npm install -g react-native-cli

# For iOS development (macOS only)
xcode-select --install
sudo gem install cocoapods

# For Android development
# Install Android Studio with SDK
# Configure ANDROID_HOME environment variable
```

### Installation
```bash
# Navigate to mobile app directory
cd /workspaces/stlouisintegration.com/amisafe-mobile

# Install dependencies
npm install

# Initialize native directories (REQUIRED - not done yet)
npx react-native init amisafe-mobile --skip-install

# iOS setup (macOS only)
cd ios && pod install && cd ..
```

### Development Commands
```bash
# Start Metro bundler
npm start

# Run on Android
npm run android

# Run on iOS (macOS only)
npm run ios

# Run tests
npm test

# Web test interface
# Open web-test.html or crime-map-demo.html in browser
```

## 🌐 API Integration

### AmISafe Backend Integration
- **Base URL**: `http://localhost/amisafe/api` (development)
- **Production URL**: `https://stlouisintegration.com/amisafe/api`

### Working Endpoints ✅
```
✅ GET /session/token          - CSRF token for security
✅ GET /api/amisafe/debug      - System diagnostics
✅ GET /api/amisafe/system-stats - Platform statistics  
✅ GET /api/amisafe/crime-types - Crime classification data
✅ GET /api/amisafe/aggregated  - H3 hexagon crime data
✅ GET /api/amisafe/incidents   - Individual crime records
✅ GET /api/amisafe/risk-level  - Location-based risk assessment
```

### H3 Resolution Strategy
| Resolution | Area Coverage | Use Case | Update Frequency |
|------------|---------------|----------|------------------|
| 5 | 251.1 km² | City statistics | Daily |
| 8 | 0.7 km² | Neighborhood context | Hourly |
| 10 | 15,047 m² | Block awareness | 15 minutes |
| 11 | ~700 m² | Background monitoring | Real-time |
| 13 | 44 m² | User position tracking | Real-time |

## 📊 Key Components

### CrimeMapComponent
Interactive map with H3 hexagon overlays showing crime density and types.

### SafetyDashboard
Real-time safety scoring for user's current location with recommendations.

### LocationTracker
Background location monitoring with privacy-conscious data handling.

### NotificationManager
Push notification system for safety alerts and area warnings.

### OfflineDataManager
Local storage management for offline functionality.

## 🔐 Privacy & Security

### Location Privacy
- Location data processed locally when possible
- Optional cloud sync with encrypted transmission
- User control over data sharing preferences
- No tracking without explicit consent

### Data Security
- HTTPS-only API communication
- Local data encryption using device keystore
- Session management with secure tokens
- Regular security updates and patches

## 🚀 Deployment

### Android Deployment
```bash
# Generate release APK
npm run build:android

# APK location: android/app/build/outputs/apk/release/
```

### iOS Deployment
```bash
# Build for release (macOS + Xcode required)
npm run build:ios

# Follow iOS App Store submission guidelines
```

### App Store Information
- **App Name**: AmISafe - Crime Safety Map
- **Category**: Navigation / Safety
- **Target Audience**: General public, safety-conscious individuals
- **Permissions Required**: Location, Notifications, Storage

## 🧪 Testing

### Available Test Files
- `test-auth.js` - Authentication testing
- `test-basic-auth.js` - Basic auth flow
- `test-api-integration.js` - API endpoint testing
- `test-crime-map.js` - Crime map functionality
- `test-h3.js` - H3 geospatial calculations

### Web Test Interfaces
- `web-test.html` - Interactive authentication testing
- `crime-map-demo.html` - Crime map visualization demo
- `demo-preview.html` - Feature preview

## 📚 Documentation

### Complete Documentation in `/docs`
- **README.md** - Comprehensive developer guide with all implementation details
- **ARCHITECTURE.md** - Complete system architecture, data flows, and native setup

### Topics Covered
- API Integration & Endpoints
- Crime Map Implementation
- Development Environment Setup
- Drupal Modules & Configuration
- Process Flows & Diagrams
- Project Summary & Roadmap
- Technical Specifications
- User Registration & Authentication
- Background Monitoring Setup
- Android/iOS Native Configuration

## 📄 License

MIT License - see [LICENSE](./LICENSE) file for details.

## 🆘 Support

- **Issues**: GitHub Issues for bug reports and feature requests
- **Documentation**: Comprehensive docs in `/docs` directory
- **Contact**: admin@stlouisintegration.com

---

**AmISafe Mobile** - Empowering communities with real-time crime awareness and safety insights.
