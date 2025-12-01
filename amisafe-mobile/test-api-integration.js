/**
 * Test Real API Integration
 * 
 * Test the Drupal authentication and crime data services
 */

const axios = require('axios');

const testApiIntegration = async () => {
  console.log('🧪 Testing Real API Integration...');
  
  const baseUrl = 'http://127.0.0.1'; // St. Louis Integration site with AmISafe module (port 80)
  
  // Test 1: Check if Drupal server is running
  try {
    console.log('1️⃣ Testing Drupal server connection...');
    const response = await axios.get(`${baseUrl}/`, { timeout: 5000 });
    console.log('✅ Drupal server is running:', response.status);
  } catch (error) {
    console.error('❌ Drupal server connection failed:', error.code);
    return;
  }
  
  // Test 2: Check OAuth endpoint
  try {
    console.log('2️⃣ Testing OAuth endpoint...');
    const response = await axios.post(`${baseUrl}/oauth/token`, 
      'grant_type=password&client_id=amisafe_mobile&username=admin&password=admin',
      {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        timeout: 5000
      }
    );
    console.log('✅ OAuth endpoint working, token received');
    
    const token = response.data.access_token;
    
    // Test 3: Check AmISafe API endpoints with authentication
    console.log('3️⃣ Testing AmISafe API endpoints...');
    
    const endpoints = [
      '/api/amisafe/citywide-stats',
      '/api/amisafe/aggregated?resolution=7&bounds=40,-75,39,-76&limit=10',
      '/api/amisafe/risk-level?lat=39.9526&lng=-75.1652'
    ];
    
    for (const endpoint of endpoints) {
      try {
        const apiResponse = await axios.get(`${baseUrl}${endpoint}`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          },
          timeout: 5000
        });
        console.log(`✅ ${endpoint} - Status: ${apiResponse.status}`);
      } catch (error) {
        console.log(`⚠️ ${endpoint} - Error: ${error.response?.status || error.code}`);
      }
    }
    
  } catch (error) {
    console.error('❌ OAuth authentication failed:', error.response?.data || error.message);
  }
  
  // Test 4: Check user registration endpoint
  try {
    console.log('4️⃣ Testing user registration endpoint...');
    const response = await axios.get(`${baseUrl}/user/register`, { timeout: 5000 });
    console.log('✅ User registration endpoint accessible:', response.status);
  } catch (error) {
    console.log('⚠️ User registration check:', error.response?.status || error.code);
  }
  
  console.log('🎯 API Integration Test Complete');
};

// Run the test
testApiIntegration().catch(console.error);