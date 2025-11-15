#!/bin/bash

# Chunked Restoration Script - Process split backup files incrementally
# Supports resumable restoration with precise progress tracking

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHUNKS_DIR="$SCRIPT_DIR/chunks"

# Database connection parameters
DB_USER="drupal_user"
DB_PASS="drupal_secure_password"
DB_HOST="127.0.0.1"
DB_NAME="amisafe_database"

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# Function to show usage
show_usage() {
    echo ""
    echo "Usage: $0 [OPTIONS] TABLE_NAME TIMESTAMP"
    echo ""
    echo "Restore data from split backup chunks with precise progress tracking"
    echo ""
    echo "Arguments:"
    echo "  TABLE_NAME    Name of the table (e.g., amisafe_clean_incidents)"
    echo "  TIMESTAMP     Backup timestamp (e.g., 20251113_125154)"
    echo ""
    echo "Options:"
    echo "  -r, --resume             Resume from last successful chunk"
    echo "  -s, --start-chunk NUM    Start from specific chunk number"
    echo "  -e, --end-chunk NUM      Stop at specific chunk number"
    echo "  -f, --force              Skip confirmation prompts"
    echo "  --dry-run                Show what would be restored without actually doing it"
    echo "  -h, --help               Show this help"
    echo ""
    echo "Examples:"
    echo "  $0 amisafe_clean_incidents 20251113_125154"
    echo "  $0 --resume amisafe_clean_incidents 20251113_125154"
    echo "  $0 --start-chunk 5 --end-chunk 10 amisafe_clean_incidents 20251113_125154"
    echo ""
}

# Function to test database connection
test_db_connection() {
    if mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -e "USE $DB_NAME; SELECT 1;" >/dev/null 2>&1; then
        log "SUCCESS" "Database connection established"
        return 0
    else
        log "ERROR" "Failed to connect to database"
        return 1
    fi
}

# Function to get table record count
get_table_count() {
    local table_name=$1
    mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME" \
        -se "SELECT COUNT(*) FROM $table_name" 2>/dev/null || echo "0"
}

# Function to read manifest file
read_manifest() {
    local manifest_file=$1
    
    if [[ ! -f "$manifest_file" ]]; then
        log "ERROR" "Manifest file not found: $manifest_file"
        return 1
    fi
    
    # Parse manifest variables
    while IFS='=' read -r key value; do
        case $key in
            table_name) MANIFEST_TABLE="$value" ;;
            timestamp) MANIFEST_TIMESTAMP="$value" ;;
            chunk_size) MANIFEST_CHUNK_SIZE="$value" ;;
            total_chunks) MANIFEST_TOTAL_CHUNKS="$value" ;;
            total_records) MANIFEST_TOTAL_RECORDS="$value" ;;
        esac
    done < <(grep -v '^#' "$manifest_file" | grep '=')
    
    log "INFO" "Manifest: $MANIFEST_TOTAL_CHUNKS chunks, $MANIFEST_TOTAL_RECORDS records, chunk size: $MANIFEST_CHUNK_SIZE"
}

# Function to check which chunks have been processed
check_processed_chunks() {
    local chunks_dir=$1
    local progress_file="$chunks_dir/.restoration_progress"
    
    if [[ -f "$progress_file" ]]; then
        cat "$progress_file" | grep "^COMPLETED:" | cut -d: -f2 | sort -n
    fi
}

# Function to mark chunk as completed
mark_chunk_completed() {
    local chunks_dir=$1
    local chunk_num=$2
    local records_added=$3
    
    local progress_file="$chunks_dir/.restoration_progress"
    echo "COMPLETED:$chunk_num:$(date -Iseconds):$records_added" >> "$progress_file"
}

# Function to restore table structure
restore_structure() {
    local structure_file=$1
    local table_name=$2
    
    log "INFO" "Checking table structure..."
    
    # Check if table exists
    local table_exists=$(mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME" \
        -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='$table_name'" 2>/dev/null || echo "0")
    
    if [[ "$table_exists" -eq 0 ]]; then
        log "INFO" "Creating table structure..."
        if mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME" < "$structure_file"; then
            log "SUCCESS" "Table structure created"
            return 0
        else
            log "ERROR" "Failed to create table structure"
            return 1
        fi
    else
        local current_count=$(get_table_count "$table_name")
        log "INFO" "Table exists with $current_count records"
        return 0
    fi
}

# Function to process a single chunk
process_chunk() {
    local chunk_file=$1
    local chunk_num=$2
    local table_name=$3
    local dry_run=${4:-false}
    
    if [[ ! -f "$chunk_file" ]]; then
        log "WARNING" "Chunk file not found: $chunk_file"
        return 1
    fi
    
    local file_size=$(ls -lh "$chunk_file" | awk '{print $5}')
    local insert_count=$(grep -c "^INSERT INTO" "$chunk_file" 2>/dev/null || echo "0")
    
    log "INFO" "Processing chunk $(printf "%03d" $chunk_num): $file_size, $insert_count INSERT statements"
    
    if [[ "$dry_run" == "true" ]]; then
        log "INFO" "[DRY RUN] Would process $chunk_file"
        return 0
    fi
    
    local before_count=$(get_table_count "$table_name")
    local start_time=$(date +%s)
    
    # Execute chunk with error handling
    if mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -D"$DB_NAME" < "$chunk_file" 2>/dev/null; then
        local after_count=$(get_table_count "$table_name")
        local records_added=$((after_count - before_count))
        local duration=$(($(date +%s) - start_time))
        
        log "SUCCESS" "Chunk $(printf "%03d" $chunk_num) completed in ${duration}s: +$records_added records (Total: $after_count)"
        echo "$records_added"
        return 0
    else
        log "ERROR" "Chunk $(printf "%03d" $chunk_num) failed"
        return 1
    fi
}

# Function to restore from chunks
restore_from_chunks() {
    local table_name=$1
    local timestamp=$2
    local start_chunk=${3:-1}
    local end_chunk=${4:-0}
    local resume_mode=${5:-false}
    local dry_run=${6:-false}
    
    local chunks_dir="$CHUNKS_DIR/${table_name}_${timestamp}"
    local manifest_file="$chunks_dir/manifest.txt"
    local structure_file="$chunks_dir/00_structure.sql"
    
    # Verify chunks directory exists
    if [[ ! -d "$chunks_dir" ]]; then
        log "ERROR" "Chunks directory not found: $chunks_dir"
        log "INFO" "Run split_backup.sh first to create chunks"
        return 1
    fi
    
    # Read manifest
    read_manifest "$manifest_file"
    
    # Set end chunk if not specified
    if [[ $end_chunk -eq 0 ]]; then
        end_chunk=$MANIFEST_TOTAL_CHUNKS
    fi
    
    # Check for resume mode
    local completed_chunks=()
    if [[ "$resume_mode" == "true" ]]; then
        mapfile -t completed_chunks < <(check_processed_chunks "$chunks_dir")
        if [[ ${#completed_chunks[@]} -gt 0 ]]; then
            local last_completed=${completed_chunks[-1]}
            start_chunk=$((last_completed + 1))
            log "INFO" "Resume mode: ${#completed_chunks[@]} chunks already completed, starting from chunk $start_chunk"
        else
            log "INFO" "Resume mode: No previous progress found, starting from chunk 1"
        fi
    fi
    
    # Restore structure if not dry run
    if [[ "$dry_run" == "false" ]]; then
        if ! restore_structure "$structure_file" "$table_name"; then
            return 1
        fi
    fi
    
    # Show restoration plan
    echo ""
    echo "=== RESTORATION PLAN ==="
    log "INFO" "Table: $table_name"
    log "INFO" "Total chunks available: $MANIFEST_TOTAL_CHUNKS"
    log "INFO" "Chunks to process: $start_chunk to $end_chunk"
    log "INFO" "Expected records: ~$MANIFEST_TOTAL_RECORDS"
    
    if [[ ${#completed_chunks[@]} -gt 0 ]]; then
        log "INFO" "Previously completed chunks: ${completed_chunks[*]}"
    fi
    
    if [[ "$dry_run" == "true" ]]; then
        log "INFO" "DRY RUN MODE - No actual changes will be made"
    fi
    
    echo "======================="
    echo ""
    
    # Start restoration
    local initial_count=$(get_table_count "$table_name")
    local total_start_time=$(date +%s)
    local chunks_processed=0
    local total_records_added=0
    local failed_chunks=0
    
    log "INFO" "Starting restoration from chunk $start_chunk to $end_chunk"
    log "INFO" "Initial table count: $initial_count records"
    
    # Process each chunk
    for chunk_num in $(seq $start_chunk $end_chunk); do
        local chunk_file="$chunks_dir/$(printf "%03d" $chunk_num)_data.sql"
        
        # Skip if already completed in resume mode
        if [[ "$resume_mode" == "true" ]] && printf '%s\n' "${completed_chunks[@]}" | grep -Fxq "$chunk_num"; then
            log "INFO" "Chunk $(printf "%03d" $chunk_num) already completed (skipping)"
            continue
        fi
        
        # Process chunk
        if records_added=$(process_chunk "$chunk_file" "$chunk_num" "$table_name" "$dry_run"); then
            ((chunks_processed++))
            if [[ "$dry_run" == "false" ]]; then
                total_records_added=$((total_records_added + records_added))
                mark_chunk_completed "$chunks_dir" "$chunk_num" "$records_added"
            fi
        else
            ((failed_chunks++))
            log "WARNING" "Chunk $chunk_num failed, continuing with next chunk..."
        fi
        
        # Progress summary every 5 chunks
        if (( chunks_processed % 5 == 0 )); then
            local current_count=$(get_table_count "$table_name")
            local elapsed=$(($(date +%s) - total_start_time))
            local progress_pct=$((chunks_processed * 100 / (end_chunk - start_chunk + 1)))
            
            log "INFO" "Progress: $chunks_processed/$((end_chunk - start_chunk + 1)) chunks ($progress_pct%), $total_records_added records added, Current total: $current_count, Time: ${elapsed}s"
        fi
    done
    
    # Final summary
    local final_count=$(get_table_count "$table_name")
    local total_duration=$(($(date +%s) - total_start_time))
    
    echo ""
    echo "=== RESTORATION COMPLETE ==="
    log "SUCCESS" "Processed $chunks_processed chunks in ${total_duration} seconds"
    log "INFO" "Records added: $total_records_added"
    log "INFO" "Final table count: $final_count records"
    if [[ $failed_chunks -gt 0 ]]; then
        log "WARNING" "$failed_chunks chunks failed"
    fi
    log "INFO" "Average rate: $((total_records_added / (total_duration + 1))) records/second"
    
    return 0
}

# Main function
main() {
    local table_name=""
    local timestamp=""
    local start_chunk=1
    local end_chunk=0
    local resume_mode=false
    local force_mode=false
    local dry_run=false
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -r|--resume)
                resume_mode=true
                shift
                ;;
            -s|--start-chunk)
                start_chunk="$2"
                shift 2
                ;;
            -e|--end-chunk)
                end_chunk="$2"
                shift 2
                ;;
            -f|--force)
                force_mode=true
                shift
                ;;
            --dry-run)
                dry_run=true
                shift
                ;;
            -h|--help)
                show_usage
                exit 0
                ;;
            -*)
                log "ERROR" "Unknown option: $1"
                show_usage
                exit 1
                ;;
            *)
                if [[ -z "$table_name" ]]; then
                    table_name="$1"
                elif [[ -z "$timestamp" ]]; then
                    timestamp="$1"
                else
                    log "ERROR" "Too many arguments: $1"
                    show_usage
                    exit 1
                fi
                shift
                ;;
        esac
    done
    
    # Validate required arguments
    if [[ -z "$table_name" ]] || [[ -z "$timestamp" ]]; then
        log "ERROR" "Missing required arguments"
        show_usage
        exit 1
    fi
    
    # Validate chunk numbers
    if [[ ! "$start_chunk" =~ ^[0-9]+$ ]] || [[ $start_chunk -lt 1 ]]; then
        log "ERROR" "Invalid start chunk: $start_chunk"
        exit 1
    fi
    
    if [[ $end_chunk -ne 0 ]] && ([[ ! "$end_chunk" =~ ^[0-9]+$ ]] || [[ $end_chunk -lt $start_chunk ]]); then
        log "ERROR" "Invalid end chunk: $end_chunk"
        exit 1
    fi
    
    echo "=== Chunked Database Restoration ==="
    echo "Restoration started: $(date)"
    
    # Test database connection
    if ! test_db_connection; then
        exit 1
    fi
    
    # Start restoration
    if restore_from_chunks "$table_name" "$timestamp" "$start_chunk" "$end_chunk" "$resume_mode" "$dry_run"; then
        log "SUCCESS" "Chunked restoration completed successfully"
        exit 0
    else
        log "ERROR" "Chunked restoration failed"
        exit 1
    fi
}

# Run main function
main "$@"