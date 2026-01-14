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
import { DebugLogger } from '../components/DebugConsole';

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
      } else if (Platform.OS === 'android' && Number(Platform.Version) >= 29) {
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
      DebugLogger.info('🚀 startMonitoring called');
      
      // Check/request permissions first
      console.log('🔐 [useBackgroundMonitoring] Requesting permissions...');
      DebugLogger.info('🔐 Requesting location permissions...');
      
      const hasPermissions = await requestLocationPermissions();
      if (!hasPermissions) {
        console.log('⚠️ [useBackgroundMonitoring] Permissions denied');
        DebugLogger.warn('⚠️ Permissions denied by user');
        return;
      }
      console.log('✅ [useBackgroundMonitoring] Permissions granted');
      DebugLogger.info('✅ Permissions granted');

      // Start the background service
      console.log('⚙️ [useBackgroundMonitoring] Starting BackgroundLocationService...');
      DebugLogger.info('⚙️ Starting BackgroundLocationService...');
      
      try {
        await BackgroundLocationService.startMonitoring();
        console.log('✅ [useBackgroundMonitoring] BackgroundLocationService started');
        DebugLogger.info('✅ BackgroundLocationService started successfully');
      } catch (serviceError) {
        DebugLogger.error('❌ BackgroundLocationService.startMonitoring failed:', serviceError);
        DebugLogger.error('Service error type:', typeof serviceError);
        DebugLogger.error('Service error message:', serviceError instanceof Error ? serviceError.message : String(serviceError));
        DebugLogger.error('Service error stack:', serviceError instanceof Error ? serviceError.stack : 'No stack');
        throw serviceError;
      }
      
      setIsMonitoring(true);
      DebugLogger.info('✅ Monitoring state set to true');

      Alert.alert(
        '🛡️ Protection Enabled',
        "Forseti is now monitoring your location. You'll be alerted if you enter high-crime areas.",
        [{ text: 'OK' }]
      );
    } catch (error) {
      console.error('❌ [useBackgroundMonitoring] Error starting monitoring:', error);
      console.error('Stack:', error instanceof Error ? error.stack : 'No stack trace');
      
      // Log comprehensive error details
      try {
        DebugLogger.error('❌ CRITICAL: startMonitoring failed');
        DebugLogger.error('Error object:', error);
        DebugLogger.error('Error type:', typeof error);
        DebugLogger.error('Error name:', error instanceof Error ? error.name : 'Not an Error object');
        DebugLogger.error('Error message:', error instanceof Error ? error.message : String(error));
        DebugLogger.error('Error stack:', error instanceof Error ? error.stack : 'No stack available');
        if (error && typeof error === 'object') {
          DebugLogger.error('Error keys:', Object.keys(error).join(', '));
          DebugLogger.error('Error JSON:', JSON.stringify(error, null, 2));
        }
      } catch (logError) {
        console.error('Failed to log error to DebugConsole:', logError);
      }
      
      Alert.alert(
        'Error',
        `Failed to start background monitoring.\n\nError: ${error instanceof Error ? error.message : String(error)}\n\nCheck the debug console (bug icon) for full details.`,
        [{ text: 'OK' }]
      );
      setIsMonitoring(false);
      throw error; // Re-throw so toggleMonitoring can catch it
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
    // Log immediately (synchronously) before any async operations
    console.log(`🔄 [useBackgroundMonitoring] Toggle monitoring (current: ${isMonitoring})`);
    DebugLogger.info(`🔄 Toggle monitoring called (current: ${isMonitoring})`);
    
    try {
      if (isMonitoring) {
        DebugLogger.info('🛑 Stopping monitoring...');
        await stopMonitoring();
        DebugLogger.info('✅ Monitoring stopped successfully');
      } else {
        DebugLogger.info('🚀 Starting monitoring...');
        await startMonitoring();
        DebugLogger.info('✅ Monitoring started successfully');
      }
    } catch (error) {
      console.error('❌ [useBackgroundMonitoring] Error toggling monitoring:', error);
      console.error('Stack:', error instanceof Error ? error.stack : 'No stack trace');
      
      // Log to DebugConsole
      try {
        DebugLogger.error('❌ Toggle monitoring failed:', error);
        DebugLogger.error('Stack trace:', error instanceof Error ? error.stack : 'No stack');
        DebugLogger.error('Error type:', typeof error);
        DebugLogger.error('Error keys:', error ? Object.keys(error).join(', ') : 'null');
      } catch (logError) {
        console.error('Failed to log to DebugConsole:', logError);
      }
      
      Alert.alert(
        'Error',
        `Failed to ${isMonitoring ? 'stop' : 'start'} background monitoring.\n\nError: ${error instanceof Error ? error.message : String(error)}\n\nCheck debug console for details.`,
        [{ text: 'OK' }]
      );
      
      // Restore state if toggle failed
      try {
        const active = BackgroundLocationService.isActive();
        console.log(`📊 [useBackgroundMonitoring] Restoring state to: ${active}`);
        setIsMonitoring(active);
      } catch (restoreError) {
        console.error('Failed to restore state:', restoreError);
        setIsMonitoring(false);
      }
    }
  };

  /**
   * Restore monitoring state on mount
   */
  useEffect(() => {
    const restoreState = async () => {
      try {
        console.log('🔄 [useBackgroundMonitoring] Restoring monitoring state...');
        
        // Restore monitoring if it was enabled before (currently disabled in service)
        await BackgroundLocationService.restoreMonitoringState();
        console.log('✅ [useBackgroundMonitoring] Monitoring state check complete');

        // Update UI state
        const active = BackgroundLocationService.isActive();
        console.log(`📊 [useBackgroundMonitoring] Monitoring active: ${active}`);
        setIsMonitoring(active);

        // Update current H3 index periodically
        const interval = setInterval(() => {
          try {
            const h3Index = BackgroundLocationService.getCurrentH3Index();
            setCurrentH3Index(h3Index);
          } catch (error) {
            // Ignore errors in interval
          }
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

    // Run restore in a way that won't crash the app
    restoreState().catch(error => {
      console.error('❌ [useBackgroundMonitoring] Fatal error in restoreState:', error);
      setIsMonitoring(false);
      setCurrentH3Index(null);
    });
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
