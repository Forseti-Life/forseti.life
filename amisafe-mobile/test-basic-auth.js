#!/usr/bin/env node

/**
 * Test script for basic Drupal authentication
 */

const DrupalAuthService = require('./src/services/DrupalAuthService.js').default;

async function testBasicAuth() {
  console.log('🧪 Testing Basic Drupal Authentication...\n');
  
  try {
    console.log('1️⃣ Testing user registration...');
    
    // Test user registration
    const registrationData = {
      username: `testuser${Date.now()}`,
      email: `test${Date.now()}@example.com`,
      password: 'TestPassword123!'
    };
    
    const registerResult = await DrupalAuthService.register(registrationData);
    console.log('✅ Registration result:', registerResult);
    
    console.log('\n2️⃣ Testing user login...');
    
    // Test user login
    const loginResult = await DrupalAuthService.login(registrationData.username, registrationData.password);
    console.log('✅ Login result:', loginResult);
    
    console.log('\n3️⃣ Testing authentication state...');
    console.log('Is authenticated:', DrupalAuthService.isAuthenticated());
    console.log('Current user:', DrupalAuthService.getCurrentUser());
    
    console.log('\n4️⃣ Testing authenticated request...');
    
    // Test authenticated API request
    try {
      const response = await DrupalAuthService.authenticatedRequest({
        method: 'GET',
        url: `${DrupalAuthService.baseUrl}/api/amisafe/aggregated?_format=json`
      });
      console.log('✅ Authenticated request successful:', response.status);
    } catch (error) {
      console.log('ℹ️ Authenticated request failed (expected):', error.message);
    }
    
    console.log('\n5️⃣ Testing logout...');
    await DrupalAuthService.logout();
    console.log('✅ Logout successful');
    console.log('Is authenticated after logout:', DrupalAuthService.isAuthenticated());
    
  } catch (error) {
    console.error('❌ Test failed:', error.message);
    console.error('Stack:', error.stack);
  }
  
  console.log('\n🎯 Basic Authentication Test Complete');
}

// Mock AsyncStorage for Node.js testing
global.AsyncStorage = {
  multiGet: async (keys) => keys.map(key => [key, null]),
  multiSet: async (pairs) => {},
  multiRemove: async (keys) => {}
};

testBasicAuth();