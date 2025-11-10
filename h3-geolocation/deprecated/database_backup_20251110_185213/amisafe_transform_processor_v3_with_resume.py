#!/usr/bin/env python3
"""
AmISafe Transform Processor v3 - WITH INTERRUPT/RESUME LOGIC
Enhanced version with checkpoint/resume capabilities for large dataset processing
"""

# Add these methods to the AmISafeTransformProcessor class:

class AmISafeTransformProcessor:
    
    def __init__(self, ...):
        # Existing initialization...
        
        # Add resume support
        self.checkpoint_file = os.path.join(self.logs_dir, 'processing_checkpoint.json')
        self.resume_from_checkpoint = False
    
    def save_checkpoint(self, offset: int, batch_num: int, processed_count: int):
        """Save processing checkpoint for resume capability."""
        checkpoint_data = {
            'timestamp': datetime.now().isoformat(),
            'offset': offset,
            'batch_num': batch_num,
            'processed_count': processed_count,
            'exclusion_stats': self.exclusion_stats
        }
        
        try:
            with open(self.checkpoint_file, 'w') as f:
                json.dump(checkpoint_data, f, indent=2)
            self.logger.info(f"Checkpoint saved: offset={offset}, batch={batch_num}")
        except Exception as e:
            self.logger.warning(f"Failed to save checkpoint: {e}")
    
    def load_checkpoint(self) -> Tuple[int, int, int]:
        """Load checkpoint data for resume. Returns (offset, batch_num, processed_count)."""
        if not os.path.exists(self.checkpoint_file):
            return 0, 0, 0
        
        try:
            with open(self.checkpoint_file, 'r') as f:
                checkpoint_data = json.load(f)
            
            offset = checkpoint_data.get('offset', 0)
            batch_num = checkpoint_data.get('batch_num', 0)
            processed_count = checkpoint_data.get('processed_count', 0)
            
            # Restore exclusion stats if available
            if 'exclusion_stats' in checkpoint_data:
                self.exclusion_stats = checkpoint_data['exclusion_stats']
            
            self.logger.info(f"Checkpoint loaded: resuming from offset={offset}, batch={batch_num}")
            self.resume_from_checkpoint = True
            return offset, batch_num, processed_count
            
        except Exception as e:
            self.logger.error(f"Failed to load checkpoint: {e}")
            return 0, 0, 0
    
    def clear_checkpoint(self):
        """Clear checkpoint file after successful completion."""
        try:
            if os.path.exists(self.checkpoint_file):
                os.remove(self.checkpoint_file)
                self.logger.info("Checkpoint file cleared after successful completion")
        except Exception as e:
            self.logger.warning(f"Failed to clear checkpoint: {e}")
    
    def get_last_processed_id(self, connection) -> int:
        """Get the highest raw incident ID that has been processed."""
        try:
            cursor = connection.cursor()
            # Extract max raw incident ID from the JSON array in raw_incident_ids
            cursor.execute("""
                SELECT MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(raw_incident_ids, '$[0]')) AS UNSIGNED)) as max_id
                FROM amisafe_clean_incidents
                WHERE raw_incident_ids IS NOT NULL
            """)
            result = cursor.fetchone()
            cursor.close()
            
            max_id = result[0] if result and result[0] is not None else 0
            self.logger.info(f"Last processed raw incident ID: {max_id}")
            return max_id
            
        except Exception as e:
            self.logger.warning(f"Failed to get last processed ID: {e}")
            return 0
    
    def process_raw_to_clean_with_resume(self, batch_size: int = 10000) -> Dict:
        """Enhanced processing function with checkpoint/resume capability."""
        self.logger.info("Starting Raw → Transform layer processing with resume support...")
        
        connection = None
        try:
            connection = self.connect_to_mysql()
            self.ensure_clean_incidents_table(connection)
            
            # Load checkpoint or determine resume point
            checkpoint_offset, checkpoint_batch_num, checkpoint_processed = self.load_checkpoint()
            
            # Alternative: use database state to determine resume point
            if not self.resume_from_checkpoint:
                last_processed_id = self.get_last_processed_id(connection)
                if last_processed_id > 0:
                    self.logger.info(f"Found existing processed data, resuming after ID {last_processed_id}")
                    # Adjust query to start after last processed ID
                    checkpoint_offset = last_processed_id
            
            # Count remaining raw records
            cursor = connection.cursor()
            if checkpoint_offset > 0:
                cursor.execute("""
                    SELECT COUNT(*) FROM amisafe_raw_incidents 
                    WHERE processing_status = 'raw' AND id > %s
                """, (checkpoint_offset,))
            else:
                cursor.execute("SELECT COUNT(*) FROM amisafe_raw_incidents WHERE processing_status = 'raw'")
            
            remaining_count = cursor.fetchone()[0]
            cursor.close()
            
            self.logger.info(f"Processing {remaining_count:,} remaining raw incidents...")
            if checkpoint_offset > 0:
                self.logger.info(f"Resuming from offset: {checkpoint_offset}")
            
            offset = checkpoint_offset
            batch_num = checkpoint_batch_num
            
            while True:
                # Modified fetch query for resume
                if offset > 0:
                    batch_df = self.fetch_raw_incidents_from_id(connection, batch_size, offset)
                else:
                    batch_df = self.fetch_raw_incidents(connection, batch_size, offset)
                
                if batch_df.empty:
                    break
                
                batch_num += 1
                batch_id = f"batch_{datetime.now().strftime('%Y%m%d_%H%M%S')}_{batch_num}"
                
                self.logger.info(f"Processing batch {batch_num} ({len(batch_df)} records)...")
                
                try:
                    batch_stats = self.process_batch(connection, batch_df, batch_id)
                    self.exclusion_stats['processed_batches'] += 1
                    
                    # Save checkpoint every 10 batches
                    if batch_num % 10 == 0:
                        current_max_id = batch_df['id'].max() if not batch_df.empty else offset
                        self.save_checkpoint(current_max_id, batch_num, self.exclusion_stats['successful_inserts'])
                    
                    self.logger.info(
                        f"Batch {batch_num}: {batch_stats['valid_records']} valid, "
                        f"{batch_stats['excluded_records']} excluded, "
                        f"{batch_stats['clean_records_created']} inserted"
                    )
                    
                except Exception as batch_error:
                    error_msg = f"Batch {batch_num} processing failed: {batch_error}"
                    self.logger.error(error_msg)
                    # Save checkpoint before continuing
                    current_max_id = batch_df['id'].max() if not batch_df.empty else offset
                    self.save_checkpoint(current_max_id, batch_num, self.exclusion_stats['successful_inserts'])
                    self.exclusion_stats['batch_failures'] += 1
                
                # Update offset for next iteration
                if not batch_df.empty:
                    offset = batch_df['id'].max()
                else:
                    offset += batch_size
            
            # Clear checkpoint on successful completion
            self.clear_checkpoint()
            
            self.logger.info("Transform processing complete! Generating audit report...")
            results = self.generate_and_save_audit_report()
            return results
            
        except KeyboardInterrupt:
            self.logger.info("Processing interrupted by user - checkpoint saved")
            if 'offset' in locals() and 'batch_num' in locals():
                self.save_checkpoint(offset, batch_num, self.exclusion_stats['successful_inserts'])
            raise
        except Exception as e:
            self.logger.error(f"Transform processing failed: {e}")
            # Save checkpoint even on failure
            if 'offset' in locals() and 'batch_num' in locals():
                self.save_checkpoint(offset, batch_num, self.exclusion_stats['successful_inserts'])
            raise
        finally:
            if connection and connection.is_connected():
                connection.close()
                self.logger.info("MySQL connection closed")
    
    def fetch_raw_incidents_from_id(self, connection, batch_size: int, start_id: int) -> pd.DataFrame:
        """Fetch raw incidents starting from a specific ID (for resume)."""
        query = """
        SELECT id, cartodb_id, objectid, dc_key, dc_dist, psa, 
               dispatch_date_time, lat, lng, location_block,
               ucr_general, text_general_code
        FROM amisafe_raw_incidents 
        WHERE processing_status = 'raw' AND id > %s
        ORDER BY id
        LIMIT %s
        """
        
        try:
            df = pd.read_sql(query, connection, params=(start_id, batch_size))
            self.logger.info(f"Fetched {len(df)} raw incidents (starting after ID: {start_id})")
            return df
        except Exception as e:
            self.logger.error(f"Error fetching raw incidents from ID {start_id}: {e}")
            return pd.DataFrame()

# Add to argument parser:
def main():
    parser = argparse.ArgumentParser(description='AmISafe Transform Processor v3 - With Resume')
    # ... existing arguments ...
    parser.add_argument('--resume', action='store_true', help='Resume from checkpoint if available')
    parser.add_argument('--start-from-id', type=int, help='Start processing from specific raw incident ID')
    
    args = parser.parse_args()
    
    processor = AmISafeTransformProcessor(...)
    
    if args.resume or args.start_from_id:
        if args.start_from_id:
            # Manual resume from specific ID
            processor.logger.info(f"Starting from specified ID: {args.start_from_id}")
        stats = processor.process_raw_to_clean_with_resume(batch_size=args.batch_size)
    else:
        # Regular processing
        stats = processor.process_raw_to_clean(batch_size=args.batch_size)