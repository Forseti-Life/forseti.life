# Deployment Checklist - St. Louis Integration

## Pre-Deployment
- [ ] All configuration changes committed to repository
- [ ] Backup and Migrate module configuration exported
- [ ] Custom scripts removed from deployment process
- [ ] GitHub Actions workflow updated for module-only approach

## Backup System Deployment
- [ ] Backup directories created in production: `/var/backups/stlouisintegration/`
- [ ] Backup and Migrate module enabled
- [ ] Backup configuration imported via `drush config:import`
- [ ] Backup schedules visible in Drupal admin
- [ ] Backup destinations configured properly

## Post-Deployment Verification

### Module Verification
- [ ] Backup and Migrate module status: `/admin/modules`
- [ ] Module version 5.1.0 or higher installed

### Schedule Verification
- [ ] Navigate to `/admin/config/development/backup_migrate/schedule`
- [ ] Verify "Daily Database Backup" appears and is enabled
- [ ] Verify "Weekly Full Site Backup" appears and is enabled
- [ ] Check schedule periods: 86400 seconds (daily), 604800 seconds (weekly)
- [ ] Confirm retention: 7 days (daily), 20 weeks (weekly)

### Destination Verification
- [ ] Navigate to `/admin/config/development/backup_migrate/destination`
- [ ] Verify "Daily Local Backups" destination exists
- [ ] Verify "Weekly Local Backups" destination exists
- [ ] Check paths: `/var/backups/stlouisintegration/daily` and `weekly`

### Source Verification  
- [ ] Navigate to `/admin/config/development/backup_migrate/source`
- [ ] Verify "Default Database" source available
- [ ] Verify "Entire Site" source available

### Manual Backup Test
- [ ] Navigate to `/admin/config/development/backup_migrate`
- [ ] Create manual database backup
- [ ] Verify backup file created in `/var/backups/stlouisintegration/daily/`
- [ ] Check file permissions (www-data:www-data)

### Permissions Check
```bash
# Verify backup directory ownership
ls -la /var/backups/stlouisintegration/
# Should show: drwxr-xr-x www-data www-data

# Check directory permissions
stat /var/backups/stlouisintegration/daily/
stat /var/backups/stlouisintegration/weekly/
```

### Cron Verification
- [ ] Check Drupal cron status: `/admin/config/system/cron`
- [ ] Verify cron is running (automated or manual setup)
- [ ] Confirm backup schedules will be triggered by cron

## Rollback Plan
If issues occur:
1. Revert configuration: `drush config:import` with previous config
2. Disable Backup and Migrate module if needed
3. Check Drupal logs for errors: `/admin/reports/dblog`

## Monitoring Setup
- [ ] Set up disk space monitoring for `/var/backups/`
- [ ] Monitor Drupal logs for backup success/failure
- [ ] Test backup restoration process
- [ ] Document backup verification procedures

## Final Sign-off
- [ ] Daily backup schedule operational
- [ ] Weekly backup schedule operational  
- [ ] All custom scripts removed from production
- [ ] Documentation updated
- [ ] Team notified of new backup process