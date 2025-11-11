# Legacy Database Scripts

This folder contains archived database setup, export, and import scripts that have been replaced by the consolidated `setup_consolidated.sh` script.

## Contents

### Setup Scripts (Archived)
- `setup_amisafe_stlouisintegration_original.sh` - Original comprehensive database setup
- `setup-amisafe-db.sh` - Simple sample data loader
- `setup-amisafe-tables.sh` - Basic test table creation
- `setup-h3-aggregated.sh` - H3 aggregation table setup
- `setup-h3-aggregated-enhanced.sh` - Enhanced H3 table setup
- `setup_database.sh` - Database creation wrapper
- `setup.sh` - H3 pipeline setup
- `run_database_setup.sh` - Quick setup wrapper

### Export Scripts (Archived)
- `export_amisafe_data.sh` - Drush-based export with CSV/SQL options
- `export_amisafe_pure.sh` - Pure MySQL export using mysqldump
- `export_amisafe_simple.sh` - Simplified drush-based export

### Import Scripts (Archived)
- `import_amisafe_data.sh` - Drush-based import from backup files
- `import_amisafe_pure.sh` - Pure MySQL import from SQL dumps
- `import_amisafe_to_stlouisintegration.sh` - Cross-database migration script

## When to Use Legacy Scripts

### Data Migration
If you need to migrate existing data from old database setups:

1. **Export existing data**:
   ```bash
   ./export_amisafe_pure.sh
   ```

2. **Import to new database**:
   ```bash
   ./import_amisafe_pure.sh
   ```

### Cross-Database Operations
For moving data between `theoryofconspiracies_dev` and `stlouisintegration_dev`:
```bash
./import_amisafe_to_stlouisintegration.sh
```

### Reference and Debugging
These scripts serve as reference implementations for:
- Understanding previous database schemas
- Debugging migration issues
- Comparing with consolidated implementation

## Recommended Approach

**For new setups**: Use the consolidated script in the parent directory:
```bash
../setup_consolidated.sh [database_name]
```

**For existing databases**: Use legacy export/import scripts to migrate data, then use consolidated script for future operations.

## Archive Status

These scripts are preserved for:
- Data migration from existing deployments
- Reference implementation details
- Troubleshooting legacy database issues
- Historical documentation

They are no longer actively maintained but remain functional for migration purposes.