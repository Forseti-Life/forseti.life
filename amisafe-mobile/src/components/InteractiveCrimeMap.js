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
  const calculateHexagonStyle = (incidentCount) => {
    const maxIntensity = 100;
    const intensity = Math.min(incidentCount / maxIntensity, 1);
    
    return {
      fillColor: minimalMode ? '#00ff41' : '#ff0040',
      strokeColor: minimalMode ? '#00ff41' : '#00bfff',
      strokeWidth: 1,
      fillOpacity: 0.3 + (intensity * 0.4),
    };
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
      // Get H3 boundary coordinates
      const boundary = h3.cellToBoundary(h3Index, true);
      
      // Convert from H3 [lng, lat] to React Native MapView format
      return boundary.map(coord => ({
        latitude: coord[1],
        longitude: coord[0]
      }));
    } catch (error) {
      console.warn('Failed to convert H3 to polygon:', error);
      return null;
    }
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
      console.log(`📊 Loading H3 Resolution ${resolution} data for mobile map...`);
      
      // Build bounds string for API
      const north = mapRegion.latitude + (mapRegion.latitudeDelta / 2);
      const south = mapRegion.latitude - (mapRegion.latitudeDelta / 2);
      const east = mapRegion.longitude + (mapRegion.longitudeDelta / 2);
      const west = mapRegion.longitude - (mapRegion.longitudeDelta / 2);
      const bounds = `${north},${east},${south},${west}`;
      
      // Use Drupal crime service
      const data = await drupalCrimeService.getAggregatedData(resolution, bounds, currentFilters);
      
      console.log('📊 Received mobile hexagon data via Drupal:', {
        hexagons: data.hexagons ? data.hexagons.length : 0,
        resolution: data.meta ? data.meta.resolution : 'unknown'
      });
      
      if (data.hexagons && data.hexagons.length > 0) {
        setHexagons(data.hexagons);
        
        // Load individual incidents for high-resolution views
        if (resolution >= 10) {
          loadIncidentPoints();
        }
      } else {
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
   * Handle map region change
   */
  const onRegionChangeComplete = (region) => {
    setMapRegion(region);
    
    // Estimate zoom level from latitudeDelta
    const zoom = Math.round(Math.log(360 / region.latitudeDelta) / Math.LN2);
    setCurrentZoom(zoom);
    
    // Notify parent component
    if (onLocationChange) {
      onLocationChange({
        latitude: region.latitude,
        longitude: region.longitude,
        zoom: zoom
      });
    }
    
    // Reload data for new region
    setTimeout(() => {
      loadHexagonData();
    }, 500);
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
          
          const style = calculateHexagonStyle(hexagon.incident_count || 0);
          
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
          {hexagons.length} hexagons | {incidents.length} incidents
        </Text>
      </View>
      
      {/* Hexagon Details Modal */}
      {renderHexagonDetails()}
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