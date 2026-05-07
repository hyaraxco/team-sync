# SPEC — Analytics & Notifications Enhancement Design

> **Date:** 2026-04-27
> **Context:** Phase 2 Gap Closure for Analytics (GAP-4) and Performance Notifications (GAP-5).

## 1. Performance Notifications (GAP-5)

### Approach
Implement 7 Database Notifications for the Performance Management lifecycle.

### Notification Classes (`app/Notifications/Performance/`)
1. `ReviewCycleStarted` (Trigger: HR activates a cycle)
2. `SelfAssessmentCompleted` (Trigger: Employee submits self-assessment)
3. `ReviewCompleted` (Trigger: HR completes calibration)
4. `CalibrationReady` (Trigger: All manager assessments submitted)
5. `GoalAssigned` (Trigger: Manager assigns goal)
6. `GoalDeadlineApproaching` (Trigger: Daily command checks goals due in 7 days)
7. `FeedbackReceived` (Trigger: Feedback submitted)

### Data Contract (`toArray()`)
```json
{
  "title": "Notification Title",
  "message": "Notification details...",
  "type": "performance_review|performance_goal|performance_feedback",
  "action_url": "/api/v1/performance/...",
  "reference_id": 123
}
```

## 2. Missing Analytics Endpoints (GAP-4)

### Approach
Add the 8 missing specific analytics endpoints to `AnalyticsController` and `AnalyticsRepository`.

### New Endpoints & Logic
#### Workforce
- `GET /api/v1/analytics/workforce/demographics` → Group by `employment_type` and calculate age/gender (if fields exist, otherwise simple distribution).

#### Attendance
- `GET /api/v1/analytics/attendance/correction-frequency` → Count `attendance_corrections` grouped by month/status.
- `GET /api/v1/analytics/attendance/policy-mismatches` → Count `attendance_policy_mismatches` grouped by month/status.

#### Leave
- `GET /api/v1/analytics/leave/approval-turnaround` → Average `DATEDIFF(approved_at, created_at)` from `leave_requests`.
- `GET /api/v1/analytics/leave/type-distribution` → Count `leave_requests` grouped by `leave_type`.

#### Payroll
- `GET /api/v1/analytics/payroll/cost-per-employee` → Total Payroll Cost / Total Active Employees.
- `GET /api/v1/analytics/payroll/processing-time` → Average time difference between period `open` and `locked` transitions (if tracked).

#### Project
- `GET /api/v1/analytics/project/resource-utilization` → Count of completed tasks vs assigned tasks per staff member or team.
