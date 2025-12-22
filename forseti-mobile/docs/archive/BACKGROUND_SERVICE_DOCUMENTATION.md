# AmISafe Background Monitoring Service - Complete Documentation

## Overview

The AmISafe background monitoring service provides **continuous, real-time safety monitoring** by tracking the user's location, calculating their H3 hexagon position, querying the Forseti API for crime data, and sending push notifications when entering high-risk areas.

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     BACKGROUND SERVICE FLOW                     │
└─────────────────────────────────────────────────────────────────┘

┌──────────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│   GPS        │───▶│  H3 Index    │───▶│  API Query   │───▶│ Notification │
│   Tracking   │    │  Calculation │    │  (Z-Score)   │    │  Trigger     │
└──────────────┘    └──────────────┘    └──────────────┘    └──────────────┘
      │                    │                    │                    │
      ▼                    ▼                    ▼                    ▼
 Every 60s or         H3 Resolution 11    forseti.life API    Alert if Z≥2.0
 50m movement         (~700m hexagon)     /api/amisafe/       5min cooldown
```

## Core Components

### 1. BackgroundLocationService.ts

**Location**: `/src/services/location/BackgroundLocationService.ts`

**Primary Responsibilities**:

- GPS location tracking via `react-native-geolocation-service`
- H3 geospatial index calculation at resolution 11 (~700m hexagons)
- Hexagon change detection (monitoring when user moves to new hex)
- API communication with Forseti backend
- Notification triggering based on z-score thresholds
- Location history persistence (last 100 locations)
- State management and restoration

**Key Configuration**:

```typescript
H3_RESOLUTION = 11; // ~700m hexagons for monitoring
Z_SCORE_THRESHOLD = 2.0; // Alert when z-score >= 2.0
API_BASE_URL = 'https://forseti.life';
UPDATE_INTERVAL = 60000; // Check every 60 seconds
DISTANCE_FILTER = 50; // Minimum 50m movement before update
NOTIFICATION_COOLDOWN = 300000; // 5 minutes between alerts
```

### 2. useBackgroundMonitoring.ts

**Location**: `/src/hooks/useBackgroundMonitoring.ts`

**Primary Responsibilities**:

- React hook interface for UI components
- Permission management (foreground + background location)
- Start/stop monitoring controls
- State restoration on app launch
- User feedback (alerts and confirmations)

**Permissions Required**:

- **iOS**: `LOCATION_WHEN_IN_USE` + `LOCATION_ALWAYS`
- **Android**: `ACCESS_FINE_LOCATION` + `ACCESS_BACKGROUND_LOCATION` (API 29+)

### 3. NotificationService.ts

**Location**: `/src/services/notifications/NotificationService.ts`

**Primary Responsibilities**:

- Local push notification delivery
- Notification channel management (Android)
- Sound and vibration configuration
- Deep linking to crime map
- Priority and delivery settings

### 4. StorageService.ts

**Location**: `/src/services/storage/StorageService.ts`

**Primary Responsibilities**:

- AsyncStorage wrapper for data persistence
- Monitoring state tracking
- Location history management
- User preferences storage

## Process Flow Diagram

### Detailed Step-by-Step Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│ 1. APP INITIALIZATION                                               │
├─────────────────────────────────────────────────────────────────────┤
│ • App launches → Check StorageService for previous state            │
│ • If monitoring was enabled → Auto-restore monitoring               │
│ • Initialize NotificationService → Request notification permissions │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│ 2. USER ENABLES MONITORING (Settings Screen)                       │
├─────────────────────────────────────────────────────────────────────┤
│ • User toggles "Enable Protection" switch                           │
│ • useBackgroundMonitoring hook calls requestLocationPermissions()   │
│ • Request foreground permission (LOCATION_WHEN_IN_USE)              │
│ • Request background permission (LOCATION_ALWAYS / BACKGROUND)      │
│ • If granted → Call BackgroundLocationService.startMonitoring()     │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│ 3. START LOCATION TRACKING                                          │
├─────────────────────────────────────────────────────────────────────┤
│ • Geolocation.watchPosition() starts GPS tracking                   │
│ • Configuration:                                                    │
│   - enableHighAccuracy: true                                        │
│   - distanceFilter: 50 meters                                       │
│   - interval: 60 seconds                                            │
│   - showsBackgroundLocationIndicator: true (iOS)                    │
│   - pausesLocationUpdatesAutomatically: false                       │
│ • Save state to AsyncStorage: background_monitoring_enabled = true  │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│ 4. LOCATION UPDATE RECEIVED                                         │
├─────────────────────────────────────────────────────────────────────┤
│ • GPS coordinates received: { lat, lng, accuracy, timestamp }       │
│ • Trigger: Every 60s OR when user moves 50+ meters                  │
│ • handleLocationUpdate() is called                                  │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│ 5. H3 INDEX CALCULATION                                             │
├─────────────────────────────────────────────────────────────────────┤
│ • Convert GPS coordinates to H3 hexagon index:                      │
│   const h3Index = h3.latLngToCell(lat, lng, 11)                     │
│ • Resolution 11 = ~700 meter hexagons                               │
│ • Example output: "8b283082d7dffff"                                 │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│ 6. HEXAGON CHANGE DETECTION                                         │
├─────────────────────────────────────────────────────────────────────┤
│ • Compare: h3Index !== currentH3Index                               │
│ • If different → User moved to new hexagon                          │
│ • If same → Skip (user still in same hex)                           │
│ • Update currentH3Index with new value                              │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│ 7. NOTIFICATION COOLDOWN CHECK                                      │
├─────────────────────────────────────────────────────────────────────┤
│ • Check time since last notification                                │
│ • If < 5 minutes → Skip API call and notification                   │
│ • If >= 5 minutes → Proceed to safety check                         │
│ • Prevents notification spam                                        │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│ 8. API QUERY TO FORSETI                                             │
├─────────────────────────────────────────────────────────────────────┤
│ • API Request:                                                      │
│   GET https://forseti.life/api/amisafe/aggregated?                  │
│       resolution=11&                                                │
│       h3_index=8b283082d7dffff&                                     │
│       format=json                                                   │
│                                                                     │
│ • Response Example:                                                 │
│   {                                                                 │
│     "hexagons": [{                                                  │
│       "h3_index": "8b283082d7dffff",                                │
│       "incident_count": 145,                                        │
│       "incident_z_score": 2.34,                                     │
│       "risk_level": "HIGH"                                          │
│     }]                                                              │
│   }                                                                 │
│                                                                     │
│ • Timeout: 10 seconds                                               │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│ 9. Z-SCORE EVALUATION                                               │
├─────────────────────────────────────────────────────────────────────┤
│ • Extract z_score from API response                                 │
│ • Compare with threshold (default: 2.0)                             │
│                                                                     │
│ • If z_score >= 2.0 → HIGH RISK → Send notification                 │
│ • If z_score < 2.0  → SAFE → Log and continue                       │
│                                                                     │
│ • Z-Score Interpretation:                                           │
│   - < 1.0  = Below average crime                                    │
│   - 1.0-2.0 = Normal range                                          │
│   - 2.0-3.0 = HIGH risk (2 std deviations above mean)               │
│   - > 3.0   = CRITICAL risk (3+ std deviations)                     │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│ 10. SEND DANGER NOTIFICATION                                        │
├─────────────────────────────────────────────────────────────────────┤
│ • NotificationService.scheduleNotification() called                 │
│ • Notification Content:                                             │
│   Title: "⚠️ High Crime Area Alert"                                 │
│   Message: "You are entering a potentially dangerous area.          │
│             145 incidents reported here (Risk: HIGH, Z-Score: 2.3)" │
│ • Configuration:                                                    │
│   - Priority: HIGH                                                  │
│   - Sound: Enabled                                                  │
│   - Vibration: Enabled                                              │
│   - Deep link data: h3_index, z_score, lat, lng                     │
│ • Update lastNotificationTime for cooldown tracking                 │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│ 11. SAVE LOCATION HISTORY                                           │
├─────────────────────────────────────────────────────────────────────┤
│ • Save to AsyncStorage:                                             │
│   {                                                                 │
│     h3_index: "8b283082d7dffff",                                    │
│     latitude: 39.9526,                                              │
│     longitude: -75.1652,                                            │
│     z_score: 2.34,                                                  │
│     timestamp: 1702473600000,                                       │
│     resolution: 11                                                  │
│   }                                                                 │
│ • Maintain last 100 locations only                                  │
│ • Used for analytics and history viewing                            │
└─────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────┐
│ 12. CONTINUE MONITORING                                             │
├─────────────────────────────────────────────────────────────────────┤
│ • Service continues running in background                           │
│ • Repeats from Step 4 on next GPS update                            │
│ • Persists through app backgrounding and foregrounding              │
│ • Survives app restarts (auto-restores from AsyncStorage)           │
└─────────────────────────────────────────────────────────────────────┘
```

## API Integration Details

### Forseti AmISafe API Endpoint

**Base URL**: `https://forseti.life/api/amisafe/aggregated`

**Request Parameters**:
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `resolution` | integer | H3 resolution level (8-13) | `11` |
| `h3_index` | string | H3 hexagon identifier | `8b283082d7dffff` |
| `format` | string | Response format | `json` |

**Response Format**:

```json
{
  "hexagons": [
    {
      "h3_index": "8b283082d7dffff",
      "incident_count": 145,
      "incident_z_score": 2.34,
      "risk_level": "HIGH",
      "resolution": 11,
      "crimes_by_type": {
        "Assault": 45,
        "Theft": 67,
        "Vandalism": 33
      }
    }
  ],
  "meta": {
    "total_hexagons": 1,
    "timestamp": "2024-12-13T10:30:00Z"
  }
}
```

**Z-Score Risk Levels**:

- **< 0.0**: Very Low (Below average)
- **0.0 - 1.0**: Low (Normal)
- **1.0 - 2.0**: Moderate (Slightly elevated)
- **2.0 - 3.0**: **HIGH** (Notification trigger)
- **> 3.0**: Critical (Extreme danger)

## User Configuration Options

### Settings Screen Controls

**1. Enable Protection Toggle**

- Start/stop background monitoring
- Requires location permissions
- Shows current H3 index when active

**2. Danger Threshold Selector**

- Options: 1.0, 1.5, 2.0, 2.5, 3.0
- Default: 2.0
- Higher = fewer notifications (only very dangerous areas)
- Lower = more notifications (moderately dangerous areas)

**3. Notification Cooldown**

- Options: 1, 5, 10, 15, 30 minutes
- Default: 5 minutes
- Prevents repeated alerts in same area

**4. Location History**

- View count of stored locations
- Clear history button
- Stored locally, never uploaded

## Platform-Specific Implementation

### iOS Configuration

**Info.plist Required Keys**:

```xml
<key>NSLocationAlwaysAndWhenInUseUsageDescription</key>
<string>AmISafe needs continuous access to your location to alert you when entering high-crime areas, even when the app is in the background.</string>

<key>NSLocationWhenInUseUsageDescription</key>
<string>AmISafe needs access to your location to provide safety information for your current area.</string>

<key>UIBackgroundModes</key>
<array>
  <string>location</string>
</array>
```

**Background Location Indicator**:

- Blue bar shown at top of screen when tracking
- `showsBackgroundLocationIndicator: true` in config
- Cannot be disabled (iOS requirement for transparency)

### Android Configuration

**AndroidManifest.xml Required Permissions**:

```xml
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
```

**Android 10+ (API 29+)**:

- Background location requires separate permission request
- Must request `ACCESS_FINE_LOCATION` first
- Then request `ACCESS_BACKGROUND_LOCATION`
- User must select "Allow all the time" in settings

**Foreground Service**:

- Consider implementing foreground service for Android 8.0+
- Shows persistent notification while tracking
- Prevents system from killing service

## Battery Optimization

### Current Settings (Balanced)

- **Update Interval**: 60 seconds (good balance)
- **Distance Filter**: 50 meters (prevents over-polling)
- **High Accuracy**: Enabled (necessary for H3 precision)

### Optimization Strategies

1. **Coarse Updates**: Use `distanceFilter` to reduce GPS calls
2. **H3 Change Detection**: Only query API when hex changes
3. **Notification Cooldown**: Prevents repeated API calls
4. **Geofencing**: Consider geofence API for area-based triggers

### Battery Impact Estimates

- **Low**: ~5-10% per day (current settings)
- **Medium**: ~10-15% per day (high frequency updates)
- **High**: ~15-25% per day (always-on high-accuracy GPS)

## Testing & Debugging

### Enable Debug Logging

All console logs are prefixed for easy filtering:

- `📍` - Location updates
- `🚨` - Danger notifications
- `✅` - Safe areas
- `⚠️` - Warnings
- `❌` - Errors
- `📱` - App state changes

### Test Scenarios

**1. Basic Monitoring**

```bash
# Start monitoring
# Move 50+ meters
# Check console for H3 index change
# Verify API call to forseti.life
```

**2. High-Risk Area Notification**

```bash
# Find hexagon with z-score >= 2.0 from web map
# Navigate to that location (or simulate GPS)
# Should receive notification
# Check 5-minute cooldown works
```

**3. State Restoration**

```bash
# Enable monitoring
# Force quit app
# Reopen app
# Verify monitoring restored automatically
```

**4. Permission Handling**

```bash
# Revoke location permissions
# Try to enable monitoring
# Verify proper error messages
# Re-grant permissions
# Verify monitoring starts successfully
```

## Troubleshooting

### Common Issues

**1. Notifications Not Appearing**

- Check notification permissions granted
- Verify z-score >= threshold
- Check cooldown period (5 min default)
- Ensure NotificationService initialized

**2. Location Not Updating**

- Verify location permissions (foreground + background)
- Check GPS enabled on device
- Ensure app not in battery optimization list
- Verify network connectivity for API calls

**3. API Errors**

- Check network connectivity
- Verify forseti.life is accessible
- Check API endpoint returns data for H3 index
- Review console logs for specific error messages

**4. High Battery Drain**

- Increase UPDATE_INTERVAL (60s → 120s)
- Increase DISTANCE_FILTER (50m → 100m)
- Consider disabling high accuracy mode
- Implement geofencing instead

## Future Enhancements

### Planned Features

1. **Geofencing**: Use native geofence API for better battery life
2. **Route Safety**: Pre-calculate safety along routes
3. **Time-based Risk**: Adjust alerts based on time of day
4. **User Feedback**: Allow marking false positives
5. **Offline Mode**: Cache hexagon data for offline use
6. **Analytics Dashboard**: Visualize location history and risk exposure

### Performance Improvements

1. **Batch API Calls**: Query multiple hexagons at once
2. **Local Cache**: Store hexagon data for 24 hours
3. **Predictive Loading**: Pre-fetch data for nearby hexagons
4. **Smart Intervals**: Adjust update frequency based on movement

## Security & Privacy

### Data Collection

- **Location Data**: Stored locally only (last 100 points)
- **API Calls**: Only H3 index sent, not raw GPS coordinates
- **No Tracking**: User location never uploaded to server
- **No Third Parties**: No data sharing with external services

### User Control

- **Opt-in Only**: Monitoring disabled by default
- **Easy Disable**: One-tap to stop monitoring
- **Clear History**: User can delete all location history
- **Transparent**: Clear messaging about what data is collected

## Code References

### Key Files

- `/src/services/location/BackgroundLocationService.ts` - Main service
- `/src/hooks/useBackgroundMonitoring.ts` - React hook interface
- `/src/services/notifications/NotificationService.ts` - Notifications
- `/src/services/storage/StorageService.ts` - Data persistence
- `/src/screens/Settings/SettingsScreen.tsx` - User controls

### External Dependencies

- `react-native-geolocation-service` - GPS tracking
- `h3-js` - H3 geospatial calculations
- `@react-native-async-storage/async-storage` - Data persistence
- `react-native-permissions` - Permission handling
- `axios` - HTTP API calls

## Support & Resources

- **Forseti Website**: https://forseti.life
- **API Documentation**: https://forseti.life/how-it-works
- **H3 Documentation**: https://h3geo.org
- **Privacy Policy**: https://forseti.life/privacy
- **Contact**: https://forseti.life/contact
