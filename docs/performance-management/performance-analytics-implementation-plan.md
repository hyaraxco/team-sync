# Performance Management & Analytics Dashboard Implementation Plan

## 1. Overview

This document outlines the detailed implementation plan for two major features:

1.  **Performance Management System (Phase 1):** Core functionalities including review cycles, goal setting, self/manager assessments, and continuous feedback.
2.  **Analytics Dashboard Enhancement (Phase 1):** Enhancing existing analytics (Workforce, Attendance, Leave, Payroll, Projects) with new metrics, improved visualizations, and optimized performance.

## 2. Architecture & Data Model

### 2.1 Performance Management Tables

- `performance_review_cycles`: Defines the overall review periods.
- `performance_reviews`: Individual employee reviews linked to a cycle.
- `performance_review_sections`: Template sections for the review.
- `performance_review_responses`: Actual ratings and comments for each section.
- `performance_goals`: Individual employee goals (OKRs, KPIs).
- `performance_goal_updates`: Progress tracking for goals.
- `performance_feedback`: Continuous feedback mechanism.

### 2.2 Analytics Dashboard Enhancements

- `analytics_snapshots`: A new table to store pre-calculated metrics daily via a scheduled job to improve dashboard performance.
- `custom_reports`: (Future Phase Placeholder) Table for saved custom report configurations.

## 3. Backend Implementation Strategy (Laravel)

### 3.1 Step 1: Database Migrations & Models

- Create migrations for all new tables listed in 2.1 and 2.2.
- Create Eloquent Models (`PerformanceReviewCycle`, `PerformanceReview`, etc.).
- Define relationships between new models and existing models (`EmployeeProfile`, `User`, `Team`).

### 3.2 Step 2: Repositories & Services

- **Performance Management:**
  - Create Repository Interfaces and Implementations for Review Cycles, Reviews, Goals, and Feedback.
  - Create Service classes to handle complex business logic (e.g., status transitions, calibration calculations).
- **Analytics Enhancements:**
  - Update existing `AnalyticsRepository` with methods for new metrics (turnover rate, compliance rate, cost trends, etc.).
  - Implement a `DailyMetricsCalculator` service and a scheduled command (e.g., `php artisan analytics:calculate-daily-snapshots`) to populate the `analytics_snapshots` table.

### 3.3 Step 3: API Controllers & Routes

- Create controllers for Performance Management endpoints (CRUD operations for cycles, reviews, goals, feedback).
- Update `AnalyticsController` to expose new metrics and utilize the `analytics_snapshots` table when applicable.
- Define routes in `api.php` grouping them appropriately under `v1/performance` and updating `v1/analytics`.

### 3.4 Step 4: Authorization & Permissions

- Add new permissions to the system (e.g., `review-cycle-manage`, `review-self-submit`, `goal-assign-team`).
- Apply these permissions using middleware on the new routes.
- Ensure the Analytics endpoints enforce data scoping based on the user's role (Manager sees team, HR sees all).

### 3.5 Step 5: Notifications

- Create Laravel Notification classes for key events (Review Cycle Started, Goal Assigned, Feedback Received).

## 4. Frontend Implementation Strategy (Vue.js + Pinia)

### 4.1 Step 1: Pinia Stores

- Create new stores: `reviewCycle.js`, `performanceReview.js`, `performanceGoal.js`, `performanceFeedback.js`.
- Update `analytics.js` store to handle fetching the new enhanced metrics and date range filtering.

### 4.2 Step 2: Performance Management UI

- Create views and components for HR to manage Review Cycles.
- Create interfaces for Employees to set Goals, submit Self-Assessments, and give/receive Feedback.
- Create interfaces for Managers to review Team Goals and submit Manager Assessments.

### 4.3 Step 3: Analytics Dashboard Enhancements

- Update existing components (`WorkforceAnalytics.vue`, `AttendanceAnalytics.vue`, etc.) to display the new metrics using appropriate charts (Line, Bar, Donut via Chart.js/ApexCharts).
- Implement a global date range picker and filter component that applies across all analytics tabs.
- Enhance the Export functionality (Excel/PDF) to include the newly added data points.

## 5. Testing & Validation

- **Backend:** Write PHPUnit tests for the new repositories, services (especially the metrics calculation logic), and API endpoints.
- **Frontend:** Write component tests for the new UI elements and store logic.
- **E2E:** Implement end-to-end tests covering full user flows (e.g., creating a review cycle -> employee self-assessment -> manager assessment).

## 6. Execution Order

1.  **Backend Foundations:** Migrations, Models, and basic Repositories.
2.  **Analytics Backend Enhancement:** Implement new metrics logic and the snapshot calculation job.
3.  **Performance Management API:** Build out the full API for reviews, goals, and feedback.
4.  **Analytics Frontend Enhancement:** Update the dashboard UI to consume the new metrics.
5.  **Performance Management Frontend:** Build the new UI modules for reviews, goals, and feedback.
6.  **Integration & Polish:** Connect everything, refine permissions, add notifications, and optimize performance.
