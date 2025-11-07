# AmISafe Hexagon Statistics Summary

Based on examination of the `amisafe_h3_aggregated` database table, here are all the statistics available for each hexagon in the AmISafe crime map system:

## 🔢 **Core Statistics Available**

### **Basic Crime Data**
- **Incident Count**: Total number of crime incidents (1 to 3,254,917 depending on resolution)
- **Unique Crime Types**: Number of different crime categories (1-26 types)
- **H3 Resolution Level**: Geographic precision level (4-13)

### **📅 Temporal Analysis** 
- **Date Range**: Earliest and latest incident dates (2006-2025 coverage)
- **Recent Activity**: Incidents in last 30 days (0 to 78,794)
- **Annual Activity**: Incidents in last 365 days
- **Last Updated**: When aggregation was last processed

### **🗺️ Geographic Information**
- **Center Coordinates**: Precise latitude/longitude of hexagon center
- **Coverage Area**: Area in square kilometers (available via H3 library calculation)
- **Resolution Description**: Human-readable description of geographic scale

### **🔍 Detailed Crime Breakdown**
- **Crime Type Counts**: JSON object with incident counts by crime code
  - Example: `{"200": 45, "300": 23, "1400": 12, "2600": 8}`
  - Covers all Philadelphia PD crime classification codes (100-2600+)
- **Police Districts**: JSON object showing which districts overlap
  - Example: `{"14": 67, "22": 23, "3": 8}`

### **📊 Quality Metrics**
- **Data Quality Score**: Average quality rating of source data
- **Valid Records**: Number of clean, processed records
- **Invalid Records**: Number of problematic source records
- **Source Record Count**: Total records used for aggregation

## 🎯 **Currently Implemented in Tooltips**

### **Hover Tooltips** (Quick View)
- H3 resolution level ("H3:7 Sector")
- Total incident count (formatted with commas)
- Number of unique crime types
- Risk level assessment (CRITICAL/HIGH/MEDIUM/LOW/MINIMAL)

### **Click Popups** (Detailed View)
- **Crime Statistics**: Total incidents, crime types, risk level, recent activity
- **Geographic Details**: Precision level, coverage area, center coordinates  
- **Temporal Analysis**: Date range, 30-day activity, annual totals
- **Crime Type Breakdown**: Top 3 most common crime types
- **Police Districts**: Top 3 districts with most incidents
- **Data Quality**: Quality score and valid record counts

## 📈 **Data Coverage by Resolution**

| Resolution | Hexagons | Incident Range | Avg Incidents | Crime Types | Geographic Scale |
|------------|----------|----------------|---------------|-------------|------------------|
| **H3:4**   | 2        | 151K - 3.25M   | 1.7M          | 26          | ~1,770 km² |
| **H3:5**   | 5        | 6.7K - 1.6M    | 681K          | 25-26       | ~252 km²   |
| **H3:6**   | 22       | 2 - 577K       | 155K          | 2-26        | ~36 km²    |
| **H3:7**   | 93       | 4 - 184K       | 37K           | 4-26        | ~5.2 km²   |
| **H3:8**   | 545      | 1 - 79K        | 6.2K          | 1-26        | ~0.74 km²  |

**Total Database**: 413,179 hexagon records across all resolutions

## 🔧 **Available but Not Yet Used**

### **Response Metrics** (Future Enhancement)
- Average police response time (currently NULL)
- Total police units involved (currently 0)

### **Enhanced Geographic Data**
- Precise coverage area calculations (can be computed via H3 library)
- Hexagon boundary coordinates (available via H3 library)

### **Advanced Analytics** (Potential)
- Crime density per square kilometer
- Temporal trend analysis (hourly, daily, seasonal patterns)
- Crime type correlation analysis
- Cross-district crime pattern analysis

## 🎨 **Risk Level Calculation**
Current algorithm based on incident count:
- **CRITICAL** (Red): ≥ 1,000 incidents  
- **HIGH** (Orange): 500-999 incidents
- **MEDIUM** (Yellow): 100-499 incidents
- **LOW** (Green): 10-99 incidents
- **MINIMAL** (Gray): 1-9 incidents

## 💾 **Database Performance**
- **Total Records**: 413,179 hexagons
- **All Records**: Have complete crime type breakdown (JSON)
- **All Records**: Have police district associations (JSON)
- **Quality Data**: Available for resolution 8+ (high precision hexagons)
- **Response Time**: Fast queries with proper indexing on h3_resolution, incident_count, latest_incident

## 🔍 **Sample Data**
```
H3:8 Hexagon (882a134953fffff):
- Incidents: 493 total
- Crime Types: 18 different types  
- Recent: 493 in last 30 days
- Risk Level: MEDIUM
- Districts: Police District 5
- Area: ~0.74 km² coverage
- Crime Breakdown: {"300": 1, "400": 1, "500": 1, "600": 1, "700": 1, ...}
```

This comprehensive database enables rich, multi-scale crime analysis with detailed statistical breakdowns for enhanced user understanding and decision-making.