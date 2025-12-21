// Minimal test to verify React Native Web is working
import React from 'react';
import { AppRegistry, View, Text, StyleSheet } from 'react-native';

const TestApp = () => (
  <View style={styles.container}>
    <Text style={styles.text}>🎉 Forseti Mobile Web Preview</Text>
    <Text style={styles.subtitle}>React Native Web is working!</Text>
  </View>
);

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#1a1a2e',
  },
  text: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#ffffff',
    marginBottom: 10,
  },
  subtitle: {
    fontSize: 16,
    color: '#16c79a',
  },
});

AppRegistry.registerComponent('ForsetiMobileApp', () => TestApp);
AppRegistry.runApplication('ForsetiMobileApp', {
  rootTag: document.getElementById('root'),
});
