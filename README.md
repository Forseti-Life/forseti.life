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
