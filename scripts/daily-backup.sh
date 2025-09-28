#!/bin/bash
# Daily backup script for St. Louis Integration

set -e

# Configuration
DRUPAL_ROOT="/var/www/html/stlouisintegration"
BACKUP_DIR="/var/backups/stlouisintegration/daily"
DATE=$(date +%Y%m%d_%H%M%S)
DRUSH="$DRUPAL_ROOT/vendor/bin/drush"
RETENTION_DAYS=7

# Change to Drupal root
cd "$DRUPAL_ROOT"

# Create timestamped backup
echo "Starting daily backup at $(date)"

# Database backup
echo "Backing up database..."
sudo -u www-data $DRUSH sql:dump --result-file="$BACKUP_DIR/database_${DATE}.sql"
gzip "$BACKUP_DIR/database_${DATE}.sql"

# Files backup (if backup_migrate_files is available)
echo "Backing up files..."
if [ -d "web/sites/default/files" ]; then
    sudo tar -czf "$BACKUP_DIR/files_${DATE}.tar.gz" -C web/sites/default files/ 2>/dev/null || echo "Files backup skipped"
fi

# Configuration backup
echo "Backing up configuration..."
sudo -u www-data $DRUSH config:export --destination="$BACKUP_DIR/config_${DATE}" -y

# Compress configuration
sudo tar -czf "$BACKUP_DIR/config_${DATE}.tar.gz" -C "$BACKUP_DIR" "config_${DATE}"
sudo rm -rf "$BACKUP_DIR/config_${DATE}"

# Clean up old daily backups (keep last 7 days)
echo "Cleaning up old backups..."
find "$BACKUP_DIR" -name "database_*.sql.gz" -mtime +$RETENTION_DAYS -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "files_*.tar.gz" -mtime +$RETENTION_DAYS -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "config_*.tar.gz" -mtime +$RETENTION_DAYS -delete 2>/dev/null || true

# Set proper permissions
sudo chown -R www-data:www-data "$BACKUP_DIR"
sudo chmod -R 640 "$BACKUP_DIR"/*.gz 2>/dev/null || true

echo "Daily backup completed at $(date)"
