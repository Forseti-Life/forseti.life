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
                 mysql_database: str = 'stlouisintegration_dev'):
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
        
        # Setup logging with subdirectory
        self.logs_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'processing_logs')
        os.makedirs(self.logs_dir, exist_ok=True)
        
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        log_filename = os.path.join(self.logs_dir, f'transform_processing_{timestamp}.log')
        
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler(log_filename),
                logging.StreamHandler()  # Also log to console
            ]
        )
        self.logger = logging.getLogger(__name__)
        self.log_filename = log_filename
        
        # Exclusion tracking
        self.exclusion_stats = {
            'total_raw_records': 0,
            'processed_batches': 0,
            'exclusions': {
                'missing_coordinates': 0,
                'invalid_coordinates': 0,
                'missing_datetime': 0,
                'invalid_datetime': 0,
                'duplicate_full_record': 0,
                'data_quality_too_low': 0,
                'h3_indexing_failed': 0
            },
            'successful_inserts': 0,
            'batch_failures': 0
        }
        
        # Philadelphia data validation rules
        self.valid_districts = [str(i) for i in range(1, 36)] + ['99']  # Districts 1-35 + default '99'
        # Updated UCR codes based on actual data (including 1100, 1200, etc.)
        self.valid_ucr_codes = ['100', '200', '300', '400', '500', '600', '700', '800', '900', 
                               '1100', '1200', '1300', '1400', '1500', '1600', '1700', '1800', '1900']
        
        # Philadelphia coordinate bounds
        self.philly_bounds = {
            'lat_min': 39.867, 'lat_max': 40.138,
            'lng_min': -75.280, 'lng_max': -74.955
        }
    
    def log_to_file(self, message: str):
        """Log message to processing log file with timestamp."""
        timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        log_message = f"[{timestamp}] {message}\n"
        
        # Write to current log file
        if hasattr(self, 'log_filename') and self.log_filename:
            try:
                with open(self.log_filename, 'a', encoding='utf-8') as log_file:
                    log_file.write(log_message)
                    log_file.flush()
            except Exception as e:
                self.logger.error(f"Failed to write to log file: {e}")
        
        # Also log to console
        self.logger.info(f"FILE_LOG: {message}")
    
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
            
            -- H3 spatial indexing (resolutions 1-15)
            h3_res_1 VARCHAR(16),
            h3_res_2 VARCHAR(16),
            h3_res_3 VARCHAR(16),
            h3_res_4 VARCHAR(16),
            h3_res_5 VARCHAR(16),
            h3_res_6 VARCHAR(16),
            h3_res_7 VARCHAR(16),
            h3_res_8 VARCHAR(16),
            h3_res_9 VARCHAR(16),
            h3_res_10 VARCHAR(16),
            h3_res_11 VARCHAR(16),
            h3_res_12 VARCHAR(16),
            h3_res_13 VARCHAR(16),
            h3_res_14 VARCHAR(16),
            h3_res_15 VARCHAR(16),
            
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
            INDEX idx_h3_res10 (h3_res_10),
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
            # Handle multiple datetime formats including timezone
            datetime_str = str(row['dispatch_date_time'])
            # Try with timezone first
            try:
                datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S+00:00')
            except ValueError:
                # Try without timezone
                datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S')
        except (ValueError, TypeError):
            return False, 'invalid_datetime'
        
        # Note: Removed missing_crime_type and invalid_district exclusions
        # These will be handled with defaults in prepare_clean_record()
        
        return True, 'valid'
    
    def detect_duplicates(self, df: pd.DataFrame) -> pd.DataFrame:
        """Detect and mark full duplicate records (every field must match)."""
        duplicates_found = 0
        
        # Get all relevant columns for comparison (exclude id and exclusion_reason)
        comparison_columns = [col for col in df.columns if col not in ['id', 'exclusion_reason']]
        
        # Check for complete duplicates - every field must match to be considered duplicate
        full_duplicates = df.duplicated(subset=comparison_columns, keep='first')
        duplicates_found += full_duplicates.sum()
        df.loc[full_duplicates, 'exclusion_reason'] = 'duplicate_full_record'
        
        self.logger.info(f"Identified {duplicates_found} complete duplicate records")
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
        """Add H3 spatial indexes for resolutions 1-15."""
        # Initialize all H3 fields to None to ensure consistent dictionary keys
        h3_indexes = {}
        for res in range(1, 16):
            h3_indexes[f'h3_res_{res}'] = None
        
        try:
            lat, lng = float(row['lat']), float(row['lng'])
            
            # Generate H3 indexes for resolutions 1-15
            for res in range(1, 16):
                h3_index = h3.latlng_to_cell(lat, lng, res)
                h3_indexes[f'h3_res_{res}'] = h3_index
                
        except Exception as e:
            self.logger.warning(f"Failed to generate H3 indexes: {e}")
            # H3 fields remain None - record will still be processed
            
        return h3_indexes
    
    def prepare_clean_record(self, row: pd.Series, batch_id: str) -> Dict:
        """Prepare a cleaned record for insertion."""
        try:
            # Parse datetime - handle timezone format
            datetime_str = str(row['dispatch_date_time'])
            try:
                # Try with timezone first
                incident_dt = datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S+00:00')
            except ValueError:
                # Try without timezone
                incident_dt = datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S')
            
            # Generate H3 indexes
            h3_indexes = self.add_h3_indexes(row)
            
            # Calculate data quality
            quality_score = self.calculate_data_quality_score(row)
            
            # Create incident ID
            incident_id = f"{row['cartodb_id']}_{row['objectid']}" if pd.notna(row['cartodb_id']) and pd.notna(row['objectid']) else str(uuid.uuid4())
            
            # Handle missing crime type with default
            ucr_code = str(row['ucr_general']) if pd.notna(row['ucr_general']) and str(row['ucr_general']).strip() != '' else '900'  # Default to 'unknown'
            
            # Handle missing/invalid district with default
            district = str(row['dc_dist']) if pd.notna(row['dc_dist']) and str(row['dc_dist']) in self.valid_districts else '99'  # Default district
            
            clean_record = {
                'raw_incident_ids': json.dumps([int(row['id'])]),
                'processing_batch_id': batch_id,
                'incident_id': incident_id,
                'cartodb_id': int(row['cartodb_id']) if pd.notna(row['cartodb_id']) else None,
                'objectid': int(row['objectid']) if pd.notna(row['objectid']) else None,
                'dc_key': str(row['dc_key']) if pd.notna(row['dc_key']) else None,
                'dc_dist': district,
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
                'ucr_general': ucr_code,
                'crime_category': self.get_crime_category(ucr_code),
                'crime_description': str(row['text_general_code']) if pd.notna(row['text_general_code']) else None,
                'severity_level': self.get_severity_level(ucr_code),
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
            '700': 'Quality of Life', '800': 'Quality of Life', '900': 'Unknown'
        }
        return category_map.get(ucr_code[:1] + '00', 'Unknown')
    
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
            '900': 1   # Unknown - least severe (default)
        }
        return severity_map.get(ucr_code[:1] + '00', 1)
    
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
            h3_res_1, h3_res_2, h3_res_3, h3_res_4, h3_res_5, h3_res_6, h3_res_7, h3_res_8, h3_res_9, h3_res_10,
            h3_res_11, h3_res_12, h3_res_13, h3_res_14, h3_res_15,
            data_quality_score, duplicate_group_id, is_duplicate, is_valid
        ) VALUES (
            %(raw_incident_ids)s, %(processing_batch_id)s, %(incident_id)s, %(cartodb_id)s, %(objectid)s, %(dc_key)s,
            %(dc_dist)s, %(psa)s, %(location_block)s, %(lat)s, %(lng)s, %(coordinate_quality)s,
            %(incident_datetime)s, %(incident_date)s, %(incident_hour)s, %(incident_month)s, %(incident_year)s, %(day_of_week)s,
            %(ucr_general)s, %(crime_category)s, %(crime_description)s, %(severity_level)s,
            %(h3_res_1)s, %(h3_res_2)s, %(h3_res_3)s, %(h3_res_4)s, %(h3_res_5)s, %(h3_res_6)s, %(h3_res_7)s, %(h3_res_8)s, %(h3_res_9)s, %(h3_res_10)s,
            %(h3_res_11)s, %(h3_res_12)s, %(h3_res_13)s, %(h3_res_14)s, %(h3_res_15)s,
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
            # Log error but continue processing instead of raising
            error_msg = f"Error inserting clean records: {e}"
            self.logger.error(error_msg)
            self.log_to_file(f"BATCH_INSERT_ERROR: {error_msg}")
            self.exclusion_stats['batch_failures'] += 1
            
            # Try INSERT IGNORE approach for duplicate handling
            try:
                self.logger.info("Attempting INSERT IGNORE for duplicate handling...")
                insert_ignore_sql = insert_sql.replace("INSERT INTO", "INSERT IGNORE INTO")
                cursor.executemany(insert_ignore_sql, clean_records)
                connection.commit()
                inserted_count = cursor.rowcount
                self.exclusion_stats['successful_inserts'] += inserted_count
                self.logger.info(f"✅ Inserted {inserted_count} clean incidents using INSERT IGNORE")
                self.log_to_file(f"RECOVERY_SUCCESS: Inserted {inserted_count} records using INSERT IGNORE")
            except Error as recovery_error:
                recovery_error_msg = f"Recovery insertion also failed: {recovery_error}"
                self.logger.error(recovery_error_msg)
                self.log_to_file(f"RECOVERY_FAILURE: {recovery_error_msg}")
                # Continue processing even if this batch fails completely
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
• Duplicate Full Record:   Records that are complete duplicates (all fields match)
• Data Quality Too Low:    Records failing quality score threshold (calculated but not used for exclusion)
• H3 Indexing Failed:      Records that couldn't be spatially indexed (processed with NULL H3 values)

NOTE: Missing crime types and invalid districts are now handled with defaults:
• Missing UCR codes default to '900' (Unknown, least severe)
• Invalid districts default to '99' (Unknown district)

DATA QUALITY ASSESSMENT:
-----------------------
Records meeting all validation criteria: {total_included:,}
Data completeness rate: {(total_included/total_processed*100):.1f}%
Primary exclusion reason: {sorted_exclusions[0][0].replace('_', ' ').title() if sorted_exclusions else 'N/A'}

{'='*80}
"""
        return report

    def generate_and_save_audit_report(self) -> Dict:
        """Generate comprehensive audit report and save to files."""
        # Generate the report text
        report_text = self.generate_exclusion_report()
        
        # Create audit data structure
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        audit_data = {
            'report_timestamp': datetime.now().isoformat(),
            'processing_summary': {
                'total_raw_records': self.exclusion_stats['total_raw_records'],
                'successfully_transformed': self.exclusion_stats['successful_inserts'], 
                'excluded_records': sum(self.exclusion_stats['exclusions'].values()),
                'processing_batches': self.exclusion_stats['processed_batches'],
                'batch_failures': self.exclusion_stats['batch_failures']
            },
            'exclusion_breakdown': dict(self.exclusion_stats['exclusions']),
            'processing_rates': {
                'success_rate': (self.exclusion_stats['successful_inserts'] / self.exclusion_stats['total_raw_records'] * 100) if self.exclusion_stats['total_raw_records'] > 0 else 0,
                'exclusion_rate': (sum(self.exclusion_stats['exclusions'].values()) / self.exclusion_stats['total_raw_records'] * 100) if self.exclusion_stats['total_raw_records'] > 0 else 0
            }
        }
        
        # Save text report
        text_report_filename = os.path.join(self.logs_dir, f'audit_report_{timestamp}.txt')
        with open(text_report_filename, 'w') as f:
            f.write(report_text)
        
        # Save JSON audit data
        json_report_filename = os.path.join(self.logs_dir, f'audit_data_{timestamp}.json')
        with open(json_report_filename, 'w') as f:
            json.dump(audit_data, f, indent=2)
        
        self.logger.info(f"📄 Audit reports saved:")
        self.logger.info(f"   Text Report: {text_report_filename}")
        self.logger.info(f"   JSON Data: {json_report_filename}")
        self.logger.info(f"   Processing Log: {self.log_filename}")
        
        return {
            'report_text': report_text,
            'audit_data': audit_data,
            'files_created': {
                'text_report': text_report_filename,
                'json_data': json_report_filename,
                'processing_log': self.log_filename
            },
            'total_raw': self.exclusion_stats['total_raw_records'],
            'total_processed': self.exclusion_stats['total_raw_records'],
            'total_clean': self.exclusion_stats['successful_inserts'],
            'total_excluded': sum(self.exclusion_stats['exclusions'].values()),
            'exclusion_breakdown': dict(self.exclusion_stats['exclusions']),
            'batches_processed': self.exclusion_stats['processed_batches'],
            'batch_failures': self.exclusion_stats['batch_failures']
        }
    
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
                
                # Process batch with error handling
                try:
                    batch_stats = self.process_batch(connection, batch_df, batch_id)
                    self.exclusion_stats['processed_batches'] += 1
                    
                    self.logger.info(
                        f"Batch {batch_num}: {batch_stats['valid_records']} valid, "
                        f"{batch_stats['excluded_records']} excluded, "
                        f"{batch_stats['clean_records_created']} inserted"
                    )
                except Exception as batch_error:
                    error_msg = f"Batch {batch_num} processing failed: {batch_error}"
                    self.logger.error(error_msg)
                    self.log_to_file(f"BATCH_PROCESSING_ERROR: {error_msg}")
                    self.exclusion_stats['batch_failures'] += 1
                    # Continue to next batch instead of stopping
                
                offset += batch_size
            
            # Generate and save audit report
            self.logger.info("Transform processing complete! Generating audit report...")
            results = self.generate_and_save_audit_report()
            print(results['report_text'])
            
            return results
            
        except Exception as e:
            error_msg = f"Transform processing failed: {e}"
            self.logger.error(error_msg)
            self.log_to_file(f"CRITICAL_ERROR: {error_msg}")
            # Generate partial audit report even on failure
            try:
                results = self.generate_audit_report()
                self.logger.info("Generated partial audit report despite processing failure")
                return results
            except Exception as audit_error:
                self.logger.error(f"Failed to generate audit report: {audit_error}")
                return {'error': str(e), 'audit_generation_failed': str(audit_error)}
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
    parser.add_argument('--mysql-database', default='stlouisintegration_dev', help='MySQL database')
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