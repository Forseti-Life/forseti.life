/**
 * Forseti Mobile Application
 * Main App Component - Entry point for React Native application
 * 
 * @format
 */

import React, { useEffect, useState } from 'react';
import {
  SafeAreaView,
  StatusBar,
  StyleSheet,
  useColorScheme,
  Alert,
  PermissionsAndroid,
  Platform,
} from 'react-native';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createStackNavigator } from '@react-navigation/stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

// Screens
import HomeScreen from './src/screens/Home/HomeScreen';
import CrimeMapScreen from './src/screens/CrimeMapScreen';
import CommunityScreen from './src/screens/Community/CommunityScreen';
import SafetyFactorsScreen from './src/screens/SafetyFactors/SafetyFactorsScreen';
import ProfileScreen from './src/screens/Profile/ProfileScreen';
import ChatScreen from './src/screens/Chat/ChatScreen';
import ConversationListScreen from './src/screens/Chat/ConversationListScreen';
import { AboutScreen } from './src/screens/About';
import { HowItWorksScreen } from './src/screens/HowItWorks';
import { PrivacyScreen } from './src/screens/Privacy';
import SettingsScreen from './src/screens/Settings/SettingsScreen';

// Services
import LocationService from './src/services/location/LocationService';
import StorageService from './src/services/storage/StorageService';
// import NotificationService from './src/services/notifications/NotificationService'; // Temporarily disabled

// Utils
import { Colors } from './src/utils/colors';
import { requestLocationPermission } from './src/utils/permissions';

// Debug: Log Colors on module load
console.log('🎨 [DEBUG] Colors object loaded:', Colors);
console.log('🎨 [DEBUG] Colors.background:', Colors?.background);
console.log('🎨 [DEBUG] Colors type:', typeof Colors);

const Tab = createBottomTabNavigator();
const Stack = createStackNavigator();

// Custom navigation theme using Forseti colors with fallbacks
const ForsetiNavigationTheme = {
  ...DefaultTheme,
  dark: true,
  colors: {
    ...DefaultTheme.colors,
    primary: Colors?.primary || '#00d4ff',
    background: Colors?.background || '#1a1a2e',
    card: Colors?.card || '#16213e',
    text: Colors?.text || '#ffffff',
    border: Colors?.border || '#2a3f5f',
    notification: Colors?.primary || '#00d4ff',
  },
};

// Main Tab Navigator
const TabNavigator = () => {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ focused, color, size }) => {
          let iconName: string;

          switch (route.name) {
            case 'Home':
              iconName = focused ? 'home' : 'home-outline';
              break;
            case 'Map':
              iconName = focused ? 'map' : 'map-outline';
              break;
            case 'Chat':
              iconName = focused ? 'robot' : 'robot-outline';
              break;
            case 'Community':
              iconName = focused ? 'account-group' : 'account-group-outline';
              break;
            case 'SafetyFactors':
              iconName = focused ? 'information' : 'information-outline';
              break;
            case 'Profile':
              iconName = focused ? 'account' : 'account-outline';
              break;
            default:
              iconName = 'help-circle-outline';
          }

          return <Icon name={iconName} size={size} color={color} />;
        },
        tabBarActiveTintColor: Colors?.primary || '#00d4ff',
        tabBarInactiveTintColor: Colors?.gray || '#6c757d',
        tabBarStyle: styles.tabBar,
        headerStyle: {
          backgroundColor: Colors?.primary || '#00d4ff',
        },
        headerTintColor: Colors?.white || '#ffffff',
        headerTitleStyle: {
          fontWeight: 'bold',
        },
      })}
    >
      <Tab.Screen 
        name="Home" 
        component={HomeScreen}
        options={{ title: 'Forseti' }}
      />
      <Tab.Screen 
        name="Map" 
        component={CrimeMapScreen}
        options={{ title: 'Safety Map', headerShown: false }}
      />
      <Tab.Screen 
        name="Chat" 
        component={ChatScreen}
        options={{ title: 'Talk with Forseti', headerShown: false }}
      />
      <Tab.Screen 
        name="Community" 
        component={CommunityScreen}
        options={{ title: 'Community' }}
      />
      <Tab.Screen 
        name="SafetyFactors" 
        component={SafetyFactorsScreen}
        options={{ title: 'Safety Factors' }}
      />
      <Tab.Screen 
        name="Profile" 
        component={ProfileScreen}
        options={{ title: 'Profile' }}
      />
    </Tab.Navigator>
  );
};

const App: React.FC = () => {
  const isDarkMode = useColorScheme() === 'dark';
  const [isInitialized, setIsInitialized] = useState(false);
  const [hasLocationPermission, setHasLocationPermission] = useState(false);
  const [initError, setInitError] = useState<string | null>(null);

  const backgroundStyle = {
    backgroundColor: isDarkMode ? (Colors?.darker || '#000000') : (Colors?.lighter || '#ffffff'),
    flex: 1,
  };

  useEffect(() => {
    initializeApp();
  }, []);

  const initializeApp = async () => {
    try {
      console.log('🚀 [INIT STEP 1] Starting Forseti Mobile App initialization...');

      // Initialize storage service
      try {
        console.log('🚀 [INIT STEP 2] Initializing storage service...');
        await StorageService.initialize();
        console.log('✅ [INIT STEP 2] Storage service initialized');
      } catch (error) {
        console.error('❌ [INIT STEP 2] Storage initialization failed:', error);
        setInitError(`Storage initialization failed: ${error}`);
        throw error;
      }

      // Request location permissions
      try {
        console.log('🚀 [INIT STEP 3] Requesting location permissions...');
        const locationGranted = await requestLocationPermission();
        setHasLocationPermission(locationGranted);
        console.log(`✅ [INIT STEP 3] Location permission: ${locationGranted}`);
        
        if (locationGranted) {
          // Initialize location service
          try {
            console.log('🚀 [INIT STEP 4] Initializing location service...');
            await LocationService.initialize();
            console.log('✅ [INIT STEP 4] Location service initialized');
          } catch (error) {
            console.error('❌ [INIT STEP 4] Location service initialization failed:', error);
            setInitError(`Location service initialization failed: ${error}`);
            throw error;
          }
        } else {
          console.warn('⚠️ [INIT STEP 3] Location permission denied - continuing without location');
        }
      } catch (error) {
        console.error('❌ [INIT STEP 3] Permission request failed:', error);
        setInitError(`Permission request failed: ${error}`);
        throw error;
      }

      // Initialize notification service
      console.log('✅ [INIT STEP 5] Notification service skipped (not implemented yet)');

      // Load user preferences
      try {
        console.log('🚀 [INIT STEP 6] Loading user preferences...');
        const userPreferences = await StorageService.getItem('userPreferences');
        if (userPreferences) {
          console.log('✅ [INIT STEP 6] User preferences loaded');
        } else {
          console.log('ℹ️ [INIT STEP 6] No user preferences found (first run)');
        }
      } catch (error) {
        console.error('❌ [INIT STEP 6] Preferences load failed (non-critical):', error);
        // Don't throw - preferences are optional
      }

      setIsInitialized(true);
      console.log('🎉 [INIT COMPLETE] Forseti Mobile App initialization complete!');

    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      console.error('❌ [INIT FAILED] App initialization failed:', error);
      console.error('❌ [INIT FAILED] Error stack:', error instanceof Error ? error.stack : 'No stack trace');
      setInitError(`App initialization failed: ${errorMessage}`);
      setIsInitialized(true); // Still show UI with error message
    }
  };

  if (!isInitialized) {
    // Show splash/loading screen
    return (
      <SafeAreaView style={[backgroundStyle, { justifyContent: 'center', alignItems: 'center', padding: 20 }]}>
        <StatusBar
          barStyle={isDarkMode ? 'light-content' : 'dark-content'}
          backgroundColor={Colors.primary}
        />
      </SafeAreaView>
    );
  }

  // Show error screen if initialization failed
  if (initError) {
    const Text = require('react-native').Text;
    const View = require('react-native').View;
    const ScrollView = require('react-native').ScrollView;
    const Button = require('react-native').Button;
    return (
      <SafeAreaView style={[backgroundStyle, { flex: 1 }]}>
        <StatusBar
          barStyle={isDarkMode ? 'light-content' : 'dark-content'}
          backgroundColor="#EF4444"
        />
        <ScrollView style={{ flex: 1, padding: 20 }}>
          <View style={{ backgroundColor: '#FEE2E2', padding: 16, borderRadius: 8, marginBottom: 16 }}>
            <Text style={{ fontSize: 20, fontWeight: 'bold', color: '#DC2626', marginBottom: 8 }}>⚠️ Initialization Error</Text>
            <Text style={{ fontSize: 14, color: '#991B1B', marginBottom: 16 }}>{initError}</Text>
            <Button title="Retry" onPress={() => { setInitError(null); setIsInitialized(false); initializeApp(); }} color="#DC2626" />
          </View>
          <View style={{ backgroundColor: '#F3F4F6', padding: 16, borderRadius: 8 }}>
            <Text style={{ fontSize: 16, fontWeight: 'bold', color: '#374151', marginBottom: 8 }}>Debug Information</Text>
            <Text style={{ fontSize: 12, color: '#6B7280', fontFamily: 'monospace' }}>Check console logs for detailed error information.</Text>
          </View>
        </ScrollView>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={backgroundStyle}>
      <StatusBar
        barStyle={isDarkMode ? 'light-content' : 'dark-content'}
        backgroundColor={Colors?.primary || '#00d4ff'}
      />
      <NavigationContainer theme={ForsetiNavigationTheme}>
        <Stack.Navigator
          screenOptions={{
            headerShown: false,
          }}
        >
          <Stack.Screen name="MainTabs" component={TabNavigator} />
          <Stack.Screen 
            name="ConversationList" 
            component={ConversationListScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors?.primary || '#00d4ff' },
              headerTintColor: Colors?.white || '#ffffff',
              headerTitle: 'Conversations',
            }}
          />
          <Stack.Screen 
            name="About" 
            component={AboutScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors?.primary || '#00d4ff' },
              headerTintColor: Colors?.white || '#ffffff',
              headerTitle: 'About Forseti',
            }}
          />
          <Stack.Screen 
            name="HowItWorks" 
            component={HowItWorksScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors?.primary || '#00d4ff' },
              headerTintColor: Colors?.white || '#ffffff',
              headerTitle: 'How It Works',
            }}
          />
          <Stack.Screen 
            name="Privacy" 
            component={PrivacyScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors?.primary || '#00d4ff' },
              headerTintColor: Colors?.white || '#ffffff',
              headerTitle: 'Privacy & Security',
            }}
          />
          <Stack.Screen 
            name="Settings" 
            component={SettingsScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors?.primary || '#00d4ff' },
              headerTintColor: Colors?.white || '#ffffff',
              headerTitle: 'Settings',
            }}
          />
        </Stack.Navigator>
      </NavigationContainer>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  tabBar: {
    backgroundColor: Colors?.white || '#ffffff',
    borderTopColor: Colors?.lightGray || '#e9ecef',
    borderTopWidth: 1,
    paddingBottom: Platform.OS === 'ios' ? 20 : 5,
    paddingTop: 5,
    height: Platform.OS === 'ios' ? 85 : 60,
  },
});

export default App;