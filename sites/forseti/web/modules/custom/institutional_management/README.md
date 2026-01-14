# Institutional Management Module

**Version**: 1.0  
**Drupal**: 10.3+ / 11  
**Dependencies**: Group module

## Overview

The Institutional Management module provides enterprise-level features for managing organizational accounts within the Forseti safety platform. It integrates with the Drupal Group module to provide multi-facility management, employee safety monitoring, and compliance reporting.

## Features

### Core Functionality
- **Institutional Groups**: Create and manage organizational structures using Drupal Groups
- **Member Management**: Add, remove, and manage employee accounts with granular permissions
- **Multi-Facility Support**: Monitor multiple locations and facilities from a central dashboard
- **Compliance Reporting**: Generate comprehensive safety and compliance reports
- **API Access**: Enterprise API endpoints for system integration

### Pages & Routes
- `/institutional` - Landing page with feature overview
- `/institutional/dashboard` - Main institutional dashboard (requires permission)
- `/admin/config/forseti/institutional` - Module settings (admin only)

### Permissions
- `access institutional dashboard` - View institutional dashboard
- `manage institution` - Create and manage institutional groups
- `manage institution members` - Add/remove members and assign roles
- `view institution reports` - Access compliance reports and analytics
- `administer institutional management` - Full administrative access (restricted)

## Installation

1. Enable the Group module and its dependencies:
   ```bash
   drush en group -y
   ```

2. Enable the Institutional Management module:
   ```bash
   drush en institutional_management -y
   ```

3. Clear cache:
   ```bash
   drush cr
   ```

4. Configure settings at `/admin/config/forseti/institutional`

## Configuration

### General Settings
- **Enable API access**: Allow institutions to access data via API
- **Maximum members per institution**: Set member limits per organization

### Compliance & Reporting
- **Enable compliance reporting**: Generate and provide compliance reports

## Usage

### Creating an Institution
1. Navigate to the institutional dashboard
2. Click "Create Institution"
3. Fill in organization details
4. Set up initial administrators

### Managing Members
1. Access the institution dashboard
2. Use "Add Member" to invite employees
3. Assign appropriate roles and permissions
4. Monitor member activity and assessments

### Viewing Reports
1. Navigate to institutional dashboard
2. Click "View Reports"
3. Select report type (compliance, safety, analytics)
4. Export or schedule automated reports

## Integration with Safety Calculator

This module integrates with the `safety_calculator` module to provide:
- Institutional safety assessments
- Employee safety monitoring
- Bulk location analysis
- Organizational safety benchmarks

## Development

### File Structure
```
institutional_management/
├── src/
│   ├── Controller/
│   │   └── InstitutionalController.php
│   └── Form/
│       └── InstitutionalSettingsForm.php
├── templates/
│   └── institutional-dashboard.html.twig
├── css/
│   └── institutional-dashboard.css
├── js/
│   └── institutional-dashboard.js
├── institutional_management.info.yml
├── institutional_management.module
├── institutional_management.routing.yml
├── institutional_management.permissions.yml
├── institutional_management.libraries.yml
└── README.md
```

### Extending Functionality

To add new institutional features:
1. Create controllers in `src/Controller/`
2. Add routes in `institutional_management.routing.yml`
3. Create templates in `templates/`
4. Add permissions in `institutional_management.permissions.yml`

## Support

For issues or feature requests, contact the Forseti development team.

## License

Proprietary - Forseti.life Platform
