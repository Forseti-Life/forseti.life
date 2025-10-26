# H3 Geolocation Framework Testing Suite

This directory contains comprehensive tests for the H3 geolocation framework to ensure reliability and correctness of all components.

## Test Structure

```
tests/
├── test_h3_framework.py      # Core framework tests
├── test_geospatial_utils.py   # Geospatial utilities tests  
├── test_data_processor.py     # Data processing tests
├── test_visualizer.py         # Visualization tests
├── test_integration.py        # Integration tests
├── test_examples.py           # Example scripts tests
├── fixtures/                  # Test data fixtures
│   ├── sample_data.json
│   ├── test_coordinates.csv
│   └── test_geojson.geojson
└── README.md                  # This file
```

## Running Tests

### Prerequisites
Ensure the H3 framework is installed:
```bash
# From h3-geolocation directory
source h3-env/bin/activate
pip install pytest pytest-cov
```

### Run All Tests
```bash
# Run all tests
python -m pytest tests/

# Run with coverage
python -m pytest tests/ --cov=. --cov-report=html

# Run specific test file
python -m pytest tests/test_h3_framework.py

# Run with verbose output
python -m pytest tests/ -v
```

## Test Categories

### Unit Tests
- **Framework Tests**: Core H3GeolocationFramework class methods
- **Utilities Tests**: GeospatialUtils helper functions
- **Data Processing Tests**: H3DataProcessor import/export/aggregation
- **Visualization Tests**: H3Visualizer map and chart generation

### Integration Tests
- **End-to-end Workflows**: Complete data processing pipelines
- **Cross-component Integration**: Testing component interactions
- **Performance Tests**: Memory usage and execution time validation

### Example Tests
- **Script Execution**: Verify all example scripts run without errors
- **Output Validation**: Check generated files and visualizations
- **Data Accuracy**: Verify calculations and transformations

## Test Data

### Sample Coordinates
- St. Louis landmarks and points of interest
- Random geographic data for stress testing
- Edge cases (poles, international date line, etc.)

### Expected Outputs
- Known H3 conversions for validation
- Pre-calculated distances and areas
- Reference visualizations for comparison

## Coverage Goals

Target test coverage by component:
- **H3 Framework**: >95%
- **Geospatial Utils**: >90%
- **Data Processor**: >90%
- **Visualizer**: >85%
- **Examples**: >80%

## Adding New Tests

When adding new functionality:

1. **Create unit tests** for new methods/functions
2. **Add integration tests** for complex workflows
3. **Include edge cases** and error conditions
4. **Update fixtures** with relevant test data
5. **Document test purpose** and expected behavior

### Test Template
```python
import pytest
from h3_framework import H3GeolocationFramework

class TestNewFeature:
    def setup_method(self):
        """Setup for each test method."""
        self.framework = H3GeolocationFramework()
    
    def test_basic_functionality(self):
        """Test basic operation."""
        # Arrange
        input_data = ...
        expected_output = ...
        
        # Act
        result = self.framework.new_method(input_data)
        
        # Assert
        assert result == expected_output
    
    def test_edge_cases(self):
        """Test edge cases and error conditions."""
        with pytest.raises(ValueError):
            self.framework.new_method(invalid_input)
```

## Continuous Integration

Tests are designed to run in CI/CD environments:
- No external dependencies required
- All test data included in fixtures
- Deterministic results for reproducibility
- Proper cleanup of temporary files

## Test Best Practices

1. **Isolation**: Each test should be independent
2. **Clarity**: Test names should describe what is being tested
3. **Completeness**: Cover happy path, edge cases, and errors
4. **Performance**: Tests should run quickly (<1s per test typically)
5. **Maintainability**: Keep tests simple and readable

## Known Test Limitations

- Visualization tests create files but don't validate visual output
- Some geographic calculations have floating-point precision limits
- Network-dependent features (geocoding) may be mocked in tests
- Large dataset tests may be time-limited for CI environments

## Contributing

When contributing tests:
1. Follow existing test patterns and naming conventions
2. Add docstrings explaining test purpose
3. Include both positive and negative test cases
4. Ensure tests pass locally before submitting
5. Update this README if adding new test categories