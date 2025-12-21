/**
 * Forseti Mobile Application - Web Version
 * Main App Component for web preview (excludes native-only features)
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
  Platform,
  View,
  Text,
} from 'react-native';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createStackNavigator } from '@react-navigation/stack';

// Screens (excluding CrimeMapScreen for web)
import HomeScreen from './src/screens/Home/HomeScreen';
import CommunityScreen from './src/screens/Community/CommunityScreen';
import SafetyFactorsScreen from './src/screens/SafetyFactors/SafetyFactorsScreen';
import ProfileScreen from './src/screens/Profile/ProfileScreen';
import ChatScreen from './src/screens/Chat/ChatScreen';
import ConversationListScreen from './src/screens/Chat/ConversationListScreen';
import { AboutScreen } from './src/screens/About';
import { HowItWorksScreen } from './src/screens/HowItWorks';
import { PrivacyScreen } from './src/screens/Privacy';
import SettingsScreen from './src/screens/Settings/SettingsScreen';

// Utils
import { Colors } from './src/utils/colors';

const Tab = createBottomTabNavigator();
const Stack = createStackNavigator();

// Placeholder for Map on Web
const MapPlaceholderScreen = () => (
  <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: Colors.background }}>
    <Text style={{ color: Colors.text, fontSize: 18, textAlign: 'center', padding: 20 }}>
      🗺️ Crime Map Feature{'\n\n'}
      Interactive maps are available in the native mobile app.{'\n\n'}
      This web preview shows other features of the Forseti app.
    </Text>
  </View>
);

// Chat Stack Navigator
function ChatStack() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: {
          backgroundColor: Colors.primary,
        },
        headerTintColor: Colors.white,
        headerTitleStyle: {
          fontWeight: 'bold',
        },
      }}
    >
      <Stack.Screen 
        name="ConversationList" 
        component={ConversationListScreen}
        options={{ title: 'AI Conversations' }}
      />
      <Stack.Screen 
        name="Chat" 
        component={ChatScreen}
        options={{ title: 'Chat' }}
      />
    </Stack.Navigator>
  );
}

// Settings Stack Navigator
function SettingsStack() {
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: {
          backgroundColor: Colors.primary,
        },
        headerTintColor: Colors.white,
        headerTitleStyle: {
          fontWeight: 'bold',
        },
      }}
    >
      <Stack.Screen 
        name="SettingsMain" 
        component={SettingsScreen}
        options={{ title: 'Settings' }}
      />
      <Stack.Screen 
        name="About" 
        component={AboutScreen}
        options={{ title: 'About Forseti' }}
      />
      <Stack.Screen 
        name="HowItWorks" 
        component={HowItWorksScreen}
        options={{ title: 'How It Works' }}
      />
      <Stack.Screen 
        name="Privacy" 
        component={PrivacyScreen}
        options={{ title: 'Privacy Policy' }}
      />
    </Stack.Navigator>
  );
}

// Custom Tab Bar Icon component
const TabIcon = ({ name, focused }: { name: string; focused: boolean }) => {
  const iconMap: {[key: string]: string} = {
    Home: '🏠',
    Map: '🗺️',
    Chat: '💬',
    Community: '👥',
    Safety: '🛡️',
    Profile: '👤',
  };
  
  return (
    <Text style={{ fontSize: 24, opacity: focused ? 1 : 0.5 }}>
      {iconMap[name] || '•'}
    </Text>
  );
};

function App(): React.JSX.Element {
  const isDarkMode = useColorScheme() === 'dark';

  const navigationTheme = {
    ...DefaultTheme,
    colors: {
      ...DefaultTheme.colors,
      primary: Colors.primary,
      background: Colors.background,
      card: Colors.surface,
      text: Colors.text,
      border: Colors.border,
    },
  };

  useEffect(() => {
    console.log('Forseti Mobile (Web) - Application loaded');
  }, []);

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar
        barStyle={isDarkMode ? 'light-content' : 'dark-content'}
        backgroundColor={Colors.primary}
      />
      <NavigationContainer theme={navigationTheme}>
        <Tab.Navigator
          screenOptions={{
            tabBarStyle: {
              backgroundColor: Colors.surface,
              borderTopColor: Colors.border,
            },
            tabBarActiveTintColor: Colors.primary,
            tabBarInactiveTintColor: Colors.textSecondary,
            headerStyle: {
              backgroundColor: Colors.primary,
            },
            headerTintColor: Colors.white,
            headerTitleStyle: {
              fontWeight: 'bold',
            },
          }}
        >
          <Tab.Screen
            name="Home"
            component={HomeScreen}
            options={{
              title: 'Forseti',
              tabBarIcon: ({ focused }) => <TabIcon name="Home" focused={focused} />,
            }}
          />
          <Tab.Screen
            name="Map"
            component={MapPlaceholderScreen}
            options={{
              title: 'Crime Map',
              tabBarIcon: ({ focused }) => <TabIcon name="Map" focused={focused} />,
            }}
          />
          <Tab.Screen
            name="Chat"
            component={ChatStack}
            options={{
              headerShown: false,
              title: 'AI Chat',
              tabBarIcon: ({ focused }) => <TabIcon name="Chat" focused={focused} />,
            }}
          />
          <Tab.Screen
            name="Community"
            component={CommunityScreen}
            options={{
              title: 'Community',
              tabBarIcon: ({ focused }) => <TabIcon name="Community" focused={focused} />,
            }}
          />
          <Tab.Screen
            name="Safety"
            component={SafetyFactorsScreen}
            options={{
              title: 'Safety Factors',
              tabBarIcon: ({ focused }) => <TabIcon name="Safety" focused={focused} />,
            }}
          />
          <Tab.Screen
            name="Profile"
            component={SettingsStack}
            options={{
              headerShown: false,
              title: 'Profile',
              tabBarIcon: ({ focused }) => <TabIcon name="Profile" focused={focused} />,
            }}
          />
        </Tab.Navigator>
      </NavigationContainer>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
});

export default App;
