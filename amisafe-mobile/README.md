# AmISafe Mobile Application

A cross-platform mobile application for crime safety awareness, built with React Native for both iOS and Android platforms. Integrates with the Drupal-based AmISafe API for real-time crime data and user management.

## 🏗️ **Backend Dependencies**

### **Required Drupal Modules (Server-Side)**
The AmISafe mobile app requires the following Drupal modules on the stlouisintegration.com server:

#### **Core API Modules (Pre-installed)**
- ✅ **JSON:API** - Automatic REST endpoints for user management
- ✅ **REST** - RESTful web services foundation
- ✅ **Serialization** - JSON/XML data serialization
- ✅ **Basic Auth** - HTTP basic authentication

#### **Authentication Modules (Installed)**
- ✅ **Simple OAuth** (v6.0.9) - OAuth 2.0 authentication with JWT tokens
- ✅ **Consumers** (v1.21.0) - OAuth client management
- ✅ **OpenAPI** (v2.3.0) - API documentation generation
- ✅ **REST UI** (v1.22.0) - User interface for REST configuration

#### **Module Installation Commands**
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

### **OAuth Consumer Setup (Required)**
After installing modules, create an OAuth consumer via Drupal admin interface:

1. **Access Admin**: Go to `https://stlouisintegration.com/user/login`
2. **Navigate**: Configuration → Web Services → Consumers (`/admin/config/services/consumer`)
3. **Add Consumer**: Click "Add Consumer" button
4. **Configure**:
   ```
   Label: AmISafe Mobile App
   Description: OAuth consumer for AmISafe mobile application
   Client ID: amisafe_mobile
   Grant Types: ☑️ Password Grant ☑️ Refresh Token  
   Scopes: basic_auth
   Confidential: ☐ NO (unchecked - mobile apps are public clients)
   ```
5. **Save**: Click "Save" to complete setup

### **Available API Endpoints**
Once configured, the following endpoints are available for the mobile app:

```typescript
// User Registration
POST https://stlouisintegration.com/user/register
{
  "name": "user@example.com",
  "mail": "user@example.com",
  "pass": "SecurePassword123!",
  "field_first_name": "John",
  "field_last_name": "Doe"
}

// OAuth Authentication
POST https://stlouisintegration.com/oauth/token
{
  "grant_type": "password",
  "client_id": "amisafe_mobile",
  "username": "user@example.com",
  "password": "SecurePassword123!"
}

// User Profile Management
GET https://stlouisintegration.com/jsonapi/user/user/{uuid}
PATCH https://stlouisintegration.com/jsonapi/user/user/{uuid}
Authorization: Bearer {access_token}

// AmISafe Crime Data (Custom API)
GET https://stlouisintegration.com/api/amisafe/risk-level?h3={index}
GET https://stlouisintegration.com/api/amisafe/aggregated?resolution=13
Authorization: Bearer {access_token}
```

## 🚀 **Features**

### **Core Functionality**
- 📍 **Real-time Location Tracking**: GPS-based crime data for user's current location
- 🗺️ **Interactive Crime Map**: H3-based hexagon visualization with zoom levels
- 🔔 **Safety Alerts**: Push notifications for high-crime areas
- 📊 **Crime Statistics**: Detailed analytics and trends
- 🕒 **Temporal Analysis**: Time-based crime patterns and recommendations
- 🚨 **Emergency Features**: Quick access to emergency services

### **Advanced Features**
- 🎯 **Predictive Safety Scoring**: AI-powered risk assessment
- 🔄 **Offline Capability**: Cached data for areas without internet
- 👥 **Community Reporting**: User-submitted safety reports
- 📈 **Personal Safety Dashboard**: Customized insights and recommendations
- 🌙 **Night Mode**: Optimized interface for low-light conditions
- 🔐 **Privacy Protection**: Local data processing with optional cloud sync

## 📱 **Platform Support**

### **iOS Requirements**
- iOS 12.0 or later
- iPhone 6s or newer
- Location services enabled
- Push notification permissions

### **Android Requirements**
- Android 7.0 (API level 24) or later
- GPS and network location enabled
- Storage permissions for offline maps
- Notification permissions

## 🏗️ **Architecture**

### **Technology Stack**
- **Framework**: React Native 0.72.6
- **Language**: TypeScript
- **Navigation**: React Navigation 6
- **Maps**: React Native Maps with custom H3 overlays
- **State Management**: React Context + Hooks
- **HTTP Client**: Axios with request/response interceptors
- **Storage**: AsyncStorage for offline data
- **Charts**: React Native Chart Kit for statistics

### **Project Structure**
```
amisafe-mobile/
├── src/
│   ├── components/          # Reusable UI components
│   │   ├── Map/            # Crime map components
│   │   ├── Safety/         # Safety-related components
│   │   ├── UI/             # General UI components
│   │   └── Charts/         # Data visualization components
│   ├── screens/            # Application screens
│   │   ├── Home/           # Main dashboard
│   │   ├── Map/            # Full-screen crime map
│   │   ├── Profile/        # User settings and preferences
│   │   ├── Safety/         # Safety features and alerts
│   │   └── Statistics/     # Crime analytics and reports
│   ├── services/           # API and data services
│   │   ├── api/            # AmISafe API integration
│   │   ├── location/       # GPS and geolocation services
│   │   ├── storage/        # Data persistence
│   │   └── notifications/  # Push notification handling
│   ├── utils/              # Helper functions and utilities
│   │   ├── h3/             # H3 geospatial calculations
│   │   ├── safety/         # Safety scoring algorithms
│   │   └── formatters/     # Data formatting utilities
│   └── assets/             # Images, fonts, and static resources
├── android/                # Android-specific configuration
├── ios/                    # iOS-specific configuration
└── docs/                   # Documentation and guides
```

## 🔧 **Development Setup**

### **Prerequisites**
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

### **Installation**
```bash
# Clone and navigate to mobile app directory
cd /workspaces/stlouisintegration.com/amisafe-mobile

# Install dependencies
npm install

# iOS setup (macOS only)
cd ios && pod install && cd ..

# Android setup
# Ensure Android SDK and emulator are configured
```

### **Development Commands**
```bash
# Start Metro bundler
npm start

# Run on Android
npm run android

# Run on iOS (macOS only)
npm run ios

# Run tests
npm test

# Lint code
npm run lint

# Clean project (if having issues)
npm run clean
```

## 🌐 **API Integration**

### **AmISafe Backend Integration**
- **Base URL**: `http://localhost/amisafe/api` (development)
- **Production URL**: `https://stlouisintegration.com/amisafe/api`
- **Endpoints Used**:
  - `/hexagons` - H3 crime data by resolution
  - `/incidents` - Individual incident data
  - `/statistics` - Aggregated crime statistics
  - `/safety-score` - Location-based safety scoring

### **Data Synchronization**
- **Real-time Updates**: WebSocket connection for live data
- **Offline Support**: Cached H3 data for common areas
- **Background Sync**: Periodic data updates when app is backgrounded
- **Delta Updates**: Only sync changed data to minimize bandwidth

## 📊 **Key Components**

### **CrimeMapComponent**
Interactive map with H3 hexagon overlays showing crime density and types.

### **SafetyDashboard**
Real-time safety scoring for user's current location with recommendations.

### **LocationTracker**
Background location monitoring with privacy-conscious data handling.

### **NotificationManager**
Push notification system for safety alerts and area warnings.

### **OfflineDataManager**
Local storage management for offline functionality.

## 🔐 **Privacy & Security**

### **Location Privacy**
- Location data processed locally when possible
- Optional cloud sync with encrypted transmission
- User control over data sharing preferences
- No tracking without explicit consent

### **Data Security**
- HTTPS-only API communication
- Local data encryption using device keystore
- Session management with secure tokens
- Regular security updates and patches

## 🚀 **Deployment**

### **Android Deployment**
```bash
# Generate release APK
npm run build:android

# APK location: android/app/build/outputs/apk/release/
```

### **iOS Deployment**
```bash
# Build for release (macOS + Xcode required)
npm run build:ios

# Follow iOS App Store submission guidelines
```

### **App Store Information**
- **App Name**: AmISafe - Crime Safety Map
- **Category**: Navigation / Safety
- **Target Audience**: General public, safety-conscious individuals
- **Permissions Required**: Location, Notifications, Storage

## 📈 **Performance Optimization**

### **Map Performance**
- H3 hexagon clustering for zoom levels
- Lazy loading of crime data
- Viewport-based data fetching
- GPU-accelerated map rendering

### **Battery Optimization**
- Intelligent location tracking frequency
- Background processing limitations
- Sleep mode for inactive periods
- Efficient data caching strategies

## 🧪 **Testing Strategy**

### **Unit Testing**
- Jest for JavaScript/TypeScript logic
- Component testing with React Native Testing Library
- API service mocking and testing

### **Integration Testing**
- E2E testing with Detox
- Map interaction testing
- Location service testing
- Notification flow testing

### **Device Testing**
- iOS: iPhone 8, iPhone 12, iPhone 14
- Android: Various devices via Firebase Test Lab
- Performance testing on older devices

## 📚 **Documentation**

### **User Guides**
- [Installation Guide](./docs/INSTALLATION.md)
- [User Manual](./docs/USER_GUIDE.md)
- [Privacy Policy](./docs/PRIVACY.md)

### **Developer Documentation**
- [API Integration](./docs/API_INTEGRATION.md)
- [Contributing Guidelines](./docs/CONTRIBUTING.md)
- [Deployment Guide](./docs/DEPLOYMENT.md)

## 🤝 **Contributing**

We welcome contributions! Please see our [Contributing Guidelines](./docs/CONTRIBUTING.md) for details on:
- Code style and standards
- Pull request process
- Testing requirements
- Documentation updates

## 📄 **License**

MIT License - see [LICENSE](./LICENSE) file for details.

## 🆘 **Support**

- **Issues**: GitHub Issues for bug reports and feature requests
- **Documentation**: Comprehensive docs in `/docs` directory
- **Contact**: admin@stlouisintegration.com

---

**AmISafe Mobile** - Empowering communities with real-time crime awareness and safety insights.