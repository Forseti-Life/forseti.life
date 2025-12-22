import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { API_BASE_URL, ENV, ENABLE_DEBUG_MODE } from '@env';

/**
 * Example component demonstrating environment variable usage
 */
export const EnvExample: React.FC = () => {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Environment Configuration</Text>
      <Text style={styles.item}>Environment: {ENV}</Text>
      <Text style={styles.item}>API URL: {API_BASE_URL}</Text>
      <Text style={styles.item}>Debug Mode: {ENABLE_DEBUG_MODE}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    padding: 20,
  },
  title: {
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 10,
  },
  item: {
    fontSize: 14,
    marginBottom: 5,
  },
});

// Example usage in API service
export const apiService = {
  baseURL: API_BASE_URL,
  
  async fetchData(endpoint: string) {
    const url = `${API_BASE_URL}/${endpoint}`;
    
    if (ENABLE_DEBUG_MODE === 'true') {
      console.log('Fetching from:', url);
    }
    
    try {
      const response = await fetch(url);
      return await response.json();
    } catch (error) {
      if (ENABLE_DEBUG_MODE === 'true') {
        console.error('API Error:', error);
      }
      throw error;
    }
  },
};
