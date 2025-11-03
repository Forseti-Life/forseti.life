# H3 Geolocation Configuration Directory

## Purpose
Configuration files for the H3 geolocation framework, managing database connections, API settings, and environment-specific parameters.

## Files

### `mysql_config.json`
MySQL database configuration for the H3 pipeline system.

**Structure**:
```json
{
  "host": "127.0.0.1",
  "user": "drupal_user", 
  "password": "drupal_secure_password",
  "database": "theoryofconspiracies_dev",
  "port": 3306
}
```

**Purpose**: 
- Database connection parameters for H3 data pipeline
- Used by Transform and Final layer processors
- Configured for development environment with shared credentials

**Usage**:
- Referenced by `amisafe_transform_processor_v2.py`
- Loaded by H3 framework initialization scripts
- Environment-specific configuration management

## Configuration Standards

### Database Connection
- **Host**: 127.0.0.1 (development) vs localhost (production)
- **Credentials**: Shared drupal_user for development consistency
- **Database**: theoryofconspiracies_dev for H3 pipeline data
- **Port**: Standard MySQL port 3306

### Security Considerations
- Development credentials documented for team consistency
- Production credentials managed separately via environment variables
- Connection strings validated during pipeline initialization

## Environment Configuration

### Development Environment
- Uses shared drupal_user credentials for team development
- Database: theoryofconspiracies_dev with full H3 pipeline tables
- Host: 127.0.0.1 for development container networking

### Production Environment
- Separate credential management (not in version control)
- Encrypted connection strings and certificate management
- Environment-specific database names and access controls

## Usage Patterns

### Pipeline Configuration Loading
```python
# Standard configuration loading pattern
import json
with open('config/mysql_config.json', 'r') as f:
    mysql_config = json.load(f)
```

### Error Handling
- Configuration validation during pipeline startup
- Fallback to environment variables if config file missing
- Connection testing before pipeline execution

## Maintenance

### Configuration Updates
1. Update configuration files for environment changes
2. Test database connectivity after configuration changes
3. Document any new configuration parameters
4. Ensure team synchronization for development settings

### Security Review
- Regular review of credential exposure in configuration files
- Environment-specific access control validation
- Connection string encryption for production environments

---

**Last Updated**: November 2025  
**Related Documentation**: See [H3 Pipeline README](../README.md) for complete system overview