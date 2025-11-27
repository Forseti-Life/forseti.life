/**
 * Map Screen - Interactive crime map for AmISafe Mobile Application
 * Based on the Drupal AmISafe crime-map.js implementation
 */

import React, { useState, useEffect } from 'react';
import { View, StyleSheet, Alert } from 'react-native';
import InteractiveCrimeMap from '../../components/InteractiveCrimeMap';
import DrupalCrimeService from '../../services/DrupalCrimeService';
import LocationService from '../../services/location/LocationService';
import { Colors } from '../../utils/colors';

const MapScreen: React.FC = () => {
  const [currentLocation, setCurrentLocation] = useState(null);
  const [crimeService] = useState(new DrupalCrimeService());

  useEffect(() => {
    initializeMap();
  }, []);

  const initializeMap = async () => {
    try {
      // Get user's current location
      const location = await LocationService.getCurrentLocation();
      setCurrentLocation({
        latitude: location.coords.latitude,
        longitude: location.coords.longitude,
        latitudeDelta: 0.0922,
        longitudeDelta: 0.0421,
      });
    } catch (error) {
      console.error('Error getting location:', error);
      // Fall back to Philadelphia center if location fails
      setCurrentLocation({
        latitude: 39.9526,
        longitude: -75.1652,
        latitudeDelta: 0.0922,
        longitudeDelta: 0.0421,
      });
    }
  };

  const handleLocationChange = (newLocation: any) => {
    console.log('Map location changed:', newLocation);
    setCurrentLocation(newLocation);
  };

  return (
    <View style={styles.container}>
      {currentLocation && (
        <InteractiveCrimeMap
          initialLocation={currentLocation}
          onLocationChange={handleLocationChange}
          filters={{}}
          drupalCrimeService={crimeService}
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
});

export default MapScreen;