#!/usr/bin/env python3
"""
Enhanced Transform Processor Monitor

Monitor the progress of the enhanced transform processor
and provide real-time status updates.
"""

import mysql.connector
import time
from datetime import datetime
import os
import sys

# Add path for processor
sys.path.append(os.path.dirname(os.path.abspath(__file__)))
from enhanced_transform_processor_v2 import EnhancedTransformProcessor

def monitor_processing():
    """Monitor the processing progress."""
    processor = EnhancedTransformProcessor()
    
    print("="*80)
    print("ENHANCED TRANSFORM PROCESSOR MONITOR")
    print("="*80)
    print("Monitoring processing progress every 30 seconds...")
    print("Press Ctrl+C to stop monitoring")
    print("="*80)
    
    previous_count = 0
    start_time = datetime.now()
    
    try:
        while True:
            # Get current status
            status = processor.get_processing_status()
            current_count = status['total_transform_records']
            
            # Calculate processing rate
            records_processed = current_count - previous_count
            elapsed_time = datetime.now() - start_time
            
            # Display status
            print(f"\n📊 Status Update - {datetime.now().strftime('%H:%M:%S')}")
            print(f"   Total Transform Records: {current_count:,}")
            print(f"   Records Remaining: {status['records_remaining']:,}")
            print(f"   Completion: {status['completion_percentage']:.2f}%")
            
            if records_processed > 0:
                rate_per_minute = (records_processed / 30) * 60  # Records per minute
                print(f"   Recent Processing Rate: {rate_per_minute:.0f} records/minute")
                
                # Estimate time remaining
                if rate_per_minute > 0:
                    minutes_remaining = status['records_remaining'] / rate_per_minute
                    hours_remaining = minutes_remaining / 60
                    print(f"   Estimated Time Remaining: {hours_remaining:.1f} hours")
            
            previous_count = current_count
            
            # Check if processing is complete
            if status['completion_percentage'] >= 100:
                print("✅ Processing Complete!")
                break
                
            # Wait before next check
            time.sleep(30)
            
    except KeyboardInterrupt:
        print("\n\n⏹️  Monitoring stopped by user")
    except Exception as e:
        print(f"\n❌ Monitor error: {e}")

if __name__ == "__main__":
    monitor_processing()