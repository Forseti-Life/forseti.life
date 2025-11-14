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
                 mysql_database: str = 'stlouisintegration_dev'):
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
        
    # Removed process_single_h3_cell() - now using Silver layer H3 indices directly
    

    
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
    parser.add_argument('--mysql-database', default='stlouisintegration_dev', help='MySQL database')
    parser.add_argument('--resolutions', nargs='+', type=int, default=[5, 6, 7, 8, 9, 10, 11, 12, 13], 
                        help='H3 resolutions to process (default: 5 6 7 8 9 10 11 12 13)')
    
    args = parser.parse_args()
    
    # Initialize Final Layer aggregator
    aggregator = AmISafeFinalLayerAggregator(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database
    )
    
    try:
        # Run Final Layer aggregation
        print(f"🚀 Starting Final Layer (Gold) aggregation for H3 resolutions: {args.resolutions}")
        results = aggregator.run_full_aggregation(args.resolutions)
        
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