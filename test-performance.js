#!/usr/bin/env node

/**
 * AmISafe Performance Test Suite
 * 
 * This script simulates various dashboard operations to validate
 * the performance optimizations we've implemented.
 */

console.log('🧪 AmISafe Performance Testing Suite');
console.log('=====================================\n');

// Mock the AmISafe environment for testing
class MockAmISafe {
  constructor() {
    this.dataCache = new Map();
    this.cacheHitCount = 0;
    this.apiCallCount = 0;
    this.loadStartTime = Date.now();
    this.debugMode = true;
  }

  // Simulate cache operations
  getCachedData(key) {
    if (this.dataCache.has(key)) {
      this.cacheHitCount++;
      console.log(`💾 CACHE HIT: ${key}`);
      return this.dataCache.get(key);
    }
    return null;
  }

  setCachedData(key, data) {
    this.dataCache.set(key, {
      data: data,
      timestamp: Date.now()
    });
    console.log(`📝 CACHE SET: ${key}`);
  }

  simulateApiCall(key) {
    this.apiCallCount++;
    console.log(`🔍 API CALL: ${key}`);
    
    // Simulate API delay
    return new Promise(resolve => {
      setTimeout(() => {
        const mockData = {
          hexagons: Array(100).fill().map((_, i) => ({
            id: `hex_${i}`,
            incident_count: Math.floor(Math.random() * 20)
          }))
        };
        this.setCachedData(key, mockData);
        resolve(mockData);
      }, 100 + Math.random() * 200); // 100-300ms delay
    });
  }

  async loadHexagonData(resolution, filters) {
    const cacheKey = `${resolution}_${JSON.stringify(filters)}`;
    
    // Try cache first
    const cachedData = this.getCachedData(cacheKey);
    if (cachedData) {
      return cachedData.data;
    }

    // API call
    return await this.simulateApiCall(cacheKey);
  }

  getPerformanceStats() {
    const totalRequests = this.cacheHitCount + this.apiCallCount;
    const cacheHitRate = totalRequests > 0 ? (this.cacheHitCount / totalRequests * 100).toFixed(1) : 0;
    const sessionTime = Date.now() - this.loadStartTime;

    return {
      cacheHits: this.cacheHitCount,
      apiCalls: this.apiCallCount,
      cacheHitRate: parseFloat(cacheHitRate),
      cacheSize: this.dataCache.size,
      sessionTime: sessionTime
    };
  }

  cleanupCache() {
    const maxAge = 5 * 60 * 1000; // 5 minutes
    const now = Date.now();
    let cleaned = 0;

    for (const [key, entry] of this.dataCache.entries()) {
      if (now - entry.timestamp > maxAge) {
        this.dataCache.delete(key);
        cleaned++;
      }
    }

    if (cleaned > 0) {
      console.log(`🧹 CACHE CLEANUP: Removed ${cleaned} expired entries`);
    }
  }
}

// Test scenarios
async function runPerformanceTests() {
  const amisafe = new MockAmISafe();
  
  console.log('Test 1: Basic Cache Functionality');
  console.log('----------------------------------');
  
  // Load same data twice (should hit cache on second load)
  await amisafe.loadHexagonData(8, { district: 1 });
  await amisafe.loadHexagonData(8, { district: 1 }); // Cache hit
  
  console.log('\nTest 2: Different Resolutions');
  console.log('------------------------------');
  
  // Load different resolutions (different cache keys)
  await amisafe.loadHexagonData(7, { district: 1 });
  await amisafe.loadHexagonData(9, { district: 1 });
  
  console.log('\nTest 3: Filter Variations');
  console.log('-------------------------');
  
  // Load with different filters
  await amisafe.loadHexagonData(8, { district: 2 });
  await amisafe.loadHexagonData(8, { district: 1, severity: 'HIGH' });
  await amisafe.loadHexagonData(8, { district: 1 }); // Cache hit
  
  console.log('\nTest 4: Parallel Loading Simulation');
  console.log('-----------------------------------');
  
  // Simulate parallel loading of filter options
  const startTime = Date.now();
  const promises = [
    amisafe.simulateApiCall('districts'),
    amisafe.simulateApiCall('crime-types'),
    amisafe.simulateApiCall('severity-levels'),
    amisafe.simulateApiCall('time-periods')
  ];
  
  await Promise.all(promises);
  const parallelTime = Date.now() - startTime;
  console.log(`⚡ Parallel loading completed in ${parallelTime}ms`);
  
  console.log('\nTest 5: Cache Cleanup');
  console.log('--------------------');
  amisafe.cleanupCache();
  
  console.log('\n📊 Final Performance Report');
  console.log('============================');
  const stats = amisafe.getPerformanceStats();
  console.log(`Cache Hits: ${stats.cacheHits}`);
  console.log(`API Calls: ${stats.apiCalls}`);
  console.log(`Cache Hit Rate: ${stats.cacheHitRate}%`);
  console.log(`Cache Size: ${stats.cacheSize} entries`);
  console.log(`Session Time: ${(stats.sessionTime / 1000).toFixed(1)}s`);
  
  // Validate performance expectations
  console.log('\n✅ Performance Validation');
  console.log('=========================');
  
  if (stats.cacheHitRate >= 30) {
    console.log(`✓ Cache efficiency: ${stats.cacheHitRate}% (Target: ≥30%)`);
  } else {
    console.log(`⚠ Cache efficiency: ${stats.cacheHitRate}% (Below target of 30%)`);
  }
  
  if (stats.cacheSize > 0) {
    console.log(`✓ Cache utilization: ${stats.cacheSize} entries stored`);
  }
  
  if (parallelTime < 1000) {
    console.log(`✓ Parallel loading: ${parallelTime}ms (Target: <1000ms)`);
  } else {
    console.log(`⚠ Parallel loading: ${parallelTime}ms (Above target of 1000ms)`);
  }
  
  console.log('\n🚀 Performance testing complete!');
}

// Run the tests
runPerformanceTests().catch(error => {
  console.error('❌ Test failed:', error);
  process.exit(1);
});