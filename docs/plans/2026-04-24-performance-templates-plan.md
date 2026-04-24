# Performance Review Templates Implementation Plan

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Implement role-based performance review templates with custom weights to enable fair evaluation across different job positions.

**Architecture:** Introduction of `ReviewTemplate` model and a pivot table `review_template_sections` to store specific weights. Refactor existing calculation helpers to be template-aware.

**Tech Stack:** Laravel (PHP), Vue.js (Frontend), MySQL.

---

### Task 1: Database Migrations

**Files:**
- Create: `team-sync-be/database/migrations/2026_04_24_000001_create_performance_review_templates_tables.php`
- Create: `team-sync-be/database/migrations/2026_04_24_000002_add_template_id_to_performance_reviews_and_job_info.php`

**Step 1: Write migrations**
Implement `up()` and `down()` for `review_templates`, `review_template_sections` (pivot), and add FK columns to `performance_reviews` and `job_informations`.

**Step 2: Run migrations**
Run: `php artisan migrate`

---

### Task 2: Models & Relationships

**Files:**
- Create: `team-sync-be/app/Models/PerformanceReviewTemplate.php`
- Modify: `team-sync-be/app/Models/PerformanceReview.php`
- Modify: `team-sync-be/app/Models/JobInformation.php`
- Modify: `team-sync-be/app/Models/PerformanceReviewSection.php`

**Step 1: Implement PerformanceReviewTemplate model**
Define `$fillable` and `sections()` relationship with pivot weights.

**Step 2: Update existing models**
Add relationships: `PerformanceReview -> belongsTo(Template)`, `JobInformation -> belongsTo(Template)`.

---

### Task 3: Refactor Calculation Helper (TDD)

**Files:**
- Modify: `team-sync-be/app/Helpers/PerformanceRatingHelper.php`
- Test: `team-sync-be/tests/Unit/Helpers/PerformanceRatingHelperTest.php`

**Step 1: Write failing test**
Create a test case where a review has a specific template, and verify it uses pivot weights instead of global weights.

**Step 2: Refactor calculateFinalRating**
Change `PerformanceReviewResponse::with('section')` logic to include template weight lookup.

**Step 3: Run tests**
Run: `./vendor/bin/pest tests/Unit/Helpers/PerformanceRatingHelperTest.php`

---

### Task 4: Refactor Review Generation

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PerformanceReviewCycleController.php`
- Modify: `team-sync-be/app/Repositories/PerformanceReviewRepository.php`

**Step 1: Update generateReviews logic**
Inject `review_template_id` during `createReview` based on employee's job information.

**Step 2: Update TOPSIS score calculation**
Refactor `getEmployeeScoresForCycle` in repository to use template-aware weights for C1 and C2.

---

### Task 5: Template Management API

**Files:**
- Create: `team-sync-be/app/Http/Controllers/PerformanceReviewTemplateController.php`
- Modify: `team-sync-be/routes/api.php`

**Step 1: Implement CRUD**
Index, Store, Show, Update, Destroy endpoints for templates and their section mappings.

---

### Task 6: Frontend Integration

**Files:**
- Create: `team-sync-fe/src/views/admin/performance/TemplateManagement.vue`
- Modify: `team-sync-fe/src/router/index.js`

**Step 1: Build Template Management UI**
A page to list and edit templates and their section weights.
