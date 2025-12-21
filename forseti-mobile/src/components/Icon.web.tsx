/**
 * Web-compatible Icon component
 * Replaces react-native-vector-icons with emoji-based icons for web preview
 */

import React from 'react';
import { Text, TextStyle } from 'react-native';

interface IconProps {
  name: string;
  size?: number;
  color?: string;
  style?: TextStyle;
}

// Map common icon names to emojis
const iconMap: { [key: string]: string } = {
  // Navigation & UI
  'home': '🏠',
  'menu': '☰',
  'chevron-right': '›',
  'chevron-left': '‹',
  'chevron-down': '⌄',
  'chevron-up': '⌃',
  'arrow-right': '→',
  'arrow-left': '←',
  'close': '✕',
  'check': '✓',
  'plus': '+',
  'minus': '−',
  
  // Map & Location
  'map': '🗺️',
  'map-marker': '📍',
  'map-marker-outline': '📌',
  'navigation': '🧭',
  'crosshairs-gps': '🎯',
  
  // Communication
  'phone': '📞',
  'email': '✉️',
  'chat': '💬',
  'forum': '💭',
  'comment': '💬',
  'message': '💬',
  
  // Social
  'account': '👤',
  'account-group': '👥',
  'account-multiple': '👥',
  'shield-account': '🛡️',
  
  // Safety & Security
  'shield': '🛡️',
  'shield-check': '✅',
  'shield-alert': '⚠️',
  'alert': '⚠️',
  'alert-circle': '⚠️',
  'information': 'ℹ️',
  'help-circle': '❓',
  
  // Status
  'loading': '⏳',
  'refresh': '🔄',
  'sync': '🔄',
  'check-circle': '✅',
  'close-circle': '❌',
  
  // Actions
  'cog': '⚙️',
  'settings': '⚙️',
  'tune': '🎛️',
  'filter': '🔍',
  'magnify': '🔍',
  'search': '🔍',
  
  // Safety Features
  'lightbulb': '💡',
  'lightbulb-on': '💡',
  'flash': '⚡',
  'bell': '🔔',
  'bell-alert': '🔔',
  
  // Charts & Data
  'chart-line': '📈',
  'chart-bar': '📊',
  'chart-pie': '📊',
  'trending-up': '📈',
  'trending-down': '📉',
  
  // Content
  'file-document': '📄',
  'book-open': '📖',
  'newspaper': '📰',
  'note': '📝',
  
  // Media
  'camera': '📷',
  'image': '🖼️',
  'video': '🎥',
  
  // Weather
  'weather-sunny': '☀️',
  'weather-cloudy': '☁️',
  'weather-rainy': '🌧️',
  'weather-night': '🌙',
  
  // Default fallback
  'circle': '●',
  'square': '■',
  'triangle': '▲',
};

const Icon: React.FC<IconProps> = ({ name, size = 24, color = '#000000', style }) => {
  const emoji = iconMap[name] || iconMap[name.replace('mdi-', '')] || '•';
  
  return (
    <Text
      style={[
        {
          fontSize: size,
          color: color,
          lineHeight: size * 1.2,
          textAlign: 'center',
        },
        style,
      ]}
    >
      {emoji}
    </Text>
  );
};

export default Icon;
