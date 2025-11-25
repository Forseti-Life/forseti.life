#!/bin/bash
# Download Philadelphia Crime Incidents Data (2006-2024)
# Usage: Run this script from anywhere, it will use its own location

set -e

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

# Base URL for OpenDataPhilly Carto API
BASE_URL="https://phl.carto.com/api/v2/sql"

echo "Working directory: $SCRIPT_DIR"

echo "================================================================"
echo "Downloading Philadelphia Crime Incidents (2006-2024)"
echo "================================================================"

# Function to download data for a specific year
download_year() {
    local year=$1
    local next_year=$((year + 1))
    local filename="crime${year}.csv"
    
    echo ""
    echo ">>> Downloading ${year} crime data..."
    
    # SQL query with lat/lng extraction
    local query="SELECT * , ST_Y(the_geom) AS lat, ST_X(the_geom) AS lng FROM incidents_part1_part2 WHERE dispatch_date_time >= '${year}-01-01' AND dispatch_date_time < '${next_year}-01-01'"
    
    # URL encode the query
    local encoded_query=$(echo "$query" | sed 's/ /%20/g' | sed 's/,/%2C/g' | sed "s/'/%27/g" | sed 's/(/%28/g' | sed 's/)/%29/g' | sed 's/>/%3E/g' | sed 's/</%3C/g')
    
    # Download with wget
    wget -O "$filename" "${BASE_URL}?filename=incidents_part1_part2&format=csv&q=${encoded_query}"
    
    # Verify download
    if [[ -f "$filename" ]]; then
        local lines=$(wc -l < "$filename")
        local size=$(du -h "$filename" | cut -f1)
        echo "✅ ${year}: ${lines} lines, ${size}"
    else
        echo "❌ Failed to download ${year}"
    fi
}

# Download data for each year from 2006 to 2024
for year in {2006..2024}; do
    download_year $year
    sleep 2  # Be nice to the API
done

echo ""
echo "================================================================"
echo "Download Complete!"
echo "================================================================"
ls -lh *.csv | awk '{print $9, $5}'
echo ""
echo "Total files: $(ls -1 *.csv | wc -l)"
echo "Total size: $(du -sh . | cut -f1)"
echo ""
echo "Next steps:"
echo "  1. Set ownership: chown -R www-data:www-data $SCRIPT_DIR"
echo "  2. Run ETL pipeline: cd ${SCRIPT_DIR}/../../database/etl && python3 amisafe_processor.py"
