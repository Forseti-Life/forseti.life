/**
 * Settings Screen
 *
 * User settings for background monitoring and safety preferences
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Switch,
  ScrollView,
  TouchableOpacity,
  Alert,
  Linking,
  TextInput,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useBackgroundMonitoring } from '../../hooks/useBackgroundMonitoring';
import StorageService from '../../services/storage/StorageService';
import NotificationService from '../../services/notifications/NotificationService';
import { Theme } from '../../utils/theme';
import DebugConsole, { DebugLogger } from '../../components/DebugConsole';

const { Colors, Spacing, Typography, Shadows } = Theme;

// Error Boundary Component
class SettingsErrorBoundary extends React.Component<
  { children: React.ReactNode },
  { hasError: boolean; error: any }
> {
  constructor(props: any) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: any) {
    DebugLogger.error('🚨 ERROR BOUNDARY CAUGHT ERROR');
    DebugLogger.error('Error type: ' + typeof error);
    DebugLogger.error('Error message: ' + (error?.message || String(error)));
    DebugLogger.error('Error stack: ' + (error?.stack || 'No stack'));
    return { hasError: true, error };
  }

  componentDidCatch(error: any, errorInfo: any) {
    DebugLogger.error('🚨 componentDidCatch called');
    DebugLogger.error('Error: ' + JSON.stringify(error));
    DebugLogger.error('ErrorInfo: ' + JSON.stringify(errorInfo));
    console.error('Settings screen error:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', padding: 20 }}>
          <Icon name="alert-circle" size={48} color={Colors.danger} />
          <Text style={{ fontSize: 18, fontWeight: 'bold', marginTop: 16, color: Colors.text }}>
            Settings Error
          </Text>
          <Text style={{ marginTop: 8, textAlign: 'center', color: Colors.text }}>
            {this.state.error?.message || 'An error occurred'}
          </Text>
          <TouchableOpacity
            style={{ marginTop: 20, backgroundColor: Colors.primary, padding: 12, borderRadius: 8 }}
            onPress={() => this.setState({ hasError: false, error: null })}
          >
            <Text style={{ color: 'white', fontWeight: 'bold' }}>Try Again</Text>
          </TouchableOpacity>
          <DebugConsole />
        </View>
      );
    }

    return this.props.children;
  }
}

const SettingsScreenContent = ({ navigation }: any) => {
  DebugLogger.info('⚙️ Settings screen mounted');
  
  // Safe hook usage with fallback
  let hookData;
  try {
    hookData = useBackgroundMonitoring();
    DebugLogger.info('✅ useBackgroundMonitoring hook loaded successfully');
  } catch (error) {
    DebugLogger.error('❌ useBackgroundMonitoring hook failed:', error);
    hookData = {
      isMonitoring: false,
      currentH3Index: null,
      toggleMonitoring: async () => {
        DebugLogger.error('Toggle called but hook is broken');
        Alert.alert('Error', 'Background monitoring is unavailable. Please restart the app.');
      },
    };
  }
  
  const { isMonitoring, currentH3Index, toggleMonitoring } = hookData;

  const [zScoreThreshold, setZScoreThreshold] = useState(2.0);
  const [notificationCooldown, setNotificationCooldown] = useState(5);
  const [h3Resolution, setH3Resolution] = useState(11);
  const [testH3Index, setTestH3Index] = useState('');

  useEffect(() => {
    DebugLogger.info('📥 Loading settings...');
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      DebugLogger.info('🔍 Fetching settings from storage...');
      const threshold = await StorageService.getItem('z_score_threshold');
      const cooldown = await StorageService.getItem('notification_cooldown');
      const resolution = await StorageService.getItem('h3_resolution');

      DebugLogger.info(`📊 Loaded threshold: ${threshold}, cooldown: ${cooldown}, resolution: ${resolution}`);
      if (threshold !== null) setZScoreThreshold(threshold);
      if (cooldown !== null) setNotificationCooldown(cooldown);
      if (resolution !== null) setH3Resolution(resolution);
      DebugLogger.info('✅ Settings loaded successfully');
    } catch (error) {
      DebugLogger.error('❌ Error loading settings:', error);
      console.error('Error loading settings:', error);
    }
  };

  const getResolutionDescription = (resolution: number): string => {
    const descriptions: { [key: number]: string } = {
      9: '~325m (Street blocks)',
      10: '~122m (Building groups)',
      11: '~46m (Buildings)',
      12: '~17m (Rooms/apartments)',
      13: '~6.6m (Ultra-precision)',
    };
    return descriptions[resolution] || `Resolution ${resolution}`;
  };

  const saveSettings = async () => {
    try {
      await StorageService.setItem('z_score_threshold', zScoreThreshold);
      await StorageService.setItem('notification_cooldown', notificationCooldown);
      await StorageService.setItem('h3_resolution', h3Resolution);

      Alert.alert('Settings Saved', 'Your preferences have been updated. Restart monitoring for changes to take effect.', [{ text: 'OK' }]);
    } catch (error) {
      console.error('Error saving settings:', error);
      Alert.alert('Error', 'Failed to save settings. Please try again.', [{ text: 'OK' }]);
    }
  };

  const viewLocationHistory = async () => {
    try {
      const history = await StorageService.getItem('location_history');
      const count = history ? history.length : 0;

      if (count === 0) {
        Alert.alert(
          'Location History',
          'No location history yet. Background monitoring will track your location once enabled.',
          [{ text: 'OK' }]
        );
        return;
      }

      const latest = history[history.length - 1];
      const latestTime = new Date(latest.timestamp).toLocaleString();

      Alert.alert(
        'Location History',
        `You have ${count} location records stored.\n\nLatest: ${latestTime}\nH3 Index: ${latest.h3_index}\nZ-Score: ${latest.z_score?.toFixed(2) || 'N/A'}\n\nThis data is used to improve your safety alerts and is stored locally on your device.`,
        [{ text: 'OK' }]
      );
    } catch (error) {
      console.error('Error viewing location history:', error);
      Alert.alert('Error', 'Failed to load location history.', [{ text: 'OK' }]);
    }
  };

  const clearLocationHistory = async () => {
    Alert.alert(
      'Clear Location History',
      'Are you sure you want to delete your location history? This cannot be undone.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Clear',
          style: 'destructive',
          onPress: async () => {
            try {
              await StorageService.setItem('location_history', []);
              Alert.alert('✅ History Cleared', 'Your location history has been deleted successfully.', [
                { text: 'OK' },
              ]);
            } catch (error) {
              console.error('Error clearing history:', error);
              Alert.alert('Error', 'Failed to clear location history.', [{ text: 'OK' }]);
            }
          },
        },
      ]
    );
  };

  return (
    <ScrollView style={styles.container}>
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🛡️ Background Monitoring</Text>
        <Text style={styles.sectionDescription}>Get alerted when entering high-crime areas</Text>

        <View style={styles.settingRow}>
          <View style={styles.settingInfo}>
            <Text style={styles.settingLabel}>Enable Protection</Text>
            <Text style={styles.settingDescription}>Monitor location in background</Text>
          </View>
          <Switch
            value={isMonitoring}
            onValueChange={() => {
              // Wrap in immediate try-catch to catch synchronous errors
              try {
                DebugLogger.info('🎛️ [Settings] Switch clicked - calling toggleMonitoring');
                console.log('🎛️ [Settings] Switch onValueChange fired');
                
                // Call toggle but don't await - let it run async with its own error handling
                toggleMonitoring().catch((error) => {
                  console.error('❌ [Settings] toggleMonitoring promise rejected:', error);
                  DebugLogger.error('❌ [Settings] toggleMonitoring promise rejected:', error);
                  DebugLogger.error('Rejected error type:', typeof error);
                  DebugLogger.error('Rejected error message:', error instanceof Error ? error.message : String(error));
                  DebugLogger.error('Rejected error stack:', error instanceof Error ? error.stack : 'No stack');
                });
                
                DebugLogger.info('✅ [Settings] toggleMonitoring called (running async)');
              } catch (syncError) {
                console.error('❌ [Settings] SYNCHRONOUS error in onValueChange:', syncError);
                DebugLogger.error('❌ [Settings] SYNCHRONOUS error in Switch handler:', syncError);
                DebugLogger.error('Sync error type:', typeof syncError);
                DebugLogger.error('Sync error message:', syncError instanceof Error ? syncError.message : String(syncError));
                DebugLogger.error('Sync error stack:', syncError instanceof Error ? syncError.stack : 'No stack');
                Alert.alert(
                  'Switch Error',
                  `Synchronous error in toggle.\n\nError: ${syncError instanceof Error ? syncError.message : String(syncError)}`,
                  [{ text: 'OK' }]
                );
              }
            }}
            trackColor={{ false: Colors.gray, true: Colors.success }}
            thumbColor={isMonitoring ? Colors.white : Colors.lightGray}
          />
        </View>

        {isMonitoring && currentH3Index && (
          <View style={styles.statusBox}>
            <Text style={styles.statusLabel}>Current Location</Text>
            <Text style={styles.statusValue}>H3: {currentH3Index}</Text>
            <Text style={styles.statusDescription}>Monitoring at {getResolutionDescription(h3Resolution)}</Text>
          </View>
        )}
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>📐 Monitoring Resolution</Text>
        <Text style={styles.sectionDescription}>H3 hexagon size for location monitoring</Text>

        <View style={styles.settingRow}>
          <View style={styles.settingInfo}>
            <Text style={styles.settingLabel}>H3 Resolution Level</Text>
            <Text style={styles.settingDescription}>
              Currently: {getResolutionDescription(h3Resolution)}
            </Text>
          </View>
        </View>

        <View style={styles.thresholdButtons}>
          {[9, 10, 11, 12, 13].map(value => (
            <TouchableOpacity
              key={value}
              style={[
                styles.resolutionButton,
                h3Resolution === value && styles.thresholdButtonActive,
              ]}
              onPress={() => setH3Resolution(value)}
            >
              <Text
                style={[
                  styles.resolutionButtonText,
                  h3Resolution === value && styles.thresholdButtonTextActive,
                ]}
              >
                {value}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        <View style={styles.resolutionInfo}>
          <Text style={styles.resolutionInfoText}>ℹ️ Resolution Guide:</Text>
          <Text style={styles.resolutionInfoDetail}>• 9: ~325m - Street blocks (less battery)</Text>
          <Text style={styles.resolutionInfoDetail}>• 10: ~122m - Building groups (balanced)</Text>
          <Text style={styles.resolutionInfoDetail}>• 11: ~46m - Individual buildings (recommended)</Text>
          <Text style={styles.resolutionInfoDetail}>• 12: ~17m - Rooms/apartments (higher battery)</Text>
          <Text style={styles.resolutionInfoDetail}>• 13: ~6.6m - Ultra-precision (most battery)</Text>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>⚙️ Alert Settings</Text>

        <View style={styles.settingRow}>
          <View style={styles.settingInfo}>
            <Text style={styles.settingLabel}>Danger Threshold</Text>
            <Text style={styles.settingDescription}>
              Alert when z-score is {zScoreThreshold.toFixed(1)} or higher
            </Text>
          </View>
        </View>

        <View style={styles.thresholdButtons}>
          {[1.0, 1.5, 2.0, 2.5, 3.0].map(value => (
            <TouchableOpacity
              key={value}
              style={[
                styles.thresholdButton,
                zScoreThreshold === value && styles.thresholdButtonActive,
              ]}
              onPress={() => setZScoreThreshold(value)}
            >
              <Text
                style={[
                  styles.thresholdButtonText,
                  zScoreThreshold === value && styles.thresholdButtonTextActive,
                ]}
              >
                {value.toFixed(1)}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        <View style={styles.settingRow}>
          <View style={styles.settingInfo}>
            <Text style={styles.settingLabel}>Notification Cooldown</Text>
            <Text style={styles.settingDescription}>
              Wait {notificationCooldown} minutes between alerts
            </Text>
          </View>
        </View>

        <View style={styles.thresholdButtons}>
          {[1, 5, 10, 15, 30].map(value => (
            <TouchableOpacity
              key={value}
              style={[
                styles.thresholdButton,
                notificationCooldown === value && styles.thresholdButtonActive,
              ]}
              onPress={() => setNotificationCooldown(value)}
            >
              <Text
                style={[
                  styles.thresholdButtonText,
                  notificationCooldown === value && styles.thresholdButtonTextActive,
                ]}
              >
                {value}m
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        <TouchableOpacity style={styles.saveButton} onPress={saveSettings}>
          <Text style={styles.saveButtonText}>Save Settings</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>📊 Data & Privacy</Text>

        <TouchableOpacity style={styles.actionButton} onPress={viewLocationHistory}>
          <Text style={styles.actionButtonText}>View Location History</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.actionButton, styles.dangerButton]}
          onPress={clearLocationHistory}
        >
          <Text style={[styles.actionButtonText, styles.dangerButtonText]}>
            Clear Location History
          </Text>
        </TouchableOpacity>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🌐 Learn More</Text>
        <Text style={styles.sectionDescription}>Explore Forseti's mission and technology</Text>

        <TouchableOpacity style={styles.linkButton} onPress={() => navigation.navigate('About')}>
          <Icon name="information" size={20} color={Colors.primary} style={styles.linkIcon} />
          <Text style={styles.linkButtonText}>About Forseti</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.linkButton}
          onPress={() => navigation.navigate('HowItWorks')}
        >
          <Icon name="lightbulb-on" size={20} color={Colors.primary} style={styles.linkIcon} />
          <Text style={styles.linkButtonText}>How It Works</Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.linkButton} onPress={() => navigation.navigate('Privacy')}>
          <Icon name="shield-check" size={20} color={Colors.primary} style={styles.linkIcon} />
          <Text style={styles.linkButtonText}>Privacy & Security</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.linkButton}
          onPress={() => Linking.openURL('https://forseti.life/contact')}
        >
          <Icon name="email" size={20} color={Colors.primary} style={styles.linkIcon} />
          <Text style={styles.linkButtonText}>Contact Us (Website)</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🐛 Developer Tools</Text>
        <Text style={styles.sectionDescription}>Debug and troubleshooting tools</Text>

        <TouchableOpacity 
          style={styles.linkButton} 
          onPress={() => navigation.navigate('DebugConsole')}
        >
          <Icon name="bug" size={20} color={Colors.warning} style={styles.linkIcon} />
          <Text style={styles.linkButtonText}>Debug Console</Text>
        </TouchableOpacity>

        <View style={{ marginTop: Spacing.md }}>
          <Text style={styles.settingLabel}>📍 Set Test Location</Text>
          <Text style={styles.settingDescription}>Enter H3 index for testing (e.g., 8b2aacb2e577fff)</Text>
          <TextInput
            style={styles.testLocationInput}
            value={testH3Index}
            onChangeText={setTestH3Index}
            placeholder="Enter H3 index..."
            placeholderTextColor={Colors.textSecondary}
            autoCapitalize="none"
            autoCorrect={false}
          />
          <TouchableOpacity 
            style={[styles.actionButton, { backgroundColor: Colors.primary, marginTop: Spacing.sm }]} 
            onPress={async () => {
              if (!testH3Index || testH3Index.length < 10) {
                Alert.alert('Invalid H3 Index', 'Please enter a valid H3 index (minimum 10 characters)');
                return;
              }
              DebugLogger.info(`📍 [DEBUG] Setting test location: ${testH3Index}`);
              await StorageService.setItem('test_h3_location', testH3Index);
              Alert.alert(
                '✅ Test Location Set', 
                `H3 Index: ${testH3Index}\n\nThis will be used for testing. Restart monitoring to activate.`,
                [{ text: 'OK' }]
              );
              DebugLogger.info('✅ [DEBUG] Test location saved to storage');
            }}
          >
            <Text style={styles.actionButtonText}>🎯 Set Test Location</Text>
          </TouchableOpacity>
          <TouchableOpacity 
            style={[styles.actionButton, { backgroundColor: Colors.gray, marginTop: Spacing.xs }]} 
            onPress={async () => {
              await StorageService.removeItem('test_h3_location');
              setTestH3Index('');
              Alert.alert('✅ Cleared', 'Test location cleared. Using real GPS location.');
              DebugLogger.info('🧹 [DEBUG] Test location cleared');
            }}
          >
            <Text style={styles.actionButtonText}>🧹 Clear Test Location</Text>
          </TouchableOpacity>
        </View>

        <TouchableOpacity 
          style={[styles.actionButton, { backgroundColor: Colors.warning, marginTop: Spacing.md }]} 
          onPress={() => {
            DebugLogger.info('🧪 [DEBUG] Simulating danger alert notification...');
            
            // Send real notification (NotificationService is already the singleton instance)
            NotificationService.sendSafetyAlert({
              id: `test-alert-${Date.now()}`,
              title: '⚠️ DANGER ALERT (TEST)',
              message: 'This is a test alert.\n\nZ-Score: 3.5\nIncident Count: 125\nRisk Level: EXTREME\n\nThis area has significantly higher crime than average.',
              type: 'high_crime_area',
              priority: 'high',
              timestamp: Date.now(),
              location: {
                latitude: 39.9526,
                longitude: -75.1652,
              },
            });
            
            DebugLogger.info('✅ [DEBUG] Test notification sent to system');
            Alert.alert('Test Notification Sent', 'Check your notification tray at the top of the screen.');
          }}
        >
          <Text style={styles.actionButtonText}>🚨 Simulate Danger Alert</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>ℹ️ About</Text>
        <Text style={styles.aboutText}>
          Forseti uses H3 geospatial hexagons to monitor your location.
          You can configure the monitoring resolution from street blocks (~325m) to ultra-precision (~6.6m).
          Safety alerts are based on crime statistics and z-scores calculated from historical
          incident data.
        </Text>
        <Text style={styles.aboutText}>
          All location data is stored locally on your device and is never shared with third parties.
        </Text>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  aboutText: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
    lineHeight: 20,
    marginBottom: Spacing.md,
  },
  resolutionButton: {
    alignItems: 'center',
    backgroundColor: Colors.surface,
    borderColor: Colors.border,
    borderRadius: Spacing.borderRadius.sm,
    borderWidth: 1,
    flex: 1,
    marginHorizontal: 4,
    paddingVertical: Spacing.sm,
  },
  resolutionButtonText: {
    ...Typography.body,
    color: Colors.text,
    fontWeight: '600',
  },
  resolutionInfo: {
    backgroundColor: Colors.surface,
    borderColor: Colors.border,
    borderRadius: Spacing.borderRadius.md,
    borderWidth: 1,
    marginTop: Spacing.md,
    padding: Spacing.md,
  },
  resolutionInfoText: {
    ...Typography.bodySmall,
    color: Colors.text,
    fontWeight: 'bold',
    marginBottom: Spacing.xs,
  },
  resolutionInfoDetail: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
    lineHeight: 18,
    marginBottom: 2,
  },
  actionButton: {
    alignItems: 'center',
    backgroundColor: Colors.primary,
    borderRadius: Spacing.borderRadius.md,
    marginVertical: Spacing.xs + 2,
    padding: Spacing.md + 2,
  },
  actionButtonText: {
    ...Typography.bodySmall,
    color: Colors.white,
    fontWeight: Typography.fontWeight.semibold,
  },
  container: {
    backgroundColor: Colors.lightGray,
    flex: 1,
  },
  dangerButton: {
    backgroundColor: Colors.white,
    borderColor: Colors.danger,
    borderWidth: 1,
  },
  dangerButtonText: {
    color: Colors.danger,
  },
  linkButton: {
    alignItems: 'center',
    backgroundColor: Colors.lightGray,
    borderColor: Colors.lightGray,
    borderRadius: Spacing.borderRadius.md,
    borderWidth: 1,
    flexDirection: 'row',
    marginVertical: Spacing.xs,
    padding: Spacing.md + 2,
  },
  linkButtonText: {
    color: Colors.primary,
    fontSize: 15,
    fontWeight: Typography.fontWeight.medium,
  },
  linkIcon: {
    marginRight: Spacing.md,
  },
  saveButton: {
    alignItems: 'center',
    backgroundColor: Colors.success,
    borderRadius: Spacing.borderRadius.md,
    marginTop: Spacing.sm,
    padding: Spacing.md + 2,
  },
  saveButtonText: {
    ...Typography.button,
    color: Colors.white,
  },
  section: {
    backgroundColor: Colors.white,
    borderBottomWidth: 1,
    borderColor: Colors.lightGray,
    borderTopWidth: 1,
    marginTop: Spacing.md,
    padding: Spacing.md,
  },
  sectionDescription: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
    marginBottom: Spacing.md,
  },
  sectionTitle: {
    ...Typography.heading3,
    marginBottom: Spacing.xs,
  },
  settingDescription: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
  },
  settingInfo: {
    flex: 1,
  },
  settingLabel: {
    ...Typography.body,
    fontWeight: Typography.fontWeight.semibold,
    marginBottom: Spacing.xs,
  },
  settingRow: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: Spacing.sm,
  },
  statusBox: {
    backgroundColor: '#e8f5e9',
    borderRadius: Spacing.borderRadius.md,
    marginTop: Spacing.md,
    padding: Spacing.md,
  },
  statusDescription: {
    ...Typography.caption,
    color: Colors.textSecondary,
  },
  statusLabel: {
    ...Typography.caption,
    color: Colors.textSecondary,
    marginBottom: Spacing.xs,
  },
  statusValue: {
    ...Typography.bodySmall,
    color: Colors.text,
    fontFamily: 'monospace',
    fontWeight: Typography.fontWeight.bold,
    marginBottom: Spacing.xs,
  },
  testLocationInput: {
    backgroundColor: Colors.white,
    borderColor: Colors.border,
    borderRadius: Spacing.borderRadius.md,
    borderWidth: 1,
    color: Colors.black,  // Black text on white background for contrast
    fontFamily: 'monospace',
    fontSize: 14,
    marginTop: Spacing.xs,
    padding: Spacing.md,
  },
  thresholdButton: {
    alignItems: 'center',
    backgroundColor: Colors.lightGray,
    borderRadius: Spacing.borderRadius.md,
    flex: 1,
    marginHorizontal: Spacing.xs,
    padding: Spacing.sm + 2,
  },
  thresholdButtonActive: {
    backgroundColor: Colors.primary,
  },
  thresholdButtonText: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
    fontWeight: Typography.fontWeight.semibold,
  },
  thresholdButtonTextActive: {
    color: Colors.white,
  },
  thresholdButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: Spacing.md,
    marginTop: Spacing.sm,
  },
});

// Catch-all error wrapper
const SettingsScreen = (props: any) => {
  try {
    DebugLogger.info('🔧 SettingsScreen wrapper called');
    return (
      <SettingsErrorBoundary>
        <SettingsScreenContent {...props} />
      </SettingsErrorBoundary>
    );
  } catch (error) {
    DebugLogger.error('🚨 CRITICAL: SettingsScreen wrapper crashed', error);
    DebugLogger.error('Wrapper error type: ' + typeof error);
    DebugLogger.error('Wrapper error: ' + (error instanceof Error ? error.message : String(error)));
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <Text>Critical Error - Check Debug Console</Text>
        <DebugConsole />
      </View>
    );
  }
};

export default SettingsScreen;
