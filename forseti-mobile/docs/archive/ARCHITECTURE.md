# AmISafe Mobile Application - Architecture

## System Overview

The AmISafe mobile application is a location-aware safety application that provides real-time crime risk assessment based on ultra-precise H3 geospatial analysis. The application operates on a three-tier architecture with comprehensive data flow between mobile client, Drupal API, and H3 geospatial database.

## 🏗️ Architecture Components

### 1. Mobile Application (React Native)
**Platform**: Cross-platform iOS and Android  
**Framework**: React Native 0.72.6 with TypeScript  
**Primary Functions**:
- Real-time GPS location tracking (H3 Level 11-13 precision)
- Background location monitoring with geo-fence detection
- Push notification system for risk level changes
- Offline data caching for critical safety information
- User authentication and account management

### 2. API Layer (Drupal Module)
**Location**: `/sites/stlouisintegration/web/modules/custom/amisafe/`  
**Platform**: Drupal 9/10/11 with custom API endpoints  
**Primary Functions**:
- RESTful API endpoints for crime data access
- User authentication and session management
- H3 spatial query processing
- Real-time risk level calculations
- Data aggregation and statistics

### 3. Database Layer (H3 Geospatial Database)
**Database**: MySQL 8.0+ with H3 spatial indexing  
**Data Warehouse**: 3-layer architecture (Bronze → Silver → Gold)  
**Primary Functions**:
- Raw incident storage (3.4M+ crime records)
- H3 spatial indexing (Resolutions 5-13)
- Pre-computed aggregations (413K+ hexagons)
- Multi-resolution analytics

## 📊 Data Flow Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│   Mobile App    │◄──►│   Drupal API     │◄──►│  H3 Database        │
│                 │    │                  │    │                     │
│ • GPS Tracking  │    │ • Authentication │    │ • Raw Incidents     │
│ • Notifications │    │ • Spatial Queries│    │   (3.4M records)    │
│ • Risk Display  │    │ • Risk Calc      │    │ • H3 Aggregations   │
│ • User Auth     │    │ • User Management│    │   (413K hexagons)   │
│ • Offline Cache │    │ • API Endpoints  │    │ • Multi-Resolution  │
└─────────────────┘    └──────────────────┘    └─────────────────────┘
        │                        │                        │
        ▼                        ▼                        ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│ User Experience │    │  Business Logic  │    │   Data Storage      │
└─────────────────┘    └──────────────────┘    └─────────────────────┘
```

## 🗺️ H3 Geospatial Integration

### Resolution Strategy
The application uses different H3 resolutions for different purposes:

| Resolution | Area Coverage | Use Case | Update Frequency |
|------------|--------------|----------|------------------|
| 5 | 251.1 km² | Citywide statistics | Daily |
| 8 | 0.7 km² | Neighborhood context | Hourly |
| 10 | 15,047 m² | Block-level awareness | Every 15 min |
| 11 | ~700 m² | Background monitoring | Real-time |
| 13 | 44 m² | User position tracking | Real-time |

### Spatial Query Flow
1. **GPS Location Capture**: Mobile app captures precise GPS coordinates
2. **H3 Index Calculation**: Convert lat/lng to H3 Level 11-13 index
3. **API Query**: Request risk data for current and surrounding hexagons
4. **Risk Assessment**: Calculate threat level based on historical data
5. **Notification Trigger**: Alert user if risk level exceeds threshold

## 📱 Background Monitoring System

### Core Components

**BackgroundLocationService.ts** - Main monitoring service
- GPS tracking via react-native-geolocation-service
- H3 index calculation at resolution 11 (~700m hexagons)
- Z-score checking via AmISafe API
- Notification triggering when z-score >= 2.0
- Location history tracking (last 100 locations)
- State persistence via AsyncStorage
- Auto-restore on app restart

**NotificationService.ts** - Push notification handler
- Local notification creation
- Channel management (Android)
- Deep linking to crime map
- Notification permissions

**StorageService.ts** - Persistent data storage
- AsyncStorage wrapper
- Monitoring state tracking
- Location history
- User preferences

**useBackgroundMonitoring.ts** - React hook
- Permission management
- Start/stop monitoring
- State restoration
- UI integration

### Location Tracking Flow

```
GPS Update → H3 Calculation → Index Comparison → Risk Query → Notification
     ↓              ↓              ↓              ↓            ↓
  Lat/Lng    H3 Level 11    Changed Hex?    API Request   Alert User
```

### How It Works

**1. Location Tracking**
- **GPS Updates**: Every 60 seconds or when user moves 50+ meters
- **Accuracy**: High-accuracy mode enabled
- **Battery**: Optimized with distance filter and interval settings

**2. H3 Hexagon Monitoring**
```typescript
// Convert GPS coordinates to H3 index
const h3Index = h3.latLngToCell(latitude, longitude, 11);

// Check if user moved to new hexagon
if (h3Index !== previousH3Index) {
  // Fetch crime data for new hexagon
  checkHexagonSafety(h3Index);
}
```

**3. Crime Data API Call**
```typescript
GET https://stlouisintegration.com/api/amisafe/aggregated
  ?resolution=11
  &h3_index=8b283082d7dffff
  &format=json

Response:
{
  "hexagons": [{
    "h3_index": "8b283082d7dffff",
    "incident_count": 145,
    "incident_z_score": 2.34,
    "risk_level": "HIGH"
  }]
}
```

**4. Notification Triggering**
- **Threshold**: Z-score ≥ 2.0 (configurable)
- **Cooldown**: 5 minutes between notifications (configurable)
- **Priority**: High priority for immediate delivery
- **Action**: Deep link to crime map at current location

**5. State Persistence**
- Monitoring state saved to AsyncStorage
- Restored on app restart
- Service auto-starts on device boot if enabled

## 🔔 Notification System Architecture

### Risk Level Monitoring
The application continuously monitors the user's H3 Level 11-13 hexagon and triggers notifications based on:

1. **Risk Level Changes**: When moving from low to medium/high risk areas
2. **Time-based Alerts**: Risk levels that increase during specific hours
3. **Proximity Warnings**: Approaching known hotspots or high-crime areas
4. **Safety Reminders**: Periodic check-ins in high-risk areas

### Notification Types

**Immediate Alerts (Push Notifications)**
- **High Risk Entry**: "⚠️ You've entered a high-crime area. Stay alert."
- **Hotspot Proximity**: "🚨 Crime hotspot detected 100m ahead."
- **Time-based Risk**: "🌙 This area shows increased risk at night."

**Background Monitoring**
- **Geofence Triggers**: Silent monitoring of risk boundaries
- **Battery Optimization**: Intelligent location update intervals
- **Offline Mode**: Cached risk data for areas without connectivity

## 🔐 Authentication Architecture

### Session-Based Authentication

The app uses straightforward Drupal session-based authentication:

```typescript
// Authentication Flow
1. CSRF Token → GET /session/token
2. Session Login → POST /user/login
3. Session Storage → AsyncStorage
4. API Access → Authenticated requests
5. Demo Fallback → Development mode
```

**Key Features:**
- **CSRF Token Management**: Proper security token handling
- **Persistent Sessions**: Login state preserved across restarts
- **Demo Mode**: Development-friendly testing
- **Clean API**: Simple login/register/logout methods

## 📦 Service Layer Architecture

### LocationService
```typescript
class LocationService {
  // Real-time GPS tracking with H3 conversion
  getCurrentLocation(): Promise<H3Location>
  startLocationUpdates(): void
  stopLocationUpdates(): void
  
  // H3 spatial operations
  getCurrentH3Index(resolution: number): string
  getH3Neighbors(h3Index: string): string[]
  calculateH3Distance(h3Index1: string, h3Index2: string): number
}
```

### ApiService
```typescript
class ApiService {
  // Authentication endpoints
  login(credentials: LoginCredentials): Promise<AuthResponse>
  register(userData: RegisterData): Promise<AuthResponse>
  
  // Crime data endpoints
  getRiskLevel(h3Index: string): Promise<RiskLevel>
  getCrimeData(bounds: H3Bounds): Promise<CrimeData[]>
  getHotspots(resolution: number): Promise<Hotspot[]>
}
```

### NotificationService
```typescript
class NotificationService {
  // Risk-based notifications
  checkRiskLevelChange(currentH3: string, previousH3: string): void
  sendRiskAlert(riskLevel: RiskLevel, location: H3Location): void
  
  // System notifications
  requestPermissions(): Promise<boolean>
  scheduleSafetyReminders(): void
}
```

### StorageService
```typescript
class StorageService {
  // Offline data caching
  cacheCrimeData(h3Index: string, data: CrimeData): Promise<void>
  getCachedRiskLevel(h3Index: string): Promise<RiskLevel | null>
  
  // User preferences
  getUserPreferences(): Promise<UserPreferences>
  updateUserPreferences(prefs: UserPreferences): Promise<void>
}
```

## 🔗 API Integration Architecture

### Authentication Endpoints
- `POST /user/register` - User registration
- `POST /user/login` - User authentication
- `POST /user/logout` - Session termination
- `GET /user/profile` - User profile data

### Crime Data Endpoints
- `GET /api/amisafe/risk-level` - Current location risk assessment
- `GET /api/amisafe/aggregated` - H3 hexagon crime aggregations
- `GET /api/amisafe/incidents` - Individual crime incidents
- `GET /api/amisafe/hotspots` - High-crime area identification

### System Information
- `GET /api/amisafe/system-stats` - Database and system statistics
- `GET /api/amisafe/crime-types` - Available crime categories
- `GET /api/amisafe/districts` - Police district boundaries

## 🛡️ Security Architecture

### Data Protection
- **Local Storage Encryption**: Sensitive data encrypted using device keychain
- **API Token Management**: CSRF tokens with session management
- **Location Privacy**: Option to anonymize location data
- **Minimal Data Collection**: Only essential data for safety features

### Authentication Security
- **Session Authentication**: Secure API access with Drupal
- **Device Registration**: Unique device identifiers for push notifications
- **Session Management**: Automatic logout after inactivity
- **Password Requirements**: Strong password enforcement

## 📊 Performance Architecture

### Caching Strategy
- **H3 Index Cache**: Recently calculated H3 indexes stored locally
- **Risk Level Cache**: 30-minute cache for risk assessments
- **Crime Data Cache**: Offline storage of surrounding area data
- **Image Caching**: Map tiles and UI assets cached for offline use

### Battery Optimization
- **Adaptive Location Updates**: Frequency based on movement and risk level
- **Background App Refresh**: Intelligent background processing
- **Network Efficiency**: Batch API requests when possible
- **CPU Optimization**: H3 calculations optimized for mobile processors

### Configuration Options

**Current Settings (Balanced)**
```typescript
interval: 60000,          // 60 seconds
distanceFilter: 50,       // 50 meters
enableHighAccuracy: true,
H3_RESOLUTION: 11,        // ~700m hexagons
Z_SCORE_THRESHOLD: 2.0,   // Risk alert threshold
notificationCooldown: 300000, // 5 minutes
```

**Battery Saving Mode**
```typescript
interval: 300000,         // 5 minutes
distanceFilter: 200,      // 200 meters
enableHighAccuracy: false,
```

**Maximum Monitoring (Higher Battery Drain)**
```typescript
interval: 30000,          // 30 seconds
distanceFilter: 25,       // 25 meters
enableHighAccuracy: true,
```

## 🔄 Offline Capability Architecture

### Data Synchronization
```
Online Mode:
GPS Update → H3 Calculation → API Query → Risk Display → Cache Update

Offline Mode:
GPS Update → H3 Calculation → Cache Lookup → Risk Display → Sync Queue
                                    │
                                    ▼
                            Network Available?
                                    │
                                    ▼
                            Background Sync → Cache Update
```

### Critical Data Caching
- **Home/Work Area**: Extended cache for frequently visited locations
- **Recent Routes**: Cache of recently traveled hexagons
- **Emergency Data**: Always-available emergency contacts and numbers

## 🏗️ Native Platform Setup

### Android Configuration

**Required Files:**

**1. AndroidManifest.xml**
```xml
<manifest>
  <!-- Location Permissions -->
  <uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
  <uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
  <uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />
  
  <!-- Background Service -->
  <uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
  <uses-permission android:name="android.permission.FOREGROUND_SERVICE_LOCATION" />
  
  <!-- Boot Auto-Start -->
  <uses-permission android:name="android.permission.RECEIVE_BOOT_COMPLETED" />
  
  <!-- Notifications -->
  <uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
  <uses-permission android:name="android.permission.VIBRATE" />

  <application>
    <!-- Boot Receiver -->
    <receiver 
      android:name=".BootReceiver"
      android:enabled="true"
      android:exported="true">
      <intent-filter>
        <action android:name="android.intent.action.BOOT_COMPLETED" />
        <action android:name="android.intent.action.QUICKBOOT_POWERON" />
        <category android:name="android.intent.category.DEFAULT" />
      </intent-filter>
    </receiver>

    <!-- Location Monitoring Service -->
    <service
      android:name=".LocationMonitoringService"
      android:enabled="true"
      android:exported="false"
      android:foregroundServiceType="location" />
  </application>
</manifest>
```

**2. BootReceiver.java**
```java
package com.amisafe;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;

public class BootReceiver extends BroadcastReceiver {
    @Override
    public void onReceive(Context context, Intent intent) {
        if (Intent.ACTION_BOOT_COMPLETED.equals(intent.getAction())) {
            SharedPreferences prefs = context.getSharedPreferences(
                "RCTAsyncLocalStorage", Context.MODE_PRIVATE
            );
            boolean enabled = prefs.getBoolean("background_monitoring_enabled", false);
            
            if (enabled) {
                Intent serviceIntent = new Intent(context, LocationMonitoringService.class);
                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                    context.startForegroundService(serviceIntent);
                } else {
                    context.startService(serviceIntent);
                }
            }
        }
    }
}
```

**3. LocationMonitoringService.java**
```java
package com.amisafe;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Intent;
import android.os.Build;
import android.os.IBinder;
import androidx.core.app.NotificationCompat;

public class LocationMonitoringService extends Service {
    private static final String CHANNEL_ID = "location_monitoring";
    private static final int NOTIFICATION_ID = 1001;

    @Override
    public void onCreate() {
        super.onCreate();
        createNotificationChannel();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Notification notification = createNotification();
        startForeground(NOTIFICATION_ID, notification);
        return START_STICKY;
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                "Location Monitoring",
                NotificationManager.IMPORTANCE_LOW
            );
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    private Notification createNotification() {
        Intent notificationIntent = new Intent(this, MainActivity.class);
        PendingIntent pendingIntent = PendingIntent.getActivity(
            this, 0, notificationIntent,
            PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT
        );

        return new NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("AmISafe Protection Active")
            .setContentText("Monitoring your location for safety alerts")
            .setSmallIcon(R.drawable.ic_notification)
            .setContentIntent(pendingIntent)
            .setOngoing(true)
            .build();
    }
}
```

### iOS Configuration

**Info.plist**
```xml
<key>UIBackgroundModes</key>
<array>
    <string>location</string>
    <string>fetch</string>
    <string>remote-notification</string>
</array>

<key>NSLocationWhenInUseUsageDescription</key>
<string>AmISafe needs your location to alert you about dangerous areas nearby</string>

<key>NSLocationAlwaysAndWhenInUseUsageDescription</key>
<string>AmISafe monitors your location in the background to send safety alerts when you enter high-crime areas. This helps keep you safe by providing real-time warnings.</string>

<key>NSLocationAlwaysUsageDescription</key>
<string>AmISafe uses your location in the background to provide real-time safety alerts and crime statistics for your area</string>
```

## 🧪 Testing Architecture

### Unit Tests
- H3 Resolution Mapping
- Risk Level Calculation
- API URL Building
- Component Dependencies

### Integration Tests
- Location service integration
- Map rendering performance
- Filter functionality
- API data loading

### Performance Metrics
- **Initial Load**: < 2 seconds for Philadelphia metro area
- **Zoom Transitions**: < 1 second resolution switching
- **Filter Application**: < 500ms with cached data
- **Memory Usage**: Optimized for mobile constraints

## 🔮 Future Enhancements

- [ ] Geofencing for specific high-crime areas
- [ ] Machine learning for personalized risk thresholds
- [ ] Emergency mode with more frequent updates
- [ ] Location sharing with trusted contacts
- [ ] Historical route safety analysis
- [ ] Battery optimization profiles
- [ ] Widget showing current area safety status

---

For detailed developer instructions, see the main README.md file.
