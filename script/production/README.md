# Production Helper Scripts

This directory contains scripts designed to run on the production server.

## Scripts

### export-config.sh

Exports Drupal configuration from production to a tarball.

**Usage (on production server):**
```bash
cd /var/www/html/scripts
./export-config.sh forseti
```

**Output:**
- Creates `/tmp/forseti-config-YYYYMMDD-HHMMSS.tar.gz`
- Contains `config/sync/` directory from the site

### sync-config-from-production.sh

Syncs configuration from production to your local development environment.

**Usage (on local dev machine):**
```bash
cd ~/forseti.life/script/production
./sync-config-from-production.sh forseti root@your-server
```

**What it does:**
1. SSHs to production server
2. Runs export-config.sh remotely
3. Downloads the config tarball
4. Extracts to your local `sites/forseti/config/sync/`
5. Shows you git diff of changes
6. Provides instructions for committing

## Workflow

### Syncing Production Config to Development

1. **Run sync script locally:**
   ```bash
   ./script/production/sync-config-from-production.sh forseti root@your-server
   ```

2. **Review changes:**
   ```bash
   git diff sites/forseti/config/sync/
   ```

3. **Commit if changes look good:**
   ```bash
   git add sites/forseti/config/sync/
   git commit -m "Sync config from production $(date +%Y-%m-%d)"
   git push
   ```

### Making Config Changes

1. **Always start by syncing from production** (see above)

2. **Make your changes in development**

3. **Export and commit:**
   ```bash
   cd sites/forseti
   drush config:export -y
   git add config/sync/
   git commit -m "Update config: describe your changes"
   git push
   ```

4. **Manually deploy config to production:**
   ```bash
   # SSH to production
   ssh root@your-server
   cd /var/www/html/forseti
   
   # Pull latest code
   git pull origin main
   
   # Import config
   sudo -u www-data ./vendor/bin/drush config:import -y
   sudo -u www-data ./vendor/bin/drush cache:rebuild
   ```

## Deployment

These scripts are automatically deployed to `/var/www/html/scripts/` on production by the GitHub Actions workflow.

## Important Notes

⚠️ **Config sync is disabled in automatic deployments** to prevent accidentally overwriting production config with out-of-sync development config.

✅ **Always sync FROM production TO development** before making config changes.

❌ **Never** manually copy dev config to production without syncing first.
