# AmISafe Crime Map - Manual Filter Testing Checklist
**Generated**: Fri Nov  7 16:25:21 UTC 2025  
**Test URL**: http://localhost/amisafe/crime-map  
**Purpose**: Validate frontend filter behavior and user experience  

## Pre-Test Setup
- [ ] Open Chrome/Firefox Developer Tools (F12)
- [ ] Navigate to Network tab to monitor API calls
- [ ] Load the crime map page: http://localhost/amisafe/crime-map
- [ ] Wait for initial map load and hexagon display
- [ ] Verify map displays with hexagons (default state)

---

## Test 1: Crime Type Filter (Positive Case)
**Objective**: Verify crime type filtering reduces displayed data

### Steps:
1. [ ] Open the "Crime Types" dropdown (`#crime-type-selector`)
2. [ ] Note the current number of hexagons on the map
3. [ ] Deselect all crime types except "Murder" (1400) and "Robbery" (300)
4. [ ] Click "Apply Filters" button
5. [ ] Monitor Network tab for API call with crime_types parameter

### Expected Results:
- [ ] API call includes: `?crime_types=1400,300`
- [ ] Map updates with fewer hexagons (filtered data)
- [ ] Statistics panel shows reduced incident count
- [ ] Loading indicator appears during filter application
- [ ] No JavaScript errors in Console

### Actual Results:
```
[Record your observations here]
```

---

## Test 2: Crime Type Filter (Negative Case)
**Objective**: Verify behavior when no crime types selected

### Steps:
1. [ ] Deselect ALL crime types in the dropdown
2. [ ] Click "Apply Filters" button
3. [ ] Observe map and statistics panel behavior

### Expected Results:
- [ ] Either: No hexagons displayed (empty result)
- [ ] Or: All crime types automatically re-selected (fallback behavior)
- [ ] Appropriate user feedback message if no data
- [ ] No JavaScript errors

### Actual Results:
```
[Record your observations here]
```

---

## Test 3: District Filter (Positive Case)
**Objective**: Verify district filtering works correctly

### Steps:
1. [ ] Click "Clear All" to reset filters
2. [ ] Open "Police Districts" dropdown (`#district-selector`)
3. [ ] Select only districts "15" and "12"
4. [ ] Click "Apply Filters" button
5. [ ] Check Network tab for API parameters

### Expected Results:
- [ ] API call includes: `?districts=15,12`
- [ ] Map shows hexagons only for selected districts
- [ ] Statistics update to reflect district-specific data
- [ ] Hexagons appear in geographically distinct areas

### Actual Results:
```
[Record your observations here]
```

---

## Test 4: Date Range Filter (Positive Case)
**Objective**: Verify temporal filtering functionality with new date picker

### Steps:
1. [ ] Set "From Date" to "2024-06-01" using the date picker
2. [ ] Set "To Date" to "2024-08-31" using the date picker
3. [ ] Click "Apply Filters" button
4. [ ] Verify API call parameters

### Expected Results:
- [ ] API call includes: \`?start_date=2024-06-01&end_date=2024-08-31\`
- [ ] Map displays summer 2024 data only
- [ ] Statistics panel reflects seasonal data
- [ ] Hexagon density may vary due to seasonal patterns

### Additional Date Picker Tests:
1. [ ] Test "Last Month" preset button
2. [ ] Test "Last Year" preset button  
3. [ ] Test "All Time" preset button
4. [ ] Verify date picker shows valid date ranges (2006-2025)

### Actual Results:
```
[Record your observations here]
```

---

## Test 5: Time of Day Filter (Positive Case)
**Objective**: Verify time period filtering

### Steps:
1. [ ] In "Time of Day" dropdown, deselect all except "Evening (18:00-23:59)"
2. [ ] Click "Apply Filters" button
3. [ ] Monitor API call

### Expected Results:
- [ ] API call includes time period parameter
- [ ] Map shows incidents from evening hours only
- [ ] Statistics reflect evening-only data
- [ ] Potential change in incident patterns/density

### Actual Results:
```
[Record your observations here]
```

---

## Test 6: Quick Preset Buttons
**Objective**: Verify preset functionality

### Steps:
1. [ ] Click "Clear All" to reset
2. [ ] Click "Violent Crimes" preset button
3. [ ] Observe filter selections and map update
4. [ ] Click "Property Crimes" preset button
5. [ ] Click "Recent (30 Days)" preset button

### Expected Results:
- [ ] Each preset automatically selects appropriate crime types
- [ ] Map updates immediately without manual "Apply Filters" click
- [ ] Preset button visual states change (active/inactive)
- [ ] Different crime patterns visible for each preset

### Actual Results:
```
[Record your observations here]
```

---

## Test 7: Display Mode Toggle
**Objective**: Verify visualization mode switching

### Steps:
1. [ ] With data loaded, click "Heatmap" button
2. [ ] Click "Points" button  
3. [ ] Click "Hexagon" button (return to default)
4. [ ] Test with different zoom levels

### Expected Results:
- [ ] Smooth transition between visualization modes
- [ ] Each mode shows the same underlying data differently
- [ ] Button states update correctly (active highlighting)
- [ ] Performance remains smooth during mode switching

### Actual Results:
```
[Record your observations here]
```

---

## Test 8: Combined Filters (Complex Case)
**Objective**: Verify multiple filters work together

### Steps:
1. [ ] Select specific crime types: "Theft" and "Burglary"
2. [ ] Select districts: "03", "06", "12"
3. [ ] Set date range: March to May
4. [ ] Select time periods: "Morning" and "Afternoon"
5. [ ] Click "Apply Filters"

### Expected Results:
- [ ] API call includes all filter parameters
- [ ] Map shows heavily filtered data (fewer hexagons)
- [ ] Statistics panel reflects all applied filters
- [ ] No conflicts between filter types
- [ ] Reasonable response time (<2 seconds)

### Actual Results:
```
[Record your observations here]
```

---

## Test 9: Clear All Functionality
**Objective**: Verify filter reset capability

### Steps:
1. [ ] Apply multiple filters (from Test 8)
2. [ ] Click "Clear All" button
3. [ ] Observe all filter controls and map

### Expected Results:
- [ ] All dropdowns reset to "all selected" state
- [ ] Date range resets to full year (01-12)
- [ ] Map returns to showing all data
- [ ] Statistics panel shows total citywide numbers
- [ ] Preset buttons deactivated

### Actual Results:
```
[Record your observations here]
```

---

## Test 10: Error Handling and Edge Cases
**Objective**: Verify graceful error handling

### Steps:
1. [ ] Try extremely restrictive filters (very specific crime type + single district + narrow date range)
2. [ ] If no data returned, verify appropriate messaging
3. [ ] Test rapid filter changes (click Apply multiple times quickly)
4. [ ] Test browser refresh while filters applied

### Expected Results:
- [ ] Graceful handling of empty result sets
- [ ] User feedback for "no data found" scenarios
- [ ] No JavaScript errors during rapid interactions
- [ ] Filter state preserved after page refresh (optional)

### Actual Results:
```
[Record your observations here]
```

---

## Performance Observations

### Loading Times:
- [ ] Initial page load: _____ seconds
- [ ] First filter application: _____ seconds  
- [ ] Subsequent filter changes: _____ seconds
- [ ] Complex multi-filter query: _____ seconds

### User Experience:
- [ ] Loading indicators clear and helpful: Yes/No
- [ ] Filter controls intuitive: Yes/No
- [ ] Response time acceptable: Yes/No
- [ ] Visual feedback adequate: Yes/No

---

## Test Summary

### Passed Tests: __ / 10
### Failed Tests: __ / 10  
### Issues Found:
```
[List any issues, bugs, or improvements needed]
```

### Overall Assessment:
```
[Overall functionality rating and recommendations]
```

---

**Tester**: _______________  
**Date**: Fri Nov  7 16:25:21 UTC 2025  
**Browser**: _______________  
**Test Duration**: _____ minutes  
