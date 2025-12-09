# Forseti.life - Community Institution Platform

**Domain:** forseti.life  
**Created:** December 9, 2025  
**Platform:** Drupal 10  
**Purpose:** Community-focused institutional website embodying the Norse concept of justice, assembly, and collective action

## About Forseti

Named after the Norse god of justice, mediation, and assembly, Forseti.life represents a community institution platform focused on bringing people together for collective decision-making and fair governance.

### Norse Mythology Background
- **Forseti** - God of justice, law, and peaceful resolution
- His hall **Glitnir** was where disputes were settled
- Represents fair judgment, mediation, and assembly
- Perfect symbolism for an inclusive community institution

## Site Structure

```
forseti/
├── composer.json          # PHP dependencies
├── web/                   # Drupal webroot
│   ├── sites/
│   │   └── default/
│   │       ├── settings.php
│   │       ├── settings.local.php
│   │       └── files/
│   ├── modules/          # Custom modules
│   ├── themes/           # Custom themes
│   └── core/             # Drupal core
├── config/               # Configuration management
└── vendor/               # Composer dependencies
```

## Technical Details

- **CMS:** Drupal 10
- **Server:** Apache with mod_rewrite
- **Database:** MySQL/MariaDB
- **PHP:** 8.1+
- **SSL:** Required (Let's Encrypt recommended)

## Domain Configuration

### DNS Records
```
A     forseti.life          -> [SERVER_IP]
CNAME www.forseti.life      -> forseti.life
```

### Apache Virtual Host
Location: `/etc/apache2/sites-available/forseti.life.conf`

## Initial Setup Checklist

- [x] Create site directory structure
- [ ] Configure Apache virtual host
- [ ] Set up SSL certificate
- [ ] Configure DNS records
- [ ] Update settings.php with database credentials
- [ ] Run Drupal installation
- [ ] Configure site branding and theme
- [ ] Set up content types and workflows

## Related Sites

- **stlouisintegration.com** - Main portfolio and consulting site
- **theoryofconspiracies.com** - AI/GenAI demonstration site
- **forseti.life** - Community institution platform (this site)

## Contact

Keith Aumiller  
keith.aumiller@stlouisintegration.com  
https://stlouisintegration.com
