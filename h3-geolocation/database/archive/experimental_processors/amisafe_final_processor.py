#!/usr/bin/env python3
"""
AmISafe Final Layer Aggregation Processor (Transform → Gold Layer)

This processor creates the Final layer of the data warehouse by aggregating
Transform layer data into H3 hexagon analytics optimized for dashboard queries.

Architecture:
- Reads from: amisafe_clean_incidents (Transform/Silver layer)
- Writes to: amisafe_h3_aggregated (Final/Gold layer)
- Purpose: H3 hexagon-based spatial analytics and time-series aggregations

Processing Features:
- H3 hexagon aggregations at multiple resolutions (6-10)
- Crime type analysis and trend detection
- Temporal patterns (hourly, daily, monthly, seasonal)
- Spatial hotspot identification and ranking
- Data quality metrics and coverage analysis
- Performance optimized for dashboard queries

Requirements:
- Requires Transform layer processing to be complete
- Creates materialized aggregations for fast dashboard performance
- Implements incremental processing for real-time updates
"""

import mysql.connector
from mysql.connector import Error
import pandas as pd
import numpy as np
import h3
import json
import logging
from datetime import datetime, date, timedelta
from typing import Dict, List, Tuple, Optional
import argparse
import sys
import os
from collections import defaultdict

# Add parent directory to path for imports
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from h3_framework import H3GeolocationFramework

class AmISafeFinalProcessor:
    """
    Final layer processor implementing Gold layer aggregation architecture.
    Processes Transform layer data into optimized H3 hexagon analytics.
    """
    
    def __init__(self, 
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = 'drupal_secure_password',
                 mysql_database: str = 'theoryofconspiracies_dev'):
        """Initialize the final layer processor."""
        self.mysql_config = {
            'host': mysql_host,
            'user': mysql_user,
            'password': mysql_password,
            'database': mysql_database,
            'autocommit': True
        }
        
        # Initialize H3 framework
        self.h3_framework = H3GeolocationFramework()
        
        # Setup logging
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s'
        )
        self.logger = logging.getLogger(__name__)
        
        # Processing statistics
        self.aggregation_stats = {
            'transform_records_processed': 0,
            'h3_hexagons_created': {
                'res_6': 0,
                'res_7': 0,
                'res_8': 0,
                'res_9': 0,
                'res_10': 0
            },
            'crime_types_analyzed': 0,
            'temporal_patterns_generated': 0,
            'processing_start_time': datetime.now(),
            'processing_end_time': None
        }
        
        # Crime type mappings for analysis
        self.crime_type_categories = {
            '100': 'Violent Crime',
            '200': 'Violent Crime', 
            '300': 'Violent Crime',
            '400': 'Violent Crime',
            '500': 'Property Crime',
            '600': 'Property Crime',
            '700': 'Property Crime',
            '800': 'Public Order',
            '900': 'Public Order',
            '1100': 'Violent Crime',
            '1200': 'Property Crime',
            '1300': 'Drug Related',
            '1400': 'Public Order',
            '1500': 'Traffic',
            '1600': 'Other',
            '1700': 'Other',
            '1800': 'Other',
            '1900': 'Other'
        }
    
    def connect_to_mysql(self) -> mysql.connector.MySQLConnection:
        """Create MySQL connection."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            if connection.is_connected():
                self.logger.info(f"Connected to MySQL Server version {connection.get_server_info()}")
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def ensure_final_layer_table(self, connection):
        """Ensure the Final layer aggregation table exists with correct schema."""
        # The table already exists with the correct schema from setup scripts
        # Just verify it exists
        try:
            cursor = connection.cursor()
            cursor.execute("SELECT COUNT(*) FROM amisafe_h3_aggregated LIMIT 1")
            cursor.close()
            self.logger.info("✅ amisafe_h3_aggregated table verified")
        except Error as e:
            self.logger.error(f"Error accessing amisafe_h3_aggregated table: {e}")
            raise
    
    def check_transform_layer_status(self, connection) -> Dict:
        """Check the status of the Transform layer."""
        status_query = """
        SELECT 
            COUNT(*) as total_records,
            MIN(incident_datetime) as earliest_record,
            MAX(incident_datetime) as latest_record,
            COUNT(DISTINCT h3_res_9) as unique_h3_res9,
            COUNT(DISTINCT h3_res_8) as unique_h3_res8,
            COUNT(DISTINCT h3_res_7) as unique_h3_res7,
            COUNT(DISTINCT h3_res_6) as unique_h3_res6,
            COUNT(DISTINCT ucr_general) as unique_crime_types,
            COUNT(DISTINCT dc_dist) as unique_districts
        FROM amisafe_clean_incidents
        WHERE is_valid = TRUE
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            cursor.execute(status_query)
            result = cursor.fetchone()
            cursor.close()
            
            self.logger.info(f"Transform layer status: {result['total_records']:,} valid records")
            self.logger.info(f"H3 coverage: Res6={result['unique_h3_res6']}, Res7={result['unique_h3_res7']}, Res8={result['unique_h3_res8']}, Res9={result['unique_h3_res9']}")
            self.logger.info(f"Crime types: {result['unique_crime_types']}, Districts: {result['unique_districts']}")
            
            return result
        except Error as e:
            self.logger.error(f"Error checking Transform layer status: {e}")
            return {}
    
    def aggregate_h3_resolution(self, connection, resolution: int, period: str = 'all_time') -> int:
        """Aggregate incidents by H3 resolution and time period."""
        self.logger.info(f"Aggregating H3 resolution {resolution} for period '{period}'")
        
        # Base aggregation query
        base_query = f"""
        SELECT 
            h3_res_{resolution} as h3_index,
            COUNT(*) as total_incidents,
            
            -- Crime type counts
            SUM(CASE WHEN ucr_general IN ('100', '200', '300', '400', '1100') THEN 1 ELSE 0 END) as violent_crime_count,
            SUM(CASE WHEN ucr_general IN ('500', '600', '700', '1200') THEN 1 ELSE 0 END) as property_crime_count,
            SUM(CASE WHEN ucr_general = '1300' THEN 1 ELSE 0 END) as drug_crime_count,
            SUM(CASE WHEN ucr_general IN ('800', '900', '1400') THEN 1 ELSE 0 END) as public_order_count,
            SUM(CASE WHEN ucr_general = '1500' THEN 1 ELSE 0 END) as traffic_crime_count,
            SUM(CASE WHEN ucr_general IN ('1600', '1700', '1800', '1900') THEN 1 ELSE 0 END) as other_crime_count,
            
            -- Temporal patterns
            AVG(HOUR(incident_datetime)) as avg_hour,
            AVG(DAYOFWEEK(incident_datetime)) as avg_day_of_week,
            SUM(CASE WHEN DAYOFWEEK(incident_datetime) IN (1, 7) THEN 1 ELSE 0 END) as weekend_incidents,
            SUM(CASE WHEN HOUR(incident_datetime) BETWEEN 22 AND 6 THEN 1 ELSE 0 END) as night_incidents,
            
            -- Spatial data
            AVG(lat) as center_lat,
            AVG(lng) as center_lng,
            AVG(data_quality_score) as avg_quality_score,
            
            -- Processing metadata
            MIN(incident_datetime) as period_start,
            MAX(incident_datetime) as period_end,
            GROUP_CONCAT(DISTINCT ucr_general) as crime_types,
            COUNT(DISTINCT incident_id) as unique_incidents
            
        FROM amisafe_clean_incidents 
        WHERE h3_res_{resolution} IS NOT NULL 
          AND is_valid = TRUE
        GROUP BY h3_res_{resolution}
        HAVING total_incidents > 0
        ORDER BY total_incidents DESC
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            cursor.execute(base_query)
            results = cursor.fetchall()
            if results:
                self.logger.info(f"DEBUG: First result keys: {list(results[0].keys())}")
            cursor.close()
            
            aggregated_count = 0
            
            for row in results:
                # Calculate percentages (ensure float conversion)
                total = int(row['total_incidents'])
                violent_pct = float(row['violent_crime_count'] / total * 100) if total > 0 else 0.0
                property_pct = float(row['property_crime_count'] / total * 100) if total > 0 else 0.0
                drug_pct = float(row['drug_crime_count'] / total * 100) if total > 0 else 0.0
                public_order_pct = float(row['public_order_count'] / total * 100) if total > 0 else 0.0
                traffic_pct = float(row['traffic_crime_count'] / total * 100) if total > 0 else 0.0
                other_pct = float(row['other_crime_count'] / total * 100) if total > 0 else 0.0
                
                weekend_pct = float(row['weekend_incidents'] / total * 100) if total > 0 else 0.0
                night_pct = float(row['night_incidents'] / total * 100) if total > 0 else 0.0
                
                # Calculate risk score based on incident density and crime severity
                risk_score = float(min(100.0, (total / 10.0) + (float(violent_pct) * 0.3) + (float(property_pct) * 0.2)))
                
                # Determine peak hour and day
                peak_hour = int(row['avg_hour']) if row['avg_hour'] else 12
                peak_day = int(row['avg_day_of_week']) if row['avg_day_of_week'] else 1
                
                # Create top crime types JSON
                top_crime_types = {
                    'violent': int(row['violent_crime_count']),
                    'property': int(row['property_crime_count']),
                    'drug': int(row['drug_crime_count']),
                    'public_order': int(row['public_order_count']),
                    'traffic': int(row['traffic_crime_count']),
                    'other': int(row['other_crime_count'])
                }
                
                # Insert aggregated record
                insert_query = """
                INSERT INTO amisafe_h3_aggregated (
                    h3_index, h3_resolution, center_lat, center_lng,
                    aggregation_period, period_start, period_end,
                    total_incidents, incident_density,
                    violent_crime_count, property_crime_count, drug_crime_count,
                    public_order_count, traffic_crime_count, other_crime_count,
                    violent_crime_pct, property_crime_pct, drug_crime_pct,
                    public_order_pct, traffic_crime_pct, other_crime_pct,
                    top_crime_types, peak_hour, peak_day_of_week,
                    weekend_incident_pct, night_incident_pct,
                    risk_score, data_quality_score,
                    processing_batch_id, source_transform_records
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s, %s, %s,
                    %s, %s, %s, %s, %s, %s,
                    %s, %s, %s, %s, %s, %s,
                    %s, %s, %s, %s, %s, %s, %s, %s, %s
                )
                ON DUPLICATE KEY UPDATE
                    total_incidents = VALUES(total_incidents),
                    violent_crime_count = VALUES(violent_crime_count),
                    property_crime_count = VALUES(property_crime_count),
                    risk_score = VALUES(risk_score),
                    updated_at = CURRENT_TIMESTAMP
                """
                
                batch_id = f"final_processing_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
                source_records = {
                    'unique_incidents': int(row['unique_incidents']),
                    'crime_types': row['crime_types'].split(',') if row['crime_types'] else []
                }
                
                cursor = connection.cursor()
                cursor.execute(insert_query, (
                    row['h3_index'], resolution, float(row['center_lat']), float(row['center_lng']),
                    period, row['period_start'], row['period_end'],
                    total, float(total / 100.0),  # incident_density simplified
                    row['violent_crime_count'], row['property_crime_count'], row['drug_crime_count'],
                    row['public_order_count'], row['traffic_crime_count'], row['other_crime_count'],
                    violent_pct, property_pct, drug_pct,
                    public_order_pct, traffic_pct, other_pct,
                    json.dumps(top_crime_types), peak_hour, peak_day,
                    weekend_pct, night_pct,
                    risk_score, float(row['avg_quality_score']) if row['avg_quality_score'] else 0.0,
                    batch_id, json.dumps(source_records)
                ))
                cursor.close()
                aggregated_count += 1
            
            connection.commit()
            self.aggregation_stats['h3_hexagons_created'][f'res_{resolution}'] = aggregated_count
            self.logger.info(f"✅ Created {aggregated_count} H3 aggregations for resolution {resolution}")
            
            return aggregated_count
            
        except Error as e:
            self.logger.error(f"Error aggregating H3 resolution {resolution}: {e}")
            return 0
    
    def calculate_hotspot_rankings(self, connection):
        """Calculate hotspot rankings across all H3 aggregations."""
        self.logger.info("Calculating hotspot rankings...")
        
        # Update hotspot rankings based on risk scores
        ranking_query = """
        UPDATE amisafe_h3_aggregated a1
        JOIN (
            SELECT h3_index, h3_resolution, aggregation_period,
                   ROW_NUMBER() OVER (PARTITION BY h3_resolution, aggregation_period 
                                     ORDER BY risk_score DESC, total_incidents DESC) as ranking
            FROM amisafe_h3_aggregated
            WHERE total_incidents > 0
        ) rankings ON a1.h3_index = rankings.h3_index 
                   AND a1.h3_resolution = rankings.h3_resolution
                   AND a1.aggregation_period = rankings.aggregation_period
        SET a1.hotspot_rank = rankings.ranking
        """
        
        try:
            cursor = connection.cursor()
            cursor.execute(ranking_query)
            connection.commit()
            cursor.close()
            self.logger.info("✅ Hotspot rankings calculated")
        except Error as e:
            self.logger.error(f"Error calculating hotspot rankings: {e}")
    
    def generate_processing_summary(self, connection) -> Dict:
        """Generate final processing summary."""
        self.aggregation_stats['processing_end_time'] = datetime.now()
        processing_duration = self.aggregation_stats['processing_end_time'] - self.aggregation_stats['processing_start_time']
        
        # Get final aggregation counts
        summary_query = """
        SELECT 
            h3_resolution,
            aggregation_period,
            COUNT(*) as aggregation_count,
            SUM(total_incidents) as total_incidents,
            AVG(risk_score) as avg_risk_score,
            MAX(total_incidents) as max_incidents_per_hex
        FROM amisafe_h3_aggregated
        GROUP BY h3_resolution, aggregation_period
        ORDER BY h3_resolution, aggregation_period
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            cursor.execute(summary_query)
            results = cursor.fetchall()
            cursor.close()
            
            summary = {
                'processing_duration': str(processing_duration),
                'total_h3_aggregations': sum(self.aggregation_stats['h3_hexagons_created'].values()),
                'aggregations_by_resolution': self.aggregation_stats['h3_hexagons_created'],
                'detailed_results': results,
                'processing_completed_at': self.aggregation_stats['processing_end_time'].isoformat()
            }
            
            return summary
            
        except Error as e:
            self.logger.error(f"Error generating processing summary: {e}")
            return {}
    
    def process_transform_to_final(self) -> Dict:
        """Main processing function - Transform to Final layer."""
        self.logger.info("🔄 AmISafe Final Processing (Transform → Gold Layer)")
        self.logger.info("Creating H3 hexagon aggregations optimized for dashboard queries...")
        
        connection = None
        try:
            # Connect to database
            connection = self.connect_to_mysql()
            
            # Create Final layer table
            self.create_final_layer_table(connection)
            
            # Check Transform layer status
            transform_status = self.check_transform_layer_status(connection)
            if not transform_status or transform_status['total_records'] == 0:
                self.logger.error("No valid records found in Transform layer")
                return {}
            
            self.aggregation_stats['transform_records_processed'] = transform_status['total_records']
            
            # Process each H3 resolution
            for resolution in range(6, 11):  # H3 resolutions 6-10
                self.aggregate_h3_resolution(connection, resolution, 'all_time')
            
            # Calculate hotspot rankings
            self.calculate_hotspot_rankings(connection)
            
            # Generate summary
            summary = self.generate_processing_summary(connection)
            
            self.logger.info("✅ Final layer processing complete!")
            self.logger.info(f"Created {summary.get('total_h3_aggregations', 0)} H3 aggregations")
            self.logger.info(f"Processing duration: {summary.get('processing_duration', 'unknown')}")
            
            return summary
            
        except Exception as e:
            self.logger.error(f"Error in Final layer processing: {e}")
            return {}
        finally:
            if connection and connection.is_connected():
                connection.close()
                self.logger.info("MySQL connection closed")

def main():
    """Main execution function."""
    parser = argparse.ArgumentParser(description='AmISafe Final Layer Aggregation Processor')
    parser.add_argument('--host', default='127.0.0.1', help='MySQL host')
    parser.add_argument('--user', default='drupal_user', help='MySQL user')
    parser.add_argument('--password', default='drupal_secure_password', help='MySQL password')
    parser.add_argument('--database', default='theoryofconspiracies_dev', help='MySQL database')
    
    args = parser.parse_args()
    
    # Initialize processor
    processor = AmISafeFinalProcessor(
        mysql_host=args.host,
        mysql_user=args.user,
        mysql_password=args.password,
        mysql_database=args.database
    )
    
    # Process Transform to Final layer
    summary = processor.process_transform_to_final()
    
    if summary:
        print("\n" + "="*60)
        print("FINAL LAYER PROCESSING SUMMARY")
        print("="*60)
        print(f"Total H3 Aggregations Created: {summary.get('total_h3_aggregations', 0):,}")
        print(f"Processing Duration: {summary.get('processing_duration', 'unknown')}")
        print("\nAggregations by Resolution:")
        for res, count in summary.get('aggregations_by_resolution', {}).items():
            print(f"  {res}: {count:,} hexagons")
        print("="*60)
    else:
        print("Final layer processing failed - check logs for details")
        sys.exit(1)

if __name__ == "__main__":
    main()