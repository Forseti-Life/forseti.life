# National Firefighter Registry (NFR) Module

## Overview

The National Firefighter Registry (NFR) module is a CDC cancer surveillance and health tracking system for firefighters. This module supports the collection, management, and analysis of cancer incidence data among firefighters nationwide.

## Key Features

### 1. Participant Registration
- Comprehensive firefighter profile collection
- Demographics and career information tracking
- Consent management for research participation
- User-friendly registration workflow

### 2. Cancer Data Collection
- Cancer incidence tracking and reporting
- Cancer type and stage documentation
- Diagnosis date recording
- De-identified data storage for research

### 3. State Cancer Registry Linkages
- Integration with state cancer registries
- Consent-based linkage system
- Batch processing capabilities
- Linkage statistics and monitoring
- Multi-state coordination support

### 4. USFA NERIS Integration
- Data ingestion from USFA National Emergency Response Information System
- Bi-directional synchronization
- NERIS ID tracking and validation
- API-based data exchange

### 5. Longitudinal Data Collection
- Follow-up survey management
- Time-series health data tracking
- Survey versioning and updates
- JSON-based flexible data storage

### 6. Data Analysis & Dashboards
- Summary statistics generation
- Cancer incidence by type and location
- Participation metrics by state
- State registry linkage rates
- Public-facing data dashboard
- De-identified data export capabilities

## Database Schema

The module creates three primary tables:

### nfr_firefighters
Core firefighter registry data:
- Personal information (first/last name)
- Department and state
- Badge number
- Career type (career/volunteer/both)
- Years of service
- NERIS ID linkage
- Status tracking

### nfr_cancer_data
Cancer incidence tracking:
- Cancer type and stage
- Diagnosis date
- State registry linkage status
- State registry ID
- Foreign key to firefighter record

### nfr_longitudinal_data
Follow-up data collection:
- Survey date and type
- JSON-encoded survey responses
- Foreign key to firefighter record

## Services

### NERISIntegration Service
- `importFromNERIS($neris_id)`: Import firefighter data from NERIS
- `syncWithNERIS($firefighter_id)`: Sync existing records with NERIS

### CancerRegistryLinkage Service
- `linkToStateRegistry($cancer_data_id, $state_registry_id)`: Link cancer record to state registry
- `getLinkageStatistics()`: Get linkage statistics by state
- `processBatchLinkage($state)`: Process batch linkages for a state

### DataExport Service
- `exportSummaryStatistics()`: Generate summary statistics for dashboards
- `exportToCSV($type, $filters)`: Export de-identified data to CSV

## Permissions

- **Access NFR Dashboard**: View the NFR dashboard
- **Administer NFR**: Configure module settings
- **Manage Firefighters**: Create, edit, and delete firefighter records
- **View Firefighter Records**: View firefighter registry records
- **View Cancer Data**: Access cancer incidence and statistics
- **Manage Cancer Data**: Create, edit, and delete cancer records
- **Export NFR Data**: Export registry data for analysis (restricted)
- **Manage State Registry Linkages**: Configure state registry integrations (restricted)

## Routes & Pages

- `/nfr/dashboard`: Main dashboard with summary statistics
- `/nfr/register`: Public registration form
- `/nfr/firefighters`: Firefighter list view
- `/nfr/data-dashboard`: Public data dashboard
- `/nfr/cancer-data`: Cancer data summary
- `/admin/config/nfr/settings`: Module configuration

## Configuration

Access module settings at `/admin/config/nfr/settings`

### General Settings
- Email notifications
- Default certification period
- Badge number requirements

### State Cancer Registry Integration
- Automatic linkage enablement
- Consent requirements
- Batch processing options

### USFA NERIS Integration
- NERIS synchronization toggle
- API endpoint configuration
- API key management

### Data Export Settings
- Public dashboard visibility
- Data anonymization options
- Export format preferences

## Development

### Directory Structure

```
nfr/
├── css/
│   └── nfr-dashboard.css
├── js/
│   └── nfr-dashboard.js
├── src/
│   ├── Commands/
│   │   └── NFRCommands.php
│   ├── Controller/
│   │   └── NFRController.php
│   ├── Form/
│   │   ├── NFRRegistrationForm.php
│   │   └── NFRSettingsForm.php
│   └── Service/
│       ├── NERISIntegration.php
│       ├── CancerRegistryLinkage.php
│       └── DataExport.php
├── css/
│   ├── nfr-dashboard.css
│   └── nfr-documentation.css
├── js/
│   └── nfr-dashboard.js
├── templates/
│   ├── nfr-dashboard.html.twig
│   ├── nfr-documentation.html.twig
│   └── nfr-documentation-page.html.twig
├── documents/                      # Project documentation
│   ├── README.md
│   ├── BUSINESS_REQUIREMENTS.md
│   ├── USER_ROLES_AND_PROCESS_FLOWS.md
│   ├── PAGE_SPECIFICATIONS.md
│   └── *.pdf                       # CDC official documents
├── nfr.info.yml
├── nfr.module
├── nfr.routing.yml
├── nfr.links.menu.yml
├── nfr.permissions.yml
├── nfr.libraries.yml
├── nfr.services.yml
├── drush.services.yml
├── nfr.install
├── README.md
├── ARCHITECTURE.md
├── INSTALLATION.md
└── DRUPAL11_COMPLIANCE.md
```

## Frontend Architecture

The NFR module follows Forseti's centralized theming patterns:

- **Templates**: Twig templates in `templates/` directory for all rendered output
- **Styling**: Module-specific CSS in `css/` directory
- **Theme Integration**: All libraries depend on `forseti/style` for consistent theming
- **No Inline Markup**: Controllers return structured render arrays, templates handle presentation
- **Assets**: Defined in `nfr.libraries.yml` with proper dependencies

## Documentation

### Web-Based Documentation
Visit `/nfr/documentation` for interactive access to all project documentation including:
- Development Documentation (Business Requirements, User Roles, Page Specifications)
- CDC Official Documents (Protocol, User Profile Form, Enrollment Questionnaire)
- Additional Technical Documentation

### Core Documentation
- **[README.md](README.md)** - This file, module overview and features
- **[ARCHITECTURE.md](ARCHITECTURE.md)** - System architecture and design patterns
- **[INSTALLATION.md](INSTALLATION.md)** - Installation, deployment, and configuration guide
- **[DRUPAL11_COMPLIANCE.md](DRUPAL11_COMPLIANCE.md)** - Drupal 11 standards compliance documentation

### Project Documentation
- **[documents/BUSINESS_REQUIREMENTS.md](documents/BUSINESS_REQUIREMENTS.md)** - Complete business requirements
- **[documents/USER_ROLES_AND_PROCESS_FLOWS.md](documents/USER_ROLES_AND_PROCESS_FLOWS.md)** - User workflows
- **[documents/PAGE_SPECIFICATIONS.md](documents/PAGE_SPECIFICATIONS.md)** - Page-level specifications

### Quick Links
- **Getting Started**: See [INSTALLATION.md](INSTALLATION.md) for setup instructions
- **Architecture Overview**: See [ARCHITECTURE.md](ARCHITECTURE.md) for system design
- **Code Standards**: See [DRUPAL11_COMPLIANCE.md](DRUPAL11_COMPLIANCE.md) for compliance details
- **Full Documentation**: Visit `/nfr/documentation` on your site

## Version

1.0.0

## License

Proprietary
