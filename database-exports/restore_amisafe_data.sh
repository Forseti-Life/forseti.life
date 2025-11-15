#!/bin/bash

# AmISafe Database Restoration Script
# Universal restoration utility for Bronze/Silver/Gold layer backups
# Supports interruption recovery and selective table restoration

set -euo pipefail  # Exit on error, undefined vars, pipe failures

echo "=== AmISafe Database Restoration Utility ==="
echo "Restoration started: $(date)"

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DUMPS_DIR="$SCRIPT_DIR/dumps"
BATCH_SIZE=1000
TEMP_DIR="$SCRIPT_DIR/.temp_restore"

# Database connection parameters
DB_USER="drupal_user"
DB_PASS="drupal_secure_password"
DB_HOST="127.0.0.1"
DB_NAME="amisafe_database"

# Available tables for restoration
declare -A AVAILABLE_TABLES=(
    ["bronze"]="amisafe_raw_incidents"
    ["silver"]="amisafe_clean_incidents"
    ["gold"]="amisafe_h3_aggregated"
    ["reference"]="amisafe_ucr_codes"
)

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to display usage
show_usage() {
    echo ""
    echo "Usage: $0 [OPTIONS] [TABLE_LAYER]"
    echo ""
    echo "TABLE_LAYER options:"
    echo "  bronze     - Restore Bronze layer (amisafe_raw_incidents)"
    echo "  silver     - Restore Silver layer (amisafe_clean_incidents)"
    echo "  gold       - Restore Gold layer (amisafe_h3_aggregated)"
    echo "  reference  - Restore reference data (amisafe_ucr_codes)"
    echo "  all        - Restore all layers"
    echo ""
    echo "OPTIONS:"
    echo "  -f, --force       Force restoration (skip confirmation)"
    echo "  -t, --timestamp   Specify backup timestamp (default: latest)"
    echo "  -s, --structure   Restore structure only"
    echo "  -d, --data        Restore data only"
    echo "  -i, --incremental Incremental restore (skip existing records)"
    echo "  -h, --help        Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 silver                          # Restore Silver layer interactively"
    echo "  $0 all -f                         # Force restore all layers"
    echo "  $0 bronze -t 20251113_125154      # Restore specific timestamp"
    echo "  $0 silver -i                      # Incremental restore (skip duplicates)"
    echo ""
}

# Function to log messages with color
log() {
    local level=$1
    local message=$2
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    case $level in
        "INFO")  echo -e "${BLUE}[INFO]${NC} $timestamp - $message" ;;
        "SUCCESS") echo -e "${GREEN}[SUCCESS]${NC} $timestamp - $message" ;;
        "WARNING") echo -e "${YELLOW}[WARNING]${NC} $timestamp - $message" ;;
        "ERROR") echo -e "${RED}[ERROR]${NC} $timestamp - $message" ;;
    esac
}

# Function to analyze current table state
analyze_table_state() {
    local table_name=$1
    
    # Check if table exists and get record count
    local current_count=$(mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME" \
        -se "SELECT COUNT(*) FROM $table_name" 2>/dev/null || echo "0")
    
    # Get sample of existing record IDs to avoid duplicates
    if [[ "$current_count" -gt 0 ]]; then
        log "INFO" "Current table state: $current_count records exist"
        return 0
    else
        log "INFO" "Table is empty, starting fresh import"
        return 1
    fi
}

# Function to create temporary batch tracking
create_batch_tracker() {
    local table_name=$1
    mkdir -p "$TEMP_DIR"
    echo "$(date -Iseconds)" > "$TEMP_DIR/${table_name}_batch_start"
}

# Function to cleanup temporary files
cleanup_temp() {
    [[ -d "$TEMP_DIR" ]] && rm -rf "$TEMP_DIR"
}

# Function to start background monitoring of table growth
start_background_monitor() {
    local table_name=$1
    local monitor_file="$TEMP_DIR/monitor_${table_name}.pid"
    
    # Create monitoring script
    cat > "$TEMP_DIR/monitor_${table_name}.sh" << EOF
#!/bin/bash
while [[ -f "$TEMP_DIR/import_active" ]]; do
    sleep 30
    current_count=\$(mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME" -se "SELECT COUNT(*) FROM $table_name" 2>/dev/null || echo "0")
    timestamp=\$(date '+%H:%M:%S')
    echo "\$timestamp - Table $table_name: \$current_count records" >> "$TEMP_DIR/monitor_${table_name}.log"
done
EOF
    
    chmod +x "$TEMP_DIR/monitor_${table_name}.sh"
    
    # Start background monitoring
    bash "$TEMP_DIR/monitor_${table_name}.sh" &
    echo $! > "$monitor_file"
    
    # Create active flag
    touch "$TEMP_DIR/import_active"
}

# Function to stop background monitoring
stop_background_monitor() {
    local table_name=$1
    local monitor_file="$TEMP_DIR/monitor_${table_name}.pid"
    
    # Remove active flag
    rm -f "$TEMP_DIR/import_active"
    
    # Stop monitor process
    if [[ -f "$monitor_file" ]]; then
        local pid=$(cat "$monitor_file")
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid" 2>/dev/null || true
            sleep 2  # Give process time to exit gracefully
        fi
        rm -f "$monitor_file"
    fi
    
    # Show monitoring log if it exists
    if [[ -f "$TEMP_DIR/monitor_${table_name}.log" ]]; then
        log "INFO" "Background monitoring results:"
        tail -5 "$TEMP_DIR/monitor_${table_name}.log" | while read -r line; do
            log "INFO" "  $line"
        done
    fi
}

# Function to test database connection
test_db_connection() {
    log "INFO" "Testing database connection..."
    
    if mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -e "USE $DB_NAME; SELECT 1;" >/dev/null 2>&1; then
        log "SUCCESS" "Database connection established"
        return 0
    else
        log "ERROR" "Failed to connect to database"
        return 1
    fi
}

# Function to find latest timestamp
find_latest_timestamp() {
    local table_name=$1
    
    # Find the most recent structure file
    local latest_file=$(find "$DUMPS_DIR" -name "${table_name}_structure_*.sql" -printf '%T@ %p\n' 2>/dev/null | sort -n | tail -1 | cut -d' ' -f2-)
    
    if [[ -n "$latest_file" ]]; then
        basename "$latest_file" | sed "s/${table_name}_structure_\(.*\)\.sql/\1/"
    else
        echo ""
    fi
}

# Function to verify backup files exist
verify_backup_files() {
    local table_name=$1
    local timestamp=$2
    
    local structure_file="$DUMPS_DIR/${table_name}_structure_${timestamp}.sql"
    local data_file="$DUMPS_DIR/${table_name}_data_${timestamp}.sql.gz"
    
    if [[ ! -f "$structure_file" ]]; then
        log "ERROR" "Structure file not found: $structure_file"
        return 1
    fi
    
    if [[ ! -f "$data_file" ]]; then
        log "ERROR" "Data file not found: $data_file"
        return 1
    fi
    
    log "SUCCESS" "Backup files verified for $table_name ($timestamp)"
    return 0
}

# Function to get table record count
get_table_count() {
    local table_name=$1
    
    mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME" \
        -se "SELECT COUNT(*) FROM $table_name" 2>/dev/null || echo "0"
}

# Function to restore table structure
restore_structure() {
    local table_name=$1
    local timestamp=$2
    
    local structure_file="$DUMPS_DIR/${table_name}_structure_${timestamp}.sql"
    
    # Check if table already exists
    local table_exists=$(mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME" \
        -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='$table_name'" 2>/dev/null || echo "0")
    
    if [[ "$table_exists" -gt 0 ]]; then
        local current_count=$(get_table_count "$table_name")
        log "INFO" "Table $table_name already exists with $current_count records - will append new data"
        return 0
    fi
    
    log "INFO" "Creating table structure for $table_name..."
    
    # Import structure
    if mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME" < "$structure_file"; then
        log "SUCCESS" "Structure created for $table_name"
        return 0
    else
        log "ERROR" "Failed to create structure for $table_name"
        return 1
    fi
}

# Function to restore table data with incremental batch processing
restore_data() {
    local table_name=$1
    local timestamp=$2
    
    local data_file="$DUMPS_DIR/${table_name}_data_${timestamp}.sql.gz"
    
    log "INFO" "Starting incremental batch restoration for $table_name..."
    
    # Show file size
    local compressed_size=$(ls -lh "$data_file" | awk '{print $5}')
    log "INFO" "Data file size: $compressed_size (compressed)"
    
    # Analyze current state
    local initial_count=$(get_table_count "$table_name")
    log "INFO" "Initial record count: $initial_count"
    
    # Create batch tracker
    create_batch_tracker "$table_name"
    
    # Start background monitoring
    start_background_monitor "$table_name"
    
    # For incremental mode, use chunked processing for resumable restoration
    if [[ "${INCREMENTAL_MODE:-false}" == "true" ]] || [[ "$initial_count" -gt 0 ]]; then
        log "INFO" "Using chunked processing for resumable restoration"
        
        # Set up chunk directories
        local chunks_dir="$TEMP_DIR/chunks_${table_name}"
        local processed_dir="$chunks_dir/processed"
        mkdir -p "$chunks_dir" "$processed_dir"
        
        # Check if chunks already exist
        local existing_chunks=()
        if ls "$chunks_dir"/chunk_*.sql >/dev/null 2>&1; then
            while IFS= read -r -d '' file; do
                existing_chunks+=("$file")
            done < <(find "$chunks_dir" -name "chunk_*.sql" -print0 | sort -zV)
        fi
        
        if [[ ${#existing_chunks[@]} -eq 0 ]]; then
            log "INFO" "No existing chunks found, splitting backup file into 100k record chunks..."
            
            # Split backup file into chunks with improved logic
            chunk_size=100000  # Remove 'local' for proper scope
            chunk_num=1
            current_chunk_records=0
            total_records_split=0
            start_time=$(date +%s)
            
            # Create first chunk file
            current_chunk_file="$chunks_dir/chunk_$(printf "%03d" $chunk_num).sql"
            
            # Function to create new chunk when needed
            create_new_chunk() {
                ((chunk_num++))
                current_chunk_file="$chunks_dir/chunk_$(printf "%03d" $chunk_num).sql"
                current_chunk_records=0
                
                if (( chunk_num % 10 == 1 )); then
                    log "INFO" "Created chunk $chunk_num (total records processed: $total_records_split)"
                fi
            }
            
            # Function to add records to current chunk
            add_records_to_chunk() {
                local insert_statement="$1"
                local record_count="$2"
                
                # Check if we need a new chunk
                if [[ $current_chunk_records -gt 0 ]] && [[ $((current_chunk_records + record_count)) -gt $chunk_size ]]; then
                    create_new_chunk
                fi
                
                # Add the INSERT statement
                echo "$insert_statement" >> "$current_chunk_file"
                current_chunk_records=$((current_chunk_records + record_count))
                total_records_split=$((total_records_split + record_count))
            }
            
            # Process the backup file line by line - SIMPLE APPROACH
            while IFS= read -r line || [[ -n "$line" ]]; do
                # Skip empty lines and comments
                if [[ -z "$line" ]] || [[ "$line" =~ ^[[:space:]]*$ ]] || [[ "$line" =~ ^-- ]] || [[ "$line" =~ ^/\* ]]; then
                    continue
                fi
                
                if [[ "$line" =~ ^INSERT\ INTO ]]; then
                    # Extract INSERT header (table and columns)
                    insert_header=$(echo "$line" | sed 's/VALUES.*/VALUES/')
                    
                    # Extract values and split into individual records
                    values_section=$(echo "$line" | sed 's/.*VALUES[[:space:]]*//' | sed 's/;$//')
                    
                    # Split on "),(" and process each record individually using process substitution
                    while IFS= read -r record_values; do
                        if [[ -n "$record_values" ]]; then
                            # Clean up parentheses
                            record_values=$(echo "$record_values" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
                            if [[ ! "$record_values" =~ ^\( ]]; then
                                record_values="($record_values"
                            fi
                            if [[ ! "$record_values" =~ \)$ ]]; then
                                record_values="$record_values)"
                            fi
                            
                            # Check if we need a new chunk
                            if [[ $current_chunk_records -ge $chunk_size ]]; then
                                create_new_chunk
                            fi
                            
                            # Write individual INSERT statement  
                            echo "$insert_header $record_values;" >> "$current_chunk_file"
                            ((current_chunk_records++))
                            ((total_records_split++))
                            
                            # Progress update every 50k records
                            if (( total_records_split % 50000 == 0 )); then
                                log "INFO" "Processed $total_records_split records, chunk $chunk_num has $current_chunk_records records"
                            fi
                        fi
                    done < <(echo "$values_section" | sed 's/),(/\n/g')
                else
                    # Non-INSERT lines (CREATE TABLE, etc.) go to current chunk
                    echo "$line" >> "$current_chunk_file"
                fi
            done < <(gunzip -c "$data_file")
            
            local split_time=$(($(date +%s) - start_time))
            log "SUCCESS" "Split into $chunk_num chunks with $total_records_split records in ${split_time}s"
            
            # Update existing chunks list with proper error handling
            existing_chunks=()
            if ls "$chunks_dir"/chunk_*.sql >/dev/null 2>&1; then
                while IFS= read -r -d '' file; do
                    existing_chunks+=("$file")
                done < <(find "$chunks_dir" -name "chunk_*.sql" -print0 | sort -zV)
            else
                log "ERROR" "No chunks were created during splitting"
                return 1
            fi
        else
            log "INFO" "Found ${#existing_chunks[@]} existing chunks, resuming processing"
        fi
        
        # Process existing chunks or recreate if needed
        if [[ ${#existing_chunks[@]} -eq 0 ]]; then
            log "ERROR" "No chunks available for processing"
            return 1
        fi
            
            # Process chunks one by one
            local processed_chunks=()
            if ls "$processed_dir"/chunk_*.sql >/dev/null 2>&1; then
                while IFS= read -r -d '' file; do
                    processed_chunks+=("$(basename "$file")")
                done < <(find "$processed_dir" -name "chunk_*.sql" -print0 2>/dev/null)
            fi
        
        local total_chunks=${#existing_chunks[@]}
        local chunks_remaining=$((total_chunks - ${#processed_chunks[@]}))
        
        log "INFO" "Processing plan: $total_chunks total chunks, ${#processed_chunks[@]} already processed, $chunks_remaining remaining"
        
        local chunk_count=${#processed_chunks[@]}  # Start from number of already processed chunks
        local total_records_added=0
        local start_time=$(date +%s)
        
        for chunk_file in "${existing_chunks[@]}"; do
            local chunk_name=$(basename "$chunk_file")
            
            # Skip if already processed
            local is_processed=false
            if [[ ${#processed_chunks[@]} -gt 0 ]]; then
                for processed_chunk in "${processed_chunks[@]}"; do
                    if [[ "$processed_chunk" == "$chunk_name" ]]; then
                        is_processed=true
                        break
                    fi
                done
            fi
            
            if [[ "$is_processed" == "true" ]]; then
                log "INFO" "Chunk $chunk_name already processed (skipping)"
                continue
            fi
            
            ((chunk_count++))
            local before_count=$(get_table_count "$table_name")
            
            # Process chunk
            log "INFO" "Processing $chunk_name ($chunk_count/$total_chunks, $((chunks_remaining - chunk_count)) remaining)"
            
            if mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME" < "$chunk_file" 2>/dev/null; then
                local after_count=$(get_table_count "$table_name")
                local records_added=$((after_count - before_count))
                total_records_added=$((total_records_added + records_added))
                
                # Move to processed directory
                mv "$chunk_file" "$processed_dir/"
                
                local elapsed=$(($(date +%s) - start_time))
                local rate=$((total_records_added / (elapsed + 1)))
                
                log "SUCCESS" "Chunk $chunk_name: +$records_added records, Total added: $total_records_added, DB Total: $after_count, Rate: ${rate}/sec"
                
            else
                log "ERROR" "Chunk $chunk_name failed, stopping restoration"
                cleanup_temp
                return 1
            fi
            
            # Brief pause between chunks to avoid overwhelming the system
            sleep 1
        done
        
        local total_time=$(($(date +%s) - start_time))
        log "SUCCESS" "Chunked restoration completed: $chunk_count chunks processed, $total_records_added records added in ${total_time}s"
        
    else
        # Fresh import - use normal INSERT for better performance
        log "INFO" "Fresh import detected, using direct import method"
        
        if gunzip -c "$data_file" | mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME"; then
            log "SUCCESS" "Direct import completed"
        else
            log "ERROR" "Direct import failed"
            cleanup_temp
            return 1
        fi
    fi
    
    # Final verification
    local final_count=$(get_table_count "$table_name")
    local imported_count=$((final_count - initial_count))
    
    # Stop background monitoring
    stop_background_monitor "$table_name"
    
    log "SUCCESS" "Data restoration complete for $table_name"
    log "INFO" "Records imported: $imported_count"
    log "INFO" "Final total: $final_count records"
    
    # Show H3 coverage for Silver layer
    if [[ "$table_name" == "amisafe_clean_incidents" ]]; then
        log "INFO" "Verifying H3 geospatial coverage..."
        local h3_coverage=$(mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME" \
            -se "SELECT CONCAT(
                'H3:5=', ROUND((SUM(CASE WHEN h3_res_5 IS NOT NULL AND h3_res_5 != '' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1), '%, ',
                'H3:8=', ROUND((SUM(CASE WHEN h3_res_8 IS NOT NULL AND h3_res_8 != '' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1), '%, ',
                'H3:11=', ROUND((SUM(CASE WHEN h3_res_11 IS NOT NULL AND h3_res_11 != '' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1), '%, ',
                'H3:13=', ROUND((SUM(CASE WHEN h3_res_13 IS NOT NULL AND h3_res_13 != '' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1), '%'
            ) FROM $table_name;" 2>/dev/null || echo "Coverage check failed")
        log "INFO" "H3 Coverage: $h3_coverage"
    fi
    
    cleanup_temp
    return 0
}

# Function to restore single table
restore_table() {
    local layer=$1
    local timestamp=$2
    local structure_only=${3:-false}
    local data_only=${4:-false}
    
    local table_name=${AVAILABLE_TABLES[$layer]}
    
    log "INFO" "Starting restoration of $layer layer ($table_name)"
    
    # Verify backup files exist
    if ! verify_backup_files "$table_name" "$timestamp"; then
        return 1
    fi
    
    # Restore structure (unless data-only mode)
    if [[ "$data_only" == "false" ]]; then
        if ! restore_structure "$table_name" "$timestamp"; then
            return 1
        fi
    fi
    
    # Restore data (unless structure-only mode)
    if [[ "$structure_only" == "false" ]]; then
        if ! restore_data "$table_name" "$timestamp"; then
            return 1
        fi
    fi
    
    log "SUCCESS" "Completed restoration of $layer layer"
    return 0
}

# Function to show restoration summary
show_summary() {
    echo ""
    log "INFO" "Restoration Summary"
    echo "===================="
    
    for layer in bronze silver gold reference; do
        if [[ -n "${AVAILABLE_TABLES[$layer]}" ]]; then
            local table_name=${AVAILABLE_TABLES[$layer]}
            local count=$(get_table_count "$table_name")
            
            if [[ "$count" -gt 0 ]]; then
                printf "%-20s: %'d records\n" "$layer ($table_name)" "$count"
            else
                printf "%-20s: Empty/Missing\n" "$layer ($table_name)"
            fi
        fi
    done
    
    echo ""
    log "SUCCESS" "Restoration completed: $(date)"
}

# Main restoration function
main() {
    local layer=""
    local timestamp=""
    local structure_only=false
    local data_only=false
    local incremental=false
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -f|--force)
                export FORCE_MODE=true
                shift
                ;;
            -t|--timestamp)
                timestamp="$2"
                shift 2
                ;;
            -s|--structure)
                structure_only=true
                shift
                ;;
            -d|--data)
                data_only=true
                shift
                ;;
            -i|--incremental)
                incremental=true
                shift
                ;;
            -h|--help)
                show_usage
                exit 0
                ;;
            bronze|silver|gold|reference|all)
                layer="$1"
                shift
                ;;
            *)
                log "ERROR" "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done
    
    # Validate inputs
    if [[ -z "$layer" ]]; then
        log "ERROR" "No layer specified"
        show_usage
        exit 1
    fi
    
    # Set incremental mode if requested
    if [[ "$incremental" == "true" ]]; then
        log "INFO" "Incremental mode enabled - existing records will be skipped"
        export INCREMENTAL_MODE=true
    fi
    
    # Test database connection
    if ! test_db_connection; then
        exit 1
    fi
    
    # Ensure dumps directory exists
    if [[ ! -d "$DUMPS_DIR" ]]; then
        log "ERROR" "Dumps directory not found: $DUMPS_DIR"
        exit 1
    fi
    
    # Handle 'all' option
    if [[ "$layer" == "all" ]]; then
        log "INFO" "Restoring all layers"
        
        for l in bronze reference silver gold; do
            if [[ -n "${AVAILABLE_TABLES[$l]}" ]]; then
                local table_name=${AVAILABLE_TABLES[$l]}
                local auto_timestamp="$timestamp"
                
                # Find latest timestamp if not specified
                if [[ -z "$auto_timestamp" ]]; then
                    auto_timestamp=$(find_latest_timestamp "$table_name")
                    if [[ -z "$auto_timestamp" ]]; then
                        log "WARNING" "No backup found for $l layer ($table_name), skipping..."
                        continue
                    fi
                fi
                
                if ! restore_table "$l" "$auto_timestamp" "$structure_only" "$data_only"; then
                    log "ERROR" "Failed to restore $l layer"
                    exit 1
                fi
            fi
        done
    else
        # Restore single layer
        if [[ -z "${AVAILABLE_TABLES[$layer]}" ]]; then
            log "ERROR" "Invalid layer: $layer"
            show_usage
            exit 1
        fi
        
        local table_name=${AVAILABLE_TABLES[$layer]}
        
        # Find latest timestamp if not specified
        if [[ -z "$timestamp" ]]; then
            timestamp=$(find_latest_timestamp "$table_name")
            if [[ -z "$timestamp" ]]; then
                log "ERROR" "No backup found for $layer layer ($table_name)"
                exit 1
            fi
            log "INFO" "Using latest backup timestamp: $timestamp"
        fi
        
        if ! restore_table "$layer" "$timestamp" "$structure_only" "$data_only"; then
            exit 1
        fi
    fi
    
    # Show summary
    show_summary
    
    # Cleanup any temporary files
    cleanup_temp
}

# Run main function
main "$@"