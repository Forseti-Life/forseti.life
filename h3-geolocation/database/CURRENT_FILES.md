# AmISafe Database Pipeline - Current Active Files

## Core Pipeline Files (Production Ready)

### Master Control Script
- **`run_pipeline.sh`** - Unified master pipeline script for stlouisintegration_dev database
  - Handles all pipeline operations: setup, processing, monitoring, status
  - Single entry point for all database operations
  - Enhanced error handling and progress tracking

### Database Setup
- **`setup_amisafe_stlouisintegration.sh`** - Database schema setup script for AmISafe tables
  - Creates all required tables with proper indexing
  - Sets up processing status tracking
  - Configures data validation rules

### Data Processing
- **`enhanced_transform_processor_v2.py`** - Advanced data transformation processor (PRIMARY)
  - Raw → Clean layer processing with deduplication
  - Enhanced progress tracking with ETA calculation
  - Batch processing with continuation support (25,000 record batches)
  - Comprehensive error handling and reporting
  
- **`amisafe_processor.py`** - Raw data ingestion (CSV → Database)
  - Processes CSV files from `/h3-geolocation/data/raw/`
  - Initial data validation and cleansing
  - Writes to `amisafe_raw_incidents` table
  
- **`amisafe_aggregator.py`** - Final aggregation (Clean → H3 Aggregations)
  - Creates H3 hexagon aggregations at multiple resolutions
  - Writes to `amisafe_h3_aggregated` table
  - Optimized for large-scale spatial aggregation

### Monitoring & Utilities
- **`monitor_processing.py`** - Comprehensive monitoring system combining:
  - Real-time database status queries with accurate progress tracking
  - Process detection and status monitoring
  - Error scanning and analysis from log files
  - Audit report detection and listing
  - Multiple monitoring modes: continuous, quick status, error-only

## Database Configuration
- **Database:** `stlouisintegration_dev`
- **Tables:** 
  - `amisafe_raw_incidents` (Bronze layer)
  - `amisafe_clean_incidents` (Silver layer) 
  - `amisafe_h3_aggregated` (Gold layer)

## Processing Pipeline (Simplified)
```
CSV Files → Raw Ingestion → Enhanced Transform → H3 Aggregation → Dashboard
   ↓              ↓               ↓                  ↓           ↓
Bronze         Silver           Gold             Analytics   Visualization
(amisafe_raw)  (temp processing) (amisafe_clean)  (amisafe_h3) (AmISafe UI)
```

## Current Processing Status
- **Raw Data**: 5,053,523 records loaded (CSV ingestion complete)
- **Transform Processing**: 152,800+ records processed (3.02% complete, continuing)
- **Enhanced Features**: Progress tracking, ETA calculation, 25K batch processing
- **Next Stage**: H3 aggregation (pending transform completion)

## Usage Commands
```bash
# Navigate to database directory
cd /workspaces/stlouisintegration.com/h3-geolocation/database

# Run full pipeline (recommended)
bash run_pipeline.sh full

# Individual operations
bash run_pipeline.sh setup         # Database setup only
bash run_pipeline.sh status        # Check processing status
bash run_pipeline.sh monitor       # Monitor current processing

# Direct processor usage (advanced)
python enhanced_transform_processor_v2.py --status-check
python enhanced_transform_processor_v2.py --continue-processing
python enhanced_transform_processor_v2.py --continue-processing --batch-size 50000

# Comprehensive monitoring options
python monitor_processing.py              # Continuous monitoring with all features
python monitor_processing.py --quick      # Quick status check and exit
python monitor_processing.py --errors-only # Error scan only
```

## Archived/Deprecated Files
- **`archive/experimental_processors/`** - Previous processor versions and experiments
- **`archive/old_scripts/`** - Deprecated pipeline scripts (wrong database targets)
- **`archive/logs/`** - Historical processing logs and results

## Performance Notes
- **Batch Size**: Optimized to 25,000 records per batch for enhanced throughput
- **Progress Tracking**: Real-time ETA calculation and percentage completion
- **Error Handling**: Comprehensive error recovery with continuation support
- **Memory Management**: Efficient pandas operations with proper connection handling

---

**Last Updated:** November 10, 2025  
**Pipeline Status:** Active processing (3.02% complete, ~4.9M records remaining)  
**Current Focus:** Enhanced transform processor with improved performance and tracking