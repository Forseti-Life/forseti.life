# AmISafe Mobile Testing

Automated testing suite for the AmISafe React Native mobile application.

## Quick Start

```bash
# Install dependencies
npm install

# Run all tests
npm test

# Run specific test suites
npm run test:auth      # Authentication tests
npm run test:h3        # H3 geospatial tests
npm run test:api       # API integration tests
npm run test:location  # Location services tests
```

## Test Suites

### 1. Authentication Tests
- User registration flow
- Login/logout functionality
- Session management
- CSRF token handling
- Error handling

### 2. H3 Geospatial Tests
- Coordinate to H3 conversion
- Multi-resolution indexing (5-13)
- Neighbor calculation
- Distance measurement
- Boundary generation

### 3. API Integration Tests
- Crime data endpoints
- Risk level assessment
- Aggregated data queries
- Filter operations
- Performance benchmarks

### 4. Location Services Tests
- GPS accuracy
- Background monitoring
- Location permission handling
- Battery optimization
- Update frequency

## Test Configuration

Edit `test-config.js` to customize:
- API base URL
- Test data sets
- Timeout values
- Performance thresholds

## Current Status

✅ **Working**: H3 geospatial tests (100% pass rate)  
✅ **Working**: API connectivity tests  
🔄 **In Progress**: Authentication flow tests  
📋 **Planned**: Location services tests  
📋 **Planned**: Background monitoring tests

---

**Test Framework**: Jest + React Native Testing Library  
**Coverage Target**: 80%+ code coverage  
**Last Updated**: December 1, 2025
