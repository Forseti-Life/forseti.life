/**
 * Forseti Mobile App Authentication Test
 * Tests the Drupal OAuth API endpoints
 */

const axios = require('axios');

// API Configuration
const API_BASE = 'https://stlouisintegration.com';
const CLIENT_ID = 'forseti_mobile';

class ForsetiAuthTest {
  constructor() {
    this.tokens = null;
  }

  async testUserRegistration() {
    console.log('🧪 Testing User Registration...');
    
    const testUser = {
      name: `testuser_${Date.now()}@example.com`,
      mail: `testuser_${Date.now()}@example.com`,
      pass: 'TestPassword123!',
      field_first_name: 'Test',
      field_last_name: 'User'
    };

    try {
      const response = await axios.post(`${API_BASE}/user/register`, testUser, {
        headers: {
          'Content-Type': 'application/json'
        }
      });

      console.log('✅ User Registration Success:', response.data);
      return testUser;
    } catch (error) {
      console.log('❌ User Registration Failed:', error.response?.data || error.message);
      return null;
    }
  }

  async testOAuthLogin(email, password) {
    console.log('🔐 Testing OAuth Authentication...');
    
    const authData = {
      grant_type: 'password',
      client_id: CLIENT_ID,
      username: email,
      password: password
    };

    try {
      const response = await axios.post(`${API_BASE}/oauth/token`, authData, {
        headers: {
          'Content-Type': 'application/json'
        }
      });

      console.log('✅ OAuth Login Success!');
      console.log('Access Token:', response.data.access_token.substring(0, 50) + '...');
      console.log('Expires In:', response.data.expires_in, 'seconds');
      
      this.tokens = response.data;
      return response.data;
    } catch (error) {
      console.log('❌ OAuth Login Failed:', error.response?.data || error.message);
      return null;
    }
  }

  async testUserProfile() {
    if (!this.tokens) {
      console.log('❌ No authentication tokens available');
      return;
    }

    console.log('👤 Testing User Profile Access...');

    try {
      const response = await axios.get(`${API_BASE}/jsonapi/user/user`, {
        headers: {
          'Authorization': `Bearer ${this.tokens.access_token}`,
          'Accept': 'application/vnd.api+json'
        }
      });

      console.log('✅ User Profile Access Success!');
      console.log('User Count:', response.data.data.length);
      if (response.data.data.length > 0) {
        const user = response.data.data[0];
        console.log('Sample User:', {
          id: user.id,
          email: user.attributes.mail,
          name: user.attributes.display_name
        });
      }
    } catch (error) {
      console.log('❌ User Profile Access Failed:', error.response?.data || error.message);
    }
  }

  async testAmISafeAPI() {
    if (!this.tokens) {
      console.log('❌ No authentication tokens available');
      return;
    }

    console.log('🗺️ Testing AmISafe Crime API...');

    try {
      const response = await axios.get(`${API_BASE}/api/amisafe/system-stats`, {
        headers: {
          'Authorization': `Bearer ${this.tokens.access_token}`,
          'Accept': 'application/json'
        }
      });

      console.log('✅ AmISafe API Access Success!');
      console.log('Crime Incidents:', response.data.data_statistics?.total_crime_incidents);
      console.log('H3 Aggregations:', response.data.data_statistics?.h3_aggregation_count);
    } catch (error) {
      console.log('❌ AmISafe API Access Failed:', error.response?.data || error.message);
    }
  }

  async runFullTest() {
    console.log('🚀 Starting AmISafe Mobile Authentication Test\n');
    
    // Test 1: User Registration
    const testUser = await this.testUserRegistration();
    console.log('');

    if (testUser) {
      // Wait a moment for user to be created
      await new Promise(resolve => setTimeout(resolve, 2000));

      // Test 2: OAuth Login
      const tokens = await this.testOAuthLogin(testUser.mail, testUser.pass);
      console.log('');

      if (tokens) {
        // Test 3: User Profile Access
        await this.testUserProfile();
        console.log('');

        // Test 4: AmISafe API Access
        await this.testAmISafeAPI();
        console.log('');
      }
    }

    console.log('🎉 Test Complete!\n');
    console.log('📱 If all tests passed, your mobile app authentication is ready!');
    console.log('🔧 Next step: Implement these same API calls in your React Native app');
  }
}

// Run the test
const tester = new AmISafeAuthTest();
tester.runFullTest().catch(console.error);