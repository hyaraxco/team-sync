# ReviewDetail.vue + Performance Data Seeder - Design Document

**Date**: 2026-04-21
**Status**: Approved

## Overview

Implement the ReviewDetail.vue page (tab-based layout) and create minimal seed data so the Performance Management module can be tested end-to-end.

## Part 1: Performance Data Seeder

### Goal
Create minimal but representative seed data for the Performance Management module.

### What to create

1. **Add `PerformanceReviewSectionSeeder` to `DatabaseSeeder.php`** - already exists with 5 sections (Technical Skills, Productivity, Communication, Initiative, Leadership), just needs to be called.

2. **Create `PerformanceDataSeeder`** - a single seeder that creates:
   - 1 active review cycle (Q1 2026, with realistic deadlines)
   - Reviews for existing employees with varied statuses:
     - 2x `pending_self` (employee can test self-assessment)
     - 2x `pending_manager` (with self-assessment responses filled)
     - 1x `pending_calibration` (with self + manager responses)
     - 1x `completed` (all data filled, final rating assigned)
   - Review responses per section for reviews past the self-assessment stage

### Dependencies
- Requires existing employees (from EmployeeSeeder) and users with manager/HR roles
- Sections must be seeded first (PerformanceReviewSectionSeeder)

## Part 2: ReviewDetail.vue

### Layout
Tab-based layout using the application's primary tab pattern (blue-gradient, grid-based).

### Tabs

| Tab | Icon | Content |
|-----|------|---------|
| Overview | FileText | Review info, employee/reviewer, cycle, status, deadlines, final rating, timeline |
| Self Assessment | User | Per-section rating (1-5) + comments form/readonly |
| Manager Assessment | UserCheck | Per-section rating + comments + final rating form/readonly |
| Calibration | Scale | Side-by-side self/manager ratings + calibration form + final rating + label |

### Role & Status Behavior

| Status | Employee | Manager | HR |
|--------|----------|---------|-----|
| pending_self | Overview + **editable** Self Assessment | Overview + tabs (readonly/empty) | Overview + tabs (readonly/empty) |
| pending_manager | Overview + Self (readonly) | Overview + Self (readonly) + **editable** Manager Assessment | Overview + tabs (readonly) |
| pending_calibration | Overview + Self/Manager (readonly) | Overview + Self/Manager (readonly) | Overview + all readonly + **editable** Calibration |
| completed | All tabs readonly | All tabs readonly | All tabs readonly |

### Tab Styling
Uses primary pattern from AnalyticsDashboard/PayrollSettings:
- Container: `bg-white border border-[#DCDEDD] rounded-[20px] p-3 mb-6`
- Grid: `grid grid-cols-2 md:grid-cols-4 gap-3`
- Active: `blue-gradient blue-btn-shadow border-[#2151A0] text-white`
- Inactive: `border-[#DCDEDD] text-brand-dark hover:border-[#0C51D9] hover:border-2 bg-white`
- Content switching: `v-show` (preserve form state)

### Assessment Form Design
- Section cards with: name, description, weight badge
- Rating: radio buttons 1-5 with labels (Unsatisfactory to Outstanding)
- Comments: textarea
- Readonly mode: filled stars/number + text display
- Submit button with confirmation modal

### Technical
- Store: `usePerformanceReviewStore` - fetchReviewById, fetchActiveSections, submitSelfAssessment, submitManagerAssessment, calibrateReview
- Auth store for current user role detection
- Loading: spinner + skeleton
- Error: Alert component
- Success: redirect back to list after submit

### Rating Scale
| Rating | Label | Color |
|--------|-------|-------|
| 5 (>= 4.5) | Outstanding | emerald |
| 4 (>= 3.5) | Exceeds Expectations | blue |
| 3 (>= 2.5) | Meets Expectations | yellow |
| 2 (>= 1.5) | Needs Improvement | orange |
| 1 (< 1.5) | Unsatisfactory | red |
