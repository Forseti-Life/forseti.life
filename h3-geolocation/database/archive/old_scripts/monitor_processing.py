#!/usr/bin/env python3
"""
Enhanced Transform Processor Monitor

Comprehensive monitoring of the enhanced transform processor combining:
- Real-time database status queries
- Process monitoring and detection
- Error scanning and analysis
- Log file monitoring and audit report detection
- Processing rate calculation and ETA estimation
"""

import mysql.connector
import time
from datetime import datetime
import os
import sys
import subprocess
import glob
import re

# Add path for processor
sys.path.append(os.path.dirname(os.path.abspath(__file__)))
from enhanced_transform_processor_v2 import EnhancedTransformProcessor

def check_process_running():
    """Check if the enhanced transform processor is running."""
    try:
        result = subprocess.run(['ps', 'aux'], capture_output=True, text=True)
        # Look for the enhanced processor
        return 'enhanced_transform_processor_v2.py' in result.stdout
    except Exception as e:
        print(f"Warning: Could not check process status: {e}")
        return False

def scan_for_errors():
    """Scan recent log files for errors and processing issues."""
    processing_logs_dir = "/workspaces/stlouisintegration.com/h3-geolocation/database/processing_logs"
    
    if not os.path.exists(processing_logs_dir):
        print("📊 No processing logs directory found")
        return
    
    # Find recent log files
    log_pattern = os.path.join(processing_logs_dir, "*processing*.log")
    log_files = glob.glob(log_pattern)
    
    if not log_files:
        print("📊 No processing log files found")
        return
    
    # Get the most recent log file
    latest_log = max(log_files, key=os.path.getmtime)
    
    try:
        with open(latest_log, 'r') as f:
            content = f.read()
        
        # Count different error types
        batch_errors = len(re.findall(r'BATCH_PROCESSING_ERROR|BATCH_INSERT_ERROR', content))
        recovery_successes = len(re.findall(r'RECOVERY_SUCCESS', content))
        recovery_failures = len(re.findall(r'RECOVERY_FAILURE', content))
        critical_errors = len(re.findall(r'CRITICAL_ERROR|ERROR:', content))
        
        print("📊 ERROR SCAN RESULTS:")
        print(f"   - Batch Processing Errors: {batch_errors}")
        print(f"   - Recovery Successes: {recovery_successes}")
        print(f"   - Recovery Failures: {recovery_failures}")
        print(f"   - Critical Errors: {critical_errors}")
        
        if batch_errors > 0 or critical_errors > 0:
            print("\n🚨 RECENT ERROR DETAILS:")
            error_lines = [line for line in content.split('\n') if 'ERROR' in line or 'FAILURE' in line]
            for line in error_lines[-5:]:  # Show last 5 error lines
                print(f"   {line}")
                
    except Exception as e:
        print(f"📊 Could not scan log file: {e}")

def check_audit_reports():
    """Check for generated audit reports."""
    processing_logs_dir = "/workspaces/stlouisintegration.com/h3-geolocation/database/processing_logs"
    
    if not os.path.exists(processing_logs_dir):
        return
    
    # Look for audit reports
    audit_pattern = os.path.join(processing_logs_dir, "*audit*")
    audit_files = glob.glob(audit_pattern)
    
    print("\n📋 AUDIT REPORTS:")
    if audit_files:
        for audit_file in sorted(audit_files, key=os.path.getmtime, reverse=True)[:3]:
            stat = os.stat(audit_file)
            size = stat.st_size
            modified = datetime.fromtimestamp(stat.st_mtime)
            print(f"   - {os.path.basename(audit_file)} ({size:,} bytes, {modified.strftime('%Y-%m-%d %H:%M:%S')})")
    else:
        print("   - No audit reports found")

def monitor_processing():
    """Comprehensive monitoring of the processing progress."""
    processor = EnhancedTransformProcessor()
    
    print("="*80)
    print("COMPREHENSIVE ENHANCED TRANSFORM PROCESSOR MONITOR")
    print("="*80)
    print("Monitoring combines database queries, process detection, and error analysis")
    print("Press Ctrl+C to stop monitoring")
    print("="*80)
    
    previous_count = 0
    start_time = datetime.now()
    monitoring_cycle = 0
    
    try:
        while True:
            monitoring_cycle += 1
            
            # Check if process is running
            process_running = check_process_running()
            
            print(f"\n📊 Status Update #{monitoring_cycle} - {datetime.now().strftime('%H:%M:%S')}")
            print(f"🔍 Process Status: {'✅ Running' if process_running else '❌ Not Running'}")
            
            try:
                # Get current database status
                status = processor.get_processing_status()
                current_count = status['total_transform_records']
                
                # Calculate processing rate
                records_processed = current_count - previous_count
                
                # Display database status
                print(f"� Database Status:")
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
                    print("✅ Database Processing Complete!")
                    break
                    
            except Exception as e:
                print(f"⚠️ Database Status Error: {e}")
            
            # Every 3rd cycle (90 seconds), do comprehensive analysis
            if monitoring_cycle % 3 == 0:
                print(f"\n🔍 COMPREHENSIVE ANALYSIS (Cycle {monitoring_cycle}):")
                scan_for_errors()
                check_audit_reports()
            
            # If process stopped but database shows incomplete, alert user
            if not process_running and status['completion_percentage'] < 100:
                print(f"\n⚠️  ALERT: Processing stopped but {status['records_remaining']:,} records remain!")
                print("   Consider restarting the processor to continue.")
                break
                
            # Wait before next check
            print(f"\n⏳ Next update in 30 seconds...")
            time.sleep(30)
            
    except KeyboardInterrupt:
        print("\n\n⏹️  Monitoring stopped by user")
        # Final status on exit
        try:
            final_status = processor.get_processing_status()
            print(f"📊 Final Status: {final_status['completion_percentage']:.2f}% complete")
            print(f"   Records Remaining: {final_status['records_remaining']:,}")
        except:
            pass
    except Exception as e:
        print(f"\n❌ Monitor error: {e}")

def quick_status():
    """Get a quick status update without continuous monitoring."""
    processor = EnhancedTransformProcessor()
    process_running = check_process_running()
    
    print("="*60)
    print("QUICK STATUS CHECK")
    print("="*60)
    print(f"Process Status: {'✅ Running' if process_running else '❌ Not Running'}")
    
    try:
        status = processor.get_processing_status()
        print(f"Completion: {status['completion_percentage']:.2f}%")
        print(f"Records Processed: {status['total_transform_records']:,}")
        print(f"Records Remaining: {status['records_remaining']:,}")
    except Exception as e:
        print(f"Database Status Error: {e}")
    
    scan_for_errors()
    check_audit_reports()

if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description='Enhanced Transform Processor Monitor')
    parser.add_argument('--quick', action='store_true', 
                       help='Quick status check without continuous monitoring')
    parser.add_argument('--errors-only', action='store_true',
                       help='Only scan for errors and exit')
    
    args = parser.parse_args()
    
    if args.errors_only:
        print("🔍 ERROR SCAN ONLY")
        print("="*40)
        scan_for_errors()
    elif args.quick:
        quick_status()
    else:
        monitor_processing()