# H3 Large Dataset Integration Guide

## How Data is Stored in H3

### H3 Index Format
H3 stores spatial data using **64-bit integers** represented as **hexadecimal strings**:
- **Example**: `892640c822bffff` (St. Louis Gateway Arch at resolution 9)
- **Structure**: Encodes resolution, base cell, and hierarchical position
- **Advantages**: Compact, indexable, hierarchical, and supports spatial relationships

### Data Storage Patterns

#### 1. **H3 Index as Primary Key**
```sql
-- Optimal MySQL table structure
CREATE TABLE spatial_data (
    h3_index BIGINT UNSIGNED PRIMARY KEY,  -- 64-bit H3 index
    resolution TINYINT NOT NULL,           -- 0-15 resolution level
    lat DECIMAL(10, 8) NOT NULL,           -- Original latitude
    lng DECIMAL(11, 8) NOT NULL,           -- Original longitude
    data_value DECIMAL(12, 4),             -- Your measurement data
    category VARCHAR(50),                  -- Data category
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    properties JSON,                       -- Additional attributes
    
    INDEX idx_resolution (resolution),
    INDEX idx_coords (lat, lng),
    INDEX idx_timestamp (timestamp),
    INDEX idx_category (category)
);
```

#### 2. **Multi-Resolution Storage**
```sql
-- Store data at multiple resolutions for efficient querying
CREATE TABLE h3_aggregated (
    h3_index BIGINT UNSIGNED,
    resolution TINYINT,
    data_type VARCHAR(50),
    count_total INT,
    sum_value DECIMAL(15, 4),
    avg_value DECIMAL(12, 4),
    min_value DECIMAL(12, 4),
    max_value DECIMAL(12, 4),
    std_value DECIMAL(12, 4),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (h3_index, resolution, data_type),
    INDEX idx_resolution_type (resolution, data_type)
);
```

## MySQL Integration for H3

### 1. **Optimized Schema Design**

```sql
-- Main data table
CREATE TABLE h3_points (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    h3_index BIGINT UNSIGNED NOT NULL,
    resolution TINYINT NOT NULL,
    original_lat DECIMAL(10, 8),
    original_lng DECIMAL(11, 8),
    value DECIMAL(12, 4),
    category VARCHAR(100),
    source VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_h3_source (h3_index, source),
    INDEX idx_h3_resolution (h3_index, resolution),
    INDEX idx_category_resolution (category, resolution),
    INDEX idx_created_at (created_at)
);

-- Spatial hierarchy table
CREATE TABLE h3_hierarchy (
    child_h3 BIGINT UNSIGNED,
    parent_h3 BIGINT UNSIGNED,
    child_resolution TINYINT,
    parent_resolution TINYINT,
    
    PRIMARY KEY (child_h3, parent_h3),
    INDEX idx_parent (parent_h3, parent_resolution),
    INDEX idx_child (child_h3, child_resolution)
);

-- Neighbors table for fast spatial queries
CREATE TABLE h3_neighbors (
    h3_index BIGINT UNSIGNED,
    neighbor_h3 BIGINT UNSIGNED,
    ring_distance TINYINT,  -- 1=immediate neighbors, 2=second ring, etc.
    
    PRIMARY KEY (h3_index, neighbor_h3),
    INDEX idx_neighbor (neighbor_h3),
    INDEX idx_ring (h3_index, ring_distance)
);
```

### 2. **MySQL Functions for H3**

```sql
-- Create MySQL functions to work with H3 indices
DELIMITER //

-- Convert H3 hex string to BIGINT
CREATE FUNCTION h3_hex_to_int(hex_string VARCHAR(16))
RETURNS BIGINT UNSIGNED
DETERMINISTIC
RETURN CONV(hex_string, 16, 10);
//

-- Convert BIGINT to H3 hex string
CREATE FUNCTION h3_int_to_hex(h3_int BIGINT UNSIGNED)
RETURNS VARCHAR(16)
DETERMINISTIC
RETURN LPAD(HEX(h3_int), 16, '0');
//

DELIMITER ;
```

### 3. **Efficient Query Patterns**

```sql
-- Query by region (using parent hexagon)
SELECT h3_index, value, category
FROM h3_points 
WHERE h3_index IN (
    SELECT child_h3 
    FROM h3_hierarchy 
    WHERE parent_h3 = 617700169958293503  -- Parent hex at resolution 6
    AND child_resolution = 9
);

-- Aggregate data by hexagon
SELECT 
    h3_index,
    COUNT(*) as point_count,
    AVG(value) as avg_value,
    SUM(value) as total_value,
    MIN(value) as min_value,
    MAX(value) as max_value
FROM h3_points 
WHERE resolution = 8
  AND category = 'temperature'
  AND created_at >= '2025-01-01'
GROUP BY h3_index;

-- Find neighbors for spatial analysis
SELECT p.h3_index, p.value, n.ring_distance
FROM h3_points p
JOIN h3_neighbors n ON p.h3_index = n.neighbor_h3
WHERE n.h3_index = 617700169958293503  -- Center hexagon
  AND n.ring_distance <= 2  -- Within 2 rings
  AND p.resolution = 9;
```

## Best Practices for Data File Organization

### 1. **Directory Structure**
```
h3-geolocation/
├── data/
│   ├── raw/                     # Original data files
│   │   ├── 2025/
│   │   │   ├── 01/             # Monthly organization
│   │   │   │   ├── temperature_readings_20250101.csv
│   │   │   │   ├── traffic_data_20250101.json
│   │   │   │   └── demographics_20250101.geojson
│   │   │   └── 02/
│   │   └── archive/             # Archived old data
│   │
│   ├── processed/               # H3-converted data
│   │   ├── h3_resolution_7/
│   │   ├── h3_resolution_8/
│   │   ├── h3_resolution_9/
│   │   └── aggregated/
│   │
│   ├── exports/                 # Generated outputs
│   │   ├── maps/               # HTML map files
│   │   ├── reports/            # Analysis reports
│   │   └── api_responses/      # API data exports
│   │
│   ├── cache/                  # Temporary cached data
│   │   ├── spatial_indices/
│   │   └── query_cache/
│   │
│   └── backups/                # Database backups
│       ├── daily/
│       └── weekly/
│
├── config/
│   ├── database.json          # DB connection configs
│   ├── data_sources.json      # Data source definitions
│   └── processing_rules.json  # Data processing rules
│
└── logs/
    ├── processing.log
    └── errors.log
```

### 2. **Data File Naming Conventions**

```python
# Standardized naming pattern
def generate_filename(data_type: str, resolution: int, date: str, region: str = None) -> str:
    """
    Generate standardized filename for H3 data files.
    
    Pattern: {data_type}_h3r{resolution}_{date}_{region}.{ext}
    Example: temperature_h3r9_20250126_stlouis.csv
    """
    base_name = f"{data_type}_h3r{resolution}_{date}"
    if region:
        base_name += f"_{region}"
    return base_name

# Examples:
# temperature_h3r9_20250126_stlouis.csv
# traffic_h3r8_20250126_midwest.parquet  
# demographics_h3r7_20250126_usa.json
# pollution_h3r10_20250126_downtown.geojson
```

### 3. **Configuration Management**

```python
# config/database.json
{
    "mysql": {
        "host": "localhost",
        "port": 3306,
        "database": "h3_spatial_data",
        "user": "h3_user",
        "password": "${MYSQL_PASSWORD}",
        "charset": "utf8mb4",
        "pool_size": 20,
        "max_overflow": 50
    },
    "h3": {
        "default_resolution": 9,
        "batch_size": 10000,
        "cache_enabled": true,
        "cache_ttl": 3600
    }
}

# config/data_sources.json
{
    "temperature_sensors": {
        "file_pattern": "temperature_*.csv",
        "lat_column": "latitude",
        "lng_column": "longitude", 
        "value_column": "temp_fahrenheit",
        "resolution": 9,
        "category": "environmental"
    },
    "traffic_data": {
        "file_pattern": "traffic_*.json",
        "coordinate_field": "coordinates",
        "value_field": "vehicle_count",
        "resolution": 8,
        "category": "transportation"
    }
}
```

## Large Dataset Processing Strategies

### 1. **Batch Processing Pipeline**

```python
import pandas as pd
import h3
from typing import Iterator
import mysql.connector
from pathlib import Path

class H3BatchProcessor:
    def __init__(self, mysql_config: dict, batch_size: int = 10000):
        self.mysql_config = mysql_config
        self.batch_size = batch_size
        self.connection = mysql.connector.connect(**mysql_config)
    
    def process_large_csv(self, file_path: str, resolution: int = 9) -> None:
        """Process large CSV files in batches."""
        
        # Read in chunks to handle large files
        chunk_iterator = pd.read_csv(file_path, chunksize=self.batch_size)
        
        for chunk_idx, chunk in enumerate(chunk_iterator):
            print(f"Processing chunk {chunk_idx + 1}...")
            
            # Convert to H3 indices
            h3_data = []
            for _, row in chunk.iterrows():
                try:
                    h3_index = h3.latlng_to_cell(row['lat'], row['lng'], resolution)
                    h3_data.append({
                        'h3_index': int(h3_index, 16),  # Convert hex to int
                        'resolution': resolution,
                        'lat': row['lat'],
                        'lng': row['lng'],
                        'value': row['value'],
                        'category': row.get('category', 'unknown')
                    })
                except Exception as e:
                    print(f"Error processing row: {e}")
                    continue
            
            # Batch insert to MySQL
            self._batch_insert(h3_data)
            
    def _batch_insert(self, data: list) -> None:
        """Insert data in batches to MySQL."""
        cursor = self.connection.cursor()
        
        insert_query = """
        INSERT IGNORE INTO h3_points 
        (h3_index, resolution, original_lat, original_lng, value, category)
        VALUES (%(h3_index)s, %(resolution)s, %(lat)s, %(lng)s, %(value)s, %(category)s)
        """
        
        cursor.executemany(insert_query, data)
        self.connection.commit()
        cursor.close()
```

### 2. **Multi-Resolution Pre-computation**

```python
def precompute_aggregations(mysql_config: dict, resolutions: list = [6, 7, 8, 9]):
    """Pre-compute aggregations at multiple resolutions."""
    
    connection = mysql.connector.connect(**mysql_config)
    cursor = connection.cursor()
    
    for target_resolution in resolutions:
        print(f"Computing aggregations for resolution {target_resolution}...")
        
        # Aggregate from higher resolution data
        query = f"""
        INSERT INTO h3_aggregated (h3_index, resolution, data_type, count_total, avg_value, sum_value, min_value, max_value)
        SELECT 
            CONV(SUBSTR(HEX(h3_index), 1, {16 - (15 - target_resolution)}), 16, 10) as parent_h3,
            {target_resolution} as resolution,
            category as data_type,
            COUNT(*) as count_total,
            AVG(value) as avg_value,
            SUM(value) as sum_value,
            MIN(value) as min_value,
            MAX(value) as max_value
        FROM h3_points 
        WHERE resolution > {target_resolution}
        GROUP BY parent_h3, category
        ON DUPLICATE KEY UPDATE
            count_total = VALUES(count_total),
            avg_value = VALUES(avg_value),
            sum_value = VALUES(sum_value),
            min_value = VALUES(min_value),
            max_value = VALUES(max_value);
        """
        
        cursor.execute(query)
        connection.commit()
    
    cursor.close()
    connection.close()
```

### 3. **Spatial Indexing Optimization**

```python
def build_spatial_indices(mysql_config: dict):
    """Build spatial relationship tables for fast queries."""
    
    connection = mysql.connector.connect(**mysql_config)
    cursor = connection.cursor()
    
    # Build hierarchy table
    print("Building H3 hierarchy relationships...")
    hierarchy_query = """
    INSERT IGNORE INTO h3_hierarchy (child_h3, parent_h3, child_resolution, parent_resolution)
    SELECT DISTINCT
        h3_index as child_h3,
        CONV(SUBSTR(HEX(h3_index), 1, 14), 16, 10) as parent_h3,  -- Resolution 8
        resolution as child_resolution,
        8 as parent_resolution
    FROM h3_points 
    WHERE resolution = 9;
    """
    cursor.execute(hierarchy_query)
    
    # Build neighbors table (this would use H3 Python library)
    print("Building neighbor relationships...")
    # This requires Python H3 library integration with MySQL
    
    connection.commit()
    cursor.close()
    connection.close()
```

## Performance Optimization Tips

### 1. **MySQL Configuration**
```sql
-- Add to my.cnf for H3 workloads
[mysqld]
innodb_buffer_pool_size = 4G        # 70-80% of available RAM
innodb_log_file_size = 1G           # Large for batch inserts
innodb_flush_log_at_trx_commit = 2  # Better performance for bulk loads
query_cache_size = 256M             # Cache frequent spatial queries
tmp_table_size = 512M               # Handle large GROUP BY operations
max_heap_table_size = 512M
```

### 2. **Indexing Strategy**
- **Primary indices**: H3 index + resolution
- **Spatial indices**: Use MySQL 8.0+ spatial data types when possible
- **Composite indices**: Resolution + category for filtered queries
- **Partitioning**: Partition by resolution or date ranges

### 3. **Query Optimization**
```sql
-- Use resolution-specific queries
SELECT * FROM h3_points WHERE resolution = 9 AND h3_index BETWEEN ? AND ?;

-- Leverage pre-computed aggregations
SELECT * FROM h3_aggregated WHERE resolution = 7 AND data_type = 'temperature';

-- Use spatial hierarchy for region queries
SELECT p.* FROM h3_points p
JOIN h3_hierarchy h ON p.h3_index = h.child_h3
WHERE h.parent_h3 = ? AND h.parent_resolution = 6;
```

This comprehensive approach allows you to efficiently store, query, and analyze massive datasets using H3 with MySQL as your primary database engine.