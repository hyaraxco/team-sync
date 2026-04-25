@# SPEC — Analytics Dashboard Enhancement

> This file is the single source of truth for Analytics Dashboard features.
> Implementation must NOT deviate from any contract defined here.
> If anything is unclear, STOP and ask before implementing.

## Principles

- **Data Accuracy**: All metrics must be calculated from source data with clear definitions
- **Performance**: Analytics queries must be optimized for large datasets
- **Role-based Access**: Users only see metrics relevant to their role and scope
- **Actionable Insights**: Metrics should drive decisions, not just display numbers
- **Real-time Updates**: Dashboard should reflect current state with minimal lag

## Scope

### Phase 1 - Core Analytics

- Workforce analytics (headcount, turnover, demographics)
- Attendance analytics (trends, patterns, compliance)
- Leave analytics (utilization, balance trends)
- Payroll analytics (cost trends, distribution)
- Project analytics (completion rates, resource allocation)
- Custom date range filtering
- Export capabilities (CSV, PDF)

### Out of Scope (Future Phases)

- Predictive analytics (attrition prediction, demand forecasting)
- Custom report builder with drag-and-drop
- Scheduled report delivery
- Benchmarking against industry standards
- Advanced visualizations (heatmaps, network graphs)

## Existing Analytics Components

Based on codebase analysis, these components already exist:

- [`AttendanceAnalytics.vue`](team-sync-fe/src/components/admin/analytics/AttendanceAnalytics.vue:1)
- [`LeaveAnalytics.vue`](team-sync-fe/src/components/admin/analytics/LeaveAnalytics.vue:1)
- [`PayrollAnalytics.vue`](team-sync-fe/src/components/admin/analytics/PayrollAnalytics.vue:1)
- [`ProjectAnalytics.vue`](team-sync-fe/src/components/admin/analytics/ProjectAnalytics.vue:1)
- [`WorkforceAnalytics.vue`](team-sync-fe/src/components/admin/analytics/WorkforceAnalytics.vue:1)
- [`AnalyticsDashboard.vue`](team-sync-fe/src/views/admin/analytics/AnalyticsDashboard.vue:1)
- [`analytics.js`](team-sync-fe/src/stores/analytics.js:1) store

## Enhancement Requirements

### 1. Workforce Analytics

**Existing Metrics to Enhance:**

- Headcount trends (monthly/quarterly)
- Department distribution
- Employment type breakdown

**New Metrics:**

- Turnover rate (monthly, quarterly, annual)
- Average tenure by department
- New hire trends
- Termination reasons analysis
- Age and gender demographics
- Diversity metrics

**Calculations:**

```
Turnover Rate = (Terminations / Average Headcount) × 100
Average Tenure = Sum(tenure_days) / Active Employees
Headcount Growth = ((Current - Previous) / Previous) × 100
```

### 2. Attendance Analytics

**Existing Metrics to Enhance:**

- Daily attendance rate
- Late arrivals count
- Absent days

**New Metrics:**

- Attendance compliance rate by department
- Attendance patterns (day of week analysis)
- Policy mismatch trends
- Remote vs office attendance ratio (for hybrid)
- Average check-in/check-out times
- Attendance correction frequency

**Calculations:**

```
Attendance Rate = (Present Days / Expected Working Days) × 100
Compliance Rate = (Days without Policy Mismatch / Total Days) × 100
Late Rate = (Late Days / Present Days) × 100
```

### 3. Leave Analytics

**Existing Metrics to Enhance:**

- Leave requests by type
- Approval rates

**New Metrics:**

- Leave utilization rate by type
- Leave balance trends
- Peak leave periods (seasonality)
- Average leave duration
- Leave approval turnaround time
- Sick leave patterns (potential abuse detection)

**Calculations:**

```
Utilization Rate = (Leave Days Taken / Leave Days Entitled) × 100
Approval Time = Average(approved_at - submitted_at)
Peak Period = Month with highest leave volume
```

### 4. Payroll Analytics

**Existing Metrics to Enhance:**

- Total payroll cost
- Average salary

**New Metrics:**

- Payroll cost trends (month-over-month)
- Salary distribution by department/level
- Deduction analysis
- Payroll cost per employee
- Overtime cost trends
- Payroll processing time

**Calculations:**

```
Payroll Growth = ((Current Month - Previous Month) / Previous Month) × 100
Cost per Employee = Total Payroll / Active Employees
Deduction Rate = (Total Deductions / Gross Salary) × 100
```

### 5. Project Analytics

**Existing Metrics to Enhance:**

- Project completion rate
- Task status distribution

**New Metrics:**

- Project timeline adherence
- Resource utilization by team
- Task completion velocity
- Overdue task trends
- Project budget vs actual (if budget tracking added)
- Team productivity metrics

**Calculations:**

```
Completion Rate = (Completed Projects / Total Projects) × 100
Timeline Adherence = Projects Completed On Time / Total Completed
Velocity = Tasks Completed / Sprint Duration
```

## Data Model Enhancements

### `analytics_snapshots`

Store pre-calculated metrics for performance.

Fields:

- `id` primary key
- `metric_type` enum: `workforce`, `attendance`, `leave`, `payroll`, `project`
- `metric_name` string (e.g., "turnover_rate", "attendance_rate")
- `period_type` enum: `daily`, `weekly`, `monthly`, `quarterly`, `annual`
- `period_start` date
- `period_end` date
- `value` decimal(12,2)
- `metadata` json (additional context)
- `calculated_at` timestamp
- timestamps

Contract:

- Snapshots calculated daily via scheduled job
- Historical snapshots never modified (immutable)
- Used for trend analysis and performance optimization

### `custom_reports`

Save custom report configurations (future phase).

Fields:

- `id` primary key
- `user_id` foreign key to `users`
- `name` string
- `report_type` string
- `filters` json
- `metrics` json
- `is_shared` boolean default false
- timestamps

## API Endpoints

### Workforce Analytics

- `GET /api/v1/analytics/workforce/headcount-trend` - Headcount over time
- `GET /api/v1/analytics/workforce/turnover-rate` - Turnover metrics
- `GET /api/v1/analytics/workforce/demographics` - Age, gender, diversity
- `GET /api/v1/analytics/workforce/department-distribution` - Headcount by department
- `GET /api/v1/analytics/workforce/tenure-analysis` - Average tenure metrics

### Attendance Analytics

- `GET /api/v1/analytics/attendance/compliance-rate` - Attendance compliance
- `GET /api/v1/analytics/attendance/patterns` - Day-of-week patterns
- `GET /api/v1/analytics/attendance/policy-mismatches` - Mismatch trends
- `GET /api/v1/analytics/attendance/remote-office-ratio` - Hybrid work stats
- `GET /api/v1/analytics/attendance/correction-frequency` - Correction trends

### Leave Analytics

- `GET /api/v1/analytics/leave/utilization-rate` - Leave utilization
- `GET /api/v1/analytics/leave/balance-trends` - Balance over time
- `GET /api/v1/analytics/leave/peak-periods` - Seasonality analysis
- `GET /api/v1/analytics/leave/approval-turnaround` - Approval time metrics
- `GET /api/v1/analytics/leave/type-distribution` - Leave by type

### Payroll Analytics

- `GET /api/v1/analytics/payroll/cost-trends` - Payroll cost over time
- `GET /api/v1/analytics/payroll/salary-distribution` - Salary ranges
- `GET /api/v1/analytics/payroll/deduction-analysis` - Deduction breakdown
- `GET /api/v1/analytics/payroll/cost-per-employee` - Per-employee cost
- `GET /api/v1/analytics/payroll/processing-time` - Processing metrics

### Project Analytics

- `GET /api/v1/analytics/project/completion-rate` - Project completion
- `GET /api/v1/analytics/project/timeline-adherence` - On-time delivery
- `GET /api/v1/analytics/project/resource-utilization` - Team utilization
- `GET /api/v1/analytics/project/task-velocity` - Task completion velocity
- `GET /api/v1/analytics/project/overdue-trends` - Overdue task analysis

### Export

- `POST /api/v1/analytics/export` - Export analytics data (CSV/PDF)

## Permissions & Access Control

### Permission Matrix

| Metric Category      | Employee     | Manager       | HR    | Finance |
| -------------------- | ------------ | ------------- | ----- | ------- |
| Workforce Analytics  | -            | Team only     | ✓ All | -       |
| Attendance Analytics | Own only     | Team only     | ✓ All | -       |
| Leave Analytics      | Own only     | Team only     | ✓ All | -       |
| Payroll Analytics    | Own only     | -             | ✓ All | ✓ All   |
| Project Analytics    | Own projects | Team projects | ✓ All | -       |

## UI/UX Requirements

### Dashboard Layout

```
┌─────────────────────────────────────────────────────┐
│ Analytics Dashboard                    [Date Range] │
├─────────────────────────────────────────────────────┤
│ [Workforce] [Attendance] [Leave] [Payroll] [Project]│
├─────────────────────────────────────────────────────┤
│                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────┐ │
│  │ Metric Card  │  │ Metric Card  │  │ Metric    │ │
│  │   1,234      │  │   56.7%      │  │   Card    │ │
│  │   ↑ 12%      │  │   ↓ 3%       │  │           │ │
│  └──────────────┘  └──────────────┘  └───────────┘ │
│                                                      │
│  ┌────────────────────────────────────────────────┐ │
│  │         Trend Chart (Line/Bar)                 │ │
│  │                                                │ │
│  └────────────────────────────────────────────────┘ │
│                                                      │
│  ┌──────────────────┐  ┌──────────────────────────┐│
│  │ Distribution     │  │ Comparison Table         ││
│  │ (Pie/Donut)      │  │                          ││
│  └──────────────────┘  └──────────────────────────┘│
│                                                      │
│                              [Export] [Refresh]     │
└─────────────────────────────────────────────────────┘
```

### Key Features

- Tab navigation between metric categories
- Date range picker (preset ranges: Last 7 days, Last 30 days, Last 3 months, Last year, Custom)
- Drill-down capability (click metric to see details)
- Comparison mode (compare periods)
- Export to CSV/PDF
- Auto-refresh option
- Responsive design for mobile

### Chart Types

- Line charts: Trends over time
- Bar charts: Comparisons across categories
- Pie/Donut charts: Distribution
- Stacked bar charts: Multi-dimensional data
- Sparklines: Inline trend indicators

## Performance Optimization

### Caching Strategy

- Cache analytics snapshots for 1 hour
- Pre-calculate daily metrics via scheduled job
- Use Redis for frequently accessed metrics
- Implement pagination for large datasets

### Query Optimization

- Use database indexes on date fields
- Aggregate data at database level
- Limit date ranges to prevent large queries
- Use materialized views for complex calculations

### Frontend Optimization

- Lazy load chart components
- Debounce filter changes
- Use virtual scrolling for large tables
- Implement skeleton loaders

## Validation Rules

### Date Range

- Maximum range: 2 years
- Start date must be before end date
- Cannot select future dates for historical metrics

### Export

- Maximum 10,000 rows per export
- Export format: CSV or PDF only
- File size limit: 10MB

## Test Scenarios

### Workforce Analytics Tests

1. Headcount trend calculated correctly for date range
2. Turnover rate matches manual calculation
3. Department distribution sums to 100%
4. Manager only sees their team metrics
5. HR sees all company metrics

### Attendance Analytics Tests

1. Attendance rate calculated correctly
2. Policy mismatch trends match actual data
3. Remote/office ratio correct for hybrid employees
4. Day-of-week pattern shows correct distribution

### Leave Analytics Tests

1. Utilization rate calculated correctly
2. Peak period identified accurately
3. Approval turnaround time matches actual data
4. Leave balance trends show correct trajectory

### Payroll Analytics Tests

1. Payroll cost trend matches actual payroll data
2. Salary distribution shows correct ranges
3. Deduction analysis sums correctly
4. Finance role can access all payroll metrics

### Project Analytics Tests

1. Completion rate calculated correctly
2. Timeline adherence matches project deadlines
3. Task velocity calculated per sprint
4. Overdue trends show correct counts

### Export Tests

1. CSV export contains correct data
2. PDF export renders correctly
3. Large datasets handled without timeout
4. Export respects role permissions

## Integration Points

### Existing Systems

- **Staff Member Management**: Workforce metrics source
- **Attendance System**: Attendance metrics source
- **Leave Management**: Leave metrics source
- **Payroll System**: Payroll metrics source
- **Project Management**: Project metrics source
- **Notifications**: Alert on anomalies (future)

### External Integrations (Future)

- Business Intelligence tools (Tableau, Power BI)
- Data warehouse export
- API for third-party analytics

## Assumptions & Constraints

- Analytics data refreshed daily (not real-time in Phase 1)
- Historical data available from system launch date
- All calculations use company timezone
- Metrics definitions follow industry standards
- Performance optimized for up to 10,000 employees
- Charts use Chart.js or similar library
- Export limited to prevent server overload
