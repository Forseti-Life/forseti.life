#!/bin/bash
#
# AmISafe Data Pipeline
# Master script to process incident data with H3 geospatial indexing
#

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
H3_ENV_DIR="$SCRIPT_DIR/../h3-env"
DATA_DIR="$SCRIPT_DIR/../data/raw"
LOG_FILE="$SCRIPT_DIR/amisafe_pipeline.log"

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
        error "H3 virtual environment not found at $H3_ENV_DIR"
        error "Please run the H3 framework installation first"
        exit 1
    fi
}

# Activate virtual environment
activate_venv() {
    log "Activating H3 virtual environment..."
    source "$H3_ENV_DIR/bin/activate"
    success "Virtual environment activated"
}

# Check MySQL service
check_mysql() {
    log "Checking MySQL service..."
    if ! systemctl is-active --quiet mysql 2>/dev/null && ! service mysql status >/dev/null 2>&1; then
        warning "MySQL service not running, attempting to start..."
        sudo service mysql start
    fi
    
    # Use credentials from config file
    if mysql -h 127.0.0.1 -u drupal_user -pdrupal_secure_password -e "SELECT 1" >/dev/null 2>&1; then
        success "MySQL is running and accessible"
    else
        error "Cannot connect to MySQL"
        exit 1
    fi
}

# Setup database
setup_database() {
    log "Setting up AmISafe database..."
    if mysql -h 127.0.0.1 -u drupal_user -pdrupal_secure_password -e "USE stlouisintegration_dev" >/dev/null 2>&1; then
        success "Database stlouisintegration_dev is accessible"
        # Check if tables exist
        if mysql -h 127.0.0.1 -u drupal_user -pdrupal_secure_password stlouisintegration_dev -e "SHOW TABLES LIKE 'amisafe_%'" | grep -q amisafe; then
            success "AmISafe tables already exist"
        else
            warning "AmISafe tables not found - they should be created by Drupal module"
        fi
    else
        error "Cannot access database stlouisintegration_dev"
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

# Process data files
process_data() {
    log "Starting data processing pipeline..."
    
    # Make sure we're in the right directory and virtual environment is active
    cd "$SCRIPT_DIR"
    source "$H3_ENV_DIR/bin/activate"
    
    # Run the data processor with correct database credentials
    python amisafe_processor.py --data-dir "$DATA_DIR" \
        --mysql-host "127.0.0.1" \
        --mysql-user "drupal_user" \
        --mysql-password "drupal_secure_password" \
        --mysql-database "stlouisintegration_dev"
    
    if [ $? -eq 0 ]; then
        success "Data processing completed successfully"
    else
        error "Data processing failed"
        exit 1
    fi
}

# Create aggregations
create_aggregations() {
    log "Creating data aggregations..."
    
    cd "$SCRIPT_DIR"
    source "$H3_ENV_DIR/bin/activate"
    
    # Run the aggregator with correct database credentials
    python amisafe_aggregator.py \
        --mysql-host "127.0.0.1" \
        --mysql-user "drupal_user" \
        --mysql-password "drupal_secure_password" \
        --mysql-database "stlouisintegration_dev" \
        --resolution 9 --days-lookback 30
    
    if [ $? -eq 0 ]; then
        success "Data aggregation completed successfully"
    else
        error "Data aggregation failed"
        exit 1
    fi
}

# Show processing status
show_status() {
    log "Checking processing status..."
    
    cd "$SCRIPT_DIR"
    source "$H3_ENV_DIR/bin/activate"
    
    python amisafe_processor.py --status \
        --mysql-host "127.0.0.1" \
        --mysql-user "drupal_user" \
        --mysql-password "drupal_secure_password" \
        --mysql-database "stlouisintegration_dev"
}

# Show database statistics
show_stats() {
    log "Database Statistics:"
    echo "==================="
    
    echo "Raw incidents table:"
    mysql -h 127.0.0.1 -u drupal_user -pdrupal_secure_password stlouisintegration_dev -e "SELECT COUNT(*) as 'Total Records', 
                     COUNT(CASE WHEN lat IS NOT NULL AND lng IS NOT NULL THEN 1 END) as 'With Coordinates',
                     MIN(ingested_at) as 'Earliest Date',
                     MAX(ingested_at) as 'Latest Date'
              FROM amisafe_raw_incidents;" 2>/dev/null || true
    
    echo -e "\nH3 Aggregations:"
    mysql -h 127.0.0.1 -u drupal_user -pdrupal_secure_password stlouisintegration_dev -e "SELECT h3_resolution, COUNT(*) as 'Hexagon Count', SUM(incident_count) as 'Total Crimes'
              FROM amisafe_h3_aggregated 
              GROUP BY h3_resolution 
              ORDER BY h3_resolution;" 2>/dev/null || true
    
    echo -e "\nDistrict Coverage:"
    mysql -h 127.0.0.1 -u drupal_user -pdrupal_secure_password stlouisintegration_dev -e "SELECT dc_dist, COUNT(*) as 'Incident Count' 
              FROM amisafe_raw_incidents 
              WHERE dc_dist IS NOT NULL
              GROUP BY dc_dist 
              ORDER BY COUNT(*) DESC 
              LIMIT 10;" 2>/dev/null || true
}

# Main function
main() {
    log "Starting AmISafe Data Pipeline"
    log "=============================="
    
    case "${1:-full}" in
        "setup")
            log "Running database setup only..."
            check_mysql
            setup_database
            success "Database setup completed"
            ;;
        "process")
            log "Running data processing only..."
            check_venv
            activate_venv
            check_mysql
            check_data_files
            process_data
            success "Data processing completed"
            ;;
        "aggregate")
            log "Running data aggregation only..."
            check_venv
            activate_venv
            check_mysql
            create_aggregations
            success "Data aggregation completed"
            ;;
        "status")
            log "Showing processing status..."
            check_venv
            activate_venv
            check_mysql
            show_status
            ;;
        "stats")
            log "Showing database statistics..."
            check_mysql
            show_stats
            ;;
        "full"|*)
            log "Running full pipeline..."
            check_venv
            activate_venv
            check_mysql
            setup_database
            check_data_files
            process_data
            create_aggregations
            show_stats
            success "Full pipeline completed successfully!"
            ;;
    esac
    
    log "Pipeline execution completed"
}

# Show usage
usage() {
    echo "AmISafe Data Pipeline"
    echo "Usage: $0 [command]"
    echo ""
    echo "Commands:"
    echo "  full      - Run complete pipeline (default)"
    echo "  setup     - Setup database only"
    echo "  process   - Process CSV files only" 
    echo "  aggregate - Create aggregations only"
    echo "  status    - Show processing status"
    echo "  stats     - Show database statistics"
    echo "  help      - Show this help message"
    echo ""
    echo "Data files should be placed in: $DATA_DIR"
    echo "Logs are written to: $LOG_FILE"
}

# Handle help command
if [ "${1:-}" = "help" ] || [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    usage
    exit 0
fi

# Run main function
main "$@"