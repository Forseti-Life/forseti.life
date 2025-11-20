#!/usr/bin/env python3
"""
AmISafe Final Layer (Gold) Aggregator
Creates H3 aggregated analytics from the Transform layer data
Part of the 3-layer data warehouse architecture:
- Raw Layer (Bronze) -> Transform Layer (Silver) -> Final Layer (Gold) <- THIS SCRIPT

Integrated Functionality:
- Metro Area H3 Generation: generate_metro_area_h3_cells() for complete Philadelphia metro coverage
- H3 Incident ID Collection: JSON_ARRAYAGG(incident_id) for granular incident tracking
- Multi-resolution H3 Aggregation: Supports resolutions 5-13 for all scales
"""

import os
import sys
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import mysql.connector
from mysql.connector import Error
import h3
import json
import logging
from typing import List, Dict, Tuple, Optional
import argparse

# Add the parent directory to sys.path to import our H3 framework
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from h3_framework import H3GeolocationFramework

# Add current directory to path for statistical calculator
sys.path.append(os.path.dirname(os.path.abspath(__file__)))
from statistical_calculator import StatisticalCalculator

class AmISafeFinalLayerAggregator:
    """
    Final Layer (Gold) processor for the AmISafe 3-layer data warehouse.
    Creates H3 aggregated analytics from clean Transform layer data.
    Supports H3 resolutions 5-10 for multi-scale visualization.
    """
    
    def __init__(self, 
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = 'drupal_secure_password',
                 mysql_database: str = 'amisafe_database'):
        """Initialize the Final Layer aggregator."""
        self.mysql_config = {
            'host': mysql_host,
            'user': mysql_user,
            'password': mysql_password,
            'database': mysql_database,
            'autocommit': True
        }
        
        # Initialize H3 framework
        self.h3_framework = H3GeolocationFramework()
        
        # Initialize statistical calculator
        self.stats_calculator = StatisticalCalculator()
        
        # Philadelphia metropolitan area bounds for metro-wide H3:5-7 coverage
        self.philly_metro_bounds = {
            'north': 41.0,    # Extends into New Jersey and suburbs
            'south': 39.5,    # South to Delaware County
            'east': -74.5,    # East to New Jersey
            'west': -76.0     # West to Lancaster County edge
        }
        
        # Setup logging
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s'
        )
        self.logger = logging.getLogger(__name__)
        
    # ========================================================================
    # GOLD LAYER COLUMN POPULATION INVENTORY
    # ========================================================================
    """
    COLUMN POPULATION STATUS:
    
    ✅ FULLY POPULATED COLUMNS (100% fill rate):
    - id: Auto-increment primary key (MySQL auto-generated)
    - h3_index: H3 hexagon identifier (from Silver layer h3_res_{resolution} columns)
    - h3_resolution: H3 resolution level 5-13 (aggregation parameter)
    - incident_count: COUNT(*) from Silver layer grouped by H3 index
    - unique_incident_types: COUNT(DISTINCT ucr_general) from Silver layer
    - earliest_incident: MIN(incident_datetime) from Silver layer
    - latest_incident: MAX(incident_datetime) from Silver layer 
    - incidents_last_30_days: COUNT with DATE_SUB filter for 30 days
    - incidents_last_year: COUNT with DATE_SUB filter for 1 year
    - center_latitude: AVG(lat) from Silver layer incidents in hexagon
    - center_longitude: AVG(lng) from Silver layer incidents in hexagon
    - incident_type_counts: JSON_OBJECT() placeholder (empty JSON)
    - district_counts: JSON_OBJECT() placeholder (empty JSON) 
    - total_valid_records: COUNT(*) duplicate of incident_count
    - last_aggregation: NOW() timestamp of aggregation processing
    - is_empty: MySQL default 0 (all current hexagons have incidents)
    
    🔄 PARTIALLY POPULATED COLUMNS:
    - incident_ids: JSON_ARRAYAGG(incident_id) only for H3:13 (41.2% fill rate)
    
    ❌ UNPOPULATED COLUMNS (0% fill rate - need functions):
    - severity_avg: Average crime severity score per hexagon
    - severity_max: Maximum severity in hexagon 
    - data_quality_avg: Data quality metrics per hexagon
    - top_crime_type: Most frequent crime type in hexagon
    - crime_diversity_index: Shannon diversity of crime types
    - incidents_by_hour: JSON array of hourly incident counts [0-23]
    - incidents_by_dow: JSON array of day-of-week counts [0-6] 
    - incidents_by_month: JSON array of monthly counts [1-12]
    - peak_hour: Hour with most incidents (0-23)
    - peak_dow: Day of week with most incidents (0-6)
    - h3_parent: Parent H3 index at resolution-1 
    - boundary_geojson: H3 hexagon boundary as GeoJSON
    - date_range_start: First incident date in hexagon
    - date_range_end: Last incident date in hexagon 
    - data_freshness_days: Days since last incident
    - aggregation_batch_id: Processing batch tracking identifier
    """
    
    def connect_to_mysql(self) -> mysql.connector.MySQLConnection:
        """Create MySQL connection."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            if connection.is_connected():
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    # Removed generate_metro_area_h3_cells() - now using Silver layer H3 indices for all resolutions

    def is_resolution_complete(self, connection, resolution: int) -> bool:
        """Check if a resolution is already complete by comparing expected vs actual hex count."""
        cursor = connection.cursor()
        
        # Get current count of hexagons for this resolution
        cursor.execute("""
        SELECT COUNT(*) FROM amisafe_h3_aggregated 
        WHERE h3_resolution = %s
        """, (resolution,))
        current_count = cursor.fetchone()[0]
        
        # For ALL resolutions, check against Silver layer H3 indices
        h3_column = f"h3_res_{resolution}"
        cursor.execute(f"""
        SELECT COUNT(DISTINCT {h3_column}) 
        FROM amisafe_clean_incidents 
        WHERE {h3_column} IS NOT NULL AND is_duplicate = FALSE
        """, ())
        expected_count = cursor.fetchone()[0]
        
        if expected_count > 0:
            self.logger.info(f"📊 Resolution {resolution}: {current_count}/{expected_count} hexagons exist")
            # Consider complete if we have at least 95% of expected cells
            completion_threshold = int(expected_count * 0.95)
            cursor.close()
            return current_count >= completion_threshold
        else:
            self.logger.info(f"📊 Resolution {resolution}: No source data available")
            cursor.close()
            return True  # No data to process, consider complete

    def create_h3_aggregations(self, connection, resolution: int):
        """Create H3 aggregations at specified resolution from Transform layer data."""
        self.logger.info(f"Creating H3 aggregations for resolution {resolution}")
        
        cursor = connection.cursor()
        
        # Check if this resolution is already complete
        if self.is_resolution_complete(connection, resolution):
            self.logger.info(f"✅ Resolution {resolution} is already complete, skipping...")
            return
        
        # Clear existing aggregations for this resolution
        cursor.execute("DELETE FROM amisafe_h3_aggregated WHERE h3_resolution = %s", (resolution,))
        
        # Use pre-calculated H3 indices from Silver layer for ALL resolutions
        # This eliminates the over-counting problem from spatial radius queries
        h3_column = f"h3_res_{resolution}"
        
        # Include incident_ids for H3:13 granular filtering
        if resolution >= 13:
            aggregation_query = f"""
            INSERT INTO amisafe_h3_aggregated (
                h3_index, h3_resolution, incident_count, unique_incident_types,
                earliest_incident, latest_incident, incidents_last_30_days, incidents_last_year,
                center_latitude, center_longitude, incident_type_counts, district_counts,
                total_valid_records, last_aggregation, incident_ids
            )
            SELECT 
                {h3_column} as h3_index,
                %s as h3_resolution,
                COUNT(*) as incident_count,
                COUNT(DISTINCT ucr_general) as unique_incident_types,
                MIN(incident_datetime) as earliest_incident,
                MAX(incident_datetime) as latest_incident,
                COUNT(CASE WHEN incident_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as incidents_last_30_days,
                COUNT(CASE WHEN incident_datetime >= DATE_SUB(NOW(), INTERVAL 1 YEAR) THEN 1 END) as incidents_last_year,
                AVG(lat) as center_latitude,
                AVG(lng) as center_longitude,
                JSON_OBJECT() as incident_type_counts,
                JSON_OBJECT() as district_counts,
                COUNT(*) as total_valid_records,
                NOW() as last_aggregation,
                JSON_ARRAYAGG(incident_id) as incident_ids
            FROM amisafe_clean_incidents 
            WHERE {h3_column} IS NOT NULL 
                AND is_duplicate = FALSE
            GROUP BY {h3_column}
            HAVING COUNT(*) > 0
            """
        else:
            aggregation_query = f"""
            INSERT INTO amisafe_h3_aggregated (
                h3_index, h3_resolution, incident_count, unique_incident_types,
                earliest_incident, latest_incident, incidents_last_30_days, incidents_last_year,
                center_latitude, center_longitude, incident_type_counts, district_counts,
                total_valid_records, last_aggregation
            )
            SELECT 
                {h3_column} as h3_index,
                %s as h3_resolution,
                COUNT(*) as incident_count,
                COUNT(DISTINCT ucr_general) as unique_incident_types,
                MIN(incident_datetime) as earliest_incident,
                MAX(incident_datetime) as latest_incident,
                COUNT(CASE WHEN incident_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as incidents_last_30_days,
                COUNT(CASE WHEN incident_datetime >= DATE_SUB(NOW(), INTERVAL 1 YEAR) THEN 1 END) as incidents_last_year,
                AVG(lat) as center_latitude,
                AVG(lng) as center_longitude,
                JSON_OBJECT() as incident_type_counts,
                JSON_OBJECT() as district_counts,
                COUNT(*) as total_valid_records,
                NOW() as last_aggregation
            FROM amisafe_clean_incidents 
            WHERE {h3_column} IS NOT NULL 
                AND is_duplicate = FALSE
            GROUP BY {h3_column}
            HAVING COUNT(*) > 0
            """
        
        cursor.execute(aggregation_query, (resolution,))
        rows_affected = cursor.rowcount
        self.logger.info(f"Created {rows_affected} H3:{resolution} aggregation records")
        
        cursor.close()
        
    # ========================================================================
    # EMPTY FUNCTIONS FOR UNPOPULATED COLUMNS - TODO: IMPLEMENT
    # ========================================================================
    
    def calculate_severity_metrics(self, connection, h3_index: str, resolution: int) -> Tuple[float, int]:
        """Calculate average and maximum severity for incidents in hexagon.
        
        TODO: Implement severity scoring based on:
        - UCR crime type severity weights
        - Incident outcome severity (arrests, injuries, property damage)
        - Time-of-day risk factors
        - Location risk factors
        
        Returns:
            Tuple[float, int]: (severity_avg, severity_max)
        """
        # TODO: Implement severity calculation logic
        return (0.0, 0)
    
    def calculate_data_quality_avg(self, connection, h3_index: str, resolution: int) -> float:
        """Calculate average data quality score for incidents in hexagon.
        
        TODO: Implement data quality scoring based on:
        - Completeness of required fields
        - Accuracy of geocoding
        - Consistency of crime type classification  
        - Timeliness of incident reporting
        
        Returns:
            float: Average data quality score (0.0-1.0)
        """
        # TODO: Implement data quality calculation logic
        return 0.0
    
    def fetch_hex_incidents(self, connection, h3_index: str, resolution: int, 
                            chunk_size: int = 100000) -> List[Dict]:
        """Fetch all incident data for a hexagon, using chunked queries for large datasets.
        
        For lower resolutions (5-9) that may contain millions of incidents per hex,
        fetches data in chunks to avoid memory issues.
        
        Args:
            connection: MySQL connection
            h3_index: H3 hexagon identifier
            resolution: H3 resolution level
            chunk_size: Number of records to fetch per chunk (default 100k)
        
        Returns:
            List[Dict]: All incident records for this hexagon
        """
        cursor = connection.cursor(dictionary=True)
        h3_column = f"h3_res_{resolution}"
        
        try:
            # For lower resolutions (5-9), use chunked fetching
            if resolution <= 9:
                self.logger.info(f"📦 Chunked fetch for {h3_index} at resolution {resolution}")
                
                all_incidents = []
                offset = 0
                
                while True:
                    query = f"""
                    SELECT 
                        incident_id,
                        ucr_general,
                        dc_dist,
                        incident_datetime,
                        lat,
                        lng,
                        HOUR(incident_datetime) as hour_of_day,
                        WEEKDAY(incident_datetime) as day_of_week,
                        MONTH(incident_datetime) as month_num,
                        DATE(incident_datetime) as incident_date
                    FROM amisafe_clean_incidents 
                    WHERE {h3_column} = %s 
                        AND is_duplicate = FALSE
                        AND incident_datetime IS NOT NULL
                    LIMIT %s OFFSET %s
                    """
                    
                    cursor.execute(query, (h3_index, chunk_size, offset))
                    chunk = cursor.fetchall()
                    
                    if not chunk:
                        break
                    
                    all_incidents.extend(chunk)
                    offset += chunk_size
                    
                    if len(chunk) < chunk_size:
                        break
                    
                    self.logger.info(f"    Fetched {len(all_incidents):,} incidents so far...")
                
                self.logger.info(f"  ✅ Total: {len(all_incidents):,} incidents for {h3_index}")
                return all_incidents
            
            else:
                # For higher resolutions (10-13), single query is fine
                query = f"""
                SELECT 
                    incident_id,
                    ucr_general,
                    dc_dist,
                    incident_datetime,
                    lat,
                    lng,
                    HOUR(incident_datetime) as hour_of_day,
                    WEEKDAY(incident_datetime) as day_of_week,
                    MONTH(incident_datetime) as month_num,
                    DATE(incident_datetime) as incident_date
                FROM amisafe_clean_incidents 
                WHERE {h3_column} = %s 
                    AND is_duplicate = FALSE
                    AND incident_datetime IS NOT NULL
                """
                
                cursor.execute(query, (h3_index,))
                incidents = cursor.fetchall()
                
                return incidents
            
        except Exception as e:
            self.logger.error(f"Error fetching incidents for {h3_index}: {e}")
            return []
        finally:
            cursor.close()
    
    def calculate_analytics_from_incidents(self, incidents: List[Dict], h3_index: str, resolution: int) -> Dict:
        """Calculate all analytics from in-memory incident data.
        
        Args:
            incidents: List of incident dictionaries from Silver layer
            h3_index: H3 hexagon identifier
            resolution: H3 resolution level
            
        Returns:
            Dict: All calculated analytical values
        """
        analytics = {
            'top_crime_type': None,
            'crime_diversity_index': 0.0,
            'incidents_by_hour': [0] * 24,
            'incidents_by_dow': [0] * 7,
            'incidents_by_month': [0] * 12,
            'peak_hour': None,
            'peak_dow': None,
            'h3_parent': None,
            'boundary_geojson': None,
            'date_range_start': None,
            'date_range_end': None,
            'data_freshness_days': None,
            'aggregation_batch_id': None,
            'incident_type_counts': {},
            'district_counts': {}
        }
        
        if not incidents:
            return analytics
            
        # Count crime types for top crime and diversity
        crime_counts = {}
        district_counts = {}
        dates = []
        
        for incident in incidents:
            # Crime type counting
            crime_type = incident.get('ucr_general')
            if crime_type:
                crime_counts[crime_type] = crime_counts.get(crime_type, 0) + 1
            
            # District counting
            district = incident.get('dc_dist')
            if district:
                district_counts[str(district)] = district_counts.get(str(district), 0) + 1
            
            # Temporal patterns
            hour = incident.get('hour_of_day')
            if hour is not None and 0 <= hour <= 23:
                analytics['incidents_by_hour'][hour] += 1
                
            dow = incident.get('day_of_week')
            if dow is not None and 0 <= dow <= 6:
                analytics['incidents_by_dow'][dow] += 1
                
            month = incident.get('month_num')
            if month is not None and 1 <= month <= 12:
                analytics['incidents_by_month'][month - 1] += 1  # 0-indexed
                
            # Date tracking
            if incident.get('incident_date'):
                dates.append(incident['incident_date'])
        
        # Store crime type and district counts
        analytics['incident_type_counts'] = crime_counts
        analytics['district_counts'] = district_counts
        
        # Calculate top crime type
        if crime_counts:
            analytics['top_crime_type'] = max(crime_counts.keys(), key=crime_counts.get)
            
            # Calculate Shannon diversity index
            if len(crime_counts) > 1:
                total_crimes = sum(crime_counts.values())
                shannon_index = 0.0
                
                for count in crime_counts.values():
                    if count > 0:
                        proportion = count / total_crimes
                        shannon_index -= proportion * np.log(proportion)
                
                analytics['crime_diversity_index'] = round(shannon_index, 3)
        
        # Find peak hour and day of week
        if any(analytics['incidents_by_hour']):
            analytics['peak_hour'] = analytics['incidents_by_hour'].index(max(analytics['incidents_by_hour']))
            
        if any(analytics['incidents_by_dow']):
            analytics['peak_dow'] = analytics['incidents_by_dow'].index(max(analytics['incidents_by_dow']))
        
        # Calculate H3 parent
        analytics['h3_parent'] = self.get_h3_parent(h3_index, resolution)
        
        # Generate boundary GeoJSON
        boundary = self.generate_boundary_geojson(h3_index)
        analytics['boundary_geojson'] = json.dumps(boundary) if boundary else None
        
        # Calculate date range and freshness
        if dates:
            analytics['date_range_start'] = min(dates).strftime('%Y-%m-%d')
            analytics['date_range_end'] = max(dates).strftime('%Y-%m-%d')
            
            from datetime import datetime
            freshness = (datetime.now().date() - max(dates)).days
            analytics['data_freshness_days'] = freshness
        
        # Generate batch ID (same for all records in this run)
        if not hasattr(self, '_current_batch_id'):
            self._current_batch_id = self.generate_batch_id()
        analytics['aggregation_batch_id'] = self._current_batch_id
        
        return analytics
    
    # Removed calculate_temporal_patterns() - now calculated in-memory from fetched data
    
    def get_h3_parent(self, h3_index: str, resolution: int) -> str:
        """Get parent H3 index at resolution-1 for hierarchical navigation.
        
        Returns:
            str: Parent H3 index or None if resolution=0
        """
        try:
            if resolution <= 0:
                return None
                
            # Get parent H3 cell at resolution-1
            parent_resolution = resolution - 1
            parent_index = h3.cell_to_parent(h3_index, parent_resolution)
            
            return parent_index
            
        except Exception as e:
            self.logger.error(f"Error getting H3 parent for {h3_index} at resolution {resolution}: {e}")
            return None
    
    def generate_boundary_geojson(self, h3_index: str) -> Dict:
        """Generate GeoJSON boundary for H3 hexagon.
        
        Returns:
            Dict: GeoJSON Polygon feature
        """
        try:
            # Get hexagon boundary vertices
            boundary = h3.cell_to_boundary(h3_index)
            
            # Convert to GeoJSON coordinates format [lng, lat]
            coordinates = []
            for lat, lng in boundary:
                coordinates.append([lng, lat])
            
            # Close the polygon by adding first point at the end
            if coordinates:
                coordinates.append(coordinates[0])
            
            # Create GeoJSON Polygon
            geojson = {
                "type": "Feature",
                "properties": {
                    "h3_index": h3_index,
                    "resolution": h3.get_resolution(h3_index)
                },
                "geometry": {
                    "type": "Polygon",
                    "coordinates": [coordinates]
                }
            }
            
            return geojson
            
        except Exception as e:
            self.logger.error(f"Error generating boundary GeoJSON for {h3_index}: {e}")
            return None
    
    # Removed calculate_date_range_and_freshness() - now calculated in-memory from fetched data
    
    def calculate_analytics_from_incidents_with_stats(self, incidents: List[Dict], 
                                                       h3_index: str, resolution: int,
                                                       all_hex_stats: List[Dict] = None) -> Dict:
        """Calculate all analytics from in-memory incident data including statistical metrics.
        
        Args:
            incidents: List of incident dictionaries from Silver layer (already in memory)
            h3_index: H3 hexagon identifier
            resolution: H3 resolution level
            all_hex_stats: Statistics for all hexagons (for z-scores/percentiles)
            
        Returns:
            Dict: All calculated analytical values ready for database update
        """
        try:
            # Calculate all basic analytics from in-memory data
            analytics = self.calculate_analytics_from_incidents(incidents, h3_index, resolution)
            
            # Calculate statistical metrics if we have population data
            if all_hex_stats:
                statistical_metrics = self.stats_calculator.calculate_complete_statistics(
                    incidents, all_hex_stats)
                analytics.update(statistical_metrics)
            
            # Convert arrays and dicts to JSON strings for database storage
            analytics['incident_type_counts'] = json.dumps(analytics['incident_type_counts'])
            analytics['district_counts'] = json.dumps(analytics['district_counts'])
            analytics['incidents_by_hour'] = json.dumps(analytics['incidents_by_hour'])
            analytics['incidents_by_dow'] = json.dumps(analytics['incidents_by_dow'])
            analytics['incidents_by_month'] = json.dumps(analytics['incidents_by_month'])
            
            # Convert temporal JSON arrays for windowed stats
            if 'incidents_by_hour_12mo' in analytics:
                analytics['incidents_by_hour_12mo'] = json.dumps(analytics['incidents_by_hour_12mo'])
                analytics['incidents_by_dow_12mo'] = json.dumps(analytics['incidents_by_dow_12mo'])
                analytics['incidents_by_month_12mo'] = json.dumps(analytics['incidents_by_month_12mo'])
            
            if 'incidents_by_hour_6mo' in analytics:
                analytics['incidents_by_hour_6mo'] = json.dumps(analytics['incidents_by_hour_6mo'])
                analytics['incidents_by_dow_6mo'] = json.dumps(analytics['incidents_by_dow_6mo'])
                analytics['incidents_by_month_6mo'] = json.dumps(analytics['incidents_by_month_6mo'])
            
            # TODO: Implement remaining functions
            analytics['severity_avg'] = None
            analytics['severity_max'] = None
            analytics['data_quality_avg'] = None
            
            return analytics
            
        except Exception as e:
            self.logger.error(f"Error in calculate_analytics_from_incidents_with_stats for {h3_index}: {e}")
            return {
                'severity_avg': None,
                'severity_max': None,
                'data_quality_avg': None,
                'top_crime_type': None,
                'crime_diversity_index': None,
                'incidents_by_hour': None,
                'incidents_by_dow': None,
                'incidents_by_month': None,
                'peak_hour': None,
                'peak_dow': None,
                'h3_parent': None,
                'boundary_geojson': None,
                'date_range_start': None,
                'date_range_end': None,
                'data_freshness_days': None,
                'aggregation_batch_id': None
            }
    
    def generate_batch_id(self) -> str:
        """Generate unique batch ID for aggregation tracking.
        
        Returns:
            str: Unique batch identifier
        """
        import uuid
        from datetime import datetime
        
        # Generate timestamp-based batch ID with UUID suffix for uniqueness
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        uuid_suffix = str(uuid.uuid4())[:8]
        
        return f"AGG_{timestamp}_{uuid_suffix}"
    
    def populate_advanced_analytics(self, connection, h3_index: str, resolution: int, all_hex_stats: List[Dict] = None) -> Dict:
        """Populate all advanced analytical columns for a hexagon.
        
        Uses single query approach: fetch all incident data once, calculate in memory.
        
        Args:
            connection: MySQL connection
            h3_index: H3 hexagon identifier
            resolution: H3 resolution level
            all_hex_stats: Statistics for all hexagons (for z-scores/percentiles)
        
        Returns:
            Dict: All calculated analytical values
        """
        try:
            # Single query to fetch all incident data for this hexagon
            incidents = self.fetch_hex_incidents(connection, h3_index, resolution)
            
            # Calculate all analytics from in-memory data
            analytics = self.calculate_analytics_from_incidents(incidents, h3_index, resolution)
            
            # Calculate statistical metrics if we have population data
            if all_hex_stats:
                statistical_metrics = self.stats_calculator.calculate_complete_statistics(
                    incidents, all_hex_stats)
                analytics.update(statistical_metrics)
            
            # Convert arrays and dicts to JSON strings for database storage
            analytics['incident_type_counts'] = json.dumps(analytics['incident_type_counts'])
            analytics['district_counts'] = json.dumps(analytics['district_counts'])
            analytics['incidents_by_hour'] = json.dumps(analytics['incidents_by_hour'])
            analytics['incidents_by_dow'] = json.dumps(analytics['incidents_by_dow'])
            analytics['incidents_by_month'] = json.dumps(analytics['incidents_by_month'])
            
            # Convert temporal JSON arrays for windowed stats
            if 'incidents_by_hour_12mo' in analytics:
                analytics['incidents_by_hour_12mo'] = json.dumps(analytics['incidents_by_hour_12mo'])
                analytics['incidents_by_dow_12mo'] = json.dumps(analytics['incidents_by_dow_12mo'])
                analytics['incidents_by_month_12mo'] = json.dumps(analytics['incidents_by_month_12mo'])
            
            if 'incidents_by_hour_6mo' in analytics:
                analytics['incidents_by_hour_6mo'] = json.dumps(analytics['incidents_by_hour_6mo'])
                analytics['incidents_by_dow_6mo'] = json.dumps(analytics['incidents_by_dow_6mo'])
                analytics['incidents_by_month_6mo'] = json.dumps(analytics['incidents_by_month_6mo'])
            
            # TODO: Implement remaining functions
            analytics['severity_avg'] = None
            analytics['severity_max'] = None
            analytics['data_quality_avg'] = None
            
            return analytics
            
        except Exception as e:
            self.logger.error(f"Error in populate_advanced_analytics for {h3_index}: {e}")
            return {
                'severity_avg': None,
                'severity_max': None,
                'data_quality_avg': None,
                'top_crime_type': None,
                'crime_diversity_index': None,
                'incidents_by_hour': None,
                'incidents_by_dow': None,
                'incidents_by_month': None,
                'peak_hour': None,
                'peak_dow': None,
                'h3_parent': None,
                'boundary_geojson': None,
                'date_range_start': None,
                'date_range_end': None,
                'data_freshness_days': None,
                'aggregation_batch_id': None
            }
    
    def update_advanced_analytics(self, connection, resolution: int):
        """Update existing aggregation records with advanced analytics using two-pass approach.
        
        Uses indexed H3 resolution columns for fast per-hexagon queries.
        
        Pass 1: Collect basic statistics for all hexagons (needed for mean/std_dev calculations)
        Pass 2: Calculate z-scores, percentiles, and risk scores using population statistics
        """
        self.logger.info(f"Updating advanced analytics for resolution {resolution}")
        
        cursor = connection.cursor()
        
        try:
            # First check if this resolution is already complete
            cursor.execute("""
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN crime_diversity_index IS NOT NULL THEN 1 ELSE 0 END) as completed
            FROM amisafe_h3_aggregated 
            WHERE h3_resolution = %s
            """, (resolution,))
            
            stats = cursor.fetchone()
            total_hexes = stats[0]
            completed_hexes = stats[1]
            
            if completed_hexes == total_hexes and total_hexes > 0:
                self.logger.info(f"✅ Resolution {resolution} already has complete analytics ({completed_hexes}/{total_hexes}), skipping...")
                return
            
            self.logger.info(f"📊 Resolution {resolution}: {completed_hexes}/{total_hexes} already complete, processing remaining {total_hexes - completed_hexes}...")
            
            # Get only H3 indices that need analytics (where crime_diversity_index is NULL)
            cursor.execute("""
            SELECT h3_index FROM amisafe_h3_aggregated 
            WHERE h3_resolution = %s AND crime_diversity_index IS NULL
            """, (resolution,))
            
            h3_indices = [row[0] for row in cursor.fetchall()]
            remaining_hexes = len(h3_indices)
            
            if remaining_hexes == 0:
                self.logger.info(f"✅ No remaining hexagons to process for resolution {resolution}")
                return
            
            self.logger.info(f"Pass 1/2: Collecting basic statistics for {len(h3_indices)} hexagons...")
            
            # PASS 1: Collect basic statistics for all hexagons
            all_hex_stats = []
            for i, h3_index in enumerate(h3_indices, 1):
                if i % 100 == 0 or i == 1:
                    self.logger.info(f"  Collecting stats {i}/{len(h3_indices)}: {h3_index}")
                
                # Query incidents using indexed column for fast retrieval
                incidents = self.fetch_hex_incidents(connection, h3_index, resolution)
                
                # Calculate basic stats (counts only, no z-scores yet)
                basic_stats = {
                    'h3_index': h3_index
                }
                
                # All-time stats
                all_time_basic = self.stats_calculator.calculate_basic_stats(incidents)
                basic_stats.update(all_time_basic)
                basic_stats['incident_count'] = len(incidents)
                
                # 12-month window stats
                incidents_12mo = self.stats_calculator.filter_incidents_by_window(incidents, 12)
                mo12_basic = self.stats_calculator.calculate_basic_stats(incidents_12mo)
                basic_stats['violent_crime_count_12mo'] = mo12_basic['violent_count']
                basic_stats['nonviolent_crime_count_12mo'] = mo12_basic['nonviolent_count']
                basic_stats['incident_count_12mo'] = len(incidents_12mo)
                
                # 6-month window stats
                incidents_6mo = self.stats_calculator.filter_incidents_by_window(incidents, 6)
                mo6_basic = self.stats_calculator.calculate_basic_stats(incidents_6mo)
                basic_stats['violent_crime_count_6mo'] = mo6_basic['violent_count']
                basic_stats['nonviolent_crime_count_6mo'] = mo6_basic['nonviolent_count']
                basic_stats['incident_count_6mo'] = len(incidents_6mo)
                
                all_hex_stats.append(basic_stats)
            
            self.logger.info(f"Pass 2/2: Calculating statistical analytics for {len(h3_indices)} hexagons...")
            
            # PASS 2: Calculate full analytics including z-scores and percentiles
            for i, h3_index in enumerate(h3_indices, 1):
                if i % 100 == 0 or i == 1:
                    self.logger.info(f"  Processing hex {i}/{len(h3_indices)}: {h3_index}")
                
                # Query incidents again using indexed column (fast with index)
                incidents = self.fetch_hex_incidents(connection, h3_index, resolution)
                
                # Calculate complete advanced analytics with population statistics
                analytics = self.calculate_analytics_from_incidents_with_stats(
                    incidents, h3_index, resolution, all_hex_stats)
                
                # Update the record with all analytics
                update_query = """
                UPDATE amisafe_h3_aggregated SET
                    incident_type_counts = %s,
                    district_counts = %s,
                    top_crime_type = %s,
                    crime_diversity_index = %s,
                    incidents_by_hour = %s,
                    incidents_by_dow = %s,
                    incidents_by_month = %s,
                    peak_hour = %s,
                    peak_dow = %s,
                    h3_parent = %s,
                    boundary_geojson = %s,
                    date_range_start = %s,
                    date_range_end = %s,
                    data_freshness_days = %s,
                    aggregation_batch_id = %s,
                    
                    -- All-time statistical fields
                    violent_crime_count = %s,
                    violent_crime_percentage = %s,
                    violent_crime_mean = %s,
                    violent_crime_std_dev = %s,
                    violent_crime_z_score = %s,
                    violent_crime_percentile = %s,
                    nonviolent_crime_count = %s,
                    nonviolent_crime_percentage = %s,
                    nonviolent_crime_mean = %s,
                    nonviolent_crime_std_dev = %s,
                    nonviolent_crime_z_score = %s,
                    nonviolent_crime_percentile = %s,
                    incident_mean = %s,
                    incident_std_dev = %s,
                    incident_z_score = %s,
                    incident_percentile = %s,
                    risk_score = %s,
                    risk_category = %s,
                    hotspot_status = %s,
                    
                    -- 12-month window fields
                    incident_count_12mo = %s,
                    unique_incident_types_12mo = %s,
                    incidents_by_hour_12mo = %s,
                    incidents_by_dow_12mo = %s,
                    incidents_by_month_12mo = %s,
                    peak_hour_12mo = %s,
                    peak_dow_12mo = %s,
                    top_crime_type_12mo = %s,
                    crime_diversity_index_12mo = %s,
                    violent_crime_count_12mo = %s,
                    violent_crime_percentage_12mo = %s,
                    violent_crime_mean_12mo = %s,
                    violent_crime_std_dev_12mo = %s,
                    violent_crime_z_score_12mo = %s,
                    violent_crime_percentile_12mo = %s,
                    nonviolent_crime_count_12mo = %s,
                    nonviolent_crime_percentage_12mo = %s,
                    nonviolent_crime_mean_12mo = %s,
                    nonviolent_crime_std_dev_12mo = %s,
                    nonviolent_crime_z_score_12mo = %s,
                    nonviolent_crime_percentile_12mo = %s,
                    incident_mean_12mo = %s,
                    incident_std_dev_12mo = %s,
                    incident_z_score_12mo = %s,
                    incident_percentile_12mo = %s,
                    risk_score_12mo = %s,
                    risk_category_12mo = %s,
                    hotspot_status_12mo = %s,
                    
                    -- 6-month window fields
                    incident_count_6mo = %s,
                    unique_incident_types_6mo = %s,
                    incidents_by_hour_6mo = %s,
                    incidents_by_dow_6mo = %s,
                    incidents_by_month_6mo = %s,
                    peak_hour_6mo = %s,
                    peak_dow_6mo = %s,
                    top_crime_type_6mo = %s,
                    crime_diversity_index_6mo = %s,
                    violent_crime_count_6mo = %s,
                    violent_crime_percentage_6mo = %s,
                    violent_crime_mean_6mo = %s,
                    violent_crime_std_dev_6mo = %s,
                    violent_crime_z_score_6mo = %s,
                    violent_crime_percentile_6mo = %s,
                    nonviolent_crime_count_6mo = %s,
                    nonviolent_crime_percentage_6mo = %s,
                    nonviolent_crime_mean_6mo = %s,
                    nonviolent_crime_std_dev_6mo = %s,
                    nonviolent_crime_z_score_6mo = %s,
                    nonviolent_crime_percentile_6mo = %s,
                    incident_mean_6mo = %s,
                    incident_std_dev_6mo = %s,
                    incident_z_score_6mo = %s,
                    incident_percentile_6mo = %s,
                    risk_score_6mo = %s,
                    risk_category_6mo = %s,
                    hotspot_status_6mo = %s
                WHERE h3_index = %s AND h3_resolution = %s
                """
                
                cursor.execute(update_query, (
                    # Original fields
                    analytics['incident_type_counts'],
                    analytics['district_counts'],
                    analytics['top_crime_type'],
                    analytics['crime_diversity_index'],
                    analytics['incidents_by_hour'],
                    analytics['incidents_by_dow'],
                    analytics['incidents_by_month'],
                    analytics['peak_hour'],
                    analytics['peak_dow'],
                    analytics['h3_parent'],
                    analytics['boundary_geojson'],
                    analytics['date_range_start'],
                    analytics['date_range_end'],
                    analytics['data_freshness_days'],
                    analytics['aggregation_batch_id'],
                    
                    # All-time statistical fields
                    analytics.get('violent_crime_count', 0),
                    analytics.get('violent_crime_percentage', 0.0),
                    analytics.get('violent_crime_mean', 0.0),
                    analytics.get('violent_crime_std_dev', 0.0),
                    analytics.get('violent_crime_z_score', 0.0),
                    analytics.get('violent_crime_percentile', 50),
                    analytics.get('nonviolent_crime_count', 0),
                    analytics.get('nonviolent_crime_percentage', 0.0),
                    analytics.get('nonviolent_crime_mean', 0.0),
                    analytics.get('nonviolent_crime_std_dev', 0.0),
                    analytics.get('nonviolent_crime_z_score', 0.0),
                    analytics.get('nonviolent_crime_percentile', 50),
                    analytics.get('incident_mean', 0.0),
                    analytics.get('incident_std_dev', 0.0),
                    analytics.get('incident_z_score', 0.0),
                    analytics.get('incident_percentile', 50),
                    analytics.get('risk_score', 0.0),
                    analytics.get('risk_category', 'MODERATE'),
                    analytics.get('hotspot_status', 'WARM'),
                    
                    # 12-month window fields
                    analytics.get('incident_count_12mo', 0),
                    analytics.get('unique_incident_types_12mo', 0),
                    analytics.get('incidents_by_hour_12mo'),
                    analytics.get('incidents_by_dow_12mo'),
                    analytics.get('incidents_by_month_12mo'),
                    analytics.get('peak_hour_12mo'),
                    analytics.get('peak_dow_12mo'),
                    analytics.get('top_crime_type_12mo'),
                    analytics.get('crime_diversity_index_12mo', 0.0),
                    analytics.get('violent_crime_count_12mo', 0),
                    analytics.get('violent_crime_percentage_12mo', 0.0),
                    analytics.get('violent_crime_mean_12mo', 0.0),
                    analytics.get('violent_crime_std_dev_12mo', 0.0),
                    analytics.get('violent_crime_z_score_12mo', 0.0),
                    analytics.get('violent_crime_percentile_12mo', 50),
                    analytics.get('nonviolent_crime_count_12mo', 0),
                    analytics.get('nonviolent_crime_percentage_12mo', 0.0),
                    analytics.get('nonviolent_crime_mean_12mo', 0.0),
                    analytics.get('nonviolent_crime_std_dev_12mo', 0.0),
                    analytics.get('nonviolent_crime_z_score_12mo', 0.0),
                    analytics.get('nonviolent_crime_percentile_12mo', 50),
                    analytics.get('incident_mean_12mo', 0.0),
                    analytics.get('incident_std_dev_12mo', 0.0),
                    analytics.get('incident_z_score_12mo', 0.0),
                    analytics.get('incident_percentile_12mo', 50),
                    analytics.get('risk_score_12mo', 0.0),
                    analytics.get('risk_category_12mo', 'MODERATE'),
                    analytics.get('hotspot_status_12mo', 'WARM'),
                    
                    # 6-month window fields
                    analytics.get('incident_count_6mo', 0),
                    analytics.get('unique_incident_types_6mo', 0),
                    analytics.get('incidents_by_hour_6mo'),
                    analytics.get('incidents_by_dow_6mo'),
                    analytics.get('incidents_by_month_6mo'),
                    analytics.get('peak_hour_6mo'),
                    analytics.get('peak_dow_6mo'),
                    analytics.get('top_crime_type_6mo'),
                    analytics.get('crime_diversity_index_6mo', 0.0),
                    analytics.get('violent_crime_count_6mo', 0),
                    analytics.get('violent_crime_percentage_6mo', 0.0),
                    analytics.get('violent_crime_mean_6mo', 0.0),
                    analytics.get('violent_crime_std_dev_6mo', 0.0),
                    analytics.get('violent_crime_z_score_6mo', 0.0),
                    analytics.get('violent_crime_percentile_6mo', 50),
                    analytics.get('nonviolent_crime_count_6mo', 0),
                    analytics.get('nonviolent_crime_percentage_6mo', 0.0),
                    analytics.get('nonviolent_crime_mean_6mo', 0.0),
                    analytics.get('nonviolent_crime_std_dev_6mo', 0.0),
                    analytics.get('nonviolent_crime_z_score_6mo', 0.0),
                    analytics.get('nonviolent_crime_percentile_6mo', 50),
                    analytics.get('incident_mean_6mo', 0.0),
                    analytics.get('incident_std_dev_6mo', 0.0),
                    analytics.get('incident_z_score_6mo', 0.0),
                    analytics.get('incident_percentile_6mo', 50),
                    analytics.get('risk_score_6mo', 0.0),
                    analytics.get('risk_category_6mo', 'MODERATE'),
                    analytics.get('hotspot_status_6mo', 'WARM'),
                    
                    # WHERE clause
                    h3_index,
                    resolution
                ))
            
            self.logger.info(f"✅ Updated {total_hexes} records with complete analytics for resolution {resolution}")
            
        except Exception as e:
            self.logger.error(f"Error updating advanced analytics for resolution {resolution}: {e}")
            import traceback
            self.logger.error(traceback.format_exc())
        finally:
            cursor.close()

    def run_full_aggregation(self, resolutions: List[int] = [5, 6, 7, 8, 9, 10, 11, 12, 13]) -> Dict:
        """Run the complete Final Layer (Gold) aggregation pipeline."""
        self.logger.info(f"Starting Final Layer aggregation for H3 resolutions: {resolutions}")
        
        connection = self.connect_to_mysql()
        results = {}
        
        try:
            # Process each resolution
            for resolution in resolutions:
                self.logger.info(f"\n📊 Processing H3 Resolution {resolution}...")
                
                # Create H3 aggregations for this resolution
                self.create_h3_aggregations(connection, resolution)
                
                # Verify the aggregation
                verification = self.verify_aggregation(connection, resolution)
                results[resolution] = verification
            
            # Generate final analytics summary
            results['summary'] = self.generate_final_summary(connection)
            
            self.logger.info("Final Layer aggregation pipeline completed successfully")
            return results
            
        finally:
            if connection.is_connected():
                connection.close()
                
    def verify_aggregation(self, connection, resolution: int) -> Dict:
        """Verify the H3 aggregation for a given resolution."""
        cursor = connection.cursor()
        
        # Get aggregation statistics
        cursor.execute("""
        SELECT 
            COUNT(*) as total_hexagons,
            SUM(incident_count) as total_incidents,
            AVG(incident_count) as avg_incidents_per_hex,
            MIN(incident_count) as min_incidents,
            MAX(incident_count) as max_incidents,
            COUNT(CASE WHEN incident_count > 0 THEN 1 END) as non_empty_hexagons
        FROM amisafe_h3_aggregated 
        WHERE h3_resolution = %s
        """, (resolution,))
        
        stats = cursor.fetchone()
        cursor.close()
        
        # Get resolution description
        res_descriptions = {
            5: "Metro Area (~251km²)",
            6: "Districts (~36km²)", 
            7: "Neighborhoods (~5.2km²)",
            8: "Areas (~0.7km²)",
            9: "Blocks (~0.1km²)",
            10: "Sub-blocks (~15,047m²)"
        }
        desc = res_descriptions.get(resolution, f"Resolution {resolution}")
        
        result = {
            'resolution': resolution,
            'description': desc,
            'total_hexagons': stats[0] if stats else 0,
            'total_incidents': stats[1] if stats and stats[1] else 0,
            'avg_incidents_per_hex': float(stats[2]) if stats and stats[2] else 0.0,
            'min_incidents': stats[3] if stats else 0,
            'max_incidents': stats[4] if stats else 0,
            'non_empty_hexagons': stats[5] if stats else 0
        }
        
        self.logger.info(f"H3:{resolution} - {desc}: {result['total_hexagons']} hexagons, {result['total_incidents']} incidents")
        return result
        
    def generate_final_summary(self, connection) -> Dict:
        """Generate final summary statistics for the Gold layer."""
        cursor = connection.cursor()
        
        # Overall statistics
        cursor.execute("""
        SELECT 
            h3_resolution,
            COUNT(*) as hexagon_count,
            SUM(incident_count) as total_incidents
        FROM amisafe_h3_aggregated 
        GROUP BY h3_resolution
        ORDER BY h3_resolution
        """)
        
        resolution_stats = {}
        total_hexagons = 0
        total_incidents = 0
        
        for row in cursor.fetchall():
            resolution = row[0]
            hexagon_count = row[1]
            incident_count = row[2] if row[2] else 0
            
            resolution_stats[resolution] = {
                'hexagons': hexagon_count,
                'incidents': incident_count
            }
            total_hexagons += hexagon_count
            total_incidents += incident_count
        
        cursor.close()
        
        return {
            'total_hexagons_all_resolutions': total_hexagons,
            'total_incidents_all_resolutions': total_incidents,
            'resolution_breakdown': resolution_stats,
            'timestamp': datetime.now().isoformat()
        }


def main():
    """Main function to run the Final Layer (Gold) aggregator."""
    parser = argparse.ArgumentParser(description='AmISafe Final Layer (Gold) Aggregator')
    parser.add_argument('--mysql-host', default='127.0.0.1', help='MySQL host')
    parser.add_argument('--mysql-user', default='drupal_user', help='MySQL user')
    parser.add_argument('--mysql-password', default='drupal_secure_password', help='MySQL password')
    parser.add_argument('--mysql-database', default='amisafe_database', help='MySQL database')
    parser.add_argument('--resolutions', nargs='+', type=int, default=[5, 6, 7, 8, 9, 10, 11, 12, 13], 
                        help='H3 resolutions to process (default: 5 6 7 8 9 10 11 12 13)')
    parser.add_argument('--analytics', action='store_true', 
                        help='Run advanced analytics (temporal patterns, crime diversity, etc.)')
    parser.add_argument('--analytics-only', action='store_true',
                        help='Only run advanced analytics on existing aggregations (skip basic aggregation)')
    
    args = parser.parse_args()
    
    # Initialize Final Layer aggregator
    aggregator = AmISafeFinalLayerAggregator(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database
    )
    
    try:
        if args.analytics_only:
            # Run only advanced analytics on existing data
            print(f"🔬 Running advanced analytics for H3 resolutions: {args.resolutions}")
            connection = aggregator.connect_to_mysql()
            results = {}
            
            try:
                for resolution in args.resolutions:
                    print(f"📊 Processing advanced analytics for H3 Resolution {resolution}...")
                    aggregator.update_advanced_analytics(connection, resolution)
                    
                    # Verify the analytics
                    verification = aggregator.verify_aggregation(connection, resolution)
                    results[resolution] = verification
                
                # Generate summary
                results['summary'] = aggregator.generate_final_summary(connection)
                
            finally:
                if connection.is_connected():
                    connection.close()
        else:
            # Run Final Layer aggregation
            print(f"🚀 Starting Final Layer (Gold) aggregation for H3 resolutions: {args.resolutions}")
            results = aggregator.run_full_aggregation(args.resolutions)
            
            # Run advanced analytics if requested
            if args.analytics:
                print(f"\n🔬 Adding advanced analytics...")
                connection = aggregator.connect_to_mysql()
                
                try:
                    for resolution in args.resolutions:
                        print(f"📊 Processing advanced analytics for H3 Resolution {resolution}...")
                        aggregator.update_advanced_analytics(connection, resolution)
                finally:
                    if connection.is_connected():
                        connection.close()
        
        if args.analytics_only:
            print(f"\n🎯 SUCCESS: Advanced analytics completed!")
        elif args.analytics:
            print(f"\n🎯 SUCCESS: Final Layer aggregation with advanced analytics completed!")
        else:
            print(f"\n🎯 SUCCESS: Final Layer aggregation completed!")
        print("=" * 70)
        
        total_hexagons = 0
        total_incidents = 0
        
        for resolution in args.resolutions:
            if resolution in results:
                data = results[resolution]
                total_hexagons += data['total_hexagons']
                total_incidents += data['total_incidents']
                
                print(f"📊 H3:{resolution} - {data['description']}")
                print(f"   Hexagons: {data['total_hexagons']:,}")
                print(f"   Incidents: {data['total_incidents']:,}")
                print(f"   Non-empty: {data['non_empty_hexagons']:,}")
                print(f"   Avg per hex: {data['avg_incidents_per_hex']:.1f}")
                print()
        
        print(f"🎯 TOTAL: {total_hexagons:,} hexagons with {total_incidents:,} incidents")
        
        if 'summary' in results:
            summary = results['summary']
            print(f"📊 Multi-resolution H3 aggregation completed at {summary['timestamp']}")
            print("🗺️ Ready for AmISafe Crime Map visualization with H3:5-7 metro area coverage")
        
    except Exception as e:
        print(f"\n❌ ERROR: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()