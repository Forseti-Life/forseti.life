# AmISafe Mobile Application - Complete Developer Guide

## Table of Contents
1. [Project Overview](#project-overview)
2. [Development Environment Setup](#development-environment-setup)
3. [API Integration](#api-integration)
4. [User Registration & Authentication](#user-registration--authentication)
5. [Crime Map Implementation](#crime-map-implementation)
6. [Drupal Modules Configuration](#drupal-modules-configuration)
7. [Technical Specifications](#technical-specifications)
8. [Development Workflow](#development-workflow)
9. [Testing & Debugging](#testing--debugging)
10. [Deployment](#deployment)

---

## Project Overview

The AmISafe mobile application is a sophisticated, cross-platform safety solution built on React Native that provides real-time crime risk assessment through ultra-precise H3 geospatial analysis. The application integrates seamlessly with the existing Drupal-based AmISafe API infrastructure, delivering location-aware safety notifications to protect users in high-crime areas.

### Key Technical Specifications
- **Platform**: Cross-platform iOS and Android using React Native
- **Authentication**: Integration with existing Drupal user system
- **Geospatial Precision**: H3 Level 13 (44m² resolution) for user tracking
- **Data Source**: Real-time access to 3.4M+ Philadelphia crime records
- **Notification System**: Background monitoring with intelligent risk alerts
- **Offline Capability**: Critical safety data cached for disconnected operation

### Current Status

**✅ Implemented:**
- Basic Drupal authentication with session management
- Background location monitoring framework
- H3 geospatial conversion services
- API integration layer
- Settings and user preferences
- Permission management

**🔄 In Progress:**
- Native platform initialization (Android/iOS folders)
- Push notification implementation
- Crime map H3 visualization
- Real-time data integration

**❌ Not Yet Started:**
- App store deployment
- Offline caching
- Community reporting features
- Advanced analytics dashboard

---

## Development Environment Setup

### Prerequisites

**Node.js & npm**
```bash
node --version  # Should be >= 16
npm --version
```

**React Native CLI**
```bash
npm install -g react-native-cli
```

**Android Development Setup**
- Install [Android Studio](https://developer.android.com/studio)
- Configure Android SDK (API level 24+)
- Set up Android Virtual Device (AVD)
- Configure ANDROID_HOME environment variable

```bash
# Add to ~/.bashrc or ~/.zshrc
export ANDROID_HOME=$HOME/Android/Sdk
export PATH=$PATH:$ANDROID_HOME/emulator
export PATH=$PATH:$ANDROID_HOME/tools
export PATH=$PATH:$ANDROID_HOME/tools/bin
export PATH=$PATH:$ANDROID_HOME/platform-tools
```

**iOS Development Setup** (macOS only)
- Install Xcode from App Store
- Install Xcode Command Line Tools: `xcode-select --install`
- Install CocoaPods: `sudo gem install cocoapods`

### Project Installation

```bash
# Navigate to mobile app directory
cd /workspaces/stlouisintegration.com/amisafe-mobile

# Install dependencies
npm install

# Initialize native directories (REQUIRED - not done yet)
npx react-native init amisafe-mobile --skip-install

# Copy existing src/ and configuration files to new structure

# iOS setup (macOS only)
cd ios && pod install && cd ..

# Start Metro bundler
npm start
```

### Running the App

```bash
# Android
npm run android

# iOS (macOS only)
npm run ios

# Start bundler only
npm start
```

### Development Commands

```bash
# Clear Metro cache
npx react-native start --reset-cache

# Clean build (if having issues)
npm run clean

# Run tests
npm test

# Type checking
npx tsc --noEmit

# Lint code
npm run lint
```

---

## API Integration

### Base API Configuration

```typescript
const API_CONFIG = {
  BASE_URL: 'https://stlouisintegration.com',
  API_PREFIX: '/api/amisafe',
  AUTH_PREFIX: '/user',
  TIMEOUT: 30000, // 30 seconds
  RETRY_ATTEMPTS: 3,
  CACHE_DURATION: 30 * 60 * 1000, // 30 minutes
};
```

### Available Endpoints

#### Authentication APIs
```
POST /user/register        - Create new user account
POST /user/login          - User authentication
POST /user/logout         - End session
GET  /user/profile        - Get user profile data
GET  /session/token       - Get CSRF token
```

#### Crime Data APIs
```
GET /api/amisafe/risk-level      - Location risk assessment
GET /api/amisafe/aggregated      - H3 hexagon crime data
GET /api/amisafe/incidents       - Individual crime incidents
GET /api/amisafe/hotspots        - High-crime area identification
GET /api/amisafe/system-stats    - Database statistics
GET /api/amisafe/crime-types     - Crime categories
GET /api/amisafe/districts       - Police districts
```

### Risk Level Assessment API

**Request:**
```typescript
GET /api/amisafe/risk-level?h3_index=8d2a1072b5b5fff&include_neighbors=true&time_window=24h

Parameters:
  - h3_index: string (H3 Level 13 index)
  - include_neighbors: boolean (default: true)
  - time_window: string (default: "24h")
```

**Response:**
```json
{
  "risk_level": "high",
  "risk_score": 78,
  "h3_index": "8d2a1072b5b5fff",
  "factors": ["recent_incidents", "time_of_day", "location_history"],
  "incident_count": 12,
  "trend": "increasing",
  "confidence": 0.87,
  "last_updated": "2025-11-07T14:30:00Z",
  "neighbors": {
    "8d2a1072b5b1fff": {"risk_level": "medium", "risk_score": 45},
    "8d2a1072b5b3fff": {"risk_level": "high", "risk_score": 82}
  }
}
```

### H3 Aggregated Data API

**Request:**
```typescript
GET /api/amisafe/aggregated?resolution=13&bounds=39.95,-75.17,39.96,-75.16&limit=1000

Parameters:
  - resolution: number (6-13)
  - bounds: string (lat1,lng1,lat2,lng2)
  - limit: number (default: 1000)
  - crime_types: string[] (optional filter)
  - min_incidents: number (optional filter)
```

**Response:**
```json
{
  "hexagons": [
    {
      "h3_index": "8d2a1072b5b5fff",
      "incident_count": 15,
      "risk_level": "high",
      "center_lat": 39.9526,
      "center_lng": -75.1652,
      "crime_types": {"theft": 8, "assault": 4, "vandalism": 3},
      "last_incident": "2025-11-06T20:15:00Z"
    }
  ],
  "meta": {
    "resolution": 13,
    "total_hexagons": 245,
    "bounds": "39.9500,-75.1700,39.9550,-75.1600"
  }
}
```

### API Service Implementation

**DrupalAuthService.js**
```javascript
class DrupalAuthService {
  async getCsrfToken() {
    const response = await fetch(`${BASE_URL}/session/token`);
    return response.text();
  }

  async login(username, password) {
    const csrfToken = await this.getCsrfToken();
    const response = await fetch(`${BASE_URL}/user/login?_format=json`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify({ name: username, pass: password }),
    });
    return response.json();
  }

  isAuthenticated() {
    return this.currentUser !== null;
  }

  getCurrentUser() {
    return this.currentUser;
  }
}
```

**DrupalCrimeService.js**
```javascript
class DrupalCrimeService {
  async getRiskLevel(h3Index) {
    const response = await fetch(
      `${BASE_URL}/api/amisafe/risk-level?h3_index=${h3Index}&include_neighbors=true`
    );
    return response.json();
  }

  async getAggregatedData(resolution, bounds) {
    const response = await fetch(
      `${BASE_URL}/api/amisafe/aggregated?resolution=${resolution}&bounds=${bounds}`
    );
    return response.json();
  }

  async getIncidents(filters) {
    const params = new URLSearchParams(filters);
    const response = await fetch(`${BASE_URL}/api/amisafe/incidents?${params}`);
    return response.json();
  }
}
```

---

## User Registration & Authentication

### Registration Requirements

**Mandatory Information:**
- Email Address (primary identifier)
- Password (strong password required)
- First Name
- Last Name
- Phone Number (optional)
- Emergency Contact (recommended)

**Security Requirements:**
- **Password Strength**: Minimum 8 characters with uppercase, lowercase, number, and special character
- **Email Verification**: Email confirmation required before account activation
- **Terms of Service**: Acceptance of terms and privacy policy required
- **Age Verification**: Must be 18+ or have parental consent

**Privacy Considerations:**
- **Location Consent**: Explicit consent for location tracking
- **Data Sharing**: Optional consent for anonymized data sharing
- **Notification Preferences**: Granular control over alert types
- **Emergency Access**: Permission for emergency services to access location data

### Registration Flow

**Step 1: Account Creation**
```typescript
interface RegistrationData {
  email: string;
  password: string;
  confirmPassword: string;
  firstName: string;
  lastName: string;
  phoneNumber?: string;
  emergencyContact: {
    name: string;
    phone: string;
    relationship: string;
  };
  agreeToTerms: boolean;
  agreeToPrivacy: boolean;
  allowLocationTracking: boolean;
}
```

**Step 2: API Call**
```typescript
POST https://stlouisintegration.com/user/register
Content-Type: application/json

{
  "mail": "user@example.com",
  "name": "user@example.com",
  "pass": "SecurePassword123!",
  "field_first_name": "John",
  "field_last_name": "Doe",
  "field_phone_number": "+1234567890",
  "field_emergency_contact": {
    "name": "Jane Doe",
    "phone": "+1234567891",
    "relationship": "Spouse"
  },
  "field_privacy_consent": true,
  "field_location_consent": true
}
```

**Step 3: Email Verification**
1. Account Created - User receives confirmation
2. Verification Email - Automated email with verification link
3. Email Confirmation - User clicks link to activate account
4. Account Activated - User can now log in

**Step 4: Mobile App Setup**
1. Login with Credentials
2. JWT Token Received
3. Profile Setup
4. Location Permissions
5. Notification Setup

### Authentication Implementation

```typescript
class AuthService {
  // Store tokens securely
  async storeTokens(tokens: AuthResponse): Promise<void> {
    await AsyncStorage.setItem('auth_token', JSON.stringify(tokens));
    await AsyncStorage.setItem('user_profile', JSON.stringify(tokens.user));
  }

  // Get valid token
  async getValidToken(): Promise<string | null> {
    const tokenData = await AsyncStorage.getItem('auth_token');
    if (!tokenData) return null;
    
    const token = JSON.parse(tokenData);
    if (this.isTokenExpired(token)) {
      await this.refreshToken();
    }
    
    return token.access_token;
  }

  // Check if authenticated
  async isAuthenticated(): Promise<boolean> {
    const token = await this.getValidToken();
    return token !== null;
  }
}
```

---

## Crime Map Implementation

### Interactive Crime Map Component

The Interactive Crime Map provides real-time H3 hexagon visualization with multi-resolution zoom levels and comprehensive crime data display.

#### H3 Resolution Mapping

Based on zoom level, the map automatically switches H3 resolution:

```javascript
Zoom Level → H3 Resolution → Coverage Area → Use Case
8-9        → Resolution 4   → ~1,770km²    → Metro area overview
10         → Resolution 5   → ~251km²      → District-level analysis
11         → Resolution 6   → ~36km²       → City area coverage
12         → Resolution 7   → ~5.2km²      → Neighborhood detail
13         → Resolution 8   → ~0.7km²      → Block group precision
14         → Resolution 9   → ~0.1km²      → Street block accuracy
15         → Resolution 10  → ~15,047m²    → Building group detail
16         → Resolution 11  → ~2,150m²     → Individual buildings
17         → Resolution 12  → ~307m²       → Room-level precision
18+        → Resolution 13  → ~44m²        → Ultra-precision tracking
```

#### Risk Assessment Algorithm

```javascript
const calculateRiskLevel = (incidentCount) => {
  if (incidentCount === 0) return 'SAFE';        // Green
  else if (incidentCount <= 5) return 'LOW';     // Light green
  else if (incidentCount <= 15) return 'MODERATE'; // Yellow
  else if (incidentCount <= 30) return 'HIGH';   // Orange
  else return 'CRITICAL';                        // Red
};
```

#### Map Component Structure

```javascript
// src/components/InteractiveCrimeMap.js
import React, { useEffect, useState } from 'react';
import MapView, { Polygon } from 'react-native-maps';
import { h3 } from 'h3-js';

const InteractiveCrimeMap = ({ initialLocation }) => {
  const [hexagons, setHexagons] = useState([]);
  const [resolution, setResolution] = useState(8);

  const loadHexagonData = async (region, res) => {
    const bounds = calculateBounds(region);
    const data = await drupalCrimeService.getAggregatedData(res, bounds);
    setHexagons(data.hexagons);
  };

  const onRegionChange = (region) => {
    const newResolution = calculateResolution(region.zoom);
    if (newResolution !== resolution) {
      setResolution(newResolution);
      loadHexagonData(region, newResolution);
    }
  };

  return (
    <MapView
      initialRegion={initialLocation}
      onRegionChangeComplete={onRegionChange}
    >
      {hexagons.map(hex => (
        <Polygon
          key={hex.h3_index}
          coordinates={h3.cellToBoundary(hex.h3_index)}
          fillColor={getRiskColor(hex.risk_level)}
          strokeColor="#00ff41"
          strokeWidth={1}
          tappable
          onPress={() => showHexagonDetails(hex)}
        />
      ))}
    </MapView>
  );
};
```

#### Crime Type Colors

```javascript
const CRIME_TYPE_COLORS = {
  'Violent Crimes': '#ff4444',      // Red
  'Property Crimes': '#ff8800',     // Orange
  'Drug Offenses': '#8844ff',       // Purple
  'Traffic Violations': '#44ff44',  // Green
  'Other Incidents': '#44ffff'      // Cyan
};
```

---

## Drupal Modules Configuration

### Required Modules (Server-Side)

The AmISafe mobile app requires the following Drupal modules on stlouisintegration.com:

#### Core API Modules (Pre-installed)
- ✅ **JSON:API** - Automatic REST endpoints for user management
- ✅ **REST** - RESTful web services foundation
- ✅ **Serialization** - JSON/XML data serialization
- ✅ **Basic Auth** - HTTP basic authentication

#### Authentication Modules (Installed)
- ✅ **Simple OAuth** (v6.0.9) - OAuth 2.0 authentication with JWT tokens
- ✅ **Consumers** (v1.21.0) - OAuth client management
- ✅ **OpenAPI** (v2.3.0) - API documentation generation
- ✅ **REST UI** (v1.22.0) - User interface for REST configuration

### Installation Commands

```bash
# Navigate to Drupal site
cd /workspaces/stlouisintegration.com/sites/stlouisintegration

# Install contributed modules
composer require drupal/simple_oauth drupal/openapi drupal/restui

# Enable all required modules
vendor/bin/drush en jsonapi rest serialization basic_auth simple_oauth consumers openapi restui -y

# Generate OAuth keys
mkdir -p keys
cd keys
openssl genrsa -out private.key 2048
openssl rsa -in private.key -outform PEM -pubout -out public.key

# Configure OAuth settings
vendor/bin/drush config:set simple_oauth.settings public_key '../keys/public.key'
vendor/bin/drush config:set simple_oauth.settings private_key '../keys/private.key'
vendor/bin/drush config:set simple_oauth.settings access_token_expiration 3600
vendor/bin/drush config:set simple_oauth.settings refresh_token_expiration 2419200

# Enable user registration
vendor/bin/drush config:set user.settings register 'visitors'
vendor/bin/drush config:set user.settings verify_mail 1

# Clear cache
vendor/bin/drush cr
```

### OAuth Consumer Setup (Optional)

If using OAuth instead of basic session authentication:

1. **Access Admin**: Go to `https://stlouisintegration.com/user/login`
2. **Navigate**: Configuration → Web Services → Consumers
3. **Add Consumer**: Click "Add Consumer" button
4. **Configure**:
   - Label: AmISafe Mobile App
   - Description: OAuth consumer for AmISafe mobile application
   - Client ID: amisafe_mobile
   - Grant Types: Password Grant, Refresh Token
   - Scopes: basic_auth
   - Confidential: NO (mobile apps are public clients)
5. **Save**: Click "Save" to complete setup

---

## Technical Specifications

### H3 Geospatial Strategy

| Resolution | Area Coverage | Precision | Use Case | Update Frequency |
|------------|---------------|-----------|----------|------------------|
| 5 | 251.1 km² | City-level | Citywide statistics | Daily |
| 8 | 0.7 km² | District | Neighborhood context | Hourly |
| 10 | 15,047 m² | Block | Block-level awareness | Every 15 min |
| 11 | ~700 m² | Street | Background monitoring | Real-time |
| 13 | 44 m² | Building | User tracking | Real-time |

### Location Processing Pipeline

```typescript
interface LocationProcessor {
  // Convert GPS coordinates to H3 index
  convertToH3(lat: number, lng: number, resolution: number): string;
  
  // Get surrounding hexagons for context
  getNeighbors(h3Index: string, ringSize: number): string[];
  
  // Calculate distance between hexagons
  h3Distance(h3Index1: string, h3Index2: string): number;
  
  // Check if user has moved to new hexagon
  hasLocationChanged(currentH3: string, previousH3: string): boolean;
}
```

### Risk Level Classifications

```typescript
enum RiskLevel {
  LOW = 'low',        // Green - Safe area
  MEDIUM = 'medium',  // Yellow - Exercise caution
  HIGH = 'high',      // Orange - Stay alert
  EXTREME = 'extreme' // Red - Consider leaving area
}

interface RiskAssessment {
  level: RiskLevel;
  score: number;        // 0-100 risk score
  factors: string[];    // Contributing risk factors
  timeWindow: string;   // Time period for assessment
  confidence: number;   // Confidence level (0-1)
  incidents: number;    // Recent incident count
  trend: 'increasing' | 'stable' | 'decreasing';
}
```

---

## Development Workflow

### Project File Structure

```
amisafe-mobile/
├── src/
│   ├── components/
│   │   └── InteractiveCrimeMap.js
│   ├── screens/
│   │   ├── Auth/LoginScreen.tsx
│   │   ├── Home/HomeScreen.tsx
│   │   ├── Map/MapScreen.tsx, CrimeMapScreen.js
│   │   ├── Profile/ProfileScreen.tsx
│   │   ├── Safety/SafetyScreen.tsx
│   │   ├── Settings/SettingsScreen.tsx
│   │   └── Statistics/StatisticsScreen.tsx
│   ├── services/
│   │   ├── DrupalAuthService.js
│   │   ├── DrupalCrimeService.js
│   │   ├── GPSLocationService.js
│   │   ├── H3LocationService.js
│   │   ├── location/
│   │   │   ├── BackgroundLocationService.ts
│   │   │   ├── LocationService.ts
│   │   │   └── PlatformConfiguration.ts
│   │   ├── notifications/NotificationService.ts
│   │   └── storage/StorageService.ts
│   ├── hooks/
│   │   └── useBackgroundMonitoring.ts
│   └── utils/
│       ├── colors.ts
│       └── permissions.ts
├── docs/
│   ├── README.md          # This file
│   └── ARCHITECTURE.md    # System architecture
├── android/               # Android native (not initialized)
├── ios/                   # iOS native (not initialized)
├── App.js
├── package.json
└── tsconfig.json
```

### State Management

Using React Context for global state:

```typescript
// src/contexts/AppContext.tsx
export const AppContext = createContext();

export const AppProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [location, setLocation] = useState(null);
  const [monitoring, setMonitoring] = useState(false);

  return (
    <AppContext.Provider value={{ user, setUser, location, setLocation, monitoring, setMonitoring }}>
      {children}
    </AppContext.Provider>
  );
};
```

---

## Testing & Debugging

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

### Running Tests

```bash
# Run all tests
npm test

# Run specific test file
node test-auth.js

# Run with coverage
npm test -- --coverage
```

### Debugging

**React Native Debugger:**
1. Install React Native Debugger
2. Start Metro bundler: `npm start`
3. Open debugger
4. Enable debugging in app (Cmd+D on iOS, Cmd+M on Android)

**Chrome DevTools:**
1. Start Metro bundler
2. Press Cmd+D (iOS) or Cmd+M (Android)
3. Select "Debug" option
4. Open Chrome DevTools

**Logging:**
```javascript
import { LogBox } from 'react-native';

// Ignore specific warnings
LogBox.ignoreLogs(['Warning: ...']);

// Console logging
console.log('Debug info:', data);
console.warn('Warning:', warning);
console.error('Error:', error);
```

---

## Deployment

### Android Deployment

**1. Generate Signing Key**
```bash
keytool -genkeypair -v -storetype PKCS12 -keystore amisafe-release-key.keystore -alias amisafe-key-alias -keyalg RSA -keysize 2048 -validity 10000
```

**2. Configure Gradle**

Edit `android/app/build.gradle`:
```gradle
android {
    signingConfigs {
        release {
            storeFile file('amisafe-release-key.keystore')
            storePassword 'YOUR_PASSWORD'
            keyAlias 'amisafe-key-alias'
            keyPassword 'YOUR_PASSWORD'
        }
    }
    buildTypes {
        release {
            signingConfig signingConfigs.release
            minifyEnabled true
            proguardFiles getDefaultProguardFile('proguard-android.txt'), 'proguard-rules.pro'
        }
    }
}
```

**3. Build Release APK**
```bash
cd android
./gradlew assembleRelease

# APK location: android/app/build/outputs/apk/release/app-release.apk
```

### iOS Deployment (macOS only)

**1. Open Xcode**
```bash
open ios/amisafe-mobile.xcworkspace
```

**2. Configure Signing**
- Select target in Xcode
- Signing & Capabilities → Team → Select development team
- Choose appropriate provisioning profile

**3. Archive for Release**
- Product → Scheme → Edit Scheme → Run → Release
- Product → Archive
- Organizer → Distribute App
- Follow App Store submission guidelines

### App Store Information

- **App Name**: AmISafe - Crime Safety Map
- **Category**: Navigation / Safety
- **Target Audience**: General public, safety-conscious individuals
- **Permissions Required**: Location, Notifications, Storage

---

## Support & Contributing

- **Issues**: GitHub Issues for bug reports and feature requests
- **Documentation**: Comprehensive docs in `/docs` directory
- **Contact**: admin@stlouisintegration.com

---

**AmISafe Mobile** - Empowering communities with real-time crime awareness and safety insights.
