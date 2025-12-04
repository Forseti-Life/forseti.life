import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  ScrollView,
  StyleSheet,
  Modal,
} from 'react-native';

const FilterPanel = ({ visible, onClose, onApplyFilters, currentFilters = {} }) => {
  // Initialize filter state from current filters
  const [crimeTypes, setCrimeTypes] = useState(currentFilters.crimeTypes || {
    part1Person: true,
    part1Property: true,
    part2: true,
    violent: true,
    nonviolent: true,
  });

  const [districts, setDistricts] = useState(currentFilters.districts || []);
  const [datePreset, setDatePreset] = useState(currentFilters.datePreset || 'alltime');
  const [timePeriods, setTimePeriods] = useState(currentFilters.timePeriods || {
    earlyMorning: true,
    morning: true,
    afternoon: true,
    evening: true,
  });

  // Crime type definitions
  const crimeTypeOptions = [
    { key: 'part1Person', label: 'Part I - Crimes Against Person', description: 'Homicide, Rape, Robbery, Aggravated Assault' },
    { key: 'part1Property', label: 'Part I - Crimes Against Property', description: 'Burglary, Larceny, Vehicle Theft, Arson' },
    { key: 'part2', label: 'Part II Crimes', description: 'Other offenses' },
    { key: 'violent', label: 'Violent Crimes', description: 'All violent offenses' },
    { key: 'nonviolent', label: 'Non-Violent Crimes', description: 'Property and other crimes' },
  ];

  // District options (1-25)
  const districtOptions = Array.from({ length: 25 }, (_, i) => i + 1);

  // Date preset options
  const datePresetOptions = [
    { key: 'alltime', label: 'All Time' },
    { key: '12months', label: 'Last 12 Months' },
    { key: '6months', label: 'Last 6 Months' },
  ];

  // Time period options
  const timePeriodOptions = [
    { key: 'earlyMorning', label: 'Early Morning', time: '12am-6am' },
    { key: 'morning', label: 'Morning', time: '6am-12pm' },
    { key: 'afternoon', label: 'Afternoon', time: '12pm-6pm' },
    { key: 'evening', label: 'Evening', time: '6pm-12am' },
  ];

  // Toggle crime type
  const toggleCrimeType = (key) => {
    setCrimeTypes(prev => ({ ...prev, [key]: !prev[key] }));
  };

  // Toggle district
  const toggleDistrict = (district) => {
    setDistricts(prev => {
      if (prev.includes(district)) {
        return prev.filter(d => d !== district);
      } else {
        return [...prev, district];
      }
    });
  };

  // Select all districts
  const selectAllDistricts = () => {
    setDistricts(districtOptions);
  };

  // Deselect all districts
  const deselectAllDistricts = () => {
    setDistricts([]);
  };

  // Toggle time period
  const toggleTimePeriod = (key) => {
    setTimePeriods(prev => ({ ...prev, [key]: !prev[key] }));
  };

  // Apply filters
  const handleApplyFilters = () => {
    const filters = {
      crimeTypes,
      districts,
      datePreset,
      timePeriods,
    };
    console.log('🔍 FilterPanel: Applying filters:', filters);
    onApplyFilters(filters);
    onClose();
  };

  // Clear all filters
  const handleClearAll = () => {
    setCrimeTypes({
      part1Person: true,
      part1Property: true,
      part2: true,
      violent: true,
      nonviolent: true,
    });
    setDistricts([]);
    setDatePreset('alltime');
    setTimePeriods({
      earlyMorning: true,
      morning: true,
      afternoon: true,
      evening: true,
    });
  };

  // Count active filters
  const getActiveFilterCount = () => {
    let count = 0;
    
    // Crime types that are disabled
    const disabledCrimeTypes = Object.values(crimeTypes).filter(v => !v).length;
    if (disabledCrimeTypes > 0) count += disabledCrimeTypes;
    
    // Districts selected (only count if not all)
    if (districts.length > 0 && districts.length < 25) count += 1;
    
    // Date preset (if not all time)
    if (datePreset !== 'alltime') count += 1;
    
    // Time periods disabled
    const disabledTimePeriods = Object.values(timePeriods).filter(v => !v).length;
    if (disabledTimePeriods > 0) count += disabledTimePeriods;
    
    return count;
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent={false}
      onRequestClose={onClose}
    >
      <View style={styles.container}>
        {/* Header */}
        <View style={styles.header}>
          <Text style={styles.headerTitle}>Filter Crime Data</Text>
          <TouchableOpacity style={styles.closeButton} onPress={onClose}>
            <Text style={styles.closeButtonText}>✕</Text>
          </TouchableOpacity>
        </View>

        <ScrollView style={styles.scrollView} contentContainerStyle={styles.scrollContent}>
          {/* Crime Types Section */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Crime Types</Text>
            {crimeTypeOptions.map(option => (
              <TouchableOpacity
                key={option.key}
                style={styles.checkboxContainer}
                onPress={() => toggleCrimeType(option.key)}
              >
                <View style={[styles.checkbox, crimeTypes[option.key] && styles.checkboxChecked]}>
                  {crimeTypes[option.key] && <Text style={styles.checkmark}>✓</Text>}
                </View>
                <View style={styles.checkboxLabelContainer}>
                  <Text style={styles.checkboxLabel}>{option.label}</Text>
                  <Text style={styles.checkboxDescription}>{option.description}</Text>
                </View>
              </TouchableOpacity>
            ))}
          </View>

          {/* Districts Section */}
          <View style={styles.section}>
            <View style={styles.sectionHeader}>
              <Text style={styles.sectionTitle}>Districts</Text>
              <View style={styles.sectionActions}>
                <TouchableOpacity onPress={selectAllDistricts}>
                  <Text style={styles.actionLink}>Select All</Text>
                </TouchableOpacity>
                <Text style={styles.actionSeparator}>|</Text>
                <TouchableOpacity onPress={deselectAllDistricts}>
                  <Text style={styles.actionLink}>Clear</Text>
                </TouchableOpacity>
              </View>
            </View>
            <View style={styles.districtGrid}>
              {districtOptions.map(district => (
                <TouchableOpacity
                  key={district}
                  style={[
                    styles.districtButton,
                    districts.includes(district) && styles.districtButtonActive
                  ]}
                  onPress={() => toggleDistrict(district)}
                >
                  <Text style={[
                    styles.districtButtonText,
                    districts.includes(district) && styles.districtButtonTextActive
                  ]}>
                    {district}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
            {districts.length > 0 && (
              <Text style={styles.districtCount}>
                {districts.length} district{districts.length !== 1 ? 's' : ''} selected
              </Text>
            )}
          </View>

          {/* Date Range Section */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Date Range</Text>
            <View style={styles.presetButtons}>
              {datePresetOptions.map(option => (
                <TouchableOpacity
                  key={option.key}
                  style={[
                    styles.presetButton,
                    datePreset === option.key && styles.presetButtonActive
                  ]}
                  onPress={() => setDatePreset(option.key)}
                >
                  <Text style={[
                    styles.presetButtonText,
                    datePreset === option.key && styles.presetButtonTextActive
                  ]}>
                    {option.label}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>

          {/* Time Period Section */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Time of Day</Text>
            {timePeriodOptions.map(option => (
              <TouchableOpacity
                key={option.key}
                style={styles.checkboxContainer}
                onPress={() => toggleTimePeriod(option.key)}
              >
                <View style={[styles.checkbox, timePeriods[option.key] && styles.checkboxChecked]}>
                  {timePeriods[option.key] && <Text style={styles.checkmark}>✓</Text>}
                </View>
                <View style={styles.checkboxLabelContainer}>
                  <Text style={styles.checkboxLabel}>{option.label}</Text>
                  <Text style={styles.checkboxDescription}>{option.time}</Text>
                </View>
              </TouchableOpacity>
            ))}
          </View>
        </ScrollView>

        {/* Footer Actions */}
        <View style={styles.footer}>
          <TouchableOpacity style={styles.clearButton} onPress={handleClearAll}>
            <Text style={styles.clearButtonText}>Clear All</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.applyButton} onPress={handleApplyFilters}>
            <Text style={styles.applyButtonText}>
              Apply Filters {getActiveFilterCount() > 0 && `(${getActiveFilterCount()})`}
            </Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#1a1a1a',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    paddingTop: 40,
    borderBottomWidth: 1,
    borderBottomColor: '#333333',
    backgroundColor: '#000000',
  },
  headerTitle: {
    color: '#00ff41',
    fontSize: 20,
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
    fontSize: 18,
    fontWeight: 'bold',
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    padding: 20,
  },
  section: {
    marginBottom: 30,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 15,
  },
  sectionTitle: {
    color: '#00ff41',
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 15,
  },
  sectionActions: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  actionLink: {
    color: '#00ff41',
    fontSize: 14,
  },
  actionSeparator: {
    color: '#666666',
    marginHorizontal: 8,
  },
  checkboxContainer: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 15,
  },
  checkbox: {
    width: 24,
    height: 24,
    borderWidth: 2,
    borderColor: '#666666',
    borderRadius: 4,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
    marginTop: 2,
  },
  checkboxChecked: {
    backgroundColor: '#00ff41',
    borderColor: '#00ff41',
  },
  checkmark: {
    color: '#000000',
    fontSize: 16,
    fontWeight: 'bold',
  },
  checkboxLabelContainer: {
    flex: 1,
  },
  checkboxLabel: {
    color: '#ffffff',
    fontSize: 16,
    marginBottom: 2,
  },
  checkboxDescription: {
    color: '#999999',
    fontSize: 12,
  },
  districtGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginHorizontal: -5,
  },
  districtButton: {
    width: '18%',
    aspectRatio: 1,
    margin: '1%',
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#2a2a2a',
    borderRadius: 8,
    borderWidth: 2,
    borderColor: '#444444',
  },
  districtButtonActive: {
    backgroundColor: '#00ff41',
    borderColor: '#00ff41',
  },
  districtButtonText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: 'bold',
  },
  districtButtonTextActive: {
    color: '#000000',
  },
  districtCount: {
    color: '#999999',
    fontSize: 12,
    marginTop: 10,
    textAlign: 'center',
  },
  presetButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  presetButton: {
    flex: 1,
    paddingVertical: 12,
    marginHorizontal: 5,
    backgroundColor: '#2a2a2a',
    borderRadius: 8,
    borderWidth: 2,
    borderColor: '#444444',
    alignItems: 'center',
  },
  presetButtonActive: {
    backgroundColor: '#00ff41',
    borderColor: '#00ff41',
  },
  presetButtonText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: 'bold',
  },
  presetButtonTextActive: {
    color: '#000000',
  },
  footer: {
    flexDirection: 'row',
    padding: 20,
    paddingBottom: 30,
    borderTopWidth: 1,
    borderTopColor: '#333333',
    backgroundColor: '#000000',
  },
  clearButton: {
    flex: 1,
    paddingVertical: 15,
    marginRight: 10,
    backgroundColor: '#333333',
    borderRadius: 8,
    alignItems: 'center',
  },
  clearButtonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  applyButton: {
    flex: 2,
    paddingVertical: 15,
    backgroundColor: '#00ff41',
    borderRadius: 8,
    alignItems: 'center',
  },
  applyButtonText: {
    color: '#000000',
    fontSize: 16,
    fontWeight: 'bold',
  },
});

export default FilterPanel;
