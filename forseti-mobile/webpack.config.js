const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');

module.exports = {
  mode: 'development',
  entry: './index.web.js',
  output: {
    path: path.resolve(__dirname, 'dist'),
    filename: 'bundle.js',
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx|ts|tsx)$/,
        exclude: /node_modules\/(?!react-native-maps)/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              '@babel/preset-env',
              '@babel/preset-react',
              '@babel/preset-typescript',
            ],
          },
        },
      },
      {
        test: /\.(png|jpg|gif|svg)$/,
        type: 'asset/resource',
      },
    ],
  },
  resolve: {
    extensions: ['.web.js', '.web.ts', '.web.tsx', '.js', '.jsx', '.ts', '.tsx'],
    alias: {
      'react-native$': 'react-native-web',
      'react-native-vector-icons/MaterialCommunityIcons': path.resolve(__dirname, 'src/components/Icon.web.tsx'),
      'react-native-vector-icons/MaterialIcons': path.resolve(__dirname, 'src/components/Icon.web.tsx'),
      'react-native-vector-icons/FontAwesome': path.resolve(__dirname, 'src/components/Icon.web.tsx'),
      'react-native-vector-icons/Ionicons': path.resolve(__dirname, 'src/components/Icon.web.tsx'),
      'react-native-maps': 'react-native-web-maps',
    },
  },
  plugins: [
    new HtmlWebpackPlugin({
      template: './public/index.html',
    }),
  ],
  devServer: {
    static: {
      directory: path.join(__dirname, 'public'),
    },
    port: 3000,
    hot: true,
    open: true,
  },
};
