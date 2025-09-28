#!/bin/bash
# Weekly backup script for St. Louis Integration

set -e

# Configuration  
DRUPAL_ROOT="/var/www/html/stlouisintegration"
BACKUP_DIR="/var/backups/stlouisintegration/weekly"
WEEK=$(date +%Y-W%U)
DRUSH="$DRUPAL_ROOT/vendor/bin/drush"
RETENTION_WEEKS=20

# Change to Drupal root
cd "$DRUPAL_ROOT"

# Create weekly backup
echo "Starting weekly backup for week $WEEK at $(date)"

# Complete database backup
echo "Backing up database..."
sudo -u www-data $DRUSH sql:dump --result-file="$BACKUP_DIR/database_${WEEK}.sql"
gzip "$BACKUP_DIR/database_${WEEK}.sql"

# Complete files backup
echo "Backing up all files..."
if [ -d "web/sites/default/files" ]; then
    sudo tar -czf "$BACKUP_DIR/files_${WEEK}.tar.gz" -C web/sites/default files/
fi

# Full site backup (code + files)
echo "Creating full site backup..."
sudo tar -czf "$BACKUP_DIR/fullsite_${WEEK}.tar.gz" \
    --exclude='web/sites/default/files/backup_migrate' \
    --exclude='vendor/*/tests' \
    --exclude='web/core/tests' \
    --exclude='.git' \
    -C "$DRUPAL_ROOT" .

# Configuration backup
echo "Backing up configuration..."
sudo -u www-data $DRUSH config:export --destination="$BACKUP_DIR/config_${WEEK}" -y
sudo tar -czf "$BACKUP_DIR/config_${WEEK}.tar.gz" -C "$BACKUP_DIR" "config_${WEEK}"
sudo rm -rf "$BACKUP_DIR/config_${WEEK}"

# Clean up old weekly backups (keep last 20 weeks)
echo "Cleaning up old weekly backups..."
find "$BACKUP_DIR" -name "database_*.sql.gz" -mtime +$((RETENTION_WEEKS * 7)) -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "files_*.tar.gz" -mtime +$((RETENTION_WEEKS * 7)) -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "fullsite_*.tar.gz" -mtime +$((RETENTION_WEEKS * 7)) -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "config_*.tar.gz" -mtime +$((RETENTION_WEEKS * 7)) -delete 2>/dev/null || true

# Set proper permissions
sudo chown -R www-data:www-data "$BACKUP_DIR"
sudo chmod -R 640 "$BACKUP_DIR"/*.gz 2>/dev/null || true

echo "Weekly backup completed at $(date)"
