# H3 Geolocation Framework for AmISafe Crime Mapping

A high-performance geospatial crime data processing framework using H3 hexagonal indexing for the AmISafe crime monitoring system.

## Overview
This framework processes 3.4M+ Philadelphia crime incidents through a modern 3-layer data warehouse architecture, creating H3 hexagonal aggregations for fast geospatial queries and crime hotspot analysis.

## Quick Start

```bash
# 1. Navigate and activate environment
cd /workspaces/stlouisintegration.com/h3-geolocation
source h3-env/bin/activate

# 2. Run the complete pipeline
cd database  
bash run_amisafe_pipeline_stlouisintegration.sh full

# 3. Monitor processing (optional)
python monitor_processing.py
```

## Core Components
- **Main Pipeline:** `database/run_amisafe_pipeline_stlouisintegration.sh`
- **Data Processor:** `database/amisafe_processor.py` (CSV → Clean Data)
- **H3 Aggregator:** `database/amisafe_aggregator.py` (Clean Data → H3 Hexagons)
- **Database Setup:** `database/setup_amisafe_stlouisintegration.sh`

## Data Flow
```
CSV Files (20 files, 673MB+) → amisafe_processor.py → Clean Data → amisafe_aggregator.py → H3 Aggregations
```

## Database Tables
- **`amisafe_raw_incidents`** - Raw CSV data (Bronze layer)
- **`amisafe_clean_incidents`** - Validated incidents (Silver layer)  
- **`amisafe_h3_aggregated`** - H3 hexagon summaries (Gold layer)

## Key Features
- ✅ **3.4M+ crime incidents** processing capability
- ✅ **H3 geospatial indexing** for fast queries
- ✅ **Multi-resolution analysis** (H3 levels 8-13)
- ✅ **Resume processing** from interruptions
- ✅ **Real-time monitoring** and progress tracking
- ✅ **Integrated with Drupal** AmISafe module

## Pipeline Commands
```bash
# Full pipeline (recommended)
bash run_amisafe_pipeline_stlouisintegration.sh full

# Individual steps
bash run_amisafe_pipeline_stlouisintegration.sh setup     # Database setup only
bash run_amisafe_pipeline_stlouisintegration.sh process   # Data processing only
bash run_amisafe_pipeline_stlouisintegration.sh aggregate # H3 aggregation only
bash run_amisafe_pipeline_stlouisintegration.sh stats     # Show statistics
```

## Technical Requirements
- **Python 3.8+** with H3 4.3.1, pandas, mysql-connector-python
- **MySQL 8.0+** with stlouisintegration_dev database
- **System packages:** python3-dev, build-essential, libgeos-dev, libproj-dev, libgdal-dev

## Directory Structure
```
h3-geolocation/
├── database/                          # Main pipeline scripts
│   ├── run_amisafe_pipeline_stlouisintegration.sh  # MAIN PIPELINE
│   ├── amisafe_processor.py           # Data processing
│   ├── amisafe_aggregator.py          # H3 aggregation
│   └── setup_amisafe_stlouisintegration.sh        # Database setup
├── data/raw/                          # 20 CSV files (673MB+)
├── h3-env/                           # Python virtual environment
├── config/mysql_config.json          # Database configuration
└── ARCHITECTURE.md                   # Detailed technical documentation
```

## Support
- **Architecture Details:** See `ARCHITECTURE.md`
- **Current Files:** Check `database/CURRENT_FILES.md`
- **Issues:** Check logs in `database/` directory

---
**Version:** H3 4.3.1 | **Database:** stlouisintegration_dev | **Updated:** November 2025
