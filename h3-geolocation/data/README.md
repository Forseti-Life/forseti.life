# H3 Geolocation Data Directory

## Purpose
Data storage and management for the H3 geolocation pipeline, organized in Bronze-Silver-Gold (Raw-Transform-Final) data warehouse architecture.

## Directory Structure

### `/raw/` - Bronze Layer (Data Lake)
**Purpose**: Original source data files exactly as received, no transformations
**Status**: ✅ **COMPLETE** - 20 CSV files totaling 3.46M Philadelphia crime incidents

**Contents**:
- `incidents_part1_part2.csv` through `incidents_part1_part2 (19).csv`
- 20 CSV files containing Philadelphia crime incident data (2015-2025)
- Original field structure preserved exactly as provided
- No data cleaning, validation, or transformation applied

**Data Volume Analysis**:
```
Total Files: 20 CSV files
Estimated Records: 3,460,000+ crime incidents
Geographic Scope: Philadelphia Police Department incidents
Time Range: 2015-2025 (10+ years of historical data)
```

## Data Pipeline Architecture

### Bronze Layer (Raw Data) ✅
- **Location**: `/data/raw/` directory
- **Status**: Complete with 20 CSV files
- **Processing**: Loaded into `amisafe_raw_incidents` table
- **Records**: 3,406,192 successfully ingested

### Silver Layer (Transform Data) 🔄
- **Location**: Database `amisafe_clean_incidents` table
- **Status**: Architecture complete, processing needed
- **Purpose**: Cleaned, validated, deduplicated data with H3 indexing
- **Dependencies**: Raw layer complete, Transform processor ready

### Gold Layer (Analytics Data) ⏳
- **Location**: Database `amisafe_h3_aggregated` table  
- **Status**: Schema created, aggregation processor pending
- **Purpose**: Multi-resolution H3 hexagon analytics for dashboard queries
- **Dependencies**: Transform layer completion required

## Raw Data Schema

### CSV File Structure
Each CSV file contains the following columns (preserved exactly):
```
- cartodb_id: Unique identifier from CartoDB system
- objectid: Philadelphia PD object identifier
- dc_key: District/complaint key
- dc_dist: Police district number
- dispatch_date_time: Incident timestamp
- lat: Latitude coordinate
- lng: Longitude coordinate  
- location_block: Street address block
- ucr_general: UCR crime classification
- text_general_code: Crime description
- psa: Police service area
- ... (additional metadata fields)
```

### Data Quality Characteristics
- **Coordinate Coverage**: ~95% of records have valid lat/lng coordinates
- **Geographic Bounds**: Philadelphia city limits (39.867-40.138 lat, -75.280 to -74.955 lng)
- **Temporal Coverage**: Continuous data from 2015-2025
- **Missing Data**: Some records lack coordinates, addresses, or classification details

## Processing Status

### ✅ **Raw Data Ingestion Complete**
```sql
-- Current raw data status
SELECT COUNT(*) as total_records FROM amisafe_raw_incidents;
-- Result: 3,406,192 records successfully loaded

SELECT processing_status, COUNT(*) as count 
FROM amisafe_raw_incidents 
GROUP BY processing_status;
-- Result: 3,406,192 records with status 'raw' (ready for Transform processing)
```

### 🔄 **Transform Processing Ready**
- Transform processor SQL parameter issue resolved
- Architecture-compliant Silver layer schema created
- Comprehensive exclusion reporting implemented
- Multi-resolution H3 spatial indexing configured

### ⏳ **Final Layer Aggregation Pending**
- H3 aggregation processor development needed
- Multi-resolution analytics architecture defined
- Performance optimization for dashboard queries required

## Data Validation

### Geographic Validation
- Philadelphia coordinate bounds enforcement
- Invalid coordinate detection and exclusion
- H3 spatial index generation for valid coordinates

### Temporal Validation  
- DateTime format validation and parsing
- Reasonable date range checking (2015-2025)
- Temporal aggregation support for analytics

### Quality Metrics
- Data completeness scoring (0.0-1.0 scale)
- Field validation and standardization
- Duplicate detection across multiple criteria

## File Management

### Raw Data Preservation
- Original CSV files maintained without modification
- Complete audit trail from source to processed data
- Backup and recovery procedures for source data

### Processing Logs
- Batch processing logs with record counts and timestamps  
- Error logging for failed records with detailed reasons
- Performance metrics for processing optimization

### Data Lineage
- Complete traceability from raw CSV to final aggregations
- Processing batch identification and metadata
- Quality metrics tracking through all pipeline stages

## Usage Patterns

### Data Access
```python
# Raw data access for pipeline processing
import pandas as pd
df = pd.read_csv('data/raw/incidents_part1_part2.csv')

# Database access for processed data
import mysql.connector
conn = mysql.connector.connect(host='127.0.0.1', user='drupal_user', 
                               password='drupal_secure_password', 
                               database='theoryofconspiracies_dev')
```

### Pipeline Execution
```bash
# Complete pipeline execution
cd /workspaces/stlouisintegration.com/h3-geolocation
./database/run_amisafe_pipeline.sh

# Individual layer processing
python database/amisafe_transform_processor_v2.py --batch-size 5000
```

## Maintenance

### Data Updates
- New CSV files processed through same Raw layer ingestion
- Incremental processing support for updated datasets
- Historical data preservation during updates

### Performance Optimization
- Batch processing configuration for large datasets
- Memory management for 3.46M record processing
- Database indexing optimization for query performance

### Monitoring
- Processing status monitoring and alerting
- Data quality metrics tracking over time
- Performance benchmarking for pipeline optimization

---

**Last Updated**: November 2025  
**Data Volume**: 3,406,192 crime incidents across 20 CSV files  
**Pipeline Status**: Raw layer complete, Transform layer ready for processing  
**Related Documentation**: See [H3 Pipeline README](../README.md) for complete system overview