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
  // Initialize filter state from current filters - SIMPLIFIED to match web version
  const [datePreset, setDatePreset] = useState(currentFilters.datePreset || '12months');

  // Date preset options (matching web implementation)
  const datePresetOptions = [
    { key: 'alltime', label: 'All Time', description: 'All available crime data' },
    { key: '12months', label: 'Last 12 Months', description: 'Most recent year of data' },
    { key: '6months', label: 'Last 6 Months', description: 'Most recent 6 months' },
  ];

  // No toggle functions needed - only date preset selection

  // Apply filters (only date preset)
  const handleApplyFilters = () => {
    const filters = {
      datePreset,
    };
    console.log('🔍 FilterPanel: Applying date filter:', filters);
    onApplyFilters(filters);
    onClose();
  };

  // Reset to default (12 months)
  const handleClearAll = () => {
    setDatePreset('12months');
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
        {/* Date Range Section - Only Control Needed (matches web version) */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Date Range Filter</Text>
          <Text style={styles.sectionDescription}>
            Select the time period for crime data visualization. This matches the web map controls.
          </Text>
          {datePresetOptions.map(option => (
            <TouchableOpacity
              key={option.key}
              style={[
                styles.presetCard,
                datePreset === option.key && styles.presetCardActive
              ]}
              onPress={() => setDatePreset(option.key)}
            >
              <View style={styles.radioContainer}>
                <View style={[styles.radio, datePreset === option.key && styles.radioActive]}>
                  {datePreset === option.key && <View style={styles.radioDot} />}
                </View>
                <View style={styles.presetLabelContainer}>
                  <Text style={[
                    styles.presetLabel,
                    datePreset === option.key && styles.presetLabelActive
                  ]}>
                    {option.label}
                  </Text>
                  <Text style={styles.presetDescription}>{option.description}</Text>
                </View>
              </View>
            </TouchableOpacity>
          ))}
        </View>

        {/* Info Section */}
        <View style={styles.infoSection}>
          <Text style={styles.infoTitle}>ℹ️ About Filters</Text>
          <Text style={styles.infoText}>
            • H3 Resolution automatically adjusts based on zoom level
          </Text>
          <Text style={styles.infoText}>
            • Date range filters apply to all crime data displayed
          </Text>
          <Text style={styles.infoText}>
            • Default is 12 months (matching web map)
          </Text>
        </View>
      </ScrollView>

      {/* Footer Actions */}
      <View style={styles.footer}>
        <TouchableOpacity style={styles.clearButton} onPress={handleClearAll}>
          <Text style={styles.clearButtonText}>Reset to 12 Months</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.applyButton} onPress={handleApplyFilters}>
          <Text style={styles.applyButtonText}>
            Apply Date Filter
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
  sectionTitle: {
    color: '#00ff41',
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 10,
  },
  sectionDescription: {
    color: '#999999',
    fontSize: 14,
    marginBottom: 20,
    lineHeight: 20,
  },
  presetCard: {
    backgroundColor: '#2a2a2a',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 2,
    borderColor: '#444444',
  },
  presetCardActive: {
    backgroundColor: 'rgba(0, 255, 65, 0.1)',
    borderColor: '#00ff41',
  },
  radioContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  radio: {
    width: 24,
    height: 24,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#666666',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  radioActive: {
    borderColor: '#00ff41',
  },
  radioDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: '#00ff41',
  },
  presetLabelContainer: {
    flex: 1,
  },
  presetLabel: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  presetLabelActive: {
    color: '#00ff41',
  },
  presetDescription: {
    color: '#999999',
    fontSize: 13,
  },
  infoSection: {
    backgroundColor: '#2a2a2a',
    borderRadius: 12,
    padding: 16,
    borderLeftWidth: 4,
    borderLeftColor: '#00ff41',
  },
  infoTitle: {
    color: '#00ff41',
    fontSize: 16,
    fontWeight: 'bold',
    marginBottom: 12,
  },
  infoText: {
    color: '#cccccc',
    fontSize: 14,
    marginBottom: 8,
    lineHeight: 20,
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