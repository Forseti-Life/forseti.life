/**
 * Background Location Monitoring Service
 *
 * Monitors user location in the background, tracks H3 hexagon changes,
 * and sends notifications when entering high-crime areas (z-score >= 2)
 */

import Geolocation from 'react-native-geolocation-service';
import { Platform, AppState, AppStateStatus, NativeModules } from 'react-native';
import { h3 } from 'h3-js';
// import NotificationService from '../notifications/NotificationService'; // Temporarily disabled
import StorageService from '../storage/StorageService';
import axios from 'axios';

const { LocationServiceModule } = NativeModules;

interface LocationCoords {
  latitude: number;
  longitude: number;
  accuracy: number;
  timestamp: number;
}

interface H3HexagonData {
  h3_index: string;
  incident_count: number;
  incident_z_score: number;
  risk_level: string;
  resolution: number;
}

class BackgroundLocationService {
  private static instance: BackgroundLocationService;
  private watchId: number | null = null;
  private isMonitoring: boolean = false;
  private currentH3Index: string | null = null;
  private lastNotificationTime: number = 0;
  private notificationCooldown: number = 300000; // 5 minutes in milliseconds (default)
  private zScoreThreshold: number = 2.0; // Default threshold

  // Configuration
  private readonly H3_RESOLUTION = 11; // ~700m hexagons for notifications
  private readonly API_BASE_URL = 'https://forseti.life';
  private readonly UPDATE_INTERVAL = 60000; // Check location every 60 seconds
  private readonly DISTANCE_FILTER = 50; // meters - minimum movement before update

  private constructor() {
    this.setupAppStateListener();
  }

  public static getInstance(): BackgroundLocationService {
    if (!BackgroundLocationService.instance) {
      BackgroundLocationService.instance = new BackgroundLocationService();
    }
    return BackgroundLocationService.instance;
  }

  /**
   * Setup app state listener to maintain monitoring when app is backgrounded
   */
  private setupAppStateListener(): void {
    AppState.addEventListener('change', (nextAppState: AppStateStatus) => {
      if (nextAppState === 'background' && this.isMonitoring) {
        console.log('📱 App backgrounded - maintaining location monitoring');
      } else if (nextAppState === 'active' && this.isMonitoring) {
        console.log('📱 App foregrounded - location monitoring active');
      }
    });
  }

  /**
   * Start background location monitoring
   */
  public async startMonitoring(): Promise<void> {
    if (this.isMonitoring) {
      console.log('⚠️ Background monitoring already active');
      return;
    }

    try {
      // Load user settings from storage
      await this.loadUserSettings();

      // Start Android foreground service if available
      if (Platform.OS === 'android' && LocationServiceModule) {
        try {
          await LocationServiceModule.startLocationService();
          console.log('✅ Android foreground service started');
        } catch (error) {
          console.error('Failed to start Android foreground service:', error);
          throw new Error('Failed to start location service. Please try again.');
        }
      }

      // Initialize notification service
      // await NotificationService.initialize(); // Temporarily disabled

      // Save monitoring state
      await StorageService.setItem('background_monitoring_enabled', true);

      this.isMonitoring = true;

      // Start watching location
      this.watchId = Geolocation.watchPosition(
        position => this.handleLocationUpdate(position.coords),
        error => this.handleLocationError(error),
        {
          enableHighAccuracy: true,
          distanceFilter: this.DISTANCE_FILTER,
          interval: this.UPDATE_INTERVAL,
          fastestInterval: this.UPDATE_INTERVAL / 2,
          showLocationDialog: true,
          forceRequestLocation: true,
          forceLocationManager: false,
          showsBackgroundLocationIndicator: true, // iOS
          pausesLocationUpdatesAutomatically: false, // iOS
        }
      );

      console.log('✅ Background location monitoring started');
      console.log(
        `📍 Monitoring H3 Resolution ${this.H3_RESOLUTION} with z-score threshold >= ${this.zScoreThreshold}`
      );
    } catch (error) {
      console.error('❌ Failed to start background monitoring:', error);
      this.isMonitoring = false;
      throw error;
    }
  }

  /**
   * Load user settings from storage
   */
  private async loadUserSettings(): Promise<void> {
    try {
      const threshold = await StorageService.getItem('z_score_threshold');
      const cooldown = await StorageService.getItem('notification_cooldown');

      if (threshold !== null) {
        this.zScoreThreshold = threshold;
      }

      if (cooldown !== null) {
        this.notificationCooldown = cooldown * 60000; // Convert minutes to milliseconds
      }

      console.log(
        `⚙️ Settings loaded - Z-Score: ${this.zScoreThreshold}, Cooldown: ${cooldown || 5}min`
      );
    } catch (error) {
      console.warn('Could not load user settings, using defaults:', error);
    }
  }

  /**
   * Stop background location monitoring
   */
  public async stopMonitoring(): Promise<void> {
    if (!this.isMonitoring) {
      return;
    }

    if (this.watchId !== null) {
      Geolocation.clearWatch(this.watchId);
      this.watchId = null;
    }

    // Stop Android foreground service if available
    if (Platform.OS === 'android' && LocationServiceModule) {
      try {
        await LocationServiceModule.stopLocationService();
        console.log('✅ Android foreground service stopped');
      } catch (error) {
        console.error('Failed to stop Android foreground service:', error);
      }
    }

    this.isMonitoring = false;
    this.currentH3Index = null;

    // Save monitoring state
    await StorageService.setItem('background_monitoring_enabled', false);

    console.log('🛑 Background location monitoring stopped');
  }

  /**
   * Handle location updates
   */
  private async handleLocationUpdate(coords: any): Promise<void> {
    try {
      const location: LocationCoords = {
        latitude: coords.latitude,
        longitude: coords.longitude,
        accuracy: coords.accuracy,
        timestamp: Date.now(),
      };

      // Convert to H3 index
      const h3Index = h3.latLngToCell(location.latitude, location.longitude, this.H3_RESOLUTION);

      // Check if we've moved to a new hexagon
      if (h3Index !== this.currentH3Index) {
        console.log(`📍 Moved to new H3:${this.H3_RESOLUTION} hexagon: ${h3Index}`);

        const previousH3 = this.currentH3Index;
        this.currentH3Index = h3Index;

        // Fetch crime data for new hexagon
        await this.checkHexagonSafety(h3Index, location, previousH3);
      }
    } catch (error) {
      console.error('Error handling location update:', error);
    }
  }

  /**
   * Check safety of current hexagon and send notification if dangerous
   */
  private async checkHexagonSafety(
    h3Index: string,
    location: LocationCoords,
    previousH3: string | null
  ): Promise<void> {
    try {
      // Check notification cooldown
      const now = Date.now();
      if (now - this.lastNotificationTime < this.notificationCooldown) {
        console.log('⏰ Notification cooldown active, skipping check');
        return;
      }

      // Fetch hexagon data from API
      const hexagonData = await this.fetchHexagonData(h3Index);

      if (!hexagonData) {
        console.log('ℹ️ No crime data available for this hexagon');
        return;
      }

      // Check if z-score meets threshold for notification
      const zScore = hexagonData.incident_z_score || 0;

      if (zScore >= this.zScoreThreshold) {
        await this.sendDangerNotification(hexagonData, location);
        this.lastNotificationTime = now;
      } else {
        console.log(
          `✅ Safe area - z-score: ${zScore.toFixed(2)} (threshold: ${this.zScoreThreshold})`
        );
      }

      // Save location history
      await this.saveLocationHistory(h3Index, location, zScore);
    } catch (error) {
      console.error('Error checking hexagon safety:', error);
    }
  }

  /**
   * Fetch hexagon crime data from API
   */
  private async fetchHexagonData(h3Index: string): Promise<H3HexagonData | null> {
    try {
      const response = await axios.get(`${this.API_BASE_URL}/api/amisafe/aggregated`, {
        params: {
          resolution: this.H3_RESOLUTION,
          h3_index: h3Index,
          format: 'json',
        },
        timeout: 10000,
      });

      if (response.data && response.data.hexagons && response.data.hexagons.length > 0) {
        const hexagon = response.data.hexagons[0];
        return {
          h3_index: hexagon.h3_index,
          incident_count: hexagon.incident_count || 0,
          incident_z_score: hexagon.incident_z_score || 0,
          risk_level: hexagon.risk_level || 'LOW',
          resolution: this.H3_RESOLUTION,
        };
      }

      return null;
    } catch (error) {
      console.error('Error fetching hexagon data:', error);
      return null;
    }
  }

  /**
   * Send danger notification to user
   */
  private async sendDangerNotification(
    hexagonData: H3HexagonData,
    location: LocationCoords
  ): Promise<void> {
    const zScore = hexagonData.incident_z_score.toFixed(1);
    const incidentCount = hexagonData.incident_count;
    const riskLevel = hexagonData.risk_level;

    // await NotificationService.scheduleNotification({ // Temporarily disabled
    console.log('⚠️ Would send danger notification:', {
      id: `danger-alert-${Date.now()}`,
      title: '⚠️ High Crime Area Alert',
      message: `You are entering a potentially dangerous area. ${incidentCount} incidents reported here (Risk: ${riskLevel}, Z-Score: ${zScore})`,
      url: `https://forseti.life/safety-map?lat=${location.latitude}&lng=${location.longitude}`,
      data: {
        type: 'danger_alert',
        h3_index: hexagonData.h3_index,
        z_score: hexagonData.incident_z_score,
        latitude: location.latitude,
        longitude: location.longitude,
        url: `https://forseti.life/safety-map?lat=${location.latitude}&lng=${location.longitude}`,
      },
      priority: 'high',
      sound: true,
      vibrate: true,
    }); // Closing console.log

    console.log(
      `🚨 DANGER NOTIFICATION (DISABLED) - Z-Score: ${zScore}, Incidents: ${incidentCount}`
    );
  }

  /**
   * Handle location errors
   */
  private handleLocationError(error: any): void {
    console.error('Background location error:', error);
  }

  /**
   * Save location history for analytics
   */
  private async saveLocationHistory(
    h3Index: string,
    location: LocationCoords,
    zScore: number
  ): Promise<void> {
    try {
      const history = (await StorageService.getItem('location_history')) || [];

      history.push({
        h3_index: h3Index,
        latitude: location.latitude,
        longitude: location.longitude,
        z_score: zScore,
        timestamp: location.timestamp,
        resolution: this.H3_RESOLUTION,
      });

      // Keep only last 100 locations
      const trimmedHistory = history.slice(-100);
      await StorageService.setItem('location_history', trimmedHistory);
    } catch (error) {
      console.error('Error saving location history:', error);
    }
  }

  /**
   * Check if monitoring is currently active
   */
  public isActive(): boolean {
    return this.isMonitoring;
  }

  /**
   * Get current H3 index
   */
  public getCurrentH3Index(): string | null {
    return this.currentH3Index;
  }

  /**
   * Restore monitoring state on app restart
   */
  public async restoreMonitoringState(): Promise<void> {
    const wasEnabled = await StorageService.getItem('background_monitoring_enabled');
    if (wasEnabled === true) {
      console.log('📱 Restoring background monitoring from previous session');
      await this.startMonitoring();
    }
  }
}

export default BackgroundLocationService.getInstance();
