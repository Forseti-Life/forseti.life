/**
 * Conversation List Screen
 * 
 * Display list of user's past conversations with Forseti
 * Allow users to resume or delete conversations
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Alert,
  RefreshControl,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import AIConversationService from '../../services/AIConversationService';
import DrupalAuthService from '../../services/DrupalAuthService';
import { Colors } from '../../utils/colors';

const ConversationListScreen = ({ navigation }) => {
  const [conversations, setConversations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    checkAuthenticationAndLoad();
  }, []);

  /**
   * Check authentication and load conversations
   */
  const checkAuthenticationAndLoad = async () => {
    try {
      const user = await DrupalAuthService.getCurrentUser();
      if (user) {
        setIsAuthenticated(true);
        await loadConversations();
      } else {
        setIsAuthenticated(false);
        Alert.alert(
          'Authentication Required',
          'Please sign in to view your conversations',
          [
            { text: 'Cancel', onPress: () => navigation.goBack() },
            { text: 'Sign In', onPress: () => navigation.navigate('Profile') }
          ]
        );
      }
    } catch (error) {
      console.error('Error checking authentication:', error);
      setIsAuthenticated(false);
    }
  };

  /**
   * Load user's conversations
   */
  const loadConversations = async () => {
    try {
      setLoading(true);
      const result = await AIConversationService.getUserConversations();
      setConversations(result);
    } catch (error) {
      console.error('Error loading conversations:', error);
      Alert.alert('Error', 'Failed to load conversations. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  /**
   * Refresh conversations
   */
  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadConversations();
    setRefreshing(false);
  }, []);

  /**
   * Start a new conversation
   */
  const startNewConversation = () => {
    navigation.navigate('Chat', { conversationId: null });
  };

  /**
   * Open a conversation
   */
  const openConversation = (conversationId) => {
    navigation.navigate('Chat', { conversationId });
  };

  /**
   * Delete a conversation
   */
  const deleteConversation = async (conversationId, title) => {
    Alert.alert(
      'Delete Conversation',
      `Are you sure you want to delete "${title}"? This action cannot be undone.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              await AIConversationService.deleteConversation(conversationId);
              setConversations(prev => 
                prev.filter(conv => conv.conversation_id !== conversationId)
              );
            } catch (error) {
              console.error('Error deleting conversation:', error);
              Alert.alert('Error', 'Failed to delete conversation. Please try again.');
            }
          }
        }
      ]
    );
  };

  /**
   * Format date for display
   */
  const formatDate = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
      return 'Today';
    } else if (diffDays === 1) {
      return 'Yesterday';
    } else if (diffDays < 7) {
      return `${diffDays} days ago`;
    } else {
      return date.toLocaleDateString();
    }
  };

  /**
   * Render a conversation item
   */
  const renderConversation = ({ item }) => {
    const messageCount = item.message_count || 0;
    const lastMessage = item.last_message || 'No messages yet';
    const truncatedMessage = lastMessage.length > 80 
      ? lastMessage.substring(0, 80) + '...'
      : lastMessage;

    return (
      <TouchableOpacity
        style={styles.conversationCard}
        onPress={() => openConversation(item.conversation_id)}
      >
        <View style={styles.conversationHeader}>
          <Icon name="chat" size={24} color={Colors.cyan} />
          <View style={styles.conversationInfo}>
            <Text style={styles.conversationTitle}>
              {item.title || 'Conversation'}
            </Text>
            <Text style={styles.conversationDate}>
              {formatDate(item.created_at)}
            </Text>
          </View>
          <TouchableOpacity
            style={styles.deleteButton}
            onPress={() => deleteConversation(item.conversation_id, item.title)}
          >
            <Icon name="delete-outline" size={20} color={Colors.danger} />
          </TouchableOpacity>
        </View>
        
        <Text style={styles.lastMessage}>{truncatedMessage}</Text>
        
        <View style={styles.conversationFooter}>
          <View style={styles.statItem}>
            <Icon name="message-text" size={14} color={Colors.textSecondary} />
            <Text style={styles.statText}>{messageCount} messages</Text>
          </View>
        </View>
      </TouchableOpacity>
    );
  };

  if (!isAuthenticated) {
    return (
      <View style={styles.container}>
        <ActivityIndicator size="large" color={Colors.cyan} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>My Conversations</Text>
        <TouchableOpacity
          style={styles.newButton}
          onPress={startNewConversation}
        >
          <Icon name="plus" size={24} color={Colors.background} />
        </TouchableOpacity>
      </View>

      {/* Conversations List */}
      {loading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={Colors.cyan} />
          <Text style={styles.loadingText}>Loading conversations...</Text>
        </View>
      ) : conversations.length === 0 ? (
        <View style={styles.emptyContainer}>
          <Icon name="chat-outline" size={80} color={Colors.textSecondary} />
          <Text style={styles.emptyTitle}>No Conversations Yet</Text>
          <Text style={styles.emptyText}>
            Start a conversation with Forseti to get personalized safety advice
          </Text>
          <TouchableOpacity
            style={styles.startButton}
            onPress={startNewConversation}
          >
            <Icon name="plus" size={20} color={Colors.background} />
            <Text style={styles.startButtonText}>Start Conversation</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={conversations}
          renderItem={renderConversation}
          keyExtractor={item => item.conversation_id.toString()}
          contentContainerStyle={styles.listContainer}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              tintColor={Colors.cyan}
            />
          }
        />
      )}
    </View>
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
    padding: 16,
    backgroundColor: Colors.card,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '600',
    color: Colors.text,
  },
  newButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: Colors.cyan,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadingContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: Colors.textSecondary,
  },
  emptyContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 40,
  },
  emptyTitle: {
    fontSize: 20,
    fontWeight: '600',
    color: Colors.text,
    marginTop: 20,
    marginBottom: 8,
  },
  emptyText: {
    fontSize: 15,
    color: Colors.textSecondary,
    textAlign: 'center',
    marginBottom: 24,
  },
  startButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.cyan,
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
  },
  startButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: Colors.background,
    marginLeft: 8,
  },
  listContainer: {
    padding: 16,
  },
  conversationCard: {
    backgroundColor: Colors.card,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: Colors.border,
  },
  conversationHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  conversationInfo: {
    flex: 1,
    marginLeft: 12,
  },
  conversationTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: Colors.text,
    marginBottom: 4,
  },
  conversationDate: {
    fontSize: 12,
    color: Colors.textSecondary,
  },
  deleteButton: {
    padding: 8,
  },
  lastMessage: {
    fontSize: 14,
    color: Colors.textSecondary,
    lineHeight: 20,
    marginBottom: 12,
  },
  conversationFooter: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  statItem: {
    flexDirection: 'row',
    alignItems: 'center',
    marginRight: 16,
  },
  statText: {
    fontSize: 12,
    color: Colors.textSecondary,
    marginLeft: 4,
  },
});

export default ConversationListScreen;
