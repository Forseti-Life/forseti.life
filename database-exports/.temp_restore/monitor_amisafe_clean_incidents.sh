#!/bin/bash
while [[ -f "/workspaces/stlouisintegration.com/database-exports/.temp_restore/import_active" ]]; do
    sleep 30
    current_count=$(mysql -u"drupal_user" -p"drupal_secure_password" -h"127.0.0.1" -D"amisafe_database" -se "SELECT COUNT(*) FROM amisafe_clean_incidents" 2>/dev/null || echo "0")
    timestamp=$(date '+%H:%M:%S')
    echo "$timestamp - Table amisafe_clean_incidents: $current_count records" >> "/workspaces/stlouisintegration.com/database-exports/.temp_restore/monitor_amisafe_clean_incidents.log"
done
