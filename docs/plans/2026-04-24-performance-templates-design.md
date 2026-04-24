# Design Doc: Performance Review Templates per Role

## Overview
Currently, the HRIS uses a global set of performance review sections (Competency/KPI) with fixed weights. This does not support different evaluation criteria for different roles or departments.

## Proposed Changes

### 1. Data Schema
- **`review_templates`**: Defines a group of evaluation parameters.
    - `id`, `name`, `description`, `is_active`, `is_default` (boolean).
- **`review_template_sections`**: Mapping table with weights.
    - `template_id`, `performance_review_section_id`, `weight` (decimal).
- **`performance_reviews`**:
    - Add `review_template_id` to store which template was used for historical accuracy.
- **`job_information`**:
    - Add `review_template_id` as the default template for the specific job position.

### 2. Implementation Logic
- **Initialization**: When `generateReviews` is called, the system looks up `JobInformation->review_template_id`. If null, it uses the template marked as `is_default`.
- **Calculation**: Refactor `PerformanceRatingHelper` and `PerformanceReviewRepository` to fetch weights from the pivot table associated with the review's `template_id`.
- **TOPSIS**: Adjust `getEmployeeScoresForCycle` to calculate C1 and C2 using template-specific weights.

### 3. API Endpoints
- `GET /api/v1/performance/templates`: List all templates.
- `POST /api/v1/performance/templates`: Create template with section mapping.
- `GET /api/v1/performance/templates/{id}`: Detail with section weights.
- `PUT /api/v1/performance/templates/{id}`: Update mapping.

### 4. Frontend Components
- **`TemplateManagement.vue`**: CRUD for templates.
- **`TemplatePicker.vue`**: Component to assign templates to Job Titles/Employees.

## Success Criteria
- [ ] HR can create different templates for "Manager" and "Staff".
- [ ] "Manager" template has different weights for "Leadership" than "Staff".
- [ ] Final ratings are correctly calculated based on the template's specific weights.
- [ ] TOPSIS rankings correctly use role-specific weighted scores.
