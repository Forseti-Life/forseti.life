// AmISafe Mobile App - Main Application Component
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ScrollView,
  StatusBar,
  SafeAreaView,
  Modal,
  Switch,
} from 'react-native';

// Import AmISafe services
import { gpsLocationService } from './src/services/GPSLocationService';
import { h3LocationService, H3Utils, H3_RESOLUTIONS } from './src/services/H3LocationService';
import drupalAuthService from './src/services/DrupalAuthService';
import drupalCrimeService from './src/services/DrupalCrimeService';

// Import Screens
import CrimeMapScreen from './src/screens/CrimeMapScreen';

const App = () => {
  const [currentScreen, setCurrentScreen] = useState('splash'); // splash, login, register, dashboard, crimeMap
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isInitializing, setIsInitializing] = useState(true);
  
  // Login form state
  const [loginData, setLoginData] = useState({
    username: '',
    password: '',
  });
  
  // Registration form state
  const [registerData, setRegisterData] = useState({
    username: '',
    email: '',
    password: '',
    confirmPassword: '',
  });
  
  // Dashboard state
  const [safetyData, setSafetyData] = useState(null);
  const [currentLocation, setCurrentLocation] = useState(null);
  const [currentH3Index, setCurrentH3Index] = useState(null);
  const [locationPermission, setLocationPermission] = useState('unknown');
  const [isLocationTracking, setIsLocationTracking] = useState(false);
  const [showSettings, setShowSettings] = useState(false);
  const [settings, setSettings] = useState({
    notifications: true,
    locationSharing: true,
    autoCheck: false,
  });

  // Initialize app - check for existing authentication
  useEffect(() => {
    const initializeApp = async () => {
      try {
        console.log('🚀 Initializing AmISafe Mobile App...');
        
        // Check if user is already logged in
        if (drupalAuthService.isAuthenticated()) {
          const currentUser = drupalAuthService.getCurrentUser();
          if (currentUser) {
            setUser(currentUser);
            setCurrentScreen('dashboard');
            console.log('✅ User already authenticated:', currentUser.username);
          } else {
            setCurrentScreen('login');
          }
        } else {
          setCurrentScreen('login');
        }
        
        // Test API connection
        const connectionTest = await drupalCrimeService.testConnection();
        if (connectionTest.success) {
          console.log('✅ AmISafe API connection successful');
        } else {
          console.warn('⚠️ AmISafe API connection failed:', connectionTest.error);
        }
        
      } catch (error) {
        console.error('App initialization error:', error);
        setCurrentScreen('login');
      } finally {
        setIsInitializing(false);
      }
    };
    
    initializeApp();
  }, []);

  // Auto-initialize location services when user logs in
  useEffect(() => {
    if (user && currentScreen === 'dashboard') {
      initializeLocationServices();
    }
    
    return () => {
      // Cleanup location services
      if (isLocationTracking) {
        gpsLocationService.stopTracking();
      }
    };
  }, [user, currentScreen]);

  // Load safety data when location or H3 index changes
  useEffect(() => {
    if (user && currentScreen === 'dashboard' && currentLocation && currentH3Index) {
      loadSafetyData();
    }
  }, [currentLocation, currentH3Index, user, currentScreen]);

  // Initialize GPS and H3 location services
  const initializeLocationServices = async () => {
    try {
      console.log('🚀 Initializing AmISafe location services...');
      
      // Check location permission
      const permissionStatus = await gpsLocationService.checkPermissionStatus();
      setLocationPermission(permissionStatus);
      
      if (permissionStatus !== 'granted') {
        const granted = await gpsLocationService.requestLocationPermission();
        setLocationPermission(granted ? 'granted' : 'denied');
        
        if (!granted) {
          Alert.alert(
            'Location Required', 
            'AmISafe requires location access to provide safety alerts. Please enable location services.',
            [{ text: 'OK' }]
          );
          return;
        }
      }
      
      // Set up location update callbacks
      gpsLocationService.onLocationUpdate((location, h3Result) => {
        console.log(`📍 Location Update: [${location.lat.toFixed(6)}, ${location.lng.toFixed(6)}] → ${h3Result.currentH3}`);
        setCurrentLocation(location);
        setCurrentH3Index(h3Result.currentH3);
      });
      
      gpsLocationService.onLocationError((error) => {
        console.error('GPS Error:', error);
        Alert.alert('Location Error', 'Unable to get your location. Please check GPS settings.');
      });
      
      gpsLocationService.onH3Change((h3Result, location) => {
        console.log(`🔄 H3 Hexagon Changed: ${h3Result.previousH3} → ${h3Result.currentH3}`);
        // This will trigger safety data reload automatically via useEffect
      });
      
      // Start location tracking
      const trackingStarted = await gpsLocationService.startTracking();
      setIsLocationTracking(trackingStarted);
      
      if (trackingStarted) {
        console.log('✅ GPS tracking started successfully');
        Alert.alert('Location Active', 'AmISafe is now monitoring your location for safety alerts.');
      } else {
        console.error('❌ Failed to start GPS tracking');
        Alert.alert('Tracking Failed', 'Unable to start location tracking. Some features may not work properly.');
      }
      
    } catch (error) {
      console.error('Location Services Error:', error);
      Alert.alert('Setup Error', 'Failed to initialize location services.');
    }
  };

  const loadSafetyData = async () => {
    try {
      if (!currentLocation) {
        console.log('⏳ No location available for safety data');
        return;
      }
      
      console.log(`🛡️ Loading safety data for H3: ${currentH3Index}`);
      
      // Use real Drupal crime data service
      const data = await drupalCrimeService.getSafetyScore(
        currentLocation.lat,
        currentLocation.lng,
        currentH3Index
      );
      
      // Add H3 information to safety data
      const enhancedData = {
        ...data,
        h3Index: currentH3Index,
        h3Info: H3Utils.getNeighbors ? {
          neighbors: H3Utils.getNeighbors(currentH3Index, 1),
          resolution: H3_RESOLUTIONS.USER_TRACKING,
          area: H3Utils.area(H3_RESOLUTIONS.USER_TRACKING)
        } : null,
        location: `${currentLocation.lat.toFixed(6)}, ${currentLocation.lng.toFixed(6)}`,
        accuracy: currentLocation.accuracy || 'Unknown'
      };
      
      setSafetyData(enhancedData);
      console.log(`✅ Safety data loaded for ${enhancedData.location}`);
      
    } catch (error) {
      console.error('Load Safety Data Error:', error);
      Alert.alert('Error', 'Failed to load safety data');
    }
  };

  const handleLogin = async () => {
    if (!loginData.username || !loginData.password) {
      Alert.alert('Error', 'Please enter both username and password');
      return;
    }

    setIsLoading(true);
    try {
      console.log('🔐 Attempting login with Drupal authentication...');
      const result = await drupalAuthService.login(loginData.username, loginData.password);
      
      if (result.success) {
        setUser(result.user);
        setCurrentScreen('dashboard');
        Alert.alert('Welcome!', `Hello ${result.user.name}, you're now logged in to AmISafe.`);
        
        // Initialize location services after successful login
        initializeLocationServices();
      } else {
        throw new Error('Login failed');
      }
    } catch (error) {
      console.error('Login error:', error);
      Alert.alert('Login Failed', error.message);
    } finally {
      setIsLoading(false);
    }
  };

  const handleRegister = async () => {
    if (!registerData.username || !registerData.email || !registerData.password) {
      Alert.alert('Error', 'Please fill in all fields');
      return;
    }
    
    if (registerData.password !== registerData.confirmPassword) {
      Alert.alert('Error', 'Passwords do not match');
      return;
    }

    setIsLoading(true);
    try {
      console.log('📝 Attempting registration with Drupal...');
      const result = await drupalAuthService.register({
        username: registerData.username,
        email: registerData.email,
        password: registerData.password
      });
      
      if (result.success) {
        Alert.alert('Success', result.message, [
          { text: 'OK', onPress: () => setCurrentScreen('login') }
        ]);
        
        // Clear registration form
        setRegisterData({
          username: '',
          email: '',
          password: '',
          confirmPassword: '',
        });
      } else {
        throw new Error('Registration failed');
      }
      setRegisterData({ username: '', email: '', password: '', confirmPassword: '' });
    } catch (error) {
      Alert.alert('Registration Failed', error.message);
    } finally {
      setIsLoading(false);
    }
  };

  const handleLogout = () => {
    Alert.alert('Logout', 'Are you sure you want to logout?', [
      { text: 'Cancel', style: 'cancel' },
      { 
        text: 'Logout', 
        onPress: async () => {
          try {
            // Stop location tracking
            if (isLocationTracking) {
              gpsLocationService.stopTracking();
              setIsLocationTracking(false);
            }
            
            // Logout from Drupal
            await drupalAuthService.logout();
            
            // Reset all state
            setUser(null);
            setSafetyData(null);
            setCurrentLocation(null);
            setCurrentH3Index(null);
            setCurrentScreen('login');
            setLoginData({ username: '', password: '' });
            
            console.log('👋 User logged out from Drupal, location tracking stopped');
          } catch (error) {
            console.error('Logout error:', error);
            // Still reset local state even if server logout fails
            setUser(null);
            setCurrentScreen('login');
          }
        }
      }
    ]);
  };

  const getSafetyColor = (score) => {
    if (score >= 85) return '#4CAF50'; // Green
    if (score >= 70) return '#FF9800'; // Orange
    return '#F44336'; // Red
  };

  const getSafetyText = (score) => {
    if (score >= 85) return 'SAFE';
    if (score >= 70) return 'MODERATE';
    return 'CAUTION';
  };

  // Splash Screen - App Initialization
  if (isInitializing || currentScreen === 'splash') {
    return (
      <SafeAreaView style={styles.container}>
        <StatusBar barStyle="light-content" backgroundColor="#000000" />
        <View style={styles.splashContainer}>
          <View style={styles.splashContent}>
            <Text style={styles.splashTitle}>AmISafe</Text>
            <Text style={styles.splashSubtitle}>Ultra-Precision Crime Safety</Text>
            <Text style={styles.splashTagline}>H3 Geospatial • Real-time Data • Drupal Powered</Text>
            
            {isInitializing && (
              <View style={styles.loadingContainer}>
                <Text style={styles.loadingText}>Initializing...</Text>
                <Text style={styles.loadingSubtext}>Connecting to Drupal backend</Text>
              </View>
            )}
            
            {!isInitializing && currentScreen === 'splash' && (
              <View style={styles.splashButtons}>
                <TouchableOpacity 
                  style={styles.splashButton}
                  onPress={() => setCurrentScreen('login')}
                >
                  <Text style={styles.splashButtonText}>Get Started</Text>
                </TouchableOpacity>
              </View>
            )}
          </View>
        </View>
      </SafeAreaView>
    );
  }

  // Login Screen
  if (currentScreen === 'login') {
    return (
      <SafeAreaView style={styles.container}>
        <StatusBar barStyle="light-content" backgroundColor="#1976D2" />
        <ScrollView contentContainerStyle={styles.scrollContainer}>
          <View style={styles.header}>
            <Text style={styles.appTitle}>AmISafe</Text>
            <Text style={styles.tagline}>Stay Safe, Stay Informed</Text>
          </View>
          
          <View style={styles.form}>
            <Text style={styles.title}>Welcome Back</Text>
            
            <TextInput
              style={styles.input}
              placeholder="Username"
              value={loginData.username}
              onChangeText={(text) => setLoginData({...loginData, username: text})}
              autoCapitalize="none"
              placeholderTextColor="#999"
            />
            
            <TextInput
              style={styles.input}
              placeholder="Password"
              value={loginData.password}
              onChangeText={(text) => setLoginData({...loginData, password: text})}
              secureTextEntry
              placeholderTextColor="#999"
            />
            
            <TouchableOpacity 
              style={[styles.button, isLoading && styles.buttonDisabled]}
              onPress={handleLogin}
              disabled={isLoading}
            >
              <Text style={styles.buttonText}>
                {isLoading ? 'Signing In...' : 'Sign In'}
              </Text>
            </TouchableOpacity>
            
            <TouchableOpacity 
              style={styles.linkButton}
              onPress={() => setCurrentScreen('register')}
            >
              <Text style={styles.linkText}>Don't have an account? Sign up</Text>
            </TouchableOpacity>
          </View>
          
          <View style={styles.demoNote}>
            <Text style={styles.demoText}>🎭 Demo Mode</Text>
            <Text style={styles.demoSubtext}>Enter any username and password to continue</Text>
          </View>
        </ScrollView>
      </SafeAreaView>
    );
  }

  // Registration Screen
  if (currentScreen === 'register') {
    return (
      <SafeAreaView style={styles.container}>
        <StatusBar barStyle="light-content" backgroundColor="#1976D2" />
        <ScrollView contentContainerStyle={styles.scrollContainer}>
          <View style={styles.header}>
            <Text style={styles.appTitle}>AmISafe</Text>
            <Text style={styles.tagline}>Join Our Safety Community</Text>
          </View>
          
          <View style={styles.form}>
            <Text style={styles.title}>Create Account</Text>
            
            <TextInput
              style={styles.input}
              placeholder="Username"
              value={registerData.username}
              onChangeText={(text) => setRegisterData({...registerData, username: text})}
              autoCapitalize="none"
              placeholderTextColor="#999"
            />
            
            <TextInput
              style={styles.input}
              placeholder="Email"
              value={registerData.email}
              onChangeText={(text) => setRegisterData({...registerData, email: text})}
              keyboardType="email-address"
              autoCapitalize="none"
              placeholderTextColor="#999"
            />
            
            <TextInput
              style={styles.input}
              placeholder="Password"
              value={registerData.password}
              onChangeText={(text) => setRegisterData({...registerData, password: text})}
              secureTextEntry
              placeholderTextColor="#999"
            />
            
            <TextInput
              style={styles.input}
              placeholder="Confirm Password"
              value={registerData.confirmPassword}
              onChangeText={(text) => setRegisterData({...registerData, confirmPassword: text})}
              secureTextEntry
              placeholderTextColor="#999"
            />
            
            <TouchableOpacity 
              style={[styles.button, isLoading && styles.buttonDisabled]}
              onPress={handleRegister}
              disabled={isLoading}
            >
              <Text style={styles.buttonText}>
                {isLoading ? 'Creating Account...' : 'Sign Up'}
              </Text>
            </TouchableOpacity>
            
            <TouchableOpacity 
              style={styles.linkButton}
              onPress={() => setCurrentScreen('login')}
            >
              <Text style={styles.linkText}>Already have an account? Sign in</Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </SafeAreaView>
    );
  }

  // Crime Map Screen
  if (currentScreen === 'crimeMap') {
    return (
      <CrimeMapScreen
        onBack={() => setCurrentScreen('dashboard')}
        initialLocation={currentLocation ? {
          latitude: currentLocation.lat,
          longitude: currentLocation.lng,
          latitudeDelta: 0.01,
          longitudeDelta: 0.01
        } : {
          latitude: 39.9526,  // Philadelphia center
          longitude: -75.1652,
          latitudeDelta: 0.0922,
          longitudeDelta: 0.0421
        }}
        drupalCrimeService={drupalCrimeService}
      />
    );
  }

  // Dashboard Screen
  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="light-content" backgroundColor="#1976D2" />
      
      {/* Header */}
      <View style={styles.dashboardHeader}>
        <View>
          <Text style={styles.welcomeText}>Welcome back,</Text>
          <Text style={styles.userName}>{user?.name}</Text>
        </View>
        <View style={styles.headerButtons}>
          <TouchableOpacity 
            style={styles.headerButton}
            onPress={() => setShowSettings(true)}
          >
            <Text style={styles.headerButtonText}>⚙️</Text>
          </TouchableOpacity>
          <TouchableOpacity 
            style={styles.headerButton}
            onPress={handleLogout}
          >
            <Text style={styles.headerButtonText}>🚪</Text>
          </TouchableOpacity>
        </View>
      </View>

      <ScrollView style={styles.dashboardContent}>
        {/* Location Status Card */}
        <View style={styles.locationCard}>
          <View style={styles.locationHeader}>
            <Text style={styles.locationTitle}>📍 Location Status</Text>
            <Text style={[styles.locationStatus, { 
              color: isLocationTracking ? '#4CAF50' : '#F44336' 
            }]}>
              {isLocationTracking ? '● ACTIVE' : '● INACTIVE'}
            </Text>
          </View>
          {currentLocation && currentH3Index && (
            <View style={styles.locationDetails}>
              <Text style={styles.locationText}>
                GPS: {currentLocation.lat.toFixed(6)}, {currentLocation.lng.toFixed(6)}
              </Text>
              <Text style={styles.locationText}>
                H3 Index: {currentH3Index}
              </Text>
              <Text style={styles.locationText}>
                Accuracy: ±{currentLocation.accuracy?.toFixed(0) || '?'}m
              </Text>
            </View>
          )}
        </View>

        {/* Safety Score Card */}
        {safetyData && (
          <View style={styles.safetyCard}>
            <View style={styles.safetyHeader}>
              <Text style={styles.safetyTitle}>🛡️ Current Location Safety</Text>
              <Text style={styles.locationText}>{safetyData.location}</Text>
              {safetyData.h3Index && (
                <Text style={styles.h3Text}>H3: {safetyData.h3Index}</Text>
              )}
            </View>
            
            <View style={styles.safetyScoreContainer}>
              <View 
                style={[
                  styles.safetyScore, 
                  { backgroundColor: getSafetyColor(safetyData.safetyScore) }
                ]}
              >
                <Text style={styles.safetyScoreText}>{safetyData.safetyScore}</Text>
                <Text style={styles.safetyScoreLabel}>SAFETY SCORE</Text>
              </View>
              <View style={styles.safetyInfo}>
                <Text style={[styles.safetyStatus, { color: getSafetyColor(safetyData.safetyScore) }]}>
                  {getSafetyText(safetyData.safetyScore)}
                </Text>
                <Text style={styles.crimeCount}>
                  {safetyData.crimeCount} incidents nearby
                </Text>
                <Text style={styles.lastUpdated}>
                  Updated: {new Date(safetyData.lastUpdated).toLocaleTimeString()}
                </Text>
              </View>
            </View>
          </View>
        )}

        {/* Recent Incidents */}
        {safetyData?.crimes && safetyData.crimes.length > 0 && (
          <View style={styles.incidentsCard}>
            <Text style={styles.sectionTitle}>Recent Incidents</Text>
            {safetyData.crimes.slice(0, 5).map((crime) => (
              <View key={crime.id} style={styles.incidentItem}>
                <View style={styles.incidentIcon}>
                  <Text style={styles.incidentEmoji}>
                    {crime.type === 'Theft' ? '🎒' : 
                     crime.type === 'Assault' ? '⚡' : 
                     crime.type === 'Vandalism' ? '🔨' : 
                     crime.type === 'Burglary' ? '🏠' : '🚗'}
                  </Text>
                </View>
                <View style={styles.incidentDetails}>
                  <Text style={styles.incidentType}>{crime.type}</Text>
                  <Text style={styles.incidentMeta}>{crime.distance} • {crime.time}</Text>
                </View>
              </View>
            ))}
          </View>
        )}

        {/* Quick Actions */}
        <View style={styles.actionsCard}>
          <Text style={styles.sectionTitle}>Quick Actions</Text>
          <View style={styles.actionButtons}>
            <TouchableOpacity style={styles.actionButton}>
              <Text style={styles.actionEmoji}>🆘</Text>
              <Text style={styles.actionText}>Emergency</Text>
            </TouchableOpacity>
            <TouchableOpacity 
              style={styles.actionButton}
              onPress={() => setCurrentScreen('crimeMap')}
            >
              <Text style={styles.actionEmoji}>�️</Text>
              <Text style={styles.actionText}>Crime Map</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.actionButton}>
              <Text style={styles.actionEmoji}>📊</Text>
              <Text style={styles.actionText}>Reports</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.actionButton}>
              <Text style={styles.actionEmoji}>👥</Text>
              <Text style={styles.actionText}>Community</Text>
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>

      {/* Settings Modal */}
      <Modal visible={showSettings} animationType="slide" transparent>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Settings</Text>
              <TouchableOpacity onPress={() => setShowSettings(false)}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>
            
            <View style={styles.settingItem}>
              <Text style={styles.settingLabel}>Push Notifications</Text>
              <Switch
                value={settings.notifications}
                onValueChange={(value) => setSettings({...settings, notifications: value})}
              />
            </View>
            
            <View style={styles.settingItem}>
              <Text style={styles.settingLabel}>Location Sharing</Text>
              <Switch
                value={settings.locationSharing}
                onValueChange={(value) => setSettings({...settings, locationSharing: value})}
              />
            </View>
            
            <View style={styles.settingItem}>
              <Text style={styles.settingLabel}>Auto Safety Check</Text>
              <Switch
                value={settings.autoCheck}
                onValueChange={(value) => setSettings({...settings, autoCheck: value})}
              />
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#1976D2',
  },
  // Splash Screen Styles
  splashContainer: {
    flex: 1,
    backgroundColor: '#000000',
    justifyContent: 'center',
    alignItems: 'center',
  },
  splashContent: {
    alignItems: 'center',
    paddingHorizontal: 40,
  },
  splashTitle: {
    fontSize: 48,
    fontWeight: 'bold',
    color: '#00ff41',
    marginBottom: 10,
    textShadow: '0 0 20px rgba(0, 255, 65, 0.5)',
  },
  splashSubtitle: {
    fontSize: 18,
    color: '#ffffff',
    marginBottom: 8,
    textAlign: 'center',
  },
  splashTagline: {
    fontSize: 14,
    color: '#cccccc',
    marginBottom: 40,
    textAlign: 'center',
  },
  loadingContainer: {
    alignItems: 'center',
    marginTop: 30,
  },
  loadingText: {
    fontSize: 16,
    color: '#00ff41',
    fontWeight: 'bold',
    marginBottom: 5,
  },
  loadingSubtext: {
    fontSize: 12,
    color: '#cccccc',
  },
  splashButtons: {
    marginTop: 30,
  },
  splashButton: {
    backgroundColor: '#00ff41',
    paddingHorizontal: 40,
    paddingVertical: 15,
    borderRadius: 25,
    minWidth: 200,
    alignItems: 'center',
  },
  splashButtonText: {
    color: '#000000',
    fontSize: 18,
    fontWeight: 'bold',
  },
  scrollContainer: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: 20,
  },
  header: {
    alignItems: 'center',
    marginBottom: 40,
  },
  appTitle: {
    fontSize: 36,
    fontWeight: 'bold',
    color: 'white',
    marginBottom: 8,
  },
  tagline: {
    fontSize: 16,
    color: 'rgba(255, 255, 255, 0.8)',
    textAlign: 'center',
  },
  form: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 24,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 3.84,
    elevation: 5,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    textAlign: 'center',
    marginBottom: 24,
    color: '#333',
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 15,
    marginBottom: 16,
    backgroundColor: 'white',
    fontSize: 16,
    color: '#333',
  },
  button: {
    backgroundColor: '#1976D2',
    borderRadius: 8,
    padding: 15,
    alignItems: 'center',
    marginBottom: 16,
  },
  buttonDisabled: {
    backgroundColor: '#ccc',
  },
  buttonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: 'bold',
  },
  linkButton: {
    alignItems: 'center',
    padding: 10,
  },
  linkText: {
    color: '#1976D2',
    fontSize: 14,
  },
  demoNote: {
    backgroundColor: 'rgba(255, 255, 255, 0.1)',
    borderRadius: 8,
    padding: 16,
    alignItems: 'center',
  },
  demoText: {
    color: 'white',
    fontSize: 16,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  demoSubtext: {
    color: 'rgba(255, 255, 255, 0.8)',
    fontSize: 12,
    textAlign: 'center',
  },
  
  // Dashboard Styles
  dashboardHeader: {
    backgroundColor: '#1976D2',
    padding: 20,
    paddingTop: 10,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  welcomeText: {
    color: 'rgba(255, 255, 255, 0.8)',
    fontSize: 14,
  },
  userName: {
    color: 'white',
    fontSize: 20,
    fontWeight: 'bold',
  },
  headerButtons: {
    flexDirection: 'row',
  },
  headerButton: {
    marginLeft: 15,
    padding: 8,
  },
  headerButtonText: {
    fontSize: 20,
  },
  dashboardContent: {
    flex: 1,
    backgroundColor: '#f5f5f5',
    padding: 16,
  },
  
  // Location Status Card
  locationCard: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 20,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
  },
  locationHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  locationTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#333',
  },
  locationStatus: {
    fontSize: 12,
    fontWeight: 'bold',
  },
  locationDetails: {
    backgroundColor: '#f8f9fa',
    borderRadius: 8,
    padding: 12,
  },
  locationText: {
    fontSize: 12,
    color: '#666',
    fontFamily: 'monospace',
    marginBottom: 4,
  },
  h3Text: {
    fontSize: 10,
    color: '#888',
    fontFamily: 'monospace',
    marginTop: 4,
  },
  
  // Safety Card
  safetyCard: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 20,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
  },
  safetyHeader: {
    marginBottom: 20,
  },
  safetyTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 4,
  },
  locationText: {
    fontSize: 14,
    color: '#666',
  },
  safetyScoreContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  safetyScore: {
    width: 80,
    height: 80,
    borderRadius: 40,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 20,
  },
  safetyScoreText: {
    color: 'white',
    fontSize: 24,
    fontWeight: 'bold',
  },
  safetyScoreLabel: {
    color: 'white',
    fontSize: 8,
    fontWeight: 'bold',
  },
  safetyInfo: {
    flex: 1,
  },
  safetyStatus: {
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  crimeCount: {
    fontSize: 14,
    color: '#666',
    marginBottom: 4,
  },
  lastUpdated: {
    fontSize: 12,
    color: '#999',
  },
  
  // Incidents Card
  incidentsCard: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 20,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 16,
  },
  incidentItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  incidentIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#f5f5f5',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  incidentEmoji: {
    fontSize: 18,
  },
  incidentDetails: {
    flex: 1,
  },
  incidentType: {
    fontSize: 16,
    fontWeight: '500',
    color: '#333',
    marginBottom: 2,
  },
  incidentMeta: {
    fontSize: 12,
    color: '#666',
  },
  
  // Actions Card
  actionsCard: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 20,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
  },
  actionButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  actionButton: {
    alignItems: 'center',
    padding: 12,
    flex: 1,
  },
  actionEmoji: {
    fontSize: 24,
    marginBottom: 8,
  },
  actionText: {
    fontSize: 12,
    color: '#333',
    textAlign: 'center',
  },
  
  // Modal Styles
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: 'white',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: 20,
    maxHeight: '50%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 24,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
  },
  modalClose: {
    fontSize: 18,
    color: '#666',
    padding: 4,
  },
  settingItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  settingLabel: {
    fontSize: 16,
    color: '#333',
  },
});

export default App;