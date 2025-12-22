// Webpack alias configuration to replace react-native-vector-icons with web version
module.exports = {
  'react-native-vector-icons/MaterialCommunityIcons':
    require.resolve('./src/components/Icon.web.tsx'),
  'react-native-vector-icons/MaterialIcons': require.resolve('./src/components/Icon.web.tsx'),
  'react-native-vector-icons/FontAwesome': require.resolve('./src/components/Icon.web.tsx'),
  'react-native-vector-icons/Ionicons': require.resolve('./src/components/Icon.web.tsx'),
};
