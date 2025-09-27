# Deployment Plan for St. Louis Integration

## Overview
This document outlines the complete deployment strategy for the St. Louis Integration website, addressing the critical security differences between our development environment (GitHub Codespaces) and production requirements.

## 🚨 Critical Security Issues to Address

### Development Environment Problems
Our current Codespaces environment has several security issues that **MUST** be fixed for production:

1. **File Permissions**: All files are 666/777 (world writable)
2. **Directory Permissions**: All directories are 777 (world writable/executable)  
3. **Development Files**: README.md, source files, development configs exposed
4. **Default Credentials**: Using development database credentials
5. **Debug Mode**: Development modules and debug settings enabled
6. **Missing Security Headers**: No security headers configured

## Deployment Strategy Options

### Option 1: Automated CI/CD Pipeline (Recommended)

**Advantages:**
- ✅ Fully automated and repeatable
- ✅ Built-in security hardening
- ✅ Zero-downtime deployments
- ✅ Automatic rollback capabilities
- ✅ Configuration management

**Implementation:**
```bash
# GitHub Actions workflow will:
1. Build production assets (npm run production)
2. Remove development files automatically
3. Run security hardening script
4. Deploy to production server
5. Update database and configuration
6. Run post-deployment tests
```

### Option 2: Manual Deployment with Scripts (Fallback)

**Advantages:**
- ✅ Full control over deployment process
- ✅ Can be run incrementally
- ✅ Good for initial deployment

**Process:**
```bash
1. ./scripts/production-security-hardening.sh  # Security hardening
2. Build and package assets for production
3. Upload to production server
4. Run deployment scripts on server
5. Update database and configuration
```

## Pre-Deployment Requirements

### 1. Production Server Setup
- [ ] **SSL Certificate**: Valid SSL certificate installed
- [ ] **Web Server**: Apache/Nginx with security headers
- [ ] **Database**: MySQL 8.0+ with production credentials
- [ ] **PHP**: PHP 8.3+ with security hardening
- [ ] **File Permissions**: Proper user/group ownership
- [ ] **Firewall**: Configured to allow only necessary ports

### 2. Content and Configuration Export
```bash
# Export Drupal configuration
cd drupal/web
../vendor/bin/drush config:export

# Export database structure and content
../vendor/bin/drush sql:dump --result-file=../database_backup.sql

# Build production theme assets
cd themes/custom/stlouisintegration
npm run production
```

### 3. Security Hardening (Critical)
```bash
# Run our comprehensive security script
./scripts/production-security-hardening.sh
```

## Deployment Process

### Phase 1: Code Deployment
1. **Repository Management**
   ```bash
   git add .
   git commit -m "Production deployment preparation"
   git push origin main
   ```

2. **Asset Building**
   ```bash
   cd themes/custom/stlouisintegration
   npm install --production
   npm run production  # Creates optimized CSS/JS
   rm -rf node_modules  # Remove after build
   ```

3. **File Cleanup** (Remove development files)
   ```bash
   rm README.md INSTALL.txt example.gitignore
   rm -rf modules/contrib/devel
   rm themes/custom/stlouisintegration/src/
   rm themes/custom/stlouisintegration/package*.json
   rm modules/custom/*/ARCHITECTURE.md
   ```

### Phase 2: Server Deployment
1. **Upload Files**
   ```bash
   rsync -avz --exclude='.git' --exclude='node_modules' \
     ./ user@production-server:/var/www/html/
   ```

2. **Set Permissions** (Critical Security Step)
   ```bash
   # On production server
   sudo chown -R www-data:www-data /var/www/html/
   sudo chmod -R 755 /var/www/html/
   sudo chmod -R 644 /var/www/html/drupal/web/sites/default/settings.php
   sudo chmod 555 /var/www/html/drupal/web/sites/default/
   ```

3. **Run Security Hardening**
   ```bash
   # On production server
   sudo /var/www/html/scripts/production-security-hardening.sh
   ```

### Phase 3: Database and Configuration
1. **Import Database**
   ```bash
   # If fresh install
   mysql -u root -p production_db < database_backup.sql
   
   # If updating existing
   cd /var/www/html/drupal/web
   ../vendor/bin/drush sql:cli < ../../database_backup.sql
   ```

2. **Update Configuration**
   ```bash
   # Import configuration changes
   ../vendor/bin/drush config:import -y
   
   # Update database schema
   ../vendor/bin/drush updatedb -y
   
   # Clear all caches
   ../vendor/bin/drush cache:rebuild
   ```

### Phase 4: Final Verification
1. **Security Verification**
   ```bash
   # Check file permissions
   find /var/www/html -type f -perm -002  # Should return empty
   
   # Verify critical file permissions
   ls -la /var/www/html/drupal/web/sites/default/settings.php  # Should be 444
   ```

2. **Functionality Testing**
   - [ ] Homepage loads correctly
   - [ ] Navigation works
   - [ ] Forms submit properly
   - [ ] Admin area accessible
   - [ ] All custom functionality works

3. **Security Testing**
   - [ ] SSL certificate valid (A+ rating)
   - [ ] Security headers present
   - [ ] No sensitive files accessible
   - [ ] Database not directly accessible

## Content Migration Strategy

### Option 1: Configuration Management (Recommended for structure)
- **Content Types**: Exported in config
- **Fields**: Exported in config  
- **Views**: Exported in config
- **Blocks**: Exported in config

### Option 2: Content Export/Import (For actual content)
```sql
-- Export specific content
SELECT * FROM node WHERE type IN ('page', 'article') AND status = 1;
SELECT * FROM node__body WHERE entity_id IN (SELECT nid FROM node WHERE type IN ('page', 'article'));
```

### Option 3: Hybrid Approach (Recommended)
1. Use configuration management for structure
2. Use content migration for actual nodes
3. Manual verification of critical content

## Post-Deployment Monitoring

### Immediate Monitoring (24-48 hours)
- [ ] Server resource usage
- [ ] Error logs monitoring
- [ ] Performance metrics
- [ ] User activity monitoring
- [ ] Security scan results

### Ongoing Monitoring
- [ ] Weekly security updates
- [ ] Monthly performance reviews
- [ ] Quarterly security audits
- [ ] Automated backup verification

## Rollback Procedures

### Emergency Rollback
```bash
# 1. Database rollback
mysql -u root -p production_db < backup_pre_deployment.sql

# 2. Code rollback  
git revert <commit-hash>
# Deploy previous version

# 3. Configuration rollback
drush config:import --source=previous_config/
```

### Partial Rollback
- **Database only**: Restore from backup
- **Code only**: Git revert + redeploy
- **Configuration only**: Previous config import

## Security Maintenance Schedule

### Daily
- [ ] Monitor error logs
- [ ] Check security alerts
- [ ] Verify backup completion

### Weekly  
- [ ] Apply security updates
- [ ] Review access logs
- [ ] Performance monitoring

### Monthly
- [ ] Security scan
- [ ] Configuration backup
- [ ] Performance optimization review

### Quarterly
- [ ] Comprehensive security audit
- [ ] Disaster recovery test
- [ ] Backup restore verification

## Emergency Contacts

- **Technical Lead**: keith@stlouisintegration.com
- **Hosting Provider**: [Provider Support Details]
- **Security Team**: [Security Contact]
- **Emergency Phone**: [Emergency Number]

## Cost Considerations

### Production Infrastructure
- **Hosting**: $X/month for production server
- **SSL Certificate**: $X/year  
- **Backup Storage**: $X/month
- **Monitoring Tools**: $X/month
- **Security Services**: $X/month

### Maintenance Resources
- **Security Updates**: X hours/month
- **Performance Monitoring**: X hours/month  
- **Content Updates**: X hours/month
- **Emergency Support**: Available 24/7

---

## Next Steps

1. **Immediate**: Review and approve this deployment plan
2. **Pre-Production**: Set up staging environment with production-like security
3. **Production**: Execute deployment following this plan
4. **Post-Production**: Monitor and maintain according to schedule

This deployment plan addresses all the critical security differences between our development and production environments while ensuring a smooth, secure deployment process.