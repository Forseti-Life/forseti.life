#!/usr/bin/env python3
"""
AmISafe Data Processor
Processes incident CSV files and loads them into MySQL with H3 geospatial indexing
"""

import os
import sys
import glob
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import mysql.connector
from mysql.connector import Error
import h3
import logging
from typing import List, Dict, Tuple, Optional
import argparse
from pathlib import Path

# Add the parent directory to sys.path to import our H3 framework
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from h3_framework import H3GeolocationFramework

class AmISafeDataProcessor:
    """
    Processes incident data files and loads them into MySQL with H3 geospatial indexing.
    """
    
    def __init__(self, 
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = 'drupal_secure_password',
                 mysql_database: str = 'stlouisintegration_dev'):
        """
        Initialize the data processor.
        
        Args:
            mysql_host: MySQL server host
            mysql_user: MySQL username
            mysql_password: MySQL password
            mysql_database: MySQL database name
        """
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
            format='%(asctime)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler('amisafe_processing.log'),
                logging.StreamHandler()
            ]
        )
        self.logger = logging.getLogger(__name__)
        
        # H3 resolutions for different analysis levels
        self.h3_resolutions = {
            'neighborhood': 7,   # ~5km hexagons
            'district': 8,       # ~1.2km hexagons  
            'block': 9,          # ~300m hexagons
            'building': 10       # ~100m hexagons
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
    
    def log_processing_start(self, connection, file_name: str) -> int:
        """Log the start of file processing."""
        # Simple logging without database table dependency
        self.logger.info(f"Starting processing: {file_name}")
        return 1  # Return dummy ID
    
    def log_processing_end(self, connection, log_id: int, records_processed: int, 
                          records_with_coordinates: int, records_with_h3: int, 
                          status: str = 'COMPLETED', error_message: str = None):
        """Log the end of file processing."""
        # Simple logging without database table dependency
        if status == 'COMPLETED':
            self.logger.info(f"Completed processing: {records_processed} records, {records_with_h3} with H3")
        else:
            self.logger.error(f"Processing failed: {error_message}")
    
    def clean_and_validate_data(self, df: pd.DataFrame) -> pd.DataFrame:
        """Clean and validate the incident data."""
        self.logger.info(f"Cleaning data: {len(df)} records")
        
        # Remove rows without coordinates
        df = df.dropna(subset=['lat', 'lng'])
        
        # Validate coordinate ranges (Philadelphia area)
        df = df[
            (df['lat'].between(39.5, 40.5)) &  # Reasonable latitude range for Philadelphia
            (df['lng'].between(-75.5, -74.5))  # Reasonable longitude range for Philadelphia
        ]
        
        # Deduplicate records using objectid as primary key
        initial_count = len(df)
        if 'objectid' in df.columns:
            df = df.drop_duplicates(subset=['objectid'], keep='first')
            dedup_count = len(df)
            self.logger.info(f"Deduplication by objectid: {initial_count} → {dedup_count} ({initial_count - dedup_count} duplicates removed)")
        else:
            # Fallback deduplication using location + time + crime type
            df = df.drop_duplicates(subset=['lat', 'lng', 'dispatch_date_time', 'ucr_general'], keep='first')
            dedup_count = len(df)
            self.logger.info(f"Deduplication by location+time+crime: {initial_count} → {dedup_count} ({initial_count - dedup_count} duplicates removed)")
        
        # Clean text fields
        text_columns = ['location_block', 'text_general_code', 'dc_key']
        for col in text_columns:
            if col in df.columns:
                df[col] = df[col].astype(str).str.strip()
                df[col] = df[col].replace('nan', np.nan)
        
        # Parse datetime fields
        if 'dispatch_date_time' in df.columns:
            df['dispatch_date_time'] = pd.to_datetime(df['dispatch_date_time'], errors='coerce')
            
        if 'dispatch_date' in df.columns:
            df['dispatch_date'] = pd.to_datetime(df['dispatch_date'], errors='coerce').dt.date
            
        # Extract hour from time if needed
        if 'hour' not in df.columns and 'dispatch_time' in df.columns:
            df['hour'] = pd.to_datetime(df['dispatch_time'], format='%H:%M:%S', errors='coerce').dt.hour
        
        self.logger.info(f"After cleaning: {len(df)} records")
        return df
    
    def add_h3_indexes(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        DEPRECATED: H3 indexing moved to Transform layer (Silver).
        Raw layer preserves original data without transformations.
        """
        self.logger.warning("H3 indexing should be done in Transform layer, not Raw layer")
        return df
    
    def process_csv_file(self, file_path: str, connection) -> Tuple[int, int, int]:
        """
        Process a single CSV file and load it into the database.
        
        Returns:
            Tuple of (total_records, records_with_coordinates, records_with_h3)
        """
        file_name = os.path.basename(file_path)
        self.logger.info(f"Processing file: {file_name}")
        
        # Start processing log
        log_id = self.log_processing_start(connection, file_name)
        
        try:
            # Read CSV file
            df = pd.read_csv(file_path, low_memory=False)
            total_records = len(df)
            self.logger.info(f"Loaded {total_records} records from {file_name}")
            
            # Clean and validate data
            df = self.clean_and_validate_data(df)
            records_with_coordinates = len(df)
            
            # RAW LAYER: Minimal processing - preserve original data integrity
            # NO H3 indexing, NO derived fields, NO transformations
            # Just add source tracking for data lineage
            df['source_file'] = file_name
            
            # Count records that have coordinates (for reporting only)
            records_with_h3 = 0  # H3 processing happens in Transform layer
            if 'lat' in df.columns and 'lng' in df.columns:
                records_with_h3 = df[['lat', 'lng']].notna().all(axis=1).sum()
            
            # Insert into database in batches
            self.insert_batch_to_mysql(df, connection)
            
            # Log successful completion
            self.log_processing_end(
                connection, log_id, total_records, 
                records_with_coordinates, records_with_h3
            )
            
            self.logger.info(f"Successfully processed {file_name}: {total_records} total, {records_with_coordinates} with coordinates, {records_with_h3} with H3")
            return total_records, records_with_coordinates, records_with_h3
            
        except Exception as e:
            error_msg = str(e)
            self.logger.error(f"Error processing {file_name}: {error_msg}")
            self.log_processing_end(
                connection, log_id, 0, 0, 0, 'FAILED', error_msg
            )
            raise
    
    def insert_batch_to_mysql(self, df: pd.DataFrame, connection, batch_size: int = 1000):
        """Insert dataframe to MySQL in batches."""
        cursor = connection.cursor()
        
        # RAW LAYER: Insert ALL CSV fields exactly as-is (Bronze layer)
        # Following data warehouse best practices - preserve immutable source data
        insert_query = """
        INSERT INTO amisafe_raw_incidents (
            source_file, the_geom, cartodb_id, the_geom_webmercator, objectid, 
            dc_dist, psa, dispatch_date_time, dispatch_date, dispatch_time, 
            hour, dc_key, location_block, ucr_general, text_general_code, 
            point_x, point_y, lat, lng
        ) VALUES (
            %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
        )
        """
        
        # Map ALL original CSV columns to preserve complete source data
        columns = [
            'source_file', 'the_geom', 'cartodb_id', 'the_geom_webmercator', 'objectid',
            'dc_dist', 'psa', 'dispatch_date_time', 'dispatch_date', 'dispatch_time',
            'hour', 'dc_key', 'location_block', 'ucr_general', 'text_general_code',
            'point_x', 'point_y', 'lat', 'lng'
        ]
        
        # RAW LAYER: Fill missing columns with None, keep all data as strings
        for col in columns:
            if col not in df.columns:
                df[col] = None
            else:
                # Convert all fields to string to preserve original format
                if col != 'source_file':  # source_file already handled
                    df[col] = df[col].astype(str).replace('nan', None)
        
        # Convert dataframe to list of tuples
        data = df[columns].replace({np.nan: None}).values.tolist()
        
        # Insert in batches
        total_inserted = 0
        for i in range(0, len(data), batch_size):
            batch = data[i:i + batch_size]
            cursor.executemany(insert_query, batch)
            total_inserted += len(batch)
            
            if i % (batch_size * 10) == 0:  # Log every 10 batches
                self.logger.info(f"Inserted {total_inserted}/{len(data)} records")
        
        cursor.close()
        self.logger.info(f"Successfully inserted {total_inserted} records")
    
    def process_all_files(self, data_directory: str) -> Dict[str, int]:
        """Process all CSV files in the data directory."""
        csv_files = glob.glob(os.path.join(data_directory, "*.csv"))
        
        if not csv_files:
            self.logger.warning(f"No CSV files found in {data_directory}")
            return {}
        
        total_files = len(csv_files)
        self.logger.info(f"Found {total_files} CSV files to process")
        print(f"\n=== Processing {total_files} CSV Files ===")
        
        connection = self.connect_to_mysql()
        total_stats = {
            'files_processed': 0,
            'total_records': 0,
            'records_with_coordinates': 0,
            'records_with_h3': 0,
            'failed_files': 0
        }
        
        try:
            for i, file_path in enumerate(sorted(csv_files), 1):
                file_name = os.path.basename(file_path)
                print(f"\n[{i}/{total_files}] Processing: {file_name}")
                
                try:
                    records, coords, h3_records = self.process_csv_file(file_path, connection)
                    total_stats['files_processed'] += 1
                    total_stats['total_records'] += records
                    total_stats['records_with_coordinates'] += coords
                    total_stats['records_with_h3'] += h3_records
                    
                    # Show progress for this file
                    print(f"    ✅ Processed {records:,} records ({coords:,} with coordinates, {h3_records:,} with H3)")
                    print(f"    📊 Total so far: {total_stats['total_records']:,} records from {total_stats['files_processed']}/{total_files} files")
                    
                except Exception as e:
                    self.logger.error(f"Failed to process {file_path}: {e}")
                    print(f"    ❌ Failed: {str(e)}")
                    total_stats['failed_files'] += 1
                    continue
            
            self.logger.info(f"Processing complete: {total_stats}")
            
            # Show final summary
            print(f"\n🎉 Processing Complete!")
            print(f"=" * 50)
            print(f"Files processed: {total_stats['files_processed']}/{total_files}")
            print(f"Failed files: {total_stats['failed_files']}")
            print(f"Total records: {total_stats['total_records']:,}")
            print(f"Records with coordinates: {total_stats['records_with_coordinates']:,}")
            print(f"Records with H3 indexes: {total_stats['records_with_h3']:,}")
            print(f"=" * 50)
            
            return total_stats
            
        finally:
            if connection.is_connected():
                connection.close()
                self.logger.info("MySQL connection closed")
    
    def get_processing_status(self) -> pd.DataFrame:
        """Get processing status for all files."""
        connection = mysql.connector.connect(**self.mysql_config)
        try:
            # Simple status check using existing tables
            query = """
            SELECT 
                'Current Status' as file_name,
                COUNT(*) as records_processed,
                SUM(CASE WHEN lat IS NOT NULL AND lng IS NOT NULL THEN 1 ELSE 0 END) as records_with_coordinates,
                SUM(CASE WHEN h3_index IS NOT NULL THEN 1 ELSE 0 END) as records_with_h3,
                MIN(created_at) as processing_start,
                MAX(created_at) as processing_end,
                'In Progress' as status,
                '' as error_message
            FROM amisafe_raw_incidents
            """
            return pd.read_sql(query, connection)
        finally:
            connection.close()


def main():
    """Main function to run the data processor."""
    parser = argparse.ArgumentParser(description='AmISafe Data Processor')
    parser.add_argument('--data-dir', 
                       default='/workspaces/stlouisintegration.com/h3-geolocation/data/raw',
                       help='Directory containing CSV files to process')
    parser.add_argument('--mysql-host', default='127.0.0.1', help='MySQL host')
    parser.add_argument('--mysql-user', default='drupal_user', help='MySQL user')
    parser.add_argument('--mysql-password', default='drupal_secure_password', help='MySQL password')
    parser.add_argument('--mysql-database', default='theoryofconspiracies_dev', help='MySQL database')
    parser.add_argument('--status', action='store_true', help='Show processing status')
    
    args = parser.parse_args()
    
    # Initialize processor
    processor = AmISafeDataProcessor(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database
    )
    
    if args.status:
        # Show processing status
        status_df = processor.get_processing_status()
        print("\nProcessing Status:")
        print("=" * 80)
        print(status_df.to_string(index=False))
    else:
        # Process all files
        print(f"Starting data processing from directory: {args.data_dir}")
        stats = processor.process_all_files(args.data_dir)
        
        print("\nProcessing Summary:")
        print("=" * 50)
        for key, value in stats.items():
            print(f"{key.replace('_', ' ').title()}: {value:,}")


if __name__ == "__main__":
    main()