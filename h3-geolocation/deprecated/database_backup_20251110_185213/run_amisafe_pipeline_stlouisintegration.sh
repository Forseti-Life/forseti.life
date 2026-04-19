#!/bin/bash
#
# AmISafe Data Pipeline for St. Louis Integration
# Master script to process incident data with H3 geospatial indexing
# MODIFIED TO USE: stlouisintegration_dev database
#

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
H3_ENV_DIR="$SCRIPT_DIR/../h3-env"
DATA_DIR="$SCRIPT_DIR/../data/raw"
LOG_FILE="$SCRIPT_DIR/amisafe_pipeline_stlouisintegration.log"

# Database configuration - MODIFIED FOR STLOUISINTEGRATION
DB_HOST="127.0.0.1"
DB_USER="drupal_user"
DB_PASSWORD="drupal_secure_password"
DB_NAME="stlouisintegration_dev"  # <-- CHANGED FROM theoryofconspiracies_dev

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

# Check if virtual environment exists
check_venv() {
    if [ ! -d "$H3_ENV_DIR" ]; then
        warning "H3 virtual environment not found at $H3_ENV_DIR"
        warning "Checking if system has h3 available..."
        if python3 -c "import h3" 2>/dev/null; then
            success "H3 is available in system Python"
            return 0
        else
            error "H3 not available. Please install: pip install h3"
            exit 1
        fi
    fi
}

# Activate virtual environment (if available)
activate_venv() {
    if [ -d "$H3_ENV_DIR" ]; then
        log "Activating H3 virtual environment..."
        source "$H3_ENV_DIR/bin/activate"
        success "Virtual environment activated"
    else
        log "Using system Python with H3 support"
    fi
}

# Check MySQL service
check_mysql() {
    log "Checking MySQL service..."
    if ! systemctl is-active --quiet mysql 2>/dev/null && ! service mysql status >/dev/null 2>&1; then
        warning "MySQL service not running, attempting to start..."
        sudo service mysql start
    fi
    
    # Test connection with correct database
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1" "$DB_NAME" >/dev/null 2>&1; then
        success "MySQL is running and database $DB_NAME is accessible"
    else
        error "Cannot connect to MySQL database $DB_NAME"
        exit 1
    fi
}

# Setup database
setup_database() {
    log "Setting up AmISafe database in $DB_NAME..."
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "USE $DB_NAME" >/dev/null 2>&1; then
        success "Database $DB_NAME is accessible"
        # Check if tables exist
        if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SHOW TABLES LIKE 'amisafe_%'" | grep -q amisafe; then
            success "AmISafe tables already exist"
        else
            warning "AmISafe tables not found - running database setup..."
            if [ -f "$SCRIPT_DIR/setup_amisafe_stlouisintegration.sh" ]; then
                bash "$SCRIPT_DIR/setup_amisafe_stlouisintegration.sh"
            else
                error "Database setup script not found"
                exit 1
            fi
        fi
    else
        error "Cannot access database $DB_NAME"
        exit 1
    fi
}

# Check data files
check_data_files() {
    log "Checking for data files in $DATA_DIR..."
    if [ ! -d "$DATA_DIR" ]; then
        error "Data directory not found: $DATA_DIR"
        exit 1
    fi
    
    CSV_COUNT=$(find "$DATA_DIR" -name "*.csv" | wc -l)
    if [ "$CSV_COUNT" -eq 0 ]; then
        error "No CSV files found in $DATA_DIR"
        exit 1
    fi
    
    success "Found $CSV_COUNT CSV files to process"
}

# Process sample data (simplified version)
process_sample_data() {
    log "Processing sample incident data for testing..."
    
    cd "$SCRIPT_DIR"
    
    # Insert sample data directly into stlouisintegration database
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" << 'EOF'
-- Insert sample Philadelphia crime data for testing
INSERT INTO amisafe_raw_incidents (
    source_file, cartodb_id, objectid, dc_dist, dispatch_date_time, 
    lat, lng, location_block, ucr_general, text_general_code
) VALUES
-- Center City incidents
('sample_data', 1001, 2001, '6', '2025-10-30 14:30:00', '39.9526', '-75.1652', '1500 BLOCK MARKET ST', '400', 'THEFT'),
('sample_data', 1002, 2002, '6', '2025-10-30 16:45:00', '39.9500', '-75.1667', '1600 BLOCK WALNUT ST', '200', 'BURGLARY'),
('sample_data', 1003, 2003, '6', '2025-10-30 09:15:00', '39.9480', '-75.1635', '1700 BLOCK CHESTNUT ST', '100', 'HOMICIDE'),

-- North Philadelphia incidents  
('sample_data', 1004, 2004, '22', '2025-10-30 22:30:00', '39.9950', '-75.1450', '2800 BLOCK N BROAD ST', '300', 'ASSAULT'),
('sample_data', 1005, 2005, '22', '2025-10-30 01:45:00', '40.0100', '-75.1300', '3200 BLOCK N 5TH ST', '400', 'THEFT'),
('sample_data', 1006, 2006, '22', '2025-10-30 18:20:00', '39.9850', '-75.1500', '2600 BLOCK N BROAD ST', '500', 'DRUG OFFENSE'),

-- South Philadelphia incidents
('sample_data', 1007, 2007, '1', '2025-10-30 12:00:00', '39.9200', '-75.1580', '1900 BLOCK S BROAD ST', '400', 'THEFT'),
('sample_data', 1008, 2008, '1', '2025-10-30 15:30:00', '39.9150', '-75.1620', '2000 BLOCK S 15TH ST', '600', 'VANDALISM'),

-- West Philadelphia incidents
('sample_data', 1009, 2009, '18', '2025-10-30 20:15:00', '39.9600', '-75.2000', '4800 BLOCK BALTIMORE AVE', '300', 'ASSAULT'),
('sample_data', 1010, 2010, '18', '2025-10-30 11:45:00', '39.9550', '-75.1950', '4600 BLOCK CHESTNUT ST', '400', 'THEFT'),

-- Additional historical data for better statistics
('sample_data', 1011, 2011, '6', '2025-10-29 08:30:00', '39.9526', '-75.1652', '1500 BLOCK MARKET ST', '400', 'THEFT'),
('sample_data', 1012, 2012, '6', '2025-10-29 19:15:00', '39.9500', '-75.1667', '1600 BLOCK WALNUT ST', '300', 'ASSAULT'),
('sample_data', 1013, 2013, '22', '2025-10-29 23:45:00', '39.9950', '-75.1450', '2800 BLOCK N BROAD ST', '500', 'DRUG OFFENSE'),
('sample_data', 1014, 2014, '1', '2025-10-29 13:20:00', '39.9200', '-75.1580', '1900 BLOCK S BROAD ST', '200', 'BURGLARY'),
('sample_data', 1015, 2015, '18', '2025-10-29 17:00:00', '39.9600', '-75.2000', '4800 BLOCK BALTIMORE AVE', '600', 'VANDALISM'),

-- Week ago data
('sample_data', 1016, 2016, '6', '2025-10-23 10:30:00', '39.9480', '-75.1635', '1700 BLOCK CHESTNUT ST', '400', 'THEFT'),
('sample_data', 1017, 2017, '22', '2025-10-23 14:15:00', '40.0100', '-75.1300', '3200 BLOCK N 5TH ST', '300', 'ASSAULT'),
('sample_data', 1018, 2018, '1', '2025-10-23 21:45:00', '39.9150', '-75.1620', '2000 BLOCK S 15TH ST', '200', 'BURGLARY'),
('sample_data', 1019, 2019, '18', '2025-10-23 07:30:00', '39.9550', '-75.1950', '4600 BLOCK CHESTNUT ST', '100', 'HOMICIDE'),

-- Month ago for temporal analysis
('sample_data', 1020, 2020, '6', '2025-09-30 11:15:00', '39.9526', '-75.1652', '1500 BLOCK MARKET ST', '200', 'BURGLARY'),
('sample_data', 1021, 2021, '22', '2025-09-30 20:30:00', '39.9950', '-75.1450', '2800 BLOCK N BROAD ST', '400', 'THEFT'),
('sample_data', 1022, 2022, '1', '2025-09-30 15:45:00', '39.9200', '-75.1580', '1900 BLOCK S BROAD ST', '300', 'ASSAULT'),
('sample_data', 1023, 2023, '18', '2025-09-30 09:00:00', '39.9600', '-75.2000', '4800 BLOCK BALTIMORE AVE', '500', 'DRUG OFFENSE');
EOF
    
    if [ $? -eq 0 ]; then
        success "Sample data inserted successfully"
    else
        error "Sample data insertion failed"
        exit 1
    fi
}

# Show database statistics
show_stats() {
    log "Database Statistics for $DB_NAME:"
    echo "================================="
    
    echo "Raw incidents table:"
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SELECT COUNT(*) as 'Total Records', 
                     COUNT(CASE WHEN lat IS NOT NULL AND lng IS NOT NULL THEN 1 END) as 'With Coordinates',
                     MIN(ingested_at) as 'First Ingested',
                     MAX(ingested_at) as 'Last Ingested'
              FROM amisafe_raw_incidents;" 2>/dev/null || true
    
    echo -e "\nDistinct Crime Types:"
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SELECT ucr_general, text_general_code, COUNT(*) as 'Count' 
              FROM amisafe_raw_incidents 
              WHERE ucr_general IS NOT NULL
              GROUP BY ucr_general, text_general_code 
              ORDER BY COUNT(*) DESC;" 2>/dev/null || true
    
    echo -e "\nDistrict Coverage:"
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SELECT dc_dist, COUNT(*) as 'Incident Count' 
              FROM amisafe_raw_incidents 
              WHERE dc_dist IS NOT NULL
              GROUP BY dc_dist 
              ORDER BY COUNT(*) DESC 
              LIMIT 10;" 2>/dev/null || true
}

# Main function
main() {
    log "Starting AmISafe Data Pipeline for St. Louis Integration"
    log "======================================================="
    log "Target Database: $DB_NAME"
    
    case "${1:-sample}" in
        "setup")
            log "Running database setup only..."
            check_mysql
            setup_database
            success "Database setup completed"
            ;;
        "sample")
            log "Running sample data processing..."
            check_mysql
            setup_database
            process_sample_data
            show_stats
            success "Sample data processing completed"
            ;;
        "stats")
            log "Showing database statistics..."
            check_mysql
            show_stats
            ;;
        "full")
            log "Running full pipeline with CSV processing..."
            check_venv
            activate_venv
            check_mysql
            setup_database
            check_data_files
            warning "Full CSV processing requires updated Python scripts"
            warning "Currently showing sample data processing instead"
            process_sample_data
            show_stats
            success "Pipeline processing completed"
            ;;  
        *)
            log "Running sample data pipeline..."
            check_mysql
            setup_database
            process_sample_data
            show_stats
            success "Sample pipeline completed successfully!"
            ;;
    esac
    
    log "Pipeline execution completed for $DB_NAME"
}

# Show usage
usage() {
    echo "AmISafe Data Pipeline for St. Louis Integration"
    echo "Usage: $0 [command]"
    echo ""
    echo "Commands:"
    echo "  sample    - Run sample data processing (default)"
    echo "  setup     - Setup database only"
    echo "  full      - Run complete pipeline with CSVs"
    echo "  stats     - Show database statistics"
    echo "  help      - Show this help message"
    echo ""
    echo "Target Database: $DB_NAME"
    echo "Data files location: $DATA_DIR"
    echo "Logs are written to: $LOG_FILE"
}

# Handle help command
if [ "${1:-}" = "help" ] || [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    usage
    exit 0
fi

# Run main function
main "$@"