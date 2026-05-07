# SPEC — Attendance for Fair Payroll Phase 1

> This file is the single source of truth.
> Codex must NOT deviate from any contract defined here.
> If anything is unclear, STOP and ask before implementing.

## Principles

- Explainability first: every payroll number must be explainable from attendance and leave facts.
- Fairness by configuration, not by hardcode: thresholds, quotas, and schedule rules must be configurable.
- Separation of concerns: attendance stores daily facts, payroll consumes those facts and computes impact.

## Scope

- This phase covers attendance policy, leave entitlement validation, working-days calculation, hybrid schedule resolution, payroll deduction readiness, and post-lock payroll adjustments.
- This phase does not include geo-fencing, biometric devices, overtime, shift rotation, or regional holiday engines.
- Hybrid mismatch is a compliance/review signal only in this phase. It does not auto-deduct salary.

## Existing Tables to Extend

### `attendances`

Keep the existing table name `attendances`.

Add fields:

- `worked_minutes` nullable unsigned integer
- `actual_work_mode` nullable string
- `policy_mismatch_flag` boolean default `false`
- `attendance_period_id` nullable foreign key to `attendance_periods`

Keep existing `check_in` and `check_out` columns. Do not rename them.

### `leave_requests`

Add fields:

- `proof_file_path` nullable string
- `proof_file_name` nullable string
- `proof_mime_type` nullable string
- `proof_size_kb` nullable unsigned integer
- `proof_uploaded_at` nullable timestamp
- `proof_review_status` nullable string
- `proof_reviewed_by` nullable foreign key to `employee_profiles`
- `proof_reviewed_at` nullable timestamp
- `proof_review_notes` nullable text

Phase 1 supports a single proof attachment per leave request.

### `payrolls`

Add fields:

- `attendance_period_id` nullable foreign key to `attendance_periods`

Keep existing unique `salary_month`.

### `payroll_details`

Keep existing columns for backward compatibility.

Add fields:

- `effective_working_days` unsigned integer default `0`
- `daily_rate` decimal(12, 2) default `0`
- `present_days` unsigned integer default `0`
- `late_days` unsigned integer default `0`
- `half_day_count` unsigned integer default `0`
- `paid_leave_days` unsigned integer default `0`
- `unpaid_leave_days` unsigned integer default `0`
- `holiday_days` unsigned integer default `0`
- `deduction_days` decimal(8, 2) default `0`
- `deduction_amount` decimal(12, 2) default `0`
- `policy_mismatch_days` unsigned integer default `0`
- `warning_flags` nullable json

Do not drop legacy fields in this phase.

## attendance_policies

Create table `attendance_policies` with one row per `employment_type`.

Fields:

- `employment_type` unique string
- `work_start_time` time
- `work_end_time` time
- `work_days_per_week` unsigned tiny integer
- `default_working_weekdays` json
- `late_grace_minutes` unsigned integer
- `half_day_min_hours` decimal(4, 2)
- `warning_absent_pct` decimal(5, 2)
- timestamps

Default seeded rows:

| employment_type | work_start_time | work_end_time | work_days_per_week | default_working_weekdays | late_grace_minutes | half_day_min_hours | warning_absent_pct |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `full_time` | `09:00:00` | `17:00:00` | `5` | `["monday","tuesday","wednesday","thursday","friday"]` | `30` | `4.00` | `15.00` |
| `contract` | `09:00:00` | `17:00:00` | `5` | `["monday","tuesday","wednesday","thursday","friday"]` | `30` | `4.00` | `15.00` |
| `intern` | `09:00:00` | `17:00:00` | `5` | `["monday","tuesday","wednesday","thursday","friday"]` | `30` | `3.00` | `20.00` |
| `part_time` | `09:00:00` | `13:00:00` | `3` | `["monday","wednesday","friday"]` | `20` | `2.00` | `20.00` |

## holiday_calendars

Create table `holiday_calendars`.

Fields:

- `date` date
- `name` string
- `type` string with allowed values `national` or `company`
- `applies_to` nullable json
- timestamps

Contract:

- `applies_to = null` means the holiday applies to all employment types.
- `applies_to = ["full_time","contract"]` means it applies only to those employment types.
- Holiday affects `effective_working_days` only when the holiday date falls on a scheduled working weekday for that employee's attendance policy.

## hybrid_work_schedules

Create table `hybrid_work_schedules`.

Fields:

- `employee_id` foreign key to `employee_profiles`
- `effective_from` date
- `effective_until` nullable date
- `monday` string
- `tuesday` string
- `wednesday` string
- `thursday` string
- `friday` string
- timestamps

Allowed weekday values:

- `office`
- `remote`
- `off`

Contract:

- This table applies only to employees whose `job_information.work_location = hybrid`.
- Default business expectation for hybrid is `3 office + 2 remote`, but the actual stored schedule is the source of truth.

## hybrid_schedule_overrides

Create table `hybrid_schedule_overrides`.

Fields:

- `employee_id` foreign key to `employee_profiles`
- `date` date
- `planned_work_mode` string
- `reason` nullable text
- `status` string
- `requested_by` foreign key to `employee_profiles`
- `approved_by` nullable foreign key to `employee_profiles`
- `approved_at` nullable timestamp
- `review_notes` nullable text
- timestamps

Allowed `planned_work_mode` values:

- `office`
- `remote`
- `off`

Allowed `status` values:

- `pending`
- `approved`
- `rejected`

Contract:

- Approved override takes precedence over the base weekly schedule.
- Use this table for hybrid day swaps or one-off exceptions.

## attendance_policy_mismatches

Create table `attendance_policy_mismatches`.

Fields:

- `attendance_id` foreign key to `attendances`
- `employee_id` foreign key to `employee_profiles`
- `mismatch_date` date
- `planned_work_mode` nullable string
- `actual_work_mode` nullable string
- `status` string
- `acknowledged_by` nullable foreign key to `employee_profiles`
- `acknowledged_at` nullable timestamp
- `escalated_at` nullable timestamp
- `resolved_by` nullable foreign key to `employee_profiles`
- `resolved_at` nullable timestamp
- `resolution_notes` nullable text
- timestamps

Allowed `status` values:

- `pending_review`
- `acknowledged`
- `escalated_hr`
- `resolved`

Lifecycle contract:

- Created only for hybrid employees when `actual_work_mode != planned_work_mode`.
- Manager may acknowledge a mismatch with notes.
- Unacknowledged mismatches escalate to HR after `3` working days.
- HR resolves the mismatch.

## leave_entitlements

Create table `leave_entitlements`.

Fields:

- `employment_type` string
- `leave_type` string
- `is_eligible` boolean
- `is_paid` boolean
- `quota_scope` nullable string
- `quota_days` nullable decimal(8, 2)
- `carry_over_max_days` nullable unsigned integer
- `requires_attachment` boolean default `false`
- `requires_reason` boolean default `false`
- `allowed_mime_types` nullable json
- `max_attachment_size_kb` nullable unsigned integer
- timestamps

Allowed `quota_scope` values:

- `annual`
- `per_occurrence`
- `unlimited`
- `unpaid`

Seed contract:

| employment_type | leave_type | is_eligible | is_paid | quota_scope | quota_days | carry_over_max_days | requires_attachment | requires_reason |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `full_time` | `annual_leave` | `true` | `true` | `annual` | `12.00` | `5` | `false` | `true` |
| `full_time` | `sick_leave` | `true` | `true` | `unlimited` | `null` | `null` | `true` | `true` |
| `full_time` | `personal_leave` | `true` | `false` | `unpaid` | `null` | `null` | `false` | `true` |
| `full_time` | `maternity_leave` | `true` | `true` | `annual` | `90.00` | `null` | `false` | `true` |
| `full_time` | `paternity_leave` | `true` | `true` | `annual` | `2.00` | `null` | `false` | `true` |
| `full_time` | `compassionate_leave` | `true` | `true` | `per_occurrence` | `3.00` | `null` | `false` | `true` |
| `full_time` | `emergency_leave` | `true` | `true` | `annual` | `2.00` | `null` | `false` | `true` |
| `contract` | `annual_leave` | `true` | `true` | `annual` | `6.00` | `5` | `false` | `true` |
| `contract` | `sick_leave` | `true` | `true` | `unlimited` | `null` | `null` | `true` | `true` |
| `contract` | `personal_leave` | `true` | `false` | `unpaid` | `null` | `null` | `false` | `true` |
| `contract` | `maternity_leave` | `true` | `true` | `annual` | `90.00` | `null` | `false` | `true` |
| `contract` | `paternity_leave` | `true` | `true` | `annual` | `2.00` | `null` | `false` | `true` |
| `contract` | `compassionate_leave` | `true` | `true` | `per_occurrence` | `3.00` | `null` | `false` | `true` |
| `contract` | `emergency_leave` | `true` | `true` | `annual` | `2.00` | `null` | `false` | `true` |
| `intern` | `annual_leave` | `true` | `true` | `annual` | `6.00` | `5` | `false` | `true` |
| `intern` | `sick_leave` | `true` | `true` | `unlimited` | `null` | `null` | `true` | `true` |
| `intern` | `personal_leave` | `true` | `false` | `unpaid` | `null` | `null` | `false` | `true` |
| `intern` | `maternity_leave` | `false` | `false` | `annual` | `0.00` | `null` | `false` | `true` |
| `intern` | `paternity_leave` | `false` | `false` | `annual` | `0.00` | `null` | `false` | `true` |
| `intern` | `compassionate_leave` | `true` | `true` | `per_occurrence` | `2.00` | `null` | `false` | `true` |
| `intern` | `emergency_leave` | `true` | `true` | `annual` | `1.00` | `null` | `false` | `true` |
| `part_time` | `annual_leave` | `true` | `true` | `annual` | `7.00` | `5` | `false` | `true` |
| `part_time` | `sick_leave` | `true` | `true` | `unlimited` | `null` | `null` | `true` | `true` |
| `part_time` | `personal_leave` | `true` | `false` | `unpaid` | `null` | `null` | `false` | `true` |
| `part_time` | `maternity_leave` | `true` | `true` | `annual` | `90.00` | `null` | `false` | `true` |
| `part_time` | `paternity_leave` | `true` | `true` | `annual` | `2.00` | `null` | `false` | `true` |
| `part_time` | `compassionate_leave` | `true` | `true` | `per_occurrence` | `2.00` | `null` | `false` | `true` |
| `part_time` | `emergency_leave` | `true` | `true` | `annual` | `1.00` | `null` | `false` | `true` |

Attachment contract for sick leave:

- allowed mime types: `["application/pdf","image/jpeg","image/png"]`
- max attachment size: `5120` KB

Carry-over contract:

- Annual leave carry-over max is `5` days.
- Any remaining balance above `5` expires on `31 December`.
- HR may override employee-level balance outside this phase. This phase stores only the default entitlement rule.

## attendance_periods

Create table `attendance_periods`.

Fields:

- `start_date` date
- `end_date` date
- `cutoff_date` date
- `status` string
- `locked_at` nullable timestamp
- timestamps

Allowed `status` values:

- `open`
- `review`
- `locked`

Contract:

- `open`: employee may submit correction and leave requests affecting the period.
- `review`: employee corrections are closed; HR may still resolve pending approvals or proof review.
- `locked`: payroll draft for the period already exists.

## payroll_adjustments

Create table `payroll_adjustments`.

Fields:

- `employee_id` foreign key to `employee_profiles`
- `source_period_id` foreign key to `attendance_periods`
- `target_period_id` foreign key to `attendance_periods`
- `source_reference_type` string
- `source_reference_id` nullable unsigned big integer
- `adjustment_kind` string
- `days_delta` decimal(8, 2) default `0`
- `amount_delta` decimal(12, 2) default `0`
- `reason` nullable text
- `status` string
- timestamps

Allowed `adjustment_kind` values:

- `paid_leave_reversal`
- `paid_leave_credit`
- `absence_correction_credit`
- `absence_correction_deduction`

Allowed `status` values:

- `pending`
- `approved`
- `applied`

Contract:

- Post-lock correction must not mutate old payroll.
- Approved post-lock correction creates a payroll adjustment for the next target period.
- Adjustment amount must be presented as a separate section in payroll detail.

## Effective Working Days

`effective_working_days` must be calculated per employee and per period.

Rules:

1. Resolve employee `employment_type`.
2. Read `attendance_policies.default_working_weekdays`.
3. Count only dates in the period that fall on scheduled weekdays for that employee.
4. Subtract holidays only when:
   - holiday date is inside the period
   - holiday applies to that employee employment type
   - holiday date falls on one of the scheduled weekdays for that employee

Part-time fairness rule:

- Do not calculate part-time working days by applying a rough monthly ratio.
- Use the exact weekday schedule from `default_working_weekdays`.
- Example: `["monday","wednesday","friday"]` means only those weekdays count for part-time.

## hybrid_work_schedules Resolution

Planned mode resolution order:

1. If employee is not `hybrid`, planned mode is `null`.
2. If an approved `hybrid_schedule_override` exists for the date, use it.
3. Otherwise use the active base weekly schedule row covering the date.
4. If there is no active schedule row, planned mode is `null`.

## Classification Rules

Daily classification must be applied in this exact order:

1. If the date is a holiday for the employee, status is `holiday`.
2. Else if there is an approved leave request covering the date and the entitlement is valid, status is the leave type.
3. Else if there is an attendance record:
   - check-in `<= work_start_time` => `present`
   - check-in `> work_start_time` and `<= work_start_time + late_grace_minutes` => `late`
   - check-in `> work_start_time + late_grace_minutes` and `worked_minutes >= half_day_min_hours * 60` => `half_day`
   - check-in `> work_start_time + late_grace_minutes` and `worked_minutes < half_day_min_hours * 60` => `absent`
4. Else fallback status is `absent`

Additional rules:

- `worked_minutes = null` must be treated as `absent`.
- `actual_work_mode` is required only for hybrid employees.
- If `actual_work_mode != planned_work_mode`, attendance remains valid but `policy_mismatch_flag` becomes `true` and a mismatch record is created.

## Leave Entitlement Validator

A leave request is payroll-valid only if all rules below pass:

1. Employee employment type is eligible for the requested leave type.
2. Quota remains available when `quota_scope` is `annual` or `per_occurrence`.
3. `sick_leave` requires proof attachment metadata and HR proof approval.
4. `emergency_leave` requires a non-empty reason.
5. Leave-day consumption counts only scheduled working days in the requested range after subtracting holidays.

If a leave request fails validation for payroll purposes:

- the leave request may still exist in the system
- but the Attendance Classifier must not treat it as valid paid leave
- the classifier falls through to attendance or absent rules

## Payroll Calculation

Payroll calculation is per employee and per attendance period.

Formula:

- `daily_rate = monthly_salary / effective_working_days`
- fully paid days:
  - `present`
  - `late`
  - valid paid leave types
- partially paid days:
  - `half_day` counts as `0.5` deduction day
- unpaid days:
  - `absent`
  - `personal_leave`

Deduction:

- `deduction_days = absent_days + unpaid_leave_days + (half_day_count * 0.5)`
- `deduction_amount = daily_rate * deduction_days`
- `final_salary = monthly_salary - deduction_amount`
- round final salary using the existing payroll rounding policy
- final salary must never be negative; floor to `0`

Paid leave types in this phase:

- `annual_leave`
- `sick_leave` only when proof is valid and approved
- `maternity_leave`
- `paternity_leave`
- `compassionate_leave`
- `emergency_leave`

Unpaid leave types in this phase:

- `personal_leave`

## Payroll Detail Breakdown

Each payroll detail must expose all of these fields:

- `effective_working_days`
- `daily_rate`
- `present_days`
- `late_days`
- `half_day_count`
- `paid_leave_days`
- `unpaid_leave_days`
- `holiday_days`
- `absent_days`
- `deduction_days`
- `deduction_amount`
- `policy_mismatch_days`
- `warning_flags`
- `adjustments[]`

`warning_flags` may include:

- `absent_pct_threshold_reached`
- `unresolved_policy_mismatch`
- `high_late_trend`
- `high_half_day_trend`

## attendance_periods Lifecycle

Lifecycle order:

1. New monthly period is created as `open`
2. Reaches cutoff and pending review stage => `review`
3. Payroll draft for the period is created => `locked`

Correction contract:

- Employee may submit correction only while the period is `open`.
- In `review`, employee correction is rejected; HR may still resolve pending review items.
- In `locked`, correction must not modify that period payroll.
- Post-lock approved correction becomes a `payroll_adjustment` on the next target period.

Sick proof timing:

- Expected proof upload window is within `7` working days from the sick date.
- Proof submitted after that may still be reviewed by HR.
- Payroll validity still depends on final HR proof approval.

## attendance_policy_mismatches Lifecycle

Lifecycle:

1. `pending_review`
2. `acknowledged`
3. `escalated_hr`
4. `resolved`

Operational contract:

- Manager acknowledges mismatch with notes.
- If a mismatch stays `pending_review` for `3` working days, it auto-escalates to HR.
- Resolved mismatch remains an audit record and does not disappear.

## Readiness Workspace

Readiness status per employee per period:

- `ready`
- `warning`
- `blocked`

Blocked reasons:

- scheduled working day has no attendance and no payroll-valid approved leave
- sick leave proof is pending or invalid
- leave approval affecting the period is still pending
- leave entitlement is invalid for paid-leave treatment

Warning flags:

- unresolved policy mismatch
- absent percentage exceeds policy threshold
- high late trend
- high half-day trend

Organization summary must include:

- total employees
- ready employees
- warning employees
- blocked employees

Payroll generation may proceed only when blocked employees count is `0`.

## Test Plan - E2E

Minimum E2E scenarios for this phase:

1. Part-time payroll uses exact scheduled weekdays and differs fairly from full-time in the same month.
2. Approved hybrid swap resolves planned mode and prevents a false mismatch.
3. Employee absent in locked period, later approved correction creates next-period adjustment and does not mutate old payroll.
4. HR cannot generate payroll while at least one employee is blocked in readiness.
5. Finance can inspect payroll detail with explicit `daily_rate` and `effective_working_days`.
