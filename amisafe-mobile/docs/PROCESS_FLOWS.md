# AmISafe Mobile Application Process Flow Documentation

## 🚀 Application Lifecycle Process Flows

### **1. Application Startup Flow**

```mermaid
graph TD
    A[App Launch] --> B[Check Authentication]
    B --> C{User Logged In?}
    C -->|Yes| D[Load User Preferences]
    C -->|No| E[Show Login Screen]
    E --> F[User Authentication Process]
    F --> D
    D --> G[Request Location Permissions]
    G --> H{Location Granted?}
    H -->|Yes| I[Initialize Location Service]
    H -->|No| J[Show Location Required Dialog]
    J --> K[Request Permissions Again]
    K --> H
    I --> L[Request Notification Permissions]
    L --> M[Load Cached Data]
    M --> N[Initialize Background Services]
    N --> O[Navigate to Home Screen]
```

### **2. User Registration & Authentication Flow**

```mermaid
graph TD
    A[New User Registration] --> B[Enter Personal Information]
    B --> C[Email & Password]
    C --> D[Terms & Privacy Agreement]
    D --> E[Submit Registration]
    E --> F[API Call: POST /user/register]
    F --> G{Registration Success?}
    G -->|No| H[Show Error Message]
    H --> B
    G -->|Yes| I[Account Created Successfully]
    I --> J[Auto-Login with Credentials]
    J --> K[Request Location Permissions]
    K --> L[Setup Safety Preferences]
    L --> M[Navigate to Home Screen]
    
    N[Existing User Login] --> O[Enter Email/Password]
    O --> P[API Call: POST /user/login]
    P --> Q{Login Success?}
    Q -->|No| R[Show Login Error]
    R --> O
    Q -->|Yes| S[Store JWT Token]
    S --> T[Load User Profile]
    T --> M
```

### **3. Location Tracking & H3 Processing Flow**

```mermaid
graph TD
    A[GPS Location Update] --> B[Extract Lat/Lng Coordinates]
    B --> C[Calculate H3 Index Level 13]
    C --> D{H3 Index Changed?}
    D -->|No| E[Continue Monitoring]
    E --> A
    D -->|Yes| F[Store Previous H3 Index]
    F --> G[Update Current Location State]
    G --> H[Check Cached Risk Data]
    H --> I{Cache Valid & Recent?}
    I -->|Yes| J[Use Cached Risk Level]
    I →|No| K[API Call: GET /api/amisafe/risk-level]
    K --> L[Process Risk Response]
    J --> M[Update UI with Risk Level]
    L --> M
    M --> N[Compare with Previous Risk]
    N --> O{Risk Level Increased?}
    O -->|Yes| P[Trigger Risk Notification]
    O -->|No| Q[Update Background State]
    P --> Q
    Q --> E
```

### **4. Risk Assessment & Notification Flow**

```mermaid
graph TD
    A[Location Change Detected] --> B[Calculate Current H3 Index]
    B --> C[Query API for Risk Data]
    C --> D[Receive Risk Assessment]
    D --> E[Process Risk Level]
    E --> F{Risk Level Classification}
    F -->|Low| G[Green Status - Safe]
    F -->|Medium| H[Yellow Status - Caution]
    F -->|High| I[Red Status - Alert]
    F -->|Extreme| J[Critical Status - Danger]
    
    I --> K[Trigger Push Notification]
    J --> L[Trigger Emergency Alert]
    K --> M[Log Risk Event]
    L --> M
    G --> N[Update UI Silently]
    H --> N
    M --> N
    N --> O[Cache Risk Data]
    O --> P[Update Location History]
    P --> Q[Continue Monitoring]
```

### **5. Background Monitoring Process Flow**

```mermaid
graph TD
    A[App Backgrounded] --> B[Enable Background Location]
    B --> C[Set Reduced Update Frequency]
    C --> D[Monitor Significant Location Changes]
    D --> E{Significant Movement?}
    E -->|No| F[Continue Low-Power Monitoring]
    F --> D
    E -->|Yes| G[Calculate New H3 Index]
    G --> H[Quick Risk Assessment]
    H --> I{High Risk Area?}
    I -->|No| J[Update Silent State]
    I -->|Yes| K[Prepare Push Notification]
    K --> L[Send Risk Alert]
    L --> M[Log Background Event]
    J --> M
    M --> F
    
    N[App Foregrounded] --> O[Resume Full Location Updates]
    O --> P[Sync Background Events]
    P --> Q[Update UI with Latest State]
```

## 📊 Data Flow Process Diagrams

### **6. API Request/Response Flow**

```mermaid
sequenceDiagram
    participant M as Mobile App
    participant A as Drupal API
    participant D as H3 Database
    
    M->>A: POST /user/login (credentials)
    A->>A: Validate credentials
    A->>M: Return JWT token
    
    M->>A: GET /api/amisafe/risk-level?h3=<index>
    Note over A: Include JWT in Authorization header
    A->>A: Validate JWT token
    A->>D: Query H3 aggregated data
    D->>A: Return crime statistics
    A->>A: Calculate risk level
    A->>M: Return risk assessment
    
    M->>A: GET /api/amisafe/aggregated?resolution=13
    A->>D: Query H3 hexagon data
    D->>A: Return aggregated crime data
    A->>M: Return hexagon risk data
```

### **7. Offline Data Synchronization Flow**

```mermaid
graph TD
    A[Network Disconnected] --> B[Switch to Offline Mode]
    B --> C[Use Cached Risk Data]
    C --> D[Queue API Requests]
    D --> E[Continue Location Tracking]
    E --> F[Store Events Locally]
    F --> G{Network Restored?}
    G -->|No| H[Continue Offline Operation]
    H --> E
    G -->|Yes| I[Begin Sync Process]
    I --> J[Send Queued Requests]
    J --> K[Update Cache with Fresh Data]
    K --> L[Reconcile Offline Events]
    L --> M[Resume Online Operation]
```

## 🔔 Notification Process Flows

### **8. Push Notification Trigger Flow**

```mermaid
graph TD
    A[Risk Level Change Detected] --> B{Risk Increase Significant?}
    B -->|No| C[Silent Update]
    B -->|Yes| D[Determine Notification Type]
    D --> E{Current Risk Level}
    E -->|Medium| F[Caution Notification]
    E -->|High| G[Warning Notification]
    E -->|Extreme| H[Emergency Notification]
    
    F --> I[Standard Push Notification]
    G --> J[Priority Push Notification]
    H --> K[Critical Alert + Vibration]
    
    I --> L[Log Notification Event]
    J --> L
    K --> L
    L --> M[Update Notification History]
    M --> N[Continue Monitoring]
    C --> N
```

### **9. Emergency Response Flow**

```mermaid
graph TD
    A[Extreme Risk Detected] --> B[Trigger Emergency Protocol]
    B --> C[Send Critical Push Notification]
    C --> D[Vibrate Device]
    D --> E[Play Alert Sound]
    E --> F[Show Emergency Screen]
    F --> G[Display Safety Options]
    G --> H{User Action}
    H -->|Call 911| I[Open Phone Dialer]
    H -->|Get Directions| J[Open Navigation to Safe Area]
    H -->|Contact Emergency Contact| K[Call Designated Contact]
    H -->|Dismiss| L[Continue High Alert Mode]
```

## 📱 User Interface Process Flows

### **10. Home Screen Interaction Flow**

```mermaid
graph TD
    A[Home Screen Loaded] --> B[Display Current Risk Level]
    B --> C[Show Location Name]
    C --> D[Display Quick Stats]
    D --> E[User Interaction]
    E --> F{Action Selected}
    F -->|View Map| G[Navigate to Map Screen]
    F -->|View Statistics| H[Navigate to Statistics Screen]
    F -->|Safety Check| I[Navigate to Safety Screen]
    F -->|Emergency| J[Open Emergency Options]
    F -->|Refresh| K[Force Location Update]
    
    K --> L[Get Current Location]
    L --> M[Query Risk Data]
    M --> N[Update Home Screen]
    N --> E
```

### **11. Map Screen Interaction Flow**

```mermaid
graph TD
    A[Map Screen Loaded] --> B[Initialize Map Component]
    B --> C[Show User Location]
    C --> D[Load H3 Hexagon Overlays]
    D --> E[Display Crime Data]
    E --> F[User Map Interaction]
    F --> G{Interaction Type}
    G -->|Pan/Zoom| H[Update Visible Area]
    G -->|Tap Hexagon| I[Show Hexagon Details]
    G -->|Toggle Layer| J[Update Map Layers]
    G -->|Search Location| K[Geocode Address]
    
    H --> L[Load New Area Data]
    I --> M[Display Crime Statistics]
    J --> N[Refresh Map Display]
    K --> O[Center Map on Location]
    
    L --> E
    M --> F
    N --> F
    O --> E
```

## 🔄 Data Synchronization Process Flows

### **12. Cache Management Flow**

```mermaid
graph TD
    A[App Data Request] --> B{Data in Cache?}
    B -->|Yes| C{Cache Still Valid?}
    C -->|Yes| D[Return Cached Data]
    C -->|No| E[Mark Cache as Expired]
    B -->|No| E
    E --> F[Fetch from API]
    F --> G{API Request Success?}
    G -->|Yes| H[Update Cache]
    G -->|No| I{Expired Cache Available?}
    I -->|Yes| J[Return Expired Cache with Warning]
    I -->|No| K[Return Error State]
    H --> L[Return Fresh Data]
    D --> M[Continue Operation]
    J --> M
    K --> M
    L --> M
```

### **13. User Preference Sync Flow**

```mermaid
graph TD
    A[User Changes Preferences] --> B[Validate Input]
    B --> C{Validation Success?}
    C -->|No| D[Show Validation Error]
    D --> A
    C -->|Yes| E[Update Local Storage]
    E --> F[Prepare API Sync]
    F --> G{Network Available?}
    G -->|Yes| H[Sync to Server]
    G -->|No| I[Queue for Later Sync]
    H --> J{Sync Success?}
    J -->|Yes| K[Confirm Preferences Saved]
    J -->|No| L[Show Sync Error]
    I --> M[Continue with Local Changes]
    K --> N[Update UI State]
    L --> N
    M --> N
```

## 🛡️ Security Process Flows

### **14. JWT Token Management Flow**

```mermaid
graph TD
    A[API Request Initiated] --> B[Check JWT Token]
    B --> C{Token Exists?}
    C -->|No| D[Redirect to Login]
    C -->|Yes| E{Token Valid & Not Expired?}
    E -->|No| F[Attempt Token Refresh]
    F --> G{Refresh Success?}
    G -->|Yes| H[Update Stored Token]
    G -->|No| D
    E -->|Yes| I[Add Token to Request Header]
    H --> I
    I --> J[Send API Request]
    J --> K{Response Status}
    K -->|200 OK| L[Process Response]
    K -->|401 Unauthorized| M[Clear Token & Redirect to Login]
    K -->|Other Error| N[Handle Error Appropriately]
```

### **15. Privacy & Data Protection Flow**

```mermaid
graph TD
    A[User Data Collection] --> B[Check Privacy Settings]
    B --> C{Location Sharing Enabled?}
    C -->|No| D[Use Anonymized Data Only]
    C -->|Yes| E{Precise Location Allowed?}
    E -->|No| F[Use Approximate Location]
    E -->|Yes| G[Use Precise Location]
    
    D --> H[Process with Privacy Protection]
    F --> H
    G --> I[Process with Full Accuracy]
    
    H --> J[Store Data Locally Only]
    I --> K{Data Sharing Consent?}
    K -->|No| J
    K -->|Yes| L[Allow API Data Sharing]
    
    J --> M[Update UI with Limited Features]
    L --> N[Update UI with Full Features]
```

This comprehensive process flow documentation covers all major user interactions, data flows, and system processes within the AmISafe mobile application, ensuring clear understanding of how the application operates from user registration through ongoing safety monitoring.