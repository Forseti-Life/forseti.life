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
      Alert.alert('Error', 'An error occurred while opening the safety map.', [{ text: 'OK' }]);
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.content}>
        <Icon name="map-marker-radius" size={80} color={Colors.primary} />

        <Text style={styles.title}>Philadelphia Safety Map</Text>

        <Text style={styles.description}>
          View the interactive safety map on the Forseti website to see real-time crime data, safety
          scores, and detailed area information.
        </Text>

        <TouchableOpacity style={styles.button} onPress={handleOpenSafetyMap}>
          <Icon name="web" size={24} color={Colors.white} style={styles.buttonIcon} />
          <Text style={styles.buttonText}>Open Safety Map</Text>
        </TouchableOpacity>

        <View style={styles.infoBox}>
          <Icon name="information" size={20} color={Colors.info} />
          <Text style={styles.infoText}>The map will open in your web browser</Text>
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  button: {
    alignItems: 'center',
    backgroundColor: Colors.primary,
    borderRadius: 8,
    elevation: 3,
    flexDirection: 'row',
    marginBottom: 24,
    paddingHorizontal: 32,
    paddingVertical: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 3.84,
  },
  buttonIcon: {
    marginRight: 8,
  },
  buttonText: {
    color: Colors.white,
    fontSize: 18,
    fontWeight: '600',
  },
  container: {
    backgroundColor: Colors.background,
    flex: 1,
  },
  content: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
    padding: 24,
  },
  description: {
    color: Colors.textSecondary,
    fontSize: 16,
    lineHeight: 24,
    marginBottom: 32,
    paddingHorizontal: 16,
    textAlign: 'center',
  },
  infoBox: {
    alignItems: 'center',
    backgroundColor: Colors.lightGray,
    borderRadius: 8,
    flexDirection: 'row',
    marginTop: 8,
    padding: 12,
  },
  infoText: {
    color: Colors.textSecondary,
    fontSize: 14,
    marginLeft: 8,
  },
  title: {
    color: Colors.text,
    fontSize: 28,
    fontWeight: 'bold',
    marginBottom: 16,
    marginTop: 24,
    textAlign: 'center',
  },
});

export default MapScreen;
