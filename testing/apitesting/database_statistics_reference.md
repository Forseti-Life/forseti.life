# AmISafe Database Statistics Reference
Generated: November 5, 2025

## Silver Layer Statistics (amisafe_clean_incidents)

### Query Used:
```sql
-- Basic count
SELECT COUNT(*) as total_records FROM amisafe_clean_incidents;

-- Detailed breakdown (for future use)
SELECT 
    'Total Records' as metric,
    COUNT(*) as value,
    'incidents' as unit
FROM amisafe_clean_incidents
UNION ALL
SELECT 
    'Valid Records',
    COUNT(*),
    'incidents'
FROM amisafe_clean_incidents WHERE is_valid = 1
UNION ALL
SELECT 
    'Invalid Records',
    COUNT(*),
    'incidents'
FROM amisafe_clean_incidents WHERE is_valid = 0
UNION ALL
SELECT 
    'Duplicate Records',
    COUNT(*),
    'incidents'  
FROM amisafe_clean_incidents WHERE is_duplicate = 1
UNION ALL
SELECT 
    'Date Range - Earliest',
    MIN(incident_date),
    'date'
FROM amisafe_clean_incidents  
UNION ALL
SELECT 
    'Date Range - Latest', 
    MAX(incident_date),
    'date'
FROM amisafe_clean_incidents
UNION ALL
SELECT 
    'Unique Districts',
    COUNT(DISTINCT dc_dist),
    'districts'
FROM amisafe_clean_incidents
UNION ALL
SELECT 
    'Unique PSAs', 
    COUNT(DISTINCT psa),
    'areas'
FROM amisafe_clean_incidents
UNION ALL
SELECT 
    'Records with H3 Res 13',
    COUNT(*),
    'incidents'
FROM amisafe_clean_incidents 
WHERE h3_res_13 IS NOT NULL AND h3_res_13 != ''
UNION ALL
SELECT 
    'Records with Coordinates',
    COUNT(*),
    'incidents'
FROM amisafe_clean_incidents 
WHERE lat IS NOT NULL AND lng IS NOT NULL;
```

### Results:
- **Total Records**: 3,406,175 incidents
- **Data Type**: Clean incident data with H3 indexing at all resolution levels
- **Coverage**: Philadelphia Metropolitan Area crime incidents

## Gold Layer Statistics (amisafe_h3_aggregated)

### Query Used:
```sql
SELECT 
    h3_resolution,
    COUNT(*) as hexagon_count,
    SUM(incident_count) as total_incidents,
    AVG(incident_count) as avg_incidents_per_hex,
    MIN(incident_count) as min_incidents,
    MAX(incident_count) as max_incidents
FROM amisafe_h3_aggregated 
GROUP BY h3_resolution 
ORDER BY h3_resolution;
```

### Results by H3 Resolution Level:

| Resolution | Hexagon Count | Total Incidents | Avg per Hex | Min | Max | Coverage |
|------------|---------------|-----------------|-------------|-----|-----|----------|
| 5 | 1 | 1,488,452 | 1,488,452.00 | 1,488,452 | 1,488,452 | 251.10 km² |
| 6 | 22 | 3,406,175 | 154,826.14 | 2 | 577,149 | 36.1 km² per hex |
| 7 | 93 | 3,406,175 | 36,625.54 | 4 | 183,668 | 5.2 km² per hex |
| 8 | 545 | 3,406,175 | 6,249.86 | 1 | 78,794 | 0.7 km² per hex |
| 9 | 3,150 | 3,406,175 | 1,081.33 | 1 | 16,948 | 0.1 km² per hex |
| 10 | 16,739 | 3,406,175 | 203.49 | 1 | 9,096 | 15,047 m² per hex |
| 11 | 69,513 | 3,406,175 | 49.00 | 1 | 8,907 | 2,150 m² per hex |
| 12 | 145,982 | 3,406,175 | 23.33 | 1 | 8,359 | 307 m² per hex |
| 13 | 177,128 | 3,406,175 | 19.23 | 1 | 8,362 | 44 m² per hex |

### Key Insights:
- **Total Hexagons Across All Resolutions**: 413,173 (including Resolution 5 citywide)
- **Resolution 5 Citywide Hexagon**: 1,488,452 incidents in single 251.10 km² hexagon
- **Philadelphia Metro Coverage**: Resolution 5 encompasses core Philadelphia metropolitan area
- **Consistent Total Incidents**: 3,406,175 across resolution levels 6-13 (validates aggregation)
- **Ultra-Precision Level 13**: 177,128 hexagons with average 19.23 incidents per 44m² hexagon
- **Coverage Efficiency**: Higher resolutions provide finer spatial detail with more balanced distribution

## Database Schema Reference

### Silver Layer (amisafe_clean_incidents) Key Columns:
- `id`: Primary key (bigint, auto_increment)
- `incident_id`: Unique incident identifier
- `dc_dist`: District code
- `psa`: Police Service Area
- `lat`, `lng`: Coordinates (decimal)
- `incident_datetime`: Full timestamp
- `incident_date`, `incident_hour`: Parsed date/time components
- `ucr_general`: Crime category code
- `h3_res_1` through `h3_res_15`: H3 indices at different resolutions
- `is_valid`, `is_duplicate`: Data quality flags

### Gold Layer (amisafe_h3_aggregated) Key Columns:
- `h3_index`: H3 hexagon identifier
- `h3_resolution`: Resolution level (5-13)
- `incident_count`: Total incidents in hexagon
- `unique_incident_types`: Count of different crime types
- `earliest_incident`, `latest_incident`: Date range
- `incidents_last_30_days`, `incidents_last_year`: Recent activity
- `center_latitude`, `center_longitude`: Hexagon center point
- `coverage_area_km2`: Spatial coverage area
- `source_record_count`: Number of child records aggregated (Resolution 5 specific)
- `aggregation_method`: Method used for aggregation (e.g., 'resolution_5_citywide')

## API Alignment Requirements

Based on these updated statistics, our API should return:
1. **Citywide incidents**: 1,488,452 (from Resolution 5 single hexagon)
2. **Total Silver layer incidents**: 3,406,175 (for filtered/detailed queries)
3. **Resolution breakdown**: Exact counts from Gold layer table including Resolution 5
4. **Ultra-precision hexagons**: 177,128 at resolution 13
5. **Spatial coverage**: Accurate area calculations based on H3 resolution

## Resolution 5 Citywide Hexagon Details

The Resolution 5 hexagon provides the single source of truth for Philadelphia citywide statistics:

- **Hexagon ID**: `852a134bfffffff`
- **Incident Count**: 1,488,452
- **Coverage Area**: 251.10 km²
- **Center Coordinates**: 40.038890, -75.200686
- **Source Records**: 7 resolution 6 hexagons aggregated
- **Aggregation Method**: `resolution_5_citywide`
- **Last Updated**: 2025-11-05 17:09:43

This hexagon encompasses the core Philadelphia metropolitan area and provides optimal performance for citywide API queries by eliminating the need to sum across multiple resolution levels.