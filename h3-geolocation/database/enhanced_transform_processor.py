#!/usr/bin/env python3
"""
Enhanced AmISafe Transform Processor with Integrated Validation Reporting

This enhanced processor combines the transform processing logic with comprehensive
validation testing and report generation. It provides:

1. Complete record accounting and validation
2. Detailed exclusion analysis and classification
3. Data quality metrics and assessment
4. H3 spatial indexing validation
5. Standardized reporting to reports/data_processing directory

Features:
- Integrated validation framework
- Real-time processing metrics
- Comprehensive exclusion tracking
- Automated report generation
- Recovery opportunity identification
- Data quality scoring and assessment

Usage:
    python enhanced_transform_processor.py --full-processing
    python enhanced_transform_processor.py --validation-only
    python enhanced_transform_processor.py --resume-processing
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
from typing import Dict, List, Tuple, Optional, Any
from collections import defaultdict
import sys
import os
import argparse

# Add validation tools to path
sys.path.append(os.path.join(os.path.dirname(__file__), 'tests', 'data_validation'))

class EnhancedTransformProcessor:
    """
    Enhanced transform processor with integrated validation and reporting.
    Combines transform processing with comprehensive validation framework.
    """
    
    def __init__(self, 
                 mysql_host: str = '127.0.0.1',
                 mysql_user: str = 'drupal_user',
                 mysql_password: str = 'drupal_secure_password',
                 mysql_database: str = 'theoryofconspiracies_dev'):
        """Initialize the enhanced transform processor."""
        
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
        
        # Philadelphia geographic bounds
        self.philly_bounds = {
            'lat_min': 39.867, 'lat_max': 40.138,
            'lng_min': -75.280, 'lng_max': -74.955
        }
        
        # Valid Philadelphia police districts
        self.valid_districts = {
            '1', '2', '3', '5', '6', '7', '8', '9', '12', '14', '15', '16', 
            '17', '18', '19', '22', '24', '25', '26', '35', '39'
        }
        
        # Processing statistics
        self.processing_stats = {
            'session_start': datetime.now(),
            'total_raw_records': 0,
            'records_processed': 0,
            'records_validated': 0,
            'records_transformed': 0,
            'batches_processed': 0,
            'batch_failures': 0,
            'validation_details': defaultdict(int),
            'exclusion_details': defaultdict(int),
            'data_quality_metrics': {},
            'h3_indexing_stats': defaultdict(int),
            'processing_errors': []
        }
        
        # Report configuration
        self.reports_dir = os.path.join(os.path.dirname(__file__), 'reports', 'data_processing')
        os.makedirs(self.reports_dir, exist_ok=True)
    
    def connect_to_mysql(self) -> mysql.connector.MySQLConnection:
        """Create MySQL connection with error handling."""
        try:
            connection = mysql.connector.connect(**self.mysql_config)
            if connection.is_connected():
                return connection
        except Error as e:
            self.logger.error(f"Error connecting to MySQL: {e}")
            raise
    
    def validate_record_with_details(self, row: pd.Series) -> Tuple[bool, str, Dict]:
        """
        Comprehensive record validation with detailed classification.
        Returns (is_valid, exclusion_reason, validation_details).
        """
        validation_details = {
            'coordinate_status': 'unknown',
            'datetime_status': 'unknown',
            'crime_type_status': 'unknown',
            'district_status': 'unknown',
            'data_quality_score': 0.0
        }
        
        # Check coordinates - Missing
        if pd.isna(row.get('lat')) or pd.isna(row.get('lng')):
            validation_details['coordinate_status'] = 'missing'
            return False, 'missing_coordinates', validation_details
        
        # Check coordinates - Invalid format
        try:
            lat, lng = float(row['lat']), float(row['lng'])
            validation_details['coordinate_status'] = 'valid_format'
        except (ValueError, TypeError):
            validation_details['coordinate_status'] = 'invalid_format'
            return False, 'invalid_coordinates_format', validation_details
        
        # Check coordinate bounds for Philadelphia
        if not (self.philly_bounds['lat_min'] <= lat <= self.philly_bounds['lat_max'] and
                self.philly_bounds['lng_min'] <= lng <= self.philly_bounds['lng_max']):
            validation_details['coordinate_status'] = 'outside_bounds'
            return False, 'coordinates_outside_bounds', validation_details
        else:
            validation_details['coordinate_status'] = 'valid'
        
        # Check datetime - Missing
        if pd.isna(row.get('dispatch_date_time')):
            validation_details['datetime_status'] = 'missing'
            return False, 'missing_datetime', validation_details
        
        # Check datetime - Invalid format
        try:
            datetime_str = str(row['dispatch_date_time'])
            # Try with timezone first
            try:
                datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S+00:00')
                validation_details['datetime_status'] = 'valid_with_timezone'
            except ValueError:
                # Try without timezone
                datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S')
                validation_details['datetime_status'] = 'valid_without_timezone'
        except (ValueError, TypeError):
            validation_details['datetime_status'] = 'invalid_format'
            return False, 'invalid_datetime_format', validation_details
        
        # Check crime type
        if pd.isna(row.get('ucr_general')) or str(row['ucr_general']).strip() == '':
            validation_details['crime_type_status'] = 'missing'
            return False, 'missing_crime_type', validation_details
        else:
            validation_details['crime_type_status'] = 'valid'
        
        # Check district
        if pd.isna(row.get('dc_dist')) or str(row['dc_dist']) not in self.valid_districts:
            validation_details['district_status'] = 'invalid'
            return False, 'invalid_district', validation_details
        else:
            validation_details['district_status'] = 'valid'
        
        # Calculate data quality score
        validation_details['data_quality_score'] = self.calculate_data_quality_score(row)
        
        return True, 'valid', validation_details
    
    def calculate_data_quality_score(self, row: pd.Series) -> float:
        """Calculate comprehensive data quality score (0.0 - 1.0)."""
        score = 1.0
        
        # Coordinate quality (30% weight)
        if pd.isna(row.get('lat')) or pd.isna(row.get('lng')):
            score -= 0.3
        
        # Temporal quality (20% weight)
        if pd.isna(row.get('dispatch_date_time')):
            score -= 0.2
        
        # Location description quality (10% weight)
        if pd.isna(row.get('location_block')) or str(row.get('location_block')).strip() == '':
            score -= 0.1
        
        # Crime description quality (10% weight)
        if pd.isna(row.get('text_general_code')) or str(row.get('text_general_code')).strip() == '':
            score -= 0.1
        
        # District validation (20% weight)
        if pd.isna(row.get('dc_dist')) or str(row.get('dc_dist')) not in self.valid_districts:
            score -= 0.2
        
        # UCR code validation (10% weight)
        if pd.isna(row.get('ucr_general')):
            score -= 0.1
        
        return max(0.0, score)
    
    def add_h3_indexes_with_validation(self, row: pd.Series) -> Dict[str, Any]:
        """Add H3 spatial indexes with validation tracking."""
        h3_result = {
            'h3_res_6': None, 'h3_res_7': None, 'h3_res_8': None, 
            'h3_res_9': None, 'h3_res_10': None,
            'h3_indexing_success': False,
            'h3_error': None
        }
        
        try:
            lat, lng = float(row['lat']), float(row['lng'])
            
            # Generate H3 indexes for multiple resolutions
            for resolution in range(6, 11):
                try:
                    h3_index = h3.latlng_to_cell(lat, lng, resolution)
                    h3_result[f'h3_res_{resolution}'] = h3_index
                    self.processing_stats['h3_indexing_stats'][f'resolution_{resolution}_success'] += 1
                except Exception as e:
                    self.processing_stats['h3_indexing_stats'][f'resolution_{resolution}_failure'] += 1
                    h3_result['h3_error'] = str(e)
            
            h3_result['h3_indexing_success'] = True
            
        except Exception as e:
            h3_result['h3_error'] = str(e)
            self.processing_stats['h3_indexing_stats']['total_failures'] += 1
            self.logger.warning(f"H3 indexing failed for record: {e}")
        
        return h3_result
    
    def detect_duplicates_with_tracking(self, df: pd.DataFrame) -> pd.DataFrame:
        """Enhanced duplicate detection with detailed tracking."""
        df = df.copy()
        df['duplicate_reason'] = 'not_duplicate'
        
        self.logger.info(f"🔍 Starting duplicate detection on {len(df)} records")
        
        # Track duplicate counts
        duplicate_stats = {
            'cartodb_id_duplicates': 0,
            'objectid_duplicates': 0,
            'composite_duplicates': 0
        }
        
        # 1. Check for cartodb_id duplicates
        cartodb_dupes = df.duplicated(subset=['cartodb_id'], keep='first')
        duplicate_stats['cartodb_id_duplicates'] = cartodb_dupes.sum()
        df.loc[cartodb_dupes, 'duplicate_reason'] = 'duplicate_cartodb_id'
        
        # 2. Check for objectid duplicates (excluding already marked duplicates)
        remaining_after_cartodb = df[~cartodb_dupes]
        objectid_dupes_in_remaining = remaining_after_cartodb.duplicated(subset=['objectid'], keep='first')
        duplicate_stats['objectid_duplicates'] = objectid_dupes_in_remaining.sum()
        df.loc[remaining_after_cartodb[objectid_dupes_in_remaining].index, 'duplicate_reason'] = 'duplicate_objectid'
        
        # 3. Check for composite duplicates (lat/lng + datetime + crime_type)
        remaining_after_objectid = remaining_after_cartodb[~objectid_dupes_in_remaining]
        composite_dupes_in_remaining = remaining_after_objectid.duplicated(
            subset=['lat', 'lng', 'dispatch_date_time', 'ucr_general'], keep='first'
        )
        duplicate_stats['composite_duplicates'] = composite_dupes_in_remaining.sum()
        df.loc[remaining_after_objectid[composite_dupes_in_remaining].index, 'duplicate_reason'] = 'duplicate_composite'
        
        # Update processing stats
        for key, count in duplicate_stats.items():
            self.processing_stats['exclusion_details'][key] += count
        
        total_duplicates = sum(duplicate_stats.values())
        remaining_unique = len(df) - total_duplicates
        
        self.logger.info(f"📊 Duplicate detection complete: {total_duplicates:,} duplicates, {remaining_unique:,} unique records")
        
        return df
    
    def prepare_clean_record_enhanced(self, row: pd.Series, batch_id: str, validation_details: Dict) -> Optional[Dict]:
        """Enhanced clean record preparation with comprehensive error handling."""
        try:
            # Parse datetime with enhanced error handling
            datetime_str = str(row['dispatch_date_time'])
            try:
                incident_dt = datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S+00:00')
            except ValueError:
                try:
                    incident_dt = datetime.strptime(datetime_str, '%Y-%m-%d %H:%M:%S')
                except ValueError:
                    self.processing_stats['processing_errors'].append({
                        'error_type': 'datetime_parse_failed',
                        'raw_id': row.get('id'),
                        'datetime_value': datetime_str
                    })
                    return None
            
            # Generate H3 indexes with validation
            h3_result = self.add_h3_indexes_with_validation(row)
            
            # Create incident ID with enhanced logic
            incident_id = f"{row['cartodb_id']}_{row['objectid']}" if pd.notna(row['cartodb_id']) and pd.notna(row['objectid']) else str(uuid.uuid4())
            
            # Build comprehensive clean record
            clean_record = {
                # Processing metadata
                'raw_incident_ids': json.dumps([int(row['id'])]),
                'processing_batch_id': batch_id,
                'incident_id': incident_id,
                
                # Original identifiers
                'cartodb_id': int(row['cartodb_id']) if pd.notna(row['cartodb_id']) else None,
                'objectid': int(row['objectid']) if pd.notna(row['objectid']) else None,
                'dc_key': str(row['dc_key']) if pd.notna(row['dc_key']) else None,
                
                # Geographic data
                'dc_dist': str(row['dc_dist']),
                'psa': str(row['psa']) if pd.notna(row['psa']) else None,
                'location_block': str(row['location_block']) if pd.notna(row['location_block']) else None,
                'lat': float(row['lat']),
                'lng': float(row['lng']),
                'coordinate_quality': 'HIGH',
                
                # Temporal data
                'incident_datetime': incident_dt,
                'incident_date': incident_dt.date(),
                'incident_hour': incident_dt.hour,
                'incident_month': incident_dt.month,
                'incident_year': incident_dt.year,
                'day_of_week': incident_dt.weekday() + 1,
                
                # Crime classification
                'ucr_general': str(row['ucr_general']),
                'crime_category': self.get_crime_category(str(row['ucr_general'])),
                'crime_description': str(row['text_general_code']) if pd.notna(row['text_general_code']) else None,
                'severity_level': self.get_severity_level(str(row['ucr_general'])),
                
                # Quality and validation metadata
                'data_quality_score': validation_details.get('data_quality_score', 0.0),
                'duplicate_group_id': None,
                'is_duplicate': False,
                'is_valid': True
            }
            
            # Add H3 indexes
            for resolution in range(6, 11):
                clean_record[f'h3_res_{resolution}'] = h3_result.get(f'h3_res_{resolution}')
            
            return clean_record
            
        except Exception as e:
            self.processing_stats['processing_errors'].append({
                'error_type': 'record_preparation_failed',
                'raw_id': row.get('id'),
                'error_message': str(e)
            })
            self.logger.error(f"Error preparing clean record for ID {row.get('id')}: {e}")
            return None
    
    def get_crime_category(self, ucr_code: str) -> str:
        """Enhanced crime categorization."""
        if not ucr_code or ucr_code.strip() == '':
            return 'Unknown'
        
        code = ucr_code.strip()[:1]
        crime_categories = {
            '1': 'Violent Crime',
            '2': 'Property Crime', 
            '3': 'Drug Crime',
            '4': 'Public Order',
            '5': 'Traffic',
            '6': 'Other',
            '7': 'Administrative',
            '8': 'Domestic',
            '9': 'Fraud'
        }
        return crime_categories.get(code, 'Other')
    
    def get_severity_level(self, ucr_code: str) -> int:
        """Enhanced severity level calculation."""
        if not ucr_code or ucr_code.strip() == '':
            return 3
        
        code = ucr_code.strip()[:1]
        severity_map = {
            '1': 5,  # Violent crimes - highest severity
            '2': 3,  # Property crimes - medium severity
            '3': 4,  # Drug crimes - high severity
            '4': 2,  # Public order - low severity
            '5': 1,  # Traffic - lowest severity
            '6': 3,  # Other - medium severity
            '7': 1,  # Administrative - lowest severity
            '8': 4,  # Domestic - high severity
            '9': 3   # Fraud - medium severity
        }
        return severity_map.get(code, 3)
    
    def process_batch_enhanced(self, connection, batch_df: pd.DataFrame, batch_id: str) -> Dict:
        """Enhanced batch processing with comprehensive tracking."""
        batch_stats = {
            'batch_id': batch_id,
            'total_records': len(batch_df),
            'validation_results': defaultdict(int),
            'exclusion_breakdown': defaultdict(int),
            'duplicate_analysis': defaultdict(int),
            'clean_records_created': 0,
            'processing_errors': 0,
            'data_quality_summary': {}
        }
        
        self.logger.info(f"🔄 Processing batch {batch_id} with {len(batch_df)} records")
        
        # Step 1: Comprehensive validation
        validation_results = []
        for idx, row in batch_df.iterrows():
            is_valid, reason, details = self.validate_record_with_details(row)
            validation_results.append({
                'index': idx,
                'is_valid': is_valid,
                'reason': reason,
                'details': details
            })
            batch_stats['validation_results'][reason] += 1
        
        # Step 2: Filter valid records
        valid_indices = [r['index'] for r in validation_results if r['is_valid']]
        valid_df = batch_df.loc[valid_indices].copy()
        
        self.logger.info(f"📊 Validation complete: {len(valid_df)} valid records from {len(batch_df)}")
        
        # Step 3: Duplicate detection on valid records
        if len(valid_df) > 0:
            valid_df = self.detect_duplicates_with_tracking(valid_df)
            
            # Count duplicates
            for reason in ['duplicate_cartodb_id', 'duplicate_objectid', 'duplicate_composite']:
                count = (valid_df['duplicate_reason'] == reason).sum()
                batch_stats['duplicate_analysis'][reason] = count
            
            # Filter non-duplicate records
            final_valid_df = valid_df[valid_df['duplicate_reason'] == 'not_duplicate']
        else:
            final_valid_df = pd.DataFrame()
        
        # Step 4: Prepare clean records
        clean_records = []
        if len(final_valid_df) > 0:
            for idx, row in final_valid_df.iterrows():
                # Get validation details for this record
                validation_detail = next((r['details'] for r in validation_results if r['index'] == idx), {})
                
                clean_record = self.prepare_clean_record_enhanced(row, batch_id, validation_detail)
                if clean_record:
                    clean_records.append(clean_record)
                else:
                    batch_stats['processing_errors'] += 1
        
        # Step 5: Insert clean records
        if clean_records:
            try:
                self.insert_clean_records(connection, clean_records, batch_id)
                batch_stats['clean_records_created'] = len(clean_records)
                self.processing_stats['records_transformed'] += len(clean_records)
            except Exception as e:
                self.logger.error(f"Failed to insert clean records: {e}")
                batch_stats['processing_errors'] += 1
                self.processing_stats['batch_failures'] += 1
        
        # Update global processing stats
        self.processing_stats['records_processed'] += len(batch_df)
        self.processing_stats['batches_processed'] += 1
        
        for reason, count in batch_stats['validation_results'].items():
            self.processing_stats['validation_details'][reason] += count
        
        self.logger.info(f"✅ Batch {batch_id} complete: {batch_stats['clean_records_created']} records created")
        
        return batch_stats
    
    def insert_clean_records(self, connection, clean_records: List[Dict], batch_id: str):
        """Insert clean records with enhanced error handling."""
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
            self.logger.info(f"✅ Inserted {len(clean_records)} clean records")
        except Error as e:
            self.logger.error(f"Error inserting clean records: {e}")
            raise
        finally:
            cursor.close()
    
    def generate_comprehensive_report(self) -> Dict:
        """Generate comprehensive processing and validation report."""
        session_duration = datetime.now() - self.processing_stats['session_start']
        
        report = {
            'report_metadata': {
                'generated_at': datetime.now().isoformat(),
                'session_start': self.processing_stats['session_start'].isoformat(),
                'session_duration_minutes': round(session_duration.total_seconds() / 60, 2),
                'processor_version': 'enhanced_v1.0'
            },
            'processing_summary': {
                'total_raw_records': self.processing_stats['total_raw_records'],
                'records_processed': self.processing_stats['records_processed'],
                'records_transformed': self.processing_stats['records_transformed'],
                'batches_processed': self.processing_stats['batches_processed'],
                'batch_failures': self.processing_stats['batch_failures'],
                'processing_rate_pct': round((self.processing_stats['records_processed'] / self.processing_stats['total_raw_records'] * 100), 2) if self.processing_stats['total_raw_records'] > 0 else 0,
                'transformation_rate_pct': round((self.processing_stats['records_transformed'] / self.processing_stats['records_processed'] * 100), 2) if self.processing_stats['records_processed'] > 0 else 0
            },
            'validation_analysis': {
                'validation_breakdown': dict(self.processing_stats['validation_details']),
                'exclusion_breakdown': dict(self.processing_stats['exclusion_details'])
            },
            'h3_indexing_analysis': dict(self.processing_stats['h3_indexing_stats']),
            'processing_errors': self.processing_stats['processing_errors'][:100],  # Limit to first 100 errors
            'recommendations': self.generate_recommendations()
        }
        
        return report
    
    def generate_recommendations(self) -> List[str]:
        """Generate processing recommendations based on analysis."""
        recommendations = []
        
        # Processing rate recommendations
        processing_rate = (self.processing_stats['records_processed'] / self.processing_stats['total_raw_records'] * 100) if self.processing_stats['total_raw_records'] > 0 else 0
        
        if processing_rate < 100:
            recommendations.append(f"Processing incomplete: {processing_rate:.1f}% of records processed. Consider resuming processing to complete remaining records.")
        
        # Validation rate recommendations
        valid_records = self.processing_stats['validation_details'].get('valid', 0)
        if self.processing_stats['records_processed'] > 0:
            validation_rate = (valid_records / self.processing_stats['records_processed']) * 100
            if validation_rate < 90:
                recommendations.append(f"Low validation rate: {validation_rate:.1f}%. Review exclusion patterns for data quality improvements.")
        
        # H3 indexing recommendations
        h3_failures = self.processing_stats['h3_indexing_stats'].get('total_failures', 0)
        if h3_failures > 0:
            recommendations.append(f"H3 indexing failures detected: {h3_failures} records. Review coordinate data quality.")
        
        # Error recommendations
        if len(self.processing_stats['processing_errors']) > 0:
            recommendations.append(f"Processing errors encountered: {len(self.processing_stats['processing_errors'])} errors. Review error log for patterns.")
        
        if not recommendations:
            recommendations.append("Processing completed successfully with no major issues identified.")
        
        return recommendations
    
    def save_report(self, report: Dict, report_type: str = 'processing_report') -> str:
        """Save report to data processing directory."""
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        filename = f"{report_type}_{timestamp}.json"
        filepath = os.path.join(self.reports_dir, filename)
        
        with open(filepath, 'w') as f:
            json.dump(report, f, indent=2, default=str)
        
        self.logger.info(f"📊 Report saved: {filepath}")
        return filepath
    
    def process_raw_to_clean_enhanced(self, batch_size: int = 10000, resume_from_offset: int = 0) -> Dict:
        """Enhanced main processing function with comprehensive reporting."""
        self.logger.info("🚀 Starting enhanced Raw → Transform processing with integrated validation...")
        
        connection = None
        try:
            connection = self.connect_to_mysql()
            
            # Get total record count
            cursor = connection.cursor()
            cursor.execute("SELECT COUNT(*) FROM amisafe_raw_incidents WHERE processing_status = 'raw'")
            total_count = cursor.fetchone()[0]
            self.processing_stats['total_raw_records'] = total_count
            cursor.close()
            
            self.logger.info(f"📊 Total raw records: {total_count:,}")
            self.logger.info(f"📊 Starting from offset: {resume_from_offset:,}")
            
            offset = resume_from_offset
            batch_num = 0
            
            while offset < total_count:
                # Fetch batch
                batch_df = self.fetch_raw_incidents(connection, batch_size, offset)
                if batch_df.empty:
                    break
                
                batch_num += 1
                batch_id = f"enhanced_batch_{datetime.now().strftime('%Y%m%d_%H%M%S')}_{batch_num}"
                
                self.logger.info(f"🔄 Processing batch {batch_num} ({len(batch_df)} records, offset: {offset:,})")
                
                # Process batch with enhanced tracking
                batch_stats = self.process_batch_enhanced(connection, batch_df, batch_id)
                
                progress_pct = ((offset + len(batch_df)) / total_count) * 100
                self.logger.info(f"📈 Progress: {progress_pct:.1f}% ({offset + len(batch_df):,}/{total_count:,})")
                
                offset += batch_size
            
            # Generate comprehensive report
            final_report = self.generate_comprehensive_report()
            
            # Save report
            report_path = self.save_report(final_report, 'enhanced_transform_processing')
            
            self.logger.info("✅ Enhanced transform processing complete!")
            
            return {
                'processing_complete': True,
                'report_path': report_path,
                'final_stats': final_report['processing_summary'],
                'recommendations': final_report['recommendations']
            }
            
        except Exception as e:
            self.logger.error(f"Enhanced transform processing failed: {e}")
            error_report = {
                'processing_complete': False,
                'error': str(e),
                'partial_stats': self.processing_stats
            }
            self.save_report(error_report, 'enhanced_transform_processing_error')
            raise
        finally:
            if connection and connection.is_connected():
                connection.close()
    
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
            return df
        except Exception as e:
            self.logger.error(f"Error fetching raw incidents: {e}")
            return pd.DataFrame()

def main():
    """Main execution function."""
    parser = argparse.ArgumentParser(description='Enhanced AmISafe Transform Processor with Validation')
    parser.add_argument('--mysql-host', default='127.0.0.1', help='MySQL host')
    parser.add_argument('--mysql-user', default='drupal_user', help='MySQL user')
    parser.add_argument('--mysql-password', default='drupal_secure_password', help='MySQL password')
    parser.add_argument('--mysql-database', default='theoryofconspiracies_dev', help='MySQL database')
    parser.add_argument('--batch-size', type=int, default=10000, help='Batch size for processing')
    parser.add_argument('--resume-from', type=int, default=0, help='Resume processing from offset')
    parser.add_argument('--full-processing', action='store_true', help='Run complete processing with validation')
    parser.add_argument('--validation-only', action='store_true', help='Run validation analysis only')
    
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
    print("WITH INTEGRATED VALIDATION REPORTING")
    print("="*100)
    print(f"Processing Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*100)
    
    try:
        if args.validation_only:
            print("🔍 Running validation analysis only...")
            # TODO: Implement validation-only mode
            print("Validation-only mode not yet implemented")
        else:
            print("🚀 Running enhanced transform processing...")
            results = processor.process_raw_to_clean_enhanced(
                batch_size=args.batch_size, 
                resume_from_offset=args.resume_from
            )
            
            print(f"\n✅ PROCESSING COMPLETE!")
            print(f"📊 Report saved to: {results['report_path']}")
            print(f"📈 Final statistics:")
            for key, value in results['final_stats'].items():
                print(f"  {key}: {value}")
            
            print(f"\n💡 Recommendations:")
            for i, rec in enumerate(results['recommendations'], 1):
                print(f"  {i}. {rec}")
        
    except Exception as e:
        print(f"❌ ERROR: {e}")
        sys.exit(1)
    
    print("\n" + "="*100)

if __name__ == "__main__":
    main()