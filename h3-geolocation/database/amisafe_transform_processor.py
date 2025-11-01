#!/usr/bin/env python3
"""
AmISafe Transform Processor
Processes raw incident data into clean, deduplicated, validated format (Silver layer)
Following data warehouse best practices for business rule application
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
import hashlib
import json
import uuid
from collections import defaultdict

# Add the parent directory to sys.path to import our H3 framework
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from h3_framework import H3GeolocationFramework

class AmISafeTransformProcessor:
    """
    Transform processor for converting raw incident data into business-ready format.
    Implements deduplication, validation, and standardization (Silver layer).
    """
    
    def __init__(self, 
                 mysql_host: str = 'localhost',
                 mysql_user: str = 'root',
                 mysql_password: str = '',
                 mysql_database: str = 'amisafe'):
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
        
        # H3 resolutions for multi-resolution indexing
        self.h3_resolutions = [6, 7, 8, 9, 10]  # District to building level
        
        # Philadelphia coordinate bounds for validation
        self.philly_bounds = {
            'lat_min': 39.86,  'lat_max': 40.14,  # Philadelphia latitude range
            'lng_min': -75.28, 'lng_max': -74.96  # Philadelphia longitude range
        }
        
        # UCR crime code mappings for standardization
        self.ucr_mappings = {
            '100': {'category': 'Violent Crime', 'severity': 5, 'description': 'Homicide'},
            '200': {'category': 'Violent Crime', 'severity': 4, 'description': 'Rape'},
            '300': {'category': 'Violent Crime', 'severity': 4, 'description': 'Robbery'},
            '400': {'category': 'Violent Crime', 'severity': 3, 'description': 'Aggravated Assault'},
            '500': {'category': 'Property Crime', 'severity': 2, 'description': 'Burglary'},
            '600': {'category': 'Property Crime', 'severity': 2, 'description': 'Theft'},
            '700': {'category': 'Property Crime', 'severity': 2, 'description': 'Motor Vehicle Theft'},
            '800': {'category': 'Quality of Life', 'severity': 1, 'description': 'Other Offenses'},
            '900': {'category': 'Quality of Life', 'severity': 1, 'description': 'Public Order'},
            '1000': {'category': 'Property Crime', 'severity': 2, 'description': 'Fraud'},
            '1100': {'category': 'Property Crime', 'severity': 2, 'description': 'Fraud'},
            '1200': {'category': 'Quality of Life', 'severity': 1, 'description': 'Vice'},
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
            self.logger.info(f"Connected to MySQL Server version {connection.server_info}")
            return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def fetch_raw_incidents(self, connection, batch_size: int = 10000, offset: int = 0) -> pd.DataFrame:
        """Fetch raw incidents from Bronze layer in batches."""
        query = """
        SELECT id, source_file, ingestion_timestamp, the_geom, cartodb_id, the_geom_webmercator,
               objectid, dc_dist, psa, dispatch_date_time, dispatch_date, dispatch_time,
               hour, dc_key, location_block, ucr_general, text_general_code,
               point_x, point_y, lat, lng
        FROM amisafe_raw_incidents 
        ORDER BY id 
        LIMIT %s OFFSET %s
        """
        
        try:
            df = pd.read_sql(query, connection, params=(batch_size, offset))
            self.logger.info(f"Fetched {len(df)} raw incidents (offset: {offset})")
            return df
        except Exception as e:
            self.logger.error(f"Error fetching raw incidents: {e}")
            raise
    
    def validate_and_clean_data(self, df: pd.DataFrame) -> pd.DataFrame:
        """Apply data validation and cleaning rules."""
        self.logger.info(f"Validating and cleaning {len(df)} records...")
        
        initial_count = len(df)
        
        # 1. Convert string coordinates to numeric
        df['lat_clean'] = pd.to_numeric(df['lat'], errors='coerce')
        df['lng_clean'] = pd.to_numeric(df['lng'], errors='coerce')
        
        # 2. Validate coordinate bounds (Philadelphia area)
        valid_coords = (
            (df['lat_clean'] >= self.philly_bounds['lat_min']) & 
            (df['lat_clean'] <= self.philly_bounds['lat_max']) &
            (df['lng_clean'] >= self.philly_bounds['lng_min']) & 
            (df['lng_clean'] <= self.philly_bounds['lng_max'])
        )
        
        # 3. Parse and validate datetime
        df['dispatch_datetime_clean'] = pd.to_datetime(df['dispatch_date_time'], errors='coerce')
        valid_datetime = df['dispatch_datetime_clean'].notna()
        
        # 4. Validate UCR codes
        df['ucr_clean'] = df['ucr_general'].astype(str).str.strip()
        valid_ucr = df['ucr_clean'].isin(self.ucr_mappings.keys())
        
        # 5. Validate district codes (1-35 for Philadelphia)
        df['dc_dist_clean'] = pd.to_numeric(df['dc_dist'], errors='coerce')
        valid_district = (df['dc_dist_clean'] >= 1) & (df['dc_dist_clean'] <= 35)
        
        # 6. Calculate coordinate quality based on precision and source
        df['coordinate_quality'] = 'LOW'
        df.loc[valid_coords & (df['lat_clean'].notna()) & (df['lng_clean'].notna()), 'coordinate_quality'] = 'MEDIUM'
        # High quality if coordinates have good precision (more than 5 decimal places)
        high_precision = (
            (df['lat_clean'].astype(str).str.split('.').str[1].str.len() > 5) &
            (df['lng_clean'].astype(str).str.split('.').str[1].str.len() > 5)
        )
        df.loc[valid_coords & high_precision, 'coordinate_quality'] = 'HIGH'
        
        # 7. Calculate overall data quality score
        quality_factors = [
            valid_coords.astype(int),
            valid_datetime.astype(int),
            valid_ucr.astype(int),
            valid_district.astype(int),
            (df['cartodb_id'].notna()).astype(int),
            (df['objectid'].notna()).astype(int)
        ]
        df['data_quality_score'] = np.mean(quality_factors, axis=0)
        
        # 8. Filter out records that don't meet minimum quality standards
        minimum_quality = valid_coords & valid_datetime & valid_ucr
        df_clean = df[minimum_quality].copy()
        
        removed_count = initial_count - len(df_clean)
        self.logger.info(f"Validation complete: {len(df_clean)} valid records, {removed_count} removed")
        
        return df_clean
    
    def deduplicate_incidents(self, df: pd.DataFrame) -> pd.DataFrame:
        """Apply multi-tier deduplication strategy."""
        self.logger.info(f"Deduplicating {len(df)} incidents...")
        
        initial_count = len(df)
        df_dedup = df.copy()
        
        # Generate unique incident IDs and track duplicates
        df_dedup['duplicate_group_id'] = None
        df_dedup['is_duplicate'] = False
        
        # Tier 1: CartoDB ID deduplication (highest priority)
        cartodb_duplicates = df_dedup.groupby('cartodb_id').size()
        cartodb_duplicates = cartodb_duplicates[cartodb_duplicates > 1].index
        
        for cartodb_id in cartodb_duplicates:
            if pd.notna(cartodb_id):
                mask = df_dedup['cartodb_id'] == cartodb_id
                group_id = f"cartodb_{cartodb_id}"
                df_dedup.loc[mask, 'duplicate_group_id'] = group_id
                # Keep first occurrence, mark others as duplicates
                duplicate_indices = df_dedup[mask].index[1:]
                df_dedup.loc[duplicate_indices, 'is_duplicate'] = True
        
        # Tier 2: ObjectID deduplication (for records without CartoDB ID)
        no_cartodb = df_dedup['cartodb_id'].isna()
        objectid_duplicates = df_dedup[no_cartodb].groupby('objectid').size()
        objectid_duplicates = objectid_duplicates[objectid_duplicates > 1].index
        
        for objectid in objectid_duplicates:
            if pd.notna(objectid):
                mask = (df_dedup['objectid'] == objectid) & no_cartodb & (~df_dedup['is_duplicate'])
                group_id = f"objectid_{int(objectid)}"
                df_dedup.loc[mask, 'duplicate_group_id'] = group_id
                # Keep first occurrence, mark others as duplicates
                duplicate_indices = df_dedup[mask].index[1:]
                df_dedup.loc[duplicate_indices, 'is_duplicate'] = True
        
        # Tier 3: Spatial-temporal fuzzy matching (for remaining records)
        no_primary_id = df_dedup['cartodb_id'].isna() & df_dedup['objectid'].isna()
        remaining = df_dedup[no_primary_id & (~df_dedup['is_duplicate'])].copy()
        
        if len(remaining) > 0:
            # Group by approximate location (rounded to ~100m precision) and time window
            remaining['lat_rounded'] = remaining['lat_clean'].round(3)  # ~100m precision
            remaining['lng_rounded'] = remaining['lng_clean'].round(3)
            remaining['datetime_rounded'] = remaining['dispatch_datetime_clean'].dt.floor('H')  # Hour precision
            
            spatial_temp_groups = remaining.groupby([
                'lat_rounded', 'lng_rounded', 'datetime_rounded', 'ucr_clean'
            ]).size()
            spatial_temp_duplicates = spatial_temp_groups[spatial_temp_groups > 1].index
            
            for group_key in spatial_temp_duplicates:
                lat_r, lng_r, dt_r, ucr = group_key
                mask = (
                    (df_dedup['lat_clean'].round(3) == lat_r) &
                    (df_dedup['lng_clean'].round(3) == lng_r) &
                    (df_dedup['dispatch_datetime_clean'].dt.floor('H') == dt_r) &
                    (df_dedup['ucr_clean'] == ucr) &
                    no_primary_id & (~df_dedup['is_duplicate'])
                )
                
                if mask.sum() > 1:
                    group_id = f"spatial_{lat_r}_{lng_r}_{dt_r.strftime('%Y%m%d%H')}_{ucr}"
                    df_dedup.loc[mask, 'duplicate_group_id'] = group_id
                    # Keep first occurrence, mark others as duplicates
                    duplicate_indices = df_dedup[mask].index[1:]
                    df_dedup.loc[duplicate_indices, 'is_duplicate'] = True
        
        # Generate master incident IDs for non-duplicates
        non_duplicates = ~df_dedup['is_duplicate']
        df_dedup.loc[non_duplicates, 'incident_id'] = df_dedup.loc[non_duplicates].apply(
            lambda row: f"PHI_{row.name}_{int(row['cartodb_id']) if pd.notna(row['cartodb_id']) else 'X'}_{int(row['objectid']) if pd.notna(row['objectid']) else 'X'}",
            axis=1
        )
        
        duplicate_count = df_dedup['is_duplicate'].sum()
        unique_count = len(df_dedup) - duplicate_count
        
        self.logger.info(f"Deduplication complete: {unique_count} unique incidents, {duplicate_count} duplicates identified")
        
        return df_dedup
    
    def standardize_crime_data(self, df: pd.DataFrame) -> pd.DataFrame:
        """Standardize crime categories and severity levels."""
        self.logger.info("Standardizing crime categories and severity...")
        
        # Apply UCR mappings
        df['crime_category'] = df['ucr_clean'].map(lambda x: self.ucr_mappings.get(x, {}).get('category', 'Unknown'))
        df['severity_level'] = df['ucr_clean'].map(lambda x: self.ucr_mappings.get(x, {}).get('severity', 3))
        df['crime_description'] = df['ucr_clean'].map(lambda x: self.ucr_mappings.get(x, {}).get('description', 'Unknown'))
        
        # Clean and standardize location blocks
        df['location_block_clean'] = df['location_block'].astype(str).str.upper().str.strip()
        df['location_block_clean'] = df['location_block_clean'].replace(['NAN', 'NONE', ''], None)
        
        return df
    
    def add_h3_spatial_indexes(self, df: pd.DataFrame) -> pd.DataFrame:
        """Add H3 spatial indexes at multiple resolutions."""
        self.logger.info("Adding H3 spatial indexes...")
        
        # Add H3 indexes for valid coordinates
        valid_coords = df['lat_clean'].notna() & df['lng_clean'].notna()
        
        for resolution in self.h3_resolutions:
            col_name = f'h3_res_{resolution}'
            df[col_name] = None
            
            if valid_coords.any():
                df.loc[valid_coords, col_name] = df.loc[valid_coords].apply(
                    lambda row: h3.latlng_to_cell(row['lat_clean'], row['lng_clean'], resolution),
                    axis=1
                )
        
        h3_count = df['h3_res_9'].notna().sum()
        self.logger.info(f"Added H3 spatial indexes to {h3_count} incidents")
        
        return df
    
    def add_temporal_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Add derived temporal features."""
        self.logger.info("Adding temporal features...")
        
        # Extract temporal components
        df['incident_datetime'] = df['dispatch_datetime_clean']
        df['incident_date'] = df['dispatch_datetime_clean'].dt.date
        df['incident_hour'] = df['dispatch_datetime_clean'].dt.hour
        df['incident_month'] = df['dispatch_datetime_clean'].dt.month
        df['incident_year'] = df['dispatch_datetime_clean'].dt.year
        df['day_of_week'] = df['dispatch_datetime_clean'].dt.dayofweek + 1  # 1=Monday
        
        return df
    
    def prepare_clean_record(self, row: pd.Series) -> Dict:
        """Prepare a single clean record for insertion."""
        return {
            'raw_incident_ids': json.dumps([int(row['id'])]),
            'processing_batch_id': self.batch_id,
            'incident_id': row.get('incident_id'),
            'cartodb_id': int(row['cartodb_id']) if pd.notna(row['cartodb_id']) else None,
            'objectid': int(row['objectid']) if pd.notna(row['objectid']) else None,
            'dc_key': str(row['dc_key']) if pd.notna(row['dc_key']) else None,
            'dc_dist': str(int(row['dc_dist_clean'])) if pd.notna(row['dc_dist_clean']) else None,
            'psa': str(row['psa']) if pd.notna(row['psa']) else None,
            'location_block': row.get('location_block_clean'),
            'lat': float(row['lat_clean']),
            'lng': float(row['lng_clean']),
            'coordinate_quality': row['coordinate_quality'],
            'incident_datetime': row['incident_datetime'],
            'incident_date': row['incident_date'],
            'incident_hour': int(row['incident_hour']),
            'incident_month': int(row['incident_month']),
            'incident_year': int(row['incident_year']),
            'day_of_week': int(row['day_of_week']),
            'ucr_general': row['ucr_clean'],
            'crime_category': row['crime_category'],
            'crime_description': row['crime_description'],
            'severity_level': int(row['severity_level']),
            'h3_res_6': row.get('h3_res_6'),
            'h3_res_7': row.get('h3_res_7'),
            'h3_res_8': row.get('h3_res_8'),
            'h3_res_9': row.get('h3_res_9'),
            'h3_res_10': row.get('h3_res_10'),
            'data_quality_score': float(row['data_quality_score']),
            'duplicate_group_id': row.get('duplicate_group_id'),
            'is_duplicate': bool(row['is_duplicate']),
            'is_valid': True
        }
    
    def insert_clean_incidents(self, df: pd.DataFrame, connection) -> int:
        """Insert clean incidents into Silver layer table."""
        self.logger.info(f"Inserting {len(df)} clean incidents...")
        
        insert_query = """
        INSERT INTO amisafe_incidents_clean (
            objectid, cartodb_id, dc_key, latitude, longitude, point_x, point_y,
            dc_dist, psa, dispatch_datetime, dispatch_date, dispatch_time, hour,
            location_block, ucr_general, text_general_code,
            h3_index, h3_resolution, data_quality_score, completeness_score, accuracy_score,
            raw_record_id, duplicate_group_id, deduplication_method,
            transform_timestamp, validation_status, validation_notes
        ) VALUES (
            %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 
            %s, %s, %s, %s, %s, %s, %s, %s, %s
        )
        """
        
        cursor = connection.cursor()
        
        # Process records in batches
        batch_size = 1000
        total_inserted = 0
        
        for i in range(0, len(df), batch_size):
            batch = df.iloc[i:i + batch_size]
            records = []
            
            for _, row in batch.iterrows():
                try:
                    record = self.prepare_clean_record(row)
                    records.append(tuple(record.values()))
                except Exception as e:
                    self.logger.warning(f"Skipping record {row['id']}: {e}")
                    continue
            
            if records:
                cursor.executemany(insert_query, records)
                total_inserted += len(records)
                
                if i % (batch_size * 10) == 0:
                    self.logger.info(f"Inserted {total_inserted}/{len(df)} records")
        
        cursor.close()
        self.logger.info(f"Successfully inserted {total_inserted} clean incidents")
        return total_inserted
    
    def process_raw_to_clean(self, batch_size: int = 10000) -> Dict[str, int]:
        """Process all raw incidents through transform pipeline."""
        self.batch_id = f"transform_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
        
        connection = self.connect_to_mysql()
        
        # Get total count of raw incidents
        cursor = connection.cursor()
        cursor.execute("SELECT COUNT(*) FROM amisafe_raw_incidents")
        total_raw_count = cursor.fetchone()[0]
        cursor.close()
        
        self.logger.info(f"Starting transform processing for {total_raw_count} raw incidents")
        
        stats = {
            'total_raw': total_raw_count,
            'total_processed': 0,
            'total_clean': 0,
            'total_duplicates': 0,
            'failed_batches': 0
        }
        
        try:
            offset = 0
            batch_num = 1
            
            while offset < total_raw_count:
                self.logger.info(f"Processing batch {batch_num} (offset: {offset})")
                
                try:
                    # 1. Fetch raw data batch
                    raw_df = self.fetch_raw_incidents(connection, batch_size, offset)
                    
                    if len(raw_df) == 0:
                        break
                    
                    # 2. Validate and clean
                    clean_df = self.validate_and_clean_data(raw_df)
                    
                    # 3. Deduplicate
                    dedup_df = self.deduplicate_incidents(clean_df)
                    
                    # 4. Standardize
                    standard_df = self.standardize_crime_data(dedup_df)
                    
                    # 5. Add H3 indexes
                    h3_df = self.add_h3_spatial_indexes(standard_df)
                    
                    # 6. Add temporal features
                    final_df = self.add_temporal_features(h3_df)
                    
                    # 7. Insert clean records
                    inserted_count = self.insert_clean_incidents(final_df, connection)
                    
                    # Update statistics
                    stats['total_processed'] += len(raw_df)
                    stats['total_clean'] += inserted_count
                    stats['total_duplicates'] += final_df['is_duplicate'].sum()
                    
                    offset += batch_size
                    batch_num += 1
                    
                except Exception as e:
                    self.logger.error(f"Failed to process batch {batch_num}: {e}")
                    stats['failed_batches'] += 1
                    offset += batch_size  # Skip this batch and continue
                    continue
            
            self.logger.info(f"Transform processing complete: {stats}")
            return stats
            
        finally:
            if connection.is_connected():
                connection.close()
                self.logger.info("MySQL connection closed")

def main():
    """Main function to run the transform processor."""
    parser = argparse.ArgumentParser(description='AmISafe Transform Processor')
    parser.add_argument('--mysql-host', default='localhost', help='MySQL host')
    parser.add_argument('--mysql-user', default='root', help='MySQL user')
    parser.add_argument('--mysql-password', default='', help='MySQL password')
    parser.add_argument('--mysql-database', default='amisafe', help='MySQL database')
    parser.add_argument('--batch-size', type=int, default=10000, help='Batch size for processing')
    
    args = parser.parse_args()
    
    # Initialize processor
    processor = AmISafeTransformProcessor(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database
    )
    
    # Process raw data to clean format
    print(f"\n=== AmISafe Transform Processing (Silver Layer) ===")
    print(f"Processing raw incidents into clean, deduplicated format...")
    
    stats = processor.process_raw_to_clean(batch_size=args.batch_size)
    
    print(f"\n🎉 Transform Processing Complete!")
    print(f"=" * 50)
    print(f"Total raw incidents: {stats['total_raw']:,}")
    print(f"Successfully processed: {stats['total_processed']:,}")
    print(f"Clean incidents created: {stats['total_clean']:,}")
    print(f"Duplicates identified: {stats['total_duplicates']:,}")
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