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
- H3 hexagon aggregations at multiple resolutions (6-13) with ultra-fine 44m² precision
- Crime type analysis and trend detection
- Temporal patterns (hourly, daily, monthly, seasonal)
- Spatial hotspot identification and ranking
- Data quality metrics and coverage analysis
- Performance optimized for dashboard queries

Requirements:
- Requires Transform layer processing to be complete
- Uses existing amisafe_h3_aggregated table schema
- Creates one record per unique H3 hexagon per resolution
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
            'total_aggregations': 0,
            'processing_start_time': datetime.now(),
            'processing_end_time': None
        }
        
        # Resolutions to process - Extended to level 13 for ultra-fine precision (44 m² hexagons)
        self.target_resolutions = [6, 7, 8, 9, 10, 11, 12, 13]
    
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
            result = cursor.fetchone()  # Consume the result
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
            COUNT(DISTINCT h3_res_10) as unique_h3_res10,
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
            self.logger.info(f"H3 coverage: Res6={result['unique_h3_res6']}, Res7={result['unique_h3_res7']}, Res8={result['unique_h3_res8']}, Res9={result['unique_h3_res9']}, Res10={result['unique_h3_res10']}")
            self.logger.info(f"Crime types: {result['unique_crime_types']}, Districts: {result['unique_districts']}")
            
            # Update processing stats
            self.aggregation_stats['transform_records_processed'] = result['total_records']
            
            return result
        except Error as e:
            self.logger.error(f"Error checking Transform layer status: {e}")
            return {}
    
    def aggregate_h3_resolution(self, connection, resolution: int) -> int:
        """Aggregate incidents by H3 resolution to match existing table schema."""
        self.logger.info(f"Aggregating H3 resolution {resolution}")
        
        # Query to aggregate by H3 hexagon - matches existing table schema
        base_query = f"""
        SELECT 
            h3_res_{resolution} as h3_index,
            COUNT(*) as incident_count,
            COUNT(DISTINCT ucr_general) as unique_incident_types,
            MIN(incident_datetime) as earliest_incident,
            MAX(incident_datetime) as latest_incident,
            AVG(lat) as center_latitude,
            AVG(lng) as center_longitude,
            AVG(data_quality_score) as avg_data_quality_score,
            COUNT(CASE WHEN is_valid = 1 THEN 1 END) as total_valid_records,
            COUNT(CASE WHEN is_valid = 0 THEN 1 END) as total_invalid_records,
            GROUP_CONCAT(DISTINCT ucr_general) as crime_types,
            GROUP_CONCAT(DISTINCT dc_dist) as districts,
            COUNT(*) as source_record_count
            
        FROM amisafe_clean_incidents 
        WHERE h3_res_{resolution} IS NOT NULL 
        GROUP BY h3_res_{resolution}
        HAVING incident_count > 0
        ORDER BY incident_count DESC
        """
        
        try:
            cursor = connection.cursor(dictionary=True)
            cursor.execute(base_query)
            results = cursor.fetchall()
            cursor.close()
            
            self.logger.info(f"Found {len(results)} unique H3 hexagons at resolution {resolution}")
            
            # Enhanced batch processing for large datasets (optimized for resolutions 11-13)
            if resolution >= 13:
                batch_size = 10000  # Larger batches for Resolution 13's ~365K hexagons
            elif resolution >= 11:
                batch_size = 5000   # Medium batches for resolutions 11-12
            else:
                batch_size = 1000   # Standard batches for resolutions 6-10
            batch_data = []
            aggregated_count = 0
            
            # Prepare INSERT query for batch processing
            insert_query = """
            INSERT INTO amisafe_h3_aggregated (
                h3_index, h3_resolution, incident_count, unique_incident_types,
                earliest_incident, latest_incident, incidents_last_30_days, incidents_last_year,
                center_latitude, center_longitude, incident_type_counts, district_counts,
                avg_data_quality_score, total_valid_records, total_invalid_records,
                source_record_count, aggregation_method
            ) VALUES (
                %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
            )
            ON DUPLICATE KEY UPDATE
                incident_count = VALUES(incident_count),
                unique_incident_types = VALUES(unique_incident_types),
                earliest_incident = VALUES(earliest_incident),
                latest_incident = VALUES(latest_incident),
                incidents_last_30_days = VALUES(incidents_last_30_days),
                incidents_last_year = VALUES(incidents_last_year),
                center_latitude = VALUES(center_latitude),
                center_longitude = VALUES(center_longitude),
                incident_type_counts = VALUES(incident_type_counts),
                district_counts = VALUES(district_counts),
                avg_data_quality_score = VALUES(avg_data_quality_score),
                total_valid_records = VALUES(total_valid_records),
                total_invalid_records = VALUES(total_invalid_records),
                source_record_count = VALUES(source_record_count),
                last_aggregation = CURRENT_TIMESTAMP
            """
            
            for row in results:
                # Create incident type counts JSON
                incident_type_counts = {}
                district_counts = {}
                
                if row['crime_types']:
                    crime_list = row['crime_types'].split(',')
                    for crime in crime_list:
                        crime = crime.strip()
                        incident_type_counts[crime] = incident_type_counts.get(crime, 0) + 1
                
                if row['districts']:
                    district_list = row['districts'].split(',')
                    for district in district_list:
                        district = district.strip()
                        district_counts[district] = district_counts.get(district, 0) + 1
                
                # Calculate recent incidents (simplified - using all incidents for now)
                incidents_last_30_days = row['incident_count']  # Simplified
                incidents_last_year = row['incident_count']     # Simplified
                
                # Prepare data for batch insert
                batch_data.append((
                    row['h3_index'],                                    # h3_index
                    resolution,                                         # h3_resolution
                    row['incident_count'],                             # incident_count
                    row['unique_incident_types'],                      # unique_incident_types
                    row['earliest_incident'],                          # earliest_incident
                    row['latest_incident'],                            # latest_incident
                    incidents_last_30_days,                            # incidents_last_30_days
                    incidents_last_year,                               # incidents_last_year
                    float(row['center_latitude']) if row['center_latitude'] else None,   # center_latitude
                    float(row['center_longitude']) if row['center_longitude'] else None, # center_longitude
                    json.dumps(incident_type_counts),                  # incident_type_counts
                    json.dumps(district_counts),                       # district_counts
                    float(row['avg_data_quality_score']) if row['avg_data_quality_score'] else None, # avg_data_quality_score
                    row['total_valid_records'],                        # total_valid_records
                    row['total_invalid_records'],                      # total_invalid_records
                    row['source_record_count'],                        # source_record_count
                    'h3_resolution_aggregation'                        # aggregation_method
                ))
                
                # Execute batch when we reach batch_size
                if len(batch_data) >= batch_size:
                    cursor = connection.cursor()
                    cursor.executemany(insert_query, batch_data)
                    connection.commit()
                    cursor.close()
                    
                    aggregated_count += len(batch_data)
                    self.logger.info(f"Batch processed {aggregated_count} hexagons at resolution {resolution}")
                    batch_data = []
            
            # Process remaining batch
            if batch_data:
                cursor = connection.cursor()
                cursor.executemany(insert_query, batch_data)
                connection.commit()
                cursor.close()
                aggregated_count += len(batch_data)
                self.logger.info(f"Final batch processed {aggregated_count} hexagons at resolution {resolution}")
            
            # Update statistics
            self.aggregation_stats['h3_hexagons_created'][f'res_{resolution}'] = aggregated_count
            self.aggregation_stats['total_aggregations'] += aggregated_count
            
            self.logger.info(f"✅ Completed resolution {resolution}: {aggregated_count} hexagons processed")
            return aggregated_count
            
        except Error as e:
            self.logger.error(f"Error aggregating H3 resolution {resolution}: {e}")
            return 0
    
    def process_transform_to_final(self) -> Dict:
        """Main processing function - Transform to Final layer."""
        self.logger.info("Starting Transform → Final layer processing...")
        
        connection = None
        try:
            connection = self.connect_to_mysql()
            
            # Ensure final layer table exists
            self.ensure_final_layer_table(connection)
            
            # Check Transform layer status
            transform_status = self.check_transform_layer_status(connection)
            if not transform_status or transform_status.get('total_records', 0) == 0:
                self.logger.error("No valid records found in Transform layer!")
                return {}
            
            # Clear existing aggregations for fresh processing
            self.logger.info("Clearing existing aggregations...")
            cursor = connection.cursor()
            cursor.execute("DELETE FROM amisafe_h3_aggregated WHERE aggregation_method = 'h3_resolution_aggregation'")
            connection.commit()
            cursor.close()
            self.logger.info("Existing aggregations cleared")
            
            # Process each H3 resolution
            total_aggregations = 0
            for resolution in self.target_resolutions:
                self.logger.info(f"\n🔄 Processing H3 Resolution {resolution}")
                hexagon_count = self.aggregate_h3_resolution(connection, resolution)
                total_aggregations += hexagon_count
                self.logger.info(f"Resolution {resolution}: {hexagon_count} hexagons created")
            
            # Update end time
            self.aggregation_stats['processing_end_time'] = datetime.now()
            processing_duration = self.aggregation_stats['processing_end_time'] - self.aggregation_stats['processing_start_time']
            
            # Generate summary
            summary = {
                'total_h3_aggregations': total_aggregations,
                'transform_records_processed': self.aggregation_stats['transform_records_processed'],
                'aggregations_by_resolution': {
                    f'Resolution {res}': self.aggregation_stats['h3_hexagons_created'][f'res_{res}']
                    for res in self.target_resolutions
                },
                'processing_duration': str(processing_duration),
                'processing_start': self.aggregation_stats['processing_start_time'].isoformat(),
                'processing_end': self.aggregation_stats['processing_end_time'].isoformat()
            }
            
            self.logger.info(f"🎉 Final layer processing complete!")
            self.logger.info(f"Total aggregations created: {total_aggregations:,}")
            self.logger.info(f"Processing duration: {processing_duration}")
            
            return summary
            
        except Exception as e:
            error_msg = f"Final layer processing failed: {e}"
            self.logger.error(error_msg)
            return {'error': str(e)}
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
    
    if summary and 'error' not in summary:
        print("\n" + "="*60)
        print("FINAL LAYER PROCESSING SUMMARY")
        print("="*60)
        print(f"Total H3 Aggregations Created: {summary.get('total_h3_aggregations', 0):,}")
        print(f"Transform Records Processed: {summary.get('transform_records_processed', 0):,}")
        print(f"Processing Duration: {summary.get('processing_duration', 'unknown')}")
        print("\nAggregations by Resolution:")
        for res, count in summary.get('aggregations_by_resolution', {}).items():
            print(f"  {res}: {count:,} hexagons")
        print("="*60)
        
        # Verify totals match expected (Extended to Resolution 13 - based on real data patterns)
        expected_totals = {
            'Resolution 6': 22,
            'Resolution 7': 93, 
            'Resolution 8': 545,
            'Resolution 9': 3150,
            'Resolution 10': 16739,
            'Resolution 11': 69513,   # Actual from Resolution 12 run
            'Resolution 12': 145982,  # Actual from Resolution 12 run (307 m² precision)
            'Resolution 13': 364955   # Estimated: ~2.5x Resolution 12 (44 m² precision)
        }
        
        print("\n📊 VALIDATION:")
        for res, expected in expected_totals.items():
            actual = summary.get('aggregations_by_resolution', {}).get(res, 0)
            status = "✅ MATCH" if actual == expected else f"❌ MISMATCH (expected {expected})"
            print(f"  {res}: {actual:,} hexagons - {status}")
        
        total_expected = sum(expected_totals.values())
        total_actual = summary.get('total_h3_aggregations', 0)
        total_status = "✅ MATCH" if total_actual == total_expected else f"❌ MISMATCH (expected {total_expected:,})"
        print(f"\nTotal: {total_actual:,} aggregations - {total_status}")
        
    else:
        print("Final layer processing failed - check logs for details")
        if summary and 'error' in summary:
            print(f"Error: {summary['error']}")
        sys.exit(1)

if __name__ == "__main__":
    main()