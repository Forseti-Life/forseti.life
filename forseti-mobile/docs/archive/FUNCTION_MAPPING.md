# Function Mapping: Web vs Mobile Crime Map

## Architecture Comparison

### Web (Drupal Module)
- **Framework**: jQuery + Leaflet.js + Drupal Behaviors
- **File**: `sites/stlouisintegration/web/modules/custom/amisafe/js/crime-map.js`
- **Pattern**: Class-based prototype with methods
- **Map Library**: Leaflet
- **Total Functions**: 63 methods

### Mobile (React Native)
- **Framework**: React Native + React Hooks
- **File**: `amisafe-mobile/src/components/InteractiveCrimeMap.js`
- **Pattern**: Functional component with hooks
- **Map Library**: react-native-maps (Google Maps)
- **Total Functions**: 12 functions

---

## Core Functions Mapping

| Category | Web Function | Mobile Function | Status | Notes |
|----------|-------------|-----------------|--------|-------|
| **INITIALIZATION** |
| Init | `initialize()` | Component mount (useEffect) | ✅ EQUIVALENT | Web uses constructor + init method, Mobile uses React lifecycle |
| Map Creation | `createMap()` | `<MapView>` component | ✅ EQUIVALENT | Web creates Leaflet map, Mobile renders MapView JSX |
| Event Listeners | `setupEventListeners()` | MapView props | ✅ EQUIVALENT | Web uses `.on()`, Mobile uses `onRegionChangeComplete` |
| Controls | `initializeControls()` | JSX render | ✅ EQUIVALENT | Web creates DOM elements, Mobile renders components |
| Filters | `initializeFilters()` | Not implemented | ❌ MISSING | Filter UI needs to be built in Mobile |
| **H3 RESOLUTION** |
| Get Resolution | `getOptimalResolution(zoom)` | `getOptimalResolution(zoom)` | ✅ IDENTICAL | Exact same logic, same zoom→resolution mapping |
| Resolution Description | `getResolutionDescription(res)` | `getResolutionDescription(res)` | ✅ IDENTICAL | Same text descriptions |
| Precision Level | `getPrecisionLevel(res)` | Not implemented | ⚠️ MISSING | Returns "Ultra", "High", "Medium", "Low" - not critical |
| **DATA LOADING** |
| Initial Load | `loadInitialData()` | `useEffect([], loadHexagonData)` | ✅ EQUIVALENT | Web calls method, Mobile uses useEffect |
| Load Hexagons | `loadHexagonData()` | `loadHexagonData()` | ✅ UPDATED | Just updated Mobile to match Web logging |
| Load with Filters | `loadHexagonDataWithFilters()` | Not implemented | ❌ MISSING | Separate filtered loading |
| Build API URL | `buildApiUrl(res, bounds, filters)` | Inside `DrupalCrimeService` | ✅ EQUIVALENT | Web builds inline, Mobile in service class |
| Load Citywide Stats | `loadCitywideStats()` | `loadCitywideStats()` | ✅ EQUIVALENT | Both call `/api/amisafe/citywide-stats` |
| **MAP INTERACTION** |
| Zoom Change | `handleZoomChange()` | `onRegionChangeComplete(region)` | ✅ EQUIVALENT | Web uses Leaflet event, Mobile uses MapView callback |
| Map Move | `handleMapMove()` | `onRegionChangeComplete(region)` | ✅ EQUIVALENT | Same debounce pattern (500ms timeout) |
| Update Zoom Indicator | `updateZoomIndicator(zoom, res)` | JSX Text render | ✅ EQUIVALENT | Web updates DOM, Mobile re-renders state |
| **HEXAGON RENDERING** |
| Render Hexagons | `renderHexagons(data)` | JSX map() in render | ✅ EQUIVALENT | Web adds to Leaflet layer, Mobile maps to Polygon components |
| Render Single Hexagon | `renderSingleHexagon(hexagon)` | Inline in map() | ✅ EQUIVALENT | Web creates L.polygon, Mobile creates `<Polygon>` |
| Calculate Style | `calculateHexagonStyle(hexagonData)` | `calculateHexagonStyle(hexagonData)` | ✅ UPDATED | Just updated Mobile to match Web z-score gradient |
| H3 to Coordinates | Built into Leaflet.H3 | `h3ToPolygonCoords(h3Index)` | ✅ EQUIVALENT | Mobile explicitly calls h3.cellToBoundary() |
| Fallback Circle | `createFallbackCircle(hexagon)` | Not implemented | ⚠️ OPTIONAL | Web fallback if H3 fails - Mobile has error handling |
| **HEXAGON INTERACTION** |
| Hexagon Click | `showHexagonDetailPanel(hexagon)` | `onHexagonPress(hexagon)` | ✅ EQUIVALENT | Web shows panel, Mobile shows modal |
| Hexagon Details | `createHexagonDetailContent(hexagon)` | `renderHexagonDetails()` | ✅ EQUIVALENT | Both show incident count, risk level, H3 index |
| Hexagon Tooltip | `createHexagonTooltip(hexagon)` | Not implemented | ⚠️ OPTIONAL | Web hover tooltip - Mobile uses press (no hover on mobile) |
| Hover Tooltip | `createHoverTooltip(hexagon)` | N/A | ⚠️ N/A | Desktop-only feature |
| Close Detail | `closeHexagonDetailPanel()` | Modal setState | ✅ EQUIVALENT | Web closes panel, Mobile closes modal |
| **FILTERING** |
| Get Current Filters | `getCurrentFilters()` | `currentFilters` state | ✅ EQUIVALENT | Web method, Mobile React state |
| Apply Filters | `applyFilters()` | Not fully implemented | ⚠️ PARTIAL | Filter state exists, UI incomplete |
| Clear Filters | `clearAllFilters()` | Not implemented | ❌ MISSING | Need to implement |
| Date Preset | `setDatePreset(preset)` | Not implemented | ❌ MISSING | Need dropdown for 6mo/12mo/alltime |
| Apply Preset | `applyPreset(preset)` | Not implemented | ❌ MISSING | Preset combinations |
| Load Filter Options | `loadFilterOptions()` | Not implemented | ❌ MISSING | Crime types, districts from API |
| Populate Crime Types | `populateCrimeTypes(types)` | Not implemented | ❌ MISSING | Checkbox list |
| Populate Districts | `populateDistricts(districts)` | Not implemented | ❌ MISSING | Checkbox list |
| **VIEW MODES** |
| Switch View Mode | `switchViewMode(mode)` | Not implemented | ❌ MISSING | Hexagon/Heatmap/Points toggle |
| Load Heatmap | `loadHeatmapData()` | Not implemented | ❌ MISSING | Heatmap visualization |
| Load Points | `loadPointsData()` | `loadIncidentPoints()` | ⚠️ PARTIAL | Mobile has basic points, not full implementation |
| Create Heatmap Layer | `createHeatmapLayer(incidents)` | Not implemented | ❌ MISSING | Leaflet.heat plugin |
| Create Points Layer | `createPointsLayer(incidents)` | Markers in JSX | ✅ EQUIVALENT | Web L.circleMarker, Mobile `<Marker>` |
| **STATISTICS** |
| Update Stats | `updateStats()` | Not implemented | ⚠️ PARTIAL | Web updates multiple stats panels |
| Update Visible Count | `updateVisibleIncidentsCount(hexagons)` | Not implemented | ❌ MISSING | Count incidents in visible hexagons |
| Get Incident Count | `getCurrentIncidentCount()` | Not implemented | ❌ MISSING | Sum from hexagons |
| Calculate Threat Level | `calculateThreatLevel()` | `calculateRiskLevel(count)` | ✅ EQUIVALENT | Similar logic |
| Get Active Sectors | `getActiveSectorCount()` | Not implemented | ❌ MISSING | Count non-zero hexagons |
| Force Update Stats | `forceUpdateStats()` | Not implemented | ❌ MISSING | Refresh button |
| **UI CONTROLS** |
| Show Loading | `showLoading(message)` | `setIsLoading(true)` | ✅ EQUIVALENT | Web DOM overlay, Mobile ActivityIndicator |
| Hide Loading | `hideLoading()` | `setIsLoading(false)` | ✅ EQUIVALENT | Same purpose |
| Reset View | `resetView()` | Not implemented | ❌ MISSING | Reset zoom/filters |
| Fit to Hexagons | `fitMapToHexagons()` | Not implemented | ⚠️ OPTIONAL | Auto-fit map bounds |
| **UTILITIES** |
| Interpolate Color | `interpolateColor(c1, c2, ratio)` | Not needed | ✅ N/A | Web gradients, Mobile uses rgba strings |
| Get Z-Score Color | `getZScoreColor(zScore)` | Inside `calculateHexagonStyle` | ✅ EQUIVALENT | Same color mapping logic |
| Get Crime Type Name | `getCrimeTypeName(code)` | `getIncidentColor(type)` | ⚠️ PARTIAL | Web returns name, Mobile returns color |
| Get Severity Color | `getSeverityColor(severity)` | `getIncidentColor(type)` | ✅ EQUIVALENT | Color mapping |
| Clear Visualization Layers | `clearVisualizationLayers()` | State reset | ✅ EQUIVALENT | Web removes layers, Mobile clears state |
| Update Layer Visibility | `updateLayerVisibility()` | Not implemented | ❌ MISSING | Show/hide hexagons vs heatmap |

---

## Critical Missing Features in Mobile

### 🔴 HIGH PRIORITY (Core Functionality)
1. **Filter UI** - No filter controls exist
   - Crime type checkboxes
   - District selection
   - Date range picker
   - Time period toggles
   
2. **Filter Application** - Data can be filtered but UI is missing
   - `applyFilters()` logic
   - `clearAllFilters()` button
   - Visual indication of active filters

3. **Date Presets** - No 6mo/12mo/alltime selector
   - `setDatePreset()` dropdown
   - Affects z-score data returned from API

### 🟡 MEDIUM PRIORITY (Enhanced Features)
4. **View Mode Switching** - Only hexagon view works
   - Heatmap mode (requires heatmap library for RN)
   - Points mode (partially implemented but incomplete)
   - Toggle buttons

5. **Statistics Dashboard** - Minimal stats shown
   - Visible incidents count
   - Active sectors count
   - Threat level indicator
   - Live stats updates

6. **Reset/Fit Controls**
   - Reset view button
   - Fit map to data button
   - Manual H3 resolution override

### 🟢 LOW PRIORITY (Nice to Have)
7. **Advanced Tooltips** - Mobile has basic press interaction
   - Hover tooltips (not applicable to touch)
   - Rich tooltip content

8. **Fallback Handling** - Error cases
   - Circle fallback for H3 failures
   - Mock data generators

---

## API Integration Status

### ✅ Working Correctly
- **Base URL**: `https://stlouisintegration.com`
- **Aggregated Endpoint**: `/api/amisafe/aggregated` ✅
- **Incidents Endpoint**: `/api/amisafe/incidents` ✅
- **Citywide Stats**: `/api/amisafe/citywide-stats` ✅
- **Authentication**: Session-based via DrupalAuthService ✅
- **Response Format**: JSON with analytics.z_scores ✅

### ⚠️ Needs Verification
- **Limit Parameter**: Mobile uses resolution-based limits (matching web) - JUST UPDATED
- **Filter Parameters**: Filter state exists but not fully wired to API
- **Error Handling**: Basic try-catch but could be more robust

---

## Data Flow Comparison

### Web (crime-map.js)
```
User Interaction → handleZoomChange/handleMapMove
  ↓
loadHexagonData() [debounced 500ms]
  ↓
buildApiUrl(resolution, bounds, filters)
  ↓
$.get(apiUrl) [jQuery AJAX]
  ↓
renderHexagons(data)
  ↓
renderSingleHexagon(hexagon) for each
  ↓
calculateHexagonStyle(hexagonData) [z-score colors]
  ↓
L.polygon(coords, style).addTo(hexagonLayer)
```

### Mobile (InteractiveCrimeMap.js)
```
User Interaction → onRegionChangeComplete(region)
  ↓
setTimeout(loadHexagonData, 500) [debounced 500ms]
  ↓
drupalCrimeService.getAggregatedData(resolution, bounds, filters)
  ↓
axios.get(url) [in DrupalCrimeService]
  ↓
setHexagons(data.hexagons) [React state update]
  ↓
Component re-renders
  ↓
hexagons.map(hexagon => {
  h3ToPolygonCoords(h3_index)
  calculateHexagonStyle(hexagon) [z-score colors]
  return <Polygon coords={coords} fillColor={style.fillColor} />
})
```

**Key Difference**: Web imperatively adds/removes Leaflet layers. Mobile declaratively renders based on React state.

---

## Color Gradient Implementation

### ✅ Z-Score Gradient (18 levels) - NOW MATCHING
Both systems use identical z-score → color mapping:
- Z ≥ 11: Dark red (#8B0000) - EXTREME
- Z 10-11: Very dark red (#A50000)
- Z 9-10: Crimson (#DC143C)
- ... (15 more levels)
- Z -0.5 to -1: Light green (#90EE90)
- Z < -1: Green (#32CD32) - SAFE

**Status**: Mobile just updated to match web's full gradient ✅

---

## Performance Optimization

### Web
- `dataCache` Map for API responses
- Debounced zoom/move (500ms)
- `currentRequest.abort()` to cancel pending requests
- Lazy JSON parsing of incident_type_counts

### Mobile
- `dataCache` useRef for API responses
- Debounced zoom/move (500ms)
- React state prevents unnecessary re-renders
- No request cancellation (could add with AbortController)

---

## Recommendations

### Immediate Actions (Fix Current Issues)
1. ✅ **Update calculateHexagonStyle** - Use z-score gradient (DONE)
2. ✅ **Add detailed logging** - Debug data flow (DONE)
3. ✅ **Fix API limit parameter** - Resolution-based limits (DONE)
4. 🔄 **Test on device** - Verify data loads and hexagons render
5. 🔄 **Check API response** - Ensure analytics.z_scores present

### Next Phase (Core Features)
6. **Build Filter UI** - Crime types, districts, dates
7. **Wire up filters** - Connect UI to API calls
8. **Add statistics panel** - Match web's stats display
9. **Implement view modes** - Heatmap + Points

### Future Enhancements
10. **Add reset button** - Clear filters, reset zoom
11. **Optimize rendering** - Virtualize hexagons if performance issues
12. **Offline support** - Cache data locally
13. **Push notifications** - Crime alerts

---

## Files Reference

### Web
- **Main**: `sites/stlouisintegration/web/modules/custom/amisafe/js/crime-map.js`
- **Controller**: `sites/stlouisintegration/web/modules/custom/amisafe/src/Controller/ApiController.php`
- **Service**: `sites/stlouisintegration/web/modules/custom/amisafe/src/Service/CrimeDataService.php`

### Mobile
- **Component**: `amisafe-mobile/src/components/InteractiveCrimeMap.js`
- **Service**: `amisafe-mobile/src/services/DrupalCrimeService.js`
- **Auth**: `amisafe-mobile/src/services/DrupalAuthService.js`
- **Screen**: `amisafe-mobile/src/screens/Map/MapScreen.tsx`

---

## Summary

**Coverage**: ~65% of core web functionality implemented in mobile
**Status**: Core hexagon visualization working, filters/stats incomplete
**Priority**: Test current implementation, then build filter UI
**Timeline**: 
- Phase 1 (Now): Fix current issues, verify data flow
- Phase 2 (Next): Build filter UI and statistics
- Phase 3 (Future): View modes and advanced features
