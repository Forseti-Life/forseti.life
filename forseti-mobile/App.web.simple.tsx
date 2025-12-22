/**
 * Forseti Mobile - Simplified Web Version
 * Custom navigation without react-navigation complexity
 */

import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import Icon from './src/components/Icon.web';
import { Colors } from './src/utils/colors';

// Screens
import HomeScreen from './src/screens/Home/HomeScreen.web';
import CommunityScreen from './src/screens/Community/CommunityScreen.web';

// Simple placeholder screens
const MapScreen = () => (
  <View style={styles.screenContainer}>
    <View style={styles.placeholderContent}>
      <Icon name="map" size={64} color={Colors.primary} />
      <Text style={styles.placeholderTitle}>Safety Map</Text>
      <Text style={styles.placeholderText}>
        Interactive crime maps available in native mobile app
      </Text>
    </View>
  </View>
);

const ChatScreen = () => (
  <View style={styles.screenContainer}>
    <View style={styles.placeholderContent}>
      <Icon name="robot" size={64} color={Colors.primary} />
      <Text style={styles.placeholderTitle}>AI Chat</Text>
      <Text style={styles.placeholderText}>Chat with Forseti AI assistant</Text>
    </View>
  </View>
);

const SafetyScreen = () => (
  <View style={styles.screenContainer}>
    <View style={styles.placeholderContent}>
      <Icon name="shield-check" size={64} color={Colors.primary} />
      <Text style={styles.placeholderTitle}>Safety Factors</Text>
      <Text style={styles.placeholderText}>Learn about safety factors in your area</Text>
    </View>
  </View>
);

const ProfileScreen = () => (
  <View style={styles.screenContainer}>
    <View style={styles.placeholderContent}>
      <Icon name="account" size={64} color={Colors.primary} />
      <Text style={styles.placeholderTitle}>Profile</Text>
      <Text style={styles.placeholderText}>Manage your account and settings</Text>
    </View>
  </View>
);

const App = () => {
  const [activeTab, setActiveTab] = useState('home');

  const tabs = [
    { id: 'home', label: 'Home', icon: 'home', iconActive: 'home', screen: HomeScreen },
    { id: 'map', label: 'Map', icon: 'map-outline', iconActive: 'map', screen: MapScreen },
    { id: 'chat', label: 'AI', icon: 'robot-outline', iconActive: 'robot', screen: ChatScreen },
    {
      id: 'community',
      label: 'Community',
      icon: 'account-group-outline',
      iconActive: 'account-group',
      screen: CommunityScreen,
    },
    {
      id: 'safety',
      label: 'Safety',
      icon: 'shield-check-outline',
      iconActive: 'shield-check',
      screen: SafetyScreen,
    },
    {
      id: 'profile',
      label: 'Profile',
      icon: 'account-outline',
      iconActive: 'account',
      screen: ProfileScreen,
    },
  ];

  const ActiveScreen = tabs.find(t => t.id === activeTab)?.screen || HomeScreen;

  return (
    <View style={styles.container}>
      {/* Screen Content */}
      <View style={styles.content}>
        <ActiveScreen />
      </View>

      {/* Bottom Tab Bar */}
      <View style={styles.tabBar}>
        {tabs.map(tab => {
          const isActive = activeTab === tab.id;
          return (
            <TouchableOpacity
              key={tab.id}
              style={styles.tabButton}
              onPress={() => setActiveTab(tab.id)}
            >
              <View style={[styles.tabIconContainer, isActive && styles.tabIconActive]}>
                <Icon
                  name={isActive ? tab.iconActive : tab.icon}
                  size={24}
                  color={isActive ? Colors.primary : Colors.textSecondary}
                />
              </View>
              <Text style={[styles.tabLabel, isActive && styles.tabLabelActive]}>{tab.label}</Text>
            </TouchableOpacity>
          );
        })}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: Colors.background,
    flex: 1,
    height: '100%',
    width: '100%',
  },
  content: {
    flex: 1,
    height: '100%',
    overflow: 'hidden',
    width: '100%',
  },
  placeholderContent: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
    padding: 40,
  },
  placeholderText: {
    color: Colors.textSecondary,
    fontSize: 14,
    lineHeight: 20,
    textAlign: 'center',
  },
  placeholderTitle: {
    color: Colors.text,
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 12,
    marginTop: 20,
  },
  screenContainer: {
    backgroundColor: Colors.background,
    flex: 1,
    height: '100%',
    width: '100%',
  },
  tabBar: {
    backgroundColor: Colors.surface,
    borderTopColor: Colors.border,
    borderTopWidth: 1,
    flexDirection: 'row',
    height: 70,
    paddingBottom: 8,
    paddingTop: 4,
  },
  tabButton: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
    paddingTop: 6,
  },
  tabIconActive: {
    backgroundColor: Colors.primary + '20',
  },
  tabIconContainer: {
    borderRadius: 20,
    paddingHorizontal: 16,
    paddingVertical: 6,
  },
  tabLabel: {
    color: Colors.textSecondary,
    fontSize: 11,
    marginTop: 4,
  },
  tabLabelActive: {
    color: Colors.primary,
    fontWeight: '600',
  },
});

export default App;
