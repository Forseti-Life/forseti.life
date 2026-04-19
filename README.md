# Forseti.life Platform Workspace
Multi-site Drupal workspace for community-managed, decentralized internet services

> **Status:** This repository is the private operational Forseti monorepo. Open-source publication is **in progress**, but this monorepo is **not** currently ready for public release. The publication strategy is curated/extracted repos under `github.com/Forseti-Life/`, not flipping this live workspace public.

**Last Updated:** April 15, 2026

## Mission
Democratize and decentralize internet services by building community-managed versions of core systems for scientific, technology-focused, and tolerant people.

## Current Focus
- Job Hunter (primary product focus)
- Dungeon Crawler (primary product focus)
- Scientific experimentation and clinical trials (early-stage)
- Community safety application (early-stage)

## Open Source Status
Open-source publication work is active, but this monorepo is **not yet public-release ready**.

Authoritative readiness and gate docs live in `copilot-hq/`:

- `copilot-hq/dashboards/open-source/public-readiness-status-2026-04.md`
- `copilot-hq/dashboards/open-source/runtime-truth-audit-2026-04.md`
- `copilot-hq/runbooks/public-release-gate-20260308.md`

Public release remains blocked by security cleanup, git-history hygiene, publication-candidate freeze, and validation evidence. See the contribution guide and code of conduct before opening pull requests against any curated public repo once published.

- Contribution guide: [CONTRIBUTING.md](CONTRIBUTING.md)
- Code of conduct: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
- Security policy: [SECURITY.md](SECURITY.md)
- License: [LICENSE](LICENSE)

## Development Process
We use a full-stack, fully automated development process with an architecture-first workflow. Documentation is treated as a first-class artifact, and architectural changes are documented before implementation.

### Local + Production Development Policy

- Development can happen on both local dev machine and production server.
- GitHub `main` is source-of-truth for shared history.
- Production deployment is manual-only (explicit run), not automatic on push.
- Conflict-safe sync workflow: `docs/technical/DEVELOPMENT_SYNC_WORKFLOW.md`
- Production-master to local-worker protocol: `copilot-hq/runbooks/production-master-dev-worker.md`

## Quick Start

### 🚀 After Workspace Restart (Fastest)
```bash
./script/quick-start.sh
```

### 🔧 Complete Multi-Site Setup (First Time)
```bash
./script/complete-setup.sh
```

### ✅ Verify Everything Works
```bash
./script/verify-setup.sh
```

These commands are for the private monorepo workspace. Repo-specific public quickstarts will ship with the curated public repos when they are released.

## 📚 Documentation

### Comprehensive Documentation Hub

**Location**: `/docs/`  
**Status**: 🟢 Complete and Actively Maintained  
**Last Updated**: December 13, 2024

The `/docs/` directory contains comprehensive product, market, and technical documentation following Lean Startup methodology.

#### Quick Links

| Section | Status | Description | Quick Link |
|---------|--------|-------------|------------|
| **🛠️ Issue Tracker** | 🟢 Active | Repository-level Open/Closed issue log | [View →](./Issues.md) |
| **🔒 Security Review** | 🟢 Active | Latest repository security audit report | [View →](./SECURITY_REVIEW_2026-02-18.md) |
| **🧭 Security Plan** | 🟢 Active | Phase-2 remediation tasks and validation criteria | [View →](./SECURITY_REMEDIATION_PLAN_2026-02-18.md) |
| **🗂️ Full File List** | 🟢 Active | Exhaustive repository file inventory (162,220 files) | [View →](./docs/security/full_file_list_2026-02-18.txt) |
| **🧾 Per-File Security Review** | 🟢 Active | File-by-file review output (CSV + summary) | [View →](./docs/security/file_security_review_2026-02-18.csv) |
| **📌 Per-File Review Summary** | 🟢 Active | Aggregate findings and first-party triage priorities | [View →](./docs/security/file_security_review_summary_2026-02-18.md) |
| **📋 Product Docs** | 🟢 Live Beta | Lean Startup product management | [View →](./docs/product/) |
| **🎯 Process Flow** | 🟢 Complete | End-to-end validation roadmap | [View →](./docs/product/process-flow-validation.md) |
| **👤 User Journey** | 🟢 Active | Sarah persona journey mapping | [View →](./docs/product/user-journey/) |
| **📊 MVP Definition** | 🟢 Live Beta | Current MVP scope & features | [View →](./docs/product/mvp/) |
| **🧪 Experiments** | 🟡 Ready | Hypothesis testing framework | [View →](./docs/product/experiments/) |
| **📈 Metrics** | 🟡 Setup | AARRR analytics & dashboards | [View →](./docs/product/metrics/) |
| **💬 Customer Dev** | 🟡 0/35 | Customer interviews & validation | [View →](./docs/product/customer-development/) |
| **🎨 Lean Canvas** | 🟡 Draft | One-page business model | [View →](./docs/product/lean-canvas/) |
| **🏪 Market Docs** | 🟡 Template | TAM/SAM/SOM & competitive analysis | [View →](./docs/market/) |
| **🔧 Technical Docs** | 🟡 Organized | Architecture & API documentation | [View →](./docs/technical/) |

#### Documentation Structure

```
docs/
├── README.md                           # 🟢 Documentation hub with operations guide
├── ARCHITECTURE.md                     # 🟢 Complete system architecture
├── product/                            # 🟢 Lean Startup product management
│   ├── README.md                       # Product documentation guide
│   ├── process-flow-validation.md      # 🟢 End-to-end validation roadmap
│   ├── lean-canvas/                    # 🟡 Business model (9 blocks)
│   │   ├── README.md                   # Methodology guide
│   │   └── forseti-lean-canvas.md      # Draft canvas
│   ├── customer-development/           # 🟡 Customer discovery (0/35 interviews)
│   │   ├── README.md                   # Process guide
│   │   ├── customer-segments.md        # Personas
│   │   ├── problem-validation.md       # Problem interviews
│   │   ├── solution-validation.md      # Solution testing
│   │   └── customer-interviews-log.md  # Interview tracking
│   ├── experiments/                    # 🟡 Build-Measure-Learn
│   │   ├── README.md                   # Experiment methodology
│   │   ├── experiment-log.md           # Hypothesis tests
│   │   └── pivot-decisions.md          # Pivot framework
│   ├── metrics/                        # 🟡 AARRR analytics
│   │   ├── README.md                   # Metrics philosophy
│   │   ├── pirate-metrics.md           # Acquisition→Referral
│   │   ├── cohort-analysis.md          # Retention tracking
│   │   └── key-metrics-dashboard.md    # North Star & KPIs
│   ├── mvp/                            # 🟢 Phase 4/5 Live Beta
│   │   ├── README.md                   # MVP principles
│   │   ├── mvp-definition.md           # Complete MVP spec
│   │   └── feature-prioritization.md   # ICE/RICE scoring
│   └── user-journey/                   # 🟢 Active journey mapping
│       ├── README.md                   # Journey methodology
│       └── sarah-urban-commuter.md     # Primary persona
├── market/                             # 🟡 Market analysis
│   ├── README.md                       # Market framework
│   ├── market-sizing.md                # TAM/SAM/SOM
│   ├── competitive-analysis.md         # Competitors
│   ├── value-proposition.md            # Unique value
│   └── go-to-market-strategy.md        # Acquisition plan
└── technical/                          # 🟡 Technical specs
    ├── README.md                       # Tech guide
    ├── architecture.md                 # System links
    ├── api-documentation.md            # API specs
    ├── data-models.md                  # Database schemas
    └── integration-guides.md           # Third-party integrations
```

#### Current MVP Status

**Phase**: 🟢 Phase 4/5 - Live Beta Testing (December 2024)  
**Target**: 500 users by March 2025  
**Decision Point**: Pivot or Persevere (March 2025)

**Success Criteria** (Minimum Thresholds):
- ✅ Activation Rate: 40%+
- ✅ Day 7 Retention: 20%+
- ✅ Day 30 Retention: 10%+
- ✅ Free-to-Paid Conversion: 2%+
- ✅ NPS Score: 30+

**Core Features** (All ✅ Implemented):
1. Interactive Safety Map (H3 hexagons, z-scores)
2. Background Location Monitoring (GPS tracking)
3. Proactive Push Notifications (deep linking)
4. User Authentication (Drupal + JWT)
5. Premium Trial Management (7-day trial)
6. Configurable Settings (threshold, cooldown)

#### Key Documentation Highlights

**🎯 Process Flow & Validation Roadmap**
- Complete user journey → system component mapping
- Validation checkpoints for every touchpoint
- Testing strategy (unit, integration, E2E, load, UAT)
- Pre-launch and post-launch checklists
- System health monitoring metrics

**👤 User Journey: Sarah (Urban Commuter)**
- Discovery → Activation → Retention → Advocacy
- Week-by-week usage patterns
- Emotional journey mapping
- Failure modes and risk mitigation
- Success indicators at each stage

**📊 Lean Startup Methodology**
- Build-Measure-Learn cycle
- Customer development (4 stages)
- Innovation accounting (baseline → tune → pivot)
- AARRR metrics (Pirate Metrics)
- Product-market fit signals

#### Documentation Principles

1. **Architecture-First**: Comprehensive documentation before code
2. **Living Documents**: Updated continuously as we learn
3. **Status Visibility**: Clear indicators (🟢🟡🔴) throughout
4. **Cross-Linked**: Related docs connected for easy navigation
5. **Actionable**: Focused on decisions and learning, not just description

---

## Recent Development Accomplishments

### **🎯 H3 Geolocation Ultra-Precision Pipeline (November 2025)**
- ✅ **Resolution 13 Achievement**: Ultra-fine 44m² (7m×7m) spatial precision hexagons
- ✅ **20.1x Spatial Analytics Improvement**: From 20,549 to 413,172 hexagon aggregations
- ✅ **3-Layer Data Warehouse**: Complete Bronze→Silver→Gold architecture operational
- ✅ **Lightning Performance**: 3:15 processing time for 413K hexagons (2,119/second throughput)
- ✅ **Multi-Resolution Analytics**: 8 precision levels from city-wide to room-level detail
- ✅ **Optimized Batch Processing**: 10K batch sizes for massive Resolution 13 datasets
- ✅ **Real-World Geographic Efficiency**: Actual counts optimized by geographic constraints
- ✅ **Production-Ready Pipeline**: Complete end-to-end processing with 186MB total storage
- ✅ **🆕 Granular Incident Access**: Individual incident retrieval at H3:13 resolution via API
- ✅ **🆕 Hexagon Incidents Endpoint**: `/api/amisafe/hexagon/{h3_index}/incidents` for room-level detail

### **🏙️ Spatial Analysis Capabilities**
- **City-Wide (Res 6)**: 22 hexagons for metropolitan analysis (36.1 km² each)
- **District (Res 7-8)**: 638 hexagons for neighborhood insights (5.2-0.7 km² each)
- **Block Level (Res 9-10)**: 19,889 hexagons for street-level analytics (105-15K m² each)
- **Building Cluster (Res 11)**: 69,513 hexagons for property analysis (2.1K m² each)
- **Fine Detail (Res 12)**: 145,982 hexagons for building precision (307 m² each)
- **Ultra-Fine (Res 13)**: 177,128 hexagons for room-level detail (44 m² each)

### **🔍 H3:13 Granular Filtering Capabilities (November 2025)**
- ✅ **Individual Incident Access**: Each H3:13 hexagon stores incident IDs in JSON arrays
- ✅ **Room-Level Precision**: 7m × 7m spatial resolution for ultra-precise analysis
- ✅ **New API Endpoint**: `/api/amisafe/hexagon/{h3_index}/incidents` for granular data
- ✅ **Advanced Filtering**: Crime type, district, time period filtering at incident level
- ✅ **High-Density Support**: Handle hexagons with 8,000+ incidents efficiently
- ✅ **Database Enhancement**: `incident_ids` JSON column in aggregated table
- ✅ **Performance Optimized**: Sub-200ms response times for complex queries

### **🎉 COMPLETE SILVER LAYER PROCESSING ACHIEVEMENT (November 2025)**
- ✅ **100% H3 Geospatial Coverage**: All 3,406,194 records with complete H3 indexing (Res 5-13)
- ✅ **Enhanced Transform Processor v2**: Optimized ETL with efficient filtering and batch processing
- ✅ **Data Quality Grade A+**: Perfect coordinate validation and comprehensive quality reporting
- ✅ **Performance Optimization**: ID-based pagination and smart filtering (336 records/second)
- ✅ **Database Export System**: Complete 457MB compressed export with organized structure
- ✅ **Production-Ready Data**: Bronze, Silver, Gold layers with comprehensive documentation
- ✅ **Real-Time Processing**: Progress tracking with accurate ETA calculations
- ✅ **Quality Assurance**: Automated data quality checks and validation reporting

### **Theory of Conspiracies Site Development (December 2024)**
- ✅ **Complete Cyberpunk Website**: Built from scratch with Philadelphia 2085 theme
- ✅ **Multi-Site Apache Configuration**: Port-based virtual hosts (80 for primary, 8080 for Theory of Conspiracies)
- ✅ **Responsive Cyberpunk Theme**: Custom SCSS build process with mobile optimization
- ✅ **Three Custom Drupal Modules**:
  - **Theory Content Module**: Philadelphia 2085 setting, characters, and story management
  - **Am I Safe Module**: Real-time safety monitoring dashboard with cyberpunk styling  
  - **AI Conversation Module**: Interactive chat system with neon effects and animations
- ✅ **Mobile-First Design**: Portrait and landscape responsive layouts across all modules
- ✅ **Service Automation**: Quick-start scripts for MySQL and Apache restart functionality
- ✅ **Production-Ready Setup**: Complete LAMP stack configuration with proper security

### **Infrastructure & Development Process**
- ✅ **Architecture-First Methodology**: Comprehensive documentation standards implemented
- ✅ **LAMP Stack Optimization**: Ubuntu 24.04.2 LTS, Apache 2.4.58, MySQL 8.0+, PHP 8.3.26
- ✅ **Git Version Control**: Active repository management with proper branching
- ✅ **Documentation Standards**: README, instructions, and ARCHITECTURE template files
- ✅ **Quality Assurance**: Testing protocols and deployment verification processes

## Sites

### 1. St. Louis Integration (Primary)
- **URL**: http://localhost (port 80)
- **Directory**: `/sites/stlouisintegration/`
- **Description**: Professional services and integration solutions website
- **Database**: stlouisintegration_dev
- **Features**: 5 custom modules + custom theme

### 2. Theory of Conspiracies (Secondary)
- **URL**: http://localhost:8080 (port 8080)
- **Directory**: `/sites/theoryofconspiracies/`
- **Description**: Cyberpunk-themed Philadelphia 2085 storytelling website
- **Database**: theoryofconspiracies_dev
- **Features**: 
  - **Custom Cyberpunk Theme**: Responsive design with neon effects and animations
  - **Theory Content Module**: Philadelphia 2085 setting, characters, and story management
  - **Am I Safe Module**: Real-time safety monitoring dashboard for the cyberpunk world
  - **AI Conversation Module**: Interactive chat system with cyberpunk styling
  - **Mobile Optimized**: Portrait and landscape responsive design across all modules

## Original Project: stlouisintegration.com
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

#### **job_application_automation/** - Job Application Automation System 🔧
- **Status:** [IN PROGRESS - DEBUGGING] - Core functionality working, debugging AJAX endpoint issues
- **Purpose:** Primary job application automation functionality including job discovery, company selection, and AI-powered resume tailoring
- **Architecture:** `/drupal/web/modules/custom/job_application_automation/ARCHITECTURE.md`
- **Admin Interface:** `/admin/job-applications` (unrestricted access)
- **Key Features:**
  - ✅ Job Discovery System - Company selection and job-specific discovery pages  
  - ✅ Tailored Resume Content Type - Programmatic creation with entity references
  - ✅ AI Integration - Environment detection (mock in dev, real AI in prod)
  - 🔧 AJAX Resume Tailoring - Debugging 500 error on `/tailor-resume/ajax` endpoint
  - 🔧 JavaScript Messaging - Fixed Drupal.Message.add → Drupal.messenger() API
- **Recent Debugging (Oct 3, 2025):** 
  - Fixed JavaScript messaging API for Drupal 11 compatibility
  - Cleared routing cache to resolve route registration issues
  - Investigating 500 Internal Server Error on AJAX endpoint
  - Web server configuration issues preventing proper Drupal site access

#### **resume_tailoring/** - AI-Powered Resume Customization ✅  
- **Status:** [COMPLETED] - Installed and operational
- **Purpose:** AI-powered resume tailoring based on job postings
- **Dashboard:** `/resume-tailoring`
- **Admin Dashboard:** `/admin/resume-tailoring`
- **Key Features:** Job posting analysis, resume customization, AI-powered recommendations
- **Content Types:** `resume`, `tailored_resume` 
- **Recent Fix:** Module enabled and routes verified working

## Recent Updates

### **October 3, 2025 - Job Application Automation Debugging Session**
- ✅ **JavaScript API Fix:** Updated Drupal.Message.add to Drupal.messenger() for Drupal 11 compatibility
- ✅ **Routing Cache:** Cleared cache to resolve "Route does not exist" errors
- ✅ **Module Architecture:** Confirmed tailorResumeAjax() method exists in UserProfileController
- ✅ **Content Type Creation:** tailored_resume content type with field_job_posting and field_resume entity references
- 🔧 **AJAX Endpoint Issue:** 500 Internal Server Error on `/tailor-resume/ajax` requires investigation
- 🔧 **Web Server Config:** Apache serving default page instead of Drupal site on localhost:8080
- 🔧 **Environment Detection:** Mock responses configured for development workspace

#### **Current Technical Issues (Active Debugging)**
1. **AJAX Endpoint 500 Error:** `/tailor-resume/ajax` returns Internal Server Error
   - **Route Status:** Confirmed exists (`job_application_automation.tailor_resume_ajax`)
   - **Controller Method:** tailorResumeAjax() exists in UserProfileController
   - **Error Logs:** Need investigation of PHP error causing 500 response
   - **Next Step:** Check Drupal error logs for specific PHP exception

2. **Web Server Configuration:** Apache default page instead of Drupal
   - **Issue:** localhost:8080 shows Apache default instead of Drupal site
   - **Workaround:** Started PHP dev server on localhost:8081
   - **Impact:** Testing AJAX endpoints requires proper Drupal site access

3. **JavaScript API Compatibility:** Fixed but needs verification
   - **Fixed:** All Drupal.Message.add calls updated to Drupal.messenger()
   - **Status:** Should resolve "Drupal.Message.add is not a function" errors
   - **Next Step:** Test with functional Drupal site to confirm fix

#### **Resume Tailoring Workflow Status**
- ✅ **Step 1:** Job Discovery System - Company selection functional
- ✅ **Step 2:** Job-Specific Pages - Displaying correct job postings
- ✅ **Step 3:** Content Type - tailored_resume with proper entity references
- ✅ **Step 4:** AI Integration - Environment detection and mock responses
- 🔧 **Step 5:** AJAX Creation - Button functionality blocked by 500 error

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

#### **resume_tailoring/** - AI-Powered Resume Tailoring ✅
- **Status:** [COMPLETED] - 5-step workflow with comprehensive dashboard
- **Purpose:** AI-powered resume tailoring functionality for job-specific customization
- **Workflow:** 5-step process (Original Resume → Job Posting → AI Analysis → Tailored Resume → Review)
- **Content Types:** resume, job_posting (existing), tailored_resume (with reference fields)
- **Dashboard:** /resume-tailoring (authenticated access required)
- **AI Integration:** **DEPENDS ON** ai_conversation for AWS Bedrock services
- **Key Features:** Programmatic content type creation, field reference management, progress tracking

#### **Job Application + Resume Tailoring Integration Status**
- **Integration Model:** job_application_automation module includes resume tailoring functionality
- **Shared Components:** Both modules use tailored_resume content type with entity references
- **AI Service:** Both depend on ai_conversation module for AWS Bedrock integration
- **User Experience:** Seamless workflow from job discovery to tailored resume creation
- **Current Focus:** Debugging AJAX endpoint for "Start AI Tailoring" button functionality

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

#### **Comprehensive Logging Infrastructure** (Updated January 3, 2026)
- **Apache Logs**: Site-specific logs at `/var/log/apache2/forseti_access.log` and `/var/log/apache2/forseti_error.log`
- **PHP Logging**: mod_php configuration sends errors to Apache error logs
- **Drupal Logging**: Database logging (dblog) enabled, accessed via `cd /var/www/html/forseti && ./vendor/bin/drush watchdog:show`
- **Multisite Context**: Each site has its own Drush installation in vendor/bin/
- **Complete Documentation**: All logging details documented in `.github/instructions/instructions.md`

#### **Forseti Site Architecture** (Updated January 3, 2026)
**Module Structure:**
- **forseti_safety_content** - All user-facing website pages and content (routes: `/home`, `/about`, `/how-it-works`, `/community`, `/mobile-app`, `/privacy`, `/contact`, `/ai-agent-hierarchy`)
- **amisafe** - Crime data API, H3 processing, mobile app backend (routes: `/api/amisafe/*`, powers `/safety-map` and `/mobile-app`)
- **forseti** (theme) - Base theme with Bootstrap 5, animated hexagonal header, layout

**Page Status:** ✅ All pages loading (HTTP 200)  
**Styling:** ✅ Unified color scheme (--primary-blue: #00d4ff, --dark-bg: #1a1a2e)  
**Refactoring:** Phase 1 complete - Agent Hierarchy page converted to Twig templates (39% controller reduction)

**See:** [sites/forseti/README.md](sites/forseti/README.md) for detailed Drupal site documentation

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

## Public Release Checklist

Before deploying this repository as a public open source project, ensure the following:

- ✅ **No hardcoded secrets**: All database passwords, API keys, and credentials removed from code
- ✅ **Environment variables**: Required secrets use env vars (DB_PASSWORD, AWS_ACCESS_KEY_ID, etc.)
- ✅ **.gitignore**: Configured to exclude .env files, credentials, and sensitive artifacts  
- ✅ **License**: Apache 2.0 license included (see [LICENSE](LICENSE))
- ✅ **Contributing guide**: [CONTRIBUTING.md](CONTRIBUTING.md) establishes collaboration guidelines
- ✅ **Code of conduct**: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) with support contact
- ✅ **Security policy**: [SECURITY.md](SECURITY.md) with responsible disclosure process
- ✅ **Documentation**: README and architecture guides complete in [docs/](docs/)
- ✅ **No personal data**: Resume files, personal emails, and identifiable info removed
- ✅ **Example configs**: `.env.example` provided for required environment variables
- ✅ **CI/CD updates**: GitHub Actions workflows use `${{ secrets.VARIABLE_NAME }}` instead of hardcoded values
- ✅ **Script hardening**: Setup/database scripts require environment variable passwords
- ✅ **Third-party properly attributed**: Vendor code and dependencies properly licensed

### Deployment on Public Mirror
When deploying to GitHub (or similar public platform):
1. Remove any remaining `.env` files from history
2. Ensure all CI/CD workflows reference secrets, not hardcoded values
3. Run security scanning on dependencies
4. Generate SBOM (Software Bill of Materials) for compliance
5. Create initial GitHub Issues from [Issues.md](Issues.md) if desired

---

# Deployment test Sun Sep 28 13:58:23 UTC 2025
# Test deployment trigger
# Navigation menu deployment - Sun Sep 28 22:04:48 UTC 2025
