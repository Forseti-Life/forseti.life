# Forseti.life - AI-Powered Community Safety Platform

**Domain:** forseti.life  
**Created:** December 9, 2025  
**Platform:** Drupal 10  
**Mission:** Building safer communities through AI-powered monitoring and intelligent safety systems

## Our Mission

**"AI Looking Out For Us"** - Forseti is a safety-focused community platform dedicated to maintaining and improving quality of life through intelligent monitoring, predictive analytics, and community engagement.

### Primary Focus
- **Physical Safety** in the Philadelphia metropolitan area
- **AI-Powered Monitoring** for real-time threat detection
- **Quality of Life** improvements for as many people as possible
- **Community Engagement** through transparent data and actionable insights

## About Forseti

Named after the Norse god of justice and peaceful resolution, Forseti represents our commitment to fair, intelligent, and proactive safety measures that protect communities while respecting individual rights.

### Core Values
- **Vigilance** - 24/7 AI monitoring for community safety
- **Transparency** - Open data and clear communication
- **Justice** - Fair and unbiased safety measures for all
- **Community** - Empowering residents with knowledge and tools
- **Prevention** - Proactive measures to stop issues before they escalate

### Technology Stack
- **AI Crime Mapping** - H3 geospatial analysis of incident data
- **Predictive Analytics** - Machine learning for pattern recognition
- **Real-time Alerts** - Immediate notifications for safety events
- **Mobile Access** - AmISafe mobile app for on-the-go safety
- **Data Visualization** - Interactive maps and dashboards

## Features

### 🛡️ Core Safety Features
1. **Live Crime Mapping**
   - Real-time incident tracking across Philadelphia
   - H3 hexagonal grid visualization for precise location data
   - Heat maps showing crime density and patterns
   - Historical trend analysis

2. **AI-Powered Alerts**
   - Intelligent pattern recognition for emerging threats
   - Predictive modeling for high-risk areas and times
   - Personalized safety notifications based on location
   - Community-sourced incident reporting

3. **Safety Dashboard**
   - Personal safety score for your neighborhood
   - Real-time safety status updates
   - Incident statistics and trends
   - Emergency resource locations (police, hospitals, shelters)

4. **Community Engagement**
   - Neighborhood watch coordination
   - Safety event calendar
   - Community forum for safety discussions
   - Resource sharing and mutual aid

### 📱 Mobile Integration
- **AmISafe Mobile App** - Native iOS and Android applications
- Location-based safety alerts
- One-touch emergency services
- Offline safety resources
- Community check-in features

### 🔐 Privacy & Security
- End-to-end encrypted communications
- Anonymous incident reporting options
- GDPR and privacy law compliant
- No sale or sharing of personal data
- Transparent data usage policies

## Technical Details

### Platform
- **CMS:** Drupal 10
- **Server:** Apache 2.4+ with mod_rewrite
- **Database:** MySQL 8.0+ / MariaDB 10.5+
- **PHP:** 8.1+ (8.3 recommended)
- **SSL:** Required (Let's Encrypt recommended)

### AI/ML Stack
- **H3 Geospatial System** - Uber's H3 hexagonal hierarchical geospatial indexing
- **Python Analytics Engine** - pandas, numpy, scikit-learn
- **Data Pipeline** - ETL processing for crime data
- **Visualization** - Folium, Plotly, D3.js

### APIs & Integrations
- Philadelphia Police Department Open Data
- Emergency Services Integration
- Weather and Environmental Data
- Mobile Push Notifications

## Installation & Setup

### Quick Start (Development)

```bash
# Navigate to project root
cd /home/keithaumiller/stlouisintegration.com

# Run automated setup script (includes Forseti)
sudo bash script/setup.sh
```

The setup script automatically:
- Creates forseti_dev database
- Configures Apache on port 8080
- Installs Drupal 11.2.5
- Enables forseti theme
- Sets up private files directory
- Configures development settings

### Manual Setup

1. **Create Database**
```bash
mysql -u root -p
CREATE DATABASE forseti_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON forseti_dev.* TO 'drupal_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

2. **Configure Apache** (Port 8080)
```bash
sudo nano /etc/apache2/sites-available/forseti.conf
sudo a2ensite forseti.conf
sudo systemctl restart apache2
```

3. **Install Drupal**
```bash
cd /home/keithaumiller/stlouisintegration.com/sites/forseti
./vendor/bin/drush site:install standard \
  --db-url="mysql://drupal_user:password@127.0.0.1:3306/forseti_dev" \
  --site-name="Forseti" \
  --account-name="admin" \
  --yes
```

4. **Enable Theme**
```bash
./vendor/bin/drush theme:enable forseti -y
./vendor/bin/drush config:set system.theme default forseti -y
./vendor/bin/drush cache:rebuild
```

### Access URLs

- **Development:** http://localhost:8080 or http://100.115.92.203:8080
- **Alternative:** http://penguin.linux.test:8080 (Chrome OS Linux)
- **Production:** https://forseti.life (when configured)

### Build Theme Assets

```bash
cd /home/keithaumiller/stlouisintegration.com/sites/forseti/web/themes/custom/forseti
npm install
npm run build
```

## Site Structure

```
forseti/
├── composer.json              # PHP dependencies
├── web/                       # Drupal webroot
│   ├── sites/
│   │   └── default/
│   │       ├── settings.php
│   │       ├── settings.local.php
│   │       └── files/
│   │           └── amisafe/   # AmISafe mobile app files
│   ├── modules/
│   │   └── custom/            # Safety modules
│   ├── themes/
│   │   └── custom/
│   │       └── forseti/       # Safety community theme
│   └── core/                  # Drupal core
├── config/                    # Configuration management
│   └── sync/                  # Exported configs
└── vendor/                    # Composer dependencies
```

## Development Commands

```bash
# Navigate to Forseti site
cd /home/keithaumiller/stlouisintegration.com/sites/forseti

# Clear cache
./vendor/bin/drush cache:rebuild

# One-time login link
./vendor/bin/drush user:login

# Export configuration
./vendor/bin/drush config:export -y

# Import configuration
./vendor/bin/drush config:import -y

# Check site status
./vendor/bin/drush status
```

## Production Deployment

### DNS Configuration
```
A     forseti.life          -> [PRODUCTION_IP]
CNAME www.forseti.life      -> forseti.life
```

### SSL Certificate
```bash
sudo certbot --apache -d forseti.life -d www.forseti.life
```

### Apache Virtual Host (Production)
```apache
<VirtualHost *:443>
    ServerName forseti.life
    ServerAlias www.forseti.life
    DocumentRoot /var/www/forseti.life/web
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/forseti.life/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/forseti.life/privkey.pem
    
    <Directory /var/www/forseti.life/web>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Security Checklist

- [ ] Remove settings.local.php in production
- [ ] Set restrictive file permissions (644 for files, 755 for directories)
- [ ] Enable SSL/TLS with valid certificate
- [ ] Configure firewall rules
- [ ] Set up automated backups
- [ ] Enable security updates
- [ ] Configure fail2ban for brute force protection
- [ ] Set up monitoring and alerting

## Related Sites

- **stlouisintegration.com** (Port 80) - Main portfolio site
- **forseti.life** (Port 8080) - Safety community platform (this site)
- **theoryofconspiracies.com** (Port 8081) - AI/GenAI demonstration site

## Contact

Keith Aumiller  
Email: keith.aumiller@forseti.life  
Web: https://forseti.life

---

**Mission:** AI Looking Out For Us - Building safer communities through intelligent monitoring and community engagement.
