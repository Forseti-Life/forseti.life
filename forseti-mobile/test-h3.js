/**
 * H3 Integration Test - Verify H3 geospatial functionality
 */

// Test H3 functionality
const testH3Integration = () => {
  console.log('🧪 Testing H3 Integration...');

  try {
    // Import H3 library
    const h3 = require('h3-js');

    // Test coordinates (St. Louis)
    const testLat = 38.627;
    const testLng = -90.1994;

    console.log(`📍 Test Location: [${testLat}, ${testLng}]`);

    // Test H3 conversion at different resolutions
    for (let res = 5; res <= 13; res++) {
      const h3Index = h3.latLngToCell(testLat, testLng, res);
      const area = h3.cellArea(h3Index, h3.UNITS.m2);
      const [backLat, backLng] = h3.cellToLatLng(h3Index);

      console.log(
        `Resolution ${res}: ${h3Index} (${area.toFixed(0)}m²) → [${backLat.toFixed(6)}, ${backLng.toFixed(6)}]`
      );
    }

    // Test with user tracking resolution (13)
    const userH3 = h3.latLngToCell(testLat, testLng, 13);
    console.log(`\n🎯 User Tracking H3 (Resolution 13): ${userH3}`);

    // Test neighbors
    const neighbors = h3.gridRingUnsafe(userH3, 1);
    console.log(`🔍 Neighbors (1 ring): ${neighbors.length} hexagons`);
    neighbors.forEach((neighbor, i) => {
      console.log(`  Neighbor ${i + 1}: ${neighbor}`);
    });

    // Test boundary
    const boundary = h3.cellToBoundary(userH3);
    console.log(`\n📐 Hexagon Boundary (${boundary.length} points):`);
    boundary.forEach((point, i) => {
      console.log(`  Point ${i + 1}: [${point[0].toFixed(6)}, ${point[1].toFixed(6)}]`);
    });

    // Test distance
    const testLat2 = 38.628; // Slightly north
    const testLng2 = -90.1984; // Slightly east
    const h3Index2 = h3.latLngToCell(testLat2, testLng2, 13);
    const distance = h3.gridDistance(userH3, h3Index2);

    console.log(`\n📏 Distance Test:`);
    console.log(`  Point 1: [${testLat}, ${testLng}] → ${userH3}`);
    console.log(`  Point 2: [${testLat2}, ${testLng2}] → ${h3Index2}`);
    console.log(`  Distance: ${distance} hexagons`);

    console.log('\n✅ H3 Integration Test PASSED!');
    return true;
  } catch (error) {
    console.error('❌ H3 Integration Test FAILED:', error);
    return false;
  }
};

// Run the test
if (testH3Integration()) {
  console.log('\n🎉 AmISafe H3 geospatial system is ready!');
  console.log('📱 Mobile app can now use ultra-precise 44m² location tracking');
} else {
  console.log('\n⚠️ H3 integration needs debugging before mobile deployment');
}
