/**
 * Home Screen - Main dashboard for AmISafe Mobile Application
 */

import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Alert,
  RefreshControl,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

// Services
import LocationService, { Location } from '../../services/location/LocationService';
import StorageService from '../../services/storage/StorageService';

// Utils
import { Colors } from '../../utils/colors';

interface SafetyScore {
  score: number;
  level: 'MINIMAL' | 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL';
  description: string;
}

interface QuickStats {
  totalIncidents: number;
  recentIncidents: number;
  safetyTrend: 'improving' | 'stable' | 'declining';
}

const HomeScreen: React.FC = () => {
  const [currentLocation, setCurrentLocation] = useState<Location | null>(null);
  const [safetyScore, setSafetyScore] = useState<SafetyScore | null>(null);
  const [quickStats, setQuickStats] = useState<QuickStats | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);

  useEffect(() => {
    initializeHomeScreen();
  }, []);

  const initializeHomeScreen = async () => {
    try {
      setIsLoading(true);
      
      // Get current location
      const location = await LocationService.getCurrentLocation();
      setCurrentLocation(location);
      
      // Load safety data for current location
      await loadSafetyData(location);
      
    } catch (error) {
      console.error('Error initializing home screen:', error);
      Alert.alert(
        'Location Error',
        'Unable to get your current location. Please check your location settings.',
        [{ text: 'OK' }]
      );
    } finally {
      setIsLoading(false);
    }
  };

  const loadSafetyData = async (location: Location) => {
    try {
      // Mock safety score calculation - in real app, this would call the API
      const mockSafetyScore: SafetyScore = {
        score: 75,
        level: 'MEDIUM',
        description: 'Moderate safety level in your area',
      };
      setSafetyScore(mockSafetyScore);

      // Mock quick stats - in real app, this would call the API
      const mockStats: QuickStats = {
        totalIncidents: 1250,
        recentIncidents: 15,
        safetyTrend: 'stable',
      };
      setQuickStats(mockStats);

    } catch (error) {
      console.error('Error loading safety data:', error);
    }
  };

  const handleRefresh = async () => {
    setIsRefreshing(true);
    await initializeHomeScreen();
    setIsRefreshing(false);
  };

  const getSafetyColor = (level: SafetyScore['level']): string => {
    switch (level) {
      case 'MINIMAL':
        return Colors.riskMinimal;
      case 'LOW':
        return Colors.riskLow;
      case 'MEDIUM':
        return Colors.riskMedium;
      case 'HIGH':
        return Colors.riskHigh;
      case 'CRITICAL':
        return Colors.riskCritical;
      default:
        return Colors.gray;
    }
  };

  const getTrendIcon = (trend: QuickStats['safetyTrend']): string => {
    switch (trend) {
      case 'improving':
        return 'trending-up';
      case 'declining':
        return 'trending-down';
      case 'stable':
      default:
        return 'trending-neutral';
    }
  };

  const getTrendColor = (trend: QuickStats['safetyTrend']): string => {
    switch (trend) {
      case 'improving':
        return Colors.success;
      case 'declining':
        return Colors.danger;
      case 'stable':
      default:
        return Colors.info;
    }
  };

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <Icon name="loading" size={48} color={Colors.primary} />
        <Text style={styles.loadingText}>Loading safety information...</Text>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={isRefreshing} onRefresh={handleRefresh} />
      }
    >
      {/* Header Section */}
      <View style={styles.header}>
        <Text style={styles.welcomeText}>Welcome to AmISafe</Text>
        <Text style={styles.subtitleText}>Stay informed, stay safe</Text>
      </View>

      {/* Current Location Card */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="map-marker" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Current Location</Text>
        </View>
        <View style={styles.cardContent}>
          {currentLocation ? (
            <>
              <Text style={styles.locationText}>
                {currentLocation.latitude.toFixed(4)}, {currentLocation.longitude.toFixed(4)}
              </Text>
              <Text style={styles.accuracyText}>
                Accuracy: {currentLocation.accuracy?.toFixed(0)}m
              </Text>
            </>
          ) : (
            <Text style={styles.errorText}>Unable to get location</Text>
          )}
        </View>
      </View>

      {/* Safety Score Card */}
      {safetyScore && (
        <View style={styles.card}>
          <View style={styles.cardHeader}>
            <Icon name="shield-check" size={24} color={getSafetyColor(safetyScore.level)} />
            <Text style={styles.cardTitle}>Safety Score</Text>
          </View>
          <View style={styles.cardContent}>
            <View style={styles.safetyScoreContainer}>
              <Text style={[styles.safetyScoreNumber, { color: getSafetyColor(safetyScore.level) }]}>
                {safetyScore.score}
              </Text>
              <View style={styles.safetyScoreDetails}>
                <Text style={[styles.safetyLevel, { color: getSafetyColor(safetyScore.level) }]}>
                  {safetyScore.level}
                </Text>
                <Text style={styles.safetyDescription}>
                  {safetyScore.description}
                </Text>
              </View>
            </View>
          </View>
        </View>
      )}

      {/* Quick Stats Card */}
      {quickStats && (
        <View style={styles.card}>
          <View style={styles.cardHeader}>
            <Icon name="chart-line" size={24} color={Colors.primary} />
            <Text style={styles.cardTitle}>Area Statistics</Text>
          </View>
          <View style={styles.cardContent}>
            <View style={styles.statsRow}>
              <View style={styles.statItem}>
                <Text style={styles.statNumber}>{quickStats.totalIncidents.toLocaleString()}</Text>
                <Text style={styles.statLabel}>Total Incidents</Text>
              </View>
              <View style={styles.statItem}>
                <Text style={styles.statNumber}>{quickStats.recentIncidents}</Text>
                <Text style={styles.statLabel}>Last 30 Days</Text>
              </View>
              <View style={styles.statItem}>
                <Icon 
                  name={getTrendIcon(quickStats.safetyTrend)} 
                  size={24} 
                  color={getTrendColor(quickStats.safetyTrend)} 
                />
                <Text style={styles.statLabel}>Trend</Text>
              </View>
            </View>
          </View>
        </View>
      )}

      {/* Quick Actions */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="flash" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Quick Actions</Text>
        </View>
        <View style={styles.cardContent}>
          <View style={styles.actionsGrid}>
            <TouchableOpacity style={styles.actionButton}>
              <Icon name="map" size={32} color={Colors.primary} />
              <Text style={styles.actionText}>View Map</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.actionButton}>
              <Icon name="alert" size={32} color={Colors.warning} />
              <Text style={styles.actionText}>Report Incident</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.actionButton}>
              <Icon name="phone" size={32} color={Colors.danger} />
              <Text style={styles.actionText}>Emergency</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.actionButton}>
              <Icon name="heart" size={32} color={Colors.success} />
              <Text style={styles.actionText}>Safety Tips</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: Colors.background,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: Colors.textSecondary,
  },
  header: {
    padding: 20,
    backgroundColor: Colors.primary,
    alignItems: 'center',
  },
  welcomeText: {
    fontSize: 24,
    fontWeight: 'bold',
    color: Colors.white,
    marginBottom: 4,
  },
  subtitleText: {
    fontSize: 16,
    color: Colors.white,
    opacity: 0.9,
  },
  card: {
    backgroundColor: Colors.white,
    margin: 16,
    borderRadius: 12,
    padding: 16,
    shadowColor: Colors.shadowMedium,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  cardTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: Colors.textPrimary,
    marginLeft: 8,
  },
  cardContent: {
    paddingLeft: 32,
  },
  locationText: {
    fontSize: 16,
    color: Colors.textPrimary,
    fontFamily: 'monospace',
  },
  accuracyText: {
    fontSize: 14,
    color: Colors.textSecondary,
    marginTop: 4,
  },
  errorText: {
    fontSize: 16,
    color: Colors.danger,
    fontStyle: 'italic',
  },
  safetyScoreContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  safetyScoreNumber: {
    fontSize: 48,
    fontWeight: 'bold',
    marginRight: 16,
  },
  safetyScoreDetails: {
    flex: 1,
  },
  safetyLevel: {
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 4,
  },
  safetyDescription: {
    fontSize: 14,
    color: Colors.textSecondary,
  },
  statsRow: {
    flexDirection: 'row',
    justifyContent: 'space-around',
  },
  statItem: {
    alignItems: 'center',
  },
  statNumber: {
    fontSize: 20,
    fontWeight: 'bold',
    color: Colors.textPrimary,
  },
  statLabel: {
    fontSize: 12,
    color: Colors.textSecondary,
    marginTop: 4,
    textAlign: 'center',
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  actionButton: {
    width: '48%',
    backgroundColor: Colors.lightGray,
    borderRadius: 8,
    padding: 16,
    alignItems: 'center',
    marginBottom: 12,
  },
  actionText: {
    fontSize: 14,
    color: Colors.textPrimary,
    marginTop: 8,
    textAlign: 'center',
  },
});

export default HomeScreen;