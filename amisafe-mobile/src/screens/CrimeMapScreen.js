/**
 * Crime Map Screen for AmISafe Mobile App
 * 
 * Full-screen crime map interface with controls and filtering
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Modal,
  ScrollView,
  TextInput,
  Alert,
  SafeAreaView,
  StatusBar,
} from 'react-native';
import InteractiveCrimeMap from '../components/InteractiveCrimeMap';
import { h3LocationService } from '../services/H3LocationService';
import { gpsLocationService } from '../services/GPSLocationService';
import drupalCrimeService from '../services/DrupalCrimeService';

const CrimeMapScreen = ({ onBack, initialLocation }) => {
  const [showFilters, setShowFilters] = useState(false);
  const [showStats, setShowStats] = useState(false);
  const [currentLocation, setCurrentLocation] = useState(initialLocation);
  const [citywideStats, setCitywideStats] = useState(null);
  
  // Filter state
  const [filters, setFilters] = useState({
    crimeTypes: [],
    districts: [],
    startDate: '2006-01-01',
    endDate: '2025-12-31',
    timePeriods: ['early-morning', 'morning', 'afternoon', 'evening'],
    viewMode: 'hexagon'
  });

  // Available filter options
  const [filterOptions, setFilterOptions] = useState({
    crimeTypes: [
      { id: 'violent', name: 'Violent Crimes', codes: ['1', '2'] },
      { id: 'property', name: 'Property Crimes', codes: ['3', '5'] },
      { id: 'drug', name: 'Drug Offenses', codes: ['4'] },
      { id: 'traffic', name: 'Traffic Violations', codes: ['7'] },
      { id: 'other', name: 'Other Incidents', codes: ['6', '8', '9'] }
    ],
    districts: Array.from({ length: 25 }, (_, i) => ({ id: i + 1, name: `District ${i + 1}` })),
    timePeriods: [
      { id: 'early-morning', name: 'Early Morning (12AM-6AM)' },
      { id: 'morning', name: 'Morning (6AM-12PM)' },
      { id: 'afternoon', name: 'Afternoon (12PM-6PM)' },
      { id: 'evening', name: 'Evening (6PM-12AM)' }
    ]
  });

  /**
   * Handle location change from map
   */
  const handleLocationChange = (location) => {
    setCurrentLocation(location);
    
    // Update H3 location service with new coordinates
    h3LocationService.updateLocation(location.latitude, location.longitude);
  };

  /**
   * Apply current filters
   */
  const applyFilters = () => {
    console.log('🔄 Applying crime map filters:', filters);
    setShowFilters(false);
    // Filters will be automatically applied via the filters prop
  };

  /**
   * Clear all filters
   */
  const clearFilters = () => {
    const clearedFilters = {
      crimeTypes: [],
      districts: [],
      startDate: '2006-01-01',
      endDate: '2025-12-31',
      timePeriods: ['early-morning', 'morning', 'afternoon', 'evening'],
      viewMode: 'hexagon'
    };
    setFilters(clearedFilters);
    console.log('🧹 Filters cleared');
  };

  /**
   * Toggle crime type filter
   */
  const toggleCrimeType = (crimeType) => {
    const newCrimeTypes = filters.crimeTypes.includes(crimeType)
      ? filters.crimeTypes.filter(type => type !== crimeType)
      : [...filters.crimeTypes, crimeType];
    
    setFilters({ ...filters, crimeTypes: newCrimeTypes });
  };

  /**
   * Toggle district filter
   */
  const toggleDistrict = (district) => {
    const newDistricts = filters.districts.includes(district)
      ? filters.districts.filter(d => d !== district)
      : [...filters.districts, district];
    
    setFilters({ ...filters, districts: newDistricts });
  };

  /**
   * Set date preset
   */
  const setDatePreset = (preset) => {
    const now = new Date();
    let startDate, endDate;
    
    switch (preset) {
      case 'lastMonth':
        startDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        endDate = new Date(now.getFullYear(), now.getMonth(), 0);
        break;
      case 'lastYear':
        startDate = new Date(now.getFullYear() - 1, 0, 1);
        endDate = new Date(now.getFullYear() - 1, 11, 31);
        break;
      case 'allTime':
        startDate = new Date('2006-01-01');
        endDate = new Date('2025-12-31');
        break;
      default:
        return;
    }
    
    setFilters({
      ...filters,
      startDate: startDate.toISOString().split('T')[0],
      endDate: endDate.toISOString().split('T')[0]
    });
  };

  /**
   * Render filter modal
   */
  const renderFilterModal = () => (
    <Modal
      visible={showFilters}
      animationType="slide"
      presentationStyle="pageSheet"
    >
      <SafeAreaView style={styles.modalContainer}>
        <View style={styles.modalHeader}>
          <Text style={styles.modalTitle}>Crime Map Filters</Text>
          <TouchableOpacity
            style={styles.closeButton}
            onPress={() => setShowFilters(false)}
          >
            <Text style={styles.closeButtonText}>✕</Text>
          </TouchableOpacity>
        </View>
        
        <ScrollView style={styles.modalContent}>
          {/* Crime Types */}
          <View style={styles.filterSection}>
            <Text style={styles.filterSectionTitle}>Crime Types</Text>
            {filterOptions.crimeTypes.map(crimeType => (
              <TouchableOpacity
                key={crimeType.id}
                style={[
                  styles.filterOption,
                  filters.crimeTypes.includes(crimeType.id) && styles.filterOptionSelected
                ]}
                onPress={() => toggleCrimeType(crimeType.id)}
              >
                <Text style={[
                  styles.filterOptionText,
                  filters.crimeTypes.includes(crimeType.id) && styles.filterOptionTextSelected
                ]}>
                  {crimeType.name}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
          
          {/* Police Districts */}
          <View style={styles.filterSection}>
            <Text style={styles.filterSectionTitle}>Police Districts</Text>
            <View style={styles.districtGrid}>
              {filterOptions.districts.slice(0, 12).map(district => (
                <TouchableOpacity
                  key={district.id}
                  style={[
                    styles.districtOption,
                    filters.districts.includes(district.id) && styles.filterOptionSelected
                  ]}
                  onPress={() => toggleDistrict(district.id)}
                >
                  <Text style={[
                    styles.districtOptionText,
                    filters.districts.includes(district.id) && styles.filterOptionTextSelected
                  ]}>
                    {district.id}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>
          
          {/* Date Range */}
          <View style={styles.filterSection}>
            <Text style={styles.filterSectionTitle}>Date Range</Text>
            <View style={styles.datePresets}>
              <TouchableOpacity
                style={styles.presetButton}
                onPress={() => setDatePreset('lastMonth')}
              >
                <Text style={styles.presetButtonText}>Last Month</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.presetButton}
                onPress={() => setDatePreset('lastYear')}
              >
                <Text style={styles.presetButtonText}>Last Year</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.presetButton}
                onPress={() => setDatePreset('allTime')}
              >
                <Text style={styles.presetButtonText}>All Time</Text>
              </TouchableOpacity>
            </View>
            
            <View style={styles.dateInputs}>
              <View style={styles.dateInputContainer}>
                <Text style={styles.dateLabel}>Start Date:</Text>
                <TextInput
                  style={styles.dateInput}
                  value={filters.startDate}
                  onChangeText={(date) => setFilters({ ...filters, startDate: date })}
                  placeholder="YYYY-MM-DD"
                  placeholderTextColor="#666666"
                />
              </View>
              <View style={styles.dateInputContainer}>
                <Text style={styles.dateLabel}>End Date:</Text>
                <TextInput
                  style={styles.dateInput}
                  value={filters.endDate}
                  onChangeText={(date) => setFilters({ ...filters, endDate: date })}
                  placeholder="YYYY-MM-DD"
                  placeholderTextColor="#666666"
                />
              </View>
            </View>
          </View>
        </ScrollView>
        
        <View style={styles.modalActions}>
          <TouchableOpacity
            style={styles.clearButton}
            onPress={clearFilters}
          >
            <Text style={styles.clearButtonText}>Clear Filters</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.applyButton}
            onPress={applyFilters}
          >
            <Text style={styles.applyButtonText}>Apply Filters</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    </Modal>
  );

  /**
   * Get current user location
   */
  const getCurrentLocation = async () => {
    try {
      const location = await gpsLocationService.getCurrentPosition();
      if (location) {
        setCurrentLocation({
          latitude: location.latitude,
          longitude: location.longitude,
          latitudeDelta: 0.01,
          longitudeDelta: 0.01
        });
        console.log('📍 Updated to current GPS location');
      }
    } catch (error) {
      Alert.alert('Location Error', 'Unable to get current location. Please check permissions.');
    }
  };

  return (
    <View style={styles.container}>
      <StatusBar barStyle="light-content" backgroundColor="#000000" />
      
      {/* Map Header */}
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={onBack}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Crime Map</Text>
        <View style={styles.headerSpacer} />
      </View>
      
      {/* Interactive Crime Map */}
      <InteractiveCrimeMap
        initialLocation={currentLocation}
        onLocationChange={handleLocationChange}
        filters={filters}
        drupalCrimeService={drupalCrimeService}
      />
      
      {/* Map Action Buttons */}
      <View style={styles.actionButtons}>
        <TouchableOpacity
          style={styles.actionButton}
          onPress={() => setShowFilters(true)}
        >
          <Text style={styles.actionButtonText}>🔧 Filters</Text>
        </TouchableOpacity>
        
        <TouchableOpacity
          style={styles.actionButton}
          onPress={() => setShowStats(true)}
        >
          <Text style={styles.actionButtonText}>📊 Stats</Text>
        </TouchableOpacity>
      </View>
      
      {/* Filter Modal */}
      {renderFilterModal()}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000000',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 15,
    backgroundColor: '#1a1a1a',
    borderBottomWidth: 1,
    borderBottomColor: '#333333',
  },
  backButton: {
    padding: 8,
  },
  backButtonText: {
    color: '#00ff41',
    fontSize: 16,
    fontWeight: 'bold',
  },
  headerTitle: {
    color: '#ffffff',
    fontSize: 18,
    fontWeight: 'bold',
  },
  headerSpacer: {
    width: 60, // Match backButton width for centered title
  },
  actionButtons: {
    position: 'absolute',
    top: 100,
    right: 20,
    gap: 10,
  },
  actionButton: {
    backgroundColor: 'rgba(0, 255, 65, 0.8)',
    paddingHorizontal: 15,
    paddingVertical: 10,
    borderRadius: 25,
    minWidth: 80,
    alignItems: 'center',
  },
  actionButtonText: {
    color: '#000000',
    fontSize: 12,
    fontWeight: 'bold',
  },
  modalContainer: {
    flex: 1,
    backgroundColor: '#1a1a1a',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#333333',
  },
  modalTitle: {
    color: '#00ff41',
    fontSize: 18,
    fontWeight: 'bold',
  },
  closeButton: {
    width: 30,
    height: 30,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#333333',
    borderRadius: 15,
  },
  closeButtonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  modalContent: {
    flex: 1,
    padding: 20,
  },
  filterSection: {
    marginBottom: 25,
  },
  filterSectionTitle: {
    color: '#00ff41',
    fontSize: 16,
    fontWeight: 'bold',
    marginBottom: 10,
  },
  filterOption: {
    backgroundColor: '#333333',
    padding: 12,
    borderRadius: 8,
    marginBottom: 8,
  },
  filterOptionSelected: {
    backgroundColor: '#00ff41',
  },
  filterOptionText: {
    color: '#ffffff',
    fontSize: 14,
  },
  filterOptionTextSelected: {
    color: '#000000',
    fontWeight: 'bold',
  },
  districtGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  districtOption: {
    backgroundColor: '#333333',
    padding: 10,
    borderRadius: 20,
    minWidth: 40,
    alignItems: 'center',
  },
  districtOptionText: {
    color: '#ffffff',
    fontSize: 12,
    fontWeight: 'bold',
  },
  districtOptionTextSelected: {
    color: '#000000',
  },
  datePresets: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 15,
  },
  presetButton: {
    backgroundColor: '#333333',
    paddingHorizontal: 15,
    paddingVertical: 8,
    borderRadius: 15,
  },
  presetButtonText: {
    color: '#ffffff',
    fontSize: 12,
  },
  dateInputs: {
    gap: 10,
  },
  dateInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  dateLabel: {
    color: '#cccccc',
    fontSize: 14,
    width: 80,
  },
  dateInput: {
    flex: 1,
    backgroundColor: '#333333',
    color: '#ffffff',
    padding: 10,
    borderRadius: 8,
    fontSize: 14,
  },
  modalActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: '#333333',
  },
  clearButton: {
    backgroundColor: '#666666',
    paddingHorizontal: 30,
    paddingVertical: 12,
    borderRadius: 25,
  },
  clearButtonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  applyButton: {
    backgroundColor: '#00ff41',
    paddingHorizontal: 30,
    paddingVertical: 12,
    borderRadius: 25,
  },
  applyButtonText: {
    color: '#000000',
    fontSize: 16,
    fontWeight: 'bold',
  },
});

export default CrimeMapScreen;