/**
 * Notification Service for Forseti Mobile Application
 * Handles push notifications, local notifications, and safety alerts
 */

import { Platform, Alert, Linking, NativeModules, PermissionsAndroid } from 'react-native';
import PushNotification from 'react-native-push-notification';

export interface NotificationConfig {
  title: string;
  message: string;
  data?: any;
  userInfo?: any;
  playSound?: boolean;
  soundName?: string;
  vibrate?: boolean;
  actions?: string[];
  url?: string; // URL to open when notification is clicked
}

export interface SafetyAlert {
  id: string;
  type: 'high_crime_area' | 'emergency' | 'safety_tip' | 'area_update';
  priority: 'low' | 'medium' | 'high' | 'critical';
  title: string;
  message: string;
  location?: {
    latitude: number;
    longitude: number;
  };
  timestamp: number;
  expiresAt?: number;
}

export interface NotificationDiagnostics {
  notificationsEnabled: boolean;
  batteryOptimized: boolean;
  doNotDisturbActive: boolean;
  channelsEnabled: boolean;
  permissionStatus: string;
  androidApiLevel?: number;
  lastError?: string;
}

class NotificationService {
  private static instance: NotificationService;
  private initialized = false;
  private notificationCallbacks: ((notification: any) => void)[] = [];

  private constructor() {}

  public static getInstance(): NotificationService {
    if (!NotificationService.instance) {
      NotificationService.instance = new NotificationService();
    }
    return NotificationService.instance;
  }

  /**
   * Initialize the notification service
   */
  public async initialize(): Promise<void> {
    try {
      // Configure push notifications with Firebase disabled to prevent crashes
      PushNotification.configure({
        onRegister: token => {
          console.log('📱 Push notification token:', token);
          // Send token to backend for remote notifications
        },

        onNotification: notification => {
          console.log('📬 Notification received:', notification);

          // Handle notification tap - open URL if provided
          if (notification.userTapped && notification.data?.url) {
            Linking.openURL(notification.data.url).catch(err =>
              console.error('Failed to open URL:', err)
            );
          }

          this.handleNotification(notification);
        },

        onAction: notification => {
          console.log('📬 Notification action:', notification);
          this.handleNotificationAction(notification);
        },

        onRegistrationError: error => {
          console.error('❌ Push notification registration error:', error);
        },

        permissions: {
          alert: true,
          badge: true,
          sound: true,
        },

        popInitialNotification: true,
        requestPermissions: Platform.OS === 'ios',
        
        // Disable Firebase to prevent crashes
        senderID: false,
      });

      // Create notification channels for Android
      if (Platform.OS === 'android') {
        this.createNotificationChannels();
      }

      this.initialized = true;
      console.log('✅ NotificationService initialized successfully');
    } catch (error) {
      console.error('❌ NotificationService initialization failed:', error);
      throw error;
    }
  }

  /**
   * Create notification channels for Android
   */
  private createNotificationChannels(): void {
    PushNotification.createChannel(
      {
        channelId: 'forseti-safety-alerts',
        channelName: 'Safety Alerts',
        channelDescription: 'Critical safety notifications and alerts',
        importance: 4, // HIGH
        vibrate: true,
      },
      created => console.log(`Safety alerts channel created: ${created}`)
    );

    PushNotification.createChannel(
      {
        channelId: 'forseti-general',
        channelName: 'General Notifications',
        channelDescription: 'General Forseti notifications',
        importance: 3, // DEFAULT
        vibrate: false,
      },
      created => console.log(`General channel created: ${created}`)
    );

    PushNotification.createChannel(
      {
        channelId: 'forseti-emergency',
        channelName: 'Emergency Alerts',
        channelDescription: 'Emergency notifications requiring immediate attention',
        importance: 5, // MAX
        vibrate: true,
        playSound: true,
      },
      created => console.log(`Emergency channel created: ${created}`)
    );
  }

  /**
   * Send a local notification
   */
  public sendLocalNotification(config: NotificationConfig): void {
    const channelId = this.getChannelId(config.title);

    PushNotification.localNotification({
      title: config.title,
      message: config.message,
      playSound: config.playSound ?? true,
      soundName: config.soundName ?? 'default',
      vibrate: config.vibrate ?? true,
      channelId,
      userInfo: {
        ...config.userInfo,
        url: config.url, // Include URL for deep linking
      },
      actions: config.actions,
    });
  }

  /**
   * Send a safety alert notification
   */
  public sendSafetyAlert(alert: SafetyAlert): void {
    try {
      const channelId = this.getSafetyChannelId(alert.priority);
      console.log(`📨 [NOTIFICATION DEBUG] Sending safety alert with channelId: ${channelId}`);
      console.log(`📨 [NOTIFICATION DEBUG] Alert title: ${alert.title}`);
      console.log(`📨 [NOTIFICATION DEBUG] Alert priority: ${alert.priority}`);
      console.log(`📨 [NOTIFICATION DEBUG] Platform: ${Platform.OS}`);

      PushNotification.localNotification({
        id: alert.id,
        title: alert.title,
        message: alert.message,
        playSound: alert.priority === 'critical' || alert.priority === 'high',
        soundName: alert.priority === 'critical' ? 'emergency.mp3' : 'default',
        vibrate: alert.priority !== 'low',
        channelId,
        userInfo: {
          alertId: alert.id,
          alertType: alert.type,
          priority: alert.priority,
          location: alert.location,
        },
        actions: ['View Details', 'Dismiss'],
      });

      console.log(`✅ [NOTIFICATION DEBUG] PushNotification.localNotification called successfully`);
    } catch (error) {
      console.error('❌ [NOTIFICATION ERROR] Failed to send safety alert:', error);
    }
  }

  /**
   * Schedule a delayed notification
   */
  public scheduleNotification(config: NotificationConfig, delayMs: number = 0): void {
    const channelId = this.getChannelId(config.title);

    const notificationData = {
      title: config.title,
      message: config.message,
      playSound: config.playSound ?? true,
      soundName: config.soundName ?? 'default',
      vibrate: config.vibrate ?? true,
      channelId,
      userInfo: {
        ...config.userInfo,
        url: config.url, // Include URL for deep linking
      },
      data: {
        ...config.data,
        url: config.url, // Include URL in data as well
      },
      actions: config.actions,
    };

    if (delayMs > 0) {
      PushNotification.localNotificationSchedule({
        ...notificationData,
        date: new Date(Date.now() + delayMs),
      });
    } else {
      PushNotification.localNotification(notificationData);
    }
  }

  /**
   * Cancel a scheduled notification
   */
  public cancelNotification(notificationId: string): void {
    PushNotification.cancelLocalNotifications({ id: notificationId });
  }

  /**
   * Cancel all notifications
   */
  public cancelAllNotifications(): void {
    PushNotification.cancelAllLocalNotifications();
  }

  /**
   * Get notification channel ID based on title/content
   */
  private getChannelId(title: string): string {
    if (Platform.OS !== 'android') {
      return '';
    }

    const lowerTitle = title.toLowerCase();
    if (lowerTitle.includes('emergency') || lowerTitle.includes('urgent')) {
      return 'forseti-emergency';
    }
    if (lowerTitle.includes('safety') || lowerTitle.includes('alert')) {
      return 'forseti-safety-alerts';
    }
    return 'forseti-general';
  }

  /**
   * Get safety alert channel ID based on priority
   */
  private getSafetyChannelId(priority: SafetyAlert['priority']): string {
    if (Platform.OS !== 'android') {
      return '';
    }

    switch (priority) {
      case 'critical':
        return 'forseti-emergency';
      case 'high':
      case 'medium':
        return 'forseti-safety-alerts';
      case 'low':
      default:
        return 'forseti-general';
    }
  }

  /**
   * Handle incoming notifications
   */
  private handleNotification(notification: any): void {
    this.notifyCallbacks(notification);

    // Handle specific notification types
    if (notification.userInfo?.alertType) {
      this.handleSafetyAlert(notification);
    }
  }

  /**
   * Handle notification actions
   */
  private handleNotificationAction(notification: any): void {
    const action = notification.action;
    const userInfo = notification.userInfo;

    switch (action) {
      case 'View Details':
        // Navigate to appropriate screen
        console.log('View Details action triggered');
        break;
      case 'Dismiss':
        // Dismiss the notification
        console.log('Dismiss action triggered');
        break;
      default:
        console.log('Unknown notification action:', action);
    }
  }

  /**
   * Handle safety alert notifications
   */
  private handleSafetyAlert(notification: any): void {
    const { alertType, priority, location } = notification.userInfo;

    console.log(`🚨 Safety alert received: ${alertType} (${priority})`);

    // Show alert dialog for critical notifications
    if (priority === 'critical') {
      Alert.alert('Critical Safety Alert', notification.alert?.body || notification.message, [
        { text: 'Dismiss', style: 'cancel' },
        { text: 'View Map', onPress: () => this.navigateToMap(location) },
      ]);
    }
  }

  /**
   * Navigate to map with specific location
   */
  private navigateToMap(location?: { latitude: number; longitude: number }): void {
    // This would be implemented with navigation service
    console.log('Navigate to map:', location);
  }

  /**
   * Subscribe to notification events
   */
  public onNotification(callback: (notification: any) => void): () => void {
    this.notificationCallbacks.push(callback);

    // Return unsubscribe function
    return () => {
      const index = this.notificationCallbacks.indexOf(callback);
      if (index > -1) {
        this.notificationCallbacks.splice(index, 1);
      }
    };
  }

  /**
   * Notify all callbacks
   */
  private notifyCallbacks(notification: any): void {
    this.notificationCallbacks.forEach(callback => {
      try {
        callback(notification);
      } catch (error) {
        console.error('Error in notification callback:', error);
      }
    });
  }

  /**
   * Check notification permissions
   */
  public async checkPermissions(): Promise<boolean> {
    try {
      if (Platform.OS === 'android') {
        // On Android 13+ (API 33+), check for POST_NOTIFICATIONS permission
        if (Platform.Version >= 33) {
          const permission = await PermissionsAndroid.check('android.permission.POST_NOTIFICATIONS');
          return permission;
        } else {
          // On older Android versions, notifications are enabled by default unless explicitly disabled
          // We can only check if the app is allowed to show notifications
          return new Promise((resolve) => {
            PushNotification.checkPermissions((permissions) => {
              resolve(permissions.alert && permissions.badge && permissions.sound);
            });
          });
        }
      } else if (Platform.OS === 'ios') {
        return new Promise((resolve) => {
          PushNotification.checkPermissions((permissions) => {
            resolve(permissions.alert && permissions.badge && permissions.sound);
          });
        });
      }
      return false;
    } catch (error) {
      console.error('Error checking permissions:', error);
      return false;
    }
  }

  /**
   * Request notification permissions
   */
  public async requestPermissions(): Promise<boolean> {
    try {
      if (Platform.OS === 'android' && Platform.Version >= 33) {
        // On Android 13+ (API 33+), request POST_NOTIFICATIONS permission
        const granted = await PermissionsAndroid.request('android.permission.POST_NOTIFICATIONS');
        return granted === PermissionsAndroid.RESULTS.GRANTED;
      } else {
        // Use PushNotification library for iOS and older Android versions
        return new Promise(resolve => {
          PushNotification.requestPermissions().then(permissions => {
            const hasPermissions = permissions.alert && permissions.badge && permissions.sound;
            resolve(hasPermissions);
          });
        });
      }
    } catch (error) {
      console.error('Error requesting permissions:', error);
      return false;
    }
  }

  /**
   * Get comprehensive notification diagnostics
   */
  public async getNotificationDiagnostics(): Promise<NotificationDiagnostics> {
    try {
      const diagnostics: NotificationDiagnostics = {
        notificationsEnabled: false,
        batteryOptimized: false,
        doNotDisturbActive: false,
        channelsEnabled: false,
        permissionStatus: 'unknown',
        androidApiLevel: Platform.OS === 'android' ? Platform.Version : undefined,
      };

      // Check basic permissions
      const hasPermissions = await this.checkPermissions();
      diagnostics.notificationsEnabled = hasPermissions;
      diagnostics.permissionStatus = hasPermissions ? 'granted' : 'denied';

      if (Platform.OS === 'android') {
        try {
          // Try to get Android-specific information
          const powerManager = NativeModules.PowerManager;
          if (powerManager) {
            diagnostics.batteryOptimized = await powerManager.isIgnoringBatteryOptimizations();
          }

          // Check Do Not Disturb (requires Android API)
          const notificationManager = NativeModules.NotificationManagerCompat;
          if (notificationManager) {
            diagnostics.doNotDisturbActive = await notificationManager.getCurrentInterruptionFilter() !== 1;
          }

          // Check notification channels
          diagnostics.channelsEnabled = await this.checkNotificationChannels();

        } catch (androidError) {
          console.warn('Android-specific notification checks failed:', androidError);
          diagnostics.lastError = `Android checks failed: ${androidError.message}`;
        }
      }

      return diagnostics;
    } catch (error) {
      console.error('Failed to get notification diagnostics:', error);
      return {
        notificationsEnabled: false,
        batteryOptimized: true,
        doNotDisturbActive: false,
        channelsEnabled: false,
        permissionStatus: 'error',
        lastError: error.message,
      };
    }
  }

  /**
   * Check if notification channels are properly enabled
   */
  private async checkNotificationChannels(): Promise<boolean> {
    try {
      if (Platform.OS !== 'android') {
        return true; // iOS doesn't use channels
      }

      // For now, assume channels are enabled if we can send notifications
      // In a real implementation, you'd check each channel individually
      return true;
    } catch (error) {
      console.warn('Failed to check notification channels:', error);
      return false;
    }
  }

  /**
   * Open system notification settings
   */
  public openNotificationSettings(): void {
    try {
      if (Platform.OS === 'android') {
        // Try to open notification settings for the app
        Linking.openSettings();
      } else {
        // iOS
        Linking.openURL('app-settings:');
      }
    } catch (error) {
      console.error('Failed to open notification settings:', error);
      Alert.alert(
        'Settings',
        'Please go to Settings > Apps > Forseti > Notifications to enable notifications.'
      );
    }
  }

  /**
   * Cleanup notification service
   */
  public cleanup(): void {
    this.cancelAllNotifications();
    this.notificationCallbacks = [];
    console.log('🧹 NotificationService cleaned up');
  }
}

// Export singleton instance
export default NotificationService.getInstance();
