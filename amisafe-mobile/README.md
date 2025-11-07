# AmISafe Mobile Application

A cross-platform mobile application for crime safety awareness, built with React Native for both iOS and Android platforms.

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