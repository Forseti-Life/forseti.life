#!/usr/bin/env python3
"""
Enhanced AmISafe Transform Processor with Integrated Validation Reporting

This enhanced processor combines transform processing with comprehensive validation
reporting to provide consistent, detailed analysis of data processing operations.

Key Features:
- Complete record accounting and reconciliation
- Integrated validation testing and reporting
- Comprehensive exclusion analysis
- Automated report generation
- Processing status tracking
- Recovery recommendations

Usage:
    python enhanced_transform_processor_v2.py --continue-processing
    python enhanced_transform_processor_v2.py --full-reprocess
    python enhanced_transform_processor_v2.py --validation-only
    python enhanced_transform_processor_v2.py --status-check
"""

import mysql.connector
from mysql.connector import Error
import pandas as pd
import numpy as np
import json
import uuid
import h3
import logging
from datetime import datetime, date
from typing import Dict, List, Tuple, Optional
from collections import defaultdict
import argparse
import sys
import os
import time

# Add parent directories to path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from h3_framework import H3GeolocationFramework

# Add validation tools path
sys.path.append(os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'tests', 'data_validation'))
from record_accounting_tool import RecordAccountingTool

class EnhancedTransformProcessor:
    """
    Enhanced transform processor with integrated validation reporting.
    Provides complete record accounting and comprehensive processing reports.
    """
    
    def __init__(self, 
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = 'drupal_secure_password',
                 mysql_database: str = 'theoryofconspiracies_dev',
                 reports_dir: str = None):
        """Initialize the enhanced transform processor."""
        
        self.mysql_config = {
            'host': mysql_host,
            'user': mysql_user,
            'password': mysql_password,
            'database': mysql_database,
            'autocommit': True
        }
        
        # Reports directory
        if reports_dir is None:
            reports_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'reports')
        self.reports_dir = reports_dir
        self.processing_reports_dir = os.path.join(reports_dir, 'processing')
        self.validation_reports_dir = os.path.join(reports_dir, 'validation')
        
        # Ensure reports directories exist
        os.makedirs(self.processing_reports_dir, exist_ok=True)
        os.makedirs(self.validation_reports_dir, exist_ok=True)
        
        # Setup logging
        log_filename = os.path.join(self.processing_reports_dir, f'transform_processing_{datetime.now().strftime("%Y%m%d_%H%M%S")}.log')
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler(log_filename),
                logging.StreamHandler(sys.stdout)
            ]
        )
        self.logger = logging.getLogger(__name__)
        
        # Initialize H3 framework
        self.h3_framework = H3GeolocationFramework()
        
        # Initialize record accounting tool
        self.accounting_tool = RecordAccountingTool(mysql_host, mysql_user, mysql_password, mysql_database)
        
        # Philadelphia geographic bounds
        self.philly_bounds = {
            'lat_min': 39.867, 'lat_max': 40.138,
            'lng_min': -75.280, 'lng_max': -74.955
        }
        
        # Valid districts
        self.valid_districts = {
            '1', '2', '3', '5', '6', '7', '8', '9', '12', '14', '15', '16', 
            '17', '18', '19', '22', '24', '25', '26', '35', '39'
        }
        
        # Processing statistics
        self.processing_stats = {
            'start_time': None,
            'end_time': None,
            'total_raw_records': 0,
            'records_processed': 0,
            'records_inserted': 0,
            'processing_batches': 0,
            'batch_failures': 0,
            'last_processed_id': 0,
            'exclusions': defaultdict(int),
            'processing_errors': []
        }
    
    def connect_to_mysql(self) -> mysql.connector.MySQLConnection:
        """Create MySQL connection."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            if connection.is_connected():
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def get_processing_status(self) -> Dict:
        """Get current processing status and statistics."""
        connection = self.connect_to_mysql()
        
        try:
            cursor = connection.cursor(dictionary=True)
            
            # Get raw layer count
            cursor.execute("SELECT COUNT(*) as total FROM amisafe_raw_incidents")
            total_raw = cursor.fetchone()['total']
            
            # Get transform layer count
            cursor.execute("SELECT COUNT(*) as total FROM amisafe_clean_incidents")
            total_transform = cursor.fetchone()['total']
            
            # Get processing range
            cursor.execute("""
                SELECT 
                    MIN(CAST(JSON_UNQUOTE(JSON_EXTRACT(raw_incident_ids, '$[0]')) AS UNSIGNED)) as min_processed_id,
                    MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(raw_incident_ids, '$[0]')) AS UNSIGNED)) as max_processed_id
                FROM amisafe_clean_incidents
                WHERE raw_incident_ids IS NOT NULL
            """)
            processing_range = cursor.fetchone()
            
            # Get batch information
            cursor.execute("""
                SELECT 
                    COUNT(DISTINCT processing_batch_id) as total_batches,
                    MIN(processed_at) as first_batch_time,
                    MAX(processed_at) as last_batch_time
                FROM amisafe_clean_incidents
            """)
            batch_info = cursor.fetchone()
            
            cursor.close()
            
            status = {
                'total_raw_records': total_raw,
                'total_transform_records': total_transform,
                'records_remaining': total_raw - total_transform,
                'completion_percentage': round((total_transform / total_raw * 100), 2) if total_raw > 0 else 0,
                'processing_range': {
                    'min_id': processing_range['min_processed_id'] if processing_range['min_processed_id'] else 0,
                    'max_id': processing_range['max_processed_id'] if processing_range['max_processed_id'] else 0
                },
                'batch_summary': {
                    'total_batches': batch_info['total_batches'] if batch_info['total_batches'] else 0,
                    'first_processed': batch_info['first_batch_time'].isoformat() if batch_info['first_batch_time'] else None,
                    'last_processed': batch_info['last_batch_time'].isoformat() if batch_info['last_batch_time'] else None
                }
            }
            
            return status
            
        finally:
            connection.close()
    
    def validate_record(self, row: pd.Series) -> Tuple[bool, str]:
        """Validate a single record using comprehensive validation logic."""
        
        # Check coordinates - Missing
        if pd.isna(row.get('lat')) or pd.isna(row.get('lng')):
            return False, 'missing_coordinates'
        
        # Check coordinates - Invalid format
        try:
            lat, lng = float(row['lat']), float(row['lng'])
        except (ValueError, TypeError):
            return False, 'invalid_coordinates_format'
        
        # Check coordinate bounds for Philadelphia
        if not (self.philly_bounds['lat_min'] <= lat <= self.philly_bounds['lat_max'] and
                self.philly_bounds['lng_min'] <= lng <= self.philly_bounds['lng_max']):
            return False, 'coordinates_outside_bounds'
        
        # Check datetime - Missing
        if pd.isna(row.get('dispatch_date_time')):
            return False, 'missing_datetime'
        
        # Check datetime - Invalid format
        try:
            datetime_str = str(row['dispatch_date_time'])
            # Try with timezone first
            try:
                datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S+00:00')
            except ValueError:
                # Try without timezone
                datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S')
        except (ValueError, TypeError):
            return False, 'invalid_datetime_format'
        
        # Check crime type
        if pd.isna(row.get('ucr_general')) or str(row['ucr_general']).strip() == '':
            return False, 'missing_crime_type'
        
        # Check district
        if pd.isna(row.get('dc_dist')) or str(row['dc_dist']) not in self.valid_districts:
            return False, 'invalid_district'
        
        return True, 'valid'
    
    def fetch_raw_incidents(self, connection, batch_size: int = 10000, start_id: int = 1) -> pd.DataFrame:
        """Fetch raw incidents starting from a specific ID."""
        query = """
        SELECT id, cartodb_id, objectid, dc_key, dc_dist, psa, 
               dispatch_date_time, lat, lng, location_block,
               ucr_general, text_general_code
        FROM amisafe_raw_incidents 
        WHERE processing_status = 'raw' AND id >= %s
        ORDER BY id
        LIMIT %s
        """
        
        try:
            df = pd.read_sql(query, connection, params=(start_id, batch_size))
            self.logger.info(f"Fetched {len(df)} raw incidents (starting from ID: {start_id})")
            return df
        except Exception as e:
            self.logger.error(f"Error fetching raw incidents: {e}")
            return pd.DataFrame()
    
    def detect_duplicates(self, df: pd.DataFrame) -> pd.DataFrame:
        """Detect and mark duplicates in the dataframe."""
        df = df.copy()
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
            lat, lng = float(row['lat']), float(row['lng'])
            h3_indexes = {}
            for resolution in range(6, 11):
                try:
                    h3_index = h3.latlng_to_cell(lat, lng, resolution)
                    h3_indexes[f'h3_res_{resolution}'] = h3_index
                except Exception as e:
                    self.logger.warning(f"H3 indexing failed for resolution {resolution}: {e}")
                    h3_indexes[f'h3_res_{resolution}'] = None
            
            # Calculate data quality score
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
                'lat': lat,
                'lng': lng,
                'coordinate_quality': 'HIGH',
                'incident_datetime': incident_dt,
                'incident_date': incident_dt.date(),
                'incident_hour': incident_dt.hour,
                'incident_month': incident_dt.month,
                'incident_year': incident_dt.year,
                'day_of_week': incident_dt.weekday() + 1,
                'ucr_general': str(row['ucr_general']),
                'crime_category': self.get_crime_category(str(row['ucr_general'])),
                'crime_description': str(row['text_general_code']) if pd.notna(row['text_general_code']) else None,
                'severity_level': self.get_severity_level(str(row['ucr_general'])),
                'data_quality_score': quality_score,
                'duplicate_group_id': None,
                'is_duplicate': row.get('exclusion_reason', '').startswith('duplicate'),
                'is_valid': True
            }
            
            # Add H3 indexes
            clean_record.update(h3_indexes)
            
            return clean_record
            
        except Exception as e:
            self.logger.error(f"Error preparing clean record: {e}")
            self.processing_stats['processing_errors'].append(f"prepare_clean_record: {str(e)}")
            return None
    
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
    
    def get_crime_category(self, ucr_code: str) -> str:
        """Map UCR code to crime category."""
        category_map = {
            '100': 'Violent Crime', '200': 'Violent Crime', '300': 'Violent Crime', '400': 'Violent Crime',
            '500': 'Property Crime', '600': 'Property Crime', '700': 'Property Crime', '800': 'Property Crime',
            '900': 'Other'
        }
        return category_map.get(ucr_code[:1] + '00', 'Other')
    
    def get_severity_level(self, ucr_code: str) -> int:
        """Map UCR code to severity level (1-5)."""
        severity_map = {
            '100': 5, '200': 4, '300': 3, '400': 2,
            '500': 3, '600': 2, '700': 2, '800': 1,
            '900': 3
        }
        return severity_map.get(ucr_code[:1] + '00', 3)
    
    def process_batch(self, connection, batch_df: pd.DataFrame, batch_id: str) -> Dict:
        """Process a single batch of raw incidents."""
        batch_stats = {
            'total_records': len(batch_df),
            'valid_records': 0,
            'excluded_records': 0,
            'exclusion_reasons': defaultdict(int),
            'clean_records_created': 0,
            'insertion_failures': 0
        }
        
        if batch_df.empty:
            return batch_stats
        
        # Add exclusion reason column
        batch_df['exclusion_reason'] = 'valid'
        
        # Validate each record
        for idx, row in batch_df.iterrows():
            is_valid, reason = self.validate_record(row)
            if not is_valid:
                batch_df.at[idx, 'exclusion_reason'] = reason
                batch_stats['exclusion_reasons'][reason] += 1
                self.processing_stats['exclusions'][reason] += 1
        
        # Detect duplicates in valid records
        valid_mask = batch_df['exclusion_reason'] == 'valid'
        if valid_mask.sum() > 0:
            batch_df = self.detect_duplicates(batch_df)
            
            # Update exclusion stats for duplicates found
            for idx, row in batch_df.iterrows():
                if row['exclusion_reason'] != 'valid' and idx in batch_df[valid_mask].index:
                    batch_stats['exclusion_reasons'][row['exclusion_reason']] += 1
                    self.processing_stats['exclusions'][row['exclusion_reason']] += 1
        
        # Count final exclusions
        excluded_mask = batch_df['exclusion_reason'] != 'valid'
        batch_stats['excluded_records'] = excluded_mask.sum()
        batch_stats['valid_records'] = len(batch_df) - batch_stats['excluded_records']
        
        # Prepare clean records from valid data
        valid_records = batch_df[~excluded_mask]
        clean_records = []
        
        for _, row in valid_records.iterrows():
            clean_record = self.prepare_clean_record(row, batch_id)
            if clean_record:
                clean_records.append(clean_record)
            else:
                batch_stats['insertion_failures'] += 1
        
        # Insert clean records
        if clean_records:
            inserted_count = self.insert_clean_records(connection, clean_records, batch_id)
            batch_stats['clean_records_created'] = inserted_count
            self.processing_stats['records_inserted'] += inserted_count
        
        return batch_stats
    
    def insert_clean_records(self, connection, clean_records: List[Dict], batch_id: str) -> int:
        """Insert clean records into amisafe_clean_incidents table."""
        if not clean_records:
            return 0
        
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
            inserted_count = cursor.rowcount
            cursor.close()
            return inserted_count
        except Error as e:
            self.logger.error(f"Error inserting clean records: {e}")
            self.processing_stats['batch_failures'] += 1
            self.processing_stats['processing_errors'].append(f"insert_clean_records: {str(e)}")
            return 0
    
    def continue_processing(self, batch_size: int = 10000) -> Dict:
        """Continue processing from where it left off."""
        self.logger.info("🔄 Starting enhanced transform processing (continue mode)...")
        self.processing_stats['start_time'] = datetime.now()
        
        connection = None
        try:
            connection = self.connect_to_mysql()
            
            # Get current processing status
            status = self.get_processing_status()
            self.processing_stats['total_raw_records'] = status['total_raw_records']
            
            start_id = status['processing_range']['max_id'] + 1 if status['processing_range']['max_id'] > 0 else 1
            
            self.logger.info(f"📊 Processing Status:")
            self.logger.info(f"   Total Raw Records: {status['total_raw_records']:,}")
            self.logger.info(f"   Already Processed: {status['total_transform_records']:,}")
            self.logger.info(f"   Remaining: {status['records_remaining']:,}")
            self.logger.info(f"   Completion: {status['completion_percentage']:.1f}%")
            self.logger.info(f"   Continuing from ID: {start_id:,}")
            
            if status['records_remaining'] == 0:
                self.logger.info("✅ All records already processed!")
                return self.generate_final_report()
            
            batch_num = status['batch_summary']['total_batches']
            
            while True:
                # Fetch next batch
                batch_df = self.fetch_raw_incidents(connection, batch_size, start_id)
                if batch_df.empty:
                    break
                
                batch_num += 1
                batch_id = f"enhanced_batch_{datetime.now().strftime('%Y%m%d_%H%M%S')}_{batch_num}"
                
                self.logger.info(f"🔄 Processing batch {batch_num} ({len(batch_df)} records, starting ID: {start_id:,})...")
                
                # Process batch
                batch_stats = self.process_batch(connection, batch_df, batch_id)
                self.processing_stats['processing_batches'] += 1
                self.processing_stats['records_processed'] += batch_stats['total_records']
                
                # Update last processed ID
                self.processing_stats['last_processed_id'] = int(batch_df['id'].max())
                start_id = self.processing_stats['last_processed_id'] + 1
                
                # Log batch results
                self.logger.info(
                    f"✅ Batch {batch_num}: {batch_stats['clean_records_created']} inserted, "
                    f"{batch_stats['excluded_records']} excluded, "
                    f"{batch_stats['insertion_failures']} failed preparations"
                )
                
                # Progress update
                current_total = status['total_transform_records'] + self.processing_stats['records_inserted']
                current_progress = (current_total / status['total_raw_records'] * 100) if status['total_raw_records'] > 0 else 0
                
                if batch_num % 10 == 0:  # Progress update every 10 batches
                    self.logger.info(f"📈 Progress Update: {current_total:,}/{status['total_raw_records']:,} ({current_progress:.1f}%)")
            
        except Exception as e:
            self.logger.error(f"Transform processing failed: {e}")
            self.processing_stats['processing_errors'].append(f"main_processing: {str(e)}")
            raise
        finally:
            if connection and connection.is_connected():
                connection.close()
        
        self.processing_stats['end_time'] = datetime.now()
        return self.generate_final_report()
    
    def generate_final_report(self) -> Dict:
        """Generate comprehensive final processing report."""
        self.logger.info("📊 Generating comprehensive processing report...")
        
        # Get final status
        final_status = self.get_processing_status()
        
        # Generate validation report
        validation_report = self.accounting_tool.generate_complete_record_accounting(batch_size=100000)
        
        # Calculate processing duration
        duration = None
        if self.processing_stats['start_time'] and self.processing_stats['end_time']:
            duration = self.processing_stats['end_time'] - self.processing_stats['start_time']
        
        # Compile comprehensive report
        comprehensive_report = {
            'report_timestamp': datetime.now().isoformat(),
            'processing_session': {
                'start_time': self.processing_stats['start_time'].isoformat() if self.processing_stats['start_time'] else None,
                'end_time': self.processing_stats['end_time'].isoformat() if self.processing_stats['end_time'] else None,
                'duration_seconds': duration.total_seconds() if duration else None,
                'records_processed_this_session': self.processing_stats['records_processed'],
                'records_inserted_this_session': self.processing_stats['records_inserted'],
                'batches_processed_this_session': self.processing_stats['processing_batches'],
                'batch_failures': self.processing_stats['batch_failures'],
                'processing_errors': self.processing_stats['processing_errors']
            },
            'final_status': final_status,
            'validation_analysis': validation_report,
            'recommendations': self.generate_recommendations(final_status, validation_report)
        }
        
        # Save reports
        self.save_reports(comprehensive_report)
        
        return comprehensive_report
    
    def generate_recommendations(self, status: Dict, validation: Dict) -> List[str]:
        """Generate processing recommendations."""
        recommendations = []
        
        if status['completion_percentage'] < 100:
            recommendations.append(f"Continue processing remaining {status['records_remaining']:,} records ({100-status['completion_percentage']:.1f}% remaining)")
        
        if self.processing_stats['batch_failures'] > 0:
            recommendations.append(f"Investigate {self.processing_stats['batch_failures']} batch failures for data integrity")
        
        if len(self.processing_stats['processing_errors']) > 0:
            recommendations.append(f"Review {len(self.processing_stats['processing_errors'])} processing errors")
        
        # Add validation-based recommendations if available
        if 'recommendations' in validation:
            recommendations.extend(validation['recommendations'][:3])  # Top 3
        
        if not recommendations:
            recommendations.append("Processing completed successfully with no issues identified")
        
        return recommendations
    
    def save_reports(self, comprehensive_report: Dict):
        """Save comprehensive reports to files."""
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        
        # Save JSON report
        json_filename = os.path.join(self.processing_reports_dir, f'enhanced_transform_report_{timestamp}.json')
        with open(json_filename, 'w') as f:
            json.dump(comprehensive_report, f, indent=2, default=str)
        
        # Save human-readable report
        markdown_filename = os.path.join(self.processing_reports_dir, f'enhanced_transform_report_{timestamp}.md')
        markdown_content = self.generate_markdown_report(comprehensive_report)
        with open(markdown_filename, 'w') as f:
            f.write(markdown_content)
        
        # Save validation report separately
        if 'validation_analysis' in comprehensive_report:
            validation_filename = os.path.join(self.validation_reports_dir, f'validation_analysis_{timestamp}.json')
            with open(validation_filename, 'w') as f:
                json.dump(comprehensive_report['validation_analysis'], f, indent=2, default=str)
        
        self.logger.info(f"📄 Reports saved:")
        self.logger.info(f"   JSON Report: {json_filename}")
        self.logger.info(f"   Markdown Report: {markdown_filename}")
        self.logger.info(f"   Validation Report: {validation_filename}")
    
    def generate_markdown_report(self, report: Dict) -> str:
        """Generate human-readable markdown report."""
        status = report['final_status']
        session = report['processing_session']
        
        duration_str = "N/A"
        if session['duration_seconds']:
            duration_str = f"{session['duration_seconds']:.1f} seconds"
        
        markdown = f"""# Enhanced Transform Processing Report

**Generated:** {report['report_timestamp']}

## 📊 Processing Summary

| Metric | Value |
|--------|-------|
| **Total Raw Records** | {status['total_raw_records']:,} |
| **Total Transform Records** | {status['total_transform_records']:,} |
| **Completion Percentage** | {status['completion_percentage']:.1f}% |
| **Records Remaining** | {status['records_remaining']:,} |

## ⏱️ Session Information

| Metric | Value |
|--------|-------|
| **Session Duration** | {duration_str} |
| **Records Processed This Session** | {session['records_processed_this_session']:,} |
| **Records Inserted This Session** | {session['records_inserted_this_session']:,} |
| **Batches Processed** | {session['batches_processed_this_session']:,} |
| **Batch Failures** | {session['batch_failures']:,} |

## 🔍 Processing Range

| Metric | Value |
|--------|-------|
| **Min Processed ID** | {status['processing_range']['min_id']:,} |
| **Max Processed ID** | {status['processing_range']['max_id']:,} |
| **Total Batches** | {status['batch_summary']['total_batches']:,} |

## 💡 Recommendations

"""
        
        for i, rec in enumerate(report['recommendations'], 1):
            markdown += f"{i}. {rec}\n"
        
        if session['processing_errors']:
            markdown += f"\n## ⚠️ Processing Errors\n\n"
            for i, error in enumerate(session['processing_errors'], 1):
                markdown += f"{i}. {error}\n"
        
        markdown += f"\n---\n*Report generated by Enhanced Transform Processor*"
        
        return markdown

def main():
    """Main execution function."""
    parser = argparse.ArgumentParser(description='Enhanced AmISafe Transform Processor')
    parser.add_argument('--continue-processing', action='store_true', help='Continue processing from last checkpoint')
    parser.add_argument('--full-reprocess', action='store_true', help='Reprocess all records (clears transform layer)')
    parser.add_argument('--validation-only', action='store_true', help='Run validation analysis only')
    parser.add_argument('--status-check', action='store_true', help='Check current processing status')
    parser.add_argument('--batch-size', type=int, default=10000, help='Batch size for processing')
    parser.add_argument('--mysql-host', default='127.0.0.1', help='MySQL host')
    parser.add_argument('--mysql-user', default='drupal_user', help='MySQL user')
    parser.add_argument('--mysql-password', default='drupal_secure_password', help='MySQL password')
    parser.add_argument('--mysql-database', default='theoryofconspiracies_dev', help='MySQL database')
    
    args = parser.parse_args()
    
    # Initialize enhanced processor
    processor = EnhancedTransformProcessor(
        mysql_host=args.mysql_host,
        mysql_user=args.mysql_user,
        mysql_password=args.mysql_password,
        mysql_database=args.mysql_database
    )
    
    print("="*100)
    print("ENHANCED AMISAFE TRANSFORM PROCESSOR")
    print("="*100)
    print(f"Processing Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*100)
    
    try:
        if args.status_check:
            # Status check only
            status = processor.get_processing_status()
            print(f"\n📊 CURRENT PROCESSING STATUS")
            print(f"Total Raw Records: {status['total_raw_records']:,}")
            print(f"Transform Records: {status['total_transform_records']:,}")
            print(f"Completion: {status['completion_percentage']:.1f}%")
            print(f"Records Remaining: {status['records_remaining']:,}")
            
        elif args.validation_only:
            # Validation analysis only
            print(f"\n🔍 RUNNING VALIDATION ANALYSIS")
            validation_report = processor.accounting_tool.generate_complete_record_accounting()
            # Save validation report
            timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
            validation_filename = os.path.join(processor.validation_reports_dir, f'validation_only_{timestamp}.json')
            with open(validation_filename, 'w') as f:
                json.dump(validation_report, f, indent=2, default=str)
            print(f"✅ Validation report saved: {validation_filename}")
            
        elif args.continue_processing or not any([args.full_reprocess, args.validation_only, args.status_check]):
            # Continue processing (default)
            print(f"\n🔄 CONTINUING TRANSFORM PROCESSING")
            results = processor.continue_processing(batch_size=args.batch_size)
            
            print(f"\n✅ PROCESSING COMPLETE")
            print(f"Final Status: {results['final_status']['completion_percentage']:.1f}% complete")
            print(f"Records Processed This Session: {results['processing_session']['records_processed_this_session']:,}")
            print(f"Reports saved to: {processor.processing_reports_dir}")
        
        elif args.full_reprocess:
            print("⚠️  Full reprocessing not implemented yet - use continue-processing mode")
            
    except Exception as e:
        print(f"\n❌ ERROR: {e}")
        sys.exit(1)
    
    print("\n" + "="*100)

if __name__ == "__main__":
    main()