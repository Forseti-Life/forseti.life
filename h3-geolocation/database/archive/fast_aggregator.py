#!/usr/bin/env python3
"""
Fast H3 Aggregator - Optimized for 3.4M+ records
Uses bulk SQL operations instead of row-by-row processing
"""

import mysql.connector
import logging
from datetime import datetime
import argparse

class FastH3Aggregator:
    """Ultra-fast H3 aggregator using pure SQL bulk operations."""
    
    def __init__(self, 
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = 'drupal_secure_password',
                 mysql_database: str = 'amisafe_database'):
        """Initialize the fast aggregator."""
        self.mysql_config = {
            'host': mysql_host,
            'user': mysql_user,
            'password': mysql_password,
            'database': mysql_database,
            'autocommit': True
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
        except Exception as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def fast_aggregate_resolution(self, connection, resolution: int) -> dict:
        """Fast bulk aggregation for a single H3 resolution."""
        self.logger.info(f"🚀 Fast aggregating H3 resolution {resolution}...")
        
        cursor = connection.cursor()
        
        # Clear existing data for this resolution
        cursor.execute("DELETE FROM amisafe_h3_aggregated WHERE h3_resolution = %s", (resolution,))
        deleted_count = cursor.rowcount
        self.logger.info(f"   Cleared {deleted_count} existing H3:{resolution} records")
        
        # Get the H3 column name
        h3_column = f"h3_res_{resolution}"
        
        # Build optimized bulk insert query
        bulk_query = f"""
        INSERT INTO amisafe_h3_aggregated (
            h3_index, 
            h3_resolution, 
            incident_count, 
            unique_incident_types,
            earliest_incident, 
            latest_incident, 
            incidents_last_30_days, 
            incidents_last_year,
            center_latitude, 
            center_longitude, 
            incident_type_counts, 
            district_counts,
            total_valid_records, 
            last_aggregation
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
            AND is_valid = 1
        GROUP BY {h3_column}
        HAVING COUNT(*) > 0
        """
        
        # Execute bulk aggregation
        start_time = datetime.now()
        cursor.execute(bulk_query, (resolution,))
        rows_inserted = cursor.rowcount
        end_time = datetime.now()
        duration = (end_time - start_time).total_seconds()
        
        # Get verification stats
        cursor.execute("""
        SELECT 
            COUNT(*) as total_hexagons,
            SUM(incident_count) as total_incidents,
            AVG(incident_count) as avg_incidents_per_hex,
            MIN(incident_count) as min_incidents,
            MAX(incident_count) as max_incidents
        FROM amisafe_h3_aggregated 
        WHERE h3_resolution = %s
        """, (resolution,))
        
        stats = cursor.fetchone()
        cursor.close()
        
        result = {
            'resolution': resolution,
            'hexagons_created': rows_inserted,
            'total_incidents': stats[1] if stats and stats[1] else 0,
            'avg_incidents_per_hex': float(stats[2]) if stats and stats[2] else 0.0,
            'min_incidents': stats[3] if stats and stats[3] else 0,
            'max_incidents': stats[4] if stats and stats[4] else 0,
            'processing_time_seconds': duration
        }
        
        self.logger.info(f"✅ H3:{resolution} completed: {rows_inserted:,} hexagons, {result['total_incidents']:,} incidents in {duration:.2f}s")
        return result
    
    def run_fast_aggregation(self, resolutions: list) -> dict:
        """Run fast aggregation for multiple resolutions."""
        self.logger.info(f"🚀 Starting FAST aggregation for H3 resolutions: {resolutions}")
        
        connection = self.connect_to_mysql()
        results = {}
        total_start = datetime.now()
        
        try:
            for resolution in resolutions:
                self.logger.info(f"\n📊 Processing H3:{resolution}...")
                result = self.fast_aggregate_resolution(connection, resolution)
                results[resolution] = result
            
            total_duration = (datetime.now() - total_start).total_seconds()
            
            # Summary
            total_hexagons = sum(r['hexagons_created'] for r in results.values())
            total_incidents = sum(r['total_incidents'] for r in results.values())
            
            self.logger.info(f"\n🎯 FAST AGGREGATION COMPLETE!")
            self.logger.info(f"   Total hexagons: {total_hexagons:,}")
            self.logger.info(f"   Total incidents: {total_incidents:,}")
            self.logger.info(f"   Total time: {total_duration:.2f} seconds")
            
            results['summary'] = {
                'total_hexagons': total_hexagons,
                'total_incidents': total_incidents,
                'total_duration': total_duration,
                'timestamp': datetime.now().isoformat()
            }
            
            return results
            
        finally:
            if connection.is_connected():
                connection.close()

def main():
    """Main function for fast H3 aggregation."""
    parser = argparse.ArgumentParser(description='Fast H3 Aggregator - Optimized for millions of records')
    parser.add_argument('--mysql-host', default='127.0.0.1', help='MySQL host')
    parser.add_argument('--mysql-user', default='drupal_user', help='MySQL user')
    parser.add_argument('--mysql-password', default='drupal_secure_password', help='MySQL password')
    parser.add_argument('--mysql-database', default='amisafe_database', help='MySQL database')
    parser.add_argument('--resolutions', nargs='+', type=int, required=True, 
                        help='H3 resolutions to process (e.g., --resolutions 5 6 7)')
    
    args = parser.parse_args()
    
    # Initialize fast aggregator
    aggregator = FastH3Aggregator(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database
    )
    
    try:
        print(f"🚀 Starting FAST H3 aggregation for resolutions: {args.resolutions}")
        results = aggregator.run_fast_aggregation(args.resolutions)
        
        print(f"\n✅ SUCCESS: Fast aggregation completed!")
        print("=" * 60)
        
        for resolution in args.resolutions:
            if resolution in results:
                data = results[resolution]
                print(f"📊 H3:{resolution}")
                print(f"   Hexagons: {data['hexagons_created']:,}")
                print(f"   Incidents: {data['total_incidents']:,}")
                print(f"   Avg/hex: {data['avg_incidents_per_hex']:.1f}")
                print(f"   Time: {data['processing_time_seconds']:.2f}s")
                print()
        
        if 'summary' in results:
            summary = results['summary']
            print(f"🎯 TOTAL: {summary['total_hexagons']:,} hexagons, {summary['total_incidents']:,} incidents")
            print(f"⏱️  Total processing time: {summary['total_duration']:.2f} seconds")
        
    except Exception as e:
        print(f"\n❌ ERROR: {e}")
        return 1

if __name__ == "__main__":
    exit(main())