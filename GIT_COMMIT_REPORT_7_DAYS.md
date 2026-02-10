# Git Commit Report - Last 7 Days
**Generated:** February 9, 2026  
**Purpose:** Identify deployment requirements for production fix

---

## 🔴 CRITICAL: Database Schema Changes (Job Hunter)

### 430bb68a2 - Update base table schemas to include all columns from update hooks
**Date:** 2026-02-09  
**Author:** Keith Aumiller  
**Impact:** ⚠️ **HIGH - DATABASE MIGRATION REQUIRED**

```
Files Changed:
- sites/forseti/web/modules/custom/job_hunter/job_hunter.install | 12 insertions(+), 2 deletions(-)
```

**Description:** Updates base `jobhunter_tailored_resumes` table schema with new columns (`uid`, `job_id`, `tailoring_status`, `tailored_resume_json`, `pdf_path`, `pdf_generated`).

**Deployment Impact:** This commit adds the schema definition, but update hooks must be run on production.

---

### f06cbf986 - Add Forseti Jobs Search integration card and fix database schema
**Date:** 2026-02-09  
**Author:** Keith Aumiller  
**Impact:** ⚠️ **HIGH - DATABASE MIGRATION REQUIRED**

```
Files Changed:
- sites/forseti/web/modules/custom/job_hunter/job_hunter.install | 315 insertions(+++)
- sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php | 58 changes
- 17 other job_hunter files modified
Total: 2435 insertions(+), 79 deletions(-)
```

**Description:** Major job_hunter update including:
- Added update hooks for schema migration
- Modified CompanyController to use new schema (uid, job_id, tailoring_status)
- Added Google Cloud Talent Solution service
- Added search tracking documentation

**Deployment Impact:** **THIS IS THE COMMIT THAT BROKE PRODUCTION** - Code deployed but database migration not run.

---

### 69d1bc448 - Fix navigation menu on /jobhunter/jobs page
**Date:** 2026-02-09  
**Author:** Keith Aumiller  
**Impact:** LOW

```
Files Changed:
- sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php | 6 insertions(+)
```

**Description:** Navigation fix - adds navigation wrapper to CompanyController.

**Deployment Impact:** Safe to deploy, cosmetic fix only.

---

## 🎮 Dungeoncrawler Site Development

### 435fa88ce - Fix saveStep to return JSON for AJAX calls
**Date:** 2026-02-09  
**Files:** CharacterCreationStepController.php (33 changes)  
**Impact:** Character creation wizard AJAX functionality

---

### bfe5bbac9 - Enforce centralized CSS standards
**Date:** 2026-02-09  
**Files:** 32 files, 30,167 insertions(+), 246 deletions(-)  
**Impact:** Major CSS refactoring, character creation UI improvements

**Key Changes:**
- Added character-creation.css (771 lines)
- Added character-steps.css (269 lines)
- Added JavaScript controllers for character steps 1-3
- Added CharacterApiController, CharacterCreationController, CharacterCreationStepController
- Added character creation wizard templates

---

### 71922098c - Create subdirectories for each PF2E reference document
**Date:** 2026-02-09  
**Files:** 9 subdirectories created  
**Impact:** Documentation organization

---

### 4ca123eed - Add reference documentation notes to all PR documents
**Date:** 2026-02-09  
**Files:** 6 PR documents updated  
**Impact:** Documentation improvement

---

### ed97a17d0 - Add comprehensive PR documents for all dungeoncrawler game systems
**Date:** 2026-02-09  
**Files:** 6 new PR documents (4,562 lines total)  
**Impact:** Project documentation

**Documents Added:**
- PR-01-character-creation-implementation.md (625 lines)
- PR-02-combat-encounter-implementation.md (730 lines)
- PR-03-action-system-implementation.md (745 lines)
- PR-04-skill-check-implementation.md (786 lines)
- PR-05-spellcasting-implementation.md (868 lines)
- PR-06-leveling-up-implementation.md (808 lines)

---

### f27ab594d - Fix front page routing
**Date:** 2026-02-09  
**Files:** 17 files changed  
**Impact:** Changed dungeoncrawler home route from '/' to '/home'

---

### 4d99dca22 - Add dungeoncrawler block enablement and front page config
**Date:** 2026-02-09  
**Files:** script/setup.sh | 15 insertions(+)  
**Impact:** Setup script improvements

---

### 332150da1 - Fix navbar text visibility
**Date:** 2026-02-09  
**Files:** 3 files changed  
**Impact:** UI fix - white on white navbar text

---

### 0d63bf41f - Add navigation and footer menus
**Date:** 2026-02-09  
**Files:** 7 files, 877 insertions(+)  
**Impact:** Added About, How To Play, World controllers and menu structure

---

### f11cf312e - Add comprehensive database schema design for PF2E system
**Date:** 2026-02-09  
**Files:** database-schema-design.md (846 lines)  
**Impact:** Documentation

---

### 82a9768f6 - Add comprehensive PF2E game mechanics documentation
**Date:** 2026-02-09  
**Files:** 7 docs (2,164 lines)  
**Impact:** Core game mechanics documentation

---

### edaf50b4c - Add Pathfinder 2E rulebooks and extracted text
**Date:** 2026-02-09  
**Files:** 18 files (368,948 lines)  
**Impact:** Reference material - PDFs and extracted text

---

### 69f95facc - Organize PF2E reference materials into subdirectory
**Date:** 2026-02-09  
**Files:** 18 files reorganized  
**Impact:** Documentation structure

---

### cb411ceec - cleanup
**Date:** 2026-02-09  
**Files:** Deleted 7 .txt files (4,622 deletions)  
**Impact:** Removed duplicate reference text files

---

## 🔍 Job Hunter Feature Development

### c45399f09 - Add external job search API integrations
**Date:** 2026-02-09  
**Files:** 6 files, 792 insertions(+), 13 deletions(-)  
**Impact:** MEDIUM - New API integrations

**Added Services:**
- AdzunaApiService.php (155 lines)
- SerpApiService.php (166 lines)
- UsaJobsApiService.php (158 lines)
- Updated JobApplicationController with API integration
- Added SettingsForm configuration

---

### b970efd59 - Add testSimpleSearch method
**Date:** 2026-02-09  
**Files:** 2 files, 67 insertions(+), 2 deletions(-)  
**Impact:** LOW - Testing method for Google Cloud API

---

### 0ed9553f9 - Add detailed error logging for Google Cloud API
**Date:** 2026-02-09  
**Files:** CloudTalentSolutionService.php | 17 changes  
**Impact:** LOW - Improved debugging

---

### 7d2ef5df2 - Refactor Google Cloud job search
**Date:** 2026-02-09  
**Files:** CloudTalentSolutionService.php | 25 changes  
**Impact:** LOW - Code refactoring

---

### 2861d78ea - Add visible diagnostic info when job search returns 0 results
**Date:** 2026-02-09  
**Files:** JobApplicationController.php | 48 insertions(+), 1 deletion(-)  
**Impact:** LOW - UX improvement

---

### d365f0874 - Add diagnostic logging and fix schema mismatches
**Date:** 2026-02-09  
**Files:** JobApplicationController.php | 69 changes  
**Impact:** LOW - Debugging improvements

---

### 6dd6f3469 - Fix sources parameter to handle array values
**Date:** 2026-02-09  
**Files:** JobApplicationController.php | 11 changes  
**Impact:** LOW - Bug fix

---

### 0f72d80d7 - Fix location default and add Google API search filters
**Date:** 2026-02-09  
**Files:** 2 files, 136 insertions(+), 11 deletions(-)  
**Impact:** MEDIUM - Search functionality

---

### dbc93fd1e - Fix InvalidArgumentException in job discovery
**Date:** 2026-02-09  
**Files:** JobApplicationController.php | 4 changes  
**Impact:** LOW - Bug fix

---

### c4ac54cc7 - Add configurable log level system
**Date:** 2026-02-09  
**Files:** 3 files, 71 insertions(+), 62 deletions(-)  
**Impact:** LOW - Logging improvements

---

### c6554ed11 - Add configurable log level setting and fix type error
**Date:** 2026-02-09  
**Files:** 4 files, 146 insertions(+), 10 deletions(-)  
**Impact:** LOW - Added JobHunterLoggerTrait (109 lines)

---

### 693c11936 - Fix online presence URLs not populating
**Date:** 2026-02-09  
**Files:** UserProfileForm.php | 60 changes  
**Impact:** MEDIUM - Bug fix for resume parsing

---

### 1194e71bb - Improve certifications handling
**Date:** 2026-02-09  
**Files:** UserProfileForm.php | 88 changes  
**Impact:** MEDIUM - Display improvements

---

### 8d1da6229 - Auto-calculate years of professional experience
**Date:** 2026-02-09  
**Files:** UserProfileForm.php | 62 insertions(+), 2 deletions(-)  
**Impact:** MEDIUM - Auto-calculation feature

---

### 705c83c6e - Add tenant name configuration field
**Date:** 2026-02-09  
**Files:** 2 files, 72 insertions(+), 18 deletions(-)  
**Impact:** MEDIUM - Google Cloud integration

---

### 45f64917f - Add Create Tenant and List Tenants buttons
**Date:** 2026-02-09  
**Files:** SettingsForm.php | 135 insertions(+), 2 deletions(-)  
**Impact:** MEDIUM - Admin UI improvements

---

### f7ca3e073 - Fix navigation route reference
**Date:** 2026-02-09  
**Files:** 2 files, 30 insertions(+), 11 deletions(-)  
**Impact:** LOW - Navigation fix

---

### f0c94db33 - Add google/auth dependency
**Date:** 2026-02-09  
**Files:** composer.json, composer.lock | 130 insertions(+)  
**Impact:** MEDIUM - Dependency addition

---

### 48d6178ed - Fix Google Cloud credentials config key
**Date:** 2026-02-09  
**Files:** 2 files, 7 insertions(+), 3 deletions(-)  
**Impact:** LOW - Configuration fix

---

### 9f30dea43 - Rename to Automated Search Assist
**Date:** 2026-02-09  
**Files:** 2 files, 81 insertions(+), 30 deletions(-)  
**Impact:** MEDIUM - Feature enhancements

---

### c115ca6fd - Enhance job discovery
**Date:** 2026-02-09  
**Files:** 2 files, 622 insertions(+), 98 deletions(-)  
**Impact:** HIGH - Major feature update

---

### 9892502cb - Redesign job discovery workflow
**Date:** 2026-02-09  
**Files:** 5 files, 127 insertions(+), 62 deletions(-)  
**Impact:** MEDIUM - Workflow changes

---

### 79a65e904 - Clarify target companies as primary job search focus
**Date:** 2026-02-09  
**Files:** 2 files, 7 insertions(+), 5 deletions(-)  
**Impact:** LOW - UI clarification

---

### e4086ef01 - Merge navigation wrapper for Google Jobs and Queue Management
**Date:** 2026-02-09  
**Impact:** LOW - Merge commit

---

## 📦 Site-Wide Updates

### 211e1b4c6 - Update all sites
**Date:** 2026-02-08  
**Author:** Forseti Development  
**Impact:** ⚠️ **VERY HIGH - COMPLETE DUNGEONCRAWLER SITE ADDITION**

**Files Changed:** 100+ files, massive additions

**Key Changes:**
- Added complete dungeoncrawler site infrastructure
- Updated setup.sh with multi-site support
- Removed old production-specific setup scripts
- Added Drupal 11 core to dungeoncrawler
- Complete theme and module structure for dungeoncrawler

**Deployment Impact:** This is the foundation commit for the dungeoncrawler site. All subsequent dungeoncrawler commits depend on this.

---

## 📊 Summary by Impact Level

### 🔴 CRITICAL - MUST DEPLOY FOR PRODUCTION FIX
1. **f06cbf986** - Fix database schema (includes update hooks)
2. **430bb68a2** - Update base table schemas
3. **69d1bc448** - Fix navigation (safe follow-up)

### 🟡 HIGH PRIORITY - Feature Complete
4. **211e1b4c6** - Update all sites (dungeoncrawler foundation)
5. **bfe5bbac9** - CSS standards and character creation UI
6. **c45399f09** - External API integrations
7. **c115ca6fd** - Enhanced job discovery

### 🟢 MEDIUM PRIORITY - Enhancements
- All other job_hunter feature commits
- Dungeoncrawler documentation and content commits
- Navigation and UI fixes

### ⚪ LOW PRIORITY - Documentation & Cleanup
- Reference documentation commits
- Cleanup commits
- Minor bug fixes

---

## 🎯 Recommended Deployment Strategy

### Option 1: Forward Migration (RECOMMENDED)
**Deploy commits in this order:**

1. **211e1b4c6** - Site infrastructure (if not already deployed)
2. **f06cbf986** - Database schema fix with update hooks
3. **430bb68a2** - Base table schema updates
4. **69d1bc448** - Navigation fix
5. Run on production: `cd /var/www/html/forseti && ./vendor/bin/drush updb -y && ./vendor/bin/drush cr`

**Pros:**
- Fixes production immediately
- Gets all new features deployed
- Brings prod up to date with dev

**Cons:**
- Larger deployment surface area
- All dungeoncrawler code deployed (but isolated to that site)

---

### Option 2: Minimal Rollback (TEMPORARY FIX)
**Rollback CompanyController.php to use old schema temporarily:**

```sql
-- Quick SQL fix on production:
ALTER TABLE jobhunter_tailored_resumes CHANGE user_id uid int unsigned NOT NULL;
ALTER TABLE jobhunter_tailored_resumes CHANGE job_requirement_id job_id int unsigned NOT NULL;
ALTER TABLE jobhunter_tailored_resumes ADD COLUMN tailoring_status varchar(32) DEFAULT 'pending';
ALTER TABLE jobhunter_tailored_resumes ADD COLUMN tailored_resume_json longtext;
ALTER TABLE jobhunter_tailored_resumes ADD COLUMN pdf_path varchar(512);
ALTER TABLE jobhunter_tailored_resumes ADD COLUMN pdf_generated int DEFAULT 0;
```

**Pros:**
- Immediate fix without code deployment
- Minimal risk

**Cons:**
- Manual SQL required
- Still need to run update hooks eventually

---

### Option 3: Surgical Deployment
**Cherry-pick only these commits:**
1. f06cbf986
2. 430bb68a2
3. 69d1bc448

**Pros:**
- Minimal deployment
- Avoids dungeoncrawler code

**Cons:**
- More complex git operations
- May have dependencies on other commits

---

## 🔧 Production Deployment Commands

```bash
# Navigate to production
cd /var/www/html/forseti

# Check current branch
git status
git log --oneline -5

# Option 1: Pull all changes (RECOMMENDED)
git pull origin main
./vendor/bin/drush updb -y
./vendor/bin/drush cr

# Option 2: Cherry-pick specific commits
git cherry-pick f06cbf986
git cherry-pick 430bb68a2
git cherry-pick 69d1bc448
./vendor/bin/drush updb -y
./vendor/bin/drush cr

# Verify fix
./vendor/bin/drush sql-query "DESCRIBE jobhunter_tailored_resumes"
# Should show: uid, job_id, tailoring_status, tailored_resume_json, pdf_path
```

---

## 📋 Verification Checklist

After deployment:

- [ ] Run `drush updb -y` to execute update hooks
- [ ] Verify table schema: `drush sql-query "DESCRIBE jobhunter_tailored_resumes"`
- [ ] Check columns exist: uid, job_id, tailoring_status, tailored_resume_json, pdf_path, pdf_generated
- [ ] Test /jobhunter/jobs page loads without error
- [ ] Verify jobs list displays correctly
- [ ] Clear cache: `drush cr`
- [ ] Check error logs for any new issues

---

**END OF REPORT**
