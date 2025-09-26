# stlouisintegration.com
Drupal website and functionality

## Development Methodology - Architecture-First Approach

### **⚠️ CRITICAL: Architecture-First Development Process**

This project follows a strict **Architecture-First Development Methodology**. All development work must follow this process:

#### **1. Architecture Documentation Requirements**
- **Every module/feature MUST have a comprehensive ARCHITECTURE.md file**
- **Architecture documentation MUST be created/updated BEFORE any code is written**
- **The architecture document serves as the single source of truth for all development decisions**
- **All changes to functionality require architecture document updates FIRST**

#### **2. Development Workflow (MANDATORY)**
```
1. READ the existing ARCHITECTURE.md file completely
2. UPDATE the ARCHITECTURE.md file with proposed changes
3. REVIEW and APPROVE architecture changes
4. IMPLEMENT code following the approved architecture
5. UPDATE architecture status indicators as work is completed
6. TEST implementation against architecture specifications
7. UPDATE final status indicators to COMPLETED
```

#### **3. Architecture Document Standards**
Each ARCHITECTURE.md file MUST include:
- **Status Legend:** Clear indicators for TODO, COMPLETED, SHELVED items
- **Development Milestones:** Specific, measurable tasks for each feature
- **Success Criteria:** Clear acceptance criteria for each component
- **Integration Points:** How components connect to other system parts
- **Testing Requirements:** Specific testing tasks and validation criteria
- **Installation/Uninstall:** Complete module lifecycle management

#### **4. Status Tracking Requirements**
- **[TODO]** - Feature needs to be implemented
- **[TODO - MVP PRIORITY]** - Critical MVP feature requiring immediate implementation
- **[TODO - BASIC ONLY]** - Simplified version for MVP, enhanced version later
- **[COMPLETED]** - Feature fully implemented and tested
- **[SHELVED]** - Feature noted but not included in current scope
- **[NOTED]** - Feature acknowledged but deferred to future phases

#### **5. File Structure Requirements**
```
/module_directory/
├── ARCHITECTURE.md (REQUIRED - comprehensive design document)
├── module_name.info.yml
├── module_name.module
├── src/
├── config/
└── tests/
```

#### **6. Enforcement Rules**
- **NO CODE MAY BE WRITTEN WITHOUT CURRENT ARCHITECTURE DOCUMENTATION**
- **ALL PULL REQUESTS MUST INCLUDE ARCHITECTURE UPDATES**
- **Architecture documents must be updated BEFORE implementation**
- **Status indicators must be updated as work progresses**
- **Architecture changes require review and approval**
- **Testing must validate against architecture specifications**

### **Job Application Automation Module Example**
See `/drupal/web/modules/custom/job_application_automation/ARCHITECTURE.md` for the gold standard example of comprehensive architecture documentation including:
- Complete development roadmap with 180+ specific tasks
- 5-phase development timeline (12 weeks)
- Detailed milestones for each process flow
- Success metrics and acceptance criteria
- Module installation and uninstall procedures
- Cross-component integration specifications

## Project Overview

### Architecture-First Benefits
- **Reduced Development Time:** Clear specifications prevent rework and scope creep
- **Better Code Quality:** Comprehensive planning leads to better implementation
- **Easier Maintenance:** Documentation stays current and accurate
- **Team Coordination:** Everyone understands the complete system design
- **Risk Mitigation:** Issues identified in planning phase, not during implementation
- **Scalable Development:** Foundation for future enhancements is well-planned

### Development Standards

#### **Pre-Development Checklist**
Before starting any development work:
- [ ] **Read existing architecture completely**
- [ ] **Understand all integration points and dependencies**
- [ ] **Review current status indicators and priorities**
- [ ] **Plan your specific tasks within the overall architecture**
- [ ] **Update architecture document with your planned changes**
- [ ] **Get architecture review and approval if needed**

#### **During Development Checklist**
While implementing features:
- [ ] **Follow architecture specifications exactly**
- [ ] **Update status indicators as milestones are completed**
- [ ] **Document any deviations from planned architecture**
- [ ] **Test implementation against architecture success criteria**
- [ ] **Update integration points as they are completed**

#### **Post-Development Checklist**
After completing development work:
- [ ] **Update all status indicators to COMPLETED**
- [ ] **Document any architecture changes discovered during implementation**
- [ ] **Update success metrics and validation criteria**
- [ ] **Test complete feature against architecture specifications**
- [ ] **Update installation/uninstall procedures if needed**

#### **Code Review Requirements**
All code reviews must verify:
- [ ] **Architecture document is current and accurate**
- [ ] **Status indicators reflect actual implementation state**
- [ ] **Code follows architecture specifications**
- [ ] **Testing validates architecture success criteria**
- [ ] **Integration points work as specified**
- [ ] **Documentation is complete and accurate**

### Module Development Guidelines

#### **New Module Creation Process**
1. **Create module directory structure**
2. **Copy `/ARCHITECTURE_TEMPLATE.md` to `/module_directory/ARCHITECTURE.md`**
3. **Fill out comprehensive ARCHITECTURE.md file with all required sections**
4. **Include all required sections (see job_application_automation example)**
5. **Get architecture review and approval**
6. **Begin implementation following architecture specifications**
7. **Update status indicators throughout development**
8. **Test against architecture success criteria**

#### **Architecture Template Usage**
- **Use `/ARCHITECTURE_TEMPLATE.md` as the starting point for all new modules**
- **Replace all placeholder text with module-specific information**
- **Add additional sections as needed for complex modules**
- **Follow the job_application_automation example for comprehensive coverage**
- **Ensure all TODO items are properly categorized and prioritized**

#### **Existing Module Modification Process**
1. **Read current ARCHITECTURE.md file completely**
2. **Update architecture with proposed changes**
3. **Review integration points and dependencies**
4. **Get architecture change approval**
5. **Implement changes following updated architecture**
6. **Update status indicators as work progresses**
7. **Test changes against updated success criteria**

### Quality Assurance Standards
- **Architecture Documentation:** Must be comprehensive, current, and accurate
- **Status Tracking:** Must reflect actual implementation state
- **Testing Coverage:** Must validate all architecture success criteria
- **Integration Testing:** Must verify all integration points work correctly
- **Performance Standards:** Must meet architecture performance requirements
- **Security Standards:** Must implement architecture security specifications

This methodology ensures consistent, high-quality development with comprehensive documentation that stays current throughout the project lifecycle.

## Architecture-First Development Tools & Enforcement

### **Automated Validation Tools**
Consider implementing these tools to enforce the architecture-first methodology:

#### **Pre-Commit Hooks**
```bash
# Check that ARCHITECTURE.md exists for any modified modules
# Validate that status indicators are properly formatted
# Ensure architecture changes are included in commits
```

#### **CI/CD Pipeline Checks**
```bash
# Verify ARCHITECTURE.md exists and follows template
# Check that status indicators match implementation state  
# Validate that all TODO items have proper priority levels
# Test implementation against architecture success criteria
```

### **Architecture Review Process**

#### **Required Reviews**
- **New Module Architecture:** Full architecture review before any code
- **Major Feature Changes:** Architecture update review before implementation
- **Status Updates:** Regular review of status indicator accuracy
- **Integration Changes:** Review of integration points and dependencies

#### **Review Checklist**
- [ ] **Architecture completeness:** All required sections included
- [ ] **Status accuracy:** Status indicators reflect actual state
- [ ] **Integration clarity:** All integration points clearly documented
- [ ] **Testing coverage:** Success criteria are measurable and testable
- [ ] **Implementation feasibility:** Architecture is realistic and achievable

### **Documentation Maintenance**

#### **Regular Architecture Updates**
- **Weekly:** Update status indicators as work progresses
- **Sprint End:** Review and update architecture for completed work
- **Major Releases:** Comprehensive architecture review and cleanup
- **New Team Members:** Architecture orientation and training

#### **Architecture Audit Process**
Periodic audits should verify:
- [ ] **Currency:** Architecture reflects current implementation state
- [ ] **Accuracy:** Status indicators match actual code state
- [ ] **Completeness:** All system components have architecture coverage
- [ ] **Consistency:** Architecture follows established standards and patterns
- [ ] **Usability:** Documentation is clear and actionable for developers

### **Training & Onboarding**

#### **New Developer Onboarding**
1. **Architecture-First Training:** Understanding the methodology and benefits
2. **Template Usage:** How to use ARCHITECTURE_TEMPLATE.md effectively
3. **Status Management:** Proper use of status indicators and milestones
4. **Review Process:** How architecture reviews work and expectations
5. **Example Study:** Deep dive into job_application_automation architecture

#### **Ongoing Education**
- **Architecture Best Practices:** Regular sharing of lessons learned
- **Tool Updates:** Training on new tools and validation processes
- **Success Stories:** Sharing examples of successful architecture-first development

This comprehensive approach ensures that architecture documentation remains a valuable, current resource that drives development decisions and maintains system quality throughout the project lifecycle.

## Tech Stack & Architecture

### **AI Services Foundation**

#### **AWS Bedrock Integration** - Core AI Service Provider
- **Service:** Amazon Bedrock with Claude 3.5 Sonnet (anthropic.claude-3-5-sonnet-20240620-v1:0)
- **Region:** us-west-2
- **Authentication:** Environment variables or IAM roles (no hardcoded credentials)
- **SDKs:** AWS SDK for PHP (Bedrock Runtime API)
- **Purpose:** Foundational AI service for resume tailoring, conversational AI, and job application automation

#### **AI Module Architecture**
```
ai_conversation (Core AI Service Provider)
    ↓
├── resume_tailoring (AI-Powered Resume Tailoring)
├── job_application_automation (Job Application Automation)
└── Future AI-powered modules
```

### **Core Platform**

#### **Drupal 11.2.4** - Content Management System
- **Version:** 11.2.4 with latest security updates
- **Environment:** Dev Container (Ubuntu 24.04.2 LTS)
- **Database:** PostgreSQL (configured via dev container)
- **Web Server:** Apache/Nginx (dev container managed)
- **PHP Version:** 8.3+ (Drupal 11 requirement)

### **External APIs & Services**

#### **Diffbot API** - Web Intelligence Platform
- **Service:** Web scraping and content extraction
- **Purpose:** Job posting extraction and company data analysis
- **Integration:** job_application_automation module
- **Security:** Environment variable configuration (regenerate exposed key)

## Current Module Status

### **Core AI Services**

#### **ai_conversation/** - AWS Bedrock AI Service Provider ✅
- **Status:** [COMPLETED] - Production-ready and TESTED working AI conversation management module
- **Purpose:** Sophisticated AI conversation system with rolling summary management
- **Architecture:** `/drupal/web/modules/custom/ai_conversation/ARCHITECTURE.md`
- **AWS Integration:** Bedrock Runtime API with Claude 3.5 Sonnet model
- **Recent Fix (Sept 26, 2025):** Resolved 500 error by fixing missing service dependency
- **Key Features:**
  - Rolling conversation summaries (every 10 messages)  
  - Multi-credential support (config, env vars, IAM roles)
  - Real-time chat interface with AJAX (VERIFIED WORKING)
  - Token tracking and usage monitoring
  - Safe uninstall preserving conversation data
  - Standard Drupal logging integration
- **Configuration:** `/admin/config/ai-conversation/settings`
- **Live Testing:** Confirmed working at `/node/13/chat` and other conversation nodes
- **Module Role:** **FOUNDATIONAL** - Advanced conversation management for AI services

### **Feature Modules**

#### **job_application_automation/** - Job Application Automation System ✅
- **Status:** [COMPLETED] - Core module with comprehensive architecture
- **Purpose:** Primary job application automation functionality including web scraping, profile management, and application submission
- **Architecture:** `/drupal/web/modules/custom/job_application_automation/ARCHITECTURE.md`
- **Admin Interface:** `/admin/job-applications` (unrestricted access)
- **Target Companies:** Johnson & Johnson (https://www.careers.jnj.com/en/)
- **Key Features:** Diffbot API integration, automated job search, application tracking
- **Recent Fix:** Resolved routing errors, removed invalid settings route references

#### **resume_tailoring/** - AI-Powered Resume Customization ✅  
- **Status:** [COMPLETED] - Installed and operational
- **Purpose:** AI-powered resume tailoring based on job postings
- **Dashboard:** `/resume-tailoring`
- **Admin Dashboard:** `/admin/resume-tailoring`
- **Key Features:** Job posting analysis, resume customization, AI-powered recommendations
- **Content Types:** `resume`, `tailored_resume` 
- **Recent Fix:** Module enabled and routes verified working

## Recent Updates

### **September 26, 2025 - AI Conversation Critical Fix**
- ✅ **500 Error Resolution:** Fixed ServiceNotFoundException for 'newsmotivationmetrics.logging_config'
- ✅ **Logging System:** Replaced non-existent service calls with standard Drupal::logger('ai_conversation')
- ✅ **Production Testing:** Live error reproduced and resolved at `/node/13/chat`
- ✅ **Error Analysis:** Complete logging infrastructure documented (Apache, PHP, Drupal)
- ✅ **Chat Interface:** AI conversation messaging now fully operational
- ✅ **Code Quality:** Removed 3 service dependency errors in ChatController.php

### **September 25, 2025 - Module System Enhancements**

#### **AI Conversation Module Enhancements**
- ✅ **Configuration System:** Added comprehensive admin settings form at `/admin/config/ai-conversation/settings`
- ✅ **Multi-Credential Support:** Environment variables, configuration, and AWS credential chain fallback
- ✅ **Installation Fixes:** Resolved missing field issues, enhanced field existence checking
- ✅ **Safe Uninstall:** Data preservation during module removal, optional complete cleanup
- ✅ **Production Ready:** Full error handling, logging, and status monitoring

### **Job Application Module Fixes**
- ✅ **Routing Errors:** Resolved missing `job_application_automation.settings` route references
- ✅ **Access Control:** Removed restrictive permissions, now accessible at `/admin/job-applications`
- ✅ **Module Configuration:** Fixed invalid configure directive causing startup errors
- ✅ **UI Updates:** Disabled settings button until proper settings page is implemented

### **Resume Tailoring Module Activation**
- ✅ **Module Installation:** Enabled previously disabled resume_tailoring module
- ✅ **Route Verification:** Confirmed `/resume-tailoring` dashboard is operational
- ✅ **Content Types:** Verified `resume` and `tailored_resume` content types are properly configured
- ✅ **Field Management:** Ensured proper field relationships between content types

### **System Stability**
- ✅ **Error Resolution:** All "unexpected error" messages resolved across modules
- ✅ **Cache Management:** Proper cache rebuilding after configuration changes
- ✅ **Log Monitoring:** Clean error logs with no routing or configuration issues
- ✅ **Module Integration:** All three core modules working together without conflicts

## Module Architecture Status
- **AI Integration:** **SHOULD DEPEND** on ai_conversation for AI services

#### **resume_tailoring/** - AI-Powered Resume Tailoring
- **Status:** [COMPLETED] - 5-step workflow with comprehensive dashboard
- **Purpose:** AI-powered resume tailoring functionality for job-specific customization
- **Workflow:** 5-step process (Original Resume → Job Posting → AI Analysis → Tailored Resume → Review)
- **Content Types:** resume, job_posting (existing), tailored_resume (with reference fields)
- **Dashboard:** /resume-tailoring (authenticated access required)
- **AI Integration:** **DEPENDS ON** ai_conversation for AWS Bedrock services
- **Key Features:** Programmatic content type creation, field reference management, progress tracking

### **Module Integration Notes**

#### **AI Service Architecture**
- **Foundation:** ai_conversation module provides centralized AWS Bedrock integration
- **Dependencies:** resume_tailoring depends on ai_conversation for AI services
- **Credential Management:** AWS credentials managed centrally in ai_conversation (environment variables/IAM roles)
- **Service Pattern:** All future AI-powered modules should depend on ai_conversation service

#### **Security Considerations**
- **⚠️ CRITICAL:** Diffbot API key was exposed during integration (8488710a556cedc9ff2ad6547bbbecaf)
- **Required Action:** Regenerate Diffbot API key immediately and configure as environment variable
- **AWS Credentials:** No hardcoded credentials - uses environment variables or IAM roles
- **Best Practice:** All API keys must be stored in environment variables, never in code

#### **External Repository Integration**
- **Source Repository:** https://github.com/keithaumiller/thetruthperspective.git
- **Integration Date:** Current session
- **Modules Integrated:** ai_conversation, resume_tailoring (renamed from job_application_automation)
- **Naming Conflict Resolution:** Original job_application_automation module renamed to resume_tailoring to avoid conflicts

#### **Drupal Compatibility**
- **Core Support:** All modules support Drupal 9, 10, and 11
- **Dependencies:** Standard Drupal core modules + ai_conversation for AI services
- **Installation:** Standard Drupal module installation process

### **Production Server Documentation**

#### **Comprehensive Logging Infrastructure** (Updated Sept 26, 2025)
- **Apache Logs**: Site-specific logs at `/var/log/apache2/stlouisintegration_access.log` and `stlouisintegration_error.log`
- **PHP Logging**: mod_php configuration sends errors to Apache error logs
- **Drupal Logging**: Database logging (dblog) enabled, accessed via `drush --uri=stlouisintegration.com watchdog:show`
- **Multisite Context**: All drush commands require `--uri=stlouisintegration.com` specification
- **Complete Documentation**: All logging details documented in `.github/instructions/instructions.md`

### **AWS Bedrock Configuration**

#### **Environment Variables (Recommended)**
```bash
AWS_ACCESS_KEY_ID=your_access_key_here
AWS_SECRET_ACCESS_KEY=your_secret_key_here  
AWS_DEFAULT_REGION=us-west-2
```

#### **IAM Role Configuration (Production)**
- **Preferred Method:** Use IAM roles for EC2/ECS deployment
- **Service:** Amazon Bedrock with Claude model access
- **Permissions:** BedrockRuntime:InvokeModel for anthropic.claude-* models
- **Region:** us-west-2 (configured in ai_conversation service)

#### **Development Environment**
- **Credentials:** Set environment variables in dev container
- **Testing:** Use ai_conversation module for all AI functionality tests
- **Region:** Defaults to us-west-2 for consistent development

This modular approach provides comprehensive job application automation with AI-powered resume tailoring and conversational interfaces while maintaining clear separation of concerns and centralized AI service management.
