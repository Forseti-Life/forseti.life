# National Firefighter Registry - Business Requirements Document

**Version:** 1.0  
**Date:** January 25, 2026  
**Based on:** CDC NFR Protocol (April 2025 OMB)

## Executive Summary

The National Firefighter Registry (NFR) for Cancer is mandated by the **Firefighter Cancer Registry Act of 2018** to develop and maintain a voluntary registry of U.S. firefighters for monitoring cancer incidence and risk factors.

### Primary Goal
Create a voluntary registry of firefighters to collect relevant health and occupational information for determining cancer incidence and improving firefighter safety.

---

## 1. Legislative Requirements

### Firefighter Cancer Registry Act of 2018
**Congressional Mandate:**
- Develop and maintain voluntary registry of U.S. firefighters
- Collect relevant health and occupational information
- Determine cancer incidence in firefighter population
- Monitor trends over time
- Funding authorized through fiscal year 2024 (5 years from 2019)

### NIOSH/CDC Responsibilities
- Develop voluntary opt-in registry system
- Ensure data security and confidentiality
- Link to state cancer registries
- Analyze cancer incidence and trends
- Provide findings to fire service community

---

## 2. Business Objectives

### Surveillance Activities (Primary)
1. **Self-Reported Data Collection**
   - Employment/workplace characteristics
   - Exposure information
   - Demographics
   - Lifestyle factors
   - Co-morbidities
   - Confounders related to cancer

2. **Fire Department Records**
   - Obtain records from departments (with consent)
   - Track exposure trends and patterns
   - Link exposure to cancer outcomes

3. **Health Database Linkage**
   - Link with population-based cancer registries (all 50 states)
   - Link with National Death Index (NDI)
   - Assess cancer incidence and mortality
   - Track longitudinal health outcomes

### Research Objectives (Secondary)
- Evaluate exposure-response relationships
- Assess effectiveness of control measures
- Identify high-risk populations
- Support evidence-based policy decisions

---

## 3. Target Populations

### Priority Populations
1. **Underrepresented Groups**
   - Minority firefighters (20% of career workforce)
   - Female firefighters (8% of all firefighters)
   - Limited statistical power in existing studies

2. **Understudied Groups**
   - Volunteer firefighters (majority of U.S. fire service)
   - Wildland firefighters
   - Fire investigators
   - Fire instructors
   - Rural firefighters (nearly half of U.S. fire departments)

3. **All Career Types**
   - Full-time paid
   - Part-time paid
   - Volunteer (full or part-time)
   - Seasonal
   - Paid on call/per call
   - Retired firefighters
   - Academy students

---

## 4. Data Collection Requirements

### 4.1 User Profile (5-minute burden)
**Purpose:** Initial registration and identity establishment

**Required Data:**
- Full name (first, middle, last)
- Other names used (maiden name, etc.)
- Country/state/city of birth
- Date of birth (month, day, year)
- Sex (Male/Female)
- Last 4 digits of SSN (optional but strongly encouraged)
- Current residential address
- Email address (primary and alternate)
- Mobile phone number (opt-in for text updates)
- Current work status in fire service
- Current or most recent department/agency/organization

**SSN Requirement Rationale:**
- Increases likelihood of successful state cancer registry linkage
- Necessary to meet statutory requirements
- Fully encrypted and protected under Assurance of Confidentiality
- Optional to encourage participation

**Age Eligibility:**
- Must be 18 years or older
- System validates DOB and displays ineligibility message if under 18

### 4.2 Enrollment Questionnaire (30-minute burden)
**Purpose:** Comprehensive occupational and health history

**Data Categories:**

#### Demographics
- Race/ethnicity (multi-select):
  - American Indian or Alaska Native
  - Asian
  - Black or African American
  - Hispanic or Latino
  - Middle Eastern or North African
  - Native Hawaiian or Pacific Islander
  - White
- Education level
- Marital status
- Height and weight (BMI calculation)

#### Work History
- Total time in fire service (years/months)
- Year first worked as firefighter
- Number of departments/agencies worked at
- Detailed employment history for each department:
  - Department name, state, jurisdiction
  - Start/end dates
  - Job titles held (multiple per department)
  - Employment type (full-time, part-time, volunteer, etc.)

#### Job Titles/Roles
- Structural/Industrial Firefighter
- Firefighter/Medical (EMT, Paramedic)
- Driver/Engineer/Operator
- Company Officer (Lt, Cpt, Sgt)
- Chief (various levels)
- Wildland Firefighter (multiple specialties)
- Wildland Supervisor/Overhead
- Fire Marshal
- Fire Investigator
- Instructor
- EMT/Paramedic
- Other specialized roles

#### Exposure Information
**Incident Types Responded To:**
- Structural fires
- Vehicle fires
- Outside rubbish/dumpster fires
- Live-fire training
- Fire investigation (post-extinguishment)
- Vegetation/brush fires
- Wildland fires/prescribed burns
- Wildland-Urban Interface fires
- Industrial fires
- Aircraft crash rescue
- Marine vessel fires
- Informal settlement fires
- HAZMAT response/spill

**Frequency Data:**
- Average number of responses per year for each incident type
- Special tracking for wildland fires (days per year)

**Special Exposures:**
- AFFF (Aqueous Film-Forming Foam) use
- Major events (disasters, terrorism, extreme incidents)
  - Event type classification
  - Duration of personal response
  - Named event identification

#### Military Service
- U.S. Armed Forces service
- Current service status
- Combat/war zone service

#### Other Employment
- Jobs held 6+ months concurrent with fire service
- Longest overlapping job details
- Jobs with routine smoke/exhaust/chemical exposure (100+ days)

#### Personal Protective Equipment (PPE) Practices
**Regular Use Assessment:**
- SCBA during interior structural attack
- SCBA during external structural attack
- SCBA/respirator during overhaul
- SCBA/respirator during vehicle fires
- Respirator during brush/vegetation fires
- Respirator during wildland suppression
- Respirator during fire investigations
- Respirator during WUI fires
- Year started each practice
- "Always done this" option

**Decontamination Practices:**
- Hood washing after fires
- Gear washing after fires
- Shower after fires
- Change out of gear at station
- Leave gear/boots outside living quarters
- Year started each practice

#### Health Information
- Height, weight (BMI)
- Current health conditions
- Cancer diagnosis history
- Family cancer history
- Smoking history (detailed)
- Alcohol consumption
- Physical activity levels
- Sleep patterns

#### Lifestyle Factors
- Tobacco use (cigarettes, cigars, pipes, chewing tobacco, e-cigarettes)
- Start/stop dates
- Frequency and quantity
- Alcohol consumption patterns
- Exercise and physical activity
- Sleep quality and duration

---

## 5. Data Security Requirements

### 5.1 Assurance of Confidentiality (AoC)
**Highest level of protection for identifiable information:**
- Formal CDC protection under Section 308(d) of Public Health Service Act
- Information cannot be shared without written permission
- Protected from Freedom of Information Act requests
- Not admissible in legal proceedings
- Violation carries criminal penalties

### 5.2 Technical Security Measures
- Multi-factor authentication (MFA)
- Transparent Data Encryption (TDE) for data at rest
- Encrypted data transmission (HTTPS/SSL)
- Universally Unique Identifiers (UUID) for record linkage
- De-identification for data analysis
- Audit logging of all data access
- Regular security assessments

### 5.3 Data Storage
- Secure CDC/NIOSH servers
- Encrypted databases
- Limited access controls
- Physical security measures
- Backup and recovery procedures

---

## 6. External Data Integration

### 6.1 State Cancer Registry Linkage
**Purpose:** Track cancer diagnoses without repeated participant contact

**Process:**
1. Obtain participant consent for linkage
2. Use identifying information (name, DOB, SSN-4, address)
3. Link through Virtual Pooled Registry Cancer Linkage System (VPR-CLS)
4. Receive confirmed cancer diagnoses
5. Analyze cancer incidence by firefighter characteristics

**Timeline:** 2-3 years for full implementation

**Challenges:**
- 50+ different state registry systems
- Varying data formats and APIs
- Different consent laws by state
- Need for Data Use Agreements (DUA)

### 6.2 National Death Index (NDI)
**Purpose:** Track mortality and cause of death

**Process:**
1. Submit identifying information annually
2. Receive confirmed death records
3. Obtain cause of death information
4. Analyze mortality patterns

### 6.3 USFA NERIS Integration
**Purpose:** Supplement self-reported data with department records

**Data Types:**
- Fire incident reports
- Apparatus run logs
- Personnel exposure records
- Training records
- Equipment usage

**Integration Method:** API-based data exchange (when available)

---

## 7. Stakeholder Engagement

### 7.1 Key Stakeholders
- International Association of Fire Fighters (IAFF)
- International Association of Fire Chiefs (IAFC)
- National Volunteer Fire Council (NVFC)
- International Association of Black Professional Fire Fighters (IABPFF)
- International Association of Wildland Fire (IAWF)
- National Fire Protection Association (NFPA)
- Fire departments and agencies nationwide
- State and local fire service organizations

### 7.2 Advisory Committee
- Provides guidance on registry development
- Reviews data collection instruments
- Advises on outreach and recruitment
- Ensures fire service perspective in decision-making

---

## 8. Consent and Participation

### 8.1 Informed Consent Requirements
**Voluntary Participation:**
- No coercion or undue influence
- Clear explanation of registry purpose
- Description of data collection activities
- Explanation of risks and benefits
- Right to withdraw at any time
- Separate consent for state registry linkage

### 8.2 Consent Process
1. Review informed consent document
2. Understand data uses and protections
3. Agree to participate (electronic signature)
4. Optional: Consent to state registry linkage
5. Optional: Provide SSN last 4 digits
6. Complete user profile
7. Complete enrollment questionnaire

---

## 9. Data Analysis and Reporting

### 9.1 Primary Analyses
- Cancer incidence rates by:
  - Cancer type
  - Firefighter demographics
  - Career type (career vs. volunteer)
  - Geographic region
  - Exposure levels
  - Years of service
  - Job specialty

### 9.2 Standardized Measures
- Standardized Incidence Ratios (SIRs)
- Standardized Mortality Ratios (SMRs)
- Exposure-response relationships
- Trend analysis over time

### 9.3 Reporting Outputs
- Public dashboard with summary statistics
- Peer-reviewed publications
- Reports to fire service community
- Policy recommendations
- Annual progress reports to Congress

---

## 10. System Features and Capabilities

### 10.1 User-Facing Features
- Simple registration process
- Mobile-responsive interface
- Progress saving (partial completion)
- Data validation and error checking
- Auto-population from user profile
- Department search and selection
- Text message updates (opt-in)
- Email notifications
- Profile updating capability

### 10.2 Administrative Features
- Participant management
- Data quality monitoring
- Export capabilities
- Linkage processing
- Statistical analysis tools
- Dashboard generation
- Report creation

### 10.3 Integration Capabilities
- State cancer registry API connections
- NDI submission and results processing
- USFA NERIS data import
- Department records import
- Follow-up survey deployment

---

## 11. Success Criteria

### 11.1 Participation Metrics
- Target: Maximum nationwide participation
- Emphasis on underrepresented groups
- Geographic diversity (all states/territories)
- Career type diversity
- Specialty role representation

### 11.2 Data Quality Metrics
- Completion rates >80%
- Missing data <10%
- Successful cancer registry linkage >70%
- Data validation accuracy >95%

### 11.3 Research Impact
- Publication of findings
- Evidence-based policy recommendations
- Adoption of recommended practices
- Improved firefighter cancer outcomes

---

## 12. Risks and Mitigation

### 12.1 Participation Risks
**Risk:** Low participation rates  
**Mitigation:** 
- Strong outreach campaign
- Stakeholder endorsements
- Simple, quick enrollment process
- Mobile-friendly design

**Risk:** Selection bias  
**Mitigation:**
- Targeted recruitment of underrepresented groups
- Multiple recruitment channels
- Ongoing engagement efforts

### 12.2 Data Security Risks
**Risk:** Data breach  
**Mitigation:**
- Assurance of Confidentiality
- Multiple layers of encryption
- Regular security audits
- Limited access controls

### 12.3 Linkage Challenges
**Risk:** Failed state registry matches  
**Mitigation:**
- Collect multiple identifiers
- Encourage SSN-4 provision
- Manual review of uncertain matches
- Ongoing quality improvement

---

## 13. Timeline and Phases

### Phase 1: Development (Completed)
- System design and architecture
- Data collection instrument development
- Security infrastructure
- OMB approval

### Phase 2: Pilot Testing
- Limited participant testing
- System validation
- Process refinement
- Stakeholder feedback

### Phase 3: National Rollout
- Promotional campaign launch
- Open enrollment
- Ongoing recruitment
- Continuous improvement

### Phase 4: Data Linkage (2-3 years)
- State cancer registry DUAs
- VPR-CLS integration
- NDI submissions
- Initial linkage results

### Phase 5: Analysis and Reporting
- Cancer incidence calculations
- Exposure-response analyses
- Publications and reports
- Policy recommendations

---

## 14. Compliance Requirements

### 14.1 Federal Regulations
- Public Health Service Act Section 308(d)
- Privacy Act of 1974
- Federal Information Security Management Act (FISMA)
- OMB Paperwork Reduction Act

### 14.2 Ethical Standards
- Institutional Review Board (IRB) approval
- Voluntary informed consent
- Confidentiality protections
- Risk minimization
- Benefit maximization

### 14.3 Data Governance
- Data Use Agreements with partners
- Data sharing policies
- Publication review process
- Embargo periods for sensitive data

---

## Appendices

### Appendix A: Official CDC Documents
- [NFR Protocol (April 2025 OMB)](documents/NFR-Protocol-Aprl_2025_OMB.pdf)
- [NFR User Profile (April 2025 OMB)](documents/NFR-User-Profile-April_-2025_OMB.pdf)
- [NFR Enrollment Questionnaire (April 2025 OMB)](documents/NFR-Enrollment-Questionnaire-April_2025_OMB.pdf)

### Appendix B: Stakeholder List
(See NFR Protocol Appendix C)

### Appendix C: Informed Consent Document
(See NFR Protocol Appendix D)

### Appendix D: Assurance of Confidentiality
(See NFR Protocol Appendix G)

---

**Document Control:**
- **Author:** NFR Development Team
- **Last Updated:** January 25, 2026
- **Next Review:** Quarterly
- **Version:** 1.0