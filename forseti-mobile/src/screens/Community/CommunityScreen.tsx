/**
 * Community Screen - Join the Forseti Community
 */

import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Linking,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { Theme } from '../../utils/theme';

const { Colors, Spacing, Typography, Shadows } = Theme;

export const CommunityScreen: React.FC = () => {
  const openWebsite = (url: string) => {
    Linking.openURL(url);
  };

  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <Icon name="account-group" size={48} color={Colors.primary} />
        <Text style={styles.headerTitle}>Join Our Community</Text>
        <Text style={styles.headerSubtitle}>
          Connect with neighbors and stay informed about safety in Philadelphia
        </Text>
      </View>

      {/* Community Forums */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="forum" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Community Forums</Text>
        </View>
        <Text style={styles.cardText}>
          Join discussions with neighbors about safety concerns, local events, and community initiatives.
        </Text>
        <TouchableOpacity
          style={styles.button}
          onPress={() => openWebsite('https://forseti.life/community')}
        >
          <Text style={styles.buttonText}>Visit Forums</Text>
          <Icon name="arrow-right" size={16} color={Colors.white} />
        </TouchableOpacity>
      </View>

      {/* Neighborhood Watch */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="account-eye" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Neighborhood Watch</Text>
        </View>
        <Text style={styles.cardText}>
          Connect with your local neighborhood watch group and coordinate community safety efforts.
        </Text>
        <TouchableOpacity
          style={styles.button}
          onPress={() => openWebsite('https://forseti.life/community')}
        >
          <Text style={styles.buttonText}>Find My Group</Text>
          <Icon name="arrow-right" size={16} color={Colors.white} />
        </TouchableOpacity>
      </View>

      {/* Safety Ambassadors */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="shield-account" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Safety Ambassadors</Text>
        </View>
        <Text style={styles.cardText}>
          Become a Forseti Safety Ambassador and help spread awareness about community safety.
        </Text>
        <TouchableOpacity
          style={styles.button}
          onPress={() => openWebsite('https://forseti.life/community')}
        >
          <Text style={styles.buttonText}>Learn More</Text>
          <Icon name="arrow-right" size={16} color={Colors.white} />
        </TouchableOpacity>
      </View>

      {/* Community Events */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="calendar-star" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Community Events</Text>
        </View>
        <Text style={styles.cardText}>
          Stay informed about upcoming safety workshops, town halls, and community gatherings.
        </Text>
        <TouchableOpacity
          style={styles.button}
          onPress={() => openWebsite('https://forseti.life/community')}
        >
          <Text style={styles.buttonText}>View Events</Text>
          <Icon name="arrow-right" size={16} color={Colors.white} />
        </TouchableOpacity>
      </View>

      {/* Share Your Experience */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Icon name="message-alert" size={24} color={Colors.primary} />
          <Text style={styles.cardTitle}>Share Your Experience</Text>
        </View>
        <Text style={styles.cardText}>
          Help others by sharing safety tips, reporting concerns, and contributing to community awareness.
        </Text>
        <TouchableOpacity
          style={styles.button}
          onPress={() => openWebsite('https://forseti.life/community')}
        >
          <Text style={styles.buttonText}>Get Started</Text>
          <Icon name="arrow-right" size={16} color={Colors.white} />
        </TouchableOpacity>
      </View>

      <View style={styles.footer}>
        <Text style={styles.footerText}>
          Together, we can make Philadelphia safer for everyone.
        </Text>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  header: {
    padding: Spacing.lg,
    backgroundColor: Colors.backgroundDark,
    alignItems: 'center',
  },
  headerTitle: {
    ...Typography.heading2,
    color: Colors.primary,
    marginTop: Spacing.md,
    marginBottom: Spacing.sm,
  },
  headerSubtitle: {
    ...Typography.body,
    color: Colors.textSecondary,
    textAlign: 'center',
  },
  card: {
    backgroundColor: Colors.white,
    margin: Spacing.md,
    padding: Spacing.md,
    borderRadius: Spacing.borderRadius.lg,
    ...Shadows.md,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: Spacing.md,
  },
  cardTitle: {
    ...Typography.heading4,
    color: Colors.textPrimary,
    marginLeft: Spacing.sm,
  },
  cardText: {
    ...Typography.body,
    color: Colors.textSecondary,
    marginBottom: Spacing.md,
    lineHeight: 24,
  },
  button: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.primary,
    padding: Spacing.md,
    borderRadius: Spacing.borderRadius.md,
  },
  buttonText: {
    ...Typography.button,
    color: Colors.white,
    marginRight: Spacing.xs,
  },
  footer: {
    padding: Spacing.xl,
    alignItems: 'center',
  },
  footerText: {
    ...Typography.body,
    color: Colors.textSecondary,
    textAlign: 'center',
    fontStyle: 'italic',
  },
});

export default CommunityScreen;
