import React from 'react';
import { AppRegistry } from 'react-native';
import { name as appName } from './app.json';

console.log('🚀 Forseti Mobile Web - Starting...');

// Wrap app import in try-catch to see errors
let App;
try {
  App = require('./App.minimal').default;
  console.log('✅ App.minimal loaded successfully');
} catch (error) {
  console.error('❌ Error loading App.web:', error);
  // Create error display component
  App = () => {
    const React = require('react');
    const { View, Text, ScrollView, StyleSheet } = require('react-native');
    return React.createElement(
      View,
      { style: { flex: 1, padding: 20, backgroundColor: '#1a1a2e' } },
      React.createElement(
        ScrollView,
        null,
        React.createElement(Text, { style: { color: '#ff6b6b', fontSize: 20, fontWeight: 'bold', marginBottom: 10 } }, '❌ Error Loading App'),
        React.createElement(Text, { style: { color: '#ffffff', fontSize: 14, fontFamily: 'monospace' } }, error.toString()),
        React.createElement(Text, { style: { color: '#aaa', fontSize: 12, marginTop: 10, fontFamily: 'monospace' } }, error.stack)
      )
    );
  };
}

// Register and run
try {
  AppRegistry.registerComponent(appName, () => App);
  console.log('✅ Component registered');
  
  const rootTag = document.getElementById('root');
  if (rootTag) {
    console.log('✅ Root element found, mounting app');
    AppRegistry.runApplication(appName, { rootTag });
    console.log('✅ App mounted successfully');
  } else {
    console.error('❌ Root element not found');
  }
} catch (error) {
  console.error('❌ Error mounting app:', error);
}
