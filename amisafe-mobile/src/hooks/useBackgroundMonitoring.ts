/**
 * Background Monitoring Hook
 * 
 * React hook to integrate background location monitoring into the app
 */

import { useEffect, useState } from 'react';
import { Alert, Platform } from 'react-native';
import BackgroundLocationService from '../services/location/BackgroundLocationService';
import StorageService from '../services/storage/StorageService';
import { request, PERMISSIONS, RESULTS } from 'react-native-permissions';

export const useBackgroundMonitoring = () => {
  const [isMonitoring, setIsMonitoring] = useState(false);
  const [currentH3Index, setCurrentH3Index] = useState<string | null>(null);
  const [permissionsGranted, setPermissionsGranted] = useState(false);

  /**
   * Request location permissions
   */
  const requestLocationPermissions = async (): Promise<boolean> => {
    try {
      // Request foreground location first
      const foregroundPermission = Platform.select({
        ios: PERMISSIONS.IOS.LOCATION_WHEN_IN_USE,
        android: PERMISSIONS.ANDROID.ACCESS_FINE_LOCATION,
      });

      if (!foregroundPermission) {
        return false;
      }

      let result = await request(foregroundPermission);

      if (result !== RESULTS.GRANTED) {
        Alert.alert(
          'Location Permission Required',
          'AmISafe needs location access to provide safety alerts.',
          [{ text: 'OK' }]
        );
        return false;
      }

      // Request background location (iOS always, Android 10+)
      if (Platform.OS === 'ios') {
        const backgroundPermission = PERMISSIONS.IOS.LOCATION_ALWAYS;
        result = await request(backgroundPermission);

        if (result !== RESULTS.GRANTED) {
          Alert.alert(
            'Background Location Required',
            'Please enable "Always Allow" location access in Settings for continuous safety monitoring.',
            [{ text: 'OK' }]
          );
          return false;
        }
      } else if (Platform.Version >= 29) {
        // Android 10+ requires separate background permission
        const backgroundPermission = PERMISSIONS.ANDROID.ACCESS_BACKGROUND_LOCATION;
        result = await request(backgroundPermission);

        if (result !== RESULTS.GRANTED) {
          Alert.alert(
            'Background Location Required',
            'Please enable "Allow all the time" location access for continuous safety monitoring.',
            [{ text: 'OK' }]
          );
          return false;
        }
      }

      setPermissionsGranted(true);
      return true;

    } catch (error) {
      console.error('Error requesting permissions:', error);
      return false;
    }
  };

  /**
   * Start background monitoring
   */
  const startMonitoring = async () => {
    try {
      // Check/request permissions first
      const hasPermissions = await requestLocationPermissions();
      if (!hasPermissions) {
        return;
      }

      // Start the background service
      await BackgroundLocationService.startMonitoring();
      setIsMonitoring(true);

      Alert.alert(
        '🛡️ Protection Enabled',
        'AmISafe is now monitoring your location. You\'ll be alerted if you enter high-crime areas.',
        [{ text: 'OK' }]
      );

    } catch (error) {
      console.error('Error starting monitoring:', error);
      Alert.alert(
        'Error',
        'Failed to start background monitoring. Please try again.',
        [{ text: 'OK' }]
      );
    }
  };

  /**
   * Stop background monitoring
   */
  const stopMonitoring = async () => {
    try {
      await BackgroundLocationService.stopMonitoring();
      setIsMonitoring(false);

      Alert.alert(
        'Protection Disabled',
        'Background monitoring has been stopped.',
        [{ text: 'OK' }]
      );

    } catch (error) {
      console.error('Error stopping monitoring:', error);
    }
  };

  /**
   * Toggle monitoring on/off
   */
  const toggleMonitoring = async () => {
    if (isMonitoring) {
      await stopMonitoring();
    } else {
      await startMonitoring();
    }
  };

  /**
   * Restore monitoring state on mount
   */
  useEffect(() => {
    const restoreState = async () => {
      try {
        // Restore monitoring if it was enabled before
        await BackgroundLocationService.restoreMonitoringState();
        
        // Update UI state
        const active = BackgroundLocationService.isActive();
        setIsMonitoring(active);

        // Update current H3 index periodically
        const interval = setInterval(() => {
          const h3Index = BackgroundLocationService.getCurrentH3Index();
          setCurrentH3Index(h3Index);
        }, 5000);

        return () => clearInterval(interval);

      } catch (error) {
        console.error('Error restoring monitoring state:', error);
      }
    };

    restoreState();
  }, []);

  return {
    isMonitoring,
    currentH3Index,
    permissionsGranted,
    startMonitoring,
    stopMonitoring,
    toggleMonitoring,
    requestLocationPermissions,
  };
};
