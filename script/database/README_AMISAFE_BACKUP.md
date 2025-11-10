# AmISafe Database Export/Import Scripts (Pure SQL)

These scripts provide a pure SQL solution for backing up and restoring AmISafe crime data without requiring Drupal or Drush dependencies.

## Overview

- **Database**: `theoryofconspiracies_dev`
- **Tables**: 3 AmISafe tables with 3.4+ million records each
- **Method**: Direct MySQL commands using `mysqldump` and `mysql` 
- **Size**: ~3GB total backup

## Quick Start

### Export AmISafe Data
```bash
./scripts/database/export_amisafe_pure.sh
```

### Import AmISafe Data  
```bash
./scripts/database/import_amisafe_pure.sh
```

## Scripts Description

### export_amisafe_pure.sh
- Creates complete SQL dumps of all 3 AmISafe tables
- Uses `mysqldump` with privilege-safe options (no routines/triggers/tablespaces)
- Generates detailed backup information file
- Creates timestamped backup in `scripts/database/DB Backup/`

**Output Files:**
- `amisafe_raw_incidents_complete.sql` (1GB+)
- `amisafe_clean_incidents_complete.sql` (2GB+)  
- `amisafe_h3_aggregated_complete.sql` (100MB+)
- `database_info.txt` (metadata)

### import_amisafe_pure.sh
- Imports complete SQL dumps back into database
- Includes safety checks and user confirmation
- Drops existing tables before recreating (if confirmed)
- Verifies record counts after import
- Creates timestamped import log

## Tables Included

1. **amisafe_raw_incidents** (3,406,192 records)
   - Original crime incident data
   - Geographic coordinates and timestamps
   - Crime types and descriptions

2. **amisafe_clean_incidents** (3,406,175 records)  
   - Processed and validated incident data
   - Cleaned geographic and temporal data
   - Standardized crime classifications

3. **amisafe_h3_aggregated** (413,173 records)
   - H3 hexagon aggregated crime statistics
   - Resolution levels 5-13 covering Philadelphia metro
   - Incident counts per hexagon for map visualization

## Database Configuration

**Connection Details:**
- Host: `127.0.0.1:3306`
- Database: `theoryofconspiracies_dev`
- User: `drupal_user`
- Password: `drupal_secure_password`

**Required MySQL Privileges:**
- SELECT (for reading data)
- INSERT, CREATE, DROP (for importing)
- Basic connection rights (no PROCESS privileges needed)

## Usage Examples

### Full Backup and Restore Cycle
```bash
# Export current data
./scripts/database/export_amisafe_pure.sh

# Later... import the data
./scripts/database/import_amisafe_pure.sh
```

### Manual Import of Specific Table
```bash
cd "/workspaces/stlouisintegration.com/scripts/database/DB Backup"
mysql -udrupal_user -pdrupal_secure_password theoryofconspiracies_dev < amisafe_h3_aggregated_complete.sql
```

### Verification After Import
```bash
mysql -udrupal_user -pdrupal_secure_password theoryofconspiracies_dev -e "
SELECT 'raw' as table_name, COUNT(*) as records FROM amisafe_raw_incidents 
UNION SELECT 'clean', COUNT(*) FROM amisafe_clean_incidents 
UNION SELECT 'h3', COUNT(*) FROM amisafe_h3_aggregated;"
```

## File Structure

```
scripts/database/
├── export_amisafe_pure.sh    # Export script
├── import_amisafe_pure.sh    # Import script  
└── DB Backup/                # Backup directory
    ├── amisafe_raw_incidents_complete.sql
    ├── amisafe_clean_incidents_complete.sql
    ├── amisafe_h3_aggregated_complete.sql
    ├── database_info.txt
    └── import_info_YYYYMMDD_HHMMSS.txt
```

## Troubleshooting

### Export Issues
- **"Access denied" errors**: Script uses limited privileges - this is normal
- **Missing tables**: Verify AmISafe data exists in database
- **Disk space**: Ensure 4GB+ free space for backup files

### Import Issues  
- **Connection failed**: Check MySQL service and credentials
- **Table exists warnings**: Import script will ask for confirmation to drop/recreate
- **Incomplete import**: Check import log file for detailed error messages

### Performance
- **Export time**: ~5-10 minutes for full 3GB+ export
- **Import time**: ~10-15 minutes depending on disk I/O
- **Memory usage**: mysqldump uses minimal memory, streams data

## Integration with AmISafe Crime Map

These backups preserve all data needed for the AmISafe crime visualization:

- **H3 Hexagons**: Resolution 5-13 covering Philadelphia metro area
- **Individual Incidents**: Building-level detail at Resolution 10+
- **Crime Statistics**: Aggregated counts for color-coded map display
- **Temporal Data**: Date/time information for filtering capabilities

After import, the crime map at `/crime-map` will have access to the complete dataset for interactive visualization.

---

**Created**: November 5, 2025
**Dependencies**: MySQL 8.0+, bash
**Compatibility**: Linux/Unix systems with MySQL client tools