# H3 Geolocation Scripts Directory

## Purpose
Utility scripts and tools for H3 geolocation pipeline maintenance, data loading, and system administration tasks.

## Scripts Inventory

### `load_incidents_to_mysql.py`
**Purpose**: Direct MySQL data loading utility for incident records  
**Status**: 🔧 **UTILITY SCRIPT** - Specialized data loading tool  

**Functionality**:
- Direct CSV to MySQL loading bypassing full pipeline
- Batch processing with configurable parameters
- Error handling and recovery for failed records
- Progress tracking and performance monitoring

**Usage Pattern**:
```python
# Direct data loading for testing or recovery scenarios
python scripts/load_incidents_to_mysql.py --file data/raw/incidents_part1_part2.csv --batch-size 10000
```

**Integration Context**:
- Complements primary `database/amisafe_processor.py` pipeline
- Useful for data recovery and testing scenarios
- Alternative loading mechanism for specific use cases

## Script Categories

### Data Loading Utilities
- **Primary**: Database pipeline processors handle normal data loading
- **Specialized**: Scripts directory provides specialized loading tools
- **Recovery**: Emergency data loading and recovery procedures
- **Testing**: Development and testing data loading scenarios

### Administrative Scripts
- **Configuration**: System configuration and setup utilities
- **Maintenance**: Database maintenance and optimization scripts
- **Monitoring**: Pipeline monitoring and status checking tools
- **Backup**: Data backup and recovery procedures

## Usage Guidelines

### When to Use Scripts Directory
1. **Specialized Loading**: Non-standard data loading requirements
2. **Recovery Operations**: Emergency data recovery procedures  
3. **Testing Scenarios**: Development testing with specific datasets
4. **Administrative Tasks**: System maintenance and configuration

### When to Use Primary Pipeline
1. **Standard Processing**: Normal data ingestion and processing
2. **Production Workflows**: Complete ETL pipeline execution
3. **Quality Assurance**: Full validation and quality control
4. **Comprehensive Logging**: Complete audit trail and monitoring

## Development Standards

### Script Development
- **Error Handling**: Comprehensive exception handling and recovery
- **Logging**: Detailed logging for troubleshooting and audit
- **Configuration**: Configurable parameters via command line or config files
- **Documentation**: Inline documentation and usage examples

### Integration Patterns
- **Database Consistency**: Use same connection parameters as main pipeline
- **Schema Compliance**: Ensure compatibility with existing database schema
- **Transaction Management**: Proper transaction handling for data integrity
- **Performance Optimization**: Efficient processing for large datasets

## Configuration Management

### Database Configuration
```python
# Standard configuration pattern
MYSQL_CONFIG = {
    'host': '127.0.0.1',
    'user': 'drupal_user',
    'password': 'drupal_secure_password', 
    'database': 'theoryofconspiracies_dev',
    'port': 3306
}
```

### Processing Parameters
- **Batch Size**: Configurable for memory management (default 10,000)
- **Connection Pooling**: Efficient database connection management
- **Error Thresholds**: Configurable error tolerance for batch processing
- **Progress Reporting**: Regular status updates during processing

## Maintenance Procedures

### Script Maintenance
1. **Regular Testing**: Validate scripts with current database schema
2. **Performance Monitoring**: Track processing performance and optimization
3. **Error Analysis**: Review error logs and improve error handling
4. **Documentation Updates**: Keep usage documentation current

### Integration Testing
1. **Schema Compatibility**: Ensure scripts work with current database schema
2. **Pipeline Coordination**: Test integration with main pipeline components
3. **Performance Impact**: Validate scripts don't interfere with primary processing
4. **Data Integrity**: Verify scripts maintain data consistency and quality

## Future Development

### Planned Enhancements
1. **Monitoring Scripts**: System health and pipeline status monitoring
2. **Backup Utilities**: Automated backup and recovery procedures
3. **Performance Tools**: Database optimization and tuning utilities
4. **Configuration Management**: Enhanced configuration and deployment tools

### Integration Opportunities
1. **Pipeline Orchestration**: Enhanced integration with main pipeline
2. **API Development**: RESTful API utilities and testing tools
3. **Visualization Support**: Data export and visualization preparation scripts
4. **Quality Assurance**: Enhanced data validation and quality checking tools

---

**Last Updated**: November 2025  
**Script Count**: 1 active utility script  
**Integration**: Complements primary database pipeline processors  
**Related Documentation**: See [Database README](../database/README.md) for primary pipeline documentation