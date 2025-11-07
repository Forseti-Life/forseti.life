import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Colors } from '../../utils/colors';

const ProfileScreen: React.FC = () => (
  <View style={styles.container}>
    <Text style={styles.placeholderText}>Profile Screen</Text>
    <Text style={styles.subText}>User settings and preferences</Text>
  </View>
);

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: Colors.background, padding: 20 },
  placeholderText: { fontSize: 24, fontWeight: 'bold', color: Colors.textPrimary, marginBottom: 10, textAlign: 'center' },
  subText: { fontSize: 16, color: Colors.textSecondary, textAlign: 'center' },
});

export default ProfileScreen;