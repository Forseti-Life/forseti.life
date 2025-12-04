/**
 * Interactive Crime Map Component for AmISafe Mobile
 * 
 * React Native implementation based on the web crime-map.js functionality
 * Features: H3 hexagon visualization, zoom-based resolution switching, crime incidents
 */

import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Dimensions,
  Alert,
  TouchableOpacity,
  Modal,
  ScrollView,
  ActivityIndicator,
} from 'react-native';
import MapView, { PROVIDER_GOOGLE, Polygon, Circle, Marker } from 'react-native-maps';
import Svg, { Polygon as SvgPolygon } from 'react-native-svg';
import { h3 } from 'h3-js';
import FilterPanel from './FilterPanel';

const { width, height } = Dimensions.get('window');

// Philadelphia center coordinates (matching web implementation)
const PHILADELPHIA_CENTER = {
  latitude: 39.9526,
  longitude: -75.1652,
  latitudeDelta: 0.0922,
  longitudeDelta: 0.0421,
};

const InteractiveCrimeMap = ({ 
  initialLocation = PHILADELPHIA_CENTER,
  onLocationChange,
  filters = {},
  drupalCrimeService = null
}) => {
  // Core state
  const [mapRegion, setMapRegion] = useState(initialLocation);
  const [hexagons, setHexagons] = useState([]);
  const [incidents, setIncidents] = useState([]);
  const [currentFilters, setCurrentFilters] = useState(filters);
  const [isLoading, setIsLoading] = useState(false);
  const [selectedHexagon, setSelectedHexagon] = useState(null);
  const [showHexagonDetails, setShowHexagonDetails] = useState(false);
  const [citywideStats, setCitywideStats] = useState(null);
  
  // Map control state
  const [currentZoom, setCurrentZoom] = useState(12);
  const [viewMode, setViewMode] = useState('hexagon'); // hexagon, heatmap, points
  const [minimalMode] = useState(true); // Clean data visualization
  
  // Filter state
  const [showFilterPanel, setShowFilterPanel] = useState(false);
  const [activeFilters, setActiveFilters] = useState({
    crimeTypes: {
      part1Person: true,
      part1Property: true,
      part2: true,
      violent: true,
      nonviolent: true,
    },
    districts: [],
    datePreset: 'alltime',
    timePeriods: {
      earlyMorning: true,
      morning: true,
      afternoon: true,
      evening: true,
    },
  });
  
  // Performance optimization
  const mapRef = useRef(null);
  const dataCache = useRef(new Map());
  const currentRequest = useRef(null);
  
  /**
   * Get optimal H3 resolution based on zoom level (matching web implementation)
   */
  const getOptimalResolution = (zoomLevel) => {
    if (zoomLevel <= 9) return 4;        // ~1,770 km² - Complete metro area coverage
    else if (zoomLevel <= 10) return 5;  // ~251 km² - Metro districts
    else if (zoomLevel <= 11) return 6;  // ~36 km² - City areas
    else if (zoomLevel <= 12) return 7;  // ~5.2 km² - Neighborhoods
    else if (zoomLevel <= 13) return 8;  // ~0.7 km² - Block groups  
    else if (zoomLevel <= 14) return 9;  // ~0.1 km² - Street blocks
    else if (zoomLevel <= 15) return 10; // ~15,047 m² - Building groups
    else if (zoomLevel <= 16) return 11; // ~2,150 m² - Individual buildings
    else if (zoomLevel <= 17) return 12; // ~307 m² - Room-level precision
    else return 13;                      // ~44 m² - Ultra-precision
  };

  /**
   * Get human-readable description of H3 resolution
   */
  const getResolutionDescription = (resolution) => {
    const descriptions = {
      4: '~1,770km² metro-wide',
      5: '~251km² districts',
      6: '~36km² city areas',
      7: '~5.2km² neighborhoods',
      8: '~0.7km² block groups', 
      9: '~0.1km² street blocks',
      10: '~15,047m² building groups',
      11: '~2,150m² buildings',
      12: '~307m² rooms',
      13: '~44m² ultra-precision'
    };
    return descriptions[resolution] || 'unknown';
  };

  /**
   * Calculate risk level based on incident count
   */
  const calculateRiskLevel = (incidentCount) => {
    if (incidentCount === 0) return 'SAFE';
    else if (incidentCount <= 5) return 'LOW';
    else if (incidentCount <= 15) return 'MODERATE'; 
    else if (incidentCount <= 30) return 'HIGH';
    else return 'CRITICAL';
  };

  /**
   * Calculate hexagon styling based on incident count
   */
  /**
   * Calculate hexagon styling based on z-score (matching web implementation)
   * Uses incident_z_score for normalized heat map coloring across resolutions
   */
  const calculateHexagonStyle = (hexagonData) => {
    // Extract z-score from analytics if available, otherwise use incident count
    let zScore = 0;
    let incidentCount = 0;
    
    if (typeof hexagonData === 'object') {
      // Get z-score from analytics (prioritize this for accurate coloring)
      if (hexagonData.analytics && hexagonData.analytics.z_scores) {
        zScore = hexagonData.analytics.z_scores.incident || 0;
      }
      incidentCount = hexagonData.incident_count || hexagonData.incidentCount || 0;
    } else {
      // Legacy: hexagonData is just an incident count number
      incidentCount = hexagonData;
      // Fallback: estimate z-score from count
      zScore = Math.log10(Math.max(1, incidentCount));
    }
    
    // Color gradient based on z-score from -1 (green) to 11+ (red)
    let fillColor, strokeColor;
    let fillOpacity = 0.6;
    
    if (zScore >= 11.0) {
      fillColor = 'rgba(139, 0, 0, 0.95)';  // Dark red
      strokeColor = '#FF0000';
    } else if (zScore >= 10.0) {
      fillColor = 'rgba(165, 0, 0, 0.92)';  // Very dark red
      strokeColor = '#FF0000';
    } else if (zScore >= 9.0) {
      fillColor = 'rgba(220, 20, 60, 0.88)';  // Crimson
      strokeColor = '#FF1493';
    } else if (zScore >= 8.0) {
      fillColor = 'rgba(232, 37, 60, 0.85)';  // Bright crimson
      strokeColor = '#FF4444';
    } else if (zScore >= 7.0) {
      fillColor = 'rgba(255, 0, 0, 0.82)';  // Red
      strokeColor = '#FF4500';
    } else if (zScore >= 6.0) {
      fillColor = 'rgba(255, 36, 0, 0.78)';  // Scarlet
      strokeColor = '#FF5500';
    } else if (zScore >= 5.0) {
      fillColor = 'rgba(255, 69, 0, 0.75)';  // Orange-red
      strokeColor = '#FF6347';
    } else if (zScore >= 4.0) {
      fillColor = 'rgba(255, 102, 0, 0.72)';  // Red-orange
      strokeColor = '#FF7700';
    } else if (zScore >= 3.0) {
      fillColor = 'rgba(255, 140, 0, 0.68)';  // Dark orange
      strokeColor = '#FFA500';
    } else if (zScore >= 2.0) {
      fillColor = 'rgba(255, 165, 0, 0.65)';  // Orange
      strokeColor = '#FFB833';
    } else if (zScore >= 1.0) {
      fillColor = 'rgba(255, 176, 0, 0.62)';  // Yellow-orange
      strokeColor = '#FFC800';
    } else if (zScore >= 0.5) {
      fillColor = 'rgba(255, 200, 0, 0.58)';  // Gold
      strokeColor = '#FFD700';
    } else if (zScore >= 0) {
      fillColor = 'rgba(255, 215, 0, 0.55)';  // Yellow
      strokeColor = '#FFEC8B';
    } else if (zScore >= -0.5) {
      fillColor = 'rgba(173, 255, 47, 0.52)';  // Yellow-green
      strokeColor = '#90EE90';
    } else if (zScore >= -1.0) {
      fillColor = 'rgba(144, 238, 144, 0.48)';  // Light green
      strokeColor = '#7FFF7F';
    } else {
      fillColor = 'rgba(50, 205, 50, 0.45)';  // Green (very safe)
      strokeColor = '#3CB371';
    }
    
    return {
      fillColor,
      strokeColor,
      strokeWidth: 1
    };
  };

  /**
   * Get crime type name from code (matching web implementation)
   */
  const getCrimeTypeName = (code) => {
    const crimeTypes = {
      '1': 'Part I Crimes Against Person',
      '2': 'Part I Crimes Against Property',
      '3': 'Part II Crimes Against Person',
      '4': 'Part II Crimes Against Property',
      '5': 'Part II Crimes Against Society',
      '6': 'Crimes Against Children',
      '7': 'Other Offenses',
      '8': 'Traffic',
      '9': 'Unknown/Other'
    };
    
    if (!code) return 'Unknown';
    
    const codeStr = code.toString();
    for (const [key, value] of Object.entries(crimeTypes)) {
      if (codeStr.startsWith(key)) {
        return value;
      }
    }
    
    return 'Other';
  };

  /**
   * Get incident marker color based on crime type
   */
  const getIncidentColor = (incidentType) => {
    const colors = {
      'violent': '#ff4444',     // Red for violent crimes
      'property': '#ff8800',    // Orange for property crimes  
      'drug': '#8844ff',        // Purple for drug crimes
      'traffic': '#44ff44',     // Green for traffic incidents
      'other': '#44ffff'        // Cyan for other incidents
    };
    
    if (!incidentType) return colors.other;
    
    const code = incidentType.toString();
    if (code.startsWith('1') || code.startsWith('2')) return colors.violent;
    if (code.startsWith('3') || code.startsWith('5')) return colors.property;
    if (code.startsWith('4')) return colors.drug;
    if (code.startsWith('7')) return colors.traffic;
    
    return colors.other;
  };

  /**
   * Convert H3 hexagon to React Native MapView polygon coordinates
   */
  const h3ToPolygonCoords = (h3Index) => {
    try {
      if (!h3Index) {
        return null;
      }
      
      if (!h3 || typeof h3.cellToBoundary !== 'function') {
        console.warn('H3 library not available');
        return null;
      }
      
      // Get H3 boundary coordinates
      const boundary = h3.cellToBoundary(h3Index, true);
      
      if (!boundary || !Array.isArray(boundary)) {
        return null;
      }
      
      // Convert from H3 [lng, lat] to React Native MapView format
      return boundary.map(coord => ({
        latitude: coord[1],
        longitude: coord[0]
      }));
    } catch (error) {
      console.warn('Failed to convert H3 to polygon:', error.message);
      return null;
    }
  };

  /**
   * Convert internal filter state to API format
   */
  const convertFiltersForAPI = (filters) => {
    const apiFilters = {};
    
    // Crime types - convert to array of enabled types
    if (filters.crimeTypes) {
      const enabledTypes = [];
      if (filters.crimeTypes.part1Person) enabledTypes.push('part1_person');
      if (filters.crimeTypes.part1Property) enabledTypes.push('part1_property');
      if (filters.crimeTypes.part2) enabledTypes.push('part2');
      if (filters.crimeTypes.violent) enabledTypes.push('violent');
      if (filters.crimeTypes.nonviolent) enabledTypes.push('nonviolent');
      
      // Only add if filtering (not all enabled)
      if (enabledTypes.length > 0 && enabledTypes.length < 5) {
        apiFilters.crimeTypes = enabledTypes;
      }
    }
    
    // Districts
    if (filters.districts && filters.districts.length > 0 && filters.districts.length < 25) {
      apiFilters.districts = filters.districts;
    }
    
    // Date preset - convert to date range
    if (filters.datePreset && filters.datePreset !== 'alltime') {
      const now = new Date();
      const monthsAgo = filters.datePreset === '6months' ? 6 : 12;
      const startDate = new Date(now.setMonth(now.getMonth() - monthsAgo));
      apiFilters.date_range = filters.datePreset;
    }
    
    // Time periods - convert to array of enabled periods
    if (filters.timePeriods) {
      const enabledPeriods = [];
      if (filters.timePeriods.earlyMorning) enabledPeriods.push('early_morning');
      if (filters.timePeriods.morning) enabledPeriods.push('morning');
      if (filters.timePeriods.afternoon) enabledPeriods.push('afternoon');
      if (filters.timePeriods.evening) enabledPeriods.push('evening');
      
      // Only add if filtering (not all enabled)
      if (enabledPeriods.length > 0 && enabledPeriods.length < 4) {
        apiFilters.timePeriods = enabledPeriods;
      }
    }
    
    return apiFilters;
  };

  /**
   * Load hexagon crime data
   */
  const loadHexagonData = async () => {
    setIsLoading(true);
    
    try {
      if (!drupalCrimeService) {
        console.error('❌ No Drupal crime service provided');
        Alert.alert('Error', 'Crime data service not available');
        return;
      }
      
      const resolution = getOptimalResolution(currentZoom);
      console.log(`\n📊 [MOBILE] Loading H3 Resolution ${resolution} data...`);
      console.log(`  Zoom: ${currentZoom}`);
      console.log(`  Map Region:`, {
        center: [mapRegion.latitude.toFixed(4), mapRegion.longitude.toFixed(4)],
        delta: [mapRegion.latitudeDelta.toFixed(4), mapRegion.longitudeDelta.toFixed(4)]
      });
      
      // Build bounds string for API (matching web format)
      const north = mapRegion.latitude + (mapRegion.latitudeDelta / 2);
      const south = mapRegion.latitude - (mapRegion.latitudeDelta / 2);
      const east = mapRegion.longitude + (mapRegion.longitudeDelta / 2);
      const west = mapRegion.longitude - (mapRegion.longitudeDelta / 2);
      const bounds = `${north},${east},${south},${west}`;
      
      console.log(`  Bounds: N=${north.toFixed(4)} S=${south.toFixed(4)} E=${east.toFixed(4)} W=${west.toFixed(4)}`);
      console.log(`  Active Filters:`, activeFilters);
      
      // Convert activeFilters to API format
      const apiFilters = convertFiltersForAPI(activeFilters);
      
      // Use Drupal crime service
      const data = await drupalCrimeService.getAggregatedData(resolution, bounds, apiFilters);
      
      console.log('\n📊 [MOBILE] Received data from Drupal API:');
      console.log(`  Hexagons: ${data.hexagons ? data.hexagons.length : 0}`);
      console.log(`  Resolution: ${data.meta ? data.meta.resolution : 'unknown'}`);
      if (data.hexagons && data.hexagons.length > 0) {
        console.log(`  Sample hexagon:`, data.hexagons[0]);
      }
      
      if (data.hexagons && data.hexagons.length > 0) {
        console.log(`✅ [MOBILE] Setting ${data.hexagons.length} hexagons for rendering`);
        setHexagons(data.hexagons);
        
        // Update visible incidents count
        const incidentCount = updateVisibleIncidentsCount(data.hexagons);
        console.log(`📊 Total visible incidents: ${incidentCount}`);
        
        // Load individual incidents for high-resolution views
        if (resolution >= 10) {
          loadIncidentPoints();
        }
      } else {
        console.log('⚠️ [MOBILE] No hexagons received - clearing display');
        setHexagons([]);
      }
      
    } catch (error) {
      console.error('Error loading hexagon data:', error);
      Alert.alert('Error', 'Failed to load crime data. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  /**
   * Load individual incident points for high-resolution views
   */
  const loadIncidentPoints = async () => {
    try {
      if (!drupalCrimeService) {
        console.warn('⚠️ No Drupal crime service for incident points');
        return;
      }
      
      const north = mapRegion.latitude + (mapRegion.latitudeDelta / 2);
      const south = mapRegion.latitude - (mapRegion.latitudeDelta / 2);
      const east = mapRegion.longitude + (mapRegion.longitudeDelta / 2);
      const west = mapRegion.longitude - (mapRegion.longitudeDelta / 2);
      const bounds = `${north},${east},${south},${west}`;
      
      // Use Drupal crime service
      const data = await drupalCrimeService.getIncidents(bounds, currentFilters);
      
      if (data.incidents && data.incidents.length > 0) {
        setIncidents(data.incidents);
        console.log(`📍 Loaded ${data.incidents.length} individual incidents via Drupal`);
      } else {
        setIncidents([]);
      }
    } catch (error) {
      console.error('Error loading incident points:', error);
    }
  };

  /**
   * Load citywide statistics
   */
  const loadCitywideStats = async () => {
    try {
      if (!drupalCrimeService) {
        console.warn('⚠️ No Drupal crime service for citywide stats');
        return;
      }
      
      const data = await drupalCrimeService.getCitywideStats();
      if (data) {
        setCitywideStats(data);
        console.log('📈 Loaded citywide statistics via Drupal');
      }
    } catch (error) {
      console.error('Error loading citywide stats:', error);
    }
  };

  /**
   * Update visible incidents count based on rendered hexagons
   */
  const updateVisibleIncidentsCount = (hexagonData) => {
    if (!hexagonData || hexagonData.length === 0) {
      return 0;
    }
    
    const totalIncidents = hexagonData.reduce((sum, hex) => {
      return sum + (hex.incident_count || 0);
    }, 0);
    
    console.log(`📊 Visible incidents: ${totalIncidents} across ${hexagonData.length} hexagons`);
    return totalIncidents;
  };

  /**
   * Get current incident count from loaded hexagons
   */
  const getCurrentIncidentCount = () => {
    return hexagons.reduce((sum, hex) => sum + (hex.incident_count || 0), 0);
  };

  /**
   * Get active sector count (hexagons with incidents)
   */
  const getActiveSectorCount = () => {
    return hexagons.filter(hex => (hex.incident_count || 0) > 0).length;
  };

  /**
   * Reset view to initial state
   */
  const resetView = () => {
    console.log('🔄 Resetting map view...');
    
    // Reset map to initial location
    if (mapRef.current) {
      mapRef.current.animateToRegion(initialLocation, 1000);
    }
    
    // Clear filters
    setCurrentFilters({});
    
    // Reload data
    loadHexagonData();
  };

  /**
   * Fit map to show all hexagons
   */
  const fitMapToHexagons = () => {
    if (hexagons.length === 0 || !mapRef.current) {
      return;
    }
    
    // Calculate bounds from hexagons
    let minLat = Infinity, maxLat = -Infinity;
    let minLng = Infinity, maxLng = -Infinity;
    
    hexagons.forEach(hex => {
      if (hex.center) {
        const lat = hex.center.lat;
        const lng = hex.center.lng;
        minLat = Math.min(minLat, lat);
        maxLat = Math.max(maxLat, lat);
        minLng = Math.min(minLng, lng);
        maxLng = Math.max(maxLng, lng);
      }
    });
    
    if (minLat !== Infinity && maxLat !== -Infinity) {
      const centerLat = (minLat + maxLat) / 2;
      const centerLng = (minLng + maxLng) / 2;
      const latDelta = (maxLat - minLat) * 1.2; // Add 20% padding
      const lngDelta = (maxLng - minLng) * 1.2;
      
      mapRef.current.animateToRegion({
        latitude: centerLat,
        longitude: centerLng,
        latitudeDelta: Math.max(latDelta, 0.01),
        longitudeDelta: Math.max(lngDelta, 0.01),
      }, 1000);
      
      console.log(`📍 Fitted map to ${hexagons.length} hexagons`);
    }
  };

  /**
   * Apply filters to crime data
   * Matches web implementation's applyFilters function
   */
  const applyFilters = (filters) => {
    console.log('🔍 ApplyFilters: New filters applied:', filters);
    setActiveFilters(filters);
    
    // Reload data with new filters
    loadHexagonData();
  };

  /**
   * Clear all filters and reload data
   * Matches web implementation's clearAllFilters function
   */
  const clearAllFilters = () => {
    console.log('🔍 ClearAllFilters: Resetting to defaults');
    const defaultFilters = {
      crimeTypes: {
        part1Person: true,
        part1Property: true,
        part2: true,
        violent: true,
        nonviolent: true,
      },
      districts: [],
      datePreset: 'alltime',
      timePeriods: {
        earlyMorning: true,
        morning: true,
        afternoon: true,
        evening: true,
      },
    };
    setActiveFilters(defaultFilters);
    loadHexagonData();
  };

  /**
   * Count active filters for badge display
   */
  const getActiveFilterCount = () => {
    let count = 0;
    
    // Crime types that are disabled
    const disabledCrimeTypes = Object.values(activeFilters.crimeTypes).filter(v => !v).length;
    if (disabledCrimeTypes > 0) count += disabledCrimeTypes;
    
    // Districts selected (only count if not all)
    if (activeFilters.districts.length > 0 && activeFilters.districts.length < 25) count += 1;
    
    // Date preset (if not all time)
    if (activeFilters.datePreset !== 'alltime') count += 1;
    
    // Time periods disabled
    const disabledTimePeriods = Object.values(activeFilters.timePeriods).filter(v => !v).length;
    if (disabledTimePeriods > 0) count += disabledTimePeriods;
    
    return count;
  };

  /**
   * Handle map region change
   */
  const onRegionChangeComplete = (region) => {
    try {
      setMapRegion(region);
      
      // Estimate zoom level from latitudeDelta
      const zoom = Math.round(Math.log(360 / region.latitudeDelta) / Math.LN2);
      setCurrentZoom(zoom);
      
      // Reload data for new region (matching web implementation handleMapMove)
      setTimeout(() => {
        loadHexagonData().catch(err => {
          console.error('Error loading hexagons on zoom:', err);
        });
      }, 500);
    } catch (error) {
      console.error('Error in onRegionChangeComplete:', error);
    }
  };

  /**
   * Handle hexagon press
   */
  const onHexagonPress = (hexagon) => {
    setSelectedHexagon(hexagon);
    setShowHexagonDetails(true);
  };

  /**
   * Create hexagon detail modal content
   */
  const renderHexagonDetails = () => {
    if (!selectedHexagon) return null;
    
    const incidentCount = selectedHexagon.incident_count || selectedHexagon.incidentCount || 0;
    const h3Index = selectedHexagon.h3_index || selectedHexagon.h3Index || 'Unknown';
    const h3Resolution = selectedHexagon.resolution || selectedHexagon.h3_resolution || 'Unknown';
    const riskLevel = calculateRiskLevel(incidentCount);
    const uniqueTypes = selectedHexagon.unique_incident_types || selectedHexagon.unique_types || 0;
    
    return (
      <Modal
        visible={showHexagonDetails}
        animationType="slide"
        presentationStyle="pageSheet"
      >
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>H3:{h3Resolution} Sector Analysis</Text>
            <TouchableOpacity
              style={styles.closeButton}
              onPress={() => setShowHexagonDetails(false)}
            >
              <Text style={styles.closeButtonText}>✕</Text>
            </TouchableOpacity>
          </View>
          
          <ScrollView style={styles.modalContent}>
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>📊 Crime Statistics</Text>
              <View style={styles.statGrid}>
                <View style={styles.statItem}>
                  <Text style={styles.statLabel}>Total Incidents:</Text>
                  <Text style={[styles.statValue, styles[`risk${riskLevel}`]]}>
                    {incidentCount.toLocaleString()}
                  </Text>
                </View>
                <View style={styles.statItem}>
                  <Text style={styles.statLabel}>Crime Types:</Text>
                  <Text style={styles.statValue}>{uniqueTypes}</Text>
                </View>
                <View style={styles.statItem}>
                  <Text style={styles.statLabel}>Risk Level:</Text>
                  <Text style={[styles.statValue, styles[`risk${riskLevel}`]]}>
                    {riskLevel}
                  </Text>
                </View>
              </View>
            </View>
            
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>🌍 Geographic Details</Text>
              <Text style={styles.infoText}>H3 Index: {h3Index}</Text>
              <Text style={styles.infoText}>
                Precision: {getResolutionDescription(h3Resolution)}
              </Text>
            </View>
          </ScrollView>
        </View>
      </Modal>
    );
  };

  // Load initial data
  useEffect(() => {
    loadHexagonData();
    loadCitywideStats();
  }, []);

  // Reload data when filters change
  useEffect(() => {
    if (currentFilters !== filters) {
      setCurrentFilters(filters);
      loadHexagonData();
    }
  }, [filters]);

  return (
    <View style={styles.container}>
      <MapView
        ref={mapRef}
        provider={PROVIDER_GOOGLE}
        style={styles.map}
        initialRegion={initialLocation}
        onRegionChangeComplete={onRegionChangeComplete}
        mapType="standard"
        showsUserLocation={true}
        showsMyLocationButton={true}
        showsCompass={true}
        showsScale={true}
      >
        {/* Render H3 Hexagons */}
        {hexagons.map((hexagon, index) => {
          const coords = h3ToPolygonCoords(hexagon.h3_index);
          if (!coords) return null;
          
          // Pass full hexagon object for z-score styling
          const style = calculateHexagonStyle(hexagon);
          
          return (
            <Polygon
              key={`hexagon-${index}`}
              coordinates={coords}
              fillColor={style.fillColor}
              strokeColor={style.strokeColor}
              strokeWidth={style.strokeWidth}
              onPress={() => onHexagonPress(hexagon)}
            />
          );
        })}
        
        {/* Render Individual Incidents */}
        {incidents.map((incident, index) => (
          <Marker
            key={`incident-${index}`}
            coordinate={{
              latitude: incident.lat,
              longitude: incident.lng
            }}
            pinColor={getIncidentColor(incident.incident_type)}
            title={`Crime: ${incident.incident_type}`}
            description={`${incident.incident_date} - ${incident.location_block}`}
          />
        ))}
      </MapView>
      
      {/* Loading Indicator */}
      {isLoading && (
        <View style={styles.loadingOverlay}>
          <ActivityIndicator size="large" color="#00ff41" />
          <Text style={styles.loadingText}>Loading Crime Data...</Text>
        </View>
      )}
      
      {/* Map Controls */}
      <View style={styles.mapControls}>
        <Text style={styles.zoomIndicator}>
          Zoom: {currentZoom} | H3: {getOptimalResolution(currentZoom)} 
        </Text>
        <Text style={styles.resolutionDescription}>
          {getResolutionDescription(getOptimalResolution(currentZoom))}
        </Text>
        <Text style={styles.hexagonCount}>
          {hexagons.length} hexagons | {getActiveSectorCount()} active
        </Text>
        <Text style={styles.incidentCount}>
          {getCurrentIncidentCount().toLocaleString()} incidents | {incidents.length} points
        </Text>
      </View>
      
      {/* Action Buttons */}
      <View style={styles.actionButtons}>
        <TouchableOpacity style={styles.actionButton} onPress={resetView}>
          <Text style={styles.actionButtonText}>Reset</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.actionButton} onPress={fitMapToHexagons}>
          <Text style={styles.actionButtonText}>Fit View</Text>
        </TouchableOpacity>
        <TouchableOpacity 
          style={[styles.actionButton, getActiveFilterCount() > 0 && styles.actionButtonActive]} 
          onPress={() => setShowFilterPanel(true)}
        >
          <Text style={styles.actionButtonText}>
            Filters {getActiveFilterCount() > 0 && `(${getActiveFilterCount()})`}
          </Text>
        </TouchableOpacity>
      </View>
      
      {/* Hexagon Details Modal */}
      {renderHexagonDetails()}
      
      {/* Filter Panel */}
      <FilterPanel
        visible={showFilterPanel}
        onClose={() => setShowFilterPanel(false)}
        onApplyFilters={applyFilters}
        currentFilters={activeFilters}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  map: {
    flex: 1,
  },
  loadingOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    color: '#00ff41',
    marginTop: 10,
    fontSize: 16,
    fontWeight: 'bold',
  },
  mapControls: {
    position: 'absolute',
    bottom: 20,
    left: 20,
    right: 20,
    backgroundColor: 'rgba(0, 0, 0, 0.8)',
    padding: 10,
    borderRadius: 8,
  },
  zoomIndicator: {
    color: '#00ff41',
    fontSize: 14,
    fontWeight: 'bold',
    textAlign: 'center',
  },
  resolutionDescription: {
    color: '#ffffff',
    fontSize: 12,
    textAlign: 'center',
    marginTop: 2,
  },
  hexagonCount: {
    color: '#cccccc',
    fontSize: 11,
    textAlign: 'center',
    marginTop: 2,
  },
  incidentCount: {
    color: '#cccccc',
    fontSize: 11,
    textAlign: 'center',
    marginTop: 2,
  },
  actionButtons: {
    position: 'absolute',
    top: 60,
    right: 10,
    flexDirection: 'column',
    gap: 10,
  },
  actionButton: {
    backgroundColor: 'rgba(0, 255, 65, 0.9)',
    paddingVertical: 10,
    paddingHorizontal: 15,
    borderRadius: 8,
    marginBottom: 10,
    elevation: 5,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 3.84,
  },
  actionButtonText: {
    color: '#000000',
    fontSize: 14,
    fontWeight: 'bold',
    textAlign: 'center',
  },
  actionButtonActive: {
    backgroundColor: 'rgba(255, 165, 0, 0.9)', // Orange for active filters
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
  section: {
    marginBottom: 20,
  },
  sectionTitle: {
    color: '#00ff41',
    fontSize: 16,
    fontWeight: 'bold',
    marginBottom: 10,
  },
  statGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  statItem: {
    width: '48%',
    marginBottom: 10,
  },
  statLabel: {
    color: '#cccccc',
    fontSize: 12,
  },
  statValue: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  infoText: {
    color: '#ffffff',
    fontSize: 14,
    marginBottom: 5,
  },
  riskSAFE: { color: '#00ff00' },
  riskLOW: { color: '#88ff00' },
  riskMODERATE: { color: '#ffff00' },
  riskHIGH: { color: '#ff8800' },
  riskCRITICAL: { color: '#ff0000' },
});

export default InteractiveCrimeMap;