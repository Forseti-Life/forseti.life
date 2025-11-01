# AmISafe - Philadelphia Crime Monitoring System 2085

## Overview
The AmISafe module is a comprehensive cyberpunk-themed crime monitoring and spatial analysis system designed for Philadelphia in the year 2085. It provides real-time crime data visualization using H3 geospatial analysis, interactive filtering, and immersive cyberpunk aesthetics.

**🔗 H3 Data Pipeline**: This module integrates with the [H3 Geolocation Framework](../../../../h3-geolocation/README.md) for advanced spatial processing. The H3 framework handles the preprocessing of 2.5M+ incident records and provides the hexagonal spatial indexing that powers this crime dashboard.

**⚠️ Data Pipeline Status**: The module currently uses sample data (37 incidents). For full functionality, the H3 preprocessing pipeline must be completed to load all 2.5M+ incidents from CSV files. See the [H3 Framework documentation](../../../../h3-geolocation/README.md) for pipeline completion instructions.

## Features

### 🗺️ Interactive Crime Map
- **H3 Geospatial Analysis**: Crime data aggregated into hexagonal sectors for precise spatial analysis
- **Multiple Visualization Modes**: Hexagon view, heatmap, and individual points
- **Dynamic Resolution**: Automatically adjusts detail level based on zoom (supports 1-meter precision)
- **Real-time Updates**: Live filtering and data refresh capabilities

### 🎛️ Advanced Filtering System
- **Crime Type Filtering**: Filter by specific crime categories (Murder, Robbery, Theft, etc.)
- **District-Based Analysis**: Focus on specific Philadelphia police districts
- **Temporal Filtering**: Date ranges and time-of-day analysis
- **Severity Levels**: Filter by threat levels (Low → Extreme)
- **Quick Presets**: One-click filters for common analysis patterns

### 📊 Real-time Statistics Dashboard
- **Citywide Overview**: Total incidents, active districts, threat levels
- **Current View Stats**: Statistics for visible map area
- **Dynamic Updates**: Real-time recalculation as filters change
- **Threat Level Assessment**: AI-enhanced threat level calculations

### 🎨 Cyberpunk 2085 Interface
- **Terminal Aesthetics**: Monospace fonts, neon colors, glitch effects
- **Interactive Elements**: Cyberpunk-styled buttons, dropdowns, and controls  
- **Loading Animations**: "Neural map initialization" and terminal-style progress
- **Responsive Design**: Optimized for desktop, tablet, and mobile

## Installation & Setup

### Requirements
- Drupal 9, 10, or 11
- MySQL database for crime data storage
- H3 JavaScript library for geospatial processing
- Leaflet.js for map rendering

### Module Installation
1. Place the `amisafe` module in `web/modules/custom/`
2. Enable the module: `drush en amisafe`
3. Import crime data using provided CSV files
4. Configure database connections if needed

### Data Import
The module includes sample crime data files in the `data/` directory:
- Multiple CSV files with Philadelphia crime incidents
- Automated import scripts available
- H3 precomputation for performance optimization

## Usage

### Main Interfaces
- **Dashboard**: `/amisafe` - Main "Am I Safe?" dashboard with threat overview
- **Crime Map**: `/amisafe/crime-map` - Interactive crime mapping interface

### API Endpoints
- `/api/amisafe/aggregated` - H3 hexagon data with crime statistics
- `/api/amisafe/incidents` - Raw incident data with filtering
- `/api/amisafe/crime-types` - Available crime categories
- `/api/amisafe/districts` - Police district information  
- `/api/amisafe/citywide-stats` - Overall city statistics

## Detailed Documentation

### 📖 Complete Interface Guide
For comprehensive documentation of every button, control, and feature, see:
**[INTERFACE_DOCUMENTATION.md](./INTERFACE_DOCUMENTATION.md)**

This detailed guide covers:
- Every button and control with exact behavior
- All filter options and expected responses  
- Interactive map features and click behaviors
- Statistics panel explanations
- Keyboard shortcuts and mouse interactions
- Visual states, animations, and effects
- Expected user workflows and use cases
- Developer integration details

### 🎯 Key Features Documented
- **27 Interactive Controls**: Buttons, dropdowns, toggles with exact IDs
- **12 Filter Types**: Crime categories, districts, time periods, severity levels
- **6 Statistics Displays**: Both citywide and view-specific metrics
- **8 API Endpoints**: Complete endpoint documentation with expected responses
- **4 Visualization Modes**: Hexagon, heatmap, points, and fullscreen views

## File Structure
```
amisafe/
├── amisafe.info.yml              # Module definition
├── amisafe.routing.yml           # URL routing configuration  
├── amisafe.services.yml          # Dependency injection services
├── amisafe.libraries.yml         # CSS/JS library definitions
├── README.md                     # This overview document
├── INTERFACE_DOCUMENTATION.md    # Complete interface guide
├── css/                          # Cyberpunk styling
│   ├── crime-map.css            # Main map interface styles
│   ├── dashboard.css            # Dashboard styling  
│   ├── cyberpunk-theme.css      # Core cyberpunk theme
│   └── h3-hexagons.css          # H3 hexagon visualizations
├── js/
│   └── crime-map.js             # Interactive map functionality
├── src/
│   ├── Controller/              # Drupal controllers
│   │   ├── AmISafeController.php       # Main dashboard
│   │   ├── CrimeMapController.php      # Crime map interface
│   │   └── ApiController.php           # REST API endpoints
│   └── Service/                 # Business logic services
│       ├── CrimeDataService.php        # Database operations
│       ├── H3AggregatorService.php     # Geospatial processing
│       └── SpatialAnalyzerService.php  # Crime analysis
├── templates/                   # Twig templates
│   ├── amisafe-dashboard.html.twig     # Main dashboard UI
│   └── amisafe-crime-map.html.twig     # Crime map interface  
└── data/                        # Sample crime data
    └── incidents_part1_part2*.csv     # Philadelphia crime datasets
```

## Technical Details

### H3 Geospatial Integration
- **Library**: H3 JavaScript library v4+
- **Resolution Range**: 8-15 (city-wide to meter-level precision)
- **Coordinate Handling**: Automatic conversion between H3 [lng,lat] and Leaflet [lat,lng]
- **Performance**: Cached hexagon boundaries with intelligent cache management

### Database Schema
- **Raw Incidents Table**: Stores individual crime records
- **H3 Aggregation**: Pre-computed hexagon statistics for performance
- **Spatial Indexing**: Optimized queries for geographic data

### API Performance
- **Caching**: Intelligent response caching with cache invalidation
- **Debouncing**: 500ms debounce on filter changes to reduce API calls
- **Request Management**: Automatic cancellation of superseded requests
- **Fallback Data**: Graceful degradation with sample data when APIs fail

## Development

### Extending the Module
- **Add Crime Categories**: Update `CrimeDataService::getCrimeTypes()`
- **New Visualizations**: Extend map view modes in `crime-map.js`  
- **Custom Filters**: Add filter logic to `ApiController::parseFilters()`
- **Enhanced Analytics**: Expand the analytics dashboard section

### Debugging Tools
- **H3 Debug Panel**: Real-time H3 library status and function availability
- **Console Logging**: Comprehensive logging with debug mode toggle
- **API Test Endpoints**: `/api/amisafe/debug-test` for troubleshooting

## Cyberpunk 2085 Theme

### Design Philosophy
- **Terminal Aesthetics**: Green monospace text on dark backgrounds
- **Neon Accents**: Cyan, orange, and red highlights for different data types
- **Glitch Effects**: Subtle animation effects on key interface elements
- **Corporate Dystopia**: "Surveillance network" and "sector monitoring" terminology

### Color Palette
- **Primary Text**: `#00ff00` (terminal green)
- **Accent Blue**: `#00ffff` (neon cyan)
- **Warning Orange**: `#ff8800` (neon orange)  
- **Critical Red**: `#ff0000` (neon red)
- **Background**: `#0a0a0a` (near black)

## License
This module is part of the Theory of Conspiracies project and follows the same licensing terms as the parent Drupal installation.

## Support
For technical issues, feature requests, or questions about the AmISafe interface, refer to the detailed interface documentation or check the module's controller and service files for implementation details.