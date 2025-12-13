/**
 * AmISafe Mobile Application
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
import MapScreen from './src/screens/Map/MapScreen';
import SafetyScreen from './src/screens/Safety/SafetyScreen';
import StatisticsScreen from './src/screens/Statistics/StatisticsScreen';
import ProfileScreen from './src/screens/Profile/ProfileScreen';

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
            case 'Safety':
              iconName = focused ? 'shield-check' : 'shield-check-outline';
              break;
            case 'Statistics':
              iconName = focused ? 'chart-line' : 'chart-line-variant';
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
        options={{ title: 'AmISafe' }}
      />
      <Tab.Screen 
        name="Map" 
        component={MapScreen}
        options={{ title: 'Safety Map' }}
      />
      <Tab.Screen 
        name="Safety" 
        component={SafetyScreen}
        options={{ title: 'Safety' }}
      />
      <Tab.Screen 
        name="Statistics" 
        component={StatisticsScreen}
        options={{ title: 'Statistics' }}
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
      console.log('🚀 Initializing AmISafe Mobile App...');

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
          'AmISafe needs location access to provide safety information for your area. Please enable location permissions in your device settings.',
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
      console.log('🎉 AmISafe Mobile App initialization complete!');

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