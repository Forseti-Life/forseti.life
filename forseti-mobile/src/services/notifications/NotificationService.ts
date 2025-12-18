/**
 * Notification Service for AmISafe Mobile Application
 * Handles push notifications, local notifications, and safety alerts
 */

import { Platform, Alert, Linking } from 'react-native';
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
      // Configure push notifications
      PushNotification.configure({
        onRegister: (token) => {
          console.log('📱 Push notification token:', token);
          // Send token to backend for remote notifications
        },

        onNotification: (notification) => {
          console.log('📬 Notification received:', notification);
          
          // Handle notification tap - open URL if provided
          if (notification.userTapped && notification.data?.url) {
            Linking.openURL(notification.data.url).catch(err => 
              console.error('Failed to open URL:', err)
            );
          }
          
          this.handleNotification(notification);
        },

        onAction: (notification) => {
          console.log('📬 Notification action:', notification);
          this.handleNotificationAction(notification);
        },

        onRegistrationError: (error) => {
          console.error('❌ Push notification registration error:', error);
        },

        permissions: {
          alert: true,
          badge: true,
          sound: true,
        },

        popInitialNotification: true,
        requestPermissions: Platform.OS === 'ios',
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
      (created) => console.log(`Safety alerts channel created: ${created}`)
    );

    PushNotification.createChannel(
      {
        channelId: 'forseti-general',
        channelName: 'General Notifications',
        channelDescription: 'General Forseti notifications',
        importance: 3, // DEFAULT
        vibrate: false,
      },
      (created) => console.log(`General channel created: ${created}`)
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
      (created) => console.log(`Emergency channel created: ${created}`)
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
    const channelId = this.getSafetyChannelId(alert.priority);
    
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
      Alert.alert(
        'Critical Safety Alert',
        notification.alert?.body || notification.message,
        [
          { text: 'Dismiss', style: 'cancel' },
          { text: 'View Map', onPress: () => this.navigateToMap(location) },
        ]
      );
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
    return new Promise((resolve) => {
      PushNotification.checkPermissions((permissions) => {
        const hasPermissions = permissions.alert && permissions.badge && permissions.sound;
        resolve(hasPermissions);
      });
    });
  }

  /**
   * Request notification permissions
   */
  public async requestPermissions(): Promise<boolean> {
    return new Promise((resolve) => {
      PushNotification.requestPermissions().then((permissions) => {
        const hasPermissions = permissions.alert && permissions.badge && permissions.sound;
        resolve(hasPermissions);
      });
    });
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