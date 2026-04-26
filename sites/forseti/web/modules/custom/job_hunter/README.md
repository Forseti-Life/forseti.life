# JobHunter

JobHunter is the Drupal-based job-search and application automation system inside `forseti.life`. Its purpose is simple: turn a candidate profile into **more completed job submissions** with as little manual work as possible.

The module combines resume parsing, structured profile building, role discovery, filtering, resume tailoring, ATS detection, credential-aware submission routing, and application tracking in one Drupal-native workflow. It is the operational core behind the JobHunter release cycle and the `submission_count` KPI.

## Value proposition

JobHunter exists to automate the highest-friction parts of a job search:

- **Resume ingestion and parsing** turn uploaded resumes into structured JSON and a consolidated job-seeker profile.
- **Search and filtering** aggregate roles from multiple sources into a searchable pipeline.
- **Tailoring** adapts resumes and related artifacts to a specific opportunity.
- **Submission orchestration** routes each application to the right employer system and decides whether it can be automated now or needs guided/manual completion.
- **Tracking and accountability** keep every application attempt, status change, and blocker visible inside Drupal.

In business terms, the module is meant to reduce time-to-apply, increase application throughput, and make the primary KPI easy to measure:

- **Primary KPI:** `submission_count`
- **Source of truth:** `jobhunter_applications`
- **Definition:** count of applications with `submission_status = 'submitted'`

## What this repository/module is

This is a **custom Drupal module**, not a separate SaaS service. It extends Drupal with:

- Drupal content and admin workflows for jobs, companies, applications, and issues
- operational database tables for resume parsing, saved jobs, search staging, submissions, credentials, and automation attempts
- queue workers for background processing
- AWS Bedrock-backed GenAI flows for parsing and tailoring
- integration points for external job APIs and employer application systems

It is designed so product, PM, QA, and operations work can stay close to the live system instead of being split across disconnected tools.

## Current automation scope

### Working now

- **Resume upload and storage**
  - Resume files are stored privately and registered in JobHunter tables.
- **Resume text extraction**
  - `.docx` resumes are parsed into extracted text.
- **Structured resume parsing**
  - AWS Bedrock is used to turn resume text into structured JSON.
- **Profile consolidation**
  - Multiple resume variants are merged into one consolidated job-seeker profile.
- **Profile projections for filtering**
  - Key fields are projected into query-friendly columns for workflow checks and filtering.
- **Job discovery**
  - The module includes discovery/search services and external-source aggregation.
- **Saved-job workflow**
  - Users can save, review, archive, and manage opportunities without mutating the global job catalog.
- **Resume tailoring**
  - Tailored resume generation is integrated into the job workflow.
- **Application record creation**
  - Applications are tracked in `jobhunter_applications` with statuses, timestamps, and attempt history.
- **ATS/apply URL resolution**
  - Application targets are classified and routed toward the correct employer system.
- **Submission readiness checks**
  - The module validates profile completeness, duplicate submissions, and other prerequisites before attempting submission.
- **Manual fallback paths**
  - When a flow cannot be completed safely, the system marks it `manual_required` instead of pretending success.

### Implemented foundation for autonomous submission

JobHunter already contains the service layer needed for end-to-end application automation:

- `ApplicationSubmissionService`
- `BrowserAutomationService`
- `CredentialManagementService`
- `ApplyUrlResolverService`
- queue workers for submission processing

That foundation supports:

- queued application execution
- ATS detection and platform-specific routing
- credential-aware gating
- audit logging of attempts and outcomes
- structured fallback instructions when full automation is not yet safe

### Current submission-platform coverage

The module is built to work across **more than a dozen ATS and employer application systems**, including:

- Greenhouse
- Lever
- Ashby
- SmartRecruiters
- Workable
- Workday
- Taleo
- iCIMS
- SuccessFactors
- UKG Pro / UltiPro
- Paylocity
- BambooHR
- USAJobs
- custom employer career pages

Current code reliably classifies and routes these systems. Full browser-driven submission is further along for some paths than others, so the module intentionally uses **automation where safe** and **manual-required routing where needed**.

## Current state summary

JobHunter is best described as a **working autonomous application pipeline with partially completed ATS form-fill depth**.

Today, the strongest completed areas are:

1. Resume parsing and consolidated profile generation
2. Job search and opportunity management
3. Resume tailoring and application preparation
4. Application status tracking and KPI measurement
5. ATS detection, apply URL resolution, and submission routing

The remaining depth work is mostly in:

1. broader per-ATS browser automation coverage
2. richer company/application-path research
3. continued hardening of fallback and orchestration flows

That means the module already delivers value now, while still having a clear path toward fuller hands-off submission.

## Why Drupal is the right home

This module uses Drupal as the control plane rather than treating it as a thin UI shell.

Drupal provides:

- familiar admin surfaces for content and operations
- permissions, routing, forms, queues, and configuration management
- a durable place to store canonical business records
- the ability to blend editorial data, operational state, and AI artifacts in one system

For this use case, Drupal is valuable because it lets JobHunter behave like an application operations platform instead of a pile of scripts.

## Core data model

The module mixes Drupal-native entities with operational tables.

### Canonical business content

- companies
- job postings
- applications
- issues

### Operational tables

- `jobhunter_job_seeker`
- `jobhunter_job_seeker_resumes`
- `jobhunter_resume_parsed_data`
- `jobhunter_job_requirements`
- `jobhunter_saved_jobs`
- `jobhunter_job_search_results`
- `jobhunter_tailored_resumes`
- `jobhunter_applications`
- `jobhunter_application_attempts`

This split keeps Drupal content manageable while still supporting efficient automation logic and reporting.

## End-to-end workflow

At a high level, JobHunter runs this flow:

1. Upload one or more resumes
2. Extract text from the source files
3. Parse resume content into structured JSON
4. Consolidate multiple resume variants into one usable profile
5. Search and filter target roles
6. Save the right opportunities
7. Tailor resume content for a selected job
8. Resolve the employer's actual apply path / ATS
9. Attempt automated submission when prerequisites are satisfied
10. Record success, or route to `manual_required` with explicit next steps

## Operational stance

JobHunter is not meant to be a passive record-keeping module. It is meant to be an execution engine.

The intended operating model is:

- keep the candidate profile current
- keep a fresh supply of target opportunities flowing in
- convert empty release cycles into new submission work
- measure success by actual submitted applications, not just parsed resumes or saved jobs

## Documentation map

Start here for deeper details:

- `ARCHITECTURE.md` - module architecture and development guidance
- `INSTALL.md` - installation and operational setup
- `docs/README.md` - documentation index
- `docs/PROCESS_FLOW.md` - workflow diagrams
- `docs/SUBMISSION_PROCESS.md` - submission process documentation
- `docs/STEP3_APPLICATION_SUBMISSION_DESIGN.md` - detailed submission design
- `docs/LANGGRAPH_WORKFLOW_ROADMAP.md` - orchestration roadmap
- `docs/API_INTEGRATION_GUIDE.md` - external service integration details

## Bottom line

JobHunter is the Forseti job-application engine: a Drupal module that turns resume data, AI-assisted tailoring, opportunity search, and ATS-aware submission workflows into a measurable stream of real applications.
