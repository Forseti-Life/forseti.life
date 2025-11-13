# AmISafe Database Export Downloads

**Generated:** November 13, 2025  
**Total Size:** 457MB compressed (~5.7GB uncompressed)  
**Records:** 3,406,194 complete crime incidents with 100% H3 geospatial coverage

## 📦 Available Downloads

### Bronze Layer (Raw Data)
- `amisafe_raw_incidents_structure_20251113_125154.sql` (3.0KB) - Table schema
- `amisafe_raw_incidents_data_20251113_125154.sql.gz` (202MB) - Raw crime data

### Silver Layer (Processed Data) ⭐ **RECOMMENDED**
- `amisafe_clean_incidents_structure_20251113_125154.sql` (4.2KB) - Table schema  
- `amisafe_clean_incidents_data_20251113_125154.sql.gz` (255MB) - **Complete processed data with H3 indexes**

### Gold Layer (Aggregations)
- `amisafe_h3_aggregated_structure_20251113_125154.sql` (3.6KB) - Table schema
- `amisafe_h3_aggregated_data_20251113_125154.sql.gz` (1.6KB) - Spatial aggregations

### Reference Data
- `amisafe_ucr_codes_structure_20251113_125154.sql` (1.8KB) - Table schema
- `amisafe_ucr_codes_data_20251113_125154.sql.gz` (730 bytes) - UCR crime code mappings

## 🎯 Quick Start (Recommended)

**For most use cases, download the Silver Layer:**
1. `amisafe_clean_incidents_structure_20251113_125154.sql` 
2. `amisafe_clean_incidents_data_20251113_125154.sql.gz`

## 💡 Restoration Instructions

```bash
# 1. Create database
mysql -u user -p -e "CREATE DATABASE amisafe_crime_data;"

# 2. Import structure
mysql -u user -p amisafe_crime_data < amisafe_clean_incidents_structure_20251113_125154.sql

# 3. Import data
gunzip amisafe_clean_incidents_data_20251113_125154.sql.gz
mysql -u user -p amisafe_crime_data < amisafe_clean_incidents_data_20251113_125154.sql

# 4. Verify
mysql -u user -p amisafe_crime_data -e "SELECT COUNT(*) FROM amisafe_clean_incidents;"
```

## 📊 Data Specifications

- **H3 Resolutions:** 5-13 (complete coverage)
- **Coordinate System:** WGS84 (EPSG:4326)
- **Date Range:** Philadelphia crime incidents
- **Quality Score:** A+ (100% coordinate validation)
- **Processing:** Complete ETL with spatial indexing

## ⚡ Features Included

✅ Complete geospatial H3 indexing  
✅ Temporal analysis fields (hour, day, month, year)  
✅ Crime categorization and severity scoring  
✅ Data quality scoring and validation flags  
✅ Coordinate quality assessment  
✅ Duplicate detection and handling  

---
*For technical support: keithaumiller@gmail.com*