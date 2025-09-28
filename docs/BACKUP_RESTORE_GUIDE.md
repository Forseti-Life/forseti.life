# Backup and Restore Guide - St. Louis Integration

## Overview
This guide covers the automated backup system and restoration procedures for the St. Louis Integration website.

## Backup Strategy
- **Daily Backups**: Automated daily at 2:00 AM, 7-day retention
  - Database snapshots
  - Configuration exports  
  - Essential files
- **Weekly Backups**: Automated weekly on Sundays at 3:00 AM, 20-week retention
  - Full database dumps
  - Complete file system backup
  - Full site archives
  - Configuration exports

## Backup Locations
- Daily: `/var/backups/stlouisintegration/daily/`
- Weekly: `/var/backups/stlouisintegration/weekly/`
- Logs: `/var/log/stlouisintegration-*-backup.log`

## Monitoring Backups
Check backup status:
```bash
./scripts/backup-status.sh
```

## Manual Backup Creation
Force immediate daily backup:
```bash
./scripts/daily-backup.sh
```

Force immediate weekly backup:
```bash
./scripts/weekly-backup.sh
```

## Restoration Procedures

### Database Restoration
```bash
cd /var/www/html/stlouisintegration
gunzip -c /var/backups/stlouisintegration/daily/database_YYYYMMDD_HHMMSS.sql.gz | sudo -u www-data ./vendor/bin/drush sql:cli
```

### Files Restoration
```bash
cd /var/www/html/stlouisintegration/web/sites/default
sudo tar -xzf /var/backups/stlouisintegration/daily/files_YYYYMMDD_HHMMSS.tar.gz
sudo chown -R www-data:www-data files/
```

### Configuration Restoration
```bash
cd /var/www/html/stlouisintegration
sudo mkdir -p /tmp/restore-config
sudo tar -xzf /var/backups/stlouisintegration/daily/config_YYYYMMDD_HHMMSS.tar.gz -C /tmp/restore-config
sudo -u www-data ./vendor/bin/drush config:import --source=/tmp/restore-config -y
```

### Full Site Restoration (Weekly Backup)
```bash
cd /var/www/html
sudo tar -xzf /var/backups/stlouisintegration/weekly/fullsite_YYYY-WXX.tar.gz
sudo chown -R www-data:www-data stlouisintegration/
sudo chmod -R 755 stlouisintegration/
```

## Production Cron Setup
To set up automated backups in production, add these cron jobs:

```bash
# Edit the crontab
sudo crontab -e

# Add these lines:
# St. Louis Integration Backups
# Daily backup at 2:00 AM
0 2 * * * /var/www/html/stlouisintegration/../scripts/daily-backup.sh >> /var/log/stlouisintegration-daily-backup.log 2>&1

# Weekly backup on Sunday at 3:00 AM  
0 3 * * 0 /var/www/html/stlouisintegration/../scripts/weekly-backup.sh >> /var/log/stlouisintegration-weekly-backup.log 2>&1
```

## Backup Verification
Test backup integrity:
```bash
# Verify database backup
gunzip -t /var/backups/stlouisintegration/daily/database_*.sql.gz

# Verify archive integrity
tar -tzf /var/backups/stlouisintegration/daily/files_*.tar.gz > /dev/null
tar -tzf /var/backups/stlouisintegration/weekly/fullsite_*.tar.gz > /dev/null
```

## Emergency Recovery
1. Put site in maintenance mode
2. Restore database from most recent backup
3. Restore files from backup
4. Clear caches: `drush cache:rebuild`
5. Take site out of maintenance mode
6. Verify functionality

## Backup Security
- Backups are owned by www-data with restricted permissions (640)
- Consider encrypting sensitive backup data
- Regularly test restoration procedures
- Monitor disk space usage
- Set up off-site backup replication for disaster recovery

## Development Testing
In the development environment, you can test the backup system:

```bash
# Test daily backup creation
sudo /workspaces/stlouisintegration.com/scripts/daily-backup.sh

# Check backup status
/workspaces/stlouisintegration.com/scripts/backup-status.sh

# Test weekly backup (if needed)
sudo /workspaces/stlouisintegration.com/scripts/weekly-backup.sh
```