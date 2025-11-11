# AmISafe Database Setup Consolidation

## Overview

All database setup, export, and import scripts have been consolidated and archived to provide a single source of truth for database operations.

**Primary Script:** `setup_consolidated.sh` - Master database setup script that replaces all previous setup scripts.

This consolidation eliminates redundancy, ensures consistency, and provides a unified approach to database schema creation.

## New Structure

### Primary Script
- **`setup_consolidated.sh`** - Master database setup script
  - Creates all tables with ObjectID-based processing
  - Includes sample data for testing
  - Supports both `stlouisintegration_dev` and `theoryofconspiracies_dev` databases
  - Complete 3-layer ETL pipeline (Bronze/Silver/Gold)
  - H3 geospatial indexing at multiple resolutions
  - UCR crime code reference tables

### Legacy Scripts (Archived)
All previous setup, export, and import scripts have been moved to `legacy/` folder:

**Setup Scripts:**
- `setup_amisafe_stlouisintegration_original.sh` - Original comprehensive setup
- `setup-amisafe-db.sh` - Simple sample data loader
- `setup-amisafe-tables.sh` - Basic test tables
- `setup-h3-aggregated.sh` - H3 aggregation tables
- `setup-h3-aggregated-enhanced.sh` - Enhanced H3 tables
- `setup_database.sh` - Database creation wrapper
- `setup.sh` - H3 pipeline setup
- `run_database_setup.sh` - Quick setup wrapper

**Export Scripts:**
- `export_amisafe_data.sh` - Drush-based export with CSV/SQL options
- `export_amisafe_pure.sh` - Pure MySQL export using mysqldump
- `export_amisafe_simple.sh` - Simplified drush export

**Import Scripts:**
- `import_amisafe_data.sh` - Drush-based import from backups
- `import_amisafe_pure.sh` - Pure MySQL import from SQL dumps
- `import_amisafe_to_stlouisintegration.sh` - Cross-database migration

## Usage

### Basic Setup
```bash
# Use default database (stlouisintegration_dev)
./setup_consolidated.sh

# Specify custom database name
./setup_consolidated.sh my_custom_database
```

### Environment Variables
You can override default connection settings:
```bash
export DB_HOST="localhost"
export DB_USER="my_user"  
export DB_PASSWORD="my_password"
./setup_consolidated.sh
```

## Key Features

### ObjectID-Based Processing
- Primary business identifier: `objectid` (unique across all 3.4M records)
- Legacy support: `cartodb_id` (75% NULL values)
- Incident ID format: `obj_{objectid}`

### Database Schema
1. **Raw Layer (Bronze)**: `amisafe_raw_incidents`
   - Immutable source data
   - All original CSV fields preserved
   - ObjectID unique constraints

2. **Transform Layer (Silver)**: `amisafe_clean_incidents`
   - Cleaned, validated data
   - H3 spatial indexing (resolutions 6-10)
   - Crime categorization

3. **Final Layer (Gold)**: `amisafe_h3_aggregated`
   - Pre-computed analytics
   - Hierarchical H3 hexagons
   - Temporal patterns

4. **Reference Data**: `amisafe_ucr_codes`
   - UCR crime code definitions
   - Severity levels and categories
   - Color coding for visualization

### Sample Data
The script includes comprehensive sample data for testing:
- 10+ raw incidents with realistic ObjectIDs
- Processed clean incidents with H3 indexing
- Multi-resolution H3 aggregations (res 8-9)
- Complete UCR code reference data

## Migration from Legacy Scripts

### Existing Databases
If you have existing databases created with legacy scripts:

1. **Backup existing data** (use legacy export scripts if needed):
   ```bash
   # From legacy folder
   ./legacy/export_amisafe_pure.sh
   ```

2. **Run consolidated setup**:
   ```bash
   ./setup_consolidated.sh your_database
   ```

3. **Import existing data** (if needed):
   ```bash
   # From legacy folder  
   ./legacy/import_amisafe_pure.sh
   ```

### Script References
Any scripts that previously called legacy setup scripts should now use:
```bash
# Old way
./setup_amisafe_stlouisintegration.sh

# New way (symlinked to consolidated script)
./setup_amisafe_stlouisintegration.sh  # Still works via symlink
# OR directly
../script/database/setup_consolidated.sh
```

## Benefits of Consolidation

1. **Single Source of Truth**: One script maintains all database schema
2. **Consistency**: Eliminates variations between different setup scripts
3. **ObjectID Support**: Proper constraints and indexing for objectid-based processing
4. **Comprehensive**: Includes all features from previous scripts
5. **Maintainable**: Easier to update and modify schema changes
6. **Testable**: Includes sample data for immediate testing
7. **Archive Preservation**: Legacy scripts preserved for reference

## Processing Pipeline Compatibility

The consolidated database schema is fully compatible with:
- `enhanced_transform_processor_v2.py` (ObjectID-based processing)
- `amisafe_aggregator.py` (H3 aggregation)
- `amisafe_processor.py` (Raw data ingestion)

## Verification

After running the setup script, verify the installation:

```bash
# Check table creation
mysql -u drupal_user -p your_database -e "SHOW TABLES LIKE 'amisafe_%';"

# Verify ObjectID constraints
mysql -u drupal_user -p your_database -e "
SELECT table_name, constraint_name, column_name 
FROM information_schema.key_column_usage 
WHERE table_schema = 'your_database' 
AND column_name = 'objectid';
"

# Check sample data
mysql -u drupal_user -p your_database -e "
SELECT COUNT(*) as raw_count FROM amisafe_raw_incidents;
SELECT COUNT(*) as clean_count FROM amisafe_clean_incidents;
SELECT COUNT(*) as h3_count FROM amisafe_h3_aggregated;
SELECT COUNT(*) as ucr_count FROM amisafe_ucr_codes;
"
```

## Data Management

### Export/Import Operations
For data backup and migration, legacy scripts are preserved in the `legacy/` folder:

- **Export existing data**: Use `legacy/export_amisafe_pure.sh`
- **Import data**: Use `legacy/import_amisafe_pure.sh`
- **Cross-database migration**: Use `legacy/import_amisafe_to_stlouisintegration.sh`

### Schema Updates
Future schema changes should be made in the consolidated script to maintain consistency across all deployments.

## Support

For issues or questions about the consolidated setup:
1. Check the script output for detailed error messages
2. Verify MySQL connection and permissions
3. Review the `legacy/` folder for reference to previous implementations
4. Ensure ObjectID unique constraints are properly applied
5. Use legacy export/import scripts for data migration if needed

The consolidated approach ensures all future database setups follow the same standardized pattern optimized for ObjectID-based processing and the complete ETL pipeline, while preserving access to legacy functionality for data migration and reference purposes.