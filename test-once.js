/**
 * Simple test to verify once() functionality
 * This can be run in the browser console to test if once() works
 */

// Test once() function availability
console.log('Testing once() function...');

if (typeof once === 'function') {
  console.log('✅ once() function is available');

  // Test basic once() functionality
  const testElement = document.createElement('div');
  testElement.className = 'test-element';
  document.body.appendChild(testElement);

  // Test once() with a selector
  const result = once('test-once', '.test-element', document);
  console.log('✅ once() returned:', result);

  if (result && result.length > 0) {
    console.log('✅ once() successfully found elements');
  } else {
    console.log('❌ once() did not find elements');
  }

  // Clean up
  document.body.removeChild(testElement);

} else {
  console.log('❌ once() function is not available');
}

console.log('Once() test complete');