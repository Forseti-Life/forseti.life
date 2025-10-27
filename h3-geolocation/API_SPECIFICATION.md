# API Specification - amIsafe Crime Dashboard
## RESTful API Endpoints

### Overview
The amIsafe Crime Dashboard API provides access to Philadelphia crime data through RESTful endpoints. All data is served through Drupal's REST API framework with custom controllers for spatial and temporal queries.

### Base URL
```
https://theoryofconspiracies.com/api/amisafe/
```

### Authentication
- **Development**: Open access (no authentication required)
- **Production**: Drupal session-based authentication
- **Rate Limiting**: 1000 requests per hour per IP

### Response Format
All API responses follow a consistent JSON structure:
```json
{
  "data": [...],
  "meta": {
    "total": 0,
    "filtered": 0,
    "page": 1,
    "limit": 100,
    "execution_time": "0.023s"
  },
  "links": {
    "self": "...",
    "first": "...",
    "last": "...",
    "next": "...",
    "prev": "..."
  },
  "errors": []
}
```

## Core Endpoints

### 1. Incident Data Endpoints

#### GET /incidents
Retrieve filtered incident data with spatial and temporal parameters.

**Parameters:**
```
?lat_min=39.9&lat_max=40.1           # Bounding box (required for large queries)
&lng_min=-75.3&lng_max=-75.1
&date_start=2025-01-01               # Date range (ISO format)
&date_end=2025-12-31
&crime_types[]=600&crime_types[]=500 # Crime category codes (array)
&districts[]=12&districts[]=14       # Police districts (array)
&severity_min=1&severity_max=5       # Severity range
&hour_start=0&hour_end=23            # Time of day range
&h3_resolution=9                     # H3 zoom level (6-12)
&limit=1000                          # Results per page
&offset=0                            # Pagination offset
&format=geojson                      # Response format (json|geojson)
```

**Response:**
```json
{
  "data": [
    {
      "id": 123456789,
      "h3_index": "892aacb2e57ffff",
      "coordinates": {
        "lat": 39.9124426,
        "lng": -75.2427417
      },
      "incident": {
        "datetime": "2025-06-29T10:10:00Z",
        "date": "2025-06-29",
        "time": "10:10:00",
        "hour": 10,
        "day_of_week": 0
      },
      "location": {
        "block": "2500 BLOCK ISLAND AVE",
        "district": "12",
        "psa": "1",
        "point_x": -75.2427417,
        "point_y": 39.9124426
      },
      "crime": {
        "ucr_code": "600",
        "category": "Theft from Vehicle",
        "severity": 3,
        "text_general_code": "Theft from Vehicle"
      },
      "metadata": {
        "source_file": "incidents_part1_part2.csv",
        "dc_key": "2025290123456",
        "objectid": "789123",
        "cartodb_id": "456789"
      }
    }
  ],
  "meta": {
    "total": 2547832,
    "filtered": 1247,
    "time_range": {
      "start": "2025-06-01T00:00:00Z",
      "end": "2025-06-30T23:59:59Z"
    },
    "spatial_bounds": {
      "lat_min": 39.9,
      "lat_max": 40.1,
      "lng_min": -75.3,
      "lng_max": -75.1
    },
    "h3_resolution": 9,
    "unique_h3_cells": 89
  }
}
```

#### GET /incidents/{id}
Retrieve detailed information for a specific incident.

**Response:**
```json
{
  "data": {
    "id": 123456789,
    "full_details": "...",
    "related_incidents": [
      {
        "id": 123456790,
        "distance_meters": 150,
        "time_difference_hours": 2.5
      }
    ],
    "h3_neighbors": [
      "892aacb2e57ffff",
      "892aacb2e5fffff"
    ]
  }
}
```

### 2. Spatial Aggregation Endpoints

#### GET /aggregated
Retrieve pre-computed H3 spatial aggregations.

**Parameters:**
```
?h3_resolution=8                     # H3 zoom level (required)
&period=daily                       # Aggregation period (hourly|daily|weekly|monthly)
&date_start=2025-01-01
&date_end=2025-12-31
&crime_types[]=600
&include_empty=false                 # Include cells with zero incidents
&min_incidents=5                     # Minimum threshold for inclusion
```

**Response:**
```json
{
  "data": [
    {
      "h3_index": "882aacb2e5fffff",
      "resolution": 8,
      "bounds": {
        "center": {
          "lat": 39.9124426,
          "lng": -75.2427417
        },
        "vertices": [
          {"lat": 39.91, "lng": -75.24},
          {"lat": 39.91, "lng": -75.25}
        ]
      },
      "statistics": {
        "total_incidents": 147,
        "unique_crime_types": 8,
        "severity_average": 2.3,
        "incidents_per_day": 4.9,
        "trend_direction": "increasing",
        "trend_percentage": 12.5
      },
      "crime_breakdown": {
        "600": {"count": 45, "percentage": 30.6},
        "500": {"count": 32, "percentage": 21.8},
        "400": {"count": 28, "percentage": 19.0}
      },
      "temporal": {
        "peak_hour": 14,
        "peak_day": 5,
        "last_incident": "2025-06-29T16:45:00Z",
        "first_incident": "2025-06-01T08:15:00Z"
      }
    }
  ],
  "meta": {
    "h3_cells_count": 234,
    "total_incidents": 15847,
    "average_incidents_per_cell": 67.7,
    "aggregation_period": "daily",
    "resolution_area_km2": 0.737
  }
}
```

#### GET /hotspots
Identify crime density hotspots using spatial clustering.

**Parameters:**
```
?h3_resolution=9
&algorithm=dbscan                    # Clustering algorithm (dbscan|kmeans)
&min_density=20                      # Minimum incidents per cluster
&date_range=30d                      # Time window (7d|30d|90d|1y)
```

**Response:**
```json
{
  "data": [
    {
      "cluster_id": 1,
      "center": {
        "h3_index": "892aacb2e57ffff",
        "lat": 39.9124426,
        "lng": -75.2427417
      },
      "properties": {
        "total_incidents": 347,
        "radius_meters": 500,
        "density_score": 0.85,
        "risk_level": "high",
        "primary_crime_types": ["600", "500", "400"]
      },
      "h3_cells": [
        "892aacb2e57ffff",
        "892aacb2e5fffff",
        "892aacb2e4fffff"
      ]
    }
  ]
}
```

### 3. Reference Data Endpoints

#### GET /categories
Retrieve crime category definitions and mappings.

**Response:**
```json
{
  "data": [
    {
      "ucr_code": "600",
      "category_name": "Theft from Vehicle",
      "severity_level": 3,
      "color_hex": "#FF6B35",
      "icon_class": "fas fa-car-side",
      "description": "Theft of property from motor vehicles",
      "parent_category": "Property Crime",
      "incident_count": 45782,
      "percentage_of_total": 18.5
    }
  ]
}
```

#### GET /districts
Retrieve police district boundary information.

**Response:**
```json
{
  "data": [
    {
      "district_code": "12",
      "district_name": "Southwest District",
      "bounds": {
        "type": "Polygon",
        "coordinates": [[[-75.25, 39.90], [-75.20, 39.95]]]
      },
      "statistics": {
        "total_incidents": 8547,
        "incidents_per_day": 23.4,
        "most_common_crime": "600",
        "safest_hour": 5,
        "busiest_hour": 15
      },
      "h3_coverage": {
        "resolution_8": ["882aacb2e5fffff", "882aacb2e4fffff"],
        "total_cells": 156
      }
    }
  ]
}
```

### 4. Temporal Analysis Endpoints

#### GET /trends
Analyze temporal patterns and trends in crime data.

**Parameters:**
```
?timeframe=30d                       # Analysis period
&granularity=daily                   # Data granularity (hourly|daily|weekly)
&crime_types[]=600
&comparison_period=previous          # Compare to previous period
&h3_resolution=8
```

**Response:**
```json
{
  "data": {
    "current_period": {
      "start": "2025-06-01",
      "end": "2025-06-30",
      "total_incidents": 2847,
      "daily_average": 94.9,
      "trend": "increasing",
      "trend_percentage": 8.3
    },
    "comparison_period": {
      "start": "2025-05-01",
      "end": "2025-05-31",
      "total_incidents": 2631,
      "daily_average": 84.9,
      "difference": 216,
      "percentage_change": 8.2
    },
    "time_series": [
      {
        "date": "2025-06-01",
        "incidents": 89,
        "moving_average": 91.2
      }
    ],
    "patterns": {
      "peak_hours": [14, 15, 16],
      "peak_days": [5, 6],
      "seasonal_index": 1.12
    }
  }
}
```

#### GET /heatmap
Generate temporal heatmap data for visualization.

**Parameters:**
```
?type=hour_day                       # Heatmap type (hour_day|day_month|month_year)
&crime_types[]=600
&date_range=90d
```

**Response:**
```json
{
  "data": {
    "matrix": [
      [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23],
      [12, 8, 5, 3, 2, 4, 8, 15, 28, 45, 52, 48, 51, 65, 72, 68, 58, 52, 48, 42, 35, 28, 22, 18],
      [15, 11, 7, 4, 3, 5, 10, 18, 32, 48, 55, 52, 55, 68, 75, 71, 61, 55, 51, 45, 38, 31, 25, 21]
    ],
    "labels": {
      "x": ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
      "y": ["00:00", "01:00", "02:00", "03:00", "04:00", "05:00", "06:00", "07:00", "08:00", "09:00", "10:00", "11:00", "12:00", "13:00", "14:00", "15:00", "16:00", "17:00", "18:00", "19:00", "20:00", "21:00", "22:00", "23:00"]
    },
    "statistics": {
      "max_value": 75,
      "min_value": 2,
      "average": 35.7,
      "peak_time": {"day": 5, "hour": 14}
    }
  }
}
```

### 5. Search and Query Endpoints

#### GET /search
Full-text and spatial search functionality.

**Parameters:**
```
?q=temple university                 # Text search query
&type=location                       # Search type (location|address|landmark)
&radius=1000                         # Search radius in meters
&lat=39.9812&lng=-75.1551           # Center point for spatial search
```

**Response:**
```json
{
  "data": [
    {
      "type": "location",
      "name": "Temple University",
      "coordinates": {
        "lat": 39.9812,
        "lng": -75.1551
      },
      "h3_index": "892aacb2e57ffff",
      "nearby_incidents": 147,
      "incident_density": 0.73,
      "safety_score": 6.2
    }
  ]
}
```

### 6. Export Endpoints

#### GET /export
Export data in various formats for external analysis.

**Parameters:**
```
?format=csv                          # Export format (csv|geojson|kml|shapefile)
&filters[date_start]=2025-01-01      # Apply same filters as incidents endpoint
&include_h3=true                     # Include H3 index columns
```

**Response:**
- CSV: `Content-Type: text/csv`
- GeoJSON: `Content-Type: application/geo+json`
- KML: `Content-Type: application/vnd.google-earth.kml+xml`

## Error Handling

### Error Response Format
```json
{
  "errors": [
    {
      "status": "400",
      "code": "INVALID_PARAMETER",
      "title": "Invalid Parameter",
      "detail": "h3_resolution must be between 6 and 12",
      "source": {
        "parameter": "h3_resolution"
      }
    }
  ]
}
```

### HTTP Status Codes
- `200` - Success
- `400` - Bad Request (invalid parameters)
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Unprocessable Entity (validation errors)
- `429` - Too Many Requests (rate limit exceeded)
- `500` - Internal Server Error

### Common Error Codes
- `INVALID_PARAMETER` - Parameter validation failed
- `MISSING_REQUIRED_PARAMETER` - Required parameter not provided
- `RATE_LIMIT_EXCEEDED` - API rate limit exceeded
- `SPATIAL_BOUNDS_TOO_LARGE` - Requested area exceeds maximum size
- `DATE_RANGE_TOO_LARGE` - Requested time range exceeds maximum
- `H3_RESOLUTION_INVALID` - H3 resolution not supported
- `INTERNAL_ERROR` - Database or processing error

## Rate Limiting

### Default Limits
- **Anonymous users**: 100 requests per hour
- **Authenticated users**: 1000 requests per hour
- **Premium users**: 10000 requests per hour

### Rate Limit Headers
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
X-RateLimit-Retry-After: 3600
```

## Caching Strategy

### Cache Headers
```
Cache-Control: public, max-age=300
ETag: "a1b2c3d4e5f6"
Last-Modified: Sat, 01 Jan 2025 12:00:00 GMT
Vary: Accept-Encoding
```

### Cache Invalidation
- **Incident data**: Cached for 5 minutes
- **Aggregated data**: Cached for 1 hour
- **Reference data**: Cached for 24 hours
- **Static boundaries**: Cached for 7 days

## API Versioning

### Version Strategy
- Current version: `v1`
- URL versioning: `/api/v1/amisafe/`
- Header versioning: `Accept: application/vnd.amisafe.v1+json`

### Backward Compatibility
- Breaking changes require new version
- Deprecated endpoints supported for 12 months
- Migration guides provided for version updates

## Development Tools

### API Documentation
- Interactive API explorer at `/api/docs`
- OpenAPI 3.0 specification available
- Postman collection provided

### Testing Endpoints
```
GET /api/amisafe/health          # API health check
GET /api/amisafe/version         # API version information
GET /api/amisafe/metrics         # Performance metrics (admin only)
```

This comprehensive API specification provides the foundation for building robust client applications that can effectively interact with the amIsafe crime data and visualization system.