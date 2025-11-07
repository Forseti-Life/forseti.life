/**
 * Color palette for AmISafe Mobile Application
 * Consistent with web application branding
 */

export const Colors = {
  // Primary colors
  primary: '#007bff',
  primaryDark: '#0056b3',
  primaryLight: '#66b3ff',
  
  // Secondary colors
  secondary: '#6c757d',
  secondaryDark: '#495057',
  secondaryLight: '#adb5bd',
  
  // Status colors
  success: '#28a745',
  warning: '#ffc107',
  danger: '#dc3545',
  info: '#17a2b8',
  
  // Safety risk colors (matching web app)
  riskCritical: '#dc3545',
  riskHigh: '#fd7e14',
  riskMedium: '#ffc107',
  riskLow: '#28a745',
  riskMinimal: '#6c757d',
  
  // Neutral colors
  white: '#ffffff',
  black: '#000000',
  gray: '#6c757d',
  lightGray: '#e9ecef',
  darkGray: '#343a40',
  
  // Background colors
  background: '#f8f9fa',
  backgroundDark: '#212529',
  lighter: '#ffffff',
  darker: '#000000',
  
  // Text colors
  textPrimary: '#212529',
  textSecondary: '#6c757d',
  textMuted: '#adb5bd',
  textLight: '#ffffff',
  
  // Map colors
  mapBackground: '#f8f9fa',
  hexagonFill: 'rgba(0, 123, 255, 0.3)',
  hexagonStroke: '#007bff',
  
  // Chart colors
  chartPrimary: '#007bff',
  chartSecondary: '#6c757d',
  chartAccent: '#28a745',
  
  // Shadow colors
  shadowLight: 'rgba(0, 0, 0, 0.1)',
  shadowMedium: 'rgba(0, 0, 0, 0.15)',
  shadowDark: 'rgba(0, 0, 0, 0.3)',
  
  // Overlay colors
  overlayLight: 'rgba(255, 255, 255, 0.9)',
  overlayDark: 'rgba(0, 0, 0, 0.7)',
  
  // Crime type colors (consistent with web interface)
  crimeTypeColors: {
    burglary: '#dc3545',
    theft: '#fd7e14',
    robbery: '#e83e8c',
    violence: '#dc3545',
    drugs: '#6f42c1',
    vandalism: '#fd7e14',
    assault: '#dc3545',
    weapons: '#343a40',
    fraud: '#20c997',
    other: '#6c757d',
  },
};

export default Colors;