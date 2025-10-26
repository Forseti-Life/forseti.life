#!/usr/bin/env python3
"""
AmISafe Data Aggregator
Creates aggregated views and analytics from the raw incident data
"""

import os
import sys
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import mysql.connector
from mysql.connector import Error
import h3
import logging
from typing import List, Dict, Tuple, Optional
import argparse

# Add the parent directory to sys.path to import our H3 framework
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from h3_framework import H3GeolocationFramework

class AmISafeDataAggregator:
    """
    Creates aggregated analytics from raw incident data for the AmISafe system.
    """
    
    def __init__(self, 
                 mysql_host: str = 'localhost',
                 mysql_user: str = 'root',
                 mysql_password: str = '',
                 mysql_database: str = 'amisafe'):
        """Initialize the data aggregator."""
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
    
    def connect_to_mysql(self) -> mysql.connector.MySQLConnection:
        """Create MySQL connection."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            if connection.is_connected():
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def create_daily_aggregations(self, connection, resolution: int = 9):
        """Create daily incident aggregations by H3 cell."""
        self.logger.info(f"Creating daily aggregations for H3 resolution {resolution}")
        
        cursor = connection.cursor()
        
        # Clear existing aggregations for this resolution
        cursor.execute(f"DELETE FROM transformed.incidents_aggregated WHERE h3_resolution = {resolution}")
        
        # Create daily aggregations
        aggregation_query = f"""
        INSERT INTO transformed.incidents_aggregated (
            h3_cell, h3_resolution, date_bucket, hour_bucket,
            total_incidents, theft_incidents, robbery_incidents, assault_incidents, other_incidents,
            center_lat, center_lng, dc_dist
        )
        SELECT 
            h3_res_{resolution} as h3_cell,
            {resolution} as h3_resolution,
            dispatch_date as date_bucket,
            hour as hour_bucket,
            COUNT(*) as total_incidents,
            SUM(CASE WHEN text_general_code LIKE '%Theft%' OR text_general_code LIKE '%theft%' THEN 1 ELSE 0 END) as theft_incidents,
            SUM(CASE WHEN text_general_code LIKE '%Robbery%' OR text_general_code LIKE '%robbery%' THEN 1 ELSE 0 END) as robbery_incidents,
            SUM(CASE WHEN text_general_code LIKE '%Assault%' OR text_general_code LIKE '%assault%' THEN 1 ELSE 0 END) as assault_incidents,
            SUM(CASE WHEN text_general_code NOT LIKE '%Theft%' AND text_general_code NOT LIKE '%theft%' 
                          AND text_general_code NOT LIKE '%Robbery%' AND text_general_code NOT LIKE '%robbery%'
                          AND text_general_code NOT LIKE '%Assault%' AND text_general_code NOT LIKE '%assault%' THEN 1 ELSE 0 END) as other_incidents,
            AVG(lat) as center_lat,
            AVG(lng) as center_lng,
            dc_dist
        FROM raw.incidents 
        WHERE h3_res_{resolution} IS NOT NULL 
            AND dispatch_date IS NOT NULL
            AND lat IS NOT NULL 
            AND lng IS NOT NULL
        GROUP BY h3_res_{resolution}, dispatch_date, hour, dc_dist
        ORDER BY dispatch_date DESC, h3_res_{resolution}
        """
        
        cursor.execute(aggregation_query)
        rows_affected = cursor.rowcount
        cursor.close()
        
        self.logger.info(f"Created {rows_affected} daily aggregation records for resolution {resolution}")
        return rows_affected
    
    def calculate_safety_scores(self, connection, days_lookback: int = 30):
        """Calculate safety scores for the final metrics table."""
        self.logger.info(f"Calculating safety scores for last {days_lookback} days")
        
        cursor = connection.cursor()
        
        # Clear existing safety metrics
        cursor.execute("DELETE FROM final.safety_metrics")
        
        # Calculate safety scores based on incident density and trends
        cutoff_date = datetime.now() - timedelta(days=days_lookback)
        
        safety_query = f"""
        INSERT INTO final.safety_metrics (
            h3_cell, h3_resolution, safety_score, risk_level,
            date_range_start, date_range_end, total_incidents_30d,
            center_lat, center_lng
        )
        SELECT 
            h3_cell,
            h3_resolution,
            CASE 
                WHEN total_incidents <= 1 THEN 95.0
                WHEN total_incidents <= 5 THEN 85.0
                WHEN total_incidents <= 15 THEN 70.0
                WHEN total_incidents <= 30 THEN 50.0
                WHEN total_incidents <= 60 THEN 30.0
                ELSE 15.0
            END as safety_score,
            CASE 
                WHEN total_incidents <= 1 THEN 'LOW'
                WHEN total_incidents <= 15 THEN 'MODERATE' 
                WHEN total_incidents <= 60 THEN 'HIGH'
                ELSE 'VERY_HIGH'
            END as risk_level,
            %s as date_range_start,
            CURDATE() as date_range_end,
            total_incidents as total_incidents_30d,
            center_lat,
            center_lng
        FROM (
            SELECT 
                h3_cell,
                h3_resolution,
                SUM(total_incidents) as total_incidents,
                AVG(center_lat) as center_lat,
                AVG(center_lng) as center_lng
            FROM transformed.incidents_aggregated 
            WHERE date_bucket >= %s
            GROUP BY h3_cell, h3_resolution
        ) aggregated_data
        """
        
        cursor.execute(safety_query, (cutoff_date.date(), cutoff_date.date()))
        rows_affected = cursor.rowcount
        cursor.close()
        
        self.logger.info(f"Calculated safety scores for {rows_affected} H3 cells")
        return rows_affected
    
    def generate_analytics_summary(self, connection) -> Dict:
        """Generate summary analytics."""
        cursor = connection.cursor()
        
        # Get overall statistics
        stats = {}
        
        # Raw data stats
        cursor.execute("SELECT COUNT(*) FROM raw.incidents")
        stats['total_raw_incidents'] = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM raw.incidents WHERE h3_res_9 IS NOT NULL")
        stats['incidents_with_h3'] = cursor.fetchone()[0]
        
        # Date range
        cursor.execute("SELECT MIN(dispatch_date), MAX(dispatch_date) FROM raw.incidents WHERE dispatch_date IS NOT NULL")
        date_range = cursor.fetchone()
        stats['date_range_start'] = date_range[0]
        stats['date_range_end'] = date_range[1]
        
        # Aggregated data stats  
        cursor.execute("SELECT COUNT(*) FROM transformed.incidents_aggregated")
        stats['aggregated_records'] = cursor.fetchone()[0]
        
        # Safety metrics
        cursor.execute("SELECT COUNT(*) FROM final.safety_metrics")
        stats['safety_metrics_records'] = cursor.fetchone()[0]
        
        # Risk level distribution
        cursor.execute("""
        SELECT risk_level, COUNT(*) 
        FROM final.safety_metrics 
        GROUP BY risk_level
        """)
        risk_distribution = dict(cursor.fetchall())
        stats['risk_distribution'] = risk_distribution
        
        # Top incident types
        cursor.execute("""
        SELECT text_general_code, COUNT(*) as count
        FROM raw.incidents 
        WHERE text_general_code IS NOT NULL
        GROUP BY text_general_code
        ORDER BY count DESC
        LIMIT 10
        """)
        stats['top_incident_types'] = dict(cursor.fetchall())
        
        cursor.close()
        return stats
    
    def run_full_aggregation(self, resolution: int = 9, days_lookback: int = 30) -> Dict:
        """Run the complete aggregation pipeline."""
        self.logger.info("Starting full aggregation pipeline")
        
        connection = self.connect_to_mysql()
        results = {}
        
        try:
            # Step 1: Create daily aggregations
            results['daily_aggregations'] = self.create_daily_aggregations(connection, resolution)
            
            # Step 2: Calculate safety scores
            results['safety_scores'] = self.calculate_safety_scores(connection, days_lookback)
            
            # Step 3: Generate analytics summary
            results['analytics'] = self.generate_analytics_summary(connection)
            
            self.logger.info("Full aggregation pipeline completed successfully")
            return results
            
        finally:
            if connection.is_connected():
                connection.close()


def main():
    """Main function to run the data aggregator."""
    parser = argparse.ArgumentParser(description='AmISafe Data Aggregator')
    parser.add_argument('--mysql-host', default='localhost', help='MySQL host')
    parser.add_argument('--mysql-user', default='root', help='MySQL user')
    parser.add_argument('--mysql-password', default='', help='MySQL password')
    parser.add_argument('--mysql-database', default='amisafe', help='MySQL database')
    parser.add_argument('--resolution', type=int, default=9, help='H3 resolution for aggregation')
    parser.add_argument('--days-lookback', type=int, default=30, help='Days to look back for safety scores')
    
    args = parser.parse_args()
    
    # Initialize aggregator
    aggregator = AmISafeDataAggregator(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database
    )
    
    # Run aggregation
    print(f"Starting aggregation with H3 resolution {args.resolution}, looking back {args.days_lookback} days")
    results = aggregator.run_full_aggregation(args.resolution, args.days_lookback)
    
    print("\nAggregation Results:")
    print("=" * 50)
    print(f"Daily aggregations created: {results['daily_aggregations']:,}")
    print(f"Safety scores calculated: {results['safety_scores']:,}")
    
    analytics = results['analytics']
    print(f"\nData Overview:")
    print(f"Total raw incidents: {analytics['total_raw_incidents']:,}")
    print(f"Incidents with H3 data: {analytics['incidents_with_h3']:,}")
    print(f"Date range: {analytics['date_range_start']} to {analytics['date_range_end']}")
    print(f"Aggregated records: {analytics['aggregated_records']:,}")
    print(f"Safety metrics records: {analytics['safety_metrics_records']:,}")
    
    print(f"\nRisk Level Distribution:")
    for risk_level, count in analytics['risk_distribution'].items():
        print(f"  {risk_level}: {count:,}")
    
    print(f"\nTop Incident Types:")
    for incident_type, count in list(analytics['top_incident_types'].items())[:5]:
        print(f"  {incident_type}: {count:,}")


if __name__ == "__main__":
    main()