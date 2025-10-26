# H3 Geolocation Data Framework

A comprehensive geospatial analysis framework using Uber's H3 (Hexagonal Hierarchical Spatial Index) for location-based data processing and visualization.

## Overview

This framework provides tools for:
- Converting latitude/longitude coordinates to H3 hexagon indices
- Spatial aggregation and analysis using hexagonal grids
- Geospatial data visualization with interactive maps
- Location-based analytics and insights
- Multi-resolution spatial indexing (resolutions 0-15)

## Installation

### Prerequisites
- Python 3.12+
- Virtual environment (recommended)

### Setup
```bash
# Activate the virtual environment
source h3-env/bin/activate

# Install dependencies (already installed)
pip install h3 pandas numpy matplotlib folium geopy requests
```

## Quick Start

```python
import h3
from h3_framework import H3GeolocationFramework

# Initialize the framework
h3_framework = H3GeolocationFramework()

# Convert coordinates to H3 index
lat, lng = 38.6270, -90.1994  # St. Louis coordinates
h3_index = h3_framework.coords_to_h3(lat, lng, resolution=9)
print(f"H3 Index: {h3_index}")

# Get hexagon boundary
boundary = h3_framework.get_hexagon_boundary(h3_index)

# Create visualization
map_viz = h3_framework.visualize_hexagons([h3_index], center=[lat, lng])
map_viz.save('st_louis_h3.html')
```

## Framework Components

### Core Modules
- `h3_framework.py` - Main framework class with core functionality
- `geospatial_utils.py` - Utility functions for coordinate transformations
- `visualization.py` - Interactive mapping and visualization tools
- `data_processor.py` - Batch processing and analysis tools
- `examples/` - Usage examples and tutorials

### Key Features
- **Multi-resolution Analysis**: Work with H3 resolutions 0-15 (global to building level)
- **Batch Processing**: Process large datasets efficiently
- **Interactive Visualization**: Create interactive maps with Folium
- **Spatial Relationships**: Find neighbors, rings, and hierarchical relationships
- **Data Aggregation**: Aggregate point data to hexagonal grids
- **Export Capabilities**: Export to GeoJSON, CSV, and other formats

## Usage Examples

### Basic H3 Operations
```python
# Convert coordinates to H3
h3_index = h3.latlng_to_cell(38.6270, -90.1994, 9)

# Get hexagon center
center = h3.cell_to_latlng(h3_index)

# Get hexagon boundary
boundary = h3.cell_to_boundary(h3_index)

# Get neighboring hexagons
neighbors = h3.grid_ring(h3_index, 1)
```

### Spatial Analysis
```python
# Find all hexagons within a distance
hexagons_within_distance = h3.grid_disk(h3_index, 5)

# Get parent/child relationships
parent = h3.cell_to_parent(h3_index, 7)  # Lower resolution
children = h3.cell_to_children(parent, 9)  # Higher resolution
```

## Resolutions Guide

| Resolution | Hexagon Edge Length | Area         | Use Case                    |
|------------|--------------------|--------------|-----------------------------|
| 0          | 1,107.712 km       | ~4,250,000 km² | Global/continental analysis |
| 1          | 418.676 km         | ~607,000 km²  | Country-level analysis     |
| 2          | 158.244 km         | ~86,700 km²   | State/province analysis    |
| 3          | 59.810 km          | ~12,400 km²   | Metropolitan areas         |
| 4          | 22.606 km          | ~1,770 km²    | City-wide analysis         |
| 5          | 8.544 km           | ~253 km²      | Urban districts            |
| 6          | 3.229 km           | ~36.1 km²     | Neighborhoods              |
| 7          | 1.221 km           | ~5.16 km²     | Local areas                |
| 8          | 461.354 m          | ~0.737 km²    | City blocks                |
| 9          | 174.375 m          | ~0.105 km²    | Buildings/parcels          |
| 10         | 65.907 m           | ~0.015 km²    | Property-level             |

## API Reference

See the [API Documentation](docs/api.md) for detailed information about all available methods and classes.

## Examples

Check the `examples/` directory for:
- Basic H3 operations
- Spatial analysis workflows
- Visualization examples
- Real-world use cases

## Contributing

This framework is part of the St. Louis Integration geospatial analysis toolkit. For contributions and issues, please follow the project guidelines.

## License

MIT License - see LICENSE file for details.