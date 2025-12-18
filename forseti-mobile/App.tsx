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
import { NavigationContainer } from '@react-navigation/native';
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
import NotificationService from './src/services/notifications/NotificationService';

// Utils
import { Colors } from './src/utils/colors';
import { requestLocationPermission } from './src/utils/permissions';

const Tab = createBottomTabNavigator();
const Stack = createStackNavigator();

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
        tabBarActiveTintColor: Colors.primary,
        tabBarInactiveTintColor: Colors.gray,
        tabBarStyle: styles.tabBar,
        headerStyle: {
          backgroundColor: Colors.primary,
        },
        headerTintColor: Colors.white,
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

  const backgroundStyle = {
    backgroundColor: isDarkMode ? Colors.darker : Colors.lighter,
    flex: 1,
  };

  useEffect(() => {
    initializeApp();
  }, []);

  const initializeApp = async () => {
    try {
      console.log('🚀 Initializing Forseti Mobile App...');

      // Initialize storage service
      await StorageService.initialize();
      console.log('✅ Storage service initialized');

      // Request location permissions
      const locationGranted = await requestLocationPermission();
      setHasLocationPermission(locationGranted);
      
      if (locationGranted) {
        // Initialize location service
        await LocationService.initialize();
        console.log('✅ Location service initialized');
      } else {
        console.warn('⚠️ Location permission denied');
        Alert.alert(
          'Location Permission Required',
          'Forseti needs location access to provide safety information for your area. Please enable location permissions in your device settings.',
          [{ text: 'OK' }]
        );
      }

      // Initialize notification service
      await NotificationService.initialize();
      console.log('✅ Notification service initialized');

      // Load user preferences
      const userPreferences = await StorageService.getItem('userPreferences');
      if (userPreferences) {
        console.log('✅ User preferences loaded');
      }

      setIsInitialized(true);
      console.log('🎉 Forseti Mobile App initialization complete!');

    } catch (error) {
      console.error('❌ App initialization failed:', error);
      Alert.alert(
        'Initialization Error',
        'There was a problem starting the app. Please restart and try again.',
        [{ text: 'OK' }]
      );
    }
  };

  if (!isInitialized) {
    // You could show a splash screen here
    return null;
  }

  return (
    <SafeAreaView style={backgroundStyle}>
      <StatusBar
        barStyle={isDarkMode ? 'light-content' : 'dark-content'}
        backgroundColor={Colors.primary}
      />
      <NavigationContainer>
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
              headerStyle: { backgroundColor: Colors.primary },
              headerTintColor: Colors.white,
              headerTitle: 'Conversations',
            }}
          />
          <Stack.Screen 
            name="About" 
            component={AboutScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors.primary },
              headerTintColor: Colors.white,
              headerTitle: 'About Forseti',
            }}
          />
          <Stack.Screen 
            name="HowItWorks" 
            component={HowItWorksScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors.primary },
              headerTintColor: Colors.white,
              headerTitle: 'How It Works',
            }}
          />
          <Stack.Screen 
            name="Privacy" 
            component={PrivacyScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors.primary },
              headerTintColor: Colors.white,
              headerTitle: 'Privacy & Security',
            }}
          />
          <Stack.Screen 
            name="Settings" 
            component={SettingsScreen}
            options={{
              headerShown: true,
              headerStyle: { backgroundColor: Colors.primary },
              headerTintColor: Colors.white,
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
    backgroundColor: Colors.white,
    borderTopColor: Colors.lightGray,
    borderTopWidth: 1,
    paddingBottom: Platform.OS === 'ios' ? 20 : 5,
    paddingTop: 5,
    height: Platform.OS === 'ios' ? 85 : 60,
  },
});

export default App;