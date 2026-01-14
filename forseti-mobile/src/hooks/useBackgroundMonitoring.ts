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
          'Forseti needs location access to provide safety alerts.',
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
      console.log('🚀 [useBackgroundMonitoring] Starting background monitoring...');
      
      // Check/request permissions first
      console.log('🔐 [useBackgroundMonitoring] Requesting permissions...');
      const hasPermissions = await requestLocationPermissions();
      if (!hasPermissions) {
        console.log('⚠️ [useBackgroundMonitoring] Permissions denied');
        return;
      }
      console.log('✅ [useBackgroundMonitoring] Permissions granted');

      // Start the background service
      console.log('⚙️ [useBackgroundMonitoring] Starting BackgroundLocationService...');
      await BackgroundLocationService.startMonitoring();
      console.log('✅ [useBackgroundMonitoring] BackgroundLocationService started');
      
      setIsMonitoring(true);

      Alert.alert(
        '🛡️ Protection Enabled',
        "Forseti is now monitoring your location. You'll be alerted if you enter high-crime areas.",
        [{ text: 'OK' }]
      );
    } catch (error) {
      console.error('❌ [useBackgroundMonitoring] Error starting monitoring:', error);
      console.error('Stack:', error instanceof Error ? error.stack : 'No stack trace');
      Alert.alert('Error', `Failed to start background monitoring. ${error instanceof Error ? error.message : 'Please try again.'}`, [
        { text: 'OK' },
      ]);
      setIsMonitoring(false);
    }
  };

  /**
   * Stop background monitoring
   */
  const stopMonitoring = async () => {
    try {
      console.log('🛑 [useBackgroundMonitoring] Stopping background monitoring...');
      await BackgroundLocationService.stopMonitoring();
      console.log('✅ [useBackgroundMonitoring] Background monitoring stopped');
      setIsMonitoring(false);

      Alert.alert('Protection Disabled', 'Background monitoring has been stopped.', [
        { text: 'OK' },
      ]);
    } catch (error) {
      console.error('❌ [useBackgroundMonitoring] Error stopping monitoring:', error);
      console.error('Stack:', error instanceof Error ? error.stack : 'No stack trace');
      setIsMonitoring(false);
    }
  };

  /**
   * Toggle monitoring on/off
   */
  const toggleMonitoring = async () => {
    try {
      console.log(`🔄 [useBackgroundMonitoring] Toggle monitoring (current: ${isMonitoring})`);
      if (isMonitoring) {
        await stopMonitoring();
      } else {
        await startMonitoring();
      }
    } catch (error) {
      console.error('❌ [useBackgroundMonitoring] Error toggling monitoring:', error);
      console.error('Stack:', error instanceof Error ? error.stack : 'No stack trace');
      Alert.alert(
        'Error',
        `Failed to ${isMonitoring ? 'stop' : 'start'} background monitoring. ${error instanceof Error ? error.message : 'Please try again.'}`,
        [{ text: 'OK' }]
      );
      // Restore state if toggle failed
      const active = BackgroundLocationService.isActive();
      console.log(`📊 [useBackgroundMonitoring] Restoring state to: ${active}`);
      setIsMonitoring(active);
    }
  };

  /**
   * Restore monitoring state on mount
   */
  useEffect(() => {
    const restoreState = async () => {
      try {
        console.log('🔄 [useBackgroundMonitoring] Restoring monitoring state...');
        
        // Restore monitoring if it was enabled before
        await BackgroundLocationService.restoreMonitoringState();
        console.log('✅ [useBackgroundMonitoring] Monitoring state restored');

        // Update UI state
        const active = BackgroundLocationService.isActive();
        console.log(`📊 [useBackgroundMonitoring] Monitoring active: ${active}`);
        setIsMonitoring(active);

        // Update current H3 index periodically
        const interval = setInterval(() => {
          const h3Index = BackgroundLocationService.getCurrentH3Index();
          setCurrentH3Index(h3Index);
        }, 5000);

        return () => clearInterval(interval);
      } catch (error) {
        console.error('❌ [useBackgroundMonitoring] Error restoring monitoring state:', error);
        console.error('Stack:', error instanceof Error ? error.stack : 'No stack trace');
        // Don't crash - just set to inactive state
        setIsMonitoring(false);
        setCurrentH3Index(null);
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
