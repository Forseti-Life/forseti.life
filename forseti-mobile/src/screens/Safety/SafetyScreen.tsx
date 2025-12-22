import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Colors } from '../../utils/colors';

const SafetyScreen: React.FC = () => (
  <View style={styles.container}>
    <Text style={styles.placeholderText}>Safety Screen</Text>
    <Text style={styles.subText}>Safety alerts and recommendations</Text>
  </View>
);

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    backgroundColor: Colors.background,
    flex: 1,
    justifyContent: 'center',
    padding: 20,
  },
  placeholderText: {
    color: Colors.textPrimary,
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 10,
    textAlign: 'center',
  },
  subText: { color: Colors.textSecondary, fontSize: 16, textAlign: 'center' },
});

export default SafetyScreen;
