# AmISafe Mobile Application - API Integration Guide

## 🔌 API Architecture Overview

The AmISafe mobile application integrates with the Drupal-based crime monitoring system through a comprehensive REST API. The API provides access to ultra-precise H3 geospatial crime data, user authentication, and real-time risk assessment capabilities.

## 🌐 Base API Configuration

### **Endpoints**
```typescript
const API_CONFIG = {
  BASE_URL: 'https://stlouisintegration.com',
  API_PREFIX: '/api/amisafe',
  AUTH_PREFIX: '/user',
  TIMEOUT: 30000, // 30 seconds
  RETRY_ATTEMPTS: 3,
  CACHE_DURATION: 30 * 60 * 1000, // 30 minutes
};

// Full endpoint URLs
const ENDPOINTS = {
  // Authentication
  LOGIN: `${API_CONFIG.BASE_URL}${API_CONFIG.AUTH_PREFIX}/login`,
  REGISTER: `${API_CONFIG.BASE_URL}${API_CONFIG.AUTH_PREFIX}/register`,
  REFRESH: `${API_CONFIG.BASE_URL}${API_CONFIG.AUTH_PREFIX}/token/refresh`,
  PROFILE: `${API_CONFIG.BASE_URL}${API_CONFIG.AUTH_PREFIX}/profile`,
  
  // Crime Data
  RISK_LEVEL: `${API_CONFIG.BASE_URL}${API_CONFIG.API_PREFIX}/risk-level`,
  AGGREGATED: `${API_CONFIG.BASE_URL}${API_CONFIG.API_PREFIX}/aggregated`,
  INCIDENTS: `${API_CONFIG.BASE_URL}${API_CONFIG.API_PREFIX}/incidents`,
  HEXAGON_INCIDENTS: (h3Index: string) => `${API_CONFIG.BASE_URL}${API_CONFIG.API_PREFIX}/hexagon/${h3Index}/incidents`, // 🆕 H3:13 granular access
  HOTSPOTS: `${API_CONFIG.BASE_URL}${API_CONFIG.API_PREFIX}/hotspots`,
  
  // System Information
  SYSTEM_STATS: `${API_CONFIG.BASE_URL}${API_CONFIG.API_PREFIX}/system-stats`,
  CRIME_TYPES: `${API_CONFIG.BASE_URL}${API_CONFIG.API_PREFIX}/crime-types`,
  DISTRICTS: `${API_CONFIG.BASE_URL}${API_CONFIG.API_PREFIX}/districts`,
};
```

## 🔐 Authentication API

### **User Registration**
```typescript
interface RegistrationRequest {
  mail: string;
  name: string;
  pass: string;
  field_first_name: string;
  field_last_name: string;
  field_phone_number?: string;
  field_emergency_contact?: {
    name: string;
    phone: string;
    relationship: string;
  };
  field_privacy_consent: boolean;
  field_location_consent: boolean;
}

interface RegistrationResponse {
  success: boolean;
  message: string;
  user: {
    uid: number;
    mail: string;
    status: 'pending' | 'active';
  };
  verification_required: boolean;
}

// API Call
async function registerUser(userData: RegistrationRequest): Promise<RegistrationResponse> {
  const response = await fetch(ENDPOINTS.REGISTER, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(userData),
  });
  
  if (!response.ok) {
    throw new Error(`Registration failed: ${response.statusText}`);
  }
  
  return response.json();
}
```

### **User Login**
```typescript
interface LoginRequest {
  name: string; // email address
  pass: string;
}

interface LoginResponse {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  token_type: 'Bearer';
  user: {
    uid: number;
    mail: string;
    name: string;
    roles: string[];
    field_first_name: string;
    field_last_name: string;
    field_phone_number?: string;
    field_location_consent: boolean;
    field_privacy_consent: boolean;
  };
}

// API Call
async function loginUser(credentials: LoginRequest): Promise<LoginResponse> {
  const response = await fetch(ENDPOINTS.LOGIN, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(credentials),
  });
  
  if (!response.ok) {
    const errorData = await response.json();
    throw new Error(errorData.message || 'Login failed');
  }
  
  return response.json();
}
```

### **Token Management**
```typescript
class AuthService {
  private accessToken: string | null = null;
  private refreshToken: string | null = null;
  private tokenExpiry: number = 0;
  
  // Store tokens securely
  async storeTokens(tokens: LoginResponse): Promise<void> {
    this.accessToken = tokens.access_token;
    this.refreshToken = tokens.refresh_token;
    this.tokenExpiry = Date.now() + (tokens.expires_in * 1000);
    
    // Store in secure storage
    await SecureStore.setItemAsync('access_token', tokens.access_token);
    await SecureStore.setItemAsync('refresh_token', tokens.refresh_token);
    await SecureStore.setItemAsync('token_expiry', this.tokenExpiry.toString());
  }
  
  // Get valid token (refresh if needed)
  async getValidToken(): Promise<string | null> {
    if (!this.accessToken) {
      await this.loadStoredTokens();
    }
    
    if (this.isTokenExpired()) {
      await this.refreshAccessToken();
    }
    
    return this.accessToken;
  }
  
  // Refresh token
  private async refreshAccessToken(): Promise<void> {
    if (!this.refreshToken) {
      throw new Error('No refresh token available');
    }
    
    const response = await fetch(ENDPOINTS.REFRESH, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${this.refreshToken}`,
      },
    });
    
    if (!response.ok) {
      await this.clearTokens();
      throw new Error('Token refresh failed');
    }
    
    const newTokens = await response.json();
    await this.storeTokens(newTokens);
  }
  
  private isTokenExpired(): boolean {
    return Date.now() >= this.tokenExpiry - 60000; // 1 minute buffer
  }
}
```

## 🗺️ Crime Data API

### **Risk Level Assessment**
```typescript
interface RiskLevelRequest {
  h3_index: string;
  include_neighbors?: boolean;
  time_window?: '1h' | '24h' | '7d' | '30d';
  resolution?: number;
}

interface RiskLevelResponse {
  risk_level: 'low' | 'medium' | 'high' | 'extreme';
  risk_score: number; // 0-100
  h3_index: string;
  factors: string[];
  incident_count: number;
  trend: 'increasing' | 'stable' | 'decreasing';
  confidence: number; // 0-1
  last_updated: string; // ISO 8601
  neighbors?: Record<string, {
    risk_level: string;
    risk_score: number;
  }>;
  temporal_analysis?: {
    hourly_risk: number[];
    peak_hours: number[];
    safest_hours: number[];
  };
}

// API Call
async function getRiskLevel(params: RiskLevelRequest): Promise<RiskLevelResponse> {
  const token = await authService.getValidToken();
  const queryParams = new URLSearchParams({
    h3_index: params.h3_index,
    include_neighbors: params.include_neighbors?.toString() || 'true',
    time_window: params.time_window || '24h',
    resolution: params.resolution?.toString() || '13',
  });
  
  const response = await fetch(`${ENDPOINTS.RISK_LEVEL}?${queryParams}`, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });
  
  if (!response.ok) {
    throw new Error(`Risk level request failed: ${response.statusText}`);
  }
  
  return response.json();
}
```

### **H3 Aggregated Data**
```typescript
interface AggregatedDataRequest {
  resolution: number; // 6-13
  bounds?: string; // 'lat1,lng1,lat2,lng2'
  limit?: number;
  offset?: number;
  crime_types?: string[];
  min_incidents?: number;
}

interface AggregatedDataResponse {
  hexagons: Array<{
    h3_index: string;
    h3_resolution: number;
    incident_count: number;
    risk_level: string;
    center_latitude: number;
    center_longitude: number;
    crime_types: Record<string, number>;
    latest_incident: string;
    incidents_last_30_days: number;
  }>;
  meta: {
    resolution: number;
    precision_level: string;
    hexagon_area: string;
    description: string;
    is_ultra_precision: boolean;
    data_source: string;
    bounds?: string;
    count: number;
    limit: number;
  };
}

// API Call
async function getAggregatedData(params: AggregatedDataRequest): Promise<AggregatedDataResponse> {
  const token = await authService.getValidToken();
  const queryParams = new URLSearchParams();
  
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined) {
      if (Array.isArray(value)) {
        queryParams.append(key, value.join(','));
      } else {
        queryParams.append(key, value.toString());
      }
    }
  });
  
  const response = await fetch(`${ENDPOINTS.AGGREGATED}?${queryParams}`, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });
  
  if (!response.ok) {
    throw new Error(`Aggregated data request failed: ${response.statusText}`);
  }
  
  return response.json();
}
```

### **Individual Incidents**
```typescript
interface IncidentsRequest {
  bounds?: string; // 'lat1,lng1,lat2,lng2'
  crime_types?: string[];
  start_date?: string; // ISO 8601
  end_date?: string;   // ISO 8601
  limit?: number;
  offset?: number;
  district?: string;
}

interface IncidentsResponse {
  incidents: Array<{
    id: number;
    lat: number;
    lng: number;
    h3_index: string;
    ucr_general: string;
    text_general_code: string;
    dispatch_date_time: string;
    location_block: string;
    dc_dist: string;
    severity_level: number;
  }>;
  meta: {
    total: number;
    page: number;
    limit: number;
    filters: Record<string, any>;
  };
}

// API Call
async function getIncidents(params: IncidentsRequest): Promise<IncidentsResponse> {
  const token = await authService.getValidToken();
  const queryParams = new URLSearchParams();
  
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined) {
      if (Array.isArray(value)) {
        queryParams.append(key, value.join(','));
      } else {
        queryParams.append(key, value.toString());
      }
    }
  });
  
  const response = await fetch(`${ENDPOINTS.INCIDENTS}?${queryParams}`, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });
  
  if (!response.ok) {
    throw new Error(`Incidents request failed: ${response.statusText}`);
  }
  
  return response.json();
}
```

### **🆕 Hexagon Incident Details (H3:13 Granular Access)**
```typescript
interface HexagonIncidentsRequest {
  h3_index: string;       // H3:13 hexagon identifier
  crime_types?: string[]; // Filter by crime types (e.g., ['600', '700'])
  districts?: string[];   // Filter by police districts
  time_periods?: string[]; // Filter by time periods ('morning', 'evening', etc.)
  limit?: number;         // Limit results for performance (default: 500)
}

interface HexagonIncidentsResponse {
  hexagon_summary: {
    h3_index: string;
    h3_resolution: number;
    total_incidents: number;
    returned_incidents: number;
    filters_applied: Record<string, any>;
  };
  incidents: Array<{
    incident_id: string;
    ucr_general: string;
    crime_description: string;
    incident_datetime: string;
    dc_dist: string;
    lat: number;
    lng: number;
    incident_month: number;
    incident_hour: number;
  }>;
}

// API Call
async function getHexagonIncidents(params: HexagonIncidentsRequest): Promise<HexagonIncidentsResponse> {
  const token = await authService.getValidToken();
  const queryParams = new URLSearchParams();
  
  // Add filters as query parameters
  if (params.crime_types?.length) {
    queryParams.append('crime_types', params.crime_types.join(','));
  }
  if (params.districts?.length) {
    queryParams.append('districts', params.districts.join(','));
  }
  if (params.time_periods?.length) {
    queryParams.append('time_periods', params.time_periods.join(','));
  }
  if (params.limit) {
    queryParams.append('limit', params.limit.toString());
  }
  
  const endpoint = `${API_CONFIG.BASE_URL}${API_CONFIG.API_PREFIX}/hexagon/${params.h3_index}/incidents`;
  const url = queryParams.toString() ? `${endpoint}?${queryParams}` : endpoint;
  
  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });
  
  if (!response.ok) {
    throw new Error(`Hexagon incidents request failed: ${response.statusText}`);
  }
  
  return response.json();
}

// Usage Example
async function analyzeRoomLevelCrime(h3Index: string) {
  try {
    const hexagonData = await getHexagonIncidents({
      h3_index: h3Index,
      crime_types: ['600', '700'], // Theft and robbery
      limit: 100
    });
    
    console.log(`Found ${hexagonData.hexagon_summary.total_incidents} incidents in hexagon`);
    console.log(`Returned ${hexagonData.incidents.length} filtered incidents`);
    
    return hexagonData.incidents;
  } catch (error) {
    console.error('Failed to get hexagon incidents:', error);
    throw error;
  }
}
```

## 📊 System Information API

### **System Statistics**
```typescript
interface SystemStatsResponse {
  system_info: {
    version: string;
    last_updated: string;
    h3_library_version: string;
  };
  data_statistics: {
    total_crime_incidents: number;
    h3_aggregation_count: number;
    supported_resolutions: number[];
    data_freshness: string;
    coverage_area: string;
  };
  resolution_breakdown: Array<{
    resolution: number;
    hexagon_count: number;
    area_coverage: string;
    precision_level: string;
  }>;
  performance_metrics: {
    average_response_time: number;
    cache_hit_rate: number;
    api_uptime: number;
  };
}

// API Call
async function getSystemStats(): Promise<SystemStatsResponse> {
  const token = await authService.getValidToken();
  
  const response = await fetch(ENDPOINTS.SYSTEM_STATS, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });
  
  if (!response.ok) {
    throw new Error(`System stats request failed: ${response.statusText}`);
  }
  
  return response.json();
}
```

## 🔄 Request Management & Caching

### **API Service Class**
```typescript
class ApiService {
  private cache = new Map<string, CacheEntry>();
  private requestQueue = new Map<string, Promise<any>>();
  
  // Generic API request with caching
  async request<T>(
    endpoint: string, 
    options: RequestOptions = {},
    cacheKey?: string,
    cacheDuration: number = API_CONFIG.CACHE_DURATION
  ): Promise<T> {
    // Check cache first
    if (cacheKey && this.isCacheValid(cacheKey)) {
      return this.cache.get(cacheKey)!.data;
    }
    
    // Prevent duplicate requests
    if (this.requestQueue.has(endpoint)) {
      return this.requestQueue.get(endpoint)!;
    }
    
    // Make request
    const requestPromise = this.makeRequest<T>(endpoint, options);
    this.requestQueue.set(endpoint, requestPromise);
    
    try {
      const result = await requestPromise;
      
      // Cache result
      if (cacheKey) {
        this.cache.set(cacheKey, {
          data: result,
          timestamp: Date.now(),
          duration: cacheDuration,
        });
      }
      
      return result;
    } finally {
      this.requestQueue.delete(endpoint);
    }
  }
  
  private async makeRequest<T>(endpoint: string, options: RequestOptions): Promise<T> {
    const token = await authService.getValidToken();
    
    const config: RequestInit = {
      method: options.method || 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`,
        ...options.headers,
      },
      timeout: API_CONFIG.TIMEOUT,
    };
    
    if (options.body) {
      config.body = JSON.stringify(options.body);
    }
    
    let attempts = 0;
    while (attempts < API_CONFIG.RETRY_ATTEMPTS) {
      try {
        const response = await fetch(endpoint, config);
        
        if (response.status === 401) {
          // Token expired, refresh and retry
          await authService.refreshAccessToken();
          config.headers!['Authorization'] = `Bearer ${await authService.getValidToken()}`;
          continue;
        }
        
        if (!response.ok) {
          throw new Error(`API request failed: ${response.statusText}`);
        }
        
        return response.json();
      } catch (error) {
        attempts++;
        if (attempts >= API_CONFIG.RETRY_ATTEMPTS) {
          throw error;
        }
        
        // Exponential backoff
        await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempts) * 1000));
      }
    }
    
    throw new Error('Max retry attempts exceeded');
  }
  
  private isCacheValid(key: string): boolean {
    const entry = this.cache.get(key);
    if (!entry) return false;
    
    return Date.now() - entry.timestamp < entry.duration;
  }
  
  // Clear expired cache entries
  clearExpiredCache(): void {
    const now = Date.now();
    for (const [key, entry] of this.cache.entries()) {
      if (now - entry.timestamp >= entry.duration) {
        this.cache.delete(key);
      }
    }
  }
}
```

## 🚨 Error Handling

### **API Error Types**
```typescript
interface ApiError {
  code: string;
  message: string;
  details?: any;
  timestamp: string;
}

enum ApiErrorCode {
  NETWORK_ERROR = 'NETWORK_ERROR',
  AUTHENTICATION_ERROR = 'AUTHENTICATION_ERROR',
  AUTHORIZATION_ERROR = 'AUTHORIZATION_ERROR',
  VALIDATION_ERROR = 'VALIDATION_ERROR',
  RATE_LIMIT_ERROR = 'RATE_LIMIT_ERROR',
  SERVER_ERROR = 'SERVER_ERROR',
  TIMEOUT_ERROR = 'TIMEOUT_ERROR',
}

class ApiErrorHandler {
  static handle(error: any): ApiError {
    if (error.name === 'TimeoutError') {
      return {
        code: ApiErrorCode.TIMEOUT_ERROR,
        message: 'Request timed out. Please check your connection.',
        timestamp: new Date().toISOString(),
      };
    }
    
    if (error.status === 401) {
      return {
        code: ApiErrorCode.AUTHENTICATION_ERROR,
        message: 'Authentication required. Please log in again.',
        timestamp: new Date().toISOString(),
      };
    }
    
    if (error.status === 403) {
      return {
        code: ApiErrorCode.AUTHORIZATION_ERROR,
        message: 'Access denied. Insufficient permissions.',
        timestamp: new Date().toISOString(),
      };
    }
    
    if (error.status === 429) {
      return {
        code: ApiErrorCode.RATE_LIMIT_ERROR,
        message: 'Too many requests. Please wait before trying again.',
        timestamp: new Date().toISOString(),
      };
    }
    
    if (error.status >= 500) {
      return {
        code: ApiErrorCode.SERVER_ERROR,
        message: 'Server error. Please try again later.',
        timestamp: new Date().toISOString(),
      };
    }
    
    return {
      code: ApiErrorCode.NETWORK_ERROR,
      message: error.message || 'An unexpected error occurred.',
      timestamp: new Date().toISOString(),
    };
  }
}
```

## 📱 Mobile-Specific Considerations

### **Network State Management**
```typescript
class NetworkManager {
  private isOnline: boolean = true;
  private listeners: ((online: boolean) => void)[] = [];
  
  constructor() {
    // Listen for network state changes
    NetInfo.addEventListener(state => {
      this.isOnline = state.isConnected ?? false;
      this.notifyListeners();
    });
  }
  
  isConnected(): boolean {
    return this.isOnline;
  }
  
  onNetworkChange(callback: (online: boolean) => void): void {
    this.listeners.push(callback);
  }
  
  private notifyListeners(): void {
    this.listeners.forEach(listener => listener(this.isOnline));
  }
}
```

### **Offline API Handling**
```typescript
class OfflineApiService extends ApiService {
  private offlineQueue: QueuedRequest[] = [];
  
  async request<T>(endpoint: string, options: RequestOptions = {}): Promise<T> {
    if (!networkManager.isConnected()) {
      // Check if data is available in cache
      const cacheKey = this.generateCacheKey(endpoint, options);
      if (this.isCacheValid(cacheKey)) {
        return this.cache.get(cacheKey)!.data;
      }
      
      // Queue request for when online
      this.queueRequest(endpoint, options);
      throw new Error('Network unavailable. Request queued for retry.');
    }
    
    return super.request(endpoint, options);
  }
  
  private queueRequest(endpoint: string, options: RequestOptions): void {
    this.offlineQueue.push({
      endpoint,
      options,
      timestamp: Date.now(),
    });
  }
  
  async processOfflineQueue(): Promise<void> {
    if (!networkManager.isConnected() || this.offlineQueue.length === 0) {
      return;
    }
    
    const queue = [...this.offlineQueue];
    this.offlineQueue = [];
    
    for (const request of queue) {
      try {
        await this.request(request.endpoint, request.options);
      } catch (error) {
        console.error('Failed to process queued request:', error);
        // Re-queue if still relevant
        if (Date.now() - request.timestamp < 300000) { // 5 minutes
          this.offlineQueue.push(request);
        }
      }
    }
  }
}
```

This comprehensive API integration guide provides all necessary components for seamless communication between the AmISafe mobile application and the Drupal-based crime monitoring system, ensuring reliable data access and robust error handling across all network conditions.