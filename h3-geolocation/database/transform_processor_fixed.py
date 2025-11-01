#!/usr/bin/env python3
"""
AmISafe Transform Processor - Fixed Version
Processes raw incident data into clean, deduplicated format with comprehensive exclusion reporting
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
from typing import Dict, List, Tuple
import json

# Add the parent directory to sys.path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from h3_framework import H3GeolocationFramework

class AmISafeTransformProcessorFixed:
    """
    Fixed Transform processor that aligns with actual database schema
    and provides comprehensive exclusion reporting.
    """
    
    def __init__(self, mysql_host='localhost', mysql_user='h3_pipeline', 
                 mysql_password='h3_pipeline_pass', mysql_database='theoryofconspiracies_dev'):
        
        self.mysql_config = {
            'host': mysql_host,
            'user': mysql_user,
            'password': mysql_password,
            'database': mysql_database,
            'autocommit': True
        }
        
        # Initialize H3 framework
        self.h3_framework = H3GeolocationFramework()
        
        # Exclusion tracking
        self.exclusion_stats = {
            'raw_layer': {
                'invalid_coordinates': 0,
                'missing_objectid': 0,
                'missing_cartodb_id': 0,
                'invalid_date_time': 0,
                'empty_ucr_general': 0,
                'coordinate_out_of_bounds': 0,
                'other_validation_errors': 0
            },
            'transform_layer': {
                'duplicates_removed': 0,
                'low_data_quality': 0,
                'h3_indexing_failed': 0,
                'data_type_conversion_failed': 0
            },
            'final_layer': {
                'aggregation_failed': 0,
                'insufficient_data_points': 0
            }
        }
        
        self.processed_count = 0
        self.valid_count = 0
        self.excluded_count = 0
        
        # Setup logging  
        logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
        self.logger = logging.getLogger(__name__)

    def connect_to_mysql(self):
        """Create MySQL connection."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            self.logger.info(f"Connected to MySQL Server version {connection.server_info}")
            return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise

    def validate_raw_record(self, row) -> Tuple[bool, List[str]]:
        """
        Validate a raw record and return validation status with exclusion reasons.
        Returns: (is_valid, list_of_exclusion_reasons)
        """
        exclusion_reasons = []
        
        # Check coordinates
        try:
            lat = float(row['lat']) if pd.notna(row['lat']) and row['lat'] != '' else None
            lng = float(row['lng']) if pd.notna(row['lng']) and row['lng'] != '' else None
            
            if lat is None or lng is None:
                exclusion_reasons.append('invalid_coordinates')
                self.exclusion_stats['raw_layer']['invalid_coordinates'] += 1
            elif lat < -90 or lat > 90 or lng < -180 or lng > 180:
                exclusion_reasons.append('coordinate_out_of_bounds')
                self.exclusion_stats['raw_layer']['coordinate_out_of_bounds'] += 1
        except (ValueError, TypeError):
            exclusion_reasons.append('invalid_coordinates')
            self.exclusion_stats['raw_layer']['invalid_coordinates'] += 1
        
        # Check required IDs
        if pd.isna(row['objectid']) or row['objectid'] == '':
            exclusion_reasons.append('missing_objectid')
            self.exclusion_stats['raw_layer']['missing_objectid'] += 1
            
        if pd.isna(row['cartodb_id']) or row['cartodb_id'] == '':
            exclusion_reasons.append('missing_cartodb_id') 
            self.exclusion_stats['raw_layer']['missing_cartodb_id'] += 1
        
        # Check date/time
        if pd.isna(row['dispatch_date_time']) or row['dispatch_date_time'] == '':
            exclusion_reasons.append('invalid_date_time')
            self.exclusion_stats['raw_layer']['invalid_date_time'] += 1
        
        # Check UCR code
        if pd.isna(row['ucr_general']) or row['ucr_general'] == '':
            exclusion_reasons.append('empty_ucr_general')
            self.exclusion_stats['raw_layer']['empty_ucr_general'] += 1
        
        return len(exclusion_reasons) == 0, exclusion_reasons

    def transform_raw_record(self, row) -> Dict:
        """Transform a validated raw record into clean format."""
        try:
            # Parse coordinates
            lat = float(row['lat'])
            lng = float(row['lng'])
            point_x = float(row['point_x']) if pd.notna(row['point_x']) else lng
            point_y = float(row['point_y']) if pd.notna(row['point_y']) else lat
            
            # Parse datetime
            dispatch_datetime = pd.to_datetime(row['dispatch_date_time'])
            dispatch_date = dispatch_datetime.date()
            dispatch_time = dispatch_datetime.time()
            hour = dispatch_datetime.hour
            
            # Generate H3 index
            h3_index = h3.geo_to_h3(lat, lng, 9)
            
            # Calculate data quality score (simple version)
            quality_factors = []
            if pd.notna(row['objectid']): quality_factors.append(0.2)
            if pd.notna(row['cartodb_id']): quality_factors.append(0.2)
            if pd.notna(row['dc_key']): quality_factors.append(0.1)
            if pd.notna(row['location_block']): quality_factors.append(0.2)
            if pd.notna(row['ucr_general']): quality_factors.append(0.1)
            if pd.notna(row['text_general_code']): quality_factors.append(0.2)
            data_quality_score = sum(quality_factors)
            
            # Prepare clean record matching our table schema exactly
            clean_record = {
                'objectid': int(row['objectid']) if pd.notna(row['objectid']) else None,
                'cartodb_id': int(row['cartodb_id']) if pd.notna(row['cartodb_id']) else None,
                'dc_key': str(row['dc_key']) if pd.notna(row['dc_key']) else None,
                'latitude': lat,
                'longitude': lng,
                'point_x': point_x,
                'point_y': point_y,
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
                'data_quality_score': data_quality_score,
                'completeness_score': data_quality_score,  # Same for now
                'accuracy_score': data_quality_score,      # Same for now
                'raw_record_id': int(row['id']),
                'duplicate_group_id': None,  # Will be set during deduplication
                'deduplication_method': 'objectid',
                'validation_status': 'valid',
                'validation_notes': None
            }
            
            return clean_record
            
        except Exception as e:
            self.exclusion_stats['transform_layer']['data_type_conversion_failed'] += 1
            self.logger.warning(f"Transform failed for record {row.get('id', 'unknown')}: {e}")
            return None

    def process_batch(self, connection, batch_size=10000, offset=0):
        """Process a batch of raw records."""
        
        # Fetch raw data
        query = """
        SELECT id, the_geom, cartodb_id, the_geom_webmercator, objectid, dc_dist, psa,
               dispatch_date_time, dispatch_date, dispatch_time, hour, dc_key, 
               location_block, ucr_general, text_general_code, point_x, point_y, lat, lng,
               source_file, ingestion_timestamp, processing_status
        FROM amisafe_raw_incidents 
        WHERE processing_status = 'raw'
        ORDER BY id 
        LIMIT %s OFFSET %s
        """
        
        cursor = connection.cursor(dictionary=True)
        cursor.execute(query, (batch_size, offset))
        raw_records = cursor.fetchall()
        cursor.close()
        
        if not raw_records:
            return 0, 0  # processed, inserted
        
        self.logger.info(f"Processing batch: {len(raw_records)} raw records (offset: {offset})")
        
        valid_records = []
        batch_exclusions = []
        
        # Validate and transform each record
        for raw_record in raw_records:
            self.processed_count += 1
            
            # Validate
            is_valid, exclusion_reasons = self.validate_raw_record(raw_record)
            
            if not is_valid:
                self.excluded_count += 1
                batch_exclusions.append({
                    'record_id': raw_record['id'],
                    'reasons': exclusion_reasons
                })
                continue
            
            # Transform
            clean_record = self.transform_raw_record(raw_record)
            if clean_record:
                valid_records.append(clean_record)
                self.valid_count += 1
            else:
                self.excluded_count += 1
        
        # Insert valid records
        inserted_count = 0
        if valid_records:
            inserted_count = self.insert_clean_records(connection, valid_records)
        
        self.logger.info(f"Batch complete: {len(raw_records)} processed, {len(valid_records)} valid, {inserted_count} inserted")
        
        return len(raw_records), inserted_count

    def insert_clean_records(self, connection, records: List[Dict]) -> int:
        """Insert clean records with proper parameter matching."""
        
        insert_query = """
        INSERT INTO amisafe_incidents_clean (
            objectid, cartodb_id, dc_key, latitude, longitude, point_x, point_y,
            dc_dist, psa, dispatch_datetime, dispatch_date, dispatch_time, hour,
            location_block, ucr_general, text_general_code,
            h3_index, h3_resolution, data_quality_score, completeness_score, accuracy_score,
            raw_record_id, duplicate_group_id, deduplication_method,
            validation_status, validation_notes
        ) VALUES (
            %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 
            %s, %s, %s, %s, %s, %s, %s, %s
        )
        """
        
        cursor = connection.cursor()
        inserted_count = 0
        
        try:
            # Prepare data tuples in exact order matching INSERT statement
            data_tuples = []
            for record in records:
                data_tuple = (
                    record['objectid'], record['cartodb_id'], record['dc_key'],
                    record['latitude'], record['longitude'], record['point_x'], record['point_y'],
                    record['dc_dist'], record['psa'], record['dispatch_datetime'], 
                    record['dispatch_date'], record['dispatch_time'], record['hour'],
                    record['location_block'], record['ucr_general'], record['text_general_code'],
                    record['h3_index'], record['h3_resolution'], 
                    record['data_quality_score'], record['completeness_score'], record['accuracy_score'],
                    record['raw_record_id'], record['duplicate_group_id'], record['deduplication_method'],
                    record['validation_status'], record['validation_notes']
                )
                data_tuples.append(data_tuple)
            
            # Execute batch insert
            cursor.executemany(insert_query, data_tuples)
            inserted_count = cursor.rowcount
            connection.commit()
            
        except Error as e:
            self.logger.error(f"Failed to insert records: {e}")
        finally:
            cursor.close()
        
        return inserted_count

    def generate_exclusion_report(self) -> str:
        """Generate comprehensive exclusion report."""
        
        report = f"""
=================================================================================
                        COMPREHENSIVE EXCLUSION REPORT
=================================================================================

PROCESSING SUMMARY:
- Total Records Processed: {self.processed_count:,}
- Valid Records: {self.valid_count:,}
- Excluded Records: {self.excluded_count:,}
- Success Rate: {(self.valid_count/self.processed_count*100):.2f}%

=================================================================================
                        RAW LAYER EXCLUSIONS
=================================================================================

Data Quality Issues:
- Invalid/Missing Coordinates: {self.exclusion_stats['raw_layer']['invalid_coordinates']:,}
- Coordinates Out of Bounds: {self.exclusion_stats['raw_layer']['coordinate_out_of_bounds']:,}
- Missing ObjectID: {self.exclusion_stats['raw_layer']['missing_objectid']:,}
- Missing CartoDB ID: {self.exclusion_stats['raw_layer']['missing_cartodb_id']:,}
- Invalid DateTime: {self.exclusion_stats['raw_layer']['invalid_date_time']:,}
- Empty UCR General Code: {self.exclusion_stats['raw_layer']['empty_ucr_general']:,}
- Other Validation Errors: {self.exclusion_stats['raw_layer']['other_validation_errors']:,}

Total Raw Layer Exclusions: {sum(self.exclusion_stats['raw_layer'].values()):,}

=================================================================================
                        TRANSFORM LAYER EXCLUSIONS  
=================================================================================

Processing Issues:
- Data Type Conversion Failed: {self.exclusion_stats['transform_layer']['data_type_conversion_failed']:,}
- H3 Indexing Failed: {self.exclusion_stats['transform_layer']['h3_indexing_failed']:,}
- Low Data Quality Score: {self.exclusion_stats['transform_layer']['low_data_quality']:,}
- Duplicate Records Removed: {self.exclusion_stats['transform_layer']['duplicates_removed']:,}

Total Transform Layer Exclusions: {sum(self.exclusion_stats['transform_layer'].values()):,}

=================================================================================
                        FINAL LAYER EXCLUSIONS
=================================================================================

Aggregation Issues:
- Aggregation Failed: {self.exclusion_stats['final_layer']['aggregation_failed']:,}
- Insufficient Data Points: {self.exclusion_stats['final_layer']['insufficient_data_points']:,}

Total Final Layer Exclusions: {sum(self.exclusion_stats['final_layer'].values()):,}

=================================================================================
                        EXCLUSION ANALYSIS
=================================================================================

Primary Exclusion Reasons (Top 5):
"""
        
        # Calculate top exclusion reasons
        all_exclusions = {}
        for layer in self.exclusion_stats:
            for reason, count in self.exclusion_stats[layer].items():
                if count > 0:
                    all_exclusions[f"{layer}.{reason}"] = count
        
        sorted_exclusions = sorted(all_exclusions.items(), key=lambda x: x[1], reverse=True)
        
        for i, (reason, count) in enumerate(sorted_exclusions[:5], 1):
            percentage = (count / self.processed_count * 100) if self.processed_count > 0 else 0
            report += f"{i}. {reason.replace('_', ' ').title()}: {count:,} ({percentage:.2f}%)\n"
        
        report += f"""
=================================================================================
                        RECOMMENDATIONS
=================================================================================

Data Quality Improvements:
1. Address coordinate validation issues - {self.exclusion_stats['raw_layer']['invalid_coordinates']:,} records lost
2. Improve ObjectID completeness - {self.exclusion_stats['raw_layer']['missing_objectid']:,} records lost  
3. Standardize datetime formats - {self.exclusion_stats['raw_layer']['invalid_date_time']:,} records lost
4. Validate UCR general codes - {self.exclusion_stats['raw_layer']['empty_ucr_general']:,} records lost

Pipeline Optimization:
- Current success rate: {(self.valid_count/self.processed_count*100):.2f}%
- Target success rate: >85%
- Records to recover: {self.excluded_count:,}

=================================================================================
"""
        return report

    def process_all_data(self, batch_size=10000):
        """Process all raw data with comprehensive reporting."""
        
        self.logger.info("Starting AmISafe Transform Processing with Exclusion Reporting")
        start_time = datetime.now()
        
        connection = self.connect_to_mysql()
        
        try:
            # Get total raw record count
            cursor = connection.cursor()
            cursor.execute("SELECT COUNT(*) FROM amisafe_raw_incidents WHERE processing_status = 'raw'")
            total_records = cursor.fetchone()[0]
            cursor.close()
            
            self.logger.info(f"Total raw records to process: {total_records:,}")
            
            # Process in batches
            offset = 0
            total_processed = 0
            total_inserted = 0
            
            while True:
                processed, inserted = self.process_batch(connection, batch_size, offset)
                
                if processed == 0:
                    break
                
                total_processed += processed
                total_inserted += inserted
                offset += batch_size
                
                # Progress update
                progress = (total_processed / total_records * 100) if total_records > 0 else 0
                self.logger.info(f"Progress: {progress:.1f}% ({total_processed:,}/{total_records:,}) - Inserted: {total_inserted:,}")
            
            # Generate and display exclusion report
            exclusion_report = self.generate_exclusion_report()
            print(exclusion_report)
            
            # Save report to file
            report_file = f"/workspaces/stlouisintegration.com/h3-geolocation/exclusion_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.txt"
            with open(report_file, 'w') as f:
                f.write(exclusion_report)
            
            self.logger.info(f"Exclusion report saved to: {report_file}")
            
            # Final summary
            duration = datetime.now() - start_time
            self.logger.info(f"""
Transform Processing Complete!
==============================
Duration: {duration}
Total Processed: {total_processed:,}
Successfully Inserted: {total_inserted:,}
Success Rate: {(total_inserted/total_processed*100):.2f}%
Exclusion Report: {report_file}
            """)
            
        finally:
            if connection.is_connected():
                connection.close()

def main():
    """Main function to run the fixed transform processor."""
    import argparse
    
    parser = argparse.ArgumentParser(description='AmISafe Transform Processor - Fixed Version')
    parser.add_argument('--mysql-host', default='localhost', help='MySQL host')
    parser.add_argument('--mysql-user', default='h3_pipeline', help='MySQL user') 
    parser.add_argument('--mysql-password', default='h3_pipeline_pass', help='MySQL password')
    parser.add_argument('--mysql-database', default='theoryofconspiracies_dev', help='MySQL database')
    parser.add_argument('--batch-size', type=int, default=10000, help='Batch size for processing')
    
    args = parser.parse_args()
    
    # Initialize processor
    processor = AmISafeTransformProcessorFixed(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database
    )
    
    # Process all data
    processor.process_all_data(batch_size=args.batch_size)

if __name__ == '__main__':
    main()