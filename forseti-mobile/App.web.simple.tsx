/**
 * Forseti Mobile - Simplified Web Version
 * Custom navigation without react-navigation complexity
 */

import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Image } from 'react-native';
import Icon from './src/components/Icon.web';
import { Colors } from './src/utils/colors';
import { SplashScreen } from './src/screens/Auth/SplashScreen';
import { LoginScreen } from './src/screens/Auth/LoginScreen';
import { RegisterScreen } from './src/screens/Auth/RegisterScreen';
import storageService from './src/services/storage/StorageService';

// Screens
import HomeScreen from './src/screens/Home/HomeScreen.web';
import ProfileScreen from './src/screens/Profile/ProfileScreen.web';
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

const App = () => {
  const [showSplash, setShowSplash] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [showRegister, setShowRegister] = useState(false);
  const [activeTab, setActiveTab] = useState('home');
  const [isInitialized, setIsInitialized] = useState(false);

  useEffect(() => {
    initializeApp();
  }, []);

  const initializeApp = async () => {
    try {
      // Initialize storage
      await storageService.initialize();
      
      // Check if user is logged in
      const userToken = await storageService.getItem('userToken');
      if (userToken) {
        setIsAuthenticated(true);
      }
      
      setIsInitialized(true);
    } catch (error) {
      console.error('Initialization error:', error);
      setIsInitialized(true);
    }
  };

  const handleSplashFinish = () => {
    setShowSplash(false);
  };

  const handleLoginSuccess = () => {
    setIsAuthenticated(true);
  };

  const handleShowRegister = () => {
    setShowRegister(true);
  };

  const handleBackToLogin = () => {
    setShowRegister(false);
  };

  // Show splash screen
  if (showSplash) {
    return <SplashScreen onFinish={handleSplashFinish} />;
  }

  // Show auth screens if not authenticated
  if (!isAuthenticated && isInitialized) {
    if (showRegister) {
      return <RegisterScreen navigation={{ navigate: handleBackToLogin }} />;
    }
    return (
      <LoginScreen 
        navigation={{ navigate: (screen: string) => {
          if (screen === 'Register') {
            handleShowRegister();
          }
        }}}
        onLoginSuccess={handleLoginSuccess}
      />
    );
  }

  // Show main app if authenticated
  const tabs = [
    { id: 'home', label: 'Home', icon: 'home-outline', iconActive: 'home', screen: HomeScreen, useIcon: true, hideFromTabBar: true },
    { id: 'map', label: 'Map', icon: 'map-outline', iconActive: 'map', screen: MapScreen, useIcon: true },
    { id: 'chat', label: 'AI', icon: 'robot-outline', iconActive: 'robot', screen: ChatScreen, useIcon: false, imageSource: require('./assets/images/forseti_chat.png') },
    {
      id: 'safety',
      label: 'Safety',
      icon: 'shield-check-outline',
      iconActive: 'shield-check',
      screen: SafetyScreen,
      useIcon: false,
      imageSource: require('./assets/images/forseti_safe.png')
    },
    {
      id: 'profile',
      label: 'Profile',
      icon: 'account-outline',
      iconActive: 'account',
      screen: ProfileScreen,
      useIcon: true
    },
  ];

  const ActiveScreen = tabs.find(t => t.id === activeTab)?.screen || HomeScreen;

  return (
    <View style={styles.container}>
      {/* Header Bar with Logo */}
      <View style={styles.headerBar}>
        <TouchableOpacity onPress={() => setActiveTab('home')} style={styles.logoContainer}>
          <Image
            source={require('./assets/images/forseti_logo_final.png')}
            style={styles.logo}
            resizeMode="contain"
          />
          <Text style={styles.logoText}>Forseti</Text>
        </TouchableOpacity>
      </View>

      {/* Screen Content */}
      <View style={styles.content}>
        <ActiveScreen />
      </View>

      {/* Bottom Tab Bar */}
      <View style={styles.tabBar}>
        {tabs.filter(tab => !tab.hideFromTabBar).map(tab => {
          const isActive = activeTab === tab.id;
          return (
            <TouchableOpacity
              key={tab.id}
              style={styles.tabButton}
              onPress={() => setActiveTab(tab.id)}
            >
              <View style={[styles.tabIconContainer, isActive && styles.tabIconActive]}>
                {tab.useIcon ? (
                  <Icon
                    name={isActive ? tab.iconActive : tab.icon}
                    size={24}
                    color={isActive ? Colors.primary : Colors.textSecondary}
                  />
                ) : (
                  <Image
                    source={tab.imageSource}
                    style={{
                      width: 24,
                      height: 24,
                      opacity: isActive ? 1 : 0.7,
                    }}
                    resizeMode="contain"
                  />
                )}
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
  headerBar: {
    backgroundColor: Colors.primary,
    height: 60,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  logoContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    cursor: 'pointer',
  },
  logo: {
    width: 40,
    height: 40,
    marginRight: 12,
  },
  logoText: {
    color: Colors.white,
    fontSize: 20,
    fontWeight: 'bold',
    letterSpacing: 0.5,
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
