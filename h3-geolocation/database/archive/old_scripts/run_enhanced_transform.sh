#!/bin/bash

# Enhanced Transform Processor Launch Script
# This script provides easy access to the enhanced transform processor
# with integrated validation and reporting capabilities.

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROCESSOR_PATH="${SCRIPT_DIR}/enhanced_transform_processor.py"

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default configuration
MYSQL_HOST="127.0.0.1"
MYSQL_USER="drupal_user"
MYSQL_PASSWORD="drupal_secure_password"
MYSQL_DATABASE="theoryofconspiracies_dev"
BATCH_SIZE=10000
RESUME_FROM=0

print_header() {
    echo -e "${BLUE}================================================================================================${NC}"
    echo -e "${BLUE}                           ENHANCED AMISAFE TRANSFORM PROCESSOR${NC}"
    echo -e "${BLUE}                        WITH INTEGRATED VALIDATION REPORTING${NC}"
    echo -e "${BLUE}================================================================================================${NC}"
    echo -e "${GREEN}Launch Time:${NC} $(date '+%Y-%m-%d %H:%M:%S')"
    echo -e "${GREEN}Processor:${NC} ${PROCESSOR_PATH}"
    echo -e "${BLUE}================================================================================================${NC}"
}

print_usage() {
    echo -e "${YELLOW}Usage:${NC}"
    echo "  $0 [OPTIONS] COMMAND"
    echo ""
    echo -e "${YELLOW}Commands:${NC}"
    echo "  full-process      - Run complete transform processing with validation"
    echo "  resume-process    - Resume processing from specified offset"
    echo "  validation-only   - Run validation analysis without processing"
    echo "  status           - Check current processing status"
    echo "  reports          - List recent processing reports"
    echo ""
    echo -e "${YELLOW}Options:${NC}"
    echo "  --batch-size SIZE     Batch size for processing (default: 10000)"
    echo "  --resume-from OFFSET  Resume processing from offset (default: 0)"
    echo "  --mysql-host HOST     MySQL host (default: 127.0.0.1)"
    echo "  --mysql-user USER     MySQL user (default: drupal_user)"
    echo "  --mysql-password PASS MySQL password (default: drupal_secure_password)"
    echo "  --mysql-database DB   MySQL database (default: theoryofconspiracies_dev)"
    echo "  --help               Show this help message"
    echo ""
    echo -e "${YELLOW}Examples:${NC}"
    echo "  $0 full-process                    # Complete processing with default settings"
    echo "  $0 resume-process --resume-from 500000  # Resume from record 500,000"
    echo "  $0 validation-only                 # Validation analysis only"
    echo "  $0 status                          # Check processing status"
}

check_dependencies() {
    echo -e "${BLUE}🔍 Checking dependencies...${NC}"
    
    # Check Python
    if ! command -v python3 &> /dev/null; then
        echo -e "${RED}❌ Python3 not found${NC}"
        return 1
    fi
    
    # Check processor file
    if [ ! -f "${PROCESSOR_PATH}" ]; then
        echo -e "${RED}❌ Enhanced processor not found: ${PROCESSOR_PATH}${NC}"
        return 1
    fi
    
    # Check MySQL connection
    echo -e "${BLUE}🔗 Testing MySQL connection...${NC}"
    mysql -h"${MYSQL_HOST}" -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" -e "USE ${MYSQL_DATABASE}; SELECT 'Connection successful' as status;" 2>/dev/null
    if [ $? -ne 0 ]; then
        echo -e "${RED}❌ MySQL connection failed${NC}"
        return 1
    fi
    
    echo -e "${GREEN}✅ All dependencies satisfied${NC}"
    return 0
}

get_processing_status() {
    echo -e "${BLUE}📊 Current Processing Status:${NC}"
    
    mysql -h"${MYSQL_HOST}" -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" -e "
    USE ${MYSQL_DATABASE};
    
    SELECT 
        'Raw Records' as Layer,
        COUNT(*) as Total_Records,
        COUNT(CASE WHEN processing_status = 'raw' THEN 1 END) as Unprocessed,
        COUNT(CASE WHEN processing_status = 'processed' THEN 1 END) as Processed
    FROM amisafe_raw_incidents
    
    UNION ALL
    
    SELECT 
        'Transform Records' as Layer,
        COUNT(*) as Total_Records,
        NULL as Unprocessed,
        NULL as Processed
    FROM amisafe_clean_incidents;
    " 2>/dev/null
    
    echo ""
    echo -e "${BLUE}📈 Processing Progress:${NC}"
    mysql -h"${MYSQL_HOST}" -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" -e "
    USE ${MYSQL_DATABASE};
    
    SELECT 
        ROUND((SELECT COUNT(*) FROM amisafe_clean_incidents) / 
              (SELECT COUNT(*) FROM amisafe_raw_incidents) * 100, 2) as 'Progress_%',
        (SELECT COUNT(*) FROM amisafe_raw_incidents WHERE processing_status = 'raw') as 'Remaining_Records';
    " 2>/dev/null
}

list_reports() {
    echo -e "${BLUE}📋 Recent Processing Reports:${NC}"
    
    REPORTS_DIR="${SCRIPT_DIR}/../reports/data_processing"
    if [ -d "${REPORTS_DIR}" ]; then
        find "${REPORTS_DIR}" -name "*.json" -type f -exec ls -lh {} \; | sort -k6,7 -r | head -10
    else
        echo -e "${YELLOW}⚠️  No reports directory found${NC}"
    fi
}

run_full_processing() {
    echo -e "${GREEN}🚀 Starting full enhanced transform processing...${NC}"
    echo -e "${BLUE}Configuration:${NC}"
    echo "  MySQL Host: ${MYSQL_HOST}"
    echo "  MySQL Database: ${MYSQL_DATABASE}"
    echo "  Batch Size: ${BATCH_SIZE}"
    echo "  Resume From: ${RESUME_FROM}"
    echo ""
    
    python3 "${PROCESSOR_PATH}" \
        --mysql-host "${MYSQL_HOST}" \
        --mysql-user "${MYSQL_USER}" \
        --mysql-password "${MYSQL_PASSWORD}" \
        --mysql-database "${MYSQL_DATABASE}" \
        --batch-size "${BATCH_SIZE}" \
        --resume-from "${RESUME_FROM}" \
        --full-processing
}

run_resume_processing() {
    echo -e "${GREEN}🔄 Resuming enhanced transform processing...${NC}"
    echo -e "${BLUE}Configuration:${NC}"
    echo "  MySQL Host: ${MYSQL_HOST}"
    echo "  MySQL Database: ${MYSQL_DATABASE}"
    echo "  Batch Size: ${BATCH_SIZE}"
    echo "  Resume From: ${RESUME_FROM}"
    echo ""
    
    python3 "${PROCESSOR_PATH}" \
        --mysql-host "${MYSQL_HOST}" \
        --mysql-user "${MYSQL_USER}" \
        --mysql-password "${MYSQL_PASSWORD}" \
        --mysql-database "${MYSQL_DATABASE}" \
        --batch-size "${BATCH_SIZE}" \
        --resume-from "${RESUME_FROM}" \
        --full-processing
}

run_validation_only() {
    echo -e "${GREEN}🔍 Running validation analysis only...${NC}"
    
    python3 "${PROCESSOR_PATH}" \
        --mysql-host "${MYSQL_HOST}" \
        --mysql-user "${MYSQL_USER}" \
        --mysql-password "${MYSQL_PASSWORD}" \
        --mysql-database "${MYSQL_DATABASE}" \
        --validation-only
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --batch-size)
            BATCH_SIZE="$2"
            shift 2
            ;;
        --resume-from)
            RESUME_FROM="$2"
            shift 2
            ;;
        --mysql-host)
            MYSQL_HOST="$2"
            shift 2
            ;;
        --mysql-user)
            MYSQL_USER="$2"
            shift 2
            ;;
        --mysql-password)
            MYSQL_PASSWORD="$2"
            shift 2
            ;;
        --mysql-database)
            MYSQL_DATABASE="$2"
            shift 2
            ;;
        --help)
            print_header
            print_usage
            exit 0
            ;;
        full-process|resume-process|validation-only|status|reports)
            COMMAND="$1"
            shift
            ;;
        *)
            echo -e "${RED}❌ Unknown option: $1${NC}"
            print_usage
            exit 1
            ;;
    esac
done

# Main execution
print_header

# Check if command provided
if [ -z "${COMMAND}" ]; then
    echo -e "${RED}❌ No command specified${NC}"
    print_usage
    exit 1
fi

# Check dependencies for processing commands
if [[ "${COMMAND}" == "full-process" || "${COMMAND}" == "resume-process" || "${COMMAND}" == "validation-only" ]]; then
    if ! check_dependencies; then
        echo -e "${RED}❌ Dependency check failed${NC}"
        exit 1
    fi
fi

# Execute command
case "${COMMAND}" in
    "full-process")
        run_full_processing
        ;;
    "resume-process")
        run_resume_processing
        ;;
    "validation-only")
        run_validation_only
        ;;
    "status")
        get_processing_status
        ;;
    "reports")
        list_reports
        ;;
    *)
        echo -e "${RED}❌ Unknown command: ${COMMAND}${NC}"
        print_usage
        exit 1
        ;;
esac

echo -e "${BLUE}================================================================================================${NC}"
echo -e "${GREEN}Operation completed at:${NC} $(date '+%Y-%m-%d %H:%M:%S')"
echo -e "${BLUE}================================================================================================${NC}"