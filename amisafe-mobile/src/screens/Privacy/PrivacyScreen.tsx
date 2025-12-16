/**
 * Privacy Screen - Privacy & Security policy
 */

import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { Colors } from '../../utils/colors';

const PrivacyScreen: React.FC = () => {
  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <Icon name="shield-lock" size={64} color={Colors.primary} />
        <Text style={styles.title}>Privacy & Security</Text>
      </View>

      <View style={styles.content}>
        <View style={styles.commitment}>
          <Text style={styles.commitmentTitle}>Our Commitment: Privacy First</Text>
          <Text style={styles.commitmentText}>
            At Forseti, we believe safety and privacy go hand-in-hand. We never sell 
            your data, and we design every feature with your privacy in mind.
          </Text>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Data Collection</Text>
          
          <Text style={styles.subTitle}>What We Collect</Text>
          <View style={styles.list}>
            <View style={styles.listItem}>
              <Icon name="check-circle" size={20} color={Colors.success} />
              <Text style={styles.listItemText}>
                <Text style={styles.bold}>Crime Data:</Text> Public incident data from 
                Philadelphia PD and emergency services
              </Text>
            </View>
            <View style={styles.listItem}>
              <Icon name="check-circle" size={20} color={Colors.success} />
              <Text style={styles.listItemText}>
                <Text style={styles.bold}>Location Data:</Text> Only when you explicitly 
                enable location services
              </Text>
            </View>
            <View style={styles.listItem}>
              <Icon name="check-circle" size={20} color={Colors.success} />
              <Text style={styles.listItemText}>
                <Text style={styles.bold}>User Reports:</Text> Incident reports you 
                voluntarily submit
              </Text>
            </View>
            <View style={styles.listItem}>
              <Icon name="check-circle" size={20} color={Colors.success} />
              <Text style={styles.listItemText}>
                <Text style={styles.bold}>Usage Analytics:</Text> Anonymous app usage 
                data to improve our service
              </Text>
            </View>
          </View>

          <Text style={styles.subTitle}>What We DON'T Collect</Text>
          <View style={styles.list}>
            <View style={styles.listItem}>
              <Icon name="close-circle" size={20} color={Colors.danger} />
              <Text style={styles.listItemText}>
                Your browsing history outside Forseti
              </Text>
            </View>
            <View style={styles.listItem}>
              <Icon name="close-circle" size={20} color={Colors.danger} />
              <Text style={styles.listItemText}>
                Your contacts or messages
              </Text>
            </View>
            <View style={styles.listItem}>
              <Icon name="close-circle" size={20} color={Colors.danger} />
              <Text style={styles.listItemText}>
                Your photos (unless you choose to attach them to a report)
              </Text>
            </View>
            <View style={styles.listItem}>
              <Icon name="close-circle" size={20} color={Colors.danger} />
              <Text style={styles.listItemText}>
                Your personal conversations
              </Text>
            </View>
          </View>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Data Usage</Text>
          <Text style={styles.paragraph}>We use your data exclusively to:</Text>
          <View style={styles.list}>
            {[
              'Provide safety alerts relevant to your location',
              'Improve our AI prediction models',
              'Generate anonymized crime statistics',
              'Communicate important safety information',
            ].map((item, index) => (
              <View key={index} style={styles.listItem}>
                <Icon name="arrow-right" size={20} color={Colors.primary} />
                <Text style={styles.listItemText}>{item}</Text>
              </View>
            ))}
          </View>

          <View style={styles.warningBox}>
            <Text style={styles.warningTitle}>We Never:</Text>
            {[
              'Sell your personal information',
              'Share your data with advertisers',
              'Track you across other websites',
              'Use your data for purposes you didn\'t consent to',
            ].map((item, index) => (
              <View key={index} style={styles.listItem}>
                <Icon name="close" size={16} color={Colors.danger} />
                <Text style={styles.warningText}>{item}</Text>
              </View>
            ))}
          </View>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Security Measures</Text>
          
          <View style={styles.securityCard}>
            <Icon name="lock" size={32} color={Colors.primary} />
            <Text style={styles.securityTitle}>Encryption</Text>
            <Text style={styles.securityText}>
              All data is encrypted in transit (TLS 1.3) and at rest (AES-256).
            </Text>
          </View>

          <View style={styles.securityCard}>
            <Icon name="fingerprint" size={32} color={Colors.primary} />
            <Text style={styles.securityTitle}>Authentication</Text>
            <Text style={styles.securityText}>
              Multi-factor authentication and secure password policies.
            </Text>
          </View>

          <View style={styles.securityCard}>
            <Icon name="shield-check" size={32} color={Colors.primary} />
            <Text style={styles.securityTitle}>Access Controls</Text>
            <Text style={styles.securityText}>
              Strict role-based access with audit logging.
            </Text>
          </View>

          <View style={styles.securityCard}>
            <Icon name="security" size={32} color={Colors.primary} />
            <Text style={styles.securityTitle}>Regular Audits</Text>
            <Text style={styles.securityText}>
              Third-party security audits and penetration testing.
            </Text>
          </View>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Your Rights</Text>
          <Text style={styles.paragraph}>
            Under GDPR and other privacy laws, you have the right to:
          </Text>
          <View style={styles.list}>
            {[
              { title: 'Access', desc: 'Request a copy of all data we have about you' },
              { title: 'Correction', desc: 'Update or correct inaccurate information' },
              { title: 'Deletion', desc: 'Request deletion of your personal data' },
              { title: 'Portability', desc: 'Export your data in a standard format' },
              { title: 'Opt-Out', desc: 'Disable location tracking or notifications anytime' },
            ].map((item, index) => (
              <View key={index} style={styles.rightItem}>
                <Text style={styles.rightTitle}>{item.title}:</Text>
                <Text style={styles.rightDesc}>{item.desc}</Text>
              </View>
            ))}
          </View>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Anonymous Reporting</Text>
          <Text style={styles.paragraph}>
            We offer completely anonymous incident reporting. When you choose this option:
          </Text>
          <View style={styles.list}>
            {[
              'No account required',
              'No location tracking',
              'No identifying information stored',
              'Reports still help improve community safety',
            ].map((item, index) => (
              <View key={index} style={styles.listItem}>
                <Icon name="check" size={20} color={Colors.success} />
                <Text style={styles.listItemText}>{item}</Text>
              </View>
            ))}
          </View>
        </View>

        <View style={styles.contactBox}>
          <Icon name="help-circle" size={32} color={Colors.primary} />
          <Text style={styles.contactTitle}>Questions or Concerns?</Text>
          <Text style={styles.contactText}>
            If you have any questions about our privacy practices or want to exercise 
            your rights, please use the Chat feature to talk with Forseti. We typically 
            respond within 48 hours.
          </Text>
        </View>

        <Text style={styles.lastUpdated}>Last Updated: December 9, 2025</Text>
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
    alignItems: 'center',
    paddingVertical: 32,
    paddingHorizontal: 20,
    backgroundColor: Colors.white,
    borderBottomWidth: 1,
    borderBottomColor: Colors.lightGray,
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    color: Colors.primary,
    marginTop: 16,
  },
  content: {
    padding: 20,
  },
  commitment: {
    backgroundColor: Colors.primaryLight,
    borderRadius: 12,
    padding: 20,
    marginBottom: 24,
  },
  commitmentTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: Colors.primary,
    marginBottom: 12,
  },
  commitmentText: {
    fontSize: 15,
    lineHeight: 22,
    color: Colors.text,
  },
  section: {
    marginBottom: 32,
  },
  sectionTitle: {
    fontSize: 22,
    fontWeight: 'bold',
    color: Colors.primary,
    marginBottom: 16,
  },
  subTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: Colors.dark,
    marginTop: 16,
    marginBottom: 12,
  },
  paragraph: {
    fontSize: 15,
    lineHeight: 22,
    color: Colors.text,
    marginBottom: 12,
  },
  bold: {
    fontWeight: 'bold',
  },
  list: {
    marginTop: 8,
  },
  listItem: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 12,
    paddingLeft: 8,
  },
  listItemText: {
    fontSize: 15,
    lineHeight: 22,
    color: Colors.text,
    flex: 1,
    marginLeft: 12,
  },
  warningBox: {
    backgroundColor: '#FFF3CD',
    borderLeftWidth: 4,
    borderLeftColor: '#FFA000',
    borderRadius: 8,
    padding: 16,
    marginTop: 16,
  },
  warningTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: Colors.dark,
    marginBottom: 12,
  },
  warningText: {
    fontSize: 14,
    color: Colors.text,
    flex: 1,
    marginLeft: 12,
  },
  securityCard: {
    backgroundColor: Colors.white,
    borderRadius: 12,
    padding: 20,
    marginBottom: 12,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  securityTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: Colors.dark,
    marginTop: 12,
    marginBottom: 8,
  },
  securityText: {
    fontSize: 14,
    lineHeight: 20,
    color: Colors.text,
    textAlign: 'center',
  },
  rightItem: {
    marginBottom: 16,
    paddingLeft: 8,
  },
  rightTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: Colors.dark,
    marginBottom: 4,
  },
  rightDesc: {
    fontSize: 14,
    lineHeight: 20,
    color: Colors.text,
  },
  contactBox: {
    backgroundColor: Colors.white,
    borderRadius: 12,
    padding: 20,
    alignItems: 'center',
    marginTop: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  contactTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: Colors.primary,
    marginTop: 12,
    marginBottom: 8,
  },
  contactText: {
    fontSize: 15,
    lineHeight: 22,
    color: Colors.text,
    textAlign: 'center',
  },
  lastUpdated: {
    fontSize: 12,
    color: Colors.gray,
    textAlign: 'center',
    marginTop: 24,
    marginBottom: 16,
  },
});

export default PrivacyScreen;
