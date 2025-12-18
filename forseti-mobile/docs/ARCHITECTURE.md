# AmISafe Mobile Application Architecture

## System Overview

The AmISafe mobile application is a location-aware safety application that provides real-time crime risk assessment based on ultra-precise H3 geospatial analysis. The application operates on a three-tier architecture with comprehensive data flow between mobile client, Drupal API, and H3 geospatial database.

## 🏗️ Architecture Components

### **1. Mobile Application (React Native)**
**Platform**: Cross-platform iOS and Android  
**Framework**: React Native 0.72.6 with TypeScript  
**Primary Functions**:
- Real-time GPS location tracking (H3 Level 13 precision - 44m²)
- Background location monitoring with geo-fence detection
- Push notification system for risk level changes
- Offline data caching for critical safety information
- User authentication and account management

### **2. API Layer (Drupal Module)**
**Location**: `/sites/stlouisintegration/web/modules/custom/amisafe/`  
**Platform**: Drupal 9/10/11 with custom API endpoints  
**Primary Functions**:
- RESTful API endpoints for crime data access
- User authentication and session management
- H3 spatial query processing
- Real-time risk level calculations
- Data aggregation and statistics

### **3. Database Layer (H3 Geospatial Database)**
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

### **Resolution Strategy**
The application uses H3 Level 13 (44m² precision) for user location tracking and risk assessment:

```
Resolution │ Area      │ Use Case                    │ Update Frequency
-----------|-----------|-----------------------------|-----------------
5          │ 251.1 km² │ Citywide statistics        │ Daily
8          │ 0.7 km²   │ Neighborhood context       │ Hourly  
10         │ 15,047 m² │ Block-level awareness      │ Every 15 min
13         │ 44 m²     │ User position tracking     │ Real-time
```

### **Spatial Query Flow**
1. **GPS Location Capture**: Mobile app captures precise GPS coordinates
2. **H3 Index Calculation**: Convert lat/lng to H3 Level 13 index
3. **API Query**: Request risk data for current and surrounding hexagons
4. **Risk Assessment**: Calculate threat level based on historical data
5. **Notification Trigger**: Alert user if risk level exceeds threshold

## 📱 Mobile Application Architecture

### **Core Services**

#### **LocationService**
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

#### **ApiService** 
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

#### **NotificationService**
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

#### **StorageService**
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

### **Screen Components**

#### **HomeScreen** - Main Dashboard
- Current location risk level display
- Quick statistics (nearby incidents, risk trends)
- Emergency contact shortcuts
- Daily safety score

#### **MapScreen** - Interactive Crime Map  
- Real-time H3 hexagon overlays
- User position marker with accuracy radius
- Crime incident markers with severity colors
- Interactive hexagon tap for detailed statistics

#### **SafetyScreen** - Risk Assessment
- Current area risk level with historical context
- Nearby hotspots and safe zones
- Time-based risk analysis (current hour safety)
- Recommended safe routes

#### **StatisticsScreen** - Analytics Dashboard
- Personal safety metrics and trends
- Area comparison tools
- Crime type breakdowns
- Historical safety patterns

#### **ProfileScreen** - User Management
- Account settings and preferences
- Notification configuration
- Emergency contacts management
- Privacy and data settings

## 🔗 API Integration Architecture

### **Authentication Flow**
```
Mobile App ──► POST /user/login ──► Drupal
           ◄── JWT Token      ◄──
           
Mobile App ──► API Requests   ──► Drupal (with JWT header)
           ◄── Authorized Data ◄──
```

### **Core API Endpoints**

#### **Authentication Endpoints**
- `POST /user/register` - User registration
- `POST /user/login` - User authentication  
- `POST /user/logout` - Session termination
- `GET /user/profile` - User profile data

#### **Crime Data Endpoints** 
- `GET /api/amisafe/risk-level` - Current location risk assessment
- `GET /api/amisafe/aggregated` - H3 hexagon crime aggregations
- `GET /api/amisafe/incidents` - Individual crime incidents
- `GET /api/amisafe/hotspots` - High-crime area identification

#### **System Information**
- `GET /api/amisafe/system-stats` - Database and system statistics
- `GET /api/amisafe/crime-types` - Available crime categories
- `GET /api/amisafe/districts` - Police district boundaries

### **Real-time Data Flow**

```
┌─────────────────┐
│ User Movement   │
│ (GPS Updates)   │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐
│ H3 Index Calc   │
│ (Level 13)      │
└─────────┬───────┘
          │
          ▼
┌─────────────────┐    ┌──────────────────┐
│ Index Changed?  │───►│ API Risk Query   │
│                 │    │ GET /risk-level  │
└─────────────────┘    └─────────┬────────┘
          │                      │
          │ No Change            ▼
          ▼                ┌──────────────────┐
┌─────────────────┐        │ Risk Assessment  │
│ Continue        │        │ (High/Med/Low)   │
│ Monitoring      │        └─────────┬────────┘
└─────────────────┘                  │
                                     ▼
                           ┌──────────────────┐
                           │ Notification     │
                           │ (if Risk High)   │
                           └──────────────────┘
```

## 🔔 Notification System Architecture

### **Risk Level Monitoring**
The application continuously monitors the user's H3 Level 13 hexagon and triggers notifications based on:

1. **Risk Level Changes**: When moving from low to medium/high risk areas
2. **Time-based Alerts**: Risk levels that increase during specific hours
3. **Proximity Warnings**: Approaching known hotspots or high-crime areas  
4. **Safety Reminders**: Periodic check-ins in high-risk areas

### **Notification Types**

#### **Immediate Alerts (Push Notifications)**
- **High Risk Entry**: "⚠️ You've entered a high-crime area. Stay alert."
- **Hotspot Proximity**: "🚨 Crime hotspot detected 100m ahead."
- **Time-based Risk**: "🌙 This area shows increased risk at night."

#### **Background Monitoring**
- **Geofence Triggers**: Silent monitoring of risk boundaries
- **Battery Optimization**: Intelligent location update intervals
- **Offline Mode**: Cached risk data for areas without connectivity

## 🛡️ Security Architecture

### **Data Protection**
- **Local Storage Encryption**: Sensitive data encrypted using device keychain
- **API Token Management**: JWT tokens with automatic refresh
- **Location Privacy**: Option to anonymize location data
- **Minimal Data Collection**: Only essential data for safety features

### **Authentication Security**
- **JWT Token Authentication**: Secure API access with Drupal
- **Device Registration**: Unique device identifiers for push notifications
- **Session Management**: Automatic logout after inactivity
- **Password Requirements**: Strong password enforcement

## 📊 Performance Architecture

### **Caching Strategy**
- **H3 Index Cache**: Recently calculated H3 indexes stored locally
- **Risk Level Cache**: 30-minute cache for risk assessments
- **Crime Data Cache**: Offline storage of surrounding area data
- **Image Caching**: Map tiles and UI assets cached for offline use

### **Battery Optimization**
- **Adaptive Location Updates**: Frequency based on movement and risk level
- **Background App Refresh**: Intelligent background processing
- **Network Efficiency**: Batch API requests when possible
- **CPU Optimization**: H3 calculations optimized for mobile processors

## 🔄 Offline Capability Architecture

### **Data Synchronization**
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

### **Critical Data Caching**
- **Home/Work Area**: Extended cache for frequently visited locations
- **Emergency Data**: Emergency contacts and services always available
- **Recent Risk Levels**: Last known risk assessments for immediate areas
- **Safe Routes**: Pre-computed safe paths for emergency navigation

This architecture ensures that the AmISafe mobile application provides reliable, accurate, and timely safety information while maintaining user privacy and optimal performance across both iOS and Android platforms.