#!/bin/bash

# Backup File Splitter - Breaks large SQL dumps into manageable chunks
# Supports resumable restoration by processing one chunk at a time

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DUMPS_DIR="$SCRIPT_DIR/dumps"
CHUNKS_DIR="$SCRIPT_DIR/chunks"
RECORDS_PER_CHUNK=50000  # Adjust based on memory and performance needs

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
    echo "Split large backup files into manageable chunks for resumable restoration"
    echo ""
    echo "Arguments:"
    echo "  TABLE_NAME    Name of the table (e.g., amisafe_clean_incidents)"
    echo "  TIMESTAMP     Backup timestamp (e.g., 20251113_125154)"
    echo ""
    echo "Options:"
    echo "  -s, --chunk-size SIZE    Records per chunk (default: $RECORDS_PER_CHUNK)"
    echo "  -f, --force              Overwrite existing chunks"
    echo "  -c, --clean              Clean up existing chunks first"
    echo "  -h, --help               Show this help"
    echo ""
    echo "Examples:"
    echo "  $0 amisafe_clean_incidents 20251113_125154"
    echo "  $0 -s 25000 amisafe_clean_incidents 20251113_125154"
    echo "  $0 --clean --force amisafe_clean_incidents 20251113_125154"
    echo ""
}

# Function to estimate record count in compressed file
estimate_record_count() {
    local data_file=$1
    
    log "INFO" "Estimating record count in backup file..."
    
    # Sample first 1MB to estimate INSERT statements and records per statement
    local sample_size=1048576  # 1MB
    local sample_data=$(gunzip -c "$data_file" | head -c $sample_size)
    
    # Count INSERT statements in sample
    local insert_count=$(echo "$sample_data" | grep -c "^INSERT INTO" || echo "0")
    
    if [[ $insert_count -eq 0 ]]; then
        log "WARNING" "No INSERT statements found in sample, using fallback method"
        return 0
    fi
    
    # Count records in first INSERT statement (using ),( pattern)
    local first_insert=$(echo "$sample_data" | grep "^INSERT INTO" | head -1)
    local records_per_insert=$(($(echo "$first_insert" | grep -o "),(" | wc -l) + 1))
    
    # Get total file size
    local total_size=$(gunzip -l "$data_file" | tail -1 | awk '{print $2}')
    
    # Estimate total records
    local estimated_inserts=$((total_size * insert_count / sample_size))
    local estimated_records=$((estimated_inserts * records_per_insert))
    
    log "INFO" "Estimated ~$estimated_records total records in backup ($estimated_inserts INSERT statements)"
    echo "$estimated_records"
}

# Function to split backup file into chunks
split_backup_file() {
    local table_name=$1
    local timestamp=$2
    local chunk_size=$3
    local force_mode=${4:-false}
    
    local data_file="$DUMPS_DIR/${table_name}_data_${timestamp}.sql.gz"
    local structure_file="$DUMPS_DIR/${table_name}_structure_${timestamp}.sql"
    
    # Verify files exist
    if [[ ! -f "$data_file" ]]; then
        log "ERROR" "Data file not found: $data_file"
        return 1
    fi
    
    if [[ ! -f "$structure_file" ]]; then
        log "ERROR" "Structure file not found: $structure_file"
        return 1
    fi
    
    # Create chunks directory
    local table_chunks_dir="$CHUNKS_DIR/${table_name}_${timestamp}"
    
    if [[ -d "$table_chunks_dir" ]] && [[ "$force_mode" == "false" ]]; then
        log "ERROR" "Chunks directory already exists: $table_chunks_dir (use --force to overwrite)"
        return 1
    fi
    
    mkdir -p "$table_chunks_dir"
    
    # Copy structure file to chunks directory
    cp "$structure_file" "$table_chunks_dir/00_structure.sql"
    log "SUCCESS" "Copied table structure to chunks directory"
    
    # Get estimated record count
    local estimated_total=$(estimate_record_count "$data_file")
    local estimated_chunks=$((estimated_total / chunk_size + 1))
    log "INFO" "Planning to create approximately $estimated_chunks chunks of $chunk_size records each"
    
    # Split the data file
    log "INFO" "Starting to split backup file into chunks..."
    
    local chunk_num=1
    local current_chunk_records=0
    local total_records_processed=0
    local start_time=$(date +%s)
    
    # Create first chunk file
    local current_chunk_file="$table_chunks_dir/$(printf "%03d" $chunk_num)_data.sql"
    
    # Process the backup file line by line
    gunzip -c "$data_file" | while IFS= read -r line; do
        if [[ "$line" =~ ^INSERT\ INTO ]]; then
            # Count records in this INSERT statement
            local records_in_insert=$(($(echo "$line" | grep -o "),(" | wc -l) + 1))
            
            # Check if adding this INSERT would exceed chunk size
            if [[ $current_chunk_records -gt 0 ]] && [[ $((current_chunk_records + records_in_insert)) -gt $chunk_size ]]; then
                # Start new chunk
                ((chunk_num++))
                current_chunk_file="$table_chunks_dir/$(printf "%03d" $chunk_num)_data.sql"
                current_chunk_records=0
                
                log "INFO" "Started chunk $chunk_num (total records processed: $total_records_processed)"
            fi
            
            # Add INSERT to current chunk
            echo "$line" >> "$current_chunk_file"
            current_chunk_records=$((current_chunk_records + records_in_insert))
            total_records_processed=$((total_records_processed + records_in_insert))
            
            # Progress update every 100k records
            if (( total_records_processed % 100000 == 0 )); then
                local elapsed=$(($(date +%s) - start_time))
                local rate=$((total_records_processed / (elapsed + 1)))
                log "INFO" "Split progress: $total_records_processed records into $chunk_num chunks, Rate: ${rate} records/sec"
            fi
            
        else
            # Non-INSERT lines (SET statements, comments) go to current chunk
            if [[ -n "$line" ]] && [[ ! "$line" =~ ^-- ]] && [[ ! "$line" =~ ^/\* ]]; then
                echo "$line" >> "$current_chunk_file"
            fi
        fi
    done
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    # Create manifest file
    local manifest_file="$table_chunks_dir/manifest.txt"
    echo "# Backup Split Manifest" > "$manifest_file"
    echo "table_name=$table_name" >> "$manifest_file"
    echo "timestamp=$timestamp" >> "$manifest_file"
    echo "chunk_size=$chunk_size" >> "$manifest_file"
    echo "total_chunks=$chunk_num" >> "$manifest_file"
    echo "total_records=$total_records_processed" >> "$manifest_file"
    echo "split_duration=${duration}s" >> "$manifest_file"
    echo "created=$(date -Iseconds)" >> "$manifest_file"
    
    # List chunk files with sizes
    echo "" >> "$manifest_file"
    echo "# Chunk Files:" >> "$manifest_file"
    ls -la "$table_chunks_dir"/*.sql | while read -r line; do
        echo "# $line" >> "$manifest_file"
    done
    
    log "SUCCESS" "Backup split completed in ${duration} seconds"
    log "INFO" "Created $chunk_num chunks with $total_records_processed total records"
    log "INFO" "Chunks directory: $table_chunks_dir"
    log "INFO" "Manifest file: $manifest_file"
    
    # Show chunk summary
    echo ""
    echo "=== CHUNK SUMMARY ==="
    printf "%-15s %-15s %-10s\n" "Chunk File" "Size" "Records (est)"
    echo "--------------------------------------------"
    
    for chunk_file in "$table_chunks_dir"/*.sql; do
        if [[ -f "$chunk_file" ]]; then
            local filename=$(basename "$chunk_file")
            local size=$(ls -lh "$chunk_file" | awk '{print $5}')
            local est_records=""
            
            if [[ "$filename" != "00_structure.sql" ]]; then
                est_records=$(grep -c "^INSERT INTO" "$chunk_file" 2>/dev/null || echo "0")
                est_records="${est_records} INSERT stmt"
            else
                est_records="structure"
            fi
            
            printf "%-15s %-15s %-10s\n" "$filename" "$size" "$est_records"
        fi
    done
    
    echo ""
    log "INFO" "Use the chunked restoration script to process these files incrementally"
    
    return 0
}

# Main function
main() {
    local table_name=""
    local timestamp=""
    local chunk_size=$RECORDS_PER_CHUNK
    local force_mode=false
    local clean_mode=false
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -s|--chunk-size)
                chunk_size="$2"
                shift 2
                ;;
            -f|--force)
                force_mode=true
                shift
                ;;
            -c|--clean)
                clean_mode=true
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
    
    # Validate chunk size
    if [[ ! "$chunk_size" =~ ^[0-9]+$ ]] || [[ $chunk_size -lt 1000 ]]; then
        log "ERROR" "Invalid chunk size: $chunk_size (minimum: 1000)"
        exit 1
    fi
    
    echo "=== Backup File Splitter ==="
    echo "Split started: $(date)"
    log "INFO" "Table: $table_name, Timestamp: $timestamp, Chunk size: $chunk_size records"
    
    # Clean existing chunks if requested
    if [[ "$clean_mode" == "true" ]]; then
        local table_chunks_dir="$CHUNKS_DIR/${table_name}_${timestamp}"
        if [[ -d "$table_chunks_dir" ]]; then
            log "INFO" "Cleaning existing chunks directory: $table_chunks_dir"
            rm -rf "$table_chunks_dir"
        fi
    fi
    
    # Split the backup file
    if split_backup_file "$table_name" "$timestamp" "$chunk_size" "$force_mode"; then
        log "SUCCESS" "Backup splitting completed successfully"
        exit 0
    else
        log "ERROR" "Backup splitting failed"
        exit 1
    fi
}

# Run main function
main "$@"