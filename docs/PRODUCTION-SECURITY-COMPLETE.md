# Production Security & Configuration Management

## Overview

We've created a comprehensive solution to handle the security differences between our development environment and production, with special attention to preserving production-specific Drupal configurations.

## 🔧 **Tools Created**

### 1. **Production Security Hardening Script**
**File:** `scripts/production-security-hardening.sh`

**What it does:**
- ✅ Fixes all file permission issues (666/777 → 644/755)
- ✅ Secures critical Drupal files (settings.php → 444, read-only)
- ✅ Removes development files and modules
- ✅ Applies Drupal production security settings
- ✅ **Preserves production-specific configurations**
- ✅ Creates automatic backups before changes
- ✅ Detects production vs development environment
- ✅ Sets up automated maintenance scripts

### 2. **Production Configuration Manager**  
**File:** `scripts/production-config-manager.sh`

**What it does:**
- 🔄 **Exports production configurations before updates**
- 🔄 **Imports development code while preserving production settings**
- 🔄 **Interactive backup and restore system**
- 🔄 **Production security settings management**
- 🔄 **Configuration status monitoring**

## 🚨 **Critical Issues Addressed**

### **Development Environment Problems:**
| Issue | Development | Production | Solution |
|-------|-------------|------------|----------|
| File permissions | 666/777 | 644/755 | Automated permission fixing |
| settings.php | 666 | 444 (read-only) | Automatic securing |
| Development files | Exposed | Removed | Automated cleanup |
| Error display | Enabled | Disabled | Drupal config management |
| User registration | Open | Admin-only | Drupal security settings |
| Debug modules | Active | Disabled | Module management |

## 📋 **Production-Specific Configuration Preservation**

### **Configurations That Are Preserved:**
```yaml
# System configurations
system.site          # Site name, email, slogan
system.mail          # Email server settings  
system.performance   # Caching and performance

# User and security
user.settings        # Registration, password policies
user.mail           # Email templates
contact.form.feedback # Contact form settings

# SEO and Analytics  
metatag.metatag_defaults.global # Meta tags
google_analytics.settings      # Analytics tracking

# Production-specific modules
automated_cron.settings        # Cron configuration
```

### **How Preservation Works:**

1. **Before Any Changes:**
   ```bash
   # Automatic backup of current production config
   drush config:export --destination=backup_$(date)
   
   # Extract production-specific settings
   drush config:get system.site > backup/system.site.yml
   drush config:get system.mail > backup/system.mail.yml
   # ... etc for all critical configs
   ```

2. **During Code Deployment:**
   ```bash
   # Import new development code/config
   drush config:import --source=development_config
   
   # Restore production-specific settings
   drush config:set system.site < backup/system.site.yml
   drush config:set system.mail < backup/system.mail.yml
   # ... etc
   ```

3. **After Deployment:**
   ```bash
   # Apply production security settings
   drush config:set system.logging error_level hide
   drush config:set user.settings register admin_only
   # ... etc
   ```

## 🔄 **Deployment Workflow Options**

### **Option 1: Manual Deployment with Scripts**
```bash
# 1. Export production configs (preserves settings)
./scripts/production-config-manager.sh
# Choose: "1. Export Production Configuration"

# 2. Deploy new code
rsync -avz ./ production-server:/var/www/html/

# 3. Run security hardening (applies new security + preserves configs)
./scripts/production-security-hardening.sh

# 4. Import new development config while preserving production
./scripts/production-config-manager.sh  
# Choose: "2. Import Development Configuration"
```

### **Option 2: Automated CI/CD (Recommended)**
```yaml
# GitHub Actions workflow
- name: Export Production Config
  run: ./scripts/production-config-manager.sh export

- name: Deploy Code
  run: rsync -avz ./ server:/var/www/html/

- name: Security Hardening + Config Preservation  
  run: ./scripts/production-security-hardening.sh

- name: Import Development Config (with preservation)
  run: ./scripts/production-config-manager.sh import_with_preservation
```

## 🛡️ **Security Features**

### **Automated Security Hardening:**
- **File Permissions:** All files → 644, directories → 755
- **Critical Files:** settings.php → 444 (read-only)
- **Development Cleanup:** Removes README.md, source files, dev modules
- **Drupal Security:** Disables error display, restricts user registration
- **Flood Control:** Prevents brute force attacks
- **Performance:** Enables caching, CSS/JS aggregation

### **Production Environment Detection:**
```bash
# Script automatically detects production environment
if [[ "$DRUPAL_ROOT" == *"/var/www/html"* ]]; then
    IS_PRODUCTION=true
    # Apply extra strict security settings
fi
```

### **Configuration Backup System:**
- **Automatic backups** before any changes
- **Restoration scripts** for rollback capability  
- **Weekly automated backups** with cleanup
- **Daily security checks** with alerting

## 🔍 **Monitoring and Maintenance**

### **Automated Daily Security Checks:**
```bash
# Created automatically by hardening script
/var/www/html/maintenance/daily_security_check.sh

# Checks:
- Security updates available
- Failed login attempts  
- File permission issues
- Configuration drift
```

### **Weekly Configuration Backups:**
```bash
# Automated weekly backups
/var/www/html/maintenance/weekly_config_backup.sh

# Backs up:
- Complete Drupal configuration
- Database dump
- Custom configurations
```

## 📊 **Production Readiness Checklist**

### **Automated by Scripts:** ✅
- [x] File permissions secured
- [x] Development files removed
- [x] Drupal security settings applied
- [x] Configuration backups created
- [x] Production settings preserved
- [x] Maintenance scripts installed

### **Manual Production Setup:** 📋
- [ ] SSL certificate (A+ grade)
- [ ] Web server security headers
- [ ] Database production credentials
- [ ] Trusted host patterns in settings.php
- [ ] Production email configuration (SMTP)
- [ ] Performance caching (Redis/Memcache)
- [ ] Monitoring and alerting setup

## 🚀 **Usage Examples**

### **First-Time Production Deployment:**
```bash
# 1. Run security hardening
./scripts/production-security-hardening.sh

# 2. Configure production-specific settings manually
# (SSL, database, email, etc.)

# 3. Test all functionality

# 4. Set up automated maintenance
crontab -e
# Add: 0 2 * * * /var/www/html/maintenance/daily_security_check.sh
```

### **Regular Updates (Code + Content):**
```bash
# 1. Backup current production config
./scripts/production-config-manager.sh
# Choose: "3. Backup Current Configuration"

# 2. Deploy new code
git pull origin main

# 3. Import development config while preserving production
./scripts/production-config-manager.sh
# Choose: "2. Import Development Configuration"

# 4. Verify everything works
./scripts/production-config-manager.sh  
# Choose: "6. Show Configuration Status"
```

## ✅ **Benefits Over Simple Post-Scripts**

### **Traditional Approach Problems:**
- ❌ Overwrites production settings
- ❌ No backup/rollback capability
- ❌ Manual configuration every time
- ❌ Easy to forget critical settings
- ❌ No environment detection

### **Our Solution Advantages:**
- ✅ **Preserves production configurations automatically**
- ✅ **Environment-aware security settings**
- ✅ **Automatic backup and restore capability**
- ✅ **Interactive management tools**
- ✅ **Comprehensive security hardening**
- ✅ **Ongoing maintenance automation**

This approach ensures that production-specific settings (like email configuration, analytics tracking, performance settings, etc.) are never lost during deployments, while still applying necessary security hardening and importing new development features.

## 🔧 **Next Steps**

1. **Test the scripts** in a staging environment
2. **Customize production-specific configurations** for your environment
3. **Set up SSL and web server security headers**  
4. **Configure monitoring and alerting**
5. **Execute first production deployment**
6. **Set up automated maintenance schedules**

The scripts handle the complex task of preserving production Drupal configurations while ensuring security compliance - no more lost settings or manual reconfiguration after deployments!