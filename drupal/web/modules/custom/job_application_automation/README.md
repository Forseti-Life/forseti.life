# Job Application Automation Module

## Description
A comprehensive Drupal module for automating and managing job application processes. This module provides tools for tracking job applications, managing application workflows, storing documents, and integrating with external services.

## Features
- **Job Application Tracking:** Complete lifecycle management of job applications
- **Document Management:** Version-controlled storage of resumes, cover letters, and portfolios
- **Workflow Automation:** Automated status updates, reminders, and follow-ups
- **Analytics & Reporting:** Success metrics, performance tracking, and custom reports
- **External Integrations:** Job board APIs, calendar systems, and document storage
- **User-Friendly Interface:** Dashboard, bulk operations, and advanced filtering

## Installation
1. Place this module in `drupal/web/modules/custom/job_application_automation/`
2. Enable the module via Drush: `drush en job_application_automation`
3. Configure permissions at `/admin/people/permissions`
4. Access settings at `/admin/config/services/job-application-automation`

## Usage
- **Dashboard:** `/admin/job-applications` - Main application management interface
- **Add Application:** `/admin/job-applications/add` - Create new job applications
- **Settings:** `/admin/config/services/job-application-automation` - Module configuration

## Permissions
- `administer job application automation` - Full module administration
- `administer job applications` - Manage all applications
- `create job applications` - Create new applications
- `edit job applications` - Edit existing applications
- `view job applications` - View applications and data
- `delete job applications` - Delete applications
- `manage job application workflow` - Manage workflow states
- `view job application reports` - Access reports and analytics

## Architecture
See `ARCHITECTURE.md` for detailed system design, data models, and integration points.

## Development Status
**Current Phase:** Framework Setup Complete
- ✅ Basic module structure created
- ✅ Routing and permissions configured
- ✅ Architecture documentation complete
- 🔄 **Next:** Entity creation and form development

## Requirements
- Drupal 10 or 11
- PHP 8.1 or higher
- MySQL/PostgreSQL database

## Support
For issues and feature requests, please use the project repository:
https://github.com/keithaumiller/stlouisintegration.com

## License
This module is licensed under the GPL v2 or later.