# AmISafe Mobile - Development Guide

## 🚀 **Quick Start**

### **Environment Setup**

1. **Install Node.js** (v16 or higher)
   ```bash
   node --version  # Should be >= 16
   npm --version
   ```

2. **Install React Native CLI**
   ```bash
   npm install -g react-native-cli
   ```

3. **Android Development Setup**
   - Install [Android Studio](https://developer.android.com/studio)
   - Configure Android SDK (API level 24+)
   - Set up Android Virtual Device (AVD)
   - Configure ANDROID_HOME environment variable

4. **iOS Development Setup** (macOS only)
   - Install Xcode from App Store
   - Install Xcode Command Line Tools: `xcode-select --install`
   - Install CocoaPods: `sudo gem install cocoapods`

### **Project Installation**

```bash
# Navigate to mobile app directory
cd /workspaces/stlouisintegration.com/amisafe-mobile

# Install dependencies
npm install

# iOS setup (macOS only)
cd ios && pod install && cd ..

# Start Metro bundler
npm start
```

### **Running the App**

```bash
# Android
npm run android

# iOS (macOS only)
npm run ios

# Start bundler only
npm start
```

## 📱 **Platform-Specific Setup**

### **Android Configuration**

1. **Ensure Android SDK is installed**
   ```bash
   echo $ANDROID_HOME
   # Should point to Android SDK location
   ```

2. **Create Virtual Device**
   - Open Android Studio
   - Tools → AVD Manager
   - Create Virtual Device (Pixel 4, API 30+)

3. **Enable Developer Options** on physical device
   - Settings → About Phone → Tap Build Number 7 times
   - Settings → Developer Options → Enable USB Debugging

### **iOS Configuration** (macOS only)

1. **Install iOS dependencies**
   ```bash
   cd ios
   pod install --repo-update
   cd ..
   ```

2. **Open in Xcode**
   ```bash
   open ios/amisafe-mobile.xcworkspace
   ```

3. **Configure signing**
   - Select target in Xcode
   - Signing & Capabilities → Team → Select development team

## 🔧 **Development Workflow**

### **File Structure**
```
amisafe-mobile/
├── src/
│   ├── components/         # Reusable UI components
│   ├── screens/           # Screen components
│   ├── services/          # Business logic services
│   ├── utils/             # Helper functions
│   └── assets/            # Images, fonts, etc.
├── android/               # Android native code
├── ios/                   # iOS native code
└── docs/                  # Documentation
```

### **Key Services**

1. **LocationService** - GPS and geolocation handling
2. **StorageService** - Local data persistence with AsyncStorage
3. **NotificationService** - Push and local notifications
4. **ApiService** - Backend API integration (to be created)

### **Development Commands**

```bash
# Start development server
npm start

# Clear Metro cache
npx react-native start --reset-cache

# Clean build (if having issues)
npm run clean

# Run tests
npm test

# Lint code
npm run lint

# Type checking
npx tsc --noEmit
```

## 🔌 **API Integration**

### **Backend Integration**
The mobile app integrates with the existing AmISafe backend:

- **Development API**: `http://localhost/amisafe/api`
- **Production API**: `https://stlouisintegration.com/amisafe/api`

### **Key Endpoints**
- `/hexagons` - H3 crime data
- `/incidents` - Individual incident data  
- `/statistics` - Aggregated statistics
- `/safety-score` - Location-based safety scoring

### **Creating API Service**
```typescript
// src/services/api/ApiService.ts
export class ApiService {
  private baseUrl = 'http://localhost/amisafe/api';
  
  async getHexagons(resolution: number, bounds: any) {
    // Implementation
  }
  
  async getSafetyScore(lat: number, lng: number) {
    // Implementation  
  }
}
```

## 🗺️ **Map Implementation**

### **React Native Maps Setup**
```bash
npm install react-native-maps
```

### **H3 Integration**
For H3 hexagon visualization:
```bash
npm install h3-js
```

### **Map Component Structure**
```typescript
// src/components/Map/CrimeMapComponent.tsx
export const CrimeMapComponent = () => {
  // Map view with H3 overlays
  // Touch interactions
  // Crime data layers
};
```

## 📊 **State Management**

### **Using React Context**
```typescript
// src/contexts/AppContext.tsx
export const AppContext = createContext();
export const AppProvider = ({ children }) => {
  // Global state management
};
```

### **Location State**
- Current user location
- Location permissions
- Background location tracking

### **Crime Data State**
- Cached H3 data
- Current map bounds
- Active filters

## 🔔 **Push Notifications**

### **Setup Firebase (Optional)**
For production push notifications:

1. Create Firebase project
2. Add Android/iOS apps
3. Download config files
4. Configure push notification service

### **Local Notifications**
For safety alerts and location-based warnings:
```typescript
NotificationService.sendSafetyAlert({
  title: 'High Crime Area',
  message: 'You are entering a high-crime zone',
  priority: 'high'
});
```

## 🧪 **Testing**

### **Unit Testing**
```bash
# Run Jest tests
npm test

# Watch mode
npm test -- --watch

# Coverage report
npm test -- --coverage
```

### **E2E Testing with Detox**
```bash
# Install Detox CLI
npm install -g detox-cli

# Build and test iOS
detox build --configuration ios
detox test --configuration ios

# Build and test Android  
detox build --configuration android
detox test --configuration android
```

### **Manual Testing Checklist**
- [ ] Location permission flow
- [ ] Map loading and interaction
- [ ] Crime data visualization
- [ ] Push notification handling
- [ ] Offline functionality
- [ ] Battery usage optimization

## 📦 **Building for Production**

### **Android Release Build**
```bash
# Generate release APK
cd android
./gradlew assembleRelease

# APK location: android/app/build/outputs/apk/release/
```

### **iOS Release Build**
```bash
# Build for release
npx react-native run-ios --configuration Release

# Or build in Xcode:
# Product → Archive → Distribute App
```

### **Code Signing & Distribution**
- **Android**: Configure signing key in `android/app/build.gradle`
- **iOS**: Configure provisioning profiles in Xcode

## 🔍 **Debugging**

### **React Native Debugger**
```bash
# Install React Native Debugger
# Enable Debug JS Remotely in dev menu
```

### **Flipper Integration**
Flipper is included for advanced debugging:
- Network inspector
- Layout inspector  
- Crash reporter
- Performance monitor

### **Common Issues**

1. **Metro bundler issues**
   ```bash
   npx react-native start --reset-cache
   ```

2. **Android build issues**
   ```bash
   cd android && ./gradlew clean && cd ..
   ```

3. **iOS build issues**
   ```bash
   cd ios && pod install --repo-update && cd ..
   ```

4. **Permission issues**
   - Check AndroidManifest.xml for Android permissions
   - Check Info.plist for iOS permissions

## 🚀 **Deployment**

### **App Store (iOS)**
1. Build release version in Xcode
2. Archive and validate
3. Upload to App Store Connect
4. Submit for review

### **Google Play Store (Android)**
1. Generate signed APK
2. Upload to Google Play Console
3. Configure store listing
4. Submit for review

### **Internal Testing**
- **iOS**: TestFlight distribution
- **Android**: Internal testing via Play Console

## 📈 **Performance Optimization**

### **Bundle Size Optimization**
- Enable Hermes engine for Android
- Use ProGuard for Android release builds
- Optimize images and assets

### **Runtime Performance** 
- Implement lazy loading for screens
- Optimize map rendering with viewport culling
- Use FlatList for large data sets
- Implement proper memory management

### **Battery Optimization**
- Implement smart location tracking
- Use background app refresh wisely
- Optimize network requests
- Implement proper cleanup in useEffect

## 🤝 **Contributing**

### **Code Style**
- Follow React Native best practices
- Use TypeScript for type safety
- Follow established folder structure
- Write unit tests for new features

### **Git Workflow**
```bash
# Create feature branch
git checkout -b feature/new-screen

# Make changes and commit
git add .
git commit -m "Add new safety screen"

# Push and create PR
git push origin feature/new-screen
```

## 📚 **Resources**

- [React Native Documentation](https://reactnative.dev/docs/getting-started)
- [React Navigation](https://reactnavigation.org/)
- [React Native Maps](https://github.com/react-native-maps/react-native-maps)
- [H3 Documentation](https://h3geo.org/)
- [AmISafe Web App](http://localhost/amisafe-crime-map) for reference

---

**Happy Coding!** 🎉 The AmISafe mobile app will provide essential crime safety information to users on-the-go, leveraging the same powerful H3 geospatial data as the web application.