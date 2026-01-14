/**
 * @format
 */

import 'react-native-gesture-handler';
import { AppRegistry } from 'react-native';
import App from './App';
import './src/services/location/BackgroundLocationService'; // Register headless task

AppRegistry.registerComponent('forseti-mobile', () => App);
