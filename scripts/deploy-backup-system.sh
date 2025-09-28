#!/bin/bash
# Production backup deployment script
# This script sets up the backup system in production via GitHub Actions

set -e

echo "=== Deploying Backup System to Production ==="

DRUPAL_ROOT="/var/www/html/stlouisintegration"
BACKUP_BASE_DIR="/var/backups/stlouisintegration" 
SCRIPTS_DIR="$DRUPAL_ROOT/../scripts"

# Create backup directories
echo "Creating backup directories..."
sudo mkdir -p "$BACKUP_BASE_DIR/daily"
sudo mkdir -p "$BACKUP_BASE_DIR/weekly"
sudo chown -R www-data:www-data "$BACKUP_BASE_DIR"
sudo chmod -R 755 "$BACKUP_BASE_DIR"

# Enable Backup and Migrate module
echo "Enabling Backup and Migrate module..."
cd "$DRUPAL_ROOT"
sudo -u www-data ./vendor/bin/drush en backup_migrate -y

# Set up log files
echo "Setting up log files..."
sudo touch /var/log/stlouisintegration-daily-backup.log
sudo touch /var/log/stlouisintegration-weekly-backup.log
sudo chown www-data:www-data /var/log/stlouisintegration-*-backup.log
sudo chmod 644 /var/log/stlouisintegration-*-backup.log

# Make scripts executable
echo "Setting up backup scripts..."
chmod +x "$SCRIPTS_DIR/daily-backup.sh"
chmod +x "$SCRIPTS_DIR/weekly-backup.sh"
chmod +x "$SCRIPTS_DIR/backup-status.sh"

# Test daily backup
echo "Testing backup system..."
$SCRIPTS_DIR/daily-backup.sh

echo "✅ Backup system deployed successfully!"
echo
echo "To complete setup, add these cron jobs:"
echo "# Daily backup at 2:00 AM"
echo "0 2 * * * $SCRIPTS_DIR/daily-backup.sh >> /var/log/stlouisintegration-daily-backup.log 2>&1"
echo
echo "# Weekly backup on Sunday at 3:00 AM"  
echo "0 3 * * 0 $SCRIPTS_DIR/weekly-backup.sh >> /var/log/stlouisintegration-weekly-backup.log 2>&1"