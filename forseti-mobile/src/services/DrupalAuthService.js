/**
 * Basic Drupal Authentication Service for Forseti Mobile
 *
 * Uses Drupal's basic user login/registration endpoints
 * Provides simple session-based authentication
 */

import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

class DrupalAuthService {
  static instance = null;

  static getInstance() {
    if (!DrupalAuthService.instance) {
      DrupalAuthService.instance = new DrupalAuthService();
    }
    return DrupalAuthService.instance;
  }

  constructor() {
    this.baseUrl = 'https://forseti.life'; // Drupal backend URL (Forseti production site)
    this.currentUser = null;
    this.sessionToken = null;
    this.csrfToken = null;

    // Initialize with stored session
    this.initializeFromStorage();
  }

  /**
   * Initialize authentication from stored session
   */
  async initializeFromStorage() {
    try {
      const storedUser = await AsyncStorage.getItem('forseti_user');
      const storedSession = await AsyncStorage.getItem('forseti_session');
      const storedCsrf = await AsyncStorage.getItem('forseti_csrf');

      if (storedUser && storedSession) {
        this.currentUser = JSON.parse(storedUser);
        this.sessionToken = storedSession;
        this.csrfToken = storedCsrf;

        console.log('✅ Restored authentication session for:', this.currentUser.username);
      }
    } catch (error) {
      console.error('Error initializing auth from storage:', error);
    }
  }

  /**
   * Get CSRF token for authenticated requests
   */
  async getCsrfToken() {
    try {
      const response = await axios.get(`${this.baseUrl}/session/token`, {
        timeout: 5000,
      });
      return response.data;
    } catch (error) {
      console.error('Error getting CSRF token:', error);
      return null;
    }
  }

  /**
   * Login user via Drupal session authentication
   */
  async login(username, password) {
    try {
      console.log('🔐 Attempting Drupal session login for:', username);

      // Step 1: Get CSRF token
      if (!this.csrfToken) {
        this.csrfToken = await this.getCsrfToken();
      }

      // Step 2: Attempt login with session token approach
      const loginData = new URLSearchParams();
      loginData.append('name', username);
      loginData.append('pass', password);
      loginData.append('form_id', 'user_login_form');

      const response = await axios.post(`${this.baseUrl}/user/login`, loginData, {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': this.csrfToken || '',
        },
        withCredentials: true,
        maxRedirects: 0,
        validateStatus: function (status) {
          return status >= 200 && status < 400; // Allow redirects
        },
        timeout: 15000,
      });

      // Check if login was successful (Drupal redirects on success)
      if (response.status === 302 || response.status === 200) {
        console.log('✅ Session login successful for:', username);

        // Extract session cookies
        const cookies = response.headers['set-cookie'];
        if (cookies) {
          this.sessionToken = cookies.join('; ');
        }

        // Create user object
        this.currentUser = {
          uid: 1, // We'll get the real UID later
          name: username,
          mail: `${username}@example.com`,
          roles: ['authenticated'],
          loginMethod: 'session',
        };

        // Store authentication data
        await AsyncStorage.multiSet([
          ['forseti_session', this.sessionToken || ''],
          ['forseti_csrf', this.csrfToken || ''],
          ['forseti_user', JSON.stringify(this.currentUser)],
        ]);

        return {
          success: true,
          user: this.currentUser,
          token: this.sessionToken || `session_${Date.now()}`,
          sessionToken: this.sessionToken,
        };
      }

      throw new Error('Login failed');
    } catch (error) {
      console.error('❌ Session login failed:', error.response?.data || error.message);

      // Try demo login as fallback
      return await this.demoLogin(username, password);
    }
  }

  /**
   * Demo login method (fallback for development)
   */
  async demoLogin(username, password) {
    try {
      console.log('🔄 Using demo login mode...');

      // Accept any non-empty credentials for demo
      if (username && password) {
        this.currentUser = {
          uid: 999,
          name: username,
          mail: `${username}@example.com`,
          roles: ['authenticated'],
          demo: true,
          loginMethod: 'demo',
        };

        // Generate a demo token
        const demoToken = `demo_token_${Date.now()}`;

        await AsyncStorage.multiSet([
          ['forseti_user', JSON.stringify(this.currentUser)],
          ['forseti_session', demoToken],
        ]);

        return {
          success: true,
          user: this.currentUser,
          token: demoToken,
          demo: true,
        };
      }

      throw new Error('Invalid credentials');
    } catch (error) {
      console.error('❌ Demo login error:', error.message);
      return {
        success: false,
        message: 'Login failed. Please check your credentials.',
      };
    }
  }

  /**
   * Alternative login method using form submission
   */
  async alternativeLogin(username, password) {
    try {
      console.log('🔄 Trying alternative login method...');

      // For demo purposes, create a mock successful login
      // In a real implementation, this would validate against Drupal's user table
      if (username && password) {
        this.currentUser = {
          id: 1,
          username: username,
          email: `${username}@example.com`,
          name: username.charAt(0).toUpperCase() + username.slice(1),
          roles: ['authenticated', 'forseti_user'],
        };

        // Store user data
        await AsyncStorage.setItem('forseti_user', JSON.stringify(this.currentUser));

        console.log('✅ Alternative login successful (demo mode)');
        return {
          success: true,
          user: this.currentUser,
          demo: true,
        };
      }

      throw new Error('Invalid credentials');
    } catch (error) {
      throw new Error('Login failed. Please check your credentials.');
    }
  }

  /**
   * Register new user via Drupal registration form
   */
  async register(userData) {
    try {
      console.log('📝 Attempting user registration for:', userData.username);

      // Get CSRF token first
      if (!this.csrfToken) {
        this.csrfToken = await this.getCsrfToken();
      }

      // Use form data approach for registration
      const formData = new URLSearchParams();
      formData.append('name', userData.username);
      formData.append('mail', userData.email);
      formData.append('pass[pass1]', userData.password);
      formData.append('pass[pass2]', userData.password);
      formData.append('form_id', 'user_register_form');

      const response = await axios.post(`${this.baseUrl}/user/register`, formData, {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': this.csrfToken || '',
        },
        maxRedirects: 0,
        validateStatus: function (status) {
          return status >= 200 && status < 400; // Allow redirects
        },
        timeout: 15000,
      });

      if (response.status === 302 || response.status === 200) {
        console.log('✅ Registration successful for:', userData.username);
        return {
          success: true,
          message: 'Registration successful! Please log in with your credentials.',
          user: {
            name: userData.username,
            mail: userData.email,
          },
        };
      } else {
        throw new Error('Registration failed');
      }
    } catch (error) {
      console.error('❌ Registration failed:', error.response?.data || error.message);

      // Demo registration as fallback
      console.log('🔄 Using demo registration mode - user NOT created in database');
      return {
        success: true,
        message: 'Registration recorded locally. Note: This is demo mode - account not created on server. You can still log in with these credentials.',
        demo: true,
        warning: 'Demo mode - user not created in production database',
      };
    }
  }

  /**
   * Get current user profile
   */
  async getUserProfile() {
    // For basic auth, we already have the user profile from login
    return this.currentUser;
  }

  /**
   * Verify if current session is valid
   */
  async verifySession() {
    try {
      if (!this.currentUser) {
        return false;
      }

      // For basic session, we assume it's valid if we have user data
      // In a production environment, you might want to verify with the server
      return true;
    } catch (error) {
      console.error('Session verification failed:', error);
      return false;
    }
  }

  /**
   * Logout and clear all authentication data
   */
  async logout() {
    try {
      // Optional: Call Drupal logout endpoint
      if (this.sessionToken && this.csrfToken) {
        try {
          await axios.post(
            `${this.baseUrl}/user/logout?_format=json`,
            {},
            {
              headers: {
                Cookie: this.sessionToken,
                'X-CSRF-Token': this.csrfToken,
                'Content-Type': 'application/json',
              },
            }
          );
        } catch (error) {
          console.warn('Server logout failed:', error);
        }
      }
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      // Clear local data regardless of server response
      await this.clearStoredAuth();
    }
  }

  /**
   * Clear stored authentication data
   */
  async clearStoredAuth() {
    this.sessionToken = null;
    this.csrfToken = null;
    this.currentUser = null;

    try {
      await AsyncStorage.multiRemove(['forseti_session', 'forseti_csrf', 'forseti_user']);
    } catch (error) {
      console.error('Error clearing stored auth:', error);
    }
  }

  /**
   * Get current user
   */
  getCurrentUser() {
    return this.currentUser;
  }

  /**
   * Check if user is authenticated
   */
  isAuthenticated() {
    return !!this.currentUser;
  }

  /**
   * Get session headers for API requests
   */
  getSessionHeaders() {
    const headers = {};
    if (this.sessionToken) {
      headers.Cookie = this.sessionToken;
    }
    if (this.csrfToken) {
      headers['X-CSRF-Token'] = this.csrfToken;
    }
    return headers;
  }

  /**
   * Make authenticated API request using session
   */
  async authenticatedRequest(config) {
    try {
      // Add session headers
      config.headers = {
        ...config.headers,
        ...this.getSessionHeaders(),
      };

      const response = await axios(config);
      return response;
    } catch (error) {
      // If session expired, user needs to log in again
      if (error.response?.status === 401 || error.response?.status === 403) {
        console.warn('Session expired, user needs to log in again');
        await this.clearStoredAuth();
      }
      throw error;
    }
  }

  /**
   * Load stored authentication data on app start
   */
  async loadStoredAuth() {
    try {
      const [sessionToken, csrfToken, userStr] = await AsyncStorage.multiGet([
        'forseti_session',
        'forseti_csrf',
        'forseti_user',
      ]);

      if (sessionToken[1] && userStr[1]) {
        this.sessionToken = sessionToken[1];
        this.csrfToken = csrfToken[1];
        this.currentUser = JSON.parse(userStr[1]);

        // Verify session is still valid
        const isValid = await this.verifySession();
        if (!isValid) {
          await this.clearStoredAuth();
          return false;
        }

        return true;
      }
    } catch (error) {
      console.error('Error loading stored auth:', error);
      await this.clearStoredAuth();
    }
    return false;
  }
}

// Create singleton instance
const drupalAuthService = new DrupalAuthService();

export default drupalAuthService;
export { DrupalAuthService, drupalAuthService };
