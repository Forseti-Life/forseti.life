#!/bin/bash
"""
AmISafe Consolidated Database Setup Script
Creates complete ETL pipeline database structure for St. Louis Integration
Combines all previous setup scripts into one comprehensive solution

FEATURES:
- Raw (Bronze) → Transform (Silver) → Final (Gold) data warehouse layers
- ObjectID-based processing (not CartoDB ID)
- H3 geospatial indexing at multiple resolutions
- UCR crime code reference tables
- Complete sample data for testing
- Production-ready with proper indexing and constraints

USAGE:
    ./setup_consolidated.sh [database_name]
    
If no database name is provided, uses: stlouisintegration_dev
"""

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Database configuration
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_USER="${DB_USER:-drupal_user}"
DB_PASSWORD="${DB_PASSWORD:-drupal_secure_password}"
DB_NAME="${1:-stlouisintegration_dev}"  # Accept database name as parameter

# Logging functions
print_header() {
    echo -e "${CYAN}================================================================${NC}"
    echo -e "${CYAN}$1${NC}"
    echo -e "${CYAN}================================================================${NC}"
}

print_section() {
    echo -e "${BLUE}>>> $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${PURPLE}ℹ️  $1${NC}"
}

# SQL execution with error handling
execute_sql() {
    local sql_command="$1"
    local description="$2"
    local suppress_output="${3:-false}"
    
    if [[ "$suppress_output" == "true" ]]; then
        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "$sql_command" >/dev/null 2>&1; then
            return 0
        else
            print_error "Failed: $description"
            return 1
        fi
    else
        print_info "$description"
        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "$sql_command" 2>/dev/null; then
            print_success "$description completed"
            return 0
        else
            print_error "Failed: $description"
            return 1
        fi
    fi
}

# Check system prerequisites
check_prerequisites() {
    print_section "Checking Prerequisites"
    
    # Check if MySQL is running
    if ! pgrep -x mysqld > /dev/null; then
        print_error "MySQL is not running. Please start MySQL first."
        print_info "Run: sudo service mysql start"
        exit 1
    fi
    print_success "MySQL service is running"
    
    # Test database connection
    if ! mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1;" >/dev/null 2>&1; then
        print_error "Cannot connect to MySQL database"
        print_info "Please check credentials:"
        print_info "  Host: $DB_HOST"
        print_info "  User: $DB_USER"
        exit 1
    fi
    print_success "Database connection verified"
}

# Create database if it doesn't exist
setup_database() {
    print_section "Database Setup"
    
    # Check if database exists
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "USE $DB_NAME;" >/dev/null 2>&1; then
        print_warning "Database '$DB_NAME' already exists"
    else
        print_info "Creating database: $DB_NAME"
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        print_success "Database '$DB_NAME' created"
    fi
}

# Create Raw Layer (Bronze) table
create_raw_layer_table() {
    print_section "Creating Raw Layer (Bronze) - amisafe_raw_incidents"
    
    local sql="
    CREATE TABLE IF NOT EXISTS amisafe_raw_incidents (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        
        -- Source tracking for data lineage
        source_file VARCHAR(255) NOT NULL DEFAULT 'consolidated_import',
        ingested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- ALL original CSV fields preserved exactly as-is
        the_geom TEXT,
        cartodb_id INT,                        -- Legacy CartoDB ID (75% NULL values)
        the_geom_webmercator TEXT,
        objectid BIGINT,                       -- PRIMARY business identifier (100% coverage)
        dc_dist VARCHAR(10),
        psa VARCHAR(10),
        dispatch_date_time VARCHAR(50),        -- Keep as string to preserve original format
        dispatch_date VARCHAR(20),             -- Keep as string initially
        dispatch_time VARCHAR(20),             -- Keep as string initially
        hour VARCHAR(10),                      -- Keep as string initially
        dc_key VARCHAR(50),
        location_block TEXT,
        ucr_general VARCHAR(10),
        text_general_code VARCHAR(255),
        point_x VARCHAR(30),                   -- Keep as string to preserve precision
        point_y VARCHAR(30),                   -- Keep as string to preserve precision
        lat VARCHAR(30),                       -- Keep as string to preserve precision
        lng VARCHAR(30),                       -- Keep as string to preserve precision
        
        -- Processing status tracking for ETL pipeline
        processing_status ENUM('raw', 'processing', 'processed', 'excluded') DEFAULT 'raw',
        
        -- Optimized indexing for objectid-based processing
        UNIQUE KEY unique_raw_objectid (objectid),  -- Primary business identifier
        INDEX idx_source_file (source_file),
        INDEX idx_ingested_at (ingested_at),
        INDEX idx_cartodb_id (cartodb_id),           -- Legacy support
        INDEX idx_processing_status (processing_status),
        INDEX idx_dc_dist (dc_dist),
        INDEX idx_dispatch_date (dispatch_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
    COMMENT='Raw Layer (Bronze): Immutable source data with objectid as primary business key (3.4M records)';
    "
    
    execute_sql "$sql" "Raw incidents table creation"
}

# Create Transform Layer (Silver) table
create_transform_layer_table() {
    print_section "Creating Transform Layer (Silver) - amisafe_clean_incidents"
    
    local sql="
    CREATE TABLE IF NOT EXISTS amisafe_clean_incidents (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        
        -- Data lineage
        raw_incident_ids JSON,                 -- Reference to source raw records
        processing_batch_id VARCHAR(50),       -- Processing batch tracking
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- Validated business fields (objectid-based processing)
        incident_id VARCHAR(50) UNIQUE,        -- Master incident identifier: obj_{objectid}
        cartodb_id INT,                        -- Legacy CartoDB ID (may be NULL)
        objectid BIGINT NOT NULL,              -- Primary incident identifier (unique for all records)
        dc_key VARCHAR(50),                    -- Validated dispatch key
        
        -- Cleaned location data
        dc_dist VARCHAR(10) NOT NULL,          -- Validated district (1-35)
        psa VARCHAR(10),                       -- Police service area
        location_block VARCHAR(500),           -- Normalized address
        lat DECIMAL(10,7) NOT NULL,            -- Validated latitude
        lng DECIMAL(11,7) NOT NULL,            -- Validated longitude
        coordinate_quality ENUM('HIGH', 'MEDIUM', 'LOW') DEFAULT 'MEDIUM',
        
        -- Normalized temporal data
        incident_datetime DATETIME NOT NULL,   -- Standardized timestamp
        incident_date DATE NOT NULL,           -- Date component
        incident_hour TINYINT NOT NULL,        -- Hour (0-23)
        incident_month TINYINT NOT NULL,       -- Month (1-12)
        incident_year SMALLINT NOT NULL,       -- Year
        day_of_week TINYINT,                   -- Day of week (1=Monday)
        
        -- Crime classification
        ucr_general VARCHAR(10) NOT NULL,      -- Validated UCR code
        crime_category VARCHAR(50),            -- Standardized category
        crime_description VARCHAR(255),        -- Cleaned description
        severity_level TINYINT DEFAULT 3,      -- Calculated severity (1-5)
        
        -- H3 spatial indexing (multiple resolutions 5-13)
        h3_res_5 VARCHAR(16),                  -- Metro regions (~251km²)
        h3_res_6 VARCHAR(16),                  -- Districts (~36km²)
        h3_res_7 VARCHAR(16),                  -- Neighborhoods (~5.2km²)
        h3_res_8 VARCHAR(16),                  -- Areas (~0.7km²)
        h3_res_9 VARCHAR(16),                  -- Blocks (~0.1km²)
        h3_res_10 VARCHAR(16),                 -- Sub-blocks (~15,047m²)
        h3_res_11 VARCHAR(16),                 -- Building groups (~2,150m²)
        h3_res_12 VARCHAR(16),                 -- Buildings (~307m²)
        h3_res_13 VARCHAR(16),                 -- Precise locations (~44m²)
        
        -- Quality and governance (simplified for objectid processing)
        data_quality_score DECIMAL(3,2) DEFAULT 0.85,
        duplicate_group_id VARCHAR(50),        -- Not used (objectid is unique)
        is_duplicate BOOLEAN DEFAULT FALSE,    -- Always FALSE (objectid is unique)
        is_valid BOOLEAN DEFAULT TRUE,         -- Data validation flag
        
        -- Optimized indexes for analytics and objectid processing
        UNIQUE KEY unique_incident (incident_id),
        UNIQUE KEY unique_objectid (objectid),  -- Primary business key constraint
        INDEX idx_location (lat, lng),
        INDEX idx_h3_res5 (h3_res_5),
        INDEX idx_h3_res6 (h3_res_6),
        INDEX idx_h3_res7 (h3_res_7),
        INDEX idx_h3_res8 (h3_res_8),
        INDEX idx_h3_res9 (h3_res_9),
        INDEX idx_h3_res10 (h3_res_10),
        INDEX idx_h3_res11 (h3_res_11),
        INDEX idx_h3_res12 (h3_res_12),
        INDEX idx_h3_res13 (h3_res_13),
        INDEX idx_datetime (incident_datetime),
        INDEX idx_district (dc_dist),
        INDEX idx_crime_type (ucr_general),
        INDEX idx_quality (data_quality_score),
        INDEX idx_batch (processing_batch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Transform Layer (Silver): Cleaned, validated incidents using objectid as primary key';
    "
    
    execute_sql "$sql" "Clean incidents table creation"
}

# Create Final Layer (Gold) table
create_final_layer_table() {
    print_section "Creating Final Layer (Gold) - amisafe_h3_aggregated"
    
    local sql="
    CREATE TABLE IF NOT EXISTS amisafe_h3_aggregated (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        
        -- H3 spatial identifier (AGGREGATOR REQUIRED FIELDS)
        h3_index VARCHAR(16) NOT NULL,         -- H3 hexagon identifier
        h3_resolution TINYINT NOT NULL,        -- Resolution level (5-13)
        
        -- Core aggregated metrics (AGGREGATOR REQUIRED FIELDS)
        incident_count INT DEFAULT 0,          -- Total incidents in hexagon
        unique_incident_types INT DEFAULT 0,   -- Count of distinct UCR codes (was unique_incidents)
        
        -- Temporal data (AGGREGATOR REQUIRED FIELDS)
        earliest_incident DATETIME,            -- Earliest incident timestamp
        latest_incident DATETIME,              -- Latest incident timestamp  
        incidents_last_30_days INT DEFAULT 0,  -- Recent activity count
        incidents_last_year INT DEFAULT 0,     -- Annual activity count
        
        -- Geospatial data (AGGREGATOR REQUIRED FIELDS)
        center_latitude DECIMAL(10, 7),        -- Hexagon center latitude (was center_lat)
        center_longitude DECIMAL(11, 7),       -- Hexagon center longitude (was center_lng)
        
        -- JSON analytics (AGGREGATOR REQUIRED FIELDS)
        incident_type_counts JSON,             -- UCR code distribution (was crime_types)
        district_counts JSON,                  -- Police district distribution (was district_list)
        
        -- Processing metadata (AGGREGATOR REQUIRED FIELDS)
        total_valid_records INT DEFAULT 0,     -- Total processed records
        last_aggregation TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- (was last_updated)
        
        -- Enhanced analytics (OPTIONAL FIELDS for richer reporting)
        incident_ids JSON,                     -- Array of incident IDs (for H3:13 granular queries)
        severity_avg DECIMAL(4,2),             -- Average severity (1.00-5.00)
        severity_max TINYINT,                  -- Maximum severity in hex
        data_quality_avg DECIMAL(3,2),         -- Average data quality
        top_crime_type VARCHAR(10),            -- Most frequent UCR code
        crime_diversity_index DECIMAL(3,2),    -- Simpson's diversity index
        
        -- Temporal patterns (ENHANCED ANALYTICS)
        incidents_by_hour JSON,                -- Hourly distribution [24 values]
        incidents_by_dow JSON,                 -- Day of week [7 values]
        incidents_by_month JSON,               -- Monthly distribution [12 values]
        peak_hour TINYINT,                     -- Hour with most incidents
        peak_dow TINYINT,                      -- Day with most incidents
        
        -- Extended geospatial (ENHANCED ANALYTICS)
        h3_parent VARCHAR(16),                 -- Parent hexagon (for hierarchical queries)
        boundary_geojson JSON,                 -- Hexagon boundary coordinates
        
        -- Date range coverage (ENHANCED ANALYTICS)
        date_range_start DATE,                 -- Earliest incident date
        date_range_end DATE,                   -- Latest incident date
        data_freshness_days INT,               -- Days since last incident
        
        -- Cache control and metadata (ENHANCED ANALYTICS)
        is_empty BOOLEAN DEFAULT FALSE,        -- True if no incidents
        aggregation_batch_id VARCHAR(50),      -- Processing batch reference
        
        -- Performance indexes optimized for aggregator queries and H3 hierarchical access
        UNIQUE KEY unique_h3_resolution (h3_index, h3_resolution),
        INDEX idx_resolution (h3_resolution),
        INDEX idx_incident_count (incident_count),
        INDEX idx_center (center_latitude, center_longitude),
        INDEX idx_temporal (earliest_incident, latest_incident),
        INDEX idx_recent_activity (incidents_last_30_days, incidents_last_year),
        INDEX idx_aggregation_time (last_aggregation),
        INDEX idx_parent_child (h3_parent, h3_index),
        INDEX idx_severity (severity_avg),
        INDEX idx_empty_filter (is_empty, incident_count),
        INDEX idx_resolution_count (h3_resolution, incident_count)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Final Layer (Gold): H3 aggregated analytics with full aggregator compatibility and enhanced reporting';
    "
    
    execute_sql "$sql" "H3 aggregated analytics table creation"
}

# Create UCR crime codes reference table
create_ucr_reference_table() {
    print_section "Creating UCR Crime Codes Reference Table"
    
    local sql="
    CREATE TABLE IF NOT EXISTS amisafe_ucr_codes (
        ucr_code VARCHAR(10) PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        description VARCHAR(255) NOT NULL,
        severity_level TINYINT NOT NULL DEFAULT 3,
        color_hex VARCHAR(7) DEFAULT '#666666',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_category (category),
        INDEX idx_severity (severity_level),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='UCR crime code reference table for standardization and categorization';
    "
    
    execute_sql "$sql" "UCR codes reference table creation"
}

# Insert UCR crime code reference data
insert_ucr_reference_data() {
    print_section "Inserting UCR Crime Code Reference Data"
    
    local sql="
    INSERT IGNORE INTO amisafe_ucr_codes (ucr_code, category, description, severity_level, color_hex) VALUES
    ('100', 'Violent Crime', 'Homicide', 5, '#8B0000'),
    ('200', 'Violent Crime', 'Rape', 4, '#CD5C5C'),
    ('300', 'Violent Crime', 'Robbery', 4, '#DC143C'),
    ('400', 'Violent Crime', 'Aggravated Assault', 3, '#FF6347'),
    ('500', 'Property Crime', 'Burglary', 2, '#FF8C00'),
    ('600', 'Property Crime', 'Theft', 2, '#FFA500'),
    ('700', 'Property Crime', 'Motor Vehicle Theft', 2, '#FFD700'),
    ('800', 'Quality of Life', 'Other Offenses', 1, '#9ACD32'),
    ('900', 'Quality of Life', 'Public Order', 1, '#32CD32'),
    ('1000', 'Property Crime', 'Fraud', 2, '#FF69B4'),
    ('1100', 'Property Crime', 'Fraud', 2, '#DA70D6'),
    ('1200', 'Quality of Life', 'Vice', 1, '#BA55D3'),
    ('1300', 'Property Crime', 'Vandalism', 1, '#20B2AA'),
    ('1400', 'Quality of Life', 'Drug Offense', 2, '#48D1CC'),
    ('1500', 'Traffic', 'Traffic Violation', 1, '#87CEEB');
    "
    
    execute_sql "$sql" "UCR reference data insertion"
}

# Insert comprehensive sample data for testing
insert_sample_data() {
    print_section "Inserting Sample Data for Testing"
    
    # Sample raw incidents data (objectid-based)
    print_info "Inserting sample raw incidents..."
    local raw_sql="
    INSERT IGNORE INTO amisafe_raw_incidents 
    (objectid, cartodb_id, dc_dist, dispatch_date_time, dispatch_date, hour, dc_key, location_block, ucr_general, text_general_code, lat, lng, source_file)
    VALUES
    -- Center City incidents (objectid: 1000000+)
    (1000001, 500001, '6', '2025-10-30 14:30:00', '2025-10-30', '14', 'PHL_CC_001', '1500 BLOCK MARKET ST', '400', 'THEFT', '39.9526', '-75.1652', 'sample_consolidated'),
    (1000002, 500002, '6', '2025-10-30 16:45:00', '2025-10-30', '16', 'PHL_CC_002', '1600 BLOCK WALNUT ST', '200', 'BURGLARY', '39.9500', '-75.1667', 'sample_consolidated'),
    (1000003, NULL, '6', '2025-10-30 09:15:00', '2025-10-30', '9', 'PHL_CC_003', '1700 BLOCK CHESTNUT ST', '100', 'HOMICIDE', '39.9480', '-75.1635', 'sample_consolidated'),
    
    -- North Philadelphia incidents (objectid: 2000000+)
    (2000001, 500003, '22', '2025-10-30 22:30:00', '2025-10-30', '22', 'PHL_NP_001', '2800 BLOCK N BROAD ST', '300', 'ASSAULT', '39.9950', '-75.1450', 'sample_consolidated'),
    (2000002, NULL, '22', '2025-10-30 01:45:00', '2025-10-30', '1', 'PHL_NP_002', '3200 BLOCK N 5TH ST', '400', 'THEFT', '40.0100', '-75.1300', 'sample_consolidated'),
    (2000003, 500004, '22', '2025-10-30 18:20:00', '2025-10-30', '18', 'PHL_NP_003', '2600 BLOCK N BROAD ST', '1400', 'DRUG OFFENSE', '39.9850', '-75.1500', 'sample_consolidated'),
    
    -- South Philadelphia incidents (objectid: 3000000+)
    (3000001, 500005, '1', '2025-10-30 12:00:00', '2025-10-30', '12', 'PHL_SP_001', '1900 BLOCK S BROAD ST', '600', 'THEFT', '39.9200', '-75.1580', 'sample_consolidated'),
    (3000002, NULL, '1', '2025-10-30 15:30:00', '2025-10-30', '15', 'PHL_SP_002', '2000 BLOCK S 15TH ST', '1300', 'VANDALISM', '39.9150', '-75.1620', 'sample_consolidated'),
    
    -- West Philadelphia incidents (objectid: 4000000+)
    (4000001, 500006, '18', '2025-10-30 20:15:00', '2025-10-30', '20', 'PHL_WP_001', '4800 BLOCK BALTIMORE AVE', '300', 'ASSAULT', '39.9600', '-75.2000', 'sample_consolidated'),
    (4000002, NULL, '18', '2025-10-30 11:45:00', '2025-10-30', '11', 'PHL_WP_002', '4600 BLOCK CHESTNUT ST', '400', 'THEFT', '39.9550', '-75.1950', 'sample_consolidated');
    "
    
    execute_sql "$raw_sql" "Sample raw incidents insertion"
    
    # Sample clean incidents (after processing)
    print_info "Inserting sample clean incidents..."
    local clean_sql="
    INSERT IGNORE INTO amisafe_clean_incidents 
    (incident_id, objectid, cartodb_id, dc_key, dc_dist, location_block, lat, lng, incident_datetime, incident_date, incident_hour, incident_month, incident_year, ucr_general, crime_category, severity_level, h3_res_5, h3_res_6, h3_res_7, h3_res_8, h3_res_9, h3_res_10, h3_res_11, h3_res_12, h3_res_13, processing_batch_id)
    VALUES
    ('obj_1000001', 1000001, 500001, 'PHL_CC_001', '6', '1500 BLOCK MARKET ST', 39.9526, -75.1652, '2025-10-30 14:30:00', '2025-10-30', 14, 10, 2025, '400', 'Violent Crime', 3, '85283473fffffff', '862834707ffffff', '872834700ffffff', '882834700ffffff', '892834700ffffff', '8a2834700ffffff', '8b2834700ffffff', '8c2834700ffffff', '8d2834700ffffff', 'batch_001'),
    ('obj_1000002', 1000002, 500002, 'PHL_CC_002', '6', '1600 BLOCK WALNUT ST', 39.9500, -75.1667, '2025-10-30 16:45:00', '2025-10-30', 16, 10, 2025, '200', 'Violent Crime', 4, '85283473fffffff', '862834707ffffff', '872834700ffffff', '882834700ffffff', '892834701ffffff', '8a2834701ffffff', '8b2834701ffffff', '8c2834701ffffff', '8d2834701ffffff', 'batch_001'),
    ('obj_1000003', 1000003, NULL, 'PHL_CC_003', '6', '1700 BLOCK CHESTNUT ST', 39.9480, -75.1635, '2025-10-30 09:15:00', '2025-10-30', 9, 10, 2025, '100', 'Violent Crime', 5, '85283473fffffff', '862834707ffffff', '872834700ffffff', '882834700ffffff', '892834700ffffff', '8a2834700ffffff', '8b2834700ffffff', '8c2834700ffffff', '8d2834700ffffff', 'batch_001'),
    ('obj_2000001', 2000001, 500003, 'PHL_NP_001', '22', '2800 BLOCK N BROAD ST', 39.9950, -75.1450, '2025-10-30 22:30:00', '2025-10-30', 22, 10, 2025, '300', 'Violent Crime', 4, '85283463fffffff', '862834637ffffff', '872834630ffffff', '882834630ffffff', '892834630ffffff', '8a2834630ffffff', '8b2834630ffffff', '8c2834630ffffff', '8d2834630ffffff', 'batch_001'),
    ('obj_2000002', 2000002, NULL, 'PHL_NP_002', '22', '3200 BLOCK N 5TH ST', 40.0100, -75.1300, '2025-10-30 01:45:00', '2025-10-30', 1, 10, 2025, '400', 'Violent Crime', 3, '85283463fffffff', '862834637ffffff', '872834631ffffff', '882834631ffffff', '892834631ffffff', '8a2834631ffffff', '8b2834631ffffff', '8c2834631ffffff', '8d2834631ffffff', 'batch_001'),
    ('obj_3000001', 3000001, 500005, 'PHL_SP_001', '1', '1900 BLOCK S BROAD ST', 39.9200, -75.1580, '2025-10-30 12:00:00', '2025-10-30', 12, 10, 2025, '600', 'Property Crime', 2, '85283447fffffff', '862834467ffffff', '872834460ffffff', '882834460ffffff', '892834460ffffff', '8a2834460ffffff', '8b2834460ffffff', '8c2834460ffffff', '8d2834460ffffff', 'batch_001'),
    ('obj_4000001', 4000001, 500006, 'PHL_WP_001', '18', '4800 BLOCK BALTIMORE AVE', 39.9600, -75.2000, '2025-10-30 20:15:00', '2025-10-30', 20, 10, 2025, '300', 'Violent Crime', 4, '85283443fffffff', '862834437ffffff', '872834430ffffff', '882834430ffffff', '892834430ffffff', '8a2834430ffffff', '8b2834430ffffff', '8c2834430ffffff', '8d2834430ffffff', 'batch_001');
    "
    
    execute_sql "$clean_sql" "Sample clean incidents insertion"
    
    # Sample H3 aggregated data
    print_info "Inserting sample H3 aggregated data..."
    local h3_sql="
    INSERT IGNORE INTO amisafe_h3_aggregated 
    (h3_index, h3_resolution, incident_count, unique_incident_types, earliest_incident, latest_incident, 
     incidents_last_30_days, incidents_last_year, center_latitude, center_longitude, 
     incident_type_counts, district_counts, total_valid_records, 
     h3_parent, severity_avg, top_crime_type, peak_hour, is_empty, aggregation_batch_id)
    VALUES
    -- Resolution 8 hexagons (large area coverage)
    ('882aacb2e57ffff', 8, 47, 6, '2025-10-20 08:30:00', '2025-10-30 16:45:00', 12, 47, 39.9526, -75.1652, 
     '{\"100\":2, \"200\":8, \"300\":12, \"400\":15, \"500\":6, \"600\":4}', '{\"6\":47}', 47,
     NULL, 3.2, '400', 16, FALSE, 'batch_h3_001'),
    ('882aacb2e4fffff', 8, 38, 4, '2025-10-15 14:20:00', '2025-10-30 22:30:00', 8, 38, 39.9950, -75.1450, 
     '{\"200\":5, \"300\":14, \"400\":10, \"500\":9}', '{\"22\":38}', 38,
     NULL, 3.8, '300', 22, FALSE, 'batch_h3_001'),
    ('882aacb2e47ffff', 8, 29, 4, '2025-10-18 10:15:00', '2025-10-30 15:30:00', 7, 29, 39.9200, -75.1580, 
     '{\"200\":7, \"300\":6, \"400\":11, \"600\":5}', '{\"1\":29}', 29,
     NULL, 3.1, '400', 15, FALSE, 'batch_h3_001'),
    ('882aacb2e5fffff', 8, 35, 5, '2025-10-12 09:45:00', '2025-10-30 20:15:00', 9, 35, 39.9600, -75.2000, 
     '{\"100\":1, \"300\":13, \"400\":12, \"500\":6, \"600\":3}', '{\"18\":35}', 35,
     NULL, 3.4, '300', 20, FALSE, 'batch_h3_001'),
    
    -- Resolution 9 hexagons (subdivisions)
    ('892aacb2e57ffff', 9, 23, 5, '2025-10-22 11:30:00', '2025-10-30 16:45:00', 6, 23, 39.9540, -75.1640, 
     '{\"200\":4, \"300\":6, \"400\":8, \"500\":3, \"600\":2}', '{\"6\":23}', 23,
     '882aacb2e57ffff', 3.1, '400', 16, FALSE, 'batch_h3_001'),
    ('892aacb2e5fffff', 9, 24, 5, '2025-10-25 08:20:00', '2025-10-30 14:30:00', 6, 24, 39.9510, -75.1665, 
     '{\"100\":2, \"200\":4, \"300\":6, \"400\":7, \"500\":3, \"600\":2}', '{\"6\":24}', 24,
     '882aacb2e57ffff', 3.3, '400', 14, FALSE, 'batch_h3_001'),
    
    -- Empty hexagon for testing
    ('882aacb2e6affff', 8, 0, 0, NULL, NULL, 0, 0, 40.1000, -75.0500, 
     '{}', '{}', 0,
     NULL, 0.0, NULL, NULL, TRUE, 'batch_h3_001');
    "
    
    execute_sql "$h3_sql" "Sample H3 aggregated data insertion"
}

# Verify database setup and show statistics
verify_database_setup() {
    print_section "Verifying Database Setup"
    
    # Check all tables exist
    local tables=(
        "amisafe_raw_incidents"
        "amisafe_clean_incidents" 
        "amisafe_h3_aggregated"
        "amisafe_ucr_codes"
    )
    
    print_info "Checking database tables..."
    local all_tables_exist=true
    for table in "${tables[@]}"; do
        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SHOW TABLES LIKE '$table';" 2>/dev/null | grep -q "$table"; then
            print_success "Table $table exists"
        else
            print_error "Table $table is missing"
            all_tables_exist=false
        fi
    done
    
    if [[ "$all_tables_exist" == "false" ]]; then
        print_error "Database setup incomplete - missing tables"
        return 1
    fi
    
    # Verify ObjectID constraints
    print_info "Verifying ObjectID constraints..."
    local objectid_constraints=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -se "
    SELECT COUNT(*) FROM information_schema.key_column_usage 
    WHERE table_schema = '$DB_NAME' 
    AND column_name = 'objectid' 
    AND constraint_name LIKE '%unique%';
    " 2>/dev/null)
    
    if [[ "$objectid_constraints" -ge 2 ]]; then
        print_success "ObjectID unique constraints verified ($objectid_constraints tables)"
    else
        print_warning "ObjectID constraints may be missing"
    fi
}

# Display database statistics and summary
show_database_summary() {
    print_section "Database Setup Summary"
    
    # Table statistics
    print_info "Table statistics:"
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
    SELECT 
        table_name as 'Table',
        table_rows as 'Rows',
        ROUND(((data_length + index_length) / 1024 / 1024), 2) as 'Size_MB',
        table_comment as 'Purpose'
    FROM information_schema.tables 
    WHERE table_schema = '$DB_NAME' 
    AND table_name LIKE 'amisafe_%'
    ORDER BY table_name;
    " 2>/dev/null
    
    # UCR codes count
    local ucr_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -se "SELECT COUNT(*) FROM amisafe_ucr_codes;" 2>/dev/null)
    print_info "UCR crime codes loaded: $ucr_count"
    
    # Sample data verification
    local raw_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -se "SELECT COUNT(*) FROM amisafe_raw_incidents;" 2>/dev/null)
    local clean_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -se "SELECT COUNT(*) FROM amisafe_clean_incidents;" 2>/dev/null)
    local h3_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -se "SELECT COUNT(*) FROM amisafe_h3_aggregated;" 2>/dev/null)
    
    print_info "Sample data loaded:"
    print_info "  Raw incidents: $raw_count"
    print_info "  Clean incidents: $clean_count"
    print_info "  H3 aggregations: $h3_count"
}

# Main execution flow
main() {
    print_header "AmISafe Consolidated Database Setup"
    print_info "Target Database: $DB_NAME"
    print_info "Features: ObjectID-based processing, 3-layer ETL, H3 indexing"
    echo ""
    
    # Execute setup steps
    check_prerequisites
    setup_database
    
    create_raw_layer_table
    create_transform_layer_table
    create_final_layer_table
    create_ucr_reference_table
    
    insert_ucr_reference_data
    insert_sample_data
    
    # Verify and summarize
    if verify_database_setup; then
        show_database_summary
        
        print_header "Setup Complete!"
        print_success "Database: $DB_NAME"
        print_success "Features: ObjectID-based processing with complete ETL pipeline"
        print_success "Sample data: Ready for testing"
        print_success "Processing: 3.4M record capability (vs 856K with CartoDB ID)"
        
        echo ""
        print_info "Next steps:"
        print_info "1. Run ETL pipeline: cd h3-geolocation/database"
        print_info "2. Process raw data: python enhanced_transform_processor_v2.py"
        print_info "3. Generate aggregations: python amisafe_aggregator.py"
        print_info "4. Test APIs with real data"
        
        return 0
    else
        print_error "Database setup failed verification"
        return 1
    fi
}

# Execute main function if script is run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi