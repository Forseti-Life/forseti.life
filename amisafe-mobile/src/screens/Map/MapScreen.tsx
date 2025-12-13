/**
 * Map Screen - Link to Forseti Safety Map
 * Opens the interactive crime map on the Forseti website
 */

import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Linking, Alert } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { Colors } from '../../utils/colors';

const MapScreen: React.FC = () => {
  const handleOpenSafetyMap = async () => {
    const url = 'https://forseti.life/safety-map';
    
    try {
      const supported = await Linking.canOpenURL(url);
      
      if (supported) {
        await Linking.openURL(url);
      } else {
        Alert.alert(
          'Cannot Open Link',
          'Unable to open the safety map. Please check your internet connection.',
          [{ text: 'OK' }]
        );
      }
    } catch (error) {
      console.error('Error opening safety map:', error);
      Alert.alert(
        'Error',
        'An error occurred while opening the safety map.',
        [{ text: 'OK' }]
      );
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.content}>
        <Icon name="map-marker-radius" size={80} color={Colors.primary} />
        
        <Text style={styles.title}>Philadelphia Safety Map</Text>
        
        <Text style={styles.description}>
          View the interactive safety map on the Forseti website to see real-time crime data, 
          safety scores, and detailed area information.
        </Text>

        <TouchableOpacity 
          style={styles.button}
          onPress={handleOpenSafetyMap}
        >
          <Icon name="web" size={24} color={Colors.white} style={styles.buttonIcon} />
          <Text style={styles.buttonText}>Open Safety Map</Text>
        </TouchableOpacity>

        <View style={styles.infoBox}>
          <Icon name="information" size={20} color={Colors.info} />
          <Text style={styles.infoText}>
            The map will open in your web browser
          </Text>
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  content: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    color: Colors.text,
    marginTop: 24,
    marginBottom: 16,
    textAlign: 'center',
  },
  description: {
    fontSize: 16,
    color: Colors.textSecondary,
    textAlign: 'center',
    lineHeight: 24,
    marginBottom: 32,
    paddingHorizontal: 16,
  },
  button: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.primary,
    paddingHorizontal: 32,
    paddingVertical: 16,
    borderRadius: 8,
    marginBottom: 24,
    elevation: 3,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 3.84,
  },
  buttonIcon: {
    marginRight: 8,
  },
  buttonText: {
    fontSize: 18,
    fontWeight: '600',
    color: Colors.white,
  },
  infoBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.lightGray,
    padding: 12,
    borderRadius: 8,
    marginTop: 8,
  },
  infoText: {
    fontSize: 14,
    color: Colors.textSecondary,
    marginLeft: 8,
  },
});

export default MapScreen;
