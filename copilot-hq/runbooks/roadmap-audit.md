# Runbook: DungeonCrawler Roadmap Requirements Audit

**Owned by**: `pm-dungeoncrawler`  
**Supervised by**: CEO  
**Triggered by**: CEO or Board request, or post-release improvement round  

---

## Purpose

This runbook defines the process for systematically auditing ALL requirements on
`https://dungeoncrawler.forseti.life/roadmap`, ensuring none remain at "Not Started"
(i.e., `pending` in the DB) without an active plan.

Every requirement must end up in one of two states:
- **`implemented`** — code exists and QA has verified it; `dc_requirements.status = 'implemented'`
- **In the feature pipeline** — a `features/dc-*/` feature file exists AND `dc_requirements.feature_id` is set to that feature's work-item-id

> ⚠️ **Coverage is only machine-verifiable if `feature_id` is set in the DB.** Without it, "in the
> pipeline" is PM assertion only and cannot be queried. See the open dev task:
> `dc_requirements` needs a `feature_id VARCHAR(64)` column — once added, the completion check
> becomes: `SELECT * FROM dc_requirements WHERE status='pending' AND feature_id IS NULL`
> Until that column exists, the SQL audit tracker (below) is the only per-cycle coverage record.

---

## Why Not One-at-a-Time from the Web Page

The roadmap webpage is the *display* layer. The canonical source of truth is the
`dc_requirements` table in the `dungeoncrawler` MySQL database.

```sql
-- Total requirements by status
SELECT status, COUNT(*) FROM dc_requirements GROUP BY status;

-- Pending work, grouped by book and chapter (your audit work queue)
SELECT book_id, chapter_key, MIN(chapter_title) as title,
       COUNT(*) as total, SUM(status='pending') as pending
FROM dc_requirements
GROUP BY book_id, chapter_key
ORDER BY book_id, chapter_key;
```

**Current totals (2026-04-07 baseline — updated post-audit-pass3):**

> ⚠️ These numbers go stale. Always re-query before starting an audit cycle:
> `sudo mysql dungeoncrawler -e "SELECT status, COUNT(*) FROM dc_requirements GROUP BY status;"`

| book | chapter | pending | implemented |
|------|---------|---------|-------------|
| core | ch03 (Classes) | 904 | 3 |
| core | ch04 (Skills) | 188 | 10 |
| core | ch05 (Feats) | 24 | 0 |
| core | ch06 (Equipment) | 161 | 0 |
| core | ch07 (Spells) | 135 | 0 |
| core | ch09 (Playing the Game) | 1 | 241 |
| core | ch10 (Game Mastering) | 80 | 7 |
| core | ch11 (Crafting & Treasure) | 154 | 0 |
| apg | ch01–ch06 | 595 | 0 |
| gmg | ch01–ch04 | 150 | 0 |
| gng | ch01–ch05 | 30 | 0 |
| som | ch01–ch05 | 30 | 0 |
| gam | s01–s06 | 36 | 0 |
| b1–b3 | all | 54 | 0 |
| **TOTAL** | | **~2536** | **261** |

---

## The Audit Process (Two Tracks)

### Track A — Code Verification (QA-first)
**Use when**: The relevant engine/service is known to be at least partially implemented.

Examples: `core/ch09` (combat rules — CombatEngine, HPManager, etc.),
`core/ch03` individual classes where a class-specific service may exist.

**Steps:**
1. Check if the relevant service(s) exist in
   `/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/`
2. If yes → dispatch a QA inbox item (see **QA Batch Format** below)
3. QA returns PASS or BLOCK per requirement group
4. **PASS** → QA outbox **must include** a PM handoff line:
   `PM action required: run drush roadmap-set-status implemented --book=X --chapter=Y --section="Z"`
   PM creates a self-inbox item upon reading this outbox, runs drush, and confirms in their own outbox.
5. **BLOCK** → find or create `features/dc-*/` file (with `DB sections` field set), dispatch dev inbox item

**Track A → Track B transition (partial chapters):**
For chapters where some reqs are implemented and others are not (e.g., `core/ch04` Skills,
`core/ch10` GM tools): run Track A first to identify and mark all already-implemented reqs,
then treat remaining BLOCK items as Track B work — create/link feature files for each gap
and dispatch dev items. Do not batch the whole chapter as Track B just because it has gaps.

### Track B — Feature Pipeline (Dev-first)
**Use when**: No relevant service exists for the chapter's content.

Examples: `apg/ch02` (APG classes — no class-specific services exist),
`gmg/ch01` (GM tools — no GM narrative pipeline implemented),
`b1/s02` (Bestiary monsters — no monster AI implemented).

**Steps:**
1. Check if a `features/dc-*` file already covers this area
2. If a feature exists with `status: ready/in_progress/done` → link that feature, skip creation
3. If no feature exists → create `features/dc-*/feature.md` stub (PM-owned, PM creates)

   **Required fields for a new feature stub:**
   ```
   - Work item id: dc-<product>-<short-name>
   - Website: dungeoncrawler
   - Module: dungeoncrawler_content
   - Status: planned          # planned | ready | in_progress | done | deferred
   - Priority: P<N>
   - PM owner: pm-dungeoncrawler
   - Dev owner: dev-dungeoncrawler
   - QA owner: qa-dungeoncrawler
   - Source: <PF2E book reference>
   - Category: game-mechanic  # or: ui | content | infra
   - Created: <YYYY-MM-DD>
   - DB sections: core/ch03/Alchemist, core/ch03/Alchemist-Feats  # book/chapter/section rows this covers
   ```
   See any existing `features/dc-*/feature.md` for a complete example.
   The `DB sections` field is required — it is the only record linking this feature to specific
   `dc_requirements` rows until a `feature_id` column is added to the DB.

4. Dispatch BA inbox item for requirements analysis + acceptance criteria
5. Dispatch dev inbox item after BA delivers AC + test plan
6. **After dev ships and QA approves:** QA outbox must include a PM handoff line:
   `PM action required: run drush roadmap-set-status implemented --book=X --chapter=Y --section="Z"`
   PM creates a self-inbox item, runs drush, confirms in outbox. PM owns this step — do not leave it
   to dev or QA to assume PM will notice.

---

## QA Batch Format (Track A)

Dispatch to `sessions/qa-dungeoncrawler/inbox/<date>-roadmap-req-<book>-<chapter>-<section>`:

```markdown
- command: |
    Roadmap requirements verification: <book> <chapter> — <section>
    
    Verify requirements <req_id_start>–<req_id_end> for section "<section>"
    against the production codebase at https://dungeoncrawler.forseti.life
    
    For each requirement:
    - Locate the implementing service/method in the codebase
    - Run a drush probe to verify it works at runtime
    - Return PASS (with service name + method) or BLOCK (with gap ID + description)
    
    Rulebook reference: /home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/<file>.md
    
    On completion, PM will:
    - Mark PASS reqs implemented via drush
    - Create feature pipeline items for BLOCK reqs
- Agent: qa-dungeoncrawler
- Status: pending
- roi: <N>
```

**Batch sizing guideline:**
- Group by `section` within a chapter (natural boundary)
- Aim for 10–30 requirements per batch (QA's sweet spot for one outbox response)
- Core ch03 classes: one batch per class (Alchemist ~115 reqs → split into sub-sections)

---

## Bulk Status Query (to find what's pending per section)

```bash
sudo mysql dungeoncrawler -e "
SELECT book_id, chapter_key, section, COUNT(*) as reqs
FROM dc_requirements
WHERE status='pending' AND book_id='core' AND chapter_key='ch03'
GROUP BY section ORDER BY section;
"
```

---

## Drush Update Commands (after QA PASS)

> **Path note:** `/var/www/html/dungeoncrawler` is the deployed production Drupal root
> (where drush commands should run to affect the live DB). The development source is at
> `/home/ubuntu/forseti.life/sites/dungeoncrawler/`. Use the production path for all
> `roadmap-set-status` drush commands; use the source path for code reading/editing.

```bash
cd /var/www/html/dungeoncrawler

# Always dry-run first
./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life \
  dungeoncrawler:roadmap-set-status implemented \
  --book=core --chapter=ch03 --section="Alchemist" --dry-run

# Apply
./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life \
  dungeoncrawler:roadmap-set-status implemented \
  --book=core --chapter=ch03 --section="Alchemist"
```

---

## SQL Audit Tracker (mandatory — must be committed as artifact)

**This is not optional.** Every audit cycle must produce a committed coverage artifact so the
next cycle can verify what was claimed, not re-scan from scratch.

**At cycle start**, create and populate the tracker:

```sql
CREATE TABLE roadmap_audit (
    id TEXT PRIMARY KEY,  -- e.g. "core-ch03-Alchemist"
    book_id TEXT,
    chapter_key TEXT,
    section TEXT,
    req_count INTEGER,
    track TEXT,           -- 'A' (QA-first) or 'B' (feature-pipeline)
    status TEXT DEFAULT 'pending',  -- pending / qa_dispatched / qa_done / dev_dispatched / implemented / covered
    feature_id TEXT,      -- dc-cr-* feature file if Track B (required when status=covered)
    notes TEXT
);
```

Load the queue:
```bash
sudo mysql dungeoncrawler -e \
  "SELECT book_id, chapter_key, section, COUNT(*) FROM dc_requirements WHERE status='pending' GROUP BY book_id, chapter_key, section ORDER BY book_id, chapter_key, section;" \
  > /tmp/pending_reqs.txt
```

**At cycle end**, export and commit:
```bash
# Export tracker to artifact
sqlite3 /tmp/roadmap_audit.db "SELECT * FROM roadmap_audit;" > \
  sessions/pm-dungeoncrawler/artifacts/roadmap-audit-<YYYYMMDD>.tsv
git add -f sessions/pm-dungeoncrawler/artifacts/roadmap-audit-<YYYYMMDD>.tsv
```

The committed artifact is the **evidence of coverage** for every section claimed as "in the pipeline."
Without it, coverage claims are unverifiable.

---

## Completion Criteria

### Per-section complete
A chapter/section is fully audited when:
```sql
SELECT COUNT(*) FROM dc_requirements
WHERE book_id='core' AND chapter_key='ch03' AND section='Alchemist' AND status='pending';
-- Must return 0
```

AND either:
- `status='implemented'` in `dc_requirements` (QA-verified), OR
- A `features/dc-*/feature.md` exists for the topic (in pipeline)

### Whole-roadmap audit complete
The full roadmap audit is complete when **every section** satisfies the per-section criteria above.

**Once `feature_id` column exists in `dc_requirements`** (open dev task), the definitive check is:
```sql
-- Returns 0 rows when all pending reqs have a feature mapped
SELECT book_id, chapter_key, section, COUNT(*) as uncovered
FROM dc_requirements
WHERE status = 'pending' AND (feature_id IS NULL OR feature_id = '')
GROUP BY book_id, chapter_key, section
ORDER BY book_id, chapter_key;
```

**Until `feature_id` column is added**, coverage must be verified against the committed audit
tracker artifact (`sessions/pm-dungeoncrawler/artifacts/roadmap-audit-<date>.tsv`):
- Every row must have `status = implemented` OR (`status = covered` AND `feature_id` is set)
- Any row with `status = pending` at cycle end is an uncovered gap

The PM outbox for the audit cycle must record:
- Total implemented count (from DB)
- Total pending count (from DB)
- Count of sections mapped to feature files (from audit tracker)
- Any explicitly deferred sections (with Board/CEO decision reference)
- Link to committed audit tracker artifact

### Audit cycle triggers
A new audit pass should be run when any of the following occur:
- A release cycle closes (post-release improvement round)
- A new book's requirements are imported into `dc_requirements`
- The CEO requests a coverage gap check
- QA auto-site audit returns new BLOCK findings with no mapped feature file

---

## PM Context: What Each Book Covers

| Book | Relevance to DungeonCrawler | Track default |
|------|-----------------------------|--------------|
| core/ch03 | All 12 core classes — no class-specific services exist yet | B (per class) |
| core/ch04 | Skills — `SkillSystem` partially implemented | A then B for gaps |
| core/ch05 | General feats — no feat engine yet | B |
| core/ch06 | Equipment — `InventoryManagementService` partial | A then B for gaps |
| core/ch07 | Spells — no spellcasting service | B |
| core/ch09 | Combat/encounter rules — CombatEngine ~95% | A (1 req remaining: REQ 2093) |
| core/ch10 | GM tools — `AiGmService` partial | A then B |
| core/ch11 | Crafting/treasure — no crafting service | B |
| apg | APG classes/archetypes/spells — no APG-specific services | B |
| gmg | GM mastery tools — GMG narrative not implemented | B |
| gng/som/gam | Guns, magic, gods — not in current roadmap scope | B (deferred) |
| b1–b3 | Bestiaries — no monster AI/stat block engine | B (deferred) |

---

## Related runbooks and references
- `runbooks/pf2e-requirements-extraction.md` — how BA extracts requirements from source books
- `runbooks/intake-to-qa-handoff.md` — feature pipeline (Track B) detail
- `runbooks/shipping-gates.md` — Gate 2 QA evidence requirements
- `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` — Roadmap maintenance section
