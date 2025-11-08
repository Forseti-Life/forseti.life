# Drupal Modules for AmISafe Mobile App Development

## 🎯 Essential Drupal Modules for Mobile App Development

Based on your AmISafe mobile application requirements, here are the **must-have** Drupal modules that will significantly simplify your server-side development:

## 🔐 **Authentication & API Modules**

### **1. JSON:API (Core Module) - HIGHEST PRIORITY**
**Status**: ✅ **Available in Drupal Core**  
**Installation**: `drush en jsonapi`

**Why Essential for AmISafe Mobile:**
- **Zero Configuration API**: Automatically exposes all entities (users, content) as REST endpoints
- **Standardized JSON Format**: Industry-standard JSON:API specification
- **Built-in Relationships**: Handle user profile relationships automatically
- **Query Parameters**: Built-in filtering, sorting, pagination for crime data
- **CORS Support**: Perfect for mobile app cross-origin requests

**Mobile App Benefits:**
```typescript
// Automatic endpoints for user management
GET /jsonapi/user/user/{uuid}           // Get user profile
PATCH /jsonapi/user/user/{uuid}         // Update user profile
POST /jsonapi/user/user                 // Register new user

// Custom AmISafe content types automatically exposed
GET /jsonapi/node/crime_report          // Get crime reports
GET /jsonapi/taxonomy_term/risk_level   // Get risk levels
```

### **2. RESTful Web Services (Core Module)**
**Status**: ✅ **Available in Drupal Core**  
**Installation**: `drush en rest serialization`

**Why Useful for AmISafe:**
- **Custom Endpoints**: Your existing AmISafe API can be enhanced
- **Flexible Authentication**: Supports multiple auth methods
- **Custom Resources**: Perfect for H3 geospatial endpoints

### **3. Simple OAuth (Contributed Module) - RECOMMENDED**
**Installation**: `composer require drupal/simple_oauth && drush en simple_oauth`

**Why Perfect for Mobile:**
```php
// OAuth 2.0 flow - industry standard for mobile apps
POST /oauth/token
{
  "grant_type": "password",
  "client_id": "your_mobile_app",
  "client_secret": "your_secret",
  "username": "user@example.com",
  "password": "password123"
}

// Response with tokens
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refresh_token": "def502004a7c5d...",
  "expires_in": 3600,
  "token_type": "Bearer"
}
```

**Mobile App Integration:**
- **JWT Tokens**: Perfect for React Native secure storage
- **Refresh Tokens**: Automatic token renewal
- **Scopes**: Granular permissions for different app features
- **Device Registration**: Support for push notifications

### **4. JWT Authentication (Alternative)**
**Installation**: `composer require drupal/jwt && drush en jwt`

**Benefits:**
- **Lightweight**: Simpler than OAuth for basic authentication
- **Stateless**: Perfect for mobile app architecture
- **Custom Claims**: Add user-specific data to tokens

## 👤 **User Management Modules**

### **5. Registration with Approval (if needed)**
**Installation**: `composer require drupal/registration && drush en registration`

**Features for AmISafe:**
- **Email Verification**: Automatic email confirmation
- **Admin Approval**: Optional manual approval process
- **Custom Registration Fields**: Emergency contacts, privacy settings

### **6. User External Auth**
**Installation**: `composer require drupal/externalauth && drush en externalauth`

**Use Cases:**
- **Social Login**: Facebook, Google, Apple Sign-In
- **SSO Integration**: If connecting to external systems
- **Third-party Authentication**: Emergency services integration

## 📱 **Mobile-Specific Modules**

### **7. CORS (Cross-Origin Resource Sharing)**
**Installation**: `composer require drupal/cors && drush en cors`

**Critical for Mobile:**
```yaml
# cors.settings.yml
cors:
  enabled: true
  allowedHeaders: ['*']
  allowedMethods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH']
  allowedOrigins: ['*']  # Configure for your mobile app domains
  supportsCredentials: true
```

### **8. OpenAPI (API Documentation)**
**Installation**: `composer require drupal/openapi && drush en openapi`

**Benefits:**
- **Automatic Documentation**: Self-generating API docs
- **JSON:API Integration**: Documents all your endpoints
- **Testing Interface**: Built-in API testing tools

## 🔔 **Push Notification Modules**

### **9. Web Push Notifications**
**Installation**: `composer require drupal/webpush && drush en webpush`

**Features:**
- **Device Registration**: Store push notification tokens
- **Targeted Notifications**: Send to specific users
- **Integration Ready**: Works with Firebase Cloud Messaging

### **10. Message (Core Alternative)**
**Status**: ✅ **Available in Drupal Core**
**Installation**: `drush en message message_ui`

**Use for AmISafe:**
- **Activity Logging**: Track user safety events
- **Notification Templates**: Standardized alert messages
- **Bulk Notifications**: Send safety alerts to multiple users

## 🗄️ **Data & Field Modules**

### **11. Field Permissions**
**Installation**: `composer require drupal/field_permissions && drush en field_permissions`

**Privacy Features:**
- **Profile Privacy**: Control what fields mobile app can access
- **Location Privacy**: Granular location sharing permissions
- **Emergency Data**: Separate permissions for emergency access

### **12. Geocoder (for Location Features)**
**Installation**: `composer require drupal/geocoder && drush en geocoder`

**Location Features:**
- **Address Validation**: Validate user addresses
- **Coordinate Conversion**: Convert addresses to lat/lng
- **Integration**: Works with your H3 geospatial system

## 🔧 **Recommended Module Installation Commands**

```bash
# Navigate to your Drupal site
cd /workspaces/stlouisintegration.com/sites/stlouisintegration

# Install essential API modules
composer require drupal/simple_oauth drupal/cors drupal/openapi
drush en jsonapi rest serialization simple_oauth cors openapi

# Install user management enhancements
composer require drupal/field_permissions drupal/webpush
drush en field_permissions webpush message message_ui

# Install development helpers
composer require drupal/devel_generate drupal/restui
drush en devel_generate restui

# Clear cache
drush cr
```

## 📱 **AmISafe Mobile Integration Strategy**

### **Phase 1: Core Authentication (JSON:API + Simple OAuth)**
```typescript
// Mobile app authentication flow
class AuthService {
  async login(email: string, password: string) {
    // OAuth token request
    const response = await fetch('/oauth/token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        grant_type: 'password',
        client_id: 'amisafe_mobile',
        username: email,
        password: password
      })
    });
    
    const tokens = await response.json();
    await this.storeTokens(tokens);
    return tokens;
  }
}
```

### **Phase 2: User Profile Management (JSON:API)**
```typescript
// Automatic user profile endpoints
class UserService {
  async getUserProfile(userId: string) {
    return fetch(`/jsonapi/user/user/${userId}`, {
      headers: { 'Authorization': `Bearer ${token}` }
    });
  }
  
  async updateEmergencyContacts(userId: string, contacts: EmergencyContact[]) {
    return fetch(`/jsonapi/user/user/${userId}`, {
      method: 'PATCH',
      headers: { 
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/vnd.api+json'
      },
      body: JSON.stringify({
        data: {
          type: 'user--user',
          id: userId,
          attributes: {
            field_emergency_contacts: contacts
          }
        }
      })
    });
  }
}
```

### **Phase 3: Push Notifications Integration**
```php
// Custom AmISafe notification service
class AmISafeNotificationService {
  public function sendRiskAlert($user_id, $risk_level, $location) {
    $message = [
      'title' => 'Safety Alert',
      'body' => "Risk level: {$risk_level} at your location",
      'data' => [
        'risk_level' => $risk_level,
        'location' => $location,
        'action_required' => true
      ]
    ];
    
    // Send via webpush module
    $this->webpushService->sendNotification($user_id, $message);
  }
}
```

## 🚀 **Development Benefits Summary**

### **With These Modules, You Get:**

✅ **Zero Custom Auth Code**: OAuth handles all authentication  
✅ **Automatic REST APIs**: JSON:API exposes everything automatically  
✅ **Mobile-Ready**: CORS, JWT tokens, push notifications built-in  
✅ **User Management**: Registration, profiles, permissions handled  
✅ **Documentation**: Self-documenting APIs with OpenAPI  
✅ **Security**: Industry-standard authentication and authorization  

### **Your Development Time Saved:**
- **Authentication System**: ~2-3 weeks → ~2-3 days
- **User Registration**: ~1-2 weeks → ~1-2 days  
- **API Development**: ~3-4 weeks → ~1 week
- **Push Notifications**: ~1-2 weeks → ~2-3 days
- **Documentation**: ~1 week → Automatic

### **Total Time Savings: ~8-12 weeks reduced to ~1-2 weeks**

## 🎯 **Next Steps**

1. **Install Core Modules**: Start with JSON:API, REST, and Simple OAuth
2. **Configure Authentication**: Set up OAuth client for your mobile app
3. **Test API Endpoints**: Use the built-in API testing tools
4. **Integrate with Mobile**: Update your React Native app to use OAuth
5. **Add Push Notifications**: Implement the webpush module

These modules will transform your mobile app development from building everything from scratch to simply configuring proven, secure, industry-standard solutions! 🚀