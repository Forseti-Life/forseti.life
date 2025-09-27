# Production Deployment Security Guide

## Development vs Production Security Differences

### Current Development Environment Issues

Our development environment in GitHub Codespaces has several relaxed permissions that **MUST** be addressed for production:

#### 🚨 Critical Security Issues in Development:

1. **World-Writable Files**: Many files have 666 permissions (readable/writable by all)
2. **Relaxed Directory Permissions**: Directories have 777 permissions 
3. **Exposed Development Files**: README.md, development configs, source files
4. **Default Database Credentials**: Using default/weak credentials
5. **Missing Security Headers**: No security headers configured
6. **Development Modules Active**: Devel module and debug tools enabled

## Production Security Requirements

### 1. File Permissions Matrix

| File/Directory | Development | Production | Purpose |
|----------------|-------------|------------|---------|
| `sites/default/settings.php` | 666 | 444 | Read-only configuration |
| `sites/default/` | 777 | 555 | Read-only directory |
| `sites/default/files/` | 777 | 775 | Web server writable |
| `sites/default/files/*` | 666 | 664 | Web server writable files |
| All other files | 666 | 644 | Read-only for web |
| All directories | 777 | 755 | Standard web permissions |
| Private files | 777 | 600/700 | Restricted access |

### 2. Ownership Requirements

```bash
# Production ownership (not codespace user)
chown -R www-data:www-data /var/www/html/drupal/web
```

### 3. Files to Remove in Production

- ✅ `INSTALL.txt`, `README.md`, `CHANGELOG.txt`
- ✅ `example.gitignore`, `web.config` 
- ✅ Development module directories (`devel/`, `simpletest/`)
- ✅ Theme source files (`src/`, `node_modules/`, `package.json`)
- ✅ Module documentation (`ARCHITECTURE.md`)

## Automated Deployment Strategy

### Option 1: CI/CD Pipeline (Recommended)

```yaml
# .github/workflows/deploy.yml
name: Production Deploy
on:
  push:
    branches: [main]
    
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Build Assets
        run: |
          cd themes/custom/stlouisintegration
          npm install
          npm run production  # Build for production
          
      - name: Remove Development Files
        run: |
          rm -rf themes/custom/stlouisintegration/src
          rm -rf themes/custom/stlouisintegration/node_modules
          rm themes/custom/stlouisintegration/package*.json
          rm modules/custom/*/ARCHITECTURE.md
          
      - name: Deploy to Server
        uses: easingthemes/ssh-deploy@v2.1.5
        with:
          SSH_PRIVATE_KEY: ${{ secrets.SERVER_PRIVATE_KEY }}
          SOURCE: "."
          REMOTE_HOST: ${{ secrets.REMOTE_HOST }}
          REMOTE_USER: ${{ secrets.REMOTE_USER }}
          TARGET: "/var/www/html/"
          
      - name: Run Security Hardening
        run: |
          ssh ${{ secrets.REMOTE_USER }}@${{ secrets.REMOTE_HOST }} \
          'bash /var/www/html/scripts/production-security-hardening.sh'
          
      - name: Update Database and Config
        run: |
          ssh ${{ secrets.REMOTE_USER }}@${{ secrets.REMOTE_HOST }} \
          'cd /var/www/html/drupal/web && \
           ../vendor/bin/drush updb -y && \
           ../vendor/bin/drush cim -y && \
           ../vendor/bin/drush cr'
```

### Option 2: Manual Deployment Script

```bash
#!/bin/bash
# deploy.sh - Manual deployment script

# 1. Build assets locally
cd themes/custom/stlouisintegration
npm run production

# 2. Create deployment package
cd ../../..
tar --exclude='node_modules' \
    --exclude='src' \
    --exclude='*.md' \
    --exclude='package*.json' \
    -czf stlouisintegration-$(date +%Y%m%d).tar.gz .

# 3. Upload to server
scp stlouisintegration-$(date +%Y%m%d).tar.gz user@server:/tmp/

# 4. Deploy on server
ssh user@server '
  cd /var/www/html
  tar -xzf /tmp/stlouisintegration-$(date +%Y%m%d).tar.gz
  bash scripts/production-security-hardening.sh
  cd drupal/web
  ../vendor/bin/drush updb -y
  ../vendor/bin/drush cim -y
  ../vendor/bin/drush cr
'
```

## Database Content Deployment

### Strategy 1: Configuration Management (Recommended)

```bash
# Export configuration (includes content types, fields, etc.)
drush config:export

# Commit configuration
git add sites/default/files/config_*/
git commit -m "Export configuration"

# Deploy configuration
drush config:import -y
```

### Strategy 2: Content Migration

For actual content (nodes), use structured content migration:

```bash
# Export specific content
drush sql:query "SELECT * FROM node WHERE type = 'page'" --result-file=pages.sql

# Import on production
drush sql:cli < pages.sql
```

## Web Server Security Configuration

### Apache Security Headers
```apache
# In .htaccess or virtual host
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set Content-Security-Policy "default-src 'self'"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

### Nginx Security Headers
```nginx
# In server block
add_header X-Content-Type-Options nosniff;
add_header X-Frame-Options DENY;
add_header X-XSS-Protection "1; mode=block";
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
add_header Content-Security-Policy "default-src 'self'";
add_header Referrer-Policy "strict-origin-when-cross-origin";
```

## Production Environment Checklist

### Pre-Deployment
- [ ] Run security hardening script
- [ ] Remove all development files
- [ ] Build production assets
- [ ] Export configuration
- [ ] Create database backup
- [ ] Test deployment in staging environment

### Deployment
- [ ] Upload files with correct ownership
- [ ] Run security hardening script on production
- [ ] Import configuration
- [ ] Update database schema
- [ ] Clear all caches
- [ ] Configure SSL certificate
- [ ] Set up monitoring

### Post-Deployment
- [ ] Verify all pages load correctly
- [ ] Test form submissions
- [ ] Verify admin functionality
- [ ] Run security scan
- [ ] Check security headers
- [ ] Verify SSL grade (A+ rating)
- [ ] Set up automated backups
- [ ] Configure log monitoring

## Ongoing Security Maintenance

### Automated Tasks
- Daily security updates check
- Weekly configuration backups
- Monthly security scans
- Quarterly security reviews

### Manual Tasks  
- Review access logs for suspicious activity
- Update security policies as needed
- Test backup restore procedures
- Review and update firewall rules

## Emergency Procedures

### Security Incident Response
1. Immediately take site offline if compromised
2. Restore from last known good backup
3. Analyze logs for intrusion vectors
4. Apply security patches
5. Change all passwords
6. Review all user accounts
7. Bring site back online after verification

### Rollback Procedures
1. Database rollback: Restore from backup
2. Code rollback: Git revert + redeploy  
3. Configuration rollback: Previous config import
4. Full site rollback: Complete backup restore

## Contact Information

- **Security Team**: security@stlouisintegration.com
- **Emergency Contact**: +1-XXX-XXX-XXXX
- **Hosting Provider**: [Provider Support]
- **SSL Certificate**: [Certificate Authority]