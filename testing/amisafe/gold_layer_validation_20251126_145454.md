# AmISafe Gold Layer Data Validation Report

**Generated**: $(date)
**Database**: amisafe_database
**Table**: amisafe_h3_aggregated

## Executive Summary

### ❌ FAIL: Total Records
  No records found in table
  - **Expected**: Greater than 0
  - **Actual**: 0

### ❌ FAIL: H3 Index Integrity
  Found records with NULL h3_index
  - **Expected**: 0 NULL values

### ❌ FAIL: H3 Resolution Integrity
  Found records with NULL h3_resolution
  - **Expected**: 0 NULL values

### ❌ FAIL: H3 Resolution Range
  Found resolutions outside valid range
  - **Expected**: 0 out of range


#### Incident Count by Resolution
```
```
### ✅ PASS: Incident Count Consistency
  All resolutions have same total incident count
  - **Expected**: 1 unique value
  - **Actual**: 1

### ❌ FAIL: Coordinate Completeness
  Found hexagons with NULL coordinates
  - **Expected**: 0 NULL values

### ❌ FAIL: Latitude Range
  Found invalid latitudes
  - **Expected**: 0 out of range

### ❌ FAIL: Longitude Range
  Found invalid longitudes
  - **Expected**: 0 out of range

### ⚠️  WARN: Geographic Bounds
  % of hexagons within Philadelphia area
  - **Expected**: Greater than 95%
  - **Actual**: %

### ✅ PASS: Temporal Coverage
  Data spans  days ( to )
  - **Expected**: Multiple years
  - **Actual**:  days

### ❌ FAIL: No Future Dates
  Found incidents with future dates
  - **Expected**: 0 future dates

### ❌ FAIL: Date Logic
  Found records where earliest > latest
  - **Expected**: 0 violations

### ⚠️  WARN: Top Crime Type
  Some hexagons missing top_crime_type
  - **Expected**: 0 NULL values

### ❌ FAIL: Risk Score
  Some hexagons missing risk_score
  - **Expected**: 0 NULL values

### ❌ FAIL: Risk Category
  Some hexagons missing risk_category
  - **Expected**: 0 NULL values


#### Risk Category Distribution
```
```
### ❌ FAIL: Violent Crime Logic
  Found records where violent > total incidents
  - **Expected**: 0 violations

### ❌ FAIL: Nonviolent Crime Logic
  Found records where nonviolent > total
  - **Expected**: 0 violations

### ⚠️  WARN: Crime Count Sum
  Some records have sum mismatch
  - **Expected**: 0 violations

### ⚠️  WARN: Z-Score Population
  Some hexagons missing z-score
  - **Expected**: 0 NULL values

### ❌ FAIL: Percentile Range
  Found percentiles outside valid range
  - **Expected**: 0 out of range

### ⚠️  WARN: 12-Month Incident Count

