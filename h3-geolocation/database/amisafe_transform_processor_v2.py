#!/usr/bin/env python3
"""
AmISafe Transform Processor - Aligned with Architecture Documentation
Implements the documented 3-layer data warehouse architecture:
Raw (Bronze) → Transform (Silver) → Final (Gold)

Following the architecture specified in h3-geolocation/README.md
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
import json
import uuid
from collections import defaultdict
from typing import Dict, List, Tuple, Optional

# Add the parent directory to sys.path to import our H3 framework
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from h3_framework import H3GeolocationFramework

class AmISafeTransformProcessor:
    """
    Transform processor implementing documented Silver layer architecture.
    Processes Raw layer data into business-ready format with comprehensive exclusion tracking.
    """
    
    def __init__(self, 
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = 'drupal_secure_password',
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
        
        # Exclusion tracking
        self.exclusion_stats = {
            'total_raw_records': 0,
            'processed_batches': 0,
            'exclusions': {
                'missing_coordinates': 0,
                'invalid_coordinates': 0,
                'missing_datetime': 0,
                'invalid_datetime': 0,
                'missing_crime_type': 0,
                'invalid_district': 0,
                'duplicate_cartodb_id': 0,
                'duplicate_objectid': 0,
                'duplicate_composite': 0,
                'data_quality_too_low': 0,
                'h3_indexing_failed': 0
            },
            'successful_inserts': 0,
            'batch_failures': 0
        }
        
        # Philadelphia data validation rules
        self.valid_districts = [str(i) for i in range(1, 36)]  # Districts 1-35
        self.valid_ucr_codes = ['100', '200', '300', '400', '500', '600', '700', '800', '900']
        
        # Philadelphia coordinate bounds
        self.philly_bounds = {
            'lat_min': 39.867, 'lat_max': 40.138,
            'lng_min': -75.280, 'lng_max': -74.955
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
    
    def ensure_clean_incidents_table(self, connection):
        """Create the amisafe_clean_incidents table following documented schema."""
        create_table_sql = """
        CREATE TABLE IF NOT EXISTS amisafe_clean_incidents (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            
            -- Data lineage
            raw_incident_ids JSON,
            processing_batch_id VARCHAR(50),
            processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Validated business fields
            incident_id VARCHAR(50) UNIQUE,
            cartodb_id INT,
            objectid BIGINT,
            dc_key VARCHAR(50),
            
            -- Cleaned location data
            dc_dist VARCHAR(10) NOT NULL,
            psa VARCHAR(10),
            location_block VARCHAR(500),
            lat DECIMAL(10,7) NOT NULL,
            lng DECIMAL(11,7) NOT NULL,
            coordinate_quality ENUM('HIGH', 'MEDIUM', 'LOW'),
            
            -- Normalized temporal data
            incident_datetime DATETIME NOT NULL,
            incident_date DATE NOT NULL,
            incident_hour TINYINT NOT NULL,
            incident_month TINYINT NOT NULL,
            incident_year SMALLINT NOT NULL,
            day_of_week TINYINT,
            
            -- Crime classification
            ucr_general VARCHAR(10) NOT NULL,
            crime_category VARCHAR(50),
            crime_description VARCHAR(255),
            severity_level TINYINT DEFAULT 3,
            
            -- H3 spatial indexing
            h3_res_6 VARCHAR(16),
            h3_res_7 VARCHAR(16),
            h3_res_8 VARCHAR(16),
            h3_res_9 VARCHAR(16),
            h3_res_10 VARCHAR(16),
            
            -- Quality and governance
            data_quality_score DECIMAL(3,2),
            duplicate_group_id VARCHAR(50),
            is_duplicate BOOLEAN DEFAULT FALSE,
            is_valid BOOLEAN DEFAULT TRUE,
            
            -- Indexes
            UNIQUE KEY unique_incident (incident_id),
            INDEX idx_location (lat, lng),
            INDEX idx_h3_res8 (h3_res_8),
            INDEX idx_h3_res9 (h3_res_9),
            INDEX idx_datetime (incident_datetime),
            INDEX idx_district (dc_dist),
            INDEX idx_crime_type (ucr_general),
            INDEX idx_quality (data_quality_score)
        );
        """
        
        try:
            cursor = connection.cursor()
            cursor.execute(create_table_sql)
            connection.commit()
            self.logger.info("✅ amisafe_clean_incidents table ready")
        except Error as e:
            self.logger.error(f"Error creating clean incidents table: {e}")
            raise
        finally:
            cursor.close()
    
    def fetch_raw_incidents(self, connection, batch_size: int = 10000, offset: int = 0) -> pd.DataFrame:
        """Fetch raw incidents from the Raw layer."""
        query = """
        SELECT id, cartodb_id, objectid, dc_key, dc_dist, psa, 
               dispatch_date_time, lat, lng, location_block,
               ucr_general, text_general_code
        FROM amisafe_raw_incidents 
        WHERE processing_status = 'raw'
        ORDER BY id
        LIMIT %s OFFSET %s
        """
        
        try:
            df = pd.read_sql(query, connection, params=(batch_size, offset))
            self.logger.info(f"Fetched {len(df)} raw incidents (offset: {offset})")
            return df
        except Exception as e:
            self.logger.error(f"Error fetching raw incidents: {e}")
            return pd.DataFrame()
    
    def validate_record(self, row: pd.Series) -> Tuple[bool, str]:
        """Validate a single record and return (is_valid, exclusion_reason)."""
        
        # Check coordinates
        if pd.isna(row.get('lat')) or pd.isna(row.get('lng')):
            return False, 'missing_coordinates'
        
        try:
            lat, lng = float(row['lat']), float(row['lng'])
        except (ValueError, TypeError):
            return False, 'invalid_coordinates'
        
        # Check coordinate bounds for Philadelphia
        if not (self.philly_bounds['lat_min'] <= lat <= self.philly_bounds['lat_max'] and
                self.philly_bounds['lng_min'] <= lng <= self.philly_bounds['lng_max']):
            return False, 'invalid_coordinates'
        
        # Check datetime
        if pd.isna(row.get('dispatch_date_time')):
            return False, 'missing_datetime'
        
        try:
            datetime.strptime(str(row['dispatch_date_time']), '%Y-%m-%d %H:%M:%S')
        except (ValueError, TypeError):
            return False, 'invalid_datetime'
        
        # Check crime type
        if pd.isna(row.get('ucr_general')) or str(row['ucr_general']).strip() == '':
            return False, 'missing_crime_type'
        
        # Check district
        if pd.isna(row.get('dc_dist')) or str(row['dc_dist']) not in self.valid_districts:
            return False, 'invalid_district'
        
        return True, 'valid'
    
    def detect_duplicates(self, df: pd.DataFrame) -> pd.DataFrame:
        """Detect and mark duplicates in the dataframe."""
        duplicates_found = 0
        
        # Check for cartodb_id duplicates
        cartodb_dupes = df.duplicated(subset=['cartodb_id'], keep='first')
        duplicates_found += cartodb_dupes.sum()
        df.loc[cartodb_dupes, 'exclusion_reason'] = 'duplicate_cartodb_id'
        
        # Check for objectid duplicates (excluding already marked duplicates)
        remaining_df = df[~cartodb_dupes]
        objectid_dupes = remaining_df.duplicated(subset=['objectid'], keep='first')
        df.loc[remaining_df[objectid_dupes].index, 'exclusion_reason'] = 'duplicate_objectid'
        duplicates_found += objectid_dupes.sum()
        
        # Check for composite duplicates (lat/lng + datetime + crime_type)
        remaining_df = df[(~cartodb_dupes) & (~df.index.isin(remaining_df[objectid_dupes].index))]
        composite_dupes = remaining_df.duplicated(
            subset=['lat', 'lng', 'dispatch_date_time', 'ucr_general'], keep='first'
        )
        df.loc[remaining_df[composite_dupes].index, 'exclusion_reason'] = 'duplicate_composite'
        duplicates_found += composite_dupes.sum()
        
        self.logger.info(f"Identified {duplicates_found} duplicate records")
        return df
    
    def calculate_data_quality_score(self, row: pd.Series) -> float:
        """Calculate data quality score (0.0 - 1.0)."""
        score = 1.0
        
        # Coordinate quality
        if pd.isna(row.get('lat')) or pd.isna(row.get('lng')):
            score -= 0.3
        
        # Temporal quality
        if pd.isna(row.get('dispatch_date_time')):
            score -= 0.2
        
        # Location description quality
        if pd.isna(row.get('location_block')) or str(row.get('location_block')).strip() == '':
            score -= 0.1
        
        # Crime description quality
        if pd.isna(row.get('text_general_code')) or str(row.get('text_general_code')).strip() == '':
            score -= 0.1
        
        # District validation
        if pd.isna(row.get('dc_dist')) or str(row.get('dc_dist')) not in self.valid_districts:
            score -= 0.2
        
        # UCR code validation
        if pd.isna(row.get('ucr_general')):
            score -= 0.1
        
        return max(0.0, score)
    
    def add_h3_indexes(self, row: pd.Series) -> Dict[str, Optional[str]]:
        """Add H3 spatial indexes for multiple resolutions."""
        # Initialize all H3 fields to None to ensure consistent dictionary keys
        h3_indexes = {
            'h3_res_6': None,
            'h3_res_7': None,
            'h3_res_8': None,
            'h3_res_9': None,
            'h3_res_10': None
        }
        
        try:
            lat, lng = float(row['lat']), float(row['lng'])
            
            # Generate H3 indexes for resolutions 6-10
            for res in range(6, 11):
                h3_index = h3.latlng_to_cell(lat, lng, res)
                h3_indexes[f'h3_res_{res}'] = h3_index
                
        except Exception as e:
            self.logger.warning(f"Failed to generate H3 indexes: {e}")
            # H3 fields remain None, which will be tracked in exclusions
            
        return h3_indexes
    
    def prepare_clean_record(self, row: pd.Series, batch_id: str) -> Dict:
        """Prepare a cleaned record for insertion."""
        try:
            # Parse datetime
            incident_dt = datetime.strptime(str(row['dispatch_date_time']), '%Y-%m-%d %H:%M:%S')
            
            # Generate H3 indexes
            h3_indexes = self.add_h3_indexes(row)
            
            # Calculate data quality
            quality_score = self.calculate_data_quality_score(row)
            
            # Create incident ID
            incident_id = f"{row['cartodb_id']}_{row['objectid']}" if pd.notna(row['cartodb_id']) and pd.notna(row['objectid']) else str(uuid.uuid4())
            
            clean_record = {
                'raw_incident_ids': json.dumps([int(row['id'])]),
                'processing_batch_id': batch_id,
                'incident_id': incident_id,
                'cartodb_id': int(row['cartodb_id']) if pd.notna(row['cartodb_id']) else None,
                'objectid': int(row['objectid']) if pd.notna(row['objectid']) else None,
                'dc_key': str(row['dc_key']) if pd.notna(row['dc_key']) else None,
                'dc_dist': str(row['dc_dist']),
                'psa': str(row['psa']) if pd.notna(row['psa']) else None,
                'location_block': str(row['location_block']) if pd.notna(row['location_block']) else None,
                'lat': float(row['lat']),
                'lng': float(row['lng']),
                'coordinate_quality': 'HIGH',  # Since we validated coordinates
                'incident_datetime': incident_dt,
                'incident_date': incident_dt.date(),
                'incident_hour': incident_dt.hour,
                'incident_month': incident_dt.month,
                'incident_year': incident_dt.year,
                'day_of_week': incident_dt.weekday() + 1,  # 1=Monday
                'ucr_general': str(row['ucr_general']),
                'crime_category': self.get_crime_category(str(row['ucr_general'])),
                'crime_description': str(row['text_general_code']) if pd.notna(row['text_general_code']) else None,
                'severity_level': self.get_severity_level(str(row['ucr_general'])),
                'data_quality_score': quality_score,
                'duplicate_group_id': None,
                'is_duplicate': False,
                'is_valid': True
            }
            
            # Add H3 indexes
            clean_record.update(h3_indexes)
            
            return clean_record
            
        except Exception as e:
            self.logger.error(f"Error preparing clean record: {e}")
            return None
    
    def get_crime_category(self, ucr_code: str) -> str:
        """Map UCR code to crime category."""
        category_map = {
            '100': 'Violent Crime', '200': 'Violent Crime', '300': 'Violent Crime',
            '400': 'Property Crime', '500': 'Property Crime', '600': 'Property Crime',
            '700': 'Quality of Life', '800': 'Quality of Life', '900': 'Other'
        }
        return category_map.get(ucr_code[:1] + '00', 'Other')
    
    def get_severity_level(self, ucr_code: str) -> int:
        """Map UCR code to severity level (1-5)."""
        severity_map = {
            '100': 5,  # Homicide - highest severity
            '200': 4,  # Rape, robbery - high severity
            '300': 3,  # Assault - medium severity
            '400': 2,  # Burglary, theft - low-medium severity
            '500': 2,  # Theft - low-medium severity
            '600': 1,  # Fraud - lowest severity
            '700': 1,  # Quality of life - lowest severity
            '800': 1,  # Other - lowest severity
            '900': 3   # Unknown - medium severity
        }
        return severity_map.get(ucr_code[:1] + '00', 3)
    
    def insert_clean_records(self, connection, clean_records: List[Dict], batch_id: str):
        """Insert clean records into amisafe_clean_incidents table."""
        if not clean_records:
            return
        
        insert_sql = """
        INSERT INTO amisafe_clean_incidents (
            raw_incident_ids, processing_batch_id, incident_id, cartodb_id, objectid, dc_key,
            dc_dist, psa, location_block, lat, lng, coordinate_quality,
            incident_datetime, incident_date, incident_hour, incident_month, incident_year, day_of_week,
            ucr_general, crime_category, crime_description, severity_level,
            h3_res_6, h3_res_7, h3_res_8, h3_res_9, h3_res_10,
            data_quality_score, duplicate_group_id, is_duplicate, is_valid
        ) VALUES (
            %(raw_incident_ids)s, %(processing_batch_id)s, %(incident_id)s, %(cartodb_id)s, %(objectid)s, %(dc_key)s,
            %(dc_dist)s, %(psa)s, %(location_block)s, %(lat)s, %(lng)s, %(coordinate_quality)s,
            %(incident_datetime)s, %(incident_date)s, %(incident_hour)s, %(incident_month)s, %(incident_year)s, %(day_of_week)s,
            %(ucr_general)s, %(crime_category)s, %(crime_description)s, %(severity_level)s,
            %(h3_res_6)s, %(h3_res_7)s, %(h3_res_8)s, %(h3_res_9)s, %(h3_res_10)s,
            %(data_quality_score)s, %(duplicate_group_id)s, %(is_duplicate)s, %(is_valid)s
        )
        """
        
        try:
            cursor = connection.cursor()
            cursor.executemany(insert_sql, clean_records)
            connection.commit()
            self.exclusion_stats['successful_inserts'] += len(clean_records)
            self.logger.info(f"✅ Inserted {len(clean_records)} clean incidents")
        except Error as e:
            self.logger.error(f"Error inserting clean records: {e}")
            self.exclusion_stats['batch_failures'] += 1
            raise
        finally:
            cursor.close()
    
    def process_batch(self, connection, batch_df: pd.DataFrame, batch_id: str) -> Dict:
        """Process a single batch of raw incidents."""
        batch_stats = {
            'total_records': len(batch_df),
            'valid_records': 0,
            'excluded_records': 0,
            'exclusion_reasons': defaultdict(int),
            'clean_records_created': 0
        }
        
        # Add exclusion reason column
        batch_df['exclusion_reason'] = 'valid'
        
        # Validate each record
        for idx, row in batch_df.iterrows():
            is_valid, reason = self.validate_record(row)
            if not is_valid:
                batch_df.at[idx, 'exclusion_reason'] = reason
                batch_stats['exclusion_reasons'][reason] += 1
        
        # Detect duplicates in valid records
        valid_mask = batch_df['exclusion_reason'] == 'valid'
        if valid_mask.sum() > 0:
            batch_df = self.detect_duplicates(batch_df)
        
        # Count exclusions
        excluded_mask = batch_df['exclusion_reason'] != 'valid'
        batch_stats['excluded_records'] = excluded_mask.sum()
        batch_stats['valid_records'] = len(batch_df) - batch_stats['excluded_records']
        
        # Update global exclusion stats
        for reason, count in batch_stats['exclusion_reasons'].items():
            self.exclusion_stats['exclusions'][reason] += count
        
        # Prepare clean records from valid data
        valid_records = batch_df[~excluded_mask]
        clean_records = []
        
        for _, row in valid_records.iterrows():
            clean_record = self.prepare_clean_record(row, batch_id)
            if clean_record:
                clean_records.append(clean_record)
        
        # Insert clean records
        if clean_records:
            self.insert_clean_records(connection, clean_records, batch_id)
            batch_stats['clean_records_created'] = len(clean_records)
        
        return batch_stats
    
    def generate_exclusion_report(self) -> str:
        """Generate comprehensive exclusion report."""
        total_processed = self.exclusion_stats['total_raw_records']
        total_excluded = sum(self.exclusion_stats['exclusions'].values())
        total_included = self.exclusion_stats['successful_inserts']
        
        report = f"""
{'='*80}
AMISAFE TRANSFORM LAYER - EXCLUSION REPORT
{'='*80}

PROCESSING SUMMARY:
------------------
Total Raw Records Processed: {total_processed:,}
Successfully Transformed:    {total_included:,} ({total_included/total_processed*100:.1f}%)
Excluded Records:            {total_excluded:,} ({total_excluded/total_processed*100:.1f}%)
Processing Batches:          {self.exclusion_stats['processed_batches']}
Batch Failures:             {self.exclusion_stats['batch_failures']}

EXCLUSION BREAKDOWN:
-------------------"""
        
        # Sort exclusions by count (highest first)
        sorted_exclusions = sorted(
            self.exclusion_stats['exclusions'].items(), 
            key=lambda x: x[1], 
            reverse=True
        )
        
        for reason, count in sorted_exclusions:
            if count > 0:
                percentage = (count / total_processed * 100) if total_processed > 0 else 0
                reason_formatted = reason.replace('_', ' ').title()
                report += f"\n  {reason_formatted:<25}: {count:>8,} ({percentage:>5.1f}%)"
        
        report += f"""

EXCLUSION REASONS EXPLAINED:
----------------------------
• Missing Coordinates:     Records without lat/lng values
• Invalid Coordinates:     Coordinates outside Philadelphia bounds
• Missing Datetime:        Records without dispatch_date_time
• Invalid Datetime:        Unparseable or invalid date/time formats
• Missing Crime Type:      Records without UCR general code
• Invalid District:        District not in valid range (1-35)
• Duplicate Cartodb ID:    Records with duplicate CartoDB identifiers
• Duplicate Objectid:      Records with duplicate incident IDs
• Duplicate Composite:     Records with same location + time + crime type
• Data Quality Too Low:    Records failing quality score threshold
• H3 Indexing Failed:      Records that couldn't be spatially indexed

DATA QUALITY ASSESSMENT:
-----------------------
Records meeting all validation criteria: {total_included:,}
Data completeness rate: {(total_included/total_processed*100):.1f}%
Primary exclusion reason: {sorted_exclusions[0][0].replace('_', ' ').title() if sorted_exclusions else 'N/A'}

{'='*80}
"""
        return report
    
    def process_raw_to_clean(self, batch_size: int = 10000) -> Dict:
        """Main processing function - Raw to Transform layer."""
        self.logger.info("Starting Raw → Transform layer processing...")
        
        connection = None
        try:
            connection = self.connect_to_mysql()
            
            # Ensure clean incidents table exists
            self.ensure_clean_incidents_table(connection)
            
            # Count total raw records
            cursor = connection.cursor()
            cursor.execute("SELECT COUNT(*) FROM amisafe_raw_incidents WHERE processing_status = 'raw'")
            total_count = cursor.fetchone()[0]
            self.exclusion_stats['total_raw_records'] = total_count
            cursor.close()
            
            self.logger.info(f"Processing {total_count:,} raw incidents...")
            
            offset = 0
            batch_num = 0
            
            while True:
                # Fetch batch
                batch_df = self.fetch_raw_incidents(connection, batch_size, offset)
                if batch_df.empty:
                    break
                
                batch_num += 1
                batch_id = f"batch_{datetime.now().strftime('%Y%m%d_%H%M%S')}_{batch_num}"
                
                self.logger.info(f"Processing batch {batch_num} ({len(batch_df)} records)...")
                
                # Process batch
                batch_stats = self.process_batch(connection, batch_df, batch_id)
                self.exclusion_stats['processed_batches'] += 1
                
                self.logger.info(
                    f"Batch {batch_num}: {batch_stats['valid_records']} valid, "
                    f"{batch_stats['excluded_records']} excluded, "
                    f"{batch_stats['clean_records_created']} inserted"
                )
                
                offset += batch_size
            
            # Generate and log exclusion report
            report = self.generate_exclusion_report()
            self.logger.info("Transform processing complete!")
            print(report)
            
            return {
                'total_raw': self.exclusion_stats['total_raw_records'],
                'total_processed': self.exclusion_stats['total_raw_records'],
                'total_clean': self.exclusion_stats['successful_inserts'],
                'total_excluded': sum(self.exclusion_stats['exclusions'].values()),
                'exclusion_breakdown': dict(self.exclusion_stats['exclusions']),
                'batches_processed': self.exclusion_stats['processed_batches'],
                'batch_failures': self.exclusion_stats['batch_failures']
            }
            
        except Exception as e:
            self.logger.error(f"Transform processing failed: {e}")
            raise
        finally:
            if connection and connection.is_connected():
                connection.close()
                self.logger.info("MySQL connection closed")

def main():
    """Main function to run the transform processor."""
    import argparse
    
    parser = argparse.ArgumentParser(description='AmISafe Transform Processor - Architecture Compliant')
    parser.add_argument('--mysql-host', default='127.0.0.1', help='MySQL host')
    parser.add_argument('--mysql-user', default='drupal_user', help='MySQL user')
    parser.add_argument('--mysql-password', default='drupal_secure_password', help='MySQL password')
    parser.add_argument('--mysql-database', default='theoryofconspiracies_dev', help='MySQL database')
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
    print(f"\n🔄 AmISafe Transform Processing (Raw → Silver Layer)")
    print(f"Processing raw incidents into clean, deduplicated format...")
    
    stats = processor.process_raw_to_clean(batch_size=args.batch_size)
    
    print(f"\n🎉 Transform Processing Complete!")
    print(f"=" * 50)
    print(f"Total raw incidents: {stats['total_raw']:,}")
    print(f"Successfully processed: {stats['total_processed']:,}")
    print(f"Clean incidents created: {stats['total_clean']:,}")
    print(f"Records excluded: {stats['total_excluded']:,}")
    print(f"Batches processed: {stats['batches_processed']}")
    print(f"Batch failures: {stats['batch_failures']}")
    
    if stats['total_processed'] > 0:
        success_rate = (stats['total_clean'] / stats['total_processed'] * 100)
        print(f"Transform success rate: {success_rate:.1f}%")
    else:
        print(f"Transform success rate: N/A (no records processed)")
    
    print(f"=" * 50)

if __name__ == '__main__':
    main()