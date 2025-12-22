/**
 * Home Screen - Web Version
 * Simplified dashboard optimized for web preview
 */

import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity } from 'react-native';
import Icon from '../../components/Icon.web';
import { Colors } from '../../utils/colors';

const HomeScreen: React.FC = () => {
  console.log('🏠 HomeScreen.web.tsx rendering...');

  const [safetyScore] = useState(75);
  const safetyLevel = 'MEDIUM';
  const location = 'Philadelphia, PA';

  console.log('HomeScreen state:', { safetyScore, safetyLevel, location });

  const getSafetyColor = () => {
    if (safetyScore >= 80) return '#4CAF50';
    if (safetyScore >= 60) return '#FF9800';
    return '#F44336';
  };

  const quickActions = [
    { icon: 'map', label: 'View Map', color: Colors.primary },
    { icon: 'robot', label: 'AI Chat', color: '#9C27B0' },
    { icon: 'alert', label: 'Report Incident', color: '#F44336' },
    { icon: 'shield-check', label: 'Safety Tips', color: '#4CAF50' },
  ];

  const recentIncidents = [
    { type: 'Theft', distance: '0.3 mi', time: '2 hours ago', severity: 'low' },
    { type: 'Assault', distance: '0.8 mi', time: '5 hours ago', severity: 'high' },
    { type: 'Vandalism', distance: '1.2 mi', time: '1 day ago', severity: 'medium' },
  ];

  console.log('HomeScreen about to render, Colors:', Colors);
  console.log('quickActions:', quickActions);

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ paddingBottom: 100, minHeight: '100%' }}
    >
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.welcomeText}>Welcome to Forseti</Text>
        <Text style={styles.subtitleText}>AI-Powered Safety Monitoring</Text>
      </View>

      {/* Location Card */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="map-marker" size={20} color={Colors.primary} />
          <Text style={styles.cardTitle}>Current Location</Text>
        </View>
        <Text style={styles.locationText}>{location}</Text>
        <Text style={styles.locationSubtext}>Tap to change location</Text>
      </View>

      {/* Safety Score */}
      <View style={[styles.card, styles.safetyCard]}>
        <Text style={styles.cardTitle}>Safety Score</Text>
        <View style={styles.scoreContainer}>
          <View style={[styles.scoreCircle, { borderColor: getSafetyColor() }]}>
            <Text style={[styles.scoreNumber, { color: getSafetyColor() }]}>{safetyScore}</Text>
          </View>
          <View style={styles.scoreDetails}>
            <Text style={[styles.scoreLevel, { color: getSafetyColor() }]}>{safetyLevel}</Text>
            <Text style={styles.scoreDescription}>Moderate safety level in your area</Text>
          </View>
        </View>
      </View>

      {/* Statistics */}
      <View style={styles.statsContainer}>
        <View style={styles.statBox}>
          <Text style={styles.statNumber}>1,247</Text>
          <Text style={styles.statLabel}>Total Incidents</Text>
        </View>
        <View style={styles.statBox}>
          <Text style={styles.statNumber}>18</Text>
          <Text style={styles.statLabel}>This Month</Text>
        </View>
        <View style={styles.statBox}>
          <Icon name="trending-up" size={32} color="#4CAF50" />
          <Text style={styles.statLabel}>Improving</Text>
        </View>
      </View>

      {/* Quick Actions */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Quick Actions</Text>
        <View style={styles.actionsGrid}>
          {quickActions.map((action, index) => (
            <TouchableOpacity
              key={index}
              style={styles.actionButton}
              onPress={() => console.log(`${action.label} pressed`)}
            >
              <View style={[styles.actionIcon, { backgroundColor: action.color + '20' }]}>
                <Icon name={action.icon} size={24} color={action.color} />
              </View>
              <Text style={styles.actionLabel}>{action.label}</Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>

      {/* Recent Incidents */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="alert-circle" size={20} color={Colors.primary} />
          <Text style={styles.cardTitle}>Recent Nearby Incidents</Text>
        </View>
        {recentIncidents.map((incident, index) => (
          <View key={index} style={styles.incidentRow}>
            <View
              style={[
                styles.severityDot,
                {
                  backgroundColor:
                    incident.severity === 'high'
                      ? '#F44336'
                      : incident.severity === 'medium'
                        ? '#FF9800'
                        : '#FFC107',
                },
              ]}
            />
            <View style={styles.incidentInfo}>
              <Text style={styles.incidentType}>{incident.type}</Text>
              <Text style={styles.incidentDetails}>
                {incident.distance} • {incident.time}
              </Text>
            </View>
            <TouchableOpacity>
              <Icon name="chevron-right" size={20} color={Colors.textSecondary} />
            </TouchableOpacity>
          </View>
        ))}
        <TouchableOpacity style={styles.viewAllButton}>
          <Text style={styles.viewAllText}>View All Incidents</Text>
        </TouchableOpacity>
      </View>

      {/* Safety Tips */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="lightbulb" size={20} color="#FFC107" />
          <Text style={styles.cardTitle}>Safety Tip of the Day</Text>
        </View>
        <Text style={styles.tipText}>
          Stay aware of your surroundings, especially in unfamiliar areas. Keep valuables out of
          sight and walk in well-lit areas at night.
        </Text>
      </View>

      <View style={styles.footer}>
        <Text style={styles.footerText}>
          Powered by H3 Geospatial Intelligence • Updated 5 min ago
        </Text>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  actionButton: {
    alignItems: 'center',
    marginBottom: 16,
    width: '48%',
  },
  actionIcon: {
    alignItems: 'center',
    borderRadius: 28,
    height: 56,
    justifyContent: 'center',
    marginBottom: 8,
    width: 56,
  },
  actionLabel: {
    color: Colors.text,
    fontSize: 12,
    textAlign: 'center',
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    marginTop: 12,
  },
  card: {
    backgroundColor: Colors.surface,
    borderRadius: 12,
    margin: 12,
    padding: 16,
  },
  cardHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    marginBottom: 12,
  },
  cardTitle: {
    color: Colors.text,
    fontSize: 16,
    fontWeight: 'bold',
    marginLeft: 8,
  },
  container: {
    backgroundColor: Colors.background,
    flex: 1,
    height: '100%',
    width: '100%',
  },
  footer: {
    alignItems: 'center',
    padding: 20,
  },
  footerText: {
    color: Colors.textSecondary,
    fontSize: 11,
    textAlign: 'center',
  },
  header: {
    backgroundColor: Colors.primary,
    minHeight: 80,
    padding: 20,
    paddingTop: 16,
  },
  incidentDetails: {
    color: Colors.textSecondary,
    fontSize: 12,
  },
  incidentInfo: {
    flex: 1,
  },
  incidentRow: {
    alignItems: 'center',
    borderBottomColor: Colors.border,
    borderBottomWidth: 1,
    flexDirection: 'row',
    paddingVertical: 12,
  },
  incidentType: {
    color: Colors.text,
    fontSize: 14,
    fontWeight: '600',
    marginBottom: 2,
  },
  locationSubtext: {
    color: Colors.textSecondary,
    fontSize: 12,
  },
  locationText: {
    color: Colors.text,
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 4,
  },
  safetyCard: {
    alignItems: 'center',
  },
  scoreCircle: {
    alignItems: 'center',
    borderRadius: 40,
    borderWidth: 4,
    height: 80,
    justifyContent: 'center',
    marginRight: 16,
    width: 80,
  },
  scoreContainer: {
    alignItems: 'center',
    flexDirection: 'row',
    marginTop: 12,
  },
  scoreDescription: {
    color: Colors.textSecondary,
    fontSize: 14,
  },
  scoreDetails: {
    flex: 1,
  },
  scoreLevel: {
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  scoreNumber: {
    fontSize: 32,
    fontWeight: 'bold',
  },
  severityDot: {
    borderRadius: 5,
    height: 10,
    marginRight: 12,
    width: 10,
  },
  statBox: {
    alignItems: 'center',
    backgroundColor: Colors.surface,
    borderRadius: 12,
    flex: 1,
    marginHorizontal: 4,
    padding: 16,
  },
  statLabel: {
    color: Colors.textSecondary,
    fontSize: 12,
    textAlign: 'center',
  },
  statNumber: {
    color: Colors.primary,
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  statsContainer: {
    flexDirection: 'row',
    marginBottom: 12,
    marginHorizontal: 12,
  },
  subtitleText: {
    color: '#FFFFFF',
    fontSize: 14,
    opacity: 0.9,
  },
  tipText: {
    color: Colors.text,
    fontSize: 14,
    lineHeight: 20,
    marginTop: 8,
  },
  viewAllButton: {
    alignItems: 'center',
    marginTop: 12,
    paddingVertical: 8,
  },
  viewAllText: {
    color: Colors.primary,
    fontSize: 14,
    fontWeight: '600',
  },
  welcomeText: {
    color: '#FFFFFF',
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 4,
  },
});

export default HomeScreen;
