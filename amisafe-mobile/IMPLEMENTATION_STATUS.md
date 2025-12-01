# Background Monitoring - Implementation Summary

## ✅ Completed

### Core Services Created

1. **BackgroundLocationService.ts**
   - GPS tracking via react-native-geolocation-service
   - H3 index calculation at resolution 11 (~700m hexagons)
   - Z-score checking via AmISafe API
   - Notification triggering when z-score >= 2.0
   - Location history tracking (last 100 locations)
   - State persistence via AsyncStorage
   - Auto-restore on app restart

2. **useBackgroundMonitoring.ts** (React Hook)
   - Permission management (foreground + background location)
   - Start/stop monitoring controls
   - State management
   - Auto-restore monitoring on app launch

3. **SettingsScreen.tsx**
   - Enable/disable monitoring toggle
   - Z-score threshold selector (1.0 - 3.0)
   - Notification cooldown selector (1-30 minutes)
   - Location history viewer
   - Clear history function

4. **PlatformConfiguration.ts**
   - Complete Android setup instructions
   - Complete iOS setup instructions
   - Permission documentation
   - Service registration templates

5. **BACKGROUND_MONITORING_SETUP.md**
   - Comprehensive implementation guide
   - Android code examples (BootReceiver, LocationMonitoringService)
   - iOS configuration examples
   - Testing procedures
   - Troubleshooting guide
   - Battery optimization tips

### Git Commit

- **Commit**: 18a22d095
- **Message**: "Implement background geolocation monitoring service"
- **Status**: Pushed to origin/main

---

## ⚠️ Required Next Steps

### 1. Install Missing Package

```bash
cd /home/keithaumiller/stlouisintegration.com/amisafe-mobile

# Install push notification library
npm install react-native-push-notification

# iOS only
npm install @react-native-community/push-notification-ios

# Link native dependencies
npx pod-install ios  # iOS
```

### 2. Android Native Configuration

#### Files to Create:

**a) android/app/src/main/java/com/amisafe/BootReceiver.java**

```java
package com.amisafe;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.util.Log;

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

**b) android/app/src/main/java/com/amisafe/LocationMonitoringService.java**

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
        startForeground(NOTIFICATION_ID, createNotification());
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
        Intent intent = new Intent(this, MainActivity.class);
        PendingIntent pendingIntent = PendingIntent.getActivity(
            this, 0, intent,
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

#### Files to Modify:

**c) android/app/src/main/AndroidManifest.xml**

Add before `<application>`:

```xml
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE_LOCATION" />
<uses-permission android:name="android.permission.RECEIVE_BOOT_COMPLETED" />
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
<uses-permission android:name="android.permission.VIBRATE" />
```

Add inside `<application>`:

```xml
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

<service
  android:name=".LocationMonitoringService"
  android:enabled="true"
  android:exported="false"
  android:foregroundServiceType="location" />
```

**d) android/app/build.gradle**

Ensure minimum SDK version:

```gradle
android {
    defaultConfig {
        minSdkVersion 23  // Required for background location
    }
}
```

### 3. iOS Native Configuration

**a) ios/AmISafe/Info.plist**

Add inside `<dict>`:

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
<string>AmISafe monitors your location in the background to send safety alerts when you enter high-crime areas</string>

<key>NSLocationAlwaysUsageDescription</key>
<string>AmISafe uses your location in the background to provide real-time safety alerts</string>
```

**b) ios/Podfile**

Ensure location pod is included:

```ruby
pod 'react-native-geolocation', :path => '../node_modules/@react-native-community/geolocation'
```

Then run:

```bash
cd ios
pod install
cd ..
```

### 4. Integrate into App

**a) App.tsx or index.js**

Add to root component:

```typescript
import { useEffect } from 'react';
import BackgroundLocationService from './src/services/location/BackgroundLocationService';
import NotificationService from './src/services/notifications/NotificationService';

function App() {
  useEffect(() => {
    // Initialize services on app launch
    const initServices = async () => {
      await NotificationService.initialize();
      await BackgroundLocationService.restoreMonitoringState();
    };
    
    initServices();
  }, []);

  // ... rest of your app
}
```

**b) Navigation**

Add SettingsScreen to your navigation:

```typescript
import SettingsScreen from './src/screens/Settings/SettingsScreen';

// In your navigator
<Stack.Screen 
  name="Settings" 
  component={SettingsScreen}
  options={{ title: 'Settings' }}
/>
```

### 5. Testing

```bash
# Install dependencies
npm install

# Android
npm run android

# iOS
npm run ios

# Test in app:
# 1. Navigate to Settings screen
# 2. Toggle "Enable Protection"
# 3. Grant location permissions
# 4. Walk around or simulate location
# 5. Check notifications when entering high-crime areas
```

### 6. Verify Auto-Start on Boot

**Android:**

```bash
# Enable monitoring in app
# Reboot device
adb reboot

# After boot, check logs
adb logcat | grep AmISafe
# Should see: "Boot completed - checking monitoring state"
# Should see: "Starting location monitoring service"
```

**iOS:**

- Enable monitoring in app
- Reboot device
- iOS will restore monitoring automatically (no logs visible)

---

## 🎯 Configuration Options

Users can customize in Settings screen:

- **Z-Score Threshold**: 1.0, 1.5, 2.0, 2.5, 3.0
- **Notification Cooldown**: 1, 5, 10, 15, 30 minutes
- **Enable/Disable Monitoring**: Toggle switch

Developers can adjust in BackgroundLocationService.ts:

- **H3_RESOLUTION**: 5-13 (default: 11 for ~700m)
- **UPDATE_INTERVAL**: milliseconds (default: 60000 for 1 minute)
- **DISTANCE_FILTER**: meters (default: 50m)

---

## 📊 Expected Behavior

### Normal Operation

1. User enables monitoring in Settings
2. App requests location permissions
3. BackgroundLocationService starts GPS tracking
4. Every 60s or 50m movement, location checked
5. GPS coordinates converted to H3:11 index
6. If new hexagon, API called to fetch z-score
7. If z-score >= 2.0 AND cooldown expired:
   - Push notification sent
   - "⚠️ High Crime Area Alert"
   - Tap to open crime map at location
8. Location history saved (last 100 locations)

### Background Operation

- App backgrounded: Monitoring continues
- App killed by OS: Monitoring stops, restarts on relaunch
- Device rebooted: BootReceiver starts service if enabled
- Low battery: System may reduce update frequency

### Notifications

**Title**: "⚠️ High Crime Area Alert"

**Message**: "You are entering a potentially dangerous area. 145 incidents reported here (Risk: HIGH, Z-Score: 2.3)"

**Actions**: Tap to open crime map

---

## 📝 Documentation

- `BACKGROUND_MONITORING_SETUP.md` - Full implementation guide
- `PlatformConfiguration.ts` - Configuration reference
- `BackgroundLocationService.ts` - Service code with comments
- `useBackgroundMonitoring.ts` - React hook documentation
- `SettingsScreen.tsx` - UI component example

---

## 🐛 Troubleshooting

See `BACKGROUND_MONITORING_SETUP.md` section "Troubleshooting" for:
- Permission issues
- Boot auto-start not working
- Notifications not showing
- Background tracking stopping
- Battery optimization conflicts

---

## 🔄 Next Phase

After completing above steps:

1. Test on physical devices (Android + iOS)
2. Verify notifications work in production
3. Test boot auto-start functionality
4. Optimize battery usage based on user feedback
5. Add analytics to track monitoring effectiveness
6. Implement geofencing for specific high-risk areas
7. Add emergency mode with more frequent updates
8. Create widget showing current area safety status
