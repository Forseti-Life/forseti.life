---
applyTo: '**'
---
# DEVELOPMENT PROCESS POLICY

**NO QUICK FIXES**: Always follow proper diagnostic and testing procedures. Never apply band-aid solutions or workarounds without understanding the root cause. Every issue must be:
1. Properly diagnosed with evidence gathering
2. Root cause analysis performed
3. Solution designed and validated
4. Changes tested before deployment
5. Documentation updated as needed

**PROCESS OVER SPEED**: Take time to understand the system, investigate thoroughly, and implement sustainable solutions that won't create technical debt.

---

Provide project context and coding guidelines that AI should follow when generating code, answering questions, or reviewing changes.

The environment is a Drupal 11 website repository for St. Louis Integration, a professional website focused on integration services and business solutions.

Dev/prod environment: The code is deployed on a production server with the following specifications:
- **Server**: Ubuntu 22.04 LTS
- **Drupal Root**: /var/www/html/stlouisintegration
- **Web Directory**: /var/www/html/stlouisintegration/web
- **Site Path**: sites/default
- **Drush Access**: Available via SSH to production server

ubuntu@ip-:~$ uname -a
Linux ip- 6.8.0-1031-aws #33-Ubuntu SMP Fri Jun 20 18:11:07 UTC 2025 x86_64 x86_64 x86_64 GNU/Linux
ubuntu@ip-:~$ php -version
PHP 8.3.6 (cli) (built: Jul 14 2025 18:30:55) (NTS)
Copyright (c) The PHP Group
Zend Engine v4.3.6, Copyright (c) Zend Technologies
    with Zend OPcache v8.3.6, Copyright (c), by Zend Technologies

ubuntu@ip-172-16-4-59:/var/www/html/stlouisintegration$ drush status
Drupal version   : 11.2.2
DB driver        : mysql
DB hostname      : localhost
DB port          : 3306
DB username      : drupal_user
DB name          : drupal_db
Database         : Connected
Drupal bootstrap : Successful
Default theme    : olivero
Admin theme      : claro
PHP binary       : /usr/bin/php8.3
Drush version    : 13.6.0.0
Drupal root      : /var/www/html/stlouisintegration


Skip giving "Immediate Fix:" solutions
Always generate the commit and push commands for the code changes after changes are generated and applied to the workspace. Do not include curl commands or testing commands just commit and push commands
Always highlight which file is recomended to update.
Always include the ARCHITECTURE.md and README.md files in the context.

When generating code, always assume the code is being written for the production server and validate which process flow it should be getting done in and where in that process flow is appropriate. If unsure, pause and ask where it should be and make suggestions.

When deciding architecture categorize functions into:
Sensors: data gathering
Processors: data transformation and analysis
Levers: actions taken based on processed data

Always assume we are troubleshooting on the production server
all curl testing functions should assume they need to run against the production URL:
https://stlouisintegration.com

Do not SSH out or create debug php files to be deployed to the server.
Always use the logging system outlined in the README.md for error handling and debugging.

When generating code, always ensure it is compatible with the current Drupal 11 environment and follows the coding standards outlined in the ARCHITECTURE.md.

I will copy and paste any command lines required.
Remember to sudo into www-data when appropriate
The autodeploy clears the cache on the server during deployment. Do not recomend a drush cr.

# St. Louis Integration Website - AI Coding Instructions

## Project Context
- **Platform**: St. Louis Integration - Professional integration services website
- **Environment**: Drupal 11 on Ubuntu Linux
- **Primary Focus**: Business integration services, professional consulting, and client solutions
- **Tech Stack**: Drupal 11, PHP 8.1+, MySQL

## Core Website Architecture
- **Custom modules**: Business-specific functionality and integrations
- **Custom themes**: Professional design and branding
- **Content types**: Services, case studies, team profiles, and contact forms

## Drupal Coding Standards
- Follow Drupal 11 coding standards and best practices
- Use dependency injection for services
- Implement proper access controls and permissions
- Use entity API for data manipulation
- Follow hook implementation patterns
- Use proper caching strategies for performance

## Database & Performance
- Optimize queries for efficient content delivery
- Use proper indexing on custom fields
- Implement efficient content queries
- Use caching for improved performance
- Optimize for professional website speed

## Integration Patterns
- Handle external API integrations gracefully
- Implement proper error handling for service failures
- Use structured data formats for business integrations
- Implement retry logic for failed connections
- Follow industry best practices for data exchange

## Security & Privacy
- Professional website accessible to public
- Admin dashboards require proper permissions
- Protect client data and business information
- Secure contact forms and lead generation
- Proper input sanitization for all user inputs

## Naming Conventions
- Functions: `modulename_descriptive_function_name()`
- Fields: `field_descriptive_name` (snake_case)
- Classes: PascalCase following PSR-4
- Routes: `modulename.route_name`
- Permissions: `access module_name feature`

## Code Organization
- Place business logic in module files, not controllers
- Use controllers only for request/response handling
- Implement helper functions for complex data processing
- Separate public and admin functionality clearly
- Document all custom functions with proper docblocks

## Error Handling & Logging
- Use Drupal's logging system: `\Drupal::logger('module_name')`
- Log important operations (AI processing, data updates)
- Handle exceptions gracefully with user-friendly messages
- Provide debug information for troubleshooting
- Use appropriate log levels (info, warning, error)

## Business Integration Best Practices
- **Contact Forms**: Lead capture and client communication
- **Service Listings**: Professional service presentation
- Implement proper form validation and submission handling
- Handle client inquiries and business communications
- Provide clear service descriptions and business information
- Ensure mobile-responsive design for all business content

## Professional Website Requirements
- Mobile-responsive design
- Fast loading times (<2 seconds)
- Clear service presentation
- Professional appearance and branding
- SEO-friendly structure
- Business contact and inquiry forms

## Business Website Patterns
- **Service Pages**: Professional service descriptions and offerings
- **Contact Forms**: Lead generation and client inquiry handling
- **Content Management**: Easy content updates for business information
- **SEO Optimization**: Search engine friendly content structure
- **Performance**: Fast loading and responsive design

## Testing & Debugging
- Test with various content types and forms
- Verify mobile responsiveness across devices
- Test contact forms and lead generation functionality
- Use Drush commands for debugging and maintenance
- Implement proper error reporting for failed operations

## Documentation Standards
- Comprehensive README.md files for each module
- Inline code documentation with examples
- API function documentation with return types
- Installation and configuration instructions
- Troubleshooting guides for common issues

## Business Website Specifics
- **Service Offerings**: Clear presentation of integration services
- **Case Studies**: Client success stories and project examples
- **Team Profiles**: Professional staff and expertise presentation
- **Contact Information**: Multiple ways for clients to reach the business
- **Lead Generation**: Forms and calls-to-action for business development

## Deployment Considerations
- Code must work in production Drupal 11 environment
- Consider memory usage for large data processing
- Implement proper cleanup for temporary data
- Use configuration management for settings
- Ensure compatibility with existing modules

## Business Website Workflow
1. **Content Creation** → Professional content for services and company information
2. **Form Processing** → Handle client inquiries and lead generation
3. **SEO Optimization** → Ensure search engine visibility
4. **Performance Monitoring** → Track website speed and functionality
5. **Client Communication** → Manage inquiries and business communications

## Website Interdependencies
- Custom modules provide business-specific functionality
- Custom themes ensure professional branding and design
- Content types support service offerings and client information
- Integration modules handle third-party business tools

## Performance Optimization
- Use Views for complex queries when possible
- Implement proper database indexing
- Cache expensive operations
- Optimize for concurrent processing
- Consider memory usage for batch operations

## User Experience Priorities
1. **Professional Presentation** - Clean, business-appropriate design
2. **Easy Navigation** - Intuitive site structure and user flow
3. **Fast Performance** - Quick loading and responsive interface
4. **Clear Communication** - Easy contact and inquiry processes
5. **Mobile Friendly** - Responsive design for all devices

## St. Louis Integration Specific Guidelines

### Business Website Context
- Focus on professional service presentation and client communication
- Emphasize business credibility and expertise
- Professional appearance suitable for business clients
- Clear service offerings and contact information

### Business Integration Philosophy
- Technology should enhance business operations
- Provide clear explanations of services and processes
- Focus on client needs and business solutions
- Handle client communications professionally

### Professional Website Priorities
- Business-appropriate design and content presentation
- Clear service descriptions and company information
- Mobile-friendly responsive design
- Fast loading with professional content
- SEO optimization for business visibility

### Content Management Patterns
- Easy content updates for business information
- Professional service and team content presentation
- Efficient contact form and inquiry handling
- Clear business communication and lead generation

### Drupal 11 Specific Features
- Use modern Drupal APIs and dependency injection
- Implement proper service containers
- Use entity query API for database operations
- Follow current caching and performance best practices

## Code Generation Preferences
- Always provide complete, production-ready code
- Include proper error handling and logging
- Follow Drupal 11 best practices and standards
- Optimize for performance with business content
- Include comprehensive documentation
- Ensure mobile responsiveness for business websites
- Implement proper security and access controls
- Focus on professional business presentation