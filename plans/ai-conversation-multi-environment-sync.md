# AI Conversation Module Multi-Environment Synchronization Plan

## Project Overview
**Objective:** Get AI conversation functionality working across all three environments with consistent AWS Bedrock integration.

**Current Status Assessment:**
- ✅ **truthperspective.org (production)** - AI conversation working with AWS Bedrock Claude 3.5 Sonnet
- ❌ **stlouisintegration.com (production)** - AI conversation module present but not functioning
- ❌ **stlouisintegration.com (GitHub Codespaces)** - AI conversation module needs proper setup

## Environmental Context

### Shared AWS Infrastructure
- **Server:** Single AWS EC2 Ubuntu 22.04 LTS instance
- **Multi-site Configuration:** Two separate Drupal installations with isolated databases
- **AWS Bedrock:** Claude 3.5 Sonnet model access via shared IAM roles
- **Potential Conflicts:** Shared credentials, different site configurations

### Development Environment Differences
- **Production:** Ubuntu 22.04 LTS on AWS EC2 with system PHP/Apache
- **Development:** Ubuntu 24.04.2 LTS in GitHub Codespaces containers
- **Credential Access:** IAM roles (production) vs environment variables (development)
- **Service Management:** systemd (production) vs service commands (containers)

## Troubleshooting Strategy

### Phase 1: Assessment and Documentation
**Goal:** Understand current state and identify root causes

#### Task 1: Production Multi-Site Audit
- **Module Status Check:**
  ```bash
  # Check truthperspective.org (working)
  cd /var/www/html/drupal && ./vendor/bin/drush --uri=thetruthperspective.org pm:list --type=module --status=enabled | grep ai_conversation
  
  # Check stlouisintegration.com (broken)
  cd /var/www/html/stlouisintegration/web && ../vendor/bin/drush --uri=stlouisintegration.com pm:list --type=module --status=enabled | grep ai_conversation
  ```

- **Configuration Comparison:**
  ```bash
  # Export working config
  cd /var/www/html/drupal && ./vendor/bin/drush --uri=thetruthperspective.org config:get ai_conversation.settings
  
  # Check broken config
  cd /var/www/html/stlouisintegration/web && ../vendor/bin/drush --uri=stlouisintegration.com config:get ai_conversation.settings
  ```

- **Error Log Analysis:**
  ```bash
  # Check truthperspective.org logs
  cd /var/www/html/drupal && ./vendor/bin/drush --uri=thetruthperspective.org watchdog:show --count=10 --type=ai_conversation
  
  # Check stlouisintegration.com logs
  cd /var/www/html/stlouisintegration/web && ../vendor/bin/drush --uri=stlouisintegration.com watchdog:show --count=10 --type=ai_conversation
  ```

#### Task 2: AWS Credential Investigation
- **IAM Role Verification:**
  ```bash
  # Check IAM role access from production server
  curl http://169.254.169.254/latest/meta-data/iam/security-credentials/
  
  # Test Bedrock API access
  aws bedrock list-foundation-models --region us-east-1
  ```

- **Environment Variable Check:**
  ```bash
  # Check for AWS credentials in environment
  env | grep AWS
  printenv | grep -i bedrock
  ```

#### Task 3: Module File Comparison
- **Compare module versions:**
  ```bash
  # Check file modifications and versions
  ls -la /var/www/html/drupal/web/modules/custom/ai_conversation/
  ls -la /var/www/html/stlouisintegration/web/modules/custom/ai_conversation/
  
  # Compare module info files
  diff /var/www/html/drupal/web/modules/custom/ai_conversation/ai_conversation.info.yml /var/www/html/stlouisintegration/web/modules/custom/ai_conversation/ai_conversation.info.yml
  ```

### Phase 2: Environmental Analysis
**Goal:** Identify specific differences causing the malfunction

#### Task 4: Database Schema Comparison
- **Content Type Analysis:**
  ```bash
  # Compare ai_conversation content types
  cd /var/www/html/drupal && ./vendor/bin/drush --uri=thetruthperspective.org config:export --destination=/tmp/truth_config
  cd /var/www/html/stlouisintegration/web && ../vendor/bin/drush --uri=stlouisintegration.com config:export --destination=/tmp/stlouis_config
  
  # Compare configurations
  diff -r /tmp/truth_config /tmp/stlouis_config | grep ai_conversation
  ```

#### Task 5: PHP and Drupal Version Compatibility
- **Version Verification:**
  ```bash
  # Check PHP versions on both sites
  cd /var/www/html/drupal && php --version
  cd /var/www/html/stlouisintegration && php --version
  
  # Check Drupal versions
  cd /var/www/html/drupal && ./vendor/bin/drush --uri=thetruthperspective.org status
  cd /var/www/html/stlouisintegration/web && ../vendor/bin/drush --uri=stlouisintegration.com status
  ```

### Phase 3: AWS Bedrock Connectivity Testing
**Goal:** Verify API access and credential functionality

#### Task 6: Bedrock API Testing
- **Direct API Tests:**
  ```bash
  # Test from truthperspective.org directory
  cd /var/www/html/drupal && aws bedrock invoke-model --region us-east-1 --model-id anthropic.claude-3-5-sonnet-20240620-v1:0 --body '{"messages":[{"role":"user","content":"Hello"}],"max_tokens":100}' --cli-binary-format raw-in-base64-out output.txt
  
  # Test from stlouisintegration.com directory
  cd /var/www/html/stlouisintegration && aws bedrock invoke-model --region us-east-1 --model-id anthropic.claude-3-5-sonnet-20240620-v1:0 --body '{"messages":[{"role":"user","content":"Hello"}],"max_tokens":100}' --cli-binary-format raw-in-base64-out output2.txt
  ```

#### Task 7: Module-Level API Testing
- **Drupal Module API Integration:**
  ```bash
  # Test AI service directly through Drupal
  cd /var/www/html/stlouisintegration/web && ../vendor/bin/drush --uri=stlouisintegration.com eval "print_r(\Drupal::service('ai_conversation.api')->testConnection());"
  ```

### Phase 4: Configuration Synchronization
**Goal:** Copy working configuration to broken environments

#### Task 8: Production Configuration Sync
- **Export/Import Process:**
  ```bash
  # Export working configuration
  cd /var/www/html/drupal && ./vendor/bin/drush --uri=thetruthperspective.org config:get ai_conversation.settings > /tmp/working_ai_config.yml
  
  # Import to stlouisintegration.com
  cd /var/www/html/stlouisintegration/web && ../vendor/bin/drush --uri=stlouisintegration.com config:set --input-format=yaml ai_conversation.settings < /tmp/working_ai_config.yml
  
  # Clear cache
  cd /var/www/html/stlouisintegration/web && ../vendor/bin/drush --uri=stlouisintegration.com cache:rebuild
  ```

#### Task 9: GitHub Codespaces Workspace Setup
- **Local Development Configuration:**
  - Install ai_conversation module in workspace
  - Configure AWS credentials for container environment
  - Test module functionality locally
  - Update deployment pipeline for proper module installation

### Phase 5: Deployment Pipeline Validation
**Goal:** Ensure GitHub Actions properly deploys AI conversation module

#### Task 10: Deployment Workflow Testing
- **GitHub Actions Verification:**
  - Test module deployment to stlouisintegration.com production
  - Verify configuration preservation during deployment
  - Validate cache clearing and module enablement
  - Test rollback procedures if needed

### Phase 6: End-to-End Testing
**Goal:** Verify functionality across all environments

#### Task 11: Comprehensive Testing Protocol
- **Functionality Verification:**
  - Create AI conversations in all environments
  - Test Claude 3.5 Sonnet integration
  - Verify error handling and logging
  - Test user interface consistency
  - Validate performance across environments

## Success Criteria

### Production Environments
- ✅ Both truthperspective.org and stlouisintegration.com have functional AI conversation modules
- ✅ AWS Bedrock integration works consistently across both sites
- ✅ Error handling and logging function properly
- ✅ User interface is consistent between environments

### Development Environment
- ✅ GitHub Codespaces workspace has fully functional AI conversation module
- ✅ Local development matches production functionality
- ✅ Deployment pipeline successfully updates production

### Operational Requirements
- ✅ Multi-site configuration prevents conflicts between sites
- ✅ AWS credential management is secure and efficient
- ✅ Troubleshooting documentation is complete and accurate
- ✅ Future deployments maintain functionality across all environments

## Risk Mitigation

### Potential Issues
- **Credential Conflicts:** Shared IAM roles may cause authentication issues
- **Configuration Drift:** Manual configuration changes may not persist through deployments
- **Version Incompatibilities:** Different Drupal/PHP versions between environments
- **Cache Issues:** Multi-site Drupal cache conflicts

### Mitigation Strategies
- **Backup Configurations:** Export all working configurations before making changes
- **Incremental Testing:** Test each change in isolation before proceeding
- **Rollback Plan:** Maintain ability to restore previous working state
- **Documentation:** Record all changes and decisions for future reference

## Next Actions
1. Begin with Production Multi-Site Audit (Task 1)
2. Document findings in this plan
3. Proceed through phases systematically
4. Update plan based on discoveries during troubleshooting

---
**Plan Created:** September 26, 2025  
**Last Updated:** September 26, 2025  
**Status:** Active - Phase 1 in Progress