# Backup and Restore Guide - St. Louis Integration

## Overview
This guide covers the automated backup system using Drupal's Backup and Migrate module for the St. Louis Integration website.

## Backup Strategy
- **Daily Backups**: Automated via Backup and Migrate module, 7-day retention
  - Source: Default Database
  - Destination: `/var/backups/stlouisintegration/daily`
- **Weekly Backups**: Automated via Backup and Migrate module, 20-week retention
  - Source: Entire Site (database + files)
  - Destination: `/var/backups/stlouisintegration/weekly`

## Backup Management

### Drupal Admin Interface
Access backup management through:
- **Schedules**: `/admin/config/development/backup_migrate/schedule`
- **Destinations**: `/admin/config/development/backup_migrate/destination`
- **Sources**: `/admin/config/development/backup_migrate/source`
- **Manual Backup**: `/admin/config/development/backup_migrate`

### Backup Schedules
1. **Daily Database Backup**
   - ID: `daily_backup`
   - Runs every 24 hours (86400 seconds)
   - Keeps last 7 backups
   - Source: Default Database
   - Destination: Daily Local Backups

2. **Weekly Full Site Backup**
   - ID: `weekly_backup`
   - Runs every 7 days (604800 seconds)
   - Keeps last 20 backups
   - Source: Entire Site
   - Destination: Weekly Local Backups

### Manual Backup Creation
Create immediate backups:
1. Go to `/admin/config/development/backup_migrate`
2. Select source and destination
3. Click "Backup now"

## Monitoring Backups
Check backup status using the included monitoring script:
```bash
./scripts/backup-status.sh
```

## Restoration Procedures

### Through Drupal Interface
1. Go to `/admin/config/development/backup_migrate/restore`
2. Select the backup file to restore
3. Choose restoration options
4. Click "Restore"

### Manual Restoration

#### Database Restoration
```bash
cd /var/www/html/stlouisintegration
# For compressed backups (.gz)
gunzip -c /var/backups/stlouisintegration/daily/backup-TIMESTAMP.sql.gz | sudo -u www-data ./vendor/bin/drush sql:cli
# For regular SQL files
sudo -u www-data ./vendor/bin/drush sql:cli < /var/backups/stlouisintegration/daily/backup-TIMESTAMP.sql
```

#### Full Site Restoration
```bash
cd /var/www/html
# Extract full site backup
sudo tar -xzf /var/backups/stlouisintegration/weekly/backup-TIMESTAMP.tar.gz
sudo chown -R www-data:www-data stlouisintegration/
sudo chmod -R 755 stlouisintegration/
```

## Configuration Management

### Backup Schedules Configuration
The backup schedules are managed through Drupal configuration files:
- `backup_migrate.backup_migrate_schedule.daily_backup.yml`
- `backup_migrate.backup_migrate_schedule.weekly_backup.yml`

### Backup Destinations Configuration
Backup destinations are configured in:
- `backup_migrate.backup_migrate_destination.daily_local_backup.yml`
- `backup_migrate.backup_migrate_destination.weekly_local_backup.yml`

### Modifying Backup Settings
To change backup settings:
1. Use the Drupal admin interface, OR
2. Update configuration files and run `drush config:import`

## Backup Verification
Test backup integrity:
```bash
# Verify compressed backups
gunzip -t /var/backups/stlouisintegration/daily/*.sql.gz

# Verify tar archives
tar -tzf /var/backups/stlouisintegration/weekly/*.tar.gz > /dev/null
```

## Emergency Recovery
1. Put site in maintenance mode: `/admin/config/development/maintenance`
2. Restore database from most recent backup
3. Restore files if needed
4. Clear caches: `drush cache:rebuild`
5. Take site out of maintenance mode
6. Verify functionality

## Backup Security
- Backups are stored with proper permissions (www-data:www-data)
- Backup directories have secure permissions (755)
- Consider encrypting sensitive backup data for off-site storage
- Regularly test restoration procedures
- Monitor disk space usage
- Set up off-site backup replication for disaster recovery

## Troubleshooting

### Common Issues
1. **Backups not running**: Check cron status and Drupal cron configuration
2. **Permission errors**: Ensure backup directories are owned by www-data
3. **Disk space**: Monitor `/var/backups` disk usage
4. **Large backups**: Consider excluding unnecessary files or using compression

### Logs
Check Drupal logs at `/admin/reports/dblog` for backup-related messages.

### Support
For backup module specific issues, consult the [Backup and Migrate module documentation](https://www.drupal.org/project/backup_migrate).