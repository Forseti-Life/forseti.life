# Database Schema - amIsafe Crime Dashboard
## MySQL 8.0 Database Design

### Overview
The amIsafe database is designed to efficiently store, index, and query large-scale crime incident data using H3 hexagonal spatial indexing. The schema supports multi-resolution spatial aggregation, temporal analysis, and real-time data updates.

### Database Configuration
```sql
-- Database: amisafe
-- Engine: InnoDB (default)
-- Character Set: utf8mb4
-- Collation: utf8mb4_unicode_ci
-- MySQL Version: 8.0+
```

## Core Tables

### 1. raw_incidents
Primary table storing individual crime incidents with H3 spatial indexing.

```sql
CREATE TABLE raw_incidents (
    -- Primary identification
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    source_file VARCHAR(255) NOT NULL,
    
    -- External references
    cartodb_id VARCHAR(50),
    objectid VARCHAR(50),
    dc_key VARCHAR(50),
    
    -- Spatial data
    lat DECIMAL(10, 7) NOT NULL,
    lng DECIMAL(11, 7) NOT NULL,
    point_x DOUBLE,
    point_y DOUBLE,
    h3_index VARCHAR(16) NOT NULL,
    h3_resolution TINYINT DEFAULT 9,
    
    -- Administrative boundaries
    dc_dist VARCHAR(10),
    psa VARCHAR(10),
    
    -- Temporal data
    dispatch_date_time DATETIME NOT NULL,
    dispatch_date DATE NOT NULL,
    dispatch_time TIME,
    hour TINYINT,
    day_of_week TINYINT,
    week_of_year TINYINT,
    month TINYINT,
    year SMALLINT,
    
    -- Crime classification
    ucr_general VARCHAR(10) NOT NULL,
    text_general_code VARCHAR(255),
    crime_category_id INT,
    severity_level TINYINT,
    
    -- Location details
    location_block TEXT,
    
    -- Additional metadata
    properties JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_h3_index (h3_index),
    INDEX idx_h3_datetime (h3_index, dispatch_date_time),
    INDEX idx_spatial (lat, lng),
    INDEX idx_temporal (dispatch_date_time),
    INDEX idx_crime_type (ucr_general),
    INDEX idx_district (dc_dist),
    INDEX idx_composite_main (h3_index, ucr_general, dispatch_date),
    INDEX idx_hour_analysis (hour, day_of_week),
    
    -- Spatial indexes (MySQL 8.0)
    SPATIAL INDEX idx_point (point_x, point_y),
    
    -- Full-text search
    FULLTEXT INDEX idx_location_search (location_block, text_general_code)
) ENGINE=InnoDB;
```

**Current Status:** ✅ 109,553 records loaded and validated
**Estimated Full Size:** 2.5M+ records from 20 CSV files
**Storage Size:** ~500MB (estimated full dataset)

### 2. h3_aggregated
Pre-computed spatial aggregations at multiple H3 resolutions.

```sql
CREATE TABLE h3_aggregated (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    
    -- H3 spatial identification
    h3_index VARCHAR(16) NOT NULL,
    h3_resolution TINYINT NOT NULL,
    h3_parent VARCHAR(16),
    h3_children JSON,
    
    -- Temporal grouping
    time_period ENUM('hourly', 'daily', 'weekly', 'monthly', 'yearly') NOT NULL,
    period_start DATETIME NOT NULL,
    period_end DATETIME NOT NULL,
    
    -- Crime categorization
    crime_type VARCHAR(10),
    all_crimes BOOLEAN DEFAULT FALSE,
    
    -- Aggregated statistics
    total_incidents INT NOT NULL DEFAULT 0,
    unique_incident_types INT DEFAULT 0,
    severity_average DECIMAL(3,2),
    severity_total INT,
    
    -- Temporal patterns
    incidents_per_hour JSON,
    incidents_per_day JSON,
    peak_hour TINYINT,
    peak_day TINYINT,
    
    -- Trend analysis
    trend_direction ENUM('increasing', 'decreasing', 'stable', 'unknown') DEFAULT 'unknown',
    trend_percentage DECIMAL(5,2),
    previous_period_incidents INT,
    
    -- Geographic context
    center_lat DECIMAL(10, 7),
    center_lng DECIMAL(11, 7),
    area_km2 DECIMAL(10, 6),
    population_estimate INT,
    density_per_km2 DECIMAL(8, 2),
    
    -- Metadata
    last_incident_date DATETIME,
    first_incident_date DATETIME,
    data_quality_score DECIMAL(3,2) DEFAULT 1.00,
    
    -- Timestamps
    computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    
    -- Indexes
    PRIMARY KEY (id),
    UNIQUE KEY unique_aggregation (h3_index, h3_resolution, time_period, period_start, crime_type),
    INDEX idx_h3_resolution (h3_index, h3_resolution),
    INDEX idx_temporal (period_start, period_end),
    INDEX idx_crime_stats (crime_type, total_incidents DESC),
    INDEX idx_trends (trend_direction, trend_percentage),
    INDEX idx_density (density_per_km2 DESC),
    INDEX idx_expiration (expires_at)
) ENGINE=InnoDB;
```

### 3. crime_categories
Master reference table for crime type definitions.

```sql
CREATE TABLE crime_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Crime classification
    ucr_code VARCHAR(10) UNIQUE NOT NULL,
    ucr_description VARCHAR(255) NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    parent_category VARCHAR(100),
    
    -- Hierarchy and grouping
    category_level TINYINT DEFAULT 1,
    category_path VARCHAR(255),
    
    -- Severity and risk assessment
    severity_level TINYINT NOT NULL DEFAULT 3 COMMENT '1=Low, 5=High',
    risk_score DECIMAL(3,2) DEFAULT 3.00,
    
    -- Visualization properties
    color_hex VARCHAR(7) NOT NULL DEFAULT '#808080',
    color_rgb VARCHAR(20),
    icon_class VARCHAR(50) DEFAULT 'fas fa-exclamation-circle',
    symbol_code VARCHAR(10),
    
    -- Statistical metadata
    total_incidents INT DEFAULT 0,
    percentage_of_total DECIMAL(5,2) DEFAULT 0.00,
    average_per_day DECIMAL(6,2) DEFAULT 0.00,
    
    -- Temporal patterns
    peak_hours JSON COMMENT 'Array of most common hours',
    seasonal_pattern JSON COMMENT 'Monthly distribution',
    
    -- Geographic patterns
    common_districts JSON COMMENT 'Top districts for this crime type',
    hotspot_h3_cells JSON COMMENT 'High-density H3 cells',
    
    -- Metadata
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Indexes
    INDEX idx_ucr_code (ucr_code),
    INDEX idx_category (category_name),
    INDEX idx_severity (severity_level),
    INDEX idx_parent (parent_category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;
```

### 4. police_districts
Geographic boundaries and metadata for Philadelphia police districts.

```sql
CREATE TABLE police_districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- District identification
    district_code VARCHAR(10) UNIQUE NOT NULL,
    district_name VARCHAR(100) NOT NULL,
    district_type ENUM('district', 'special', 'transit') DEFAULT 'district',
    
    -- Geographic data
    boundary_geojson JSON NOT NULL,
    center_lat DECIMAL(10, 7),
    center_lng DECIMAL(11, 7),
    area_km2 DECIMAL(8, 3),
    
    -- H3 coverage
    h3_cells_resolution_6 JSON,
    h3_cells_resolution_7 JSON,
    h3_cells_resolution_8 JSON,
    h3_cells_resolution_9 JSON,
    primary_h3_cell VARCHAR(16),
    
    -- Demographics and context
    population_estimate INT,
    area_type ENUM('urban', 'suburban', 'mixed') DEFAULT 'urban',
    socioeconomic_index DECIMAL(3,2),
    
    -- Crime statistics
    total_incidents INT DEFAULT 0,
    incidents_per_capita DECIMAL(8, 4),
    crime_rate_rank TINYINT,
    dominant_crime_type VARCHAR(10),
    
    -- Operational data
    station_address TEXT,
    phone_number VARCHAR(20),
    commander_name VARCHAR(100),
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Indexes
    INDEX idx_district_code (district_code),
    INDEX idx_crime_stats (total_incidents DESC),
    INDEX idx_location (center_lat, center_lng),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;
```

### 5. h3_grid_cache
Cache table for H3 grid boundaries to optimize map rendering.

```sql
CREATE TABLE h3_grid_cache (
    h3_index VARCHAR(16) PRIMARY KEY,
    h3_resolution TINYINT NOT NULL,
    
    -- Geometric data
    center_lat DECIMAL(10, 7) NOT NULL,
    center_lng DECIMAL(11, 7) NOT NULL,
    boundary_geojson JSON NOT NULL,
    area_km2 DECIMAL(10, 6),
    
    -- Hierarchical relationships
    parent_h3 VARCHAR(16),
    children_h3 JSON,
    neighbor_h3 JSON,
    
    -- Administrative context
    district_code VARCHAR(10),
    neighborhood VARCHAR(100),
    postal_code VARCHAR(10),
    
    -- Cache metadata
    cache_hit_count INT DEFAULT 0,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    
    -- Indexes
    INDEX idx_resolution (h3_resolution),
    INDEX idx_parent (parent_h3),
    INDEX idx_district (district_code),
    INDEX idx_expiration (expires_at),
    INDEX idx_location (center_lat, center_lng)
) ENGINE=InnoDB;
```

### 6. spatial_hotspots
Dynamically computed crime hotspots using clustering algorithms.

```sql
CREATE TABLE spatial_hotspots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Hotspot identification
    hotspot_id VARCHAR(50) UNIQUE NOT NULL,
    algorithm_used ENUM('dbscan', 'kmeans', 'hierarchical') NOT NULL,
    
    -- Spatial definition
    center_h3 VARCHAR(16) NOT NULL,
    center_lat DECIMAL(10, 7) NOT NULL,
    center_lng DECIMAL(11, 7) NOT NULL,
    radius_meters INT NOT NULL,
    boundary_h3_cells JSON NOT NULL,
    
    -- Hotspot characteristics
    total_incidents INT NOT NULL,
    incident_density DECIMAL(8, 4) NOT NULL,
    risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    confidence_score DECIMAL(3, 2) DEFAULT 0.80,
    
    -- Temporal patterns
    time_period_start DATETIME NOT NULL,
    time_period_end DATETIME NOT NULL,
    peak_hours JSON,
    active_days JSON,
    
    -- Crime composition
    primary_crime_types JSON NOT NULL,
    crime_diversity_index DECIMAL(3, 2),
    severity_average DECIMAL(3, 2),
    
    -- Clustering metrics
    cluster_cohesion DECIMAL(3, 2),
    cluster_separation DECIMAL(3, 2),
    statistical_significance DECIMAL(6, 4),
    
    -- Metadata
    computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    computation_time_ms INT,
    data_quality_score DECIMAL(3, 2) DEFAULT 1.00,
    
    -- Indexes
    INDEX idx_center_h3 (center_h3),
    INDEX idx_risk_level (risk_level, incident_density DESC),
    INDEX idx_temporal (time_period_start, time_period_end),
    INDEX idx_expiration (expires_at),
    INDEX idx_location (center_lat, center_lng),
    INDEX idx_algorithm (algorithm_used, confidence_score DESC)
) ENGINE=InnoDB;
```

### 7. data_quality_metrics
Track data quality and processing statistics.

```sql
CREATE TABLE data_quality_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Processing batch information
    batch_id VARCHAR(50) NOT NULL,
    source_file VARCHAR(255),
    processing_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Data volume metrics
    total_rows_processed INT NOT NULL,
    successful_inserts INT NOT NULL,
    failed_inserts INT DEFAULT 0,
    duplicate_records INT DEFAULT 0,
    
    -- Quality indicators
    coordinate_accuracy_score DECIMAL(3, 2),
    temporal_completeness DECIMAL(3, 2),
    categorical_consistency DECIMAL(3, 2),
    overall_quality_score DECIMAL(3, 2),
    
    -- Error tracking
    error_types JSON,
    error_samples JSON,
    validation_warnings JSON,
    
    -- Processing performance
    processing_time_seconds INT,
    memory_usage_mb DECIMAL(8, 2),
    cpu_utilization DECIMAL(5, 2),
    
    -- H3 indexing metrics
    h3_index_success_rate DECIMAL(5, 2),
    h3_resolution_distribution JSON,
    spatial_validation_errors INT DEFAULT 0,
    
    -- Indexes
    INDEX idx_batch_id (batch_id),
    INDEX idx_processing_date (processing_date),
    INDEX idx_quality_score (overall_quality_score DESC),
    INDEX idx_source_file (source_file)
) ENGINE=InnoDB;
```

## Views and Computed Tables

### 1. v_incident_summary
Real-time view of incident statistics by district and crime type.

```sql
CREATE VIEW v_incident_summary AS
SELECT 
    dc_dist as district,
    ucr_general as crime_type,
    COUNT(*) as total_incidents,
    COUNT(DISTINCT DATE(dispatch_date_time)) as active_days,
    MIN(dispatch_date_time) as first_incident,
    MAX(dispatch_date_time) as last_incident,
    AVG(severity_level) as avg_severity,
    COUNT(DISTINCT h3_index) as unique_h3_cells,
    ROUND(COUNT(*) / COUNT(DISTINCT DATE(dispatch_date_time)), 2) as incidents_per_day
FROM raw_incidents 
WHERE dispatch_date_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY dc_dist, ucr_general
ORDER BY total_incidents DESC;
```

### 2. v_h3_density_map
Spatial density view for map visualization.

```sql
CREATE VIEW v_h3_density_map AS
SELECT 
    h3_index,
    h3_resolution,
    COUNT(*) as incident_count,
    COUNT(DISTINCT ucr_general) as crime_type_diversity,
    AVG(severity_level) as avg_severity,
    MIN(dispatch_date_time) as period_start,
    MAX(dispatch_date_time) as period_end,
    COUNT(*) / DATEDIFF(MAX(dispatch_date), MIN(dispatch_date)) as incidents_per_day,
    GROUP_CONCAT(DISTINCT ucr_general ORDER BY ucr_general) as crime_types
FROM raw_incidents 
WHERE dispatch_date_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY h3_index, h3_resolution
HAVING incident_count >= 3
ORDER BY incident_count DESC;
```

### 3. v_temporal_patterns
Temporal analysis view for time-series visualization.

```sql
CREATE VIEW v_temporal_patterns AS
SELECT 
    DATE(dispatch_date_time) as incident_date,
    hour,
    day_of_week,
    ucr_general,
    dc_dist,
    COUNT(*) as incident_count,
    COUNT(DISTINCT h3_index) as spatial_spread,
    AVG(severity_level) as avg_severity
FROM raw_incidents 
WHERE dispatch_date_time >= DATE_SUB(NOW(), INTERVAL 365 DAY)
GROUP BY DATE(dispatch_date_time), hour, day_of_week, ucr_general, dc_dist
ORDER BY incident_date DESC, hour;
```

## Stored Procedures

### 1. sp_update_h3_aggregations
Update spatial aggregations for specified time period and resolution.

```sql
DELIMITER //
CREATE PROCEDURE sp_update_h3_aggregations(
    IN p_h3_resolution TINYINT,
    IN p_time_period VARCHAR(20),
    IN p_start_date DATETIME,
    IN p_end_date DATETIME
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_h3_index VARCHAR(16);
    DECLARE v_incident_count INT;
    DECLARE cur CURSOR FOR 
        SELECT h3_index, COUNT(*) 
        FROM raw_incidents 
        WHERE h3_resolution = p_h3_resolution 
        AND dispatch_date_time BETWEEN p_start_date AND p_end_date
        GROUP BY h3_index;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Delete existing aggregations for this period
    DELETE FROM h3_aggregated 
    WHERE h3_resolution = p_h3_resolution 
    AND time_period = p_time_period 
    AND period_start = p_start_date;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_h3_index, v_incident_count;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Insert updated aggregation
        INSERT INTO h3_aggregated (
            h3_index, h3_resolution, time_period, 
            period_start, period_end, total_incidents,
            computed_at
        ) VALUES (
            v_h3_index, p_h3_resolution, p_time_period,
            p_start_date, p_end_date, v_incident_count,
            NOW()
        );
    END LOOP;
    
    CLOSE cur;
END //
DELIMITER ;
```

### 2. sp_compute_hotspots
Identify crime hotspots using spatial clustering.

```sql
DELIMITER //
CREATE PROCEDURE sp_compute_hotspots(
    IN p_h3_resolution TINYINT,
    IN p_min_incidents INT,
    IN p_days_back INT
)
BEGIN
    DECLARE v_start_date DATETIME;
    SET v_start_date = DATE_SUB(NOW(), INTERVAL p_days_back DAY);
    
    -- Clear existing hotspots
    DELETE FROM spatial_hotspots 
    WHERE time_period_start >= v_start_date;
    
    -- Insert new hotspots based on incident density
    INSERT INTO spatial_hotspots (
        hotspot_id, algorithm_used, center_h3, center_lat, center_lng,
        radius_meters, total_incidents, incident_density, risk_level,
        time_period_start, time_period_end, computed_at, expires_at
    )
    SELECT 
        CONCAT('hs_', h3_index, '_', UNIX_TIMESTAMP()) as hotspot_id,
        'density_based' as algorithm_used,
        h3_index as center_h3,
        AVG(lat) as center_lat,
        AVG(lng) as center_lng,
        500 as radius_meters,
        COUNT(*) as total_incidents,
        COUNT(*) / 0.737 as incident_density, -- Average H3 cell area at resolution 9
        CASE 
            WHEN COUNT(*) >= 50 THEN 'critical'
            WHEN COUNT(*) >= 25 THEN 'high'
            WHEN COUNT(*) >= 10 THEN 'medium'
            ELSE 'low'
        END as risk_level,
        v_start_date as time_period_start,
        NOW() as time_period_end,
        NOW() as computed_at,
        DATE_ADD(NOW(), INTERVAL 1 HOUR) as expires_at
    FROM raw_incidents 
    WHERE h3_resolution = p_h3_resolution
    AND dispatch_date_time >= v_start_date
    GROUP BY h3_index
    HAVING COUNT(*) >= p_min_incidents
    ORDER BY COUNT(*) DESC;
END //
DELIMITER ;
```

## Database Triggers

### 1. Update Crime Category Statistics
```sql
DELIMITER //
CREATE TRIGGER tr_update_crime_stats 
AFTER INSERT ON raw_incidents
FOR EACH ROW
BEGIN
    INSERT INTO crime_categories (ucr_code, total_incidents, updated_at) 
    VALUES (NEW.ucr_general, 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_incidents = total_incidents + 1,
        updated_at = NOW();
END //
DELIMITER ;
```

### 2. Maintain H3 Grid Cache
```sql
DELIMITER //
CREATE TRIGGER tr_cache_h3_cells 
AFTER INSERT ON raw_incidents
FOR EACH ROW
BEGIN
    INSERT IGNORE INTO h3_grid_cache (h3_index, h3_resolution, center_lat, center_lng)
    VALUES (NEW.h3_index, NEW.h3_resolution, NEW.lat, NEW.lng);
END //
DELIMITER ;
```

## Indexing Strategy

### Primary Indexes
- **Spatial**: H3 index for fast spatial queries
- **Temporal**: DateTime columns for time-series analysis
- **Categorical**: Crime type codes for filtering
- **Composite**: Combined indexes for common query patterns

### Index Maintenance
```sql
-- Analyze table statistics weekly
ANALYZE TABLE raw_incidents;
ANALYZE TABLE h3_aggregated;

-- Optimize tables monthly
OPTIMIZE TABLE raw_incidents;
OPTIMIZE TABLE h3_aggregated;
```

## Performance Optimization

### Partitioning Strategy
```sql
-- Partition raw_incidents by year for better performance
ALTER TABLE raw_incidents
PARTITION BY RANGE (YEAR(dispatch_date_time)) (
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),  
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

### Query Optimization
- Use covering indexes for frequent queries
- Implement query result caching
- Pre-compute common aggregations
- Use appropriate data types for storage efficiency

## Backup and Maintenance

### Daily Backup Strategy
```bash
# Full database backup
mysqldump --single-transaction --routines --triggers amisafe > amisafe_backup_$(date +%Y%m%d).sql

# Incremental backup using binary logs
mysqlbinlog --start-datetime="2025-01-01 00:00:00" mysql-bin.000001 > incremental_backup.sql
```

### Data Retention Policy
- **Raw incidents**: Retain all historical data
- **Aggregations**: Retain for 2 years
- **Hotspots**: Retain for 6 months
- **Cache tables**: Automatically expire based on usage

This comprehensive database schema provides the foundation for efficient storage, retrieval, and analysis of Philadelphia crime data using H3 spatial indexing technology.