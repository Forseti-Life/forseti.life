# AmISafe Pipeline Quick Reference

## 🚀 Quick Start (Copy & Paste)

```bash
# Setup
export DB_USER='stlouis_user'
export DB_PASSWORD='StLouis2024!Secure#DB'
export DB_SOCKET='/var/run/mysqld/mysqld.sock'
cd /var/www/html/stlouisintegration/h3-geolocation
source h3-env/bin/activate

# Run complete pipeline
nohup ./database/etl/run_complete_pipeline.sh --full > pipeline.log 2>&1 &
```

## 📊 Pipeline Stages

| Stage | Script | Time | Output |
|-------|--------|------|--------|
| **Bronze** | `amisafe_processor.py` | 30-60 min | ~3.5M raw records |
| **Silver** | `enhanced_transform_processor_v2.py` | 20-40 min | Clean incidents |
| **Gold** | `amisafe_aggregator.py` | 15-30 min | ~410K hexagons |
| **Analytics** | Stored procedures | 3-6 hrs | 84 columns/hex |

## 🎯 Commands

### Run Options
```bash
# Complete pipeline (all 4 stages)
./database/etl/run_complete_pipeline.sh --full

# Individual stages
./database/etl/run_complete_pipeline.sh --bronze
./database/etl/run_complete_pipeline.sh --silver
./database/etl/run_complete_pipeline.sh --gold
./database/etl/run_complete_pipeline.sh --analytics

# Fast analytics (skip 12mo/6mo windows)
./database/etl/run_complete_pipeline.sh --analytics-basic

# Resume from last successful stage
./database/etl/run_complete_pipeline.sh --resume

# Help
./database/etl/run_complete_pipeline.sh --help
```

## 📈 Monitoring

### Check Progress
```bash
# Watch log
tail -f database/pipeline_*.log

# Check state
cat database/pipeline_state.json

# Monitor background process
ps aux | grep run_complete_pipeline
```

### Database Stats
```bash
mysql -u stlouis_user -p'StLouis2024!Secure#DB' \
      -S /var/run/mysqld/mysqld.sock amisafe_database << 'EOF'
-- Record counts
SELECT 
    'Bronze' as Layer, COUNT(*) as Records FROM amisafe_raw_incidents
UNION ALL
SELECT 'Silver', COUNT(*) FROM amisafe_clean_incidents
UNION ALL
SELECT 'Gold', COUNT(*) FROM amisafe_h3_aggregated;

-- Hexagon distribution
SELECT 
    h3_resolution,
    COUNT(*) as hexagons,
    SUM(incident_count) as incidents,
    COUNT(CASE WHEN top_crime_type IS NOT NULL THEN 1 END) as with_analytics
FROM amisafe_h3_aggregated
GROUP BY h3_resolution
ORDER BY h3_resolution DESC;
EOF
```

## 🔧 Troubleshooting

### Virtual Environment
```bash
cd /var/www/html/stlouisintegration/h3-geolocation
python3 -m venv h3-env
source h3-env/bin/activate
pip install pandas numpy h3 mysql-connector-python folium matplotlib plotly seaborn geopy tqdm psutil
```

### MySQL Connection
```bash
# Test connection
mysql -u stlouis_user -p'StLouis2024!Secure#DB' \
      -S /var/run/mysqld/mysqld.sock amisafe_database -e "SELECT 1;"

# Check socket
mysqladmin -u stlouis_user -p'StLouis2024!Secure#DB' variables | grep socket
```

### CSV Files
```bash
# Download data
cd data/raw
./download_crime_data.sh

# Verify
ls -lh *.csv | wc -l  # Should be 19 files
```

### Slow Analytics
```bash
# Use basic analytics only (skip windowed)
./database/etl/run_complete_pipeline.sh --analytics-basic

# Or process one resolution at a time
mysql -u stlouis_user -p'StLouis2024!Secure#DB' \
      -S /var/run/mysqld/mysqld.sock amisafe_database \
      -e "CALL sp_complete_resolution_analytics(13);"
```

## 📁 Key Files

| File | Purpose |
|------|---------|
| `database/etl/run_complete_pipeline.sh` | Master orchestrator |
| `database/pipeline_state.json` | Checkpoint/resume state |
| `database/pipeline_*.log` | Execution logs |
| `PIPELINE_GUIDE.md` | Full documentation |
| `PIPELINE_ARCHITECTURE.md` | Architecture details |

## 🔢 Analytics Breakdown

**84 Total Columns Per Hexagon:**
- **All-Time** (28 columns): Historical perspective, complete crime profile
- **12-Month** (28 columns): Recent trends, seasonal patterns
- **6-Month** (28 columns): Current activity, tactical intelligence

**28 Column Types (per window):**
- 10 basic metrics (crime types, temporal patterns, violence stats)
- 6 statistical metrics (z-scores, percentiles)
- 2 risk metrics (score, category)
- 10 data quality/metadata columns

## 🌍 H3 Resolutions

| Res | Hexagons | Area/Hex | Use Case |
|-----|----------|----------|----------|
| 5 | ~5 | 253 km² | City-wide |
| 6 | ~23 | 36 km² | Districts |
| 7 | ~93 | 5.2 km² | Neighborhoods |
| 8 | ~545 | 0.74 km² | Sub-neighborhoods |
| 9 | ~3K | 0.10 km² | Multi-block |
| 10 | ~17K | 0.015 km² | City blocks |
| 11 | ~70K | 0.002 km² | Street segments |
| 12 | ~146K | 0.0003 km² | Building clusters |
| 13 | ~177K | 0.00004 km² | Individual buildings |

## ⏱️ Expected Timeline

**Complete Pipeline** (~5-8 hours total):
1. Bronze: 30-60 minutes
2. Silver: 20-40 minutes
3. Gold: 15-30 minutes
4. Analytics: 3-6 hours

**Fast Track** (~2-3 hours):
- Use `--analytics-basic` instead of full analytics
- Reduces analytics time to ~1-2 hours
- Can add windowed metrics later

## 🎬 Next Steps After Completion

1. **Enable Drupal module**:
   ```bash
   drush en amisafe -y
   drush cr
   ```

2. **Test API**:
   ```bash
   curl "https://stlouisintegration.com/amisafe/api/v1/crime-data?lat=39.9526&lng=-75.1652&resolution=13"
   ```

3. **Performance tuning** (if needed):
   - Add indexes for slow queries
   - Consider resolution-specific optimizations
   - Monitor query execution times

## 📞 Support

- Logs: `database/pipeline_*.log`
- State: `database/pipeline_state.json`
- Documentation: `PIPELINE_GUIDE.md` and `PIPELINE_ARCHITECTURE.md`
- ETL script documentation: Check `--help` on each script
