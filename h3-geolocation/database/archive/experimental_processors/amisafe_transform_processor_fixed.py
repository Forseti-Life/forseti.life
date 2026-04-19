#!/usr/bin/env python3
"""
AmISafe Transform Processor - Fixed Version
Processes raw incident data into clean, deduplicated, validated format (Silver layer)
Includes comprehensive exclusion reporting by reason
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
from typing import List, Dict, Tuple, Optional, Set
import argparse
import json
from collections import defaultdict, Counter

# Add the parent directory to sys.path to import our H3 framework
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

class AmISafeTransformProcessorFixed:
    """
    Fixed Transform processor with proper SQL alignment and exclusion reporting.
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
        
        # Exclusion tracking
        self.exclusion_stats = {
            'invalid_coordinates': 0,
            'missing_required_fields': 0,
            'invalid_date_format': 0,
            'duplicate_records': 0,
            'data_quality_too_low': 0,
            'processing_errors': 0,
            'invalid_h3_generation': 0,
            'total_excluded': 0
        }
        
        self.exclusion_details = defaultdict(list)
        
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
    
    def validate_record(self, row: pd.Series) -> Tuple[bool, str]:
        """Validate a single record and return (is_valid, exclusion_reason)."""
        
        # Check for valid coordinates
        try:
            lat = float(row['lat']) if pd.notna(row['lat']) and str(row['lat']).strip() != '' else None
            lng = float(row['lng']) if pd.notna(row['lng']) and str(row['lng']).strip() != '' else None
            
            if lat is None or lng is None:
                return False, "missing_coordinates"
            
            if not (-90 <= lat <= 90) or not (-180 <= lng <= 180):
                return False, "invalid_coordinate_range"
                
        except (ValueError, TypeError):
            return False, "invalid_coordinate_format"
        
        # Check required fields
        if pd.isna(row['objectid']) or str(row['objectid']).strip() == '':
            return False, "missing_objectid"
            
        if pd.isna(row['cartodb_id']) or str(row['cartodb_id']).strip() == '':
            return False, "missing_cartodb_id"
        
        # Check date format
        try:
            if pd.notna(row['dispatch_date_time']):
                pd.to_datetime(row['dispatch_date_time'])
        except (ValueError, TypeError):
            return False, "invalid_date_format"
        
        return True, "valid"
    
    def process_batch(self, raw_df: pd.DataFrame) -> Tuple[pd.DataFrame, Dict[str, int]]:
        """Process a batch of raw data with exclusion tracking."""
        
        batch_stats = {
            'total_input': len(raw_df),
            'valid_records': 0,
            'excluded_records': 0,
            'exclusions_by_reason': Counter()
        }
        
        valid_records = []
        
        for idx, row in raw_df.iterrows():
            is_valid, reason = self.validate_record(row)
            
            if is_valid:
                try:
                    # Process valid record
                    clean_record = self.create_clean_record(row)
                    valid_records.append(clean_record)
                    batch_stats['valid_records'] += 1
                    
                except Exception as e:
                    # Processing error
                    batch_stats['excluded_records'] += 1
                    batch_stats['exclusions_by_reason']['processing_error'] += 1
                    self.exclusion_details['processing_error'].append({
                        'raw_id': row.get('id', 'unknown'),
                        'error': str(e)
                    })
                    
            else:
                # Record excluded
                batch_stats['excluded_records'] += 1
                batch_stats['exclusions_by_reason'][reason] += 1
                self.exclusion_details[reason].append({
                    'raw_id': row.get('id', 'unknown'),
                    'objectid': row.get('objectid', 'N/A'),
                    'cartodb_id': row.get('cartodb_id', 'N/A'),
                    'coordinates': f"({row.get('lat', 'N/A')}, {row.get('lng', 'N/A')})"
                })
        
        # Update global stats
        for reason, count in batch_stats['exclusions_by_reason'].items():
            self.exclusion_stats[reason] += count
        
        self.exclusion_stats['total_excluded'] += batch_stats['excluded_records']
        
        clean_df = pd.DataFrame(valid_records) if valid_records else pd.DataFrame()
        return clean_df, batch_stats
    
    def create_clean_record(self, row: pd.Series) -> Dict:
        """Create a clean record that matches our table schema."""
        
        # Parse coordinates
        lat = float(row['lat'])
        lng = float(row['lng'])
        
        # Parse datetime
        dispatch_datetime = None
        dispatch_date = None
        dispatch_time = None
        hour = None
        
        try:
            if pd.notna(row['dispatch_date_time']):
                dt = pd.to_datetime(row['dispatch_date_time'])
                dispatch_datetime = dt
                dispatch_date = dt.date()
                dispatch_time = dt.time()
                hour = dt.hour
        except:
            pass
        
        # Generate H3 index
        h3_index = None
        try:
            h3_index = h3.latlng_to_cell(lat, lng, 9)
        except:
            pass
        
        # Calculate basic data quality score
        quality_score = 0.0
        if lat and lng: quality_score += 0.4
        if dispatch_datetime: quality_score += 0.3
        if pd.notna(row.get('text_general_code')): quality_score += 0.2
        if pd.notna(row.get('location_block')): quality_score += 0.1
        
        return {
            'objectid': int(row['objectid']) if pd.notna(row['objectid']) else None,
            'cartodb_id': int(row['cartodb_id']) if pd.notna(row['cartodb_id']) else None,
            'dc_key': str(row['dc_key']) if pd.notna(row['dc_key']) else None,
            'latitude': lat,
            'longitude': lng,
            'point_x': float(row['point_x']) if pd.notna(row['point_x']) else None,
            'point_y': float(row['point_y']) if pd.notna(row['point_y']) else None,
            'dc_dist': str(row['dc_dist']) if pd.notna(row['dc_dist']) else None,
            'psa': str(row['psa']) if pd.notna(row['psa']) else None,
            'dispatch_datetime': dispatch_datetime,
            'dispatch_date': dispatch_date,
            'dispatch_time': dispatch_time,
            'hour': hour,
            'location_block': str(row['location_block']) if pd.notna(row['location_block']) else None,
            'ucr_general': str(row['ucr_general']) if pd.notna(row['ucr_general']) else None,
            'text_general_code': str(row['text_general_code']) if pd.notna(row['text_general_code']) else None,
            'h3_index': h3_index,
            'h3_resolution': 9,
            'data_quality_score': quality_score,
            'completeness_score': quality_score,
            'accuracy_score': quality_score,
            'raw_record_id': int(row['id']) if pd.notna(row['id']) else None,
            'duplicate_group_id': None,
            'deduplication_method': 'objectid',
            'validation_status': 'valid',
            'validation_notes': None
        }
    
    def insert_clean_batch(self, connection: mysql.connector.MySQLConnection, df: pd.DataFrame) -> int:
        """Insert clean records with proper SQL matching our table schema."""
        
        if df.empty:
            return 0
            
        insert_query = """
        INSERT IGNORE INTO amisafe_incidents_clean (
            objectid, cartodb_id, dc_key, latitude, longitude, point_x, point_y,
            dc_dist, psa, dispatch_datetime, dispatch_date, dispatch_time, hour,
            location_block, ucr_general, text_general_code,
            h3_index, h3_resolution, data_quality_score, completeness_score, accuracy_score,
            raw_record_id, duplicate_group_id, deduplication_method,
            validation_status, validation_notes
        ) VALUES (
            %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 
            %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
        )
        """
        
        cursor = connection.cursor()
        records = []
        
        for _, row in df.iterrows():
            record = (
                row['objectid'], row['cartodb_id'], row['dc_key'],
                row['latitude'], row['longitude'], row['point_x'], row['point_y'],
                row['dc_dist'], row['psa'], row['dispatch_datetime'], row['dispatch_date'], 
                row['dispatch_time'], row['hour'], row['location_block'], 
                row['ucr_general'], row['text_general_code'],
                row['h3_index'], row['h3_resolution'], row['data_quality_score'], 
                row['completeness_score'], row['accuracy_score'],
                row['raw_record_id'], row['duplicate_group_id'], row['deduplication_method'],
                row['validation_status'], row['validation_notes']
            )
            records.append(record)
        
        try:
            cursor.executemany(insert_query, records)
            connection.commit()
            inserted = cursor.rowcount
            cursor.close()
            return inserted
            
        except Error as e:
            self.logger.error(f"Failed to insert batch: {e}")
            cursor.close()
            raise
    
    def generate_exclusion_report(self) -> str:
        """Generate comprehensive exclusion report."""
        
        report = []
        report.append("=" * 80)
        report.append("DATA EXCLUSION REPORT - TRANSFORM LAYER")
        report.append("=" * 80)
        report.append("")
        
        # Summary statistics
        total_excluded = self.exclusion_stats['total_excluded']
        report.append("EXCLUSION SUMMARY:")
        report.append("-" * 40)
        
        exclusion_reasons = {
            'missing_coordinates': 'Missing latitude/longitude coordinates',
            'invalid_coordinate_range': 'Coordinates outside valid range (-90/90, -180/180)',
            'invalid_coordinate_format': 'Invalid coordinate format (non-numeric)',
            'missing_objectid': 'Missing required objectid field',
            'missing_cartodb_id': 'Missing required cartodb_id field',
            'invalid_date_format': 'Invalid dispatch_date_time format',
            'processing_error': 'Processing/transformation errors',
            'invalid_h3_generation': 'Failed to generate H3 spatial index'
        }
        
        for reason_key, description in exclusion_reasons.items():
            count = self.exclusion_stats.get(reason_key, 0)
            if count > 0:
                percentage = (count / total_excluded * 100) if total_excluded > 0 else 0
                report.append(f"  {description}: {count:,} records ({percentage:.1f}%)")
        
        report.append(f"\nTOTAL EXCLUDED: {total_excluded:,} records")
        report.append("")
        
        # Detailed breakdown
        report.append("DETAILED EXCLUSION BREAKDOWN:")
        report.append("-" * 40)
        
        for reason, records in self.exclusion_details.items():
            if records:
                report.append(f"\n{exclusion_reasons.get(reason, reason).upper()}:")
                report.append(f"  Count: {len(records):,} records")
                
                # Show first 10 examples
                report.append("  Examples (first 10):")
                for i, record in enumerate(records[:10]):
                    raw_id = record.get('raw_id', 'N/A')
                    objectid = record.get('objectid', 'N/A')
                    cartodb_id = record.get('cartodb_id', 'N/A')
                    coords = record.get('coordinates', 'N/A')
                    error = record.get('error', '')
                    
                    if error:
                        report.append(f"    {i+1}. Raw ID: {raw_id}, Error: {error}")
                    else:
                        report.append(f"    {i+1}. Raw ID: {raw_id}, ObjectID: {objectid}, CartoDB: {cartodb_id}, Coords: {coords}")
                
                if len(records) > 10:
                    report.append(f"    ... and {len(records) - 10:,} more records")
        
        report.append("")
        report.append("=" * 80)
        
        return "\n".join(report)
    
    def process_all_raw_data(self, batch_size: int = 10000) -> Dict[str, int]:
        """Process all raw data with comprehensive reporting."""
        
        connection = self.connect_to_mysql()
        
        try:
            # Get total count
            cursor = connection.cursor()
            cursor.execute("SELECT COUNT(*) FROM amisafe_raw_incidents")
            total_raw = cursor.fetchone()[0]
            cursor.close()
            
            self.logger.info(f"Starting Transform processing for {total_raw:,} raw incidents")
            
            stats = {
                'total_raw': total_raw,
                'total_processed': 0,
                'total_clean': 0,
                'batches_processed': 0,
                'failed_batches': 0
            }
            
            offset = 0
            batch_num = 0
            
            while offset < total_raw:
                batch_num += 1
                
                # Fetch batch
                query = """
                SELECT id, the_geom, cartodb_id, the_geom_webmercator, objectid, dc_dist, psa,
                       dispatch_date_time, dispatch_date, dispatch_time, hour, dc_key,
                       location_block, ucr_general, text_general_code, point_x, point_y, lat, lng,
                       source_file, ingestion_timestamp, processing_status
                FROM amisafe_raw_incidents 
                ORDER BY id 
                LIMIT %s OFFSET %s
                """
                
                raw_df = pd.read_sql(query, connection, params=(batch_size, offset))
                
                if raw_df.empty:
                    break
                
                self.logger.info(f"Processing batch {batch_num} (offset: {offset:,}) - {len(raw_df):,} records")
                
                try:
                    # Process batch
                    clean_df, batch_stats = self.process_batch(raw_df)
                    
                    # Insert clean records
                    if not clean_df.empty:
                        inserted = self.insert_clean_batch(connection, clean_df)
                        self.logger.info(f"Inserted {inserted:,} clean records")
                        stats['total_clean'] += inserted
                    
                    stats['total_processed'] += len(raw_df)
                    stats['batches_processed'] += 1
                    
                    self.logger.info(f"Batch {batch_num} complete: {batch_stats['valid_records']:,} valid, {batch_stats['excluded_records']:,} excluded")
                    
                except Exception as e:
                    self.logger.error(f"Failed to process batch {batch_num}: {e}")
                    stats['failed_batches'] += 1
                
                offset += batch_size
            
            return stats
            
        finally:
            if connection.is_connected():
                connection.close()
                self.logger.info("MySQL connection closed")

def main():
    """Main function to run the fixed transform processor."""
    parser = argparse.ArgumentParser(description='AmISafe Transform Processor - Fixed Version')
    parser.add_argument('--mysql-host', default='localhost', help='MySQL host')
    parser.add_argument('--mysql-user', default='root', help='MySQL user')
    parser.add_argument('--mysql-password', default='', help='MySQL password')
    parser.add_argument('--mysql-database', default='theoryofconspiracies_dev', help='MySQL database')
    parser.add_argument('--batch-size', type=int, default=10000, help='Batch size for processing')
    parser.add_argument('--report-file', default='exclusion_report.txt', help='Output file for exclusion report')
    
    args = parser.parse_args()
    
    # Initialize processor
    processor = AmISafeTransformProcessorFixed(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database
    )
    
    # Process raw data
    print(f"\n=== AmISafe Transform Processing (Silver Layer) - FIXED ===")
    print(f"Processing raw incidents into clean, deduplicated format...")
    
    stats = processor.process_all_raw_data(batch_size=args.batch_size)
    
    # Generate exclusion report
    exclusion_report = processor.generate_exclusion_report()
    
    # Save report to file
    with open(args.report_file, 'w') as f:
        f.write(exclusion_report)
    
    print(f"\n🎉 Transform Processing Complete!")
    print(f"=" * 60)
    print(f"Total raw incidents: {stats['total_raw']:,}")
    print(f"Successfully processed: {stats['total_processed']:,}")
    print(f"Clean incidents created: {stats['total_clean']:,}")
    print(f"Total excluded: {processor.exclusion_stats['total_excluded']:,}")
    print(f"Failed batches: {stats['failed_batches']}")
    
    if stats['total_processed'] > 0:
        success_rate = (stats['total_clean'] / stats['total_processed'] * 100)
        exclusion_rate = (processor.exclusion_stats['total_excluded'] / stats['total_processed'] * 100)
        print(f"Success rate: {success_rate:.1f}%")
        print(f"Exclusion rate: {exclusion_rate:.1f}%")
    
    print(f"Exclusion report saved to: {args.report_file}")
    print(f"=" * 60)
    
    # Print summary of exclusions
    print(f"\nEXCLUSION SUMMARY:")
    print(f"- Missing coordinates: {processor.exclusion_stats.get('missing_coordinates', 0):,}")
    print(f"- Invalid coordinate range: {processor.exclusion_stats.get('invalid_coordinate_range', 0):,}")
    print(f"- Invalid coordinate format: {processor.exclusion_stats.get('invalid_coordinate_format', 0):,}")
    print(f"- Missing required fields: {processor.exclusion_stats.get('missing_objectid', 0) + processor.exclusion_stats.get('missing_cartodb_id', 0):,}")
    print(f"- Processing errors: {processor.exclusion_stats.get('processing_error', 0):,}")

if __name__ == '__main__':
    main()