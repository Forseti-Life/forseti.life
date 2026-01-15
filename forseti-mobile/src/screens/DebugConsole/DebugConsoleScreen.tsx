/**
 * Debug Console Screen
 * 
 * Full-screen view of all debug logs with scrolling
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  SafeAreaView,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { DebugLogger } from '../../components/DebugConsole';
import { Theme } from '../../utils/theme';

const { Colors, Spacing, Typography } = Theme;

interface LogEntry {
  id: number;
  timestamp: string;
  level: 'info' | 'warn' | 'error';
  message: string;
}

const DebugConsoleScreen = ({ navigation }: any) => {
  const [entries, setEntries] = useState<LogEntry[]>([]);
  const [autoScroll, setAutoScroll] = useState(true);
  const scrollViewRef = React.useRef<ScrollView>(null);

  useEffect(() => {
    const unsubscribe = DebugLogger.subscribe(setEntries);
    return unsubscribe;
  }, []);

  useEffect(() => {
    if (autoScroll && scrollViewRef.current) {
      scrollViewRef.current.scrollToEnd({ animated: true });
    }
  }, [entries, autoScroll]);

  const getColor = (level: string) => {
    switch (level) {
      case 'info': return Colors.primary;
      case 'warn': return '#FFA500';
      case 'error': return Colors.danger;
      default: return Colors.text;
    }
  };

  const getIcon = (level: string) => {
    switch (level) {
      case 'info': return 'information';
      case 'warn': return 'alert';
      case 'error': return 'alert-circle';
      default: return 'message';
    }
  };

  const clearLogs = () => {
    DebugLogger.clear();
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Icon name="arrow-left" size={24} color={Colors.primary} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Debug Console</Text>
        <View style={styles.headerActions}>
          <TouchableOpacity 
            onPress={() => setAutoScroll(!autoScroll)} 
            style={styles.headerButton}
          >
            <Icon 
              name={autoScroll ? "pause" : "play"} 
              size={20} 
              color={autoScroll ? Colors.success : Colors.textSecondary} 
            />
          </TouchableOpacity>
          <TouchableOpacity onPress={clearLogs} style={styles.headerButton}>
            <Icon name="delete" size={20} color={Colors.danger} />
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.statsBar}>
        <Text style={styles.statsText}>
          Total: {entries.length} | 
          Info: {entries.filter(e => e.level === 'info').length} | 
          Warn: {entries.filter(e => e.level === 'warn').length} | 
          Error: {entries.filter(e => e.level === 'error').length}
        </Text>
      </View>

      {entries.length === 0 ? (
        <View style={styles.emptyState}>
          <Icon name="bug-outline" size={64} color={Colors.lightGray} />
          <Text style={styles.emptyText}>No logs yet</Text>
          <Text style={styles.emptySubtext}>Debug logs will appear here</Text>
        </View>
      ) : (
        <ScrollView 
          ref={scrollViewRef}
          style={styles.logContainer}
          contentContainerStyle={styles.logContent}
        >
          {entries.map((entry) => (
            <View key={entry.id} style={[styles.logEntry, { borderLeftColor: getColor(entry.level) }]}>
              <View style={styles.logHeader}>
                <Icon name={getIcon(entry.level)} size={16} color={getColor(entry.level)} />
                <Text style={[styles.timestamp, { color: getColor(entry.level) }]}>
                  {new Date(entry.timestamp).toLocaleTimeString()}
                </Text>
                <Text style={[styles.level, { color: getColor(entry.level) }]}>
                  [{entry.level.toUpperCase()}]
                </Text>
              </View>
              <Text style={styles.message}>{entry.message}</Text>
            </View>
          ))}
        </ScrollView>
      )}
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: Spacing.md,
    backgroundColor: Colors.white,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  backButton: {
    padding: Spacing.xs,
  },
  headerTitle: {
    ...Typography.heading2,
    flex: 1,
    marginLeft: Spacing.md,
  },
  headerActions: {
    flexDirection: 'row',
    gap: Spacing.md,
  },
  headerButton: {
    padding: Spacing.xs,
  },
  statsBar: {
    backgroundColor: Colors.lightGray,
    padding: Spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  statsText: {
    ...Typography.caption,
    color: Colors.textSecondary,
    textAlign: 'center',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: Spacing.xl,
  },
  emptyText: {
    ...Typography.heading3,
    color: Colors.textSecondary,
    marginTop: Spacing.md,
  },
  emptySubtext: {
    ...Typography.body,
    color: Colors.textSecondary,
    marginTop: Spacing.xs,
  },
  logContainer: {
    flex: 1,
  },
  logContent: {
    padding: Spacing.sm,
  },
  logEntry: {
    backgroundColor: Colors.white,
    borderRadius: Spacing.borderRadius.md,
    borderLeftWidth: 4,
    padding: Spacing.md,
    marginBottom: Spacing.sm,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  logHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: Spacing.xs,
    gap: Spacing.xs,
  },
  timestamp: {
    ...Typography.caption,
    fontWeight: Typography.fontWeight.semibold,
  },
  level: {
    ...Typography.caption,
    fontWeight: Typography.fontWeight.bold,
  },
  message: {
    ...Typography.body,
    color: Colors.text,
    lineHeight: 20,
    fontFamily: 'monospace',
  },
});

export default DebugConsoleScreen;
