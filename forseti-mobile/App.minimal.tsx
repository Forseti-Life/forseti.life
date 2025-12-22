/**
 * Minimal App - Progressive loading to identify issues
 */

import React from 'react';
import {
  SafeAreaView,
  StatusBar,
  StyleSheet,
  View,
  Text,
  ScrollView,
  TouchableOpacity,
} from 'react-native';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createStackNavigator } from '@react-navigation/stack';
import { Colors } from './src/utils/colors';

// Import real screens
import HomeScreen from './src/screens/Home/HomeScreen';
import CommunityScreen from './src/screens/Community/CommunityScreen';
import SafetyFactorsScreen from './src/screens/SafetyFactors/SafetyFactorsScreen';
import ChatScreen from './src/screens/Chat/ChatScreen';
import ConversationListScreen from './src/screens/Chat/ConversationListScreen';
import SettingsScreen from './src/screens/Settings/SettingsScreen';
import { AboutScreen } from './src/screens/About';
import { HowItWorksScreen } from './src/screens/HowItWorks';
import { PrivacyScreen } from './src/screens/Privacy';

const Tab = createBottomTabNavigator();
const Stack = createStackNavigator();

// Simple placeholder screens for features not yet loaded
const SimpleHomeScreen = () => (
  <View style={styles.screen}>
    <Text style={styles.title}>🏠 Home</Text>
    <Text style={styles.text}>Welcome to Forseti Mobile</Text>
  </View>
);

const SimpleMapScreen = () => (
  <View style={styles.screen}>
    <Text style={styles.title}>🗺️ Crime Map</Text>
    <Text style={styles.text}>Interactive maps available in native app</Text>
  </View>
);

const SimpleChatScreen = () => (
  <View style={styles.screen}>
    <Text style={styles.title}>💬 AI Chat</Text>
    <Text style={styles.text}>Chat with Forseti AI assistant</Text>
  </View>
);

const SimpleCommunityScreen = () => (
  <View style={styles.screen}>
    <Text style={styles.title}>👥 Community</Text>
    <Text style={styles.text}>Connect with your neighbors</Text>
  </View>
);

const SimpleSafetyScreen = () => (
  <View style={styles.screen}>
    <Text style={styles.title}>🛡️ Safety Factors</Text>
    <Text style={styles.text}>View safety information</Text>
  </View>
);

const SimpleProfileScreen = () => (
  <View style={styles.screen}>
    <Text style={styles.title}>👤 Profile</Text>
    <Text style={styles.text}>Manage your settings</Text>
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
      <Stack.Screen name="Chat" component={ChatScreen} options={{ title: 'Chat' }} />
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
      <Stack.Screen name="About" component={AboutScreen} options={{ title: 'About Forseti' }} />
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

// Simple icon component
const TabIcon = ({ emoji, focused }: { emoji: string; focused: boolean }) => (
  <Text style={{ fontSize: 24, opacity: focused ? 1 : 0.5 }}>{emoji}</Text>
);

function App() {
  console.log('🎨 App rendering with Colors:', Colors);
  console.log('📱 SafeAreaView styles:', styles.container);

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

  console.log('🎨 Navigation theme:', navigationTheme);

  return (
    <View style={styles.container}>
      <StatusBar barStyle="light-content" backgroundColor={Colors.primary} />
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
              tabBarIcon: ({ focused }) => <TabIcon emoji="🏠" focused={focused} />,
            }}
          />
          <Tab.Screen
            name="Map"
            component={SimpleMapScreen}
            options={{
              title: 'Crime Map',
              tabBarIcon: ({ focused }) => <TabIcon emoji="🗺️" focused={focused} />,
            }}
          />
          <Tab.Screen
            name="Chat"
            component={ChatStack}
            options={{
              headerShown: false,
              title: 'AI Chat',
              tabBarIcon: ({ focused }) => <TabIcon emoji="💬" focused={focused} />,
            }}
          />
          <Tab.Screen
            name="Community"
            component={CommunityScreen}
            options={{
              title: 'Community',
              tabBarIcon: ({ focused }) => <TabIcon emoji="👥" focused={focused} />,
            }}
          />
          <Tab.Screen
            name="Safety"
            component={SafetyFactorsScreen}
            options={{
              title: 'Safety Factors',
              tabBarIcon: ({ focused }) => <TabIcon emoji="🛡️" focused={focused} />,
            }}
          />
          <Tab.Screen
            name="Profile"
            component={SettingsStack}
            options={{
              headerShown: false,
              title: 'Profile',
              tabBarIcon: ({ focused }) => <TabIcon emoji="👤" focused={focused} />,
            }}
          />
        </Tab.Navigator>
      </NavigationContainer>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: Colors.background,
    flex: 1,
  },
  screen: {
    alignItems: 'center',
    backgroundColor: Colors.background,
    flex: 1,
    justifyContent: 'center',
    padding: 20,
  },
  text: {
    color: Colors.textSecondary,
    fontSize: 16,
    textAlign: 'center',
  },
  title: {
    color: Colors.text,
    fontSize: 32,
    fontWeight: 'bold',
    marginBottom: 16,
  },
});

export default App;
