# AmISafe Crime Map Filter Validation Plan

## Overview
Comprehensive testing protocol to validate all filter controls in the AmISafe crime map interface.

## Filter Controls Identified

### 1. Crime Type Multi-Select Filter (`#crime-type-selector`)
- **Element**: `<select id="crime-type-selector" multiple>`
- **Functionality**: Filter incidents by UCR crime type codes
- **API Integration**: `/api/amisafe/aggregated` and `/api/amisafe/incidents`
- **Data Source**: `amisafe_h3_aggregated.incident_type_counts` JSON field

### 2. Police District Multi-Select Filter (`#district-selector`)
- **Element**: `<select id="district-selector" multiple>`
- **Functionality**: Filter incidents by Philadelphia police district
- **API Integration**: `/api/amisafe/aggregated` and `/api/amisafe/incidents`
- **Data Source**: `amisafe_h3_aggregated.district_counts` JSON field

### 3. Date Range Filters (`#start-month`, `#end-month`)
- **Elements**: Two dropdown selectors for month range
- **Functionality**: Filter incidents by temporal range (month-based)
- **API Integration**: Date filtering in backend queries
- **Data Source**: `dispatch_date_time` field filtering

### 4. Time of Day Multi-Select Filter (`#time-period-selector`)
- **Element**: `<select id="time-period-selector" multiple>`
- **Options**: 
  - `early-morning` (00:00-05:59)
  - `morning` (06:00-11:59)
  - `afternoon` (12:00-17:59)
  - `evening` (18:00-23:59)
- **API Integration**: Hour-based filtering

### 5. Quick Preset Buttons
- **Violent Crimes** (`#preset-violent`): Pre-select violent crime categories
- **Property Crimes** (`#preset-property`): Pre-select property crime categories  
- **Recent (30 Days)** (`#preset-recent`): Temporal filter for recent activity

### 6. Display Mode Toggle (`#hexagon-view`, `#heatmap-view`, `#points-view`)
- **Functionality**: Switch between visualization modes
- **Default**: Hexagon view active

### 7. Action Buttons
- **Apply Filters** (`#apply-filters`): Execute filter query
- **Clear All** (`#clear-filters`): Reset to default state

## Test Case Categories

### Positive Test Cases
- Filter applies correctly and reduces dataset
- API calls include proper filter parameters
- Map visualization updates to reflect filtered data
- Statistics panel updates with filtered counts
- Multiple filters work in combination

### Negative Test Cases
- Empty filter selections handled gracefully
- Invalid filter values rejected
- API errors handled with fallback behavior
- Filter combinations that return no results display appropriate message
- Performance with extreme filter combinations

## Success Criteria
1. **Filter Application**: Each filter correctly modifies API requests
2. **Data Response**: Backend returns properly filtered datasets
3. **Visualization Update**: Map hexagons/points update to reflect filtered data
4. **Statistics Accuracy**: Stats panel shows filtered counts, not total counts
5. **User Feedback**: Loading states and completion indicators work
6. **Error Handling**: Graceful degradation for invalid filters or API failures

## Test Environment
- **URL**: `http://localhost/amisafe/crime-map`
- **Database**: `stlouisintegration_dev` with 3.4M+ incident records
- **API Endpoints**: Local development endpoints
- **Browser**: Chrome/Firefox developer tools for network monitoring