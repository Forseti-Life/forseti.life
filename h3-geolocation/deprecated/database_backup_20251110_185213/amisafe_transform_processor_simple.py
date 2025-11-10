#!/usr/bin/env python3
"""
AmISafe Transform Processor - Simplified Version
Processes raw incident data into clean, deduplicated format (Silver layer)
Matches the actual database schema we created
"""

import os
import sys
import pandas as pd
import numpy as np
from datetime import datetime
import mysql.connector
from mysql.connector import Error
import h3
import logging
from typing import Dict, Optional
import argparse

# Add the parent directory to sys.path to import our H3 framework
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from h3_framework import H3GeolocationFramework

class AmISafeTransformProcessorSimple:
    """
    Simplified Transform processor for converting raw incident data into business-ready format.
    Matches the actual database schema created by setup_h3_pipeline.sql
    """
    
    def __init__(self, 
                 mysql_host: str = 'localhost',
                 mysql_user: str = 'root',
                 mysql_password: str = '',
                 mysql_database: str = 'theoryofconspiracies_dev'):
        """Initialize the transform processor."""
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
            self.logger.info(f"Connected to MySQL Server version {connection.server_info}")
            return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def clean_and_validate_record(self, row) -> Optional[Dict]:
        """Clean and validate a single raw record."""
        try:
            # Extract and clean coordinates
            lat = float(row['lat']) if row['lat'] and row['lat'] != '' else None
            lng = float(row['lng']) if row['lng'] and row['lng'] != '' else None
            point_x = float(row['point_x']) if row['point_x'] and row['point_x'] != '' else None
            point_y = float(row['point_y']) if row['point_y'] and row['point_y'] != '' else None
            
            # Validate coordinates
            if lat is None or lng is None or lat < -90 or lat > 90 or lng < -180 or lng > 180:
                return None
                
            # Generate H3 index
            h3_index = h3.geo_to_h3(lat, lng, 9)
            
            # Clean datetime
            dispatch_datetime = None
            dispatch_date = None
            dispatch_time = None
            if row['dispatch_date_time']:
                try:
                    dt = pd.to_datetime(row['dispatch_date_time'])
                    dispatch_datetime = dt
                    dispatch_date = dt.date()
                    dispatch_time = dt.time()
                except:
                    pass
            
            # Calculate data quality scores
            completeness_score = sum([
                1 if row['objectid'] else 0,
                1 if row['cartodb_id'] else 0,
                1 if row['dc_key'] else 0,
                1 if lat is not None else 0,
                1 if lng is not None else 0,
                1 if row['location_block'] else 0,
                1 if row['text_general_code'] else 0,
                1 if dispatch_datetime else 0
            ]) / 8.0
            
            accuracy_score = 1.0 if (-90 <= lat <= 90 and -180 <= lng <= 180) else 0.5
            data_quality_score = (completeness_score + accuracy_score) / 2.0
            
            # Determine validation status
            validation_status = 'valid'
            validation_notes = []
            
            if completeness_score < 0.7:
                validation_status = 'warning'
                validation_notes.append('Low completeness score')
            
            if accuracy_score < 1.0:
                validation_status = 'warning'
                validation_notes.append('Coordinate accuracy issues')
            
            return {
                'objectid': int(row['objectid']) if row['objectid'] else None,
                'cartodb_id': int(row['cartodb_id']) if row['cartodb_id'] else None,
                'dc_key': row['dc_key'],
                'latitude': lat,
                'longitude': lng,
                'point_x': point_x,
                'point_y': point_y,
                'dc_dist': row['dc_dist'],
                'psa': row['psa'],
                'dispatch_datetime': dispatch_datetime,
                'dispatch_date': dispatch_date,
                'dispatch_time': dispatch_time,
                'hour': int(row['hour']) if row['hour'] else None,
                'location_block': row['location_block'],
                'ucr_general': row['ucr_general'],
                'text_general_code': row['text_general_code'],
                'h3_index': h3_index,
                'h3_resolution': 9,
                'data_quality_score': round(data_quality_score, 2),
                'completeness_score': round(completeness_score, 2),
                'accuracy_score': round(accuracy_score, 2),
                'raw_record_id': row['id'],
                'duplicate_group_id': None,  # Will be set during deduplication
                'deduplication_method': None,
                'validation_status': validation_status,
                'validation_notes': '; '.join(validation_notes) if validation_notes else None
            }
            
        except Exception as e:
            self.logger.warning(f"Failed to clean record {row.get('id', 'unknown')}: {e}")
            return None
    
    def find_duplicates(self, records):
        """Find and mark duplicate records."""
        # Group by potential duplicate keys
        duplicate_groups = {}
        
        for i, record in enumerate(records):
            # Try different deduplication methods
            keys_to_try = []
            
            if record['objectid']:
                keys_to_try.append(('objectid', str(record['objectid'])))
            if record['cartodb_id']:
                keys_to_try.append(('cartodb_id', str(record['cartodb_id'])))
            if record['dc_key']:
                keys_to_try.append(('dc_key', record['dc_key']))
            
            # Find if this record matches any existing group
            group_found = False
            for method, key in keys_to_try:
                if key in duplicate_groups:
                    duplicate_groups[key].append(i)
                    record['duplicate_group_id'] = key
                    record['deduplication_method'] = method
                    group_found = True
                    break
            
            if not group_found and keys_to_try:
                # Start a new group with the first available key
                method, key = keys_to_try[0]
                duplicate_groups[key] = [i]
                record['duplicate_group_id'] = key
                record['deduplication_method'] = method
        
        # Count duplicates
        duplicate_count = sum(len(group) - 1 for group in duplicate_groups.values() if len(group) > 1)
        
        return records, duplicate_count
    
    def process_batch(self, connection, batch_size: int, offset: int) -> Dict[str, int]:
        """Process a batch of raw records."""
        # Fetch raw records
        query = """
        SELECT id, the_geom, cartodb_id, the_geom_webmercator, objectid, dc_dist, psa,
               dispatch_date_time, dispatch_date, dispatch_time, hour, dc_key, location_block,
               ucr_general, text_general_code, point_x, point_y, lat, lng
        FROM amisafe_raw_incidents 
        ORDER BY id
        LIMIT %s OFFSET %s
        """
        
        cursor = connection.cursor(dictionary=True)
        cursor.execute(query, (batch_size, offset))
        raw_records = cursor.fetchall()
        cursor.close()
        
        if not raw_records:
            return {'processed': 0, 'clean': 0, 'duplicates': 0, 'errors': 0}
        
        self.logger.info(f"Processing batch of {len(raw_records)} records (offset: {offset})")
        
        # Clean and validate records
        clean_records = []
        error_count = 0
        
        for raw_record in raw_records:
            clean_record = self.clean_and_validate_record(raw_record)
            if clean_record:
                clean_records.append(clean_record)
            else:
                error_count += 1
        
        if not clean_records:
            return {'processed': len(raw_records), 'clean': 0, 'duplicates': 0, 'errors': error_count}
        
        # Find duplicates
        clean_records, duplicate_count = self.find_duplicates(clean_records)
        
        # Insert clean records
        insert_query = """
        INSERT INTO amisafe_incidents_clean (
            objectid, cartodb_id, dc_key, latitude, longitude, point_x, point_y,
            dc_dist, psa, dispatch_datetime, dispatch_date, dispatch_time, hour,
            location_block, ucr_general, text_general_code,
            h3_index, h3_resolution, data_quality_score, completeness_score, accuracy_score,
            raw_record_id, duplicate_group_id, deduplication_method,
            validation_status, validation_notes
        ) VALUES (
            %(objectid)s, %(cartodb_id)s, %(dc_key)s, %(latitude)s, %(longitude)s, 
            %(point_x)s, %(point_y)s, %(dc_dist)s, %(psa)s, %(dispatch_datetime)s, 
            %(dispatch_date)s, %(dispatch_time)s, %(hour)s, %(location_block)s, 
            %(ucr_general)s, %(text_general_code)s, %(h3_index)s, %(h3_resolution)s,
            %(data_quality_score)s, %(completeness_score)s, %(accuracy_score)s,
            %(raw_record_id)s, %(duplicate_group_id)s, %(deduplication_method)s,
            %(validation_status)s, %(validation_notes)s
        )
        """
        
        cursor = connection.cursor()
        try:
            cursor.executemany(insert_query, clean_records)
            self.logger.info(f"Inserted {len(clean_records)} clean records")
        except Error as e:
            self.logger.error(f"Failed to insert batch: {e}")
            error_count += len(clean_records)
            clean_records = []
        finally:
            cursor.close()
        
        return {
            'processed': len(raw_records),
            'clean': len(clean_records),
            'duplicates': duplicate_count,
            'errors': error_count
        }
    
    def process_all_records(self, batch_size: int = 5000) -> Dict[str, int]:
        """Process all raw records through the transform pipeline."""
        connection = self.connect_to_mysql()
        
        try:
            # Get total count
            cursor = connection.cursor()
            cursor.execute("SELECT COUNT(*) FROM amisafe_raw_incidents")
            total_count = cursor.fetchone()[0]
            cursor.close()
            
            self.logger.info(f"Starting transform processing for {total_count:,} raw incidents")
            
            # Process in batches
            stats = {'total_raw': total_count, 'total_processed': 0, 'total_clean': 0, 
                    'total_duplicates': 0, 'total_errors': 0, 'failed_batches': 0}
            
            offset = 0
            batch_num = 1
            
            while offset < total_count:
                self.logger.info(f"Processing batch {batch_num} (offset: {offset:,})")
                
                try:
                    batch_stats = self.process_batch(connection, batch_size, offset)
                    
                    stats['total_processed'] += batch_stats['processed']
                    stats['total_clean'] += batch_stats['clean']
                    stats['total_duplicates'] += batch_stats['duplicates']
                    stats['total_errors'] += batch_stats['errors']
                    
                    self.logger.info(f"Batch {batch_num}: {batch_stats['clean']:,} clean, "
                                   f"{batch_stats['duplicates']:,} duplicates, "
                                   f"{batch_stats['errors']:,} errors")
                    
                except Exception as e:
                    self.logger.error(f"Failed to process batch {batch_num}: {e}")
                    stats['failed_batches'] += 1
                
                offset += batch_size
                batch_num += 1
            
            return stats
            
        finally:
            if connection.is_connected():
                connection.close()
                self.logger.info("MySQL connection closed")

def main():
    """Main function to run the transform processor."""
    parser = argparse.ArgumentParser(description='AmISafe Transform Processor - Simplified')
    parser.add_argument('--mysql-host', default='localhost', help='MySQL host')
    parser.add_argument('--mysql-user', default='root', help='MySQL user')
    parser.add_argument('--mysql-password', default='', help='MySQL password')
    parser.add_argument('--mysql-database', default='theoryofconspiracies_dev', help='MySQL database')
    parser.add_argument('--batch-size', type=int, default=5000, help='Batch size for processing')
    
    args = parser.parse_args()
    
    # Initialize processor
    processor = AmISafeTransformProcessorSimple(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database
    )
    
    # Process raw data to clean format
    print(f"\n=== AmISafe Transform Processing (Silver Layer) ===")
    print(f"Processing raw incidents into clean, deduplicated format...")
    
    stats = processor.process_all_records(batch_size=args.batch_size)
    
    print(f"\n🎉 Transform Processing Complete!")
    print(f"=" * 50)
    print(f"Total raw incidents: {stats['total_raw']:,}")
    print(f"Successfully processed: {stats['total_processed']:,}")
    print(f"Clean incidents created: {stats['total_clean']:,}")
    print(f"Duplicates identified: {stats['total_duplicates']:,}")
    print(f"Processing errors: {stats['total_errors']:,}")
    print(f"Failed batches: {stats['failed_batches']}")
    
    # Calculate success rate safely
    if stats['total_processed'] > 0:
        success_rate = (stats['total_clean'] / stats['total_processed'] * 100)
        print(f"Success rate: {success_rate:.1f}%")
    else:
        print(f"Success rate: N/A (no records processed)")
    
    print(f"=" * 50)

if __name__ == '__main__':
    main()