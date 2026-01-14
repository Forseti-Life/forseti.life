/**
 * Debug Console - Display log messages in the app for debugging
 */

import React, { useState, useEffect } from 'react';
import { View, Text, ScrollView, StyleSheet, TouchableOpacity, ErrorUtils } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { Colors } from '../utils/colors';
import AsyncStorage from '@react-native-async-storage/async-storage';

interface LogEntry {
  id: number;
  timestamp: string; // Changed to string for JSON serialization
  level: 'info' | 'warn' | 'error';
  message: string;
}

let logId = 0;
const logs: LogEntry[] = [];
let listeners: ((logs: LogEntry[]) => void)[] = [];

// Load logs from storage on startup
AsyncStorage.getItem('debug_logs').then(stored => {
  if (stored) {
    try {
      const parsed = JSON.parse(stored);
      logs.push(...parsed);
      logId = logs.length > 0 ? Math.max(...logs.map(l => l.id)) + 1 : 0;
      listeners.forEach(listener => listener([...logs]));
    } catch (e) {
      console.error('Failed to load debug logs:', e);
    }
  }
}).catch(() => {});

// Persist logs immediately to storage
const persistLogs = () => {
  AsyncStorage.setItem('debug_logs', JSON.stringify(logs.slice(-100))).catch(e => 
    console.error('Failed to persist logs:', e)
  );
};

export const DebugLogger = {
  info: (message: string) => {
    const entry: LogEntry = { id: logId++, timestamp: new Date().toISOString(), level: 'info', message };
    logs.push(entry);
    if (logs.length > 100) logs.shift();
    listeners.forEach(listener => listener([...logs]));
    console.log('[DEBUG]', message);
    persistLogs(); // Persist immediately
  },
  warn: (message: string) => {
    const entry: LogEntry = { id: logId++, timestamp: new Date().toISOString(), level: 'warn', message };
    logs.push(entry);
    if (logs.length > 100) logs.shift();
    listeners.forEach(listener => listener([...logs]));
    console.warn('[DEBUG]', message);
    persistLogs(); // Persist immediately
  },
  error: (message: string, error?: any) => {
    let fullMessage = message;
    if (error) {
      fullMessage += ` ${typeof error === 'object' ? JSON.stringify(error) : String(error)}`;
    }
    const entry: LogEntry = { id: logId++, timestamp: new Date().toISOString(), level: 'error', message: fullMessage };
    logs.push(entry);
    if (logs.length > 100) logs.shift();
    listeners.forEach(listener => listener([...logs]));
    console.error('[DEBUG]', fullMessage);
    persistLogs(); // Persist immediately
  },
  subscribe: (listener: (logs: LogEntry[]) => void) => {
    listeners.push(listener);
    listener([...logs]);
    return () => {
      listeners = listeners.filter(l => l !== listener);
    };
  },
  clear: () => {
    logs.length = 0;
    logId = 0;
    listeners.forEach(listener => listener([...logs]));
    AsyncStorage.removeItem('debug_logs').catch(() => {});
  },
};

// Global error handler
const originalHandler = ErrorUtils.getGlobalHandler();
ErrorUtils.setGlobalHandler((error, isFatal) => {
  DebugLogger.error('❌ GLOBAL ERROR (FATAL=' + isFatal + '):', error);
  DebugLogger.error('Error name: ' + (error?.name || 'Unknown'));
  DebugLogger.error('Error message: ' + (error?.message || String(error)));
  DebugLogger.error('Error stack: ' + (error?.stack || 'No stack'));
  
  // Call original handler
  if (originalHandler) {
    originalHandler(error, isFatal);
  }
});

const DebugConsole: React.FC = () => {
  const [isVisible, setIsVisible] = useState(true); // Start visible by default
  const [entries, setEntries] = useState<LogEntry[]>([]);

  useEffect(() => {
    const unsubscribe = DebugLogger.subscribe(setEntries);
    return unsubscribe;
  }, []);

  const getColor = (level: string) => {
    switch (level) {
      case 'info': return Colors.primary;
      case 'warn': return '#FFA500';
      case 'error': return Colors.danger;
      default: return Colors.text;
    }
  };

  if (!isVisible) {
    return (
      <TouchableOpacity
        style={styles.toggleButton}
        onPress={() => setIsVisible(true)}
      >
        <Icon name="bug" size={24} color={Colors.primary} />
      </TouchableOpacity>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Debug Console ({entries.length})</Text>
        <View style={styles.headerButtons}>
          <TouchableOpacity onPress={() => DebugLogger.clear()} style={{ marginRight: 12 }}>
            <Icon name="delete" size={20} color={Colors.text} />
          </TouchableOpacity>
          <TouchableOpacity onPress={() => setIsVisible(false)}>
            <Icon name="close" size={24} color={Colors.text} />
          </TouchableOpacity>
        </View>
      </View>
      <ScrollView style={styles.logContainer}>
        {entries.map((entry) => (
          <View key={entry.id} style={styles.logEntry}>
            <Text style={[styles.timestamp, { color: getColor(entry.level) }]}>
              {new Date(entry.timestamp).toLocaleTimeString()}
            </Text>
            <Text style={[styles.level, { color: getColor(entry.level) }]}>
              [{entry.level.toUpperCase()}]
            </Text>
            <Text style={styles.message}>{entry.message}</Text>
          </View>
        ))}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: 'rgba(26, 26, 46, 0.95)',
    borderRadius: 8,
    bottom: 20,
    maxHeight: 300,
    position: 'absolute',
    right: 20,
    width: 350,
    zIndex: 1000,
  },
  header: {
    alignItems: 'center',
    borderBottomColor: Colors.border,
    borderBottomWidth: 1,
    flexDirection: 'row',
    justifyContent: 'space-between',
    padding: 12,
  },
  headerButtons: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  level: {
    fontSize: 10,
    fontWeight: 'bold',
    marginRight: 8,
  },
  logContainer: {
    maxHeight: 250,
    padding: 8,
  },
  logEntry: {
    borderBottomColor: Colors.border,
    borderBottomWidth: 1,
    flexDirection: 'row',
    flexWrap: 'wrap',
    paddingVertical: 6,
  },
  message: {
    color: Colors.text,
    flex: 1,
    fontSize: 11,
  },
  timestamp: {
    fontSize: 10,
    marginRight: 8,
  },
  title: {
    color: Colors.primary,
    fontSize: 14,
    fontWeight: 'bold',
  },
  toggleButton: {
    backgroundColor: 'rgba(26, 26, 46, 0.8)',
    borderRadius: 30,
    bottom: 20,
    elevation: 5,
    padding: 12,
    position: 'absolute',
    right: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 3.84,
    zIndex: 1000,
  },
});

export default DebugConsole;
