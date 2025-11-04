#!/bin/bash

# Log monitoring script for transform processor - non-intrusive
LOG_FILE="/workspaces/stlouisintegration.com/h3-geolocation/database/processing_logs/transform_processing_20251104_150911.log"
PROCESSING_LOGS_DIR="/workspaces/stlouisintegration.com/h3-geolocation/database/processing_logs"

echo "🔍 BINGO LOG MONITORING INITIATED"
echo "Monitoring: $LOG_FILE"
echo "Scanning for completion and errors without interrupting processing..."
echo ""

# Function to check if processing is complete
check_completion() {
    if [[ -f "$LOG_FILE" ]]; then
        # Check for completion indicators
        if grep -q "COMPREHENSIVE_AUDIT_COMPLETE\|Transform processing completed\|Final audit report" "$LOG_FILE" 2>/dev/null; then
            return 0  # Complete
        fi
    fi
    return 1  # Not complete
}

# Function to scan for errors
scan_errors() {
    if [[ -f "$LOG_FILE" ]]; then
        echo "📊 ERROR SCAN RESULTS:"
        
        # Count different error types
        batch_errors=$(grep -c "BATCH_PROCESSING_ERROR\|BATCH_INSERT_ERROR" "$LOG_FILE" 2>/dev/null || echo "0")
        recovery_successes=$(grep -c "RECOVERY_SUCCESS" "$LOG_FILE" 2>/dev/null || echo "0")
        recovery_failures=$(grep -c "RECOVERY_FAILURE" "$LOG_FILE" 2>/dev/null || echo "0")
        critical_errors=$(grep -c "CRITICAL_ERROR" "$LOG_FILE" 2>/dev/null || echo "0")
        
        echo "   - Batch Processing Errors: $batch_errors"
        echo "   - Recovery Successes: $recovery_successes" 
        echo "   - Recovery Failures: $recovery_failures"
        echo "   - Critical Errors: $critical_errors"
        
        if [ "$batch_errors" -gt 0 ] || [ "$critical_errors" -gt 0 ]; then
            echo ""
            echo "🚨 DETAILED ERROR LOG:"
            grep "ERROR\|FAILURE" "$LOG_FILE" 2>/dev/null | tail -10
        fi
    else
        echo "📊 Log file not yet created or accessible"
    fi
}

# Function to check processing progress
check_progress() {
    if ps aux | grep -q "[a]misafe_transform_processor_v2.py"; then
        echo "✅ Transform processor is running"
        
        # Try to estimate progress from recent terminal output  
        if [[ -f "$LOG_FILE" ]]; then
            last_batch=$(grep -o "Batch [0-9]*:" "$LOG_FILE" 2>/dev/null | tail -1 | grep -o "[0-9]*" || echo "0")
            if [ "$last_batch" -gt 0 ]; then
                progress=$((last_batch * 100 / 441))  # Estimate: 4.4M records / 10K batch = 441 batches
                echo "📈 Estimated Progress: Batch $last_batch (~${progress}%)"
            fi
        fi
    else
        echo "🔍 Transform processor completed or stopped"
        return 1
    fi
    return 0
}

# Main monitoring loop
echo "Starting non-intrusive monitoring (will not interrupt processing)..."

# Initial scan
check_progress
scan_errors

# Wait for completion or monitor periodically
while check_progress; do
    echo ""
    echo "⏳ Processing continues... (checking again in 30 seconds)"  
    sleep 30
done

echo ""
echo "🎯 PROCESSING COMPLETED - FINAL ANALYSIS:"
echo "=========================================="

# Final comprehensive scan
scan_errors

# Check for audit reports
echo ""
echo "📋 AUDIT REPORTS GENERATED:"
ls -la "$PROCESSING_LOGS_DIR"/*audit* 2>/dev/null || echo "   No audit reports found yet"
ls -la "$PROCESSING_LOGS_DIR"/*processing* 2>/dev/null | tail -3

echo ""
echo "🔍 BINGO LOG MONITORING COMPLETE"