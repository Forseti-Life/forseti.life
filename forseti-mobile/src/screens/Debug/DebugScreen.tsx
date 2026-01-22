/**
 * Debug Screen
 *
 * Debugging and troubleshooting tools for development and support
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Alert,
  TextInput,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useConsoleLogs } from '../../hooks/useConsoleLogs';
import StorageService from '../../services/storage/StorageService';
import NotificationService from '../../services/notifications/NotificationService';
import { Theme } from '../../utils/theme';
import DebugConsole, { DebugLogger } from '../../components/DebugConsole';

const { Colors, Spacing, Typography, Shadows } = Theme;

const DebugScreen = ({ navigation }: any) => {
  DebugLogger.info('🔧 Debug screen mounted');

  // Console log functionality
  const {
    logCount,
    isUploading,
    uploadLogs,
    clearLogs: clearConsoleLogs,
    uploadError,
  } = useConsoleLogs();

  const [testH3Index, setTestH3Index] = useState('8b2a134f6cb5fff');

  useEffect(() => {
    DebugLogger.info('📥 Loading debug screen...');
  }, []);

  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => navigation.goBack()}
        >
          <Icon name="arrow-left" size={24} color={Colors.primary} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>🔧 Debug Tools</Text>
      </View>

      {/* Debug Console Access */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🐛 Development Tools</Text>
        <Text style={styles.sectionDescription}>Access debugging console and logs</Text>

        <TouchableOpacity
          style={styles.linkButton}
          onPress={() => navigation.navigate('DebugConsole')}
        >
          <Icon name="bug" size={20} color={Colors.warning} style={styles.linkIcon} />
          <Text style={styles.linkButtonText}>Debug Console</Text>
        </TouchableOpacity>
      </View>

      {/* Debug Log Management */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>📋 Debug Log Management</Text>
        <Text style={styles.sectionDescription}>
          Upload app debug logs to server for troubleshooting ({logCount} entries)
        </Text>

        <TouchableOpacity
          style={[
            styles.actionButton,
            {
              backgroundColor: isUploading ? Colors.gray : Colors.primary,
              marginTop: Spacing.sm,
            },
          ]}
          onPress={async () => {
            const success = await uploadLogs();
            if (success) {
              Alert.alert('✅ Success', 'Debug logs uploaded successfully!');
            } else {
              Alert.alert('❌ Upload Failed', uploadError || 'Unknown error occurred');
            }
          }}
          disabled={isUploading}
        >
          <Text style={styles.actionButtonText}>
            {isUploading ? '📤 Uploading...' : '📤 Upload Debug Logs'}
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.actionButton, { backgroundColor: Colors.gray, marginTop: Spacing.xs }]}
          onPress={() => {
            Alert.alert(
              'Clear Debug Logs?',
              `This will clear ${logCount} log entries from the app. This action cannot be undone.`,
              [
                { text: 'Cancel', style: 'cancel' },
                {
                  text: 'Clear',
                  style: 'destructive',
                  onPress: async () => {
                    await clearConsoleLogs();
                    Alert.alert('✅ Cleared', 'Debug logs have been cleared.');
                  },
                },
              ]
            );
          }}
        >
          <Text style={styles.actionButtonText}>🧹 Clear Debug Logs</Text>
        </TouchableOpacity>
      </View>

      {/* Test Location Setting */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>📍 Location Override for Testing</Text>
        <Text style={styles.sectionDescription}>
          Override your GPS location with a specific H3 hex index for testing safety alerts.
          
          • "Set Test Location" - Saves the H3 index and overrides GPS (restart monitoring to activate)
          • "Clear Test Location" - Removes override and returns to real GPS location
          
          Default: Philadelphia high-crime area for testing
        </Text>
        <TextInput
          style={styles.testLocationInput}
          value={testH3Index}
          onChangeText={setTestH3Index}
          placeholder="8b2a134f6cb5fff"
          placeholderTextColor={Colors.textSecondary}
          autoCapitalize="none"
          autoCorrect={false}
        />
        <TouchableOpacity
          style={[
            styles.actionButton,
            { backgroundColor: Colors.primary, marginTop: Spacing.sm },
          ]}
          onPress={async () => {
            if (!testH3Index || testH3Index.length < 10) {
              Alert.alert(
                'Invalid H3 Index',
                'Please enter a valid H3 index (minimum 10 characters)'
              );
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
            setTestH3Index('8b2a134f6cb5fff'); // Reset to default instead of empty
            Alert.alert('✅ Cleared', 'Test location cleared. Using real GPS location.');
            DebugLogger.info('🧹 [DEBUG] Test location cleared');
          }}
        >
          <Text style={styles.actionButtonText}>🧹 Clear Test Location</Text>
        </TouchableOpacity>
      </View>

      {/* Notification Testing */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>🔔 Notification Testing</Text>
        <Text style={styles.sectionDescription}>Test notification functionality</Text>

        <TouchableOpacity
          style={[styles.actionButton, { backgroundColor: Colors.success, marginTop: Spacing.sm }]}
          onPress={() => {
            DebugLogger.info('🧪 [DEBUG] Sending basic test notification...');
            
            NotificationService.sendBasicTestNotification();
            
            DebugLogger.info('✅ [DEBUG] Basic test notification sent');
            Alert.alert('Basic Test Sent', 'Check notification tray - this uses minimal notification settings');
          }}
        >
          <Text style={styles.actionButtonText}>🧪 Basic Notification Test</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.actionButton, { backgroundColor: Colors.gray, marginTop: Spacing.xs }]}
          onPress={() => {
            DebugLogger.info('🧪 [DEBUG] Testing notification channels...');
            
            NotificationService.testChannels();
            
            DebugLogger.info('✅ [DEBUG] Channel test initiated');
            Alert.alert('Channel Test Sent', 'Testing notification channels - check logs and notification tray');
          }}
        >
          <Text style={styles.actionButtonText}>🔧 Test Notification Channels</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.actionButton, { backgroundColor: Colors.warning, marginTop: Spacing.sm }]}
          onPress={() => {
            DebugLogger.info('🧪 [DEBUG] Simulating danger alert notification...');

            NotificationService.sendSafetyAlert({
              id: `test-alert-${Date.now()}`,
              title: '⚠️ DANGER ALERT (TEST)',
              message:
                'This is a test alert.\n\nZ-Score: 3.5\nIncident Count: 125\nRisk Level: EXTREME\n\nThis area has significantly higher crime than average.',
              type: 'high_crime_area',
              priority: 'high',
              timestamp: Date.now(),
              location: {
                latitude: 39.9526,
                longitude: -75.1652,
              },
            });

            DebugLogger.info('✅ [DEBUG] Test notification sent to system');
            Alert.alert(
              'Test Notification Sent',
              'Check your notification tray at the top of the screen.'
            );
          }}
        >
          <Text style={styles.actionButtonText}>🚨 Simulate Danger Alert</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.actionButton, { backgroundColor: Colors.info, marginTop: Spacing.sm }]}
          onPress={() => {
            DebugLogger.info('🧪 [DEBUG] Sending simple test notification...');

            NotificationService.sendSafetyAlert({
              id: `simple-test-${Date.now()}`,
              title: '🔔 Simple Test',
              message: 'This is a basic notification test from Forseti',
              type: 'safety_tip',
              priority: 'medium',
              timestamp: Date.now(),
            });

            DebugLogger.info('✅ [DEBUG] Simple notification sent');
            Alert.alert('Simple Test Sent', 'Check notification tray');
          }}
        >
          <Text style={styles.actionButtonText}>🔔 Simple Notification Test</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.actionButton, { backgroundColor: Colors.primary, marginTop: Spacing.sm }]}
          onPress={async () => {
            DebugLogger.info('🧪 [DEBUG] Sending native Android test notification...');
            
            try {
              await NotificationService.sendNativeTestNotification();
              DebugLogger.info('✅ [DEBUG] Native test notification sent');
              Alert.alert('Native Test Sent', 'Native Android notification sent - check notification tray!');
            } catch (error) {
              DebugLogger.error('❌ [DEBUG] Native test failed:', error);
              Alert.alert('Native Test Failed', `Error: ${error?.message || error}`);
            }
          }}
        >
          <Text style={styles.actionButtonText}>🤖 Native Android Test</Text>
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: Colors.lightGray,
    flex: 1,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.md,
    backgroundColor: Colors.white,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
    ...Shadows.light,
  },
  backButton: {
    padding: Spacing.xs,
    marginRight: Spacing.md,
  },
  headerTitle: {
    ...Typography.title,
    color: Colors.text,
    fontWeight: Typography.fontWeight.bold,
  },
  section: {
    backgroundColor: Colors.white,
    margin: Spacing.md,
    marginBottom: Spacing.sm,
    padding: Spacing.md,
    borderRadius: Spacing.borderRadius.md,
    ...Shadows.light,
  },
  sectionTitle: {
    ...Typography.title,
    color: Colors.text,
    fontWeight: Typography.fontWeight.bold,
    marginBottom: Spacing.xs,
  },
  sectionDescription: {
    ...Typography.body,
    color: Colors.textSecondary,
    marginBottom: Spacing.md,
    lineHeight: 20,
  },
  linkButton: {
    alignItems: 'center',
    backgroundColor: Colors.surface,
    borderColor: Colors.border,
    borderRadius: Spacing.borderRadius.md,
    borderWidth: 1,
    flexDirection: 'row',
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.sm + 2,
  },
  linkIcon: {
    marginRight: Spacing.sm,
  },
  linkButtonText: {
    ...Typography.body,
    color: Colors.text,
    fontWeight: Typography.fontWeight.medium,
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
  testLocationInput: {
    backgroundColor: Colors.white,
    borderColor: Colors.border,
    borderRadius: Spacing.borderRadius.md,
    borderWidth: 1,
    color: Colors.black, // Ensure black text for visibility
    fontSize: Typography.body.fontSize,
    fontFamily: 'monospace',
    marginBottom: Spacing.sm,
    paddingHorizontal: Spacing.md,
    paddingVertical: Spacing.sm,
  },
  diagnosticsContainer: {
    backgroundColor: Colors.surface,
    borderColor: Colors.border,
    borderRadius: Spacing.borderRadius.md,
    borderWidth: 1,
    padding: Spacing.md,
  },
  diagnosticItem: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: Spacing.xs,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  diagnosticLabel: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
    flex: 1,
  },
  diagnosticValue: {
    ...Typography.bodySmall,
    fontWeight: Typography.fontWeight.semibold,
    textAlign: 'right',
    flex: 1,
  },
});

export default DebugScreen;