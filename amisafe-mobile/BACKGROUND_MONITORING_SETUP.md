# Background Monitoring Setup Guide

## Overview

The AmISafe mobile app includes a sophisticated background location monitoring system that:

- ✅ Runs continuously in the background
- ✅ Auto-starts when the phone boots
- ✅ Monitors H3 resolution 11 (~700m hexagons)
- ✅ Alerts users when entering areas with z-score ≥ 2
- ✅ Sends push notifications with crime map links
- ✅ Works on both iOS and Android

## Architecture

### Core Components

1. **BackgroundLocationService.ts** - Main monitoring service
   - GPS tracking via react-native-geolocation-service
   - H3 index calculation using h3-js
   - Z-score checking via AmISafe API
   - Notification triggering
   - State persistence

2. **NotificationService.ts** - Push notification handler
   - Local notification creation
   - Channel management (Android)
   - Deep linking to crime map
   - Notification permissions

3. **StorageService.ts** - Persistent data storage
   - AsyncStorage wrapper
   - Monitoring state tracking
   - Location history
   - User preferences

4. **useBackgroundMonitoring.ts** - React hook
   - Permission management
   - Start/stop monitoring
   - State restoration
   - UI integration

5. **SettingsScreen.tsx** - User interface
   - Enable/disable monitoring toggle
   - Z-score threshold adjustment
   - Notification cooldown settings
   - Location history management

## Configuration

### Required Packages

```bash
cd amisafe-mobile

# Core location tracking (already installed)
npm install react-native-geolocation-service

# H3 geospatial library (already installed)
npm install h3-js

# Storage (already installed)
npm install @react-native-async-storage/async-storage

# Permissions (already installed)
npm install react-native-permissions

# Push notifications (NEEDS INSTALLATION)
npm install react-native-push-notification
npm install @react-native-community/push-notification-ios  # iOS only
```

### Android Configuration

#### 1. Permissions (android/app/src/main/AndroidManifest.xml)

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

#### 2. Boot Receiver (android/app/src/main/java/com/amisafe/BootReceiver.java)

```java
package com.amisafe;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.util.Log;

public class BootReceiver extends BroadcastReceiver {
    private static final String TAG = "AmISafe.BootReceiver";
    private static final String PREFS_NAME = "RCTAsyncLocalStorage";
    
    @Override
    public void onReceive(Context context, Intent intent) {
        if (Intent.ACTION_BOOT_COMPLETED.equals(intent.getAction())) {
            Log.d(TAG, "Device boot completed");
            
            // Check if monitoring was enabled before reboot
            SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
            boolean monitoringEnabled = prefs.getBoolean("background_monitoring_enabled", false);
            
            if (monitoringEnabled) {
                Log.d(TAG, "Starting location monitoring service");
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

#### 3. Foreground Service (android/app/src/main/java/com/amisafe/LocationMonitoringService.java)

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
import android.util.Log;
import androidx.core.app.NotificationCompat;

public class LocationMonitoringService extends Service {
    private static final String TAG = "AmISafe.LocationService";
    private static final String CHANNEL_ID = "location_monitoring";
    private static final int NOTIFICATION_ID = 1001;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "Service created");
        createNotificationChannel();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "Service started");
        
        // Create and start foreground notification
        Notification notification = createNotification();
        startForeground(NOTIFICATION_ID, notification);
        
        // Return START_STICKY so service restarts if killed
        return START_STICKY;
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        Log.d(TAG, "Service destroyed");
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
            channel.setDescription("AmISafe background location monitoring");
            channel.setShowBadge(false);
            
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    private Notification createNotification() {
        Intent notificationIntent = new Intent(this, MainActivity.class);
        PendingIntent pendingIntent = PendingIntent.getActivity(
            this,
            0,
            notificationIntent,
            PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT
        );

        return new NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("AmISafe Protection Active")
            .setContentText("Monitoring your location for safety alerts")
            .setSmallIcon(R.drawable.ic_notification)
            .setContentIntent(pendingIntent)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setCategory(NotificationCompat.CATEGORY_SERVICE)
            .build();
    }
}
```

#### 4. Gradle Configuration (android/app/build.gradle)

```gradle
android {
    defaultConfig {
        minSdkVersion 23  // Required for background location
        targetSdkVersion 33
    }
}
```

### iOS Configuration

#### 1. Info.plist

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

#### 2. Podfile

```ruby
target 'AmISafe' do
  # ... other pods ...
  
  pod 'react-native-geolocation', :path => '../node_modules/@react-native-community/geolocation'
end
```

## Usage

### In App.tsx or Root Component

```typescript
import { useEffect } from 'react';
import BackgroundLocationService from './src/services/location/BackgroundLocationService';

function App() {
  useEffect(() => {
    // Restore monitoring state on app launch
    BackgroundLocationService.restoreMonitoringState();
  }, []);

  return (
    // Your app components
  );
}
```

### In Settings Screen

```typescript
import { useBackgroundMonitoring } from './src/hooks/useBackgroundMonitoring';

function SettingsScreen() {
  const { isMonitoring, toggleMonitoring } = useBackgroundMonitoring();

  return (
    <Switch 
      value={isMonitoring}
      onValueChange={toggleMonitoring}
    />
  );
}
```

## How It Works

### 1. Location Tracking

- **GPS Updates**: Every 60 seconds or when user moves 50+ meters
- **Accuracy**: High-accuracy mode enabled
- **Battery**: Optimized with distance filter and interval settings

### 2. H3 Hexagon Monitoring

```typescript
// Convert GPS coordinates to H3 index
const h3Index = h3.latLngToCell(latitude, longitude, 11);

// Check if user moved to new hexagon
if (h3Index !== previousH3Index) {
  // Fetch crime data for new hexagon
  checkHexagonSafety(h3Index);
}
```

### 3. Crime Data API Call

```typescript
// Fetch hexagon statistics from AmISafe API
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

### 4. Notification Triggering

- **Threshold**: Z-score ≥ 2.0 (configurable)
- **Cooldown**: 5 minutes between notifications (configurable)
- **Priority**: High priority for immediate delivery
- **Action**: Deep link to crime map at current location

### 5. State Persistence

- Monitoring state saved to AsyncStorage
- Restored on app restart
- Service auto-starts on device boot if enabled

## Configuration Options

### In BackgroundLocationService.ts

```typescript
// H3 resolution (5-13, default: 11 for ~700m hexagons)
private readonly H3_RESOLUTION = 11;

// Z-score threshold for notifications (default: 2.0)
private readonly Z_SCORE_THRESHOLD = 2.0;

// Location update interval in milliseconds (default: 60s)
private readonly UPDATE_INTERVAL = 60000;

// Minimum movement before update in meters (default: 50m)
private readonly DISTANCE_FILTER = 50;

// Notification cooldown in milliseconds (default: 5min)
private notificationCooldown: number = 300000;
```

### User-Configurable Settings

Users can adjust in Settings screen:
- Z-score threshold: 1.0, 1.5, 2.0, 2.5, 3.0
- Notification cooldown: 1, 5, 10, 15, 30 minutes
- Enable/disable monitoring

## Testing

### Local Testing

```bash
# Start Metro bundler
npm start

# Run on Android
npm run android

# Run on iOS
npm run ios

# Enable monitoring from Settings screen
# Walk around or use location simulation
# Check logs for H3 index changes and z-score checks
```

### Simulating Location (iOS)

1. Product → Scheme → Edit Scheme
2. Run → Options → Default Location → Custom Location
3. Enter coordinates in high-crime area

### Simulating Location (Android)

```bash
# Connect to emulator
adb shell

# Set location (latitude, longitude)
geo fix -75.1652 39.9526
```

### Testing Boot Auto-Start

```bash
# Android - Reboot device
adb reboot

# After boot, check if service started
adb logcat | grep AmISafe
```

## Troubleshooting

### Android

**Permissions not granted:**
- Check AndroidManifest.xml has all required permissions
- Request permissions at runtime before starting monitoring
- For Android 10+, ensure background location permission requested separately

**Service not starting on boot:**
- Verify BootReceiver is registered in AndroidManifest.xml
- Check BootReceiver.java has correct package name
- Ensure monitoring was enabled before reboot (check AsyncStorage)
- Some manufacturers (Xiaomi, Huawei) require "Autostart" permission in Settings

**Notifications not showing:**
- Create notification channels before sending notifications
- Check notification permissions granted (Android 13+)
- Verify NotificationService.initialize() called before first notification

### iOS

**Location updates stop in background:**
- Ensure Info.plist has "location" in UIBackgroundModes
- Request "Always" location permission, not just "When In Use"
- Check pausesLocationUpdatesAutomatically is false

**App doesn't restart after force-quit:**
- iOS doesn't restart apps after user force-quits
- This is expected iOS behavior for privacy/battery
- App will restart on next device reboot

## Battery Optimization

### Current Settings (Balanced)

- **Update Interval**: 60s (can increase to 120-300s for better battery)
- **Distance Filter**: 50m (can increase to 100-200m)
- **Accuracy**: High (can lower to balanced)

### Aggressive Battery Saving

```typescript
interval: 300000,        // 5 minutes
distanceFilter: 200,     // 200 meters
enableHighAccuracy: false,
```

### Maximum Monitoring (More Battery Drain)

```typescript
interval: 30000,         // 30 seconds
distanceFilter: 25,      // 25 meters
enableHighAccuracy: true,
```

## Privacy & Data

- **Local Storage**: All location history stored locally via AsyncStorage
- **Never Shared**: User location never sent to remote servers
- **API Calls**: Only H3 index sent to API (not exact coordinates)
- **User Control**: Users can clear location history anytime
- **Transparency**: Clear permissions descriptions explain monitoring purpose

## Future Enhancements

- [ ] Geofencing for specific high-crime areas
- [ ] Machine learning for personalized risk thresholds
- [ ] Emergency mode with more frequent updates
- [ ] Location sharing with trusted contacts
- [ ] Historical route safety analysis
- [ ] Battery optimization profiles
- [ ] Widget showing current area safety status
