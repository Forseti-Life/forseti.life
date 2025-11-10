# Complete Setup Script - Dependency Order

## Correct Dependency Chain in complete-setup.sh

The script now ensures proper dependency order for module and theme enablement:

### Phase 1: System Dependencies
1. **PHP 8.3** - Install and configure
2. **MySQL** - Install and configure  
3. **Apache** - Install and configure with PHP 8.3 module
4. **Composer** - Install and verify with PHP 8.3

### Phase 2: Database Setup
1. **MySQL databases** - Create stlouisintegration_dev and theoryofconspiracies_dev
2. **MySQL user** - Create drupal_user with proper permissions

### Phase 3: Drupal Installation
1. **Composer dependencies** - Install/repair if needed
2. **Drupal core installation** - Only if database tables don't exist
3. **Database verification** - Check that 'users' table exists

### Phase 4: Module Enablement (Only after Drupal is functional)
1. **Drupal bootstrap verification** - Ensure Drupal can bootstrap successfully
2. **Development modules** (prerequisites):
   - devel
   - admin_toolbar 
   - admin_toolbar_tools
   - pathauto
   - metatag
   - token
3. **Development modules verification** - Confirm they're enabled before proceeding
4. **Dependency modules**:
   - profile (required by job_application_automation)
   - entity (auto-installed with profile)
5. **Custom modules in dependency order**:
   - professional_website_content (base module)
   - ai_conversation
   - stli_site_customizations  
   - resume_tailoring
   - **Cache rebuild** (before complex module)
   - job_application_automation (most complex, enabled last)

### Phase 5: Theme Setup (Only after modules are working)
1. **Theme installation** - Enable stlouisintegration theme
2. **Default theme setting** - Set as site default
3. **Final verification** - Cache rebuild and functionality test

## Safety Mechanisms

- **Bootstrap verification**: Checks Drupal can bootstrap before enabling any modules
- **Database table verification**: Confirms installation before module enablement  
- **Development module verification**: Ensures prerequisites before custom modules
- **Error handling**: Uses `2>/dev/null || true` for non-critical operations
- **Cache rebuilds**: Strategic cache clearing between complex operations
- **Final verification**: Confirms everything works after all changes

## Conditional Logic

```bash
if [ "$DRUPAL_INSTALLED" = true ]; then
  # Verify bootstrap works
  if drush status shows successful bootstrap; then
    # Enable development modules
    if development modules enabled successfully; then
      # Enable custom modules in order
      # Enable theme
      # Final verification
    fi
  fi
fi
```

This ensures that each phase only runs if all previous dependencies are satisfied.