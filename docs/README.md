# Forseti.life - Documentation Hub

**Last Updated**: February 19, 2026  
**Repository**: forseti.life  
**Products**: Job Hunter, Dungeon Crawler, Scientific Experimentation + Clinical Trials (early), Community Safety (early)

---

## Table of Contents
1. [Overview](#overview)
2. [Documentation Structure](#documentation-structure)
3. [Product Documentation](#product-documentation)
4. [Technical Documentation](#technical-documentation)
5. [Market Documentation](#market-documentation)
6. [Operations Guide](#operations-guide)

---

## Overview

This directory contains comprehensive documentation for the Forseti.life project, including:
- **Job Hunter**: Hiring and job search automation product focus
- **Dungeon Crawler**: AI-assisted Pathfinder 2e experience
- **Scientific Experimentation + Clinical Trials**: Early-stage product line
- **Community Safety**: Safety platform and mobile capabilities (early-stage)
- **H3 Geolocation System**: Geospatial processing pipeline using Uber's H3 hexagonal indexing

---

## Documentation Structure

```
docs/
├── README.md (this file)                # Documentation hub and operations guide
├── ARCHITECTURE.md                      # System architecture overview
├── product/                             # 🟢 Product management (Lean Startup)
│   ├── README.md                        # Product documentation guide
│   ├── process-flow-validation.md       # End-to-end validation roadmap
│   ├── lean-canvas/                     # Business model canvas
│   ├── customer-development/            # Customer interviews & validation
│   ├── experiments/                     # Hypothesis testing & pivots
│   ├── metrics/                         # AARRR analytics & dashboards
│   ├── mvp/                             # MVP definition & prioritization
│   └── user-journey/                    # Persona journey mapping
├── market/                              # 🟡 Market analysis
│   ├── README.md                        # Market documentation guide
│   ├── market-sizing.md                 # TAM/SAM/SOM analysis
│   ├── competitive-analysis.md          # Competitor landscape
│   ├── value-proposition.md             # Unique value proposition
│   └── go-to-market-strategy.md         # Customer acquisition plan
└── technical/                           # 🟡 Technical documentation
    ├── README.md                        # Technical documentation guide
    ├── architecture.md                  # Links to architecture docs
    ├── api-documentation.md             # API endpoints & specs
    ├── data-models.md                   # Database schemas
    └── integration-guides.md            # Third-party integrations
```

---

## Product Documentation

**Location**: `/docs/product/`  
**Purpose**: Lean Startup product management framework  
**Status**: 🟢 Live Beta Testing

### Key Documents

#### [Process Flow & Validation Roadmap](./product/process-flow-validation.md)
End-to-end system validation connecting user journey to technical implementation.
- Discovery & Activation flows
- Retention & Engagement mechanics
- Conversion & Monetization process
- Testing strategy and go/no-go criteria

#### [MVP Definition](./product/mvp/mvp-definition.md)
Current MVP scope with 6 core features in live beta testing.
- Interactive safety map with H3 hexagons
- Background location monitoring
- Proactive push notifications
- Success criteria: 40% activation, 20% D7 retention, 2% conversion

#### [User Journey: Sarah](./product/user-journey/sarah-urban-commuter.md)
Detailed persona journey from discovery to advocacy.
- Primary persona: Urban professional, 28, walks/uses transit
- Journey stages: Problem trigger → Aha moment → Trial → Paid conversion
- Success indicators at each touchpoint

#### [Lean Canvas](./product/lean-canvas/)
One-page business model with 9 building blocks.
- Problem, Solution, Customer Segments, Value Proposition
- Channels, Revenue, Costs, Metrics, Unfair Advantage

#### [Customer Development](./product/customer-development/)
Customer discovery and validation process.
- Target: 35 interviews (15 Urban Commuters, 10 Parents, 10 Real Estate)
- Current: 0 completed
- Problem and solution validation frameworks

#### [Experiments](./product/experiments/)
Build-Measure-Learn hypothesis testing.
- Experiment logging and tracking
- Pivot vs. persevere decision framework
- Innovation accounting

#### [Metrics](./product/metrics/)
AARRR (Pirate Metrics) and analytics.
- North Star Metric (TBD)
- Acquisition → Activation → Retention → Revenue → Referral
- Cohort analysis and PMF signals

---

## Technical Documentation

**Location**: `/docs/technical/`  
**Purpose**: System architecture and implementation guides  
**Status**: 🟡 Organized

### System Architecture

**Primary Document**: [`/docs/ARCHITECTURE.md`](./ARCHITECTURE.md)

Comprehensive overview of:
- Frontend (Drupal 11 web, React Native mobile)
- Backend (RESTful APIs, Python ETL)
- Data Layer (MySQL, H3 geospatial indexing)
- Integration points

**Related Architectures**:
- `/amisafe-mobile/ARCHITECTURE.md` - Mobile app architecture
- `/h3-geolocation/ARCHITECTURE.md` - Geospatial processing
- `/amisafe-mobile/BACKGROUND_SERVICE_DOCUMENTATION.md` - Background monitoring

### Technology Stack

**Frontend**:
- Web: Drupal 11, Radix theme, Leaflet.js maps
- Mobile: React Native 0.72.6, TypeScript

**Backend**:
- CMS: Drupal 11 with custom modules
- API: RESTful endpoints
- Processing: Python scripts, cron jobs

**Data**:
- Database: MySQL
- Geospatial: H3 (resolution 11, ~700m hexagons)
- ETL: Python for crime data ingestion

---

## Market Documentation

**Location**: `/docs/market/`  
**Purpose**: Market analysis and go-to-market strategy  
**Status**: 🟡 Template Ready

### Framework

- **Market Sizing**: TAM/SAM/SOM analysis for safety apps in St. Louis
- **Competitive Analysis**: Direct/indirect competitors, substitutes
- **Value Proposition**: Why Forseti is different (hyperlocal, proactive, statistical)
- **Go-to-Market**: Acquisition channels, positioning, launch plan

---

## Operations Guide

**Sections Below**:
1. [Backup & Restore](#backup--restore) - Daily/weekly backup strategy
2. [Deployment Strategy](#deployment-strategy) - Deployment procedures
3. [Security Hardening](#security-hardening) - Security best practices
4. [Production Checklist](#production-checklist) - Launch readiness

---

## Backup & Restore

All paths and commands below are examples. Replace `<site>` and backup locations with values appropriate for your environment.

### Backup Strategy

**Daily Backups** - Automated via Backup and Migrate module
- Source: Default Database
- Destination: `/var/backups/<site>/daily`
- Retention: 7 days
- Schedule: Every 24 hours (86400 seconds)

**Weekly Backups** - Full site backup
- Source: Entire Site (database + files)
- Destination: `/var/backups/<site>/weekly`
- Retention: 20 weeks
- Schedule: Every 7 days (604800 seconds)

### Backup Management

**Drupal Admin Interface:**
- Schedules: `/admin/config/development/backup_migrate/schedule`
- Destinations: `/admin/config/development/backup_migrate/destination`
- Sources: `/admin/config/development/backup_migrate/source`
- Manual Backup: `/admin/config/development/backup_migrate`

**Backup Schedules:**

1. **daily_backup**
   - Runs: Every 24 hours
   - Keeps: Last 7 backups
   - Source: Default Database
   - Destination: Daily Local Backups

2. **weekly_backup**
   - Runs: Every 7 days
   - Keeps: Last 20 backups
   - Source: Entire Site
   - Destination: Weekly Local Backups

### Manual Backup Creation

**Through Drupal Interface:**
1. Navigate to `/admin/config/development/backup_migrate`
2. Select source and destination
3. Click "Backup now"

**Via Command Line:**
```bash
cd /var/www/html/<site>

# Database backup
./vendor/bin/drush sql:dump --result-file=../backup-$(date +%Y%m%d-%H%M%S).sql

# Full site backup
tar -czf ../backup-full-$(date +%Y%m%d-%H%M%S).tar.gz .
```

### Restoration Procedures

**Database Restoration:**
```bash
cd /var/www/html/<site>

# For compressed backups (.gz)
gunzip -c /var/backups/<site>/daily/backup-TIMESTAMP.sql.gz | \
  sudo -u www-data ./vendor/bin/drush sql:cli

# For regular SQL files
sudo -u www-data ./vendor/bin/drush sql:cli < \
  /var/backups/<site>/daily/backup-TIMESTAMP.sql
```

**Full Site Restoration:**
```bash
cd /var/www/html

# Extract full site backup
sudo tar -xzf /var/backups/<site>/weekly/backup-TIMESTAMP.tar.gz

# Fix permissions
sudo chown -R www-data:www-data <site>/
sudo chmod -R 755 <site>/
```

**Through Drupal Interface:**
1. Go to `/admin/config/development/backup_migrate/restore`
2. Select the backup file to restore
3. Choose restoration options
4. Click "Restore"

### Monitoring Backups

Check backup status using the monitoring script:
```bash
./scripts/backup-status.sh
```

Verify backup integrity:
```bash
# Verify compressed backups
gunzip -t /var/backups/forseti/daily/*.sql.gz

# Test restoration (dry run)
gunzip -c backup.sql.gz | head -100
```

---

## Deployment Strategy

### Deployment Options

**Option 1: Automated CI/CD Pipeline (Recommended)**

Advantages:
- ✅ Fully automated and repeatable
- ✅ Built-in security hardening
- ✅ Zero-downtime deployments
- ✅ Automatic rollback capabilities
- ✅ Configuration management

GitHub Actions workflow will:
1. Build production assets (npm run production)
2. Remove development files automatically
3. Run security hardening script
4. Deploy to production server
5. Update database and configuration
6. Run post-deployment tests

**Option 2: Manual Deployment with Scripts (Fallback)**

Advantages:
- ✅ Full control over deployment process
- ✅ Can be run incrementally
- ✅ Good for initial deployment

Process:
1. Run `./scripts/production-security-hardening.sh`
2. Build and package assets for production
3. Upload to production server
4. Run deployment scripts on server
5. Update database and configuration

### Pre-Deployment Requirements

**1. Production Server Setup**
- [ ] SSL Certificate installed and configured
- [ ] Web Server (Apache/Nginx) with security headers
- [ ] MySQL 8.0+ with production credentials
- [ ] PHP 8.3+ with security hardening
- [ ] Proper file permissions and ownership
- [ ] Firewall configured (allow only necessary ports)

**2. Content and Configuration Export**
```bash
# Export Drupal configuration
cd drupal/web
../vendor/bin/drush config:export

# Export database structure and content
../vendor/bin/drush sql:dump --result-file=../database_backup.sql

# Build production theme assets (if custom theme exists)
cd themes/custom/forseti
npm run production
```

**3. Security Hardening (Critical)**
```bash
# Run comprehensive security script
./scripts/production-security-hardening.sh
```

### Deployment Process

**Phase 1: Code Deployment**
```bash
# Repository management
git add .
git commit -m "Production deployment preparation"
git push origin main

# Asset building (if custom theme exists)
cd themes/custom/forseti
npm install --production
npm run production  # Creates optimized CSS/JS
```

**Phase 2: Server Deployment**
```bash
# Upload to production server
rsync -avz --exclude='node_modules' --exclude='.git' \
  ./ user@production-server:/var/www/html/

# Or use SCP
scp -r ./drupal user@production-server:/var/www/html/
```

**Phase 3: Post-Deployment**
```bash
# On production server
cd /var/www/html/drupal/web

# Update database
../vendor/bin/drush updb -y

# Import configuration
../vendor/bin/drush cim -y

# Clear caches
../vendor/bin/drush cr

# Run security hardening
bash ../../scripts/production-security-hardening.sh
```

---

## Security Hardening

### Development vs Production Security

**Critical Development Environment Issues:**

1. **World-Writable Files**: Many files have 666 permissions
2. **Relaxed Directory Permissions**: Directories have 777 permissions
3. **Exposed Development Files**: README.md, configs, source files
4. **Default Database Credentials**: Using default/weak credentials
5. **Missing Security Headers**: No security headers configured
6. **Development Modules Active**: Devel module and debug tools enabled

### File Permissions Matrix

| File/Directory | Development | Production | Purpose |
|----------------|-------------|------------|---------|
| `sites/default/settings.php` | 666 | 444 | Read-only configuration |
| `sites/default/` | 777 | 555 | Read-only directory |
| `sites/default/files/` | 777 | 775 | Web server writable |
| `sites/default/files/*` | 666 | 664 | Web server writable files |
| All other files | 666 | 644 | Read-only for web |
| All directories | 777 | 755 | Standard web permissions |
| Private files | 777 | 600/700 | Restricted access |

### Production Ownership Requirements

```bash
# Production ownership (not codespace user)
chown -R www-data:www-data /var/www/html/drupal/web
```

### Files to Remove in Production

- ✅ `INSTALL.txt`, `README.md`, `CHANGELOG.txt`
- ✅ `example.gitignore`, `web.config`
- ✅ Development module directories (`devel/`, `simpletest/`)
- ✅ Theme source files (`src/`, `node_modules/`, `package.json`)
- ✅ Module documentation (`ARCHITECTURE.md`)

### Security Headers Configuration

**Apache (.htaccess):**
```apache
# Security Headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';"

# HSTS (only after SSL is working)
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

**Nginx:**
```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

### Automated Security Hardening Script

```bash
#!/bin/bash
# scripts/production-security-hardening.sh

echo "🔒 Starting Production Security Hardening..."

# 1. Set file permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# 2. Secure settings.php
chmod 444 sites/default/settings.php
chmod 555 sites/default/

# 3. Set writable directories
chmod 775 sites/default/files/
find sites/default/files/ -type f -exec chmod 664 {} \;
find sites/default/files/ -type d -exec chmod 775 {} \;

# 4. Remove development files
rm -f INSTALL.txt README.md CHANGELOG.txt
rm -f example.gitignore web.config

# 5. Disable development modules
../vendor/bin/drush pmu devel devel_generate webprofiler -y

# 6. Clear caches
../vendor/bin/drush cr

echo "✅ Security hardening complete!"
```

---

## Production Checklist

### Pre-Launch Checklist

**Security:**
- [ ] Run security hardening script
- [ ] Remove all development files
- [ ] Disable development modules (devel, webprofiler)
- [ ] Set proper file permissions (644/755)
- [ ] Configure security headers
- [ ] Enable HTTPS/SSL
- [ ] Update database credentials
- [ ] Disable error display
- [ ] Configure firewall rules

**Performance:**
- [ ] Enable page caching
- [ ] Configure CSS/JS aggregation
- [ ] Set up CDN (if applicable)
- [ ] Configure Redis/Memcache (if available)
- [ ] Optimize database queries
- [ ] Enable Gzip compression

**Monitoring:**
- [ ] Set up backup monitoring
- [ ] Configure error logging
- [ ] Set up uptime monitoring
- [ ] Enable security logging
- [ ] Configure email alerts

**Content:**
- [ ] Export and import configuration
- [ ] Verify all content migrated
- [ ] Test all forms and workflows
- [ ] Verify media files accessible
- [ ] Check user permissions

**Testing:**
- [ ] Smoke test all major features
- [ ] Test user registration/login
- [ ] Verify email functionality
- [ ] Test AmISafe API endpoints
- [ ] Check mobile responsiveness
- [ ] Cross-browser testing

### Post-Launch Checklist

**Day 1:**
- [ ] Monitor error logs
- [ ] Check backup completion
- [ ] Verify SSL certificate
- [ ] Test contact forms
- [ ] Monitor traffic/performance

**Week 1:**
- [ ] Review backup integrity
- [ ] Monitor security logs
- [ ] Check search engine indexing
- [ ] Review analytics setup
- [ ] Performance optimization review

**Month 1:**
- [ ] Security audit
- [ ] Backup restoration test
- [ ] Performance review
- [ ] User feedback collection
- [ ] Plan feature updates

### Deployment Rollback Procedure

If issues arise after deployment:

```bash
# 1. Restore from backup
cd /var/www/html
sudo tar -xzf /var/backups/forseti/weekly/backup-TIMESTAMP.tar.gz

# 2. Restore database
sudo -u www-data drupal/vendor/bin/drush sql:cli < backup-TIMESTAMP.sql

# 3. Fix permissions
sudo chown -R www-data:www-data forseti/
sudo chmod -R 755 forseti/

# 4. Clear caches
cd drupal/web
../vendor/bin/drush cr

# 5. Verify site functionality
../vendor/bin/drush status
```

### Maintenance Mode

**Enable Maintenance Mode:**
```bash
cd /var/www/html/drupal/web
../vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer
../vendor/bin/drush cr
```

**Disable Maintenance Mode:**
```bash
../vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer
../vendor/bin/drush cr
```

---

## Support & Resources

- **Backup Status**: `./scripts/backup-status.sh`
- **Security Hardening**: `./scripts/production-security-hardening.sh`
- **Deployment Scripts**: `/scripts/database/`
- **Configuration**: `/sites/forseti/config/sync/`

For additional documentation:
- **Forseti Mobile**: `/forseti-mobile/README.md`
- **H3 Geolocation**: `/h3-geolocation/README.md`
- **Database Exports**: `/database-exports/README.md`

---

**Last Updated**: February 2026
