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
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useBackgroundMonitoring } from '../../hooks/useBackgroundMonitoring';
import StorageService from '../../services/storage/StorageService';
import { Theme } from '../../utils/theme';

const { Colors, Spacing, Typography, Shadows } = Theme;

const SettingsScreen = ({ navigation }: any) => {
  const {
    isMonitoring,
    currentH3Index,
    toggleMonitoring,
  } = useBackgroundMonitoring();

  const [zScoreThreshold, setZScoreThreshold] = useState(2.0);
  const [notificationCooldown, setNotificationCooldown] = useState(5);

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      const threshold = await StorageService.getData('z_score_threshold');
      const cooldown = await StorageService.getData('notification_cooldown');

      if (threshold !== null) setZScoreThreshold(threshold);
      if (cooldown !== null) setNotificationCooldown(cooldown);
    } catch (error) {
      console.error('Error loading settings:', error);
    }
  };

  const saveSettings = async () => {
    try {
      await StorageService.saveData('z_score_threshold', zScoreThreshold);
      await StorageService.saveData('notification_cooldown', notificationCooldown);

      Alert.alert('Settings Saved', 'Your preferences have been updated.', [
        { text: 'OK' },
      ]);
    } catch (error) {
      console.error('Error saving settings:', error);
      Alert.alert('Error', 'Failed to save settings. Please try again.', [
        { text: 'OK' },
      ]);
    }
  };

  const viewLocationHistory = async () => {
    try {
      const history = await StorageService.getData('location_history');
      const count = history ? history.length : 0;
      
      Alert.alert(
        'Location History',
        `You have ${count} location records stored.\n\nThis data is used to improve your safety alerts and is stored locally on your device.`,
        [{ text: 'OK' }]
      );
    } catch (error) {
      console.error('Error viewing location history:', error);
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
              await StorageService.saveData('location_history', []);
              Alert.alert('History Cleared', 'Your location history has been deleted.', [
                { text: 'OK' },
              ]);
            } catch (error) {
              console.error('Error clearing history:', error);
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
        <Text style={styles.sectionDescription}>
          Get alerted when entering high-crime areas
        </Text>

        <View style={styles.settingRow}>
          <View style={styles.settingInfo}>
            <Text style={styles.settingLabel}>Enable Protection</Text>
            <Text style={styles.settingDescription}>
              Monitor location in background
            </Text>
          </View>
          <Switch
            value={isMonitoring}
            onValueChange={toggleMonitoring}
            trackColor={{ false: Colors.gray, true: Colors.success }}
            thumbColor={isMonitoring ? Colors.white : Colors.lightGray}
          />
        </View>

        {isMonitoring && currentH3Index && (
          <View style={styles.statusBox}>
            <Text style={styles.statusLabel}>Current Location</Text>
            <Text style={styles.statusValue}>H3: {currentH3Index}</Text>
            <Text style={styles.statusDescription}>
              Monitoring at ~700m resolution
            </Text>
          </View>
        )}
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
          {[1.0, 1.5, 2.0, 2.5, 3.0].map((value) => (
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
          {[1, 5, 10, 15, 30].map((value) => (
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

        <TouchableOpacity
          style={styles.actionButton}
          onPress={viewLocationHistory}
        >
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
        <Text style={styles.sectionDescription}>
          Explore Forseti's mission and technology
        </Text>

        <TouchableOpacity
          style={styles.linkButton}
          onPress={() => navigation.navigate('About')}
        >
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

        <TouchableOpacity
          style={styles.linkButton}
          onPress={() => navigation.navigate('Privacy')}
        >
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
        <Text style={styles.sectionTitle}>ℹ️ About</Text>
        <Text style={styles.aboutText}>
          Forseti uses H3 geospatial hexagons at
          resolution 11 (~700m) to monitor your location. Safety alerts are based on 
          crime statistics and z-scores calculated from historical incident data.
        </Text>
        <Text style={styles.aboutText}>
          All location data is stored locally on your device and is never
          shared with third parties.
        </Text>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.lightGray,
  },
  section: {
    backgroundColor: Colors.white,
    marginTop: Spacing.md,
    padding: Spacing.md,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: Colors.lightGray,
  },
  sectionTitle: {
    ...Typography.heading3,
    marginBottom: Spacing.xs,
  },
  sectionDescription: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
    marginBottom: Spacing.md,
  },
  settingRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: Spacing.sm,
  },
  settingInfo: {
    flex: 1,
  },
  settingLabel: {
    ...Typography.body,
    fontWeight: Typography.fontWeight.semibold,
    marginBottom: Spacing.xs,
  },
  settingDescription: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
  },
  statusBox: {
    backgroundColor: '#e8f5e9',
    padding: Spacing.md,
    borderRadius: Spacing.borderRadius.md,
    marginTop: Spacing.md,
  },
  statusLabel: {
    ...Typography.caption,
    color: Colors.textSecondary,
    marginBottom: Spacing.xs,
  },
  statusValue: {
    ...Typography.bodySmall,
    fontWeight: Typography.fontWeight.bold,
    fontFamily: 'monospace',
    marginBottom: Spacing.xs,
  },
  statusDescription: {
    ...Typography.caption,
    color: Colors.textSecondary,
  },
  thresholdButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: Spacing.sm,
    marginBottom: Spacing.md,
  },
  thresholdButton: {
    flex: 1,
    padding: Spacing.sm + 2,
    marginHorizontal: Spacing.xs,
    backgroundColor: Colors.lightGray,
    borderRadius: Spacing.borderRadius.md,
    alignItems: 'center',
  },
  thresholdButtonActive: {
    backgroundColor: Colors.primary,
  },
  thresholdButtonText: {
    ...Typography.bodySmall,
    fontWeight: Typography.fontWeight.semibold,
    color: Colors.textSecondary,
  },
  thresholdButtonTextActive: {
    color: Colors.white,
  },
  saveButton: {
    backgroundColor: Colors.success,
    padding: Spacing.md + 2,
    borderRadius: Spacing.borderRadius.md,
    alignItems: 'center',
    marginTop: Spacing.sm,
  },
  saveButtonText: {
    ...Typography.button,
    color: Colors.white,
  },
  actionButton: {
    backgroundColor: Colors.primary,
    padding: Spacing.md + 2,
    borderRadius: Spacing.borderRadius.md,
    alignItems: 'center',
    marginVertical: Spacing.xs + 2,
  },
  actionButtonText: {
    ...Typography.bodySmall,
    fontWeight: Typography.fontWeight.semibold,
    color: Colors.white,
  },
  dangerButton: {
    backgroundColor: Colors.white,
    borderWidth: 1,
    borderColor: Colors.danger,
  },
  dangerButtonText: {
    color: Colors.danger,
  },
  linkButton: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: Spacing.md + 2,
    marginVertical: Spacing.xs,
    backgroundColor: Colors.lightGray,
    borderRadius: Spacing.borderRadius.md,
    borderWidth: 1,
    borderColor: Colors.lightGray,
  },
  linkIcon: {
    marginRight: Spacing.md,
  },
  linkButtonText: {
    fontSize: 15,
    fontWeight: Typography.fontWeight.medium,
    color: Colors.primary,
  },
  aboutText: {
    ...Typography.bodySmall,
    color: Colors.textSecondary,
    lineHeight: 20,
    marginBottom: Spacing.md,
  },
});

export default SettingsScreen;
