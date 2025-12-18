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
            trackColor={{ false: '#ccc', true: '#4CAF50' }}
            thumbColor={isMonitoring ? '#fff' : '#f4f3f4'}
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
          <Icon name="information" size={20} color="#2196F3" style={styles.linkIcon} />
          <Text style={styles.linkButtonText}>About Forseti</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.linkButton}
          onPress={() => navigation.navigate('HowItWorks')}
        >
          <Icon name="lightbulb-on" size={20} color="#2196F3" style={styles.linkIcon} />
          <Text style={styles.linkButtonText}>How It Works</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.linkButton}
          onPress={() => navigation.navigate('Privacy')}
        >
          <Icon name="shield-check" size={20} color="#2196F3" style={styles.linkIcon} />
          <Text style={styles.linkButtonText}>Privacy & Security</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.linkButton}
          onPress={() => Linking.openURL('https://forseti.life/contact')}
        >
          <Icon name="email" size={20} color="#2196F3" style={styles.linkIcon} />
          <Text style={styles.linkButtonText}>Contact Us (Website)</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>ℹ️ About</Text>
        <Text style={styles.aboutText}>
          AmISafe is powered by Forseti, using H3 geospatial hexagons at
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
    backgroundColor: '#f5f5f5',
  },
  section: {
    backgroundColor: '#fff',
    marginTop: 12,
    padding: 16,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: '#e0e0e0',
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  sectionDescription: {
    fontSize: 14,
    color: '#666',
    marginBottom: 16,
  },
  settingRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
  },
  settingInfo: {
    flex: 1,
  },
  settingLabel: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 4,
  },
  settingDescription: {
    fontSize: 14,
    color: '#666',
  },
  statusBox: {
    backgroundColor: '#e8f5e9',
    padding: 12,
    borderRadius: 8,
    marginTop: 12,
  },
  statusLabel: {
    fontSize: 12,
    color: '#666',
    marginBottom: 4,
  },
  statusValue: {
    fontSize: 14,
    fontWeight: 'bold',
    fontFamily: 'monospace',
    marginBottom: 4,
  },
  statusDescription: {
    fontSize: 12,
    color: '#666',
  },
  thresholdButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 8,
    marginBottom: 16,
  },
  thresholdButton: {
    flex: 1,
    padding: 10,
    marginHorizontal: 4,
    backgroundColor: '#f0f0f0',
    borderRadius: 8,
    alignItems: 'center',
  },
  thresholdButtonActive: {
    backgroundColor: '#2196F3',
  },
  thresholdButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
  },
  thresholdButtonTextActive: {
    color: '#fff',
  },
  saveButton: {
    backgroundColor: '#4CAF50',
    padding: 14,
    borderRadius: 8,
    alignItems: 'center',
    marginTop: 8,
  },
  saveButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  actionButton: {
    backgroundColor: '#2196F3',
    padding: 14,
    borderRadius: 8,
    alignItems: 'center',
    marginVertical: 6,
  },
  actionButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  dangerButton: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#f44336',
  },
  dangerButtonText: {
    color: '#f44336',
  },
  linkButton: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    marginVertical: 4,
    backgroundColor: '#f5f5f5',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e0e0e0',
  },
  linkIcon: {
    marginRight: 12,
  },
  linkButtonText: {
    fontSize: 15,
    fontWeight: '500',
    color: '#2196F3',
  },
  aboutText: {
    fontSize: 14,
    color: '#666',
    lineHeight: 20,
    marginBottom: 12,
  },
});

export default SettingsScreen;
