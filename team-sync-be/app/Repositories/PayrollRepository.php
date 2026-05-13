<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\Enums\PayrollStatus;
use App\Exceptions\PayrollAlreadyPaidException;
use App\Exceptions\PayrollReconciliationBlockedException;
use App\Exceptions\PayrollStateException;
use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\AttendancePolicyMismatch;
use App\Models\BpjsRate;
use App\Models\HolidayCalendar;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\OvertimeRecord;
use App\Models\Payroll;
use App\Models\PayrollActivityLog;
use App\Models\PayrollAdjustment;
use App\Models\PayrollApproval;
use App\Models\PayrollApprovalPolicy;
use App\Models\PayrollDetail;
use App\Models\PayrollNotificationDelivery;
use App\Models\PayrollReconciliationResolution;
use App\Models\PayrollSetting;
use App\Models\PayrollSettingVersion;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Notifications\PayrollCorrected;
use App\Services\Attendance\AttendanceClassifier;
use App\Services\Attendance\AttendancePeriodService;
use App\Services\EmailService;
use App\Services\Payroll\OvertimeCalculationService;
use App\Services\Payroll\TaxCalculationService;
use App\Services\PayrollActivityLogger;
use App\Support\AttendanceHelper;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class PayrollRepository implements PayrollRepositoryInterface
{
    private const UNRESOLVED_MISMATCH_STATUSES = AttendancePolicyMismatch::UNRESOLVED_STATUSES;

    private const DEDUCTION_WARNING_RATIO = 0.5;

    private const HIGH_LATE_TREND_RATIO = 0.2;

    private const HIGH_HALF_DAY_TREND_RATIO = 0.1;

    private const MAX_CORRECTION_COUNT = 3;

    protected EmailService $emailService;

    protected PayrollActivityLogger $activityLogger;

    protected AttendanceClassifier $attendanceClassifier;

    protected AttendancePeriodService $attendancePeriodService;

    protected TaxCalculationService $taxCalculationService;

    protected OvertimeCalculationService $overtimeCalculationService;

    public function __construct(
        EmailService $emailService,
        PayrollActivityLogger $activityLogger,
        AttendanceClassifier $attendanceClassifier,
        AttendancePeriodService $attendancePeriodService,
        TaxCalculationService $taxCalculationService,
        OvertimeCalculationService $overtimeCalculationService
    ) {
        $this->emailService = $emailService;
        $this->activityLogger = $activityLogger;
        $this->attendanceClassifier = $attendanceClassifier;
        $this->attendancePeriodService = $attendancePeriodService;
        $this->taxCalculationService = $taxCalculationService;
        $this->overtimeCalculationService = $overtimeCalculationService;
    }

    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    ): Builder|QueryBuilder|Collection {
        $query = Payroll::with(['payrollDetails', 'payrollSettingVersion'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('payrollDetails.staffMember', function ($q) use ($search) {
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%'.$search.'%');
                    })
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('salary_month', 'desc');

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            null,
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(string $id): Payroll
    {
        return Payroll::withCount('payrollDetails')
            ->with(['payrollSettingVersion.updatedBy'])
            ->findOrFail($id);
    }

    public function findById(string $id): Payroll
    {
        return Payroll::findOrFail($id);
    }

    public function findByIdWithDetails(string $id): Payroll
    {
        $payroll = Payroll::query()
            ->with([
                'payrollDetails.staffMember.user',
                'payrollDetails.staffMember.jobInformation.team',
                'payrollSettingVersion',
            ])
            ->findOrFail($id);

        // Manually load period-filtered applied adjustments to avoid pulling
        // adjustments from unrelated payroll periods for the same employee.
        $this->loadAppliedAdjustmentsForDetails($payroll->payrollDetails, $payroll);

        return $payroll;
    }

    public function getPayrollDetailsPaginated(string $payrollId, int $perPage = 50): LengthAwarePaginator
    {
        // Verify payroll exists
        $payroll = Payroll::findOrFail($payrollId);

        // Get paginated details with optimized eager loading
        $paginated = PayrollDetail::with([
            'staffMember.user',
            'staffMember.jobInformation.team',
            'staffMember.bankInformation',
            'payroll',
        ])
            ->where('payroll_id', $payrollId)
            ->orderBy('final_salary', 'desc') // Highest salary first
            ->paginate($perPage);

        $this->loadAppliedAdjustmentsForDetails($paginated->getCollection(), $payroll);

        return $paginated;
    }

    /**
     * Load period-filtered applied adjustments onto a collection of PayrollDetail models.
     *
     * This avoids the bug where the base relationship loads ALL adjustments for an
     * employee regardless of the payroll period, causing adjustments to appear on
     * the wrong payslip when an employee has multiple payroll details across months.
     */
    private function loadAppliedAdjustmentsForDetails($details, Payroll $payroll): void
    {
        $employeeIds = $details
            ->pluck('staff_member_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $appliedAdjustmentsByEmployee = collect();

        if (! empty($employeeIds) && $payroll->attendance_period_id) {
            $appliedAdjustmentsByEmployee = PayrollAdjustment::query()
                ->where('target_period_id', $payroll->attendance_period_id)
                ->whereIn('staff_member_id', $employeeIds)
                ->where('status', PayrollAdjustment::STATUS_APPLIED)
                ->orderBy('id')
                ->get()
                ->groupBy('staff_member_id');
        }

        $details->each(function (PayrollDetail $detail) use ($appliedAdjustmentsByEmployee) {
            $detail->setRelation(
                'appliedAdjustments',
                $appliedAdjustmentsByEmployee->get((int) $detail->staff_member_id, collect())
            );
        });
    }

    /**
     * Batch size for processing employees during payroll generation.
     * Smaller than PAYROLL_BULK_INSERT_CHUNK_SIZE to limit memory during calculation.
     */
    private const PAYROLL_PROCESSING_BATCH_SIZE = 100;

    public function generatePayroll(string $salaryMonth, ?int $actorId = null): Payroll
    {
        return DB::transaction(function () use ($salaryMonth, $actorId) {
            $month = Carbon::parse($salaryMonth)->startOfMonth();
            $readiness = $this->buildGenerateReadiness($month);

            if (! $readiness['can_generate']) {
                throw new \Exception($readiness['message']);
            }

            $settings = PayrollSetting::current();
            $settingsVersion = $settings->resolveActiveVersion($actorId);
            $attendancePeriod = $this->attendancePeriodService->ensurePeriodForMonth(
                $month,
                (int) $settingsVersion->attendance_cutoff_day
            );

            try {
                $payroll = Payroll::create([
                    'salary_month' => $month->format('Y-m-d'),
                    'attendance_period_id' => $attendancePeriod->id,
                    'payroll_setting_version_id' => $settingsVersion->id,
                    'status' => PayrollStatus::PROCESSING,
                    'processed_count' => 0,
                ]);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                    throw new \Exception('Payroll for this month is already being generated by another process.');
                }
                throw $e;
            }

            $staffMemberIds = $readiness['meta']['staff_member_ids_with_attendance'] ?? [];
            $startOfMonth = $month->copy()->startOfMonth();
            $endOfMonth = $month->copy()->endOfMonth();
            $workingDays = $this->resolveWorkingDays($settingsVersion, $startOfMonth, $endOfMonth);
            $deductionRate = (float) $settingsVersion->absent_deduction_rate;

            $totalProcessed = 0;

            // Process employees in batches to limit memory usage
            $staffMemberIdChunks = array_chunk($staffMemberIds, self::PAYROLL_PROCESSING_BATCH_SIZE);

            foreach ($staffMemberIdChunks as $batchIds) {
                $batchEmployees = StaffMemberProfile::with(['jobInformation', 'user'])
                    ->whereIn('id', $batchIds)
                    ->whereHas('jobInformation', function ($query) {
                        $query->where('status', 'active');
                    })
                    ->get();

                $payrollDetails = [];

                foreach ($batchEmployees as $employee) {
                    $payrollDetails[] = $this->buildPayrollDetailRow(
                        $employee,
                        $payroll,
                        $settingsVersion,
                        $startOfMonth,
                        $endOfMonth,
                        $workingDays,
                        $deductionRate
                    );
                }

                // Bulk insert this batch
                if (! empty($payrollDetails)) {
                    foreach (array_chunk($payrollDetails, CacheConstants::PAYROLL_BULK_INSERT_CHUNK_SIZE) as $chunk) {
                        DB::table('payroll_details')->insert($chunk);
                    }
                    $totalProcessed += count($payrollDetails);

                    // Update progress on the payroll record
                    $payroll->updateQuietly(['processed_count' => $totalProcessed]);
                }

                // Free memory from this batch
                unset($batchEmployees, $payrollDetails);
            }

            unset($staffMemberIdChunks);

            $appliedAdjustmentsCount = $this->applyApprovedAdjustmentsToPayroll(
                $payroll,
                $attendancePeriod
            );

            Attendance::query()
                ->whereDate('date', '>=', $startOfMonth->toDateString())
                ->whereDate('date', '<=', $endOfMonth->toDateString())
                ->update([
                    'attendance_period_id' => $attendancePeriod->id,
                ]);

            $payroll->update(['status' => PayrollStatus::PENDING]);
            $this->attendancePeriodService->lockPeriod($attendancePeriod);

            $this->activityLogger->log(
                $payroll->id,
                'generated',
                'Payroll draft generated',
                'Payroll draft was generated from validated attendance data.',
                $actorId,
                [
                    'salary_month' => $month->format('Y-m'),
                    'employee_count' => $totalProcessed,
                    'applied_adjustments_count' => $appliedAdjustmentsCount,
                    'settings_version_id' => (int) $settingsVersion->id,
                    'settings_version_number' => (int) $settingsVersion->version_number,
                    'settings_snapshot' => [
                        'payday_day' => (int) $settingsVersion->payday_day,
                        'attendance_cutoff_day' => (int) $settingsVersion->attendance_cutoff_day,
                        'working_days_mode' => (string) $settingsVersion->working_days_mode,
                        'default_working_days' => (int) $settingsVersion->default_working_days,
                        'absent_deduction_rate' => (float) $settingsVersion->absent_deduction_rate,
                        'rounding_mode' => (string) $settingsVersion->rounding_mode,
                        'rounding_unit' => (int) $settingsVersion->rounding_unit,
                    ],
                ]
            );

            DB::afterCommit(function () use ($payroll, $actorId) {
                $actorName = $actorId ? User::find($actorId)?->name : null;
                $this->emailService->sendPayrollDraftCreatedNotification($payroll, $actorName);
            });

            return $payroll->load([
                'payrollSettingVersion.updatedBy',
                'payrollDetails.staffMember.user',
                'payrollDetails.staffMember.jobInformation.team',
                'payrollDetails.staffMember.bankInformation',
            ]);
        });
    }

    /**
     * Build a single payroll detail row for an employee.
     * Extracted to reduce memory footprint during batch processing.
     */
    private function buildPayrollDetailRow(
        StaffMemberProfile $employee,
        Payroll $payroll,
        PayrollSettingVersion $settingsVersion,
        Carbon $startOfMonth,
        Carbon $endOfMonth,
        int $workingDays,
        float $deductionRate
    ): array {
        $jobInfo = $employee->jobInformation;
        $originalSalary = $jobInfo->monthly_salary ?? 0;

        $fairnessSummary = $this->attendanceClassifier->summarizePeriod(
            $employee->id,
            $startOfMonth,
            $endOfMonth
        );

        $effectiveWorkingDays = (int) ($fairnessSummary['effective_working_days'] ?? 0);
        $dailySalary = (float) ($fairnessSummary['daily_rate'] ?? 0);
        $deductionDays = (float) ($fairnessSummary['deduction_days'] ?? 0);
        $legacyDailySalary = $workingDays > 0 ? ((float) $originalSalary / $workingDays) : 0;
        $deduction = $deductionDays * $legacyDailySalary * $deductionRate;

        $attendedDays = (int) ($fairnessSummary['attended_days'] ?? 0);
        $lateDays = (int) ($fairnessSummary['late_days'] ?? 0);
        $sickDays = (int) ($fairnessSummary['sick_days'] ?? 0);
        $absentDays = (int) ($fairnessSummary['absent_days'] ?? 0);
        $warningFlags = $fairnessSummary['warning_flags'] ?? [];

        // ── Tax & BPJS Calculation ────────────────────────────
        $ptkpStatus = $employee->ptkp_status ?? null;
        $hasNpwp = ! empty($employee->npwp);
        $grossForTax = (float) $originalSalary;

        // Use TER method for Jan–Nov, annualized progressive for December true-up
        $isDecember = $startOfMonth->month === 12;
        $taxResult = $isDecember
            ? $this->taxCalculationService->calculateAnnualizedPph21($grossForTax, $ptkpStatus, $hasNpwp)
            : $this->taxCalculationService->calculateMonthlyTer($grossForTax, $ptkpStatus, $hasNpwp);

        $bpjsResult = $this->taxCalculationService->calculateBpjs($grossForTax);

        $pph21Amount = $taxResult['pph21_monthly'];

        // BPJS Ketenagakerjaan = JHT + JKK + JKM + JP
        $bpjsTkEmployee = ($bpjsResult['breakdown']['jht_employee'] ?? 0)
            + ($bpjsResult['breakdown']['jp_employee'] ?? 0);
        $bpjsTkEmployer = ($bpjsResult['breakdown']['jht_employer'] ?? 0)
            + ($bpjsResult['breakdown']['jkk_employer'] ?? 0)
            + ($bpjsResult['breakdown']['jkm_employer'] ?? 0)
            + ($bpjsResult['breakdown']['jp_employer'] ?? 0);

        $bpjsKesEmployee = $bpjsResult['breakdown']['bpjs_kesehatan_employee'] ?? 0;
        $bpjsKesEmployer = $bpjsResult['breakdown']['bpjs_kesehatan_employer'] ?? 0;

        // ── Overtime Calculation ──────────────────────────────────────
        $approvedOvertimeRecords = OvertimeRecord::query()
            ->approved()
            ->forStaffMember($employee->id)
            ->forPeriod($startOfMonth, $endOfMonth)
            ->get();

        $overtimeResult = $this->overtimeCalculationService->calculateOvertimePay(
            (float) $originalSalary,
            $approvedOvertimeRecords
        );

        $overtimeAmount = $overtimeResult['total_amount'];
        $overtimeHours = $overtimeResult['total_hours'];
        $overtimeRecordsCount = $approvedOvertimeRecords->count();

        // Add overtime to final salary
        $finalSalary = $this->applyRounding(
            max(0, $originalSalary - $deduction) + $overtimeAmount,
            $settingsVersion->rounding_mode,
            (int) $settingsVersion->rounding_unit
        );

        return [
            'payroll_id' => $payroll->id,
            'staff_member_id' => $employee->id,
            'original_salary' => $originalSalary,
            'final_salary' => $finalSalary,
            'effective_working_days' => $effectiveWorkingDays,
            'daily_rate' => round($dailySalary, 2),
            'attended_days' => $attendedDays,
            'present_days' => (int) ($fairnessSummary['present_days'] ?? 0),
            'late_days' => $lateDays,
            'half_day_count' => (int) ($fairnessSummary['half_day_count'] ?? 0),
            'paid_leave_days' => (int) ($fairnessSummary['paid_leave_days'] ?? 0),
            'unpaid_leave_days' => (int) ($fairnessSummary['unpaid_leave_days'] ?? 0),
            'holiday_days' => (int) ($fairnessSummary['holiday_days'] ?? 0),
            'sick_days' => $sickDays,
            'absent_days' => $absentDays,
            'deduction_days' => round($deductionDays, 2),
            'deduction_amount' => round($deduction, 2),
            'overtime_hours' => round($overtimeHours, 2),
            'overtime_amount' => round($overtimeAmount, 2),
            'overtime_records_count' => $overtimeRecordsCount,
            'pph21_amount' => round($pph21Amount, 2),
            'bpjs_tk_employee' => round($bpjsTkEmployee, 2),
            'bpjs_tk_employer' => round($bpjsTkEmployer, 2),
            'bpjs_kes_employee' => round($bpjsKesEmployee, 2),
            'bpjs_kes_employer' => round($bpjsKesEmployer, 2),
            'tax_calculation_meta' => json_encode($taxResult, JSON_THROW_ON_ERROR),
            'policy_mismatch_days' => (int) ($fairnessSummary['policy_mismatch_days'] ?? 0),
            'warning_flags' => empty($warningFlags) ? null : json_encode(array_values($warningFlags), JSON_THROW_ON_ERROR),
            'notes' => $this->buildPayrollNote($settingsVersion, [
                'working_days' => $workingDays,
                'attended_days' => $attendedDays,
                'late_days' => $lateDays,
                'sick_days' => $sickDays,
                'permission_days' => 0,
                'absent_days' => $absentDays,
                'deduction' => $deduction,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function getGenerateReadiness(string $salaryMonth): array
    {
        $month = Carbon::createFromFormat('Y-m', $salaryMonth)->startOfMonth();

        return $this->buildGenerateReadiness($month);
    }

    public function getReadinessDashboard(string $salaryMonth): array
    {
        $month = Carbon::createFromFormat('Y-m', $salaryMonth)->startOfMonth();
        $readiness = $this->buildGenerateReadiness($month);

        $activeEmployeeIds = StaffMemberProfile::query()
            ->whereHas('jobInformation', function ($query) {
                $query->where('status', 'active');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $employeeRowsPayload = $this->buildEmployeeReadinessRows($month, $activeEmployeeIds);
        $rows = $employeeRowsPayload['rows'];

        usort($rows, function (array $left, array $right) {
            $priority = ['blocked' => 0, 'warning' => 1, 'ready' => 2];

            $leftPriority = $priority[$left['status']] ?? 3;
            $rightPriority = $priority[$right['status']] ?? 3;

            if ($leftPriority === $rightPriority) {
                return strcmp((string) ($left['employee_name'] ?? ''), (string) ($right['employee_name'] ?? ''));
            }

            return $leftPriority <=> $rightPriority;
        });

        $summary = [
            'total_employees' => count($rows),
            'ready_employees' => count(array_filter($rows, fn ($row) => $row['status'] === 'ready')),
            'warning_employees' => count(array_filter($rows, fn ($row) => $row['status'] === 'warning')),
            'blocked_employees' => count(array_filter($rows, fn ($row) => $row['status'] === 'blocked')),
            'employees_with_attendance' => (int) ($readiness['meta']['active_employees_with_attendance_count'] ?? 0),
        ];

        // BPJS rate validation warnings for readiness dashboard
        $bpjsValidation = $this->taxCalculationService->validateBpjsRates();

        return [
            'salary_month' => (string) ($readiness['meta']['salary_month'] ?? $month->format('Y-m')),
            'attendance_period' => [
                'id' => (int) ($readiness['meta']['attendance_period_id'] ?? 0),
                'status' => (string) ($readiness['meta']['attendance_period_status'] ?? ''),
                'cutoff_day' => (int) ($readiness['meta']['cutoff_day'] ?? 0),
                'cutoff_date' => (string) ($readiness['meta']['cutoff_date'] ?? ''),
            ],
            'generation' => [
                'can_generate' => (bool) ($readiness['can_generate'] ?? false),
                'reason_code' => (string) ($readiness['reason_code'] ?? 'unknown'),
                'message' => (string) ($readiness['message'] ?? ''),
            ],
            'bpjs_validation' => [
                'is_valid' => $bpjsValidation['is_valid'],
                'warnings' => $bpjsValidation['warnings'],
            ],
            'summary' => $summary,
            'employees' => $rows,
            'blocked_reasons' => $employeeRowsPayload['blocked_reasons'],
            'warning_flags' => $employeeRowsPayload['warning_flags'],
        ];
    }

    public function getReadinessTeamSummary(string $salaryMonth): array
    {
        $dashboard = $this->getReadinessDashboard($salaryMonth);
        $employees = $dashboard['employees'] ?? [];

        $grouped = [];
        $unassigned = ['total' => 0, 'ready' => 0, 'warning' => 0, 'blocked' => 0, 'covered_days' => 0, 'scheduled_days' => 0];

        foreach ($employees as $employee) {
            $teamName = $employee['team_name'] ?? null;
            $status = $employee['status'] ?? 'ready';
            $coveredDays = (int) ($employee['metrics']['covered_days'] ?? 0);
            $scheduledDays = (int) ($employee['metrics']['scheduled_working_days'] ?? 0);

            if ($teamName === null || $teamName === '') {
                $unassigned['total']++;
                $unassigned[$status] = ($unassigned[$status] ?? 0) + 1;
                $unassigned['covered_days'] += $coveredDays;
                $unassigned['scheduled_days'] += $scheduledDays;
            } else {
                if (! isset($grouped[$teamName])) {
                    $grouped[$teamName] = ['total' => 0, 'ready' => 0, 'warning' => 0, 'blocked' => 0, 'covered_days' => 0, 'scheduled_days' => 0];
                }

                $grouped[$teamName]['total']++;
                $grouped[$teamName][$status] = ($grouped[$teamName][$status] ?? 0) + 1;
                $grouped[$teamName]['covered_days'] += $coveredDays;
                $grouped[$teamName]['scheduled_days'] += $scheduledDays;
            }
        }

        $teams = [];
        foreach ($grouped as $teamName => $data) {
            $teams[] = [
                'team_name' => $teamName,
                'total' => $data['total'],
                'ready' => $data['ready'],
                'warning' => $data['warning'],
                'blocked' => $data['blocked'],
                'coverage_pct' => $data['scheduled_days'] > 0
                    ? round(($data['covered_days'] / $data['scheduled_days']) * 100, 1)
                    : 0,
            ];
        }

        usort($teams, fn (array $a, array $b) => strcmp($a['team_name'], $b['team_name']));

        return [
            'salary_month' => $dashboard['salary_month'],
            'teams' => $teams,
            'unassigned' => [
                'total' => $unassigned['total'],
                'ready' => $unassigned['ready'],
                'warning' => $unassigned['warning'],
                'blocked' => $unassigned['blocked'],
                'coverage_pct' => $unassigned['scheduled_days'] > 0
                    ? round(($unassigned['covered_days'] / $unassigned['scheduled_days']) * 100, 1)
                    : 0,
            ],
        ];
    }

    public function getReconciliation(string $payrollId, array $filters = []): array
    {
        $payroll = Payroll::query()
            ->with([
                'payrollDetails.staffMember.user',
                'payrollDetails.staffMember.bankInformation',
            ])
            ->findOrFail($payrollId);

        return $this->buildPayrollReconciliationPayload($payroll, $filters);
    }

    public function resolveReconciliationException(string $payrollId, array $data, ?int $actorId = null): array
    {
        $payroll = Payroll::findOrFail($payrollId);

        $existing = PayrollReconciliationResolution::where('payroll_id', $payroll->id)
            ->where('staff_member_id', $data['staff_member_id'])
            ->where('exception_type', $data['exception_type'])
            ->exists();

        if ($existing) {
            throw new \Exception('This exception has already been resolved.');
        }

        $resolution = PayrollReconciliationResolution::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $data['staff_member_id'],
            'exception_type' => $data['exception_type'],
            'resolution_action' => $data['resolution_action'],
            'reason' => $data['reason'],
            'resolved_by' => $actorId,
            'metadata' => $data['metadata'] ?? null,
        ]);

        $resolution->load('resolvedByUser');

        $this->activityLogger->log(
            $payroll->id,
            'reconciliation_exception_resolved',
            'Reconciliation exception resolved',
            sprintf(
                'Exception "%s" for staff member #%d was resolved with action "%s".',
                $data['exception_type'],
                $data['staff_member_id'],
                $data['resolution_action']
            ),
            $actorId,
            [
                'staff_member_id' => $data['staff_member_id'],
                'exception_type' => $data['exception_type'],
                'resolution_action' => $data['resolution_action'],
            ]
        );

        return [
            'id' => $resolution->id,
            'payroll_id' => $resolution->payroll_id,
            'staff_member_id' => $resolution->staff_member_id,
            'exception_type' => $resolution->exception_type,
            'resolution_action' => $resolution->resolution_action,
            'reason' => $resolution->reason,
            'resolved_by_name' => $resolution->resolvedByUser?->name,
            'resolved_at' => $resolution->created_at?->toIso8601String(),
        ];
    }

    public function getReconciliationResolutions(string $payrollId): array
    {
        $payroll = Payroll::findOrFail($payrollId);

        $resolutions = PayrollReconciliationResolution::query()
            ->where('payroll_id', $payroll->id)
            ->with(['resolvedByUser', 'staffMember.user'])
            ->orderByDesc('created_at')
            ->get();

        return $resolutions->map(function (PayrollReconciliationResolution $resolution) {
            return [
                'id' => $resolution->id,
                'payroll_id' => $resolution->payroll_id,
                'staff_member_id' => $resolution->staff_member_id,
                'employee_name' => $resolution->staffMember?->user?->name ?? 'Unknown',
                'exception_type' => $resolution->exception_type,
                'resolution_action' => $resolution->resolution_action,
                'reason' => $resolution->reason,
                'resolved_by_name' => $resolution->resolvedByUser?->name ?? 'Unknown',
                'resolved_at' => $resolution->created_at?->toIso8601String(),
            ];
        })->all();
    }

    public function updatePayrollDetail(string $id, array $data, ?int $actorId = null): PayrollDetail
    {
        return DB::transaction(function () use ($id, $data, $actorId) {
            $payrollDetail = PayrollDetail::findOrFail($id);

            if (in_array($payrollDetail->payroll->status, [PayrollStatus::APPROVED, PayrollStatus::PAID], true)) {
                throw new PayrollStateException('Cannot update payroll details for a payroll that has already been approved or paid.');
            }

            $updateData = [];
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }
            if (isset($data['final_salary'])) {
                $updateData['final_salary'] = $data['final_salary'];
            }

            $payrollDetail->update($updateData);

            if (! empty($updateData)) {
                $this->activityLogger->log(
                    $payrollDetail->payroll_id,
                    'detail_updated',
                    'Payroll detail updated',
                    'Payroll detail values were reviewed and updated.',
                    $actorId,
                    [
                        'payroll_detail_id' => $payrollDetail->id,
                        'staff_member_id' => $payrollDetail->staff_member_id,
                        'changed_fields' => array_keys($updateData),
                    ]
                );
            }

            return $payrollDetail->load([
                'staffMember.user',
                'staffMember.jobInformation.team',
                'payroll',
            ]);
        });
    }

    public function approvePayroll(string $payrollId, ?int $actorId = null): Payroll
    {
        // Use NOWAIT to fail immediately if another process holds the lock,
        try {
            return DB::transaction(function () use ($payrollId, $actorId) {
                $payroll = Payroll::query()
                    ->whereKey($payrollId)
                    ->lock('for update nowait')
                    ->firstOrFail();

            if ($payroll->status === PayrollStatus::PAID) {
                throw new PayrollAlreadyPaidException('Payroll has already been paid and cannot be approved.');
            }

            if ($payroll->status === PayrollStatus::APPROVED) {
                throw new PayrollStateException('Payroll has already been approved.');
            }

            if ($payroll->status !== PayrollStatus::PENDING) {
                throw new PayrollStateException(sprintf(
                    'Payroll must be in "pending" status to be approved. Current status: "%s".',
                    $payroll->status->value
                ));
            }

            // Check if multi-step approval policies apply
            $totalAmount = PayrollDetail::where('payroll_id', $payroll->id)->sum('final_salary');
            $applicablePolicies = PayrollApprovalPolicy::getApplicablePolicies((float) $totalAmount);

            if ($applicablePolicies->isNotEmpty()) {
                // Check if multi-step approval is active for this payroll
                $existingApprovals = PayrollApproval::where('payroll_id', $payroll->id)->count();
                $isFirstCall = ($existingApprovals === 0);

                if ($isFirstCall) {
                    // Create approval records for the first time
                    foreach ($applicablePolicies as $policy) {
                        PayrollApproval::create([
                            'payroll_id' => $payroll->id,
                            'policy_id' => $policy->id,
                            'status' => PayrollApproval::STATUS_PENDING,
                        ]);
                    }
                }

                // Multi-step is active — check if all steps are complete
                $pendingCount = PayrollApproval::where('payroll_id', $payroll->id)
                    ->where('status', PayrollApproval::STATUS_PENDING)
                    ->count();

                if ($pendingCount > 0) {
                    // Find if this actor has a pending approval
                    $actorApproval = PayrollApproval::where('payroll_id', $payroll->id)
                        ->where('status', PayrollApproval::STATUS_PENDING)
                        ->whereHas('policy', function ($q) use ($actorId) {
                            $user = User::find($actorId);
                            if ($user) {
                                $q->whereIn('required_role', $user->getRoleNames()->toArray());
                            }
                        })
                        ->first();

                    if ($actorApproval) {
                        $actorApproval->update([
                            'status' => PayrollApproval::STATUS_APPROVED,
                            'approver_id' => $actorId,
                            'approved_at' => now(),
                        ]);

                        // Re-check if all are now complete
                        $stillPending = PayrollApproval::where('payroll_id', $payroll->id)
                            ->where('status', PayrollApproval::STATUS_PENDING)
                            ->count();

                        if ($stillPending > 0) {
                            $this->activityLogger->log(
                                $payroll->id,
                                'approval_initiated',
                                'Multi-step approval initiated',
                                sprintf(
                                    'Payroll requires %d approval step(s). %d still pending.',
                                    $isFirstCall ? $applicablePolicies->count() : $existingApprovals,
                                    $stillPending
                                ),
                                $actorId,
                                [
                                    'total_steps' => $isFirstCall ? $applicablePolicies->count() : $existingApprovals,
                                    'pending_steps' => $stillPending,
                                ]
                            );

                            return $payroll->loadCount('payrollDetails');
                        }
                        // Fall through to approve below
                    } else {
                        if ($isFirstCall) {
                            // First call initiating multi-step — actor doesn't have a matching role
                            // but they triggered the process. Log and return pending.
                            $this->activityLogger->log(
                                $payroll->id,
                                'approval_initiated',
                                'Multi-step approval initiated',
                                sprintf(
                                    'Payroll requires %d approval step(s). %d still pending.',
                                    $applicablePolicies->count(),
                                    $pendingCount
                                ),
                                $actorId,
                                [
                                    'total_steps' => $applicablePolicies->count(),
                                    'pending_steps' => $pendingCount,
                                ]
                            );

                            return $payroll->loadCount('payrollDetails');
                        }

                        throw new PayrollStateException('You do not have the required role to approve this payroll step.');
                    }
                }
                // All approvals complete — fall through to set status = approved
            }

            $payroll->update([
                'status' => PayrollStatus::APPROVED,
            ]);

            $this->activityLogger->log(
                $payroll->id,
                'approved',
                'Payroll approved for payment',
                'Payroll review was completed and the payroll is ready to be marked as paid.',
                $actorId
            );

            DB::afterCommit(function () use ($payroll, $actorId) {
                $actorName = $actorId ? User::find($actorId)?->name : null;
                $this->emailService->sendPayrollApprovedNotification($payroll, $actorName);
            });

            return $payroll->loadCount('payrollDetails');
        });
        } catch (QueryException $e) {
            if ($this->isLockContentionError($e)) {
                throw new \Exception('This payroll is currently being processed by another user. Please try again in a few moments.');
            }
            throw $e;
        }
    }

    public function markAsPaid(string $payrollId, string $paymentDate, ?int $actorId = null): Payroll
    {
        // Use NOWAIT to fail immediately if another process holds the lock,
        try {
            $result = DB::transaction(function () use ($payrollId, $paymentDate, $actorId) {
                $payroll = Payroll::query()
                    ->whereKey($payrollId)
                    ->lock('for update nowait')
                    ->firstOrFail();

            if ($payroll->status === PayrollStatus::PAID) {
                throw new PayrollAlreadyPaidException('Payroll has already been paid and cannot be modified.');
            }

            if ($payroll->status !== PayrollStatus::APPROVED) {
                throw new PayrollStateException(sprintf(
                    'Payroll must be in "approved" status to be marked as paid. Current status: "%s".',
                    $payroll->status->value
                ));
            }

            // Check multi-step approval completion
            $pendingApprovals = PayrollApproval::where('payroll_id', $payroll->id)
                ->where('status', PayrollApproval::STATUS_PENDING)
                ->count();

            if ($pendingApprovals > 0) {
                throw new PayrollStateException(sprintf(
                    'Payroll cannot be marked as paid because %d approval step(s) are still pending.',
                    $pendingApprovals
                ));
            }

            $reconciliation = $this->buildPayrollReconciliationPayload($payroll);
            $criticalCount = (int) ($reconciliation['summary']['unresolved_critical_count'] ?? 0);

            if ($criticalCount > 0) {
                $this->activityLogger->log(
                    $payroll->id,
                    'payment_blocked_reconciliation',
                    'Payroll payment blocked by reconciliation',
                    'Mark as paid was blocked because critical reconciliation issues remain unresolved.',
                    $actorId,
                    [
                        'critical_count' => $criticalCount,
                        'critical_staff_member_ids' => $reconciliation['summary']['critical_staff_member_ids'] ?? [],
                    ]
                );

                return [
                    'blocked' => true,
                    'message' => sprintf(
                        'Payroll cannot be marked as paid because %d critical reconciliation issue(s) remain. Complete employee bank information and regenerate payroll before retrying.',
                        $criticalCount
                    ),
                    'details' => [
                        'critical_count' => $criticalCount,
                        'critical_staff_member_ids' => $reconciliation['summary']['critical_staff_member_ids'] ?? [],
                    ],
                ];
            }

            $payroll->update([
                'status' => PayrollStatus::PAID,
                'payment_date' => Carbon::parse($paymentDate)->format('Y-m-d'),
            ]);

            $this->activityLogger->log(
                $payroll->id,
                'marked_paid',
                'Payroll marked as paid',
                'Payroll was finalized and employee notifications were queued automatically.',
                $actorId,
                [
                    'payment_date' => Carbon::parse($paymentDate)->format('Y-m-d'),
                ]
            );

            $correctionCount = (int) $payroll->correction_count;

            DB::afterCommit(function () use ($payroll, $correctionCount) {
                if ($correctionCount > 0) {
                    $this->sendCorrectionNotifications($payroll, $correctionCount);
                } else {
                    $this->emailService->sendPayrollPaidNotifications(
                        $payroll->id,
                        PayrollNotificationDelivery::TRIGGER_AUTO_PAID
                    );
                }
            });

            return [
                'blocked' => false,
                'payroll' => $payroll->loadCount('payrollDetails'),
            ];
        });

        // Throw after transaction commits so activity log is persisted
        if (($result['blocked'] ?? false) === true) {
            throw new PayrollReconciliationBlockedException(
                (string) ($result['message'] ?? 'Payroll payment was blocked by reconciliation issues.'),
                (array) ($result['details'] ?? [])
            );
        }

        return $result['payroll'];
        } catch (QueryException $e) {
            if ($this->isLockContentionError($e)) {
                throw new \Exception('This payroll is currently being processed by another user. Please try again in a few moments.');
            }
            throw $e;
        }
    }

    public function reopenPayroll(string $payrollId, string $reason, ?int $actorId = null): Payroll
    {
        // Use NOWAIT to fail immediately if another process holds the lock,
        try {
            return DB::transaction(function () use ($payrollId, $reason, $actorId) {
                $payroll = Payroll::query()
                    ->whereKey($payrollId)
                    ->lock('for update nowait')
                    ->firstOrFail();

            if ($payroll->status === PayrollStatus::PENDING) {
                throw new \Exception('Payroll is already in pending status');
            }

            if ($payroll->status === PayrollStatus::PROCESSING) {
                throw new \Exception('Processing payroll cannot be reopened for correction');
            }

            if ($payroll->status === PayrollStatus::PAID) {
                throw new \Exception('Payroll must be unapproved before it can be reopened. Please unapprove the payroll first to move it from paid to approved status.');
            }

            if ($payroll->status !== PayrollStatus::APPROVED) {
                throw new \Exception('Only approved payroll can be reopened for correction');
            }

            // Check correction count limit
            if ($payroll->correction_count >= self::MAX_CORRECTION_COUNT) {
                throw new PayrollStateException(sprintf(
                    'Payroll has reached maximum correction limit (%d). Cannot reopen further.',
                    self::MAX_CORRECTION_COUNT
                ));
            }

            $previousStatus = $payroll->status;
            $previousPaymentDate = optional($payroll->payment_date)?->format('Y-m-d');
            $newCorrectionCount = ((int) $payroll->correction_count) + 1;

            $payroll->update([
                'status' => PayrollStatus::PENDING,
                'payment_date' => null,
                'correction_count' => $newCorrectionCount,
            ]);

            $this->activityLogger->log(
                $payroll->id,
                'reopened_for_correction',
                'Payroll reopened for correction',
                'Payroll was reopened to pending status so corrections can be applied before approval/payment.',
                $actorId,
                [
                    'reason' => trim($reason),
                    'previous_status' => $previousStatus,
                    'previous_payment_date' => $previousPaymentDate,
                    'correction_count' => $newCorrectionCount,
                ]
            );

            return $payroll->loadCount('payrollDetails');
        });
        } catch (QueryException $e) {
            if ($this->isLockContentionError($e)) {
                throw new \Exception('This payroll is currently being processed by another user. Please try again in a few moments.');
            }
            throw $e;
        }
    }

    public function resendNotifications(string $payrollId, ?int $actorId = null): Payroll
    {
        return DB::transaction(function () use ($payrollId, $actorId) {
            $payroll = Payroll::withCount('payrollDetails')->findOrFail($payrollId);

            if ($payroll->status !== PayrollStatus::PAID) {
                throw new \Exception('Notifications can only be resent for paid payrolls');
            }

            $this->activityLogger->log(
                $payroll->id,
                'notifications_resent',
                'Payroll notifications resent',
                'Payroll payment notifications were resent manually to employees.',
                $actorId,
                [
                    'payroll_detail_count' => $payroll->payroll_details_count ?? 0,
                ]
            );

            DB::afterCommit(function () use ($payroll) {
                $this->emailService->sendPayrollPaidNotifications(
                    $payroll->id,
                    PayrollNotificationDelivery::TRIGGER_MANUAL_RESEND
                );
            });

            return $payroll;
        });
    }

    public function getNotificationDeliverySummary(string $payrollId): array
    {
        $payroll = Payroll::query()
            ->withCount('payrollDetails')
            ->findOrFail($payrollId);

        $deliveries = PayrollNotificationDelivery::query()
            ->with(['staffMember.user'])
            ->where('payroll_id', $payrollId)
            ->orderByDesc('id')
            ->get();

        $totalRecipients = (int) ($payroll->payroll_details_count ?? 0);
        $sentCount = $deliveries->where('delivery_status', PayrollNotificationDelivery::STATUS_SENT)->count();
        $failedCount = $deliveries->where('delivery_status', PayrollNotificationDelivery::STATUS_FAILED)->count();
        $skippedCount = $deliveries->where('delivery_status', PayrollNotificationDelivery::STATUS_SKIPPED)->count();
        $deliveryRate = $totalRecipients > 0
            ? round(($sentCount / $totalRecipients) * 100, 1)
            : 0;

        $summary = [
            'total_recipients' => $totalRecipients,
            'total_attempts' => $deliveries->count(),
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'skipped_count' => $skippedCount,
            'delivery_rate' => $deliveryRate,
            'auto_attempt_count' => $deliveries->where('trigger_type', PayrollNotificationDelivery::TRIGGER_AUTO_PAID)->count(),
            'manual_attempt_count' => $deliveries->where('trigger_type', PayrollNotificationDelivery::TRIGGER_MANUAL_RESEND)->count(),
            'last_attempt_at' => optional($deliveries->first()?->created_at)->toIso8601String(),
            'last_sent_at' => optional(
                $deliveries->firstWhere('delivery_status', PayrollNotificationDelivery::STATUS_SENT)?->sent_at
            )->toIso8601String(),
        ];

        $attemptCountByDetail = $deliveries
            ->filter(fn (PayrollNotificationDelivery $delivery) => $delivery->payroll_detail_id !== null)
            ->groupBy('payroll_detail_id')
            ->map(fn (SupportCollection $items) => $items->count());

        $latestByEmployee = $deliveries
            ->filter(fn (PayrollNotificationDelivery $delivery) => $delivery->payroll_detail_id !== null)
            ->unique('payroll_detail_id')
            ->values()
            ->map(function (PayrollNotificationDelivery $delivery) use ($attemptCountByDetail) {
                $attemptCount = (int) ($attemptCountByDetail->get($delivery->payroll_detail_id) ?? 0);
                $payslipPath = $delivery->payroll_detail_id
                    ? '/admin/my-payroll/'.(int) $delivery->payroll_detail_id
                    : null;

                return [
                    'payroll_detail_id' => (int) $delivery->payroll_detail_id,
                    'staff_member_id' => $delivery->staff_member_id ? (int) $delivery->staff_member_id : null,
                    'employee_name' => $delivery->staffMember?->user?->name,
                    'employee_code' => $delivery->staffMember?->code,
                    'recipient_email' => $delivery->recipient_email,
                    'delivery_status' => $delivery->delivery_status,
                    'trigger_type' => $delivery->trigger_type,
                    'failure_reason' => $delivery->failure_reason,
                    'sent_at' => optional($delivery->sent_at)->toIso8601String(),
                    'attempted_at' => optional($delivery->created_at)->toIso8601String(),
                    'attempt_count' => $attemptCount,
                    'payslip_path' => $payslipPath,
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'latest_by_employee' => $latestByEmployee,
        ];
    }

    public function getBpjsRateHistory()
    {
        return BpjsRate::query()
            ->orderByDesc('updated_at')
            ->orderBy('component')
            ->get()
            ->map(function (BpjsRate $rate) {
                return [
                    'id' => $rate->id,
                    'component' => $rate->component,
                    'description' => $rate->description,
                    'employee_rate' => (float) $rate->employee_rate,
                    'employer_rate' => (float) $rate->employer_rate,
                    'max_salary_base' => $rate->max_salary_base !== null
                        ? (float) $rate->max_salary_base
                        : null,
                    'effective_at' => optional($rate->updated_at)->toIso8601String(),
                    'created_at' => optional($rate->created_at)->toIso8601String(),
                    'updated_at' => optional($rate->updated_at)->toIso8601String(),
                ];
            })
            ->values();
    }

    public function getStatistics()
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Current month payroll
        $currentPayroll = Payroll::where('salary_month', $currentMonth->format('Y-m-d'))->first();
        $lastPayroll = Payroll::where('salary_month', $lastMonth->format('Y-m-d'))->first();

        $totalEmployeesCurrentMonth = $currentPayroll
            ? $currentPayroll->payrollDetails()->count()
            : 0;

        $totalSalaryCurrentMonth = $currentPayroll
            ? $currentPayroll->payrollDetails()->sum('final_salary')
            : 0;

        $totalSalaryLastMonth = $lastPayroll
            ? $lastPayroll->payrollDetails()->sum('final_salary')
            : 0;

        $paidPayrolls = Payroll::where('status', PayrollStatus::PAID)
            ->whereYear('salary_month', now()->year)
            ->count();

        $pendingPayrolls = Payroll::where('status', PayrollStatus::PENDING)
            ->count();

        // Calculate average salary
        $averageSalary = $totalEmployeesCurrentMonth > 0
            ? $totalSalaryCurrentMonth / $totalEmployeesCurrentMonth
            : 0;

        // Calculate total deductions (difference between original and final salary)
        $totalDeductions = $currentPayroll
            ? $currentPayroll->payrollDetails()->selectRaw('SUM(original_salary - final_salary) as total_deductions')->value('total_deductions')
            : 0;

        return [
            'total_payroll' => $totalEmployeesCurrentMonth,
            'pending_review' => $pendingPayrolls,
            'finalized' => $paidPayrolls,
            'total_amount' => round($totalSalaryCurrentMonth, 2),
            'average_salary' => round($averageSalary, 2),
            'deductions' => round($totalDeductions ?? 0, 2),
            // Backward compatibility
            'total_employees' => $totalEmployeesCurrentMonth,
            'total_salary_current_month' => round($totalSalaryCurrentMonth, 2),
            'total_salary_last_month' => round($totalSalaryLastMonth, 2),
            'salary_change' => $totalSalaryLastMonth > 0
                ? round((($totalSalaryCurrentMonth - $totalSalaryLastMonth) / $totalSalaryLastMonth) * 100, 1)
                : 0,
            'paid_payrolls' => $paidPayrolls,
            'pending_payrolls' => $pendingPayrolls,
        ];
    }

    public function getComparison(string $month1, string $month2): array
    {
        $cacheKey = CacheConstants::CACHE_KEY_PAYROLL_ANALYTICS.'_compare_'.$month1.'_'.$month2;

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($month1, $month2) {
            $months = [$month1, $month2];
            $results = [];

            foreach ($months as $idx => $m) {
                $monthDate = Carbon::parse($m)->startOfMonth()->toDateString();
                $payroll = Payroll::query()
                    ->whereIn('status', [PayrollStatus::APPROVED, PayrollStatus::PAID])
                    ->whereDate('salary_month', $monthDate)
                    ->first();

                if (! $payroll) {
                    $results[$idx === 0 ? 'month1' : 'month2'] = [
                        'period' => Carbon::parse($m)->format('F Y'),
                        'found' => false,
                        'employee_count' => 0,
                        'gross_salary' => 0,
                        'allowances' => 0,
                        'deductions' => 0,
                        'bpjs_deductions' => 0,
                        'bpjs_employer' => 0,
                        'tax_amount' => 0,
                        'net_salary' => 0,
                    ];

                    continue;
                }

                $details = $payroll->payrollDetails()
                    ->select([
                        DB::raw('COUNT(DISTINCT staff_member_id) as employee_count'),
                        DB::raw('COALESCE(SUM(original_salary), 0) as gross_salary'),
                        DB::raw('0 as allowances'),
                        DB::raw('COALESCE(SUM(deduction_amount), 0) as total_deductions'),
                        DB::raw('COALESCE(SUM(bpjs_tk_employee + bpjs_kes_employee), 0) as bpjs_deductions'),
                        DB::raw('COALESCE(SUM(bpjs_tk_employer + bpjs_kes_employer), 0) as bpjs_employer'),
                        DB::raw('COALESCE(SUM(pph21_amount), 0) as tax_amount'),
                        DB::raw('COALESCE(SUM(final_salary), 0) as net_salary'),
                    ])->first();

                $results[$idx === 0 ? 'month1' : 'month2'] = [
                    'period' => Carbon::parse($m)->format('F Y'),
                    'found' => true,
                    'employee_count' => (int) $details->employee_count,
                    'gross_salary' => (float) $details->gross_salary,
                    'allowances' => (float) $details->allowances,
                    'deductions' => (float) $details->total_deductions,
                    'bpjs_deductions' => (float) $details->bpjs_deductions,
                    'bpjs_employer' => (float) $details->bpjs_employer,
                    'tax_amount' => (float) $details->tax_amount,
                    'net_salary' => (float) $details->net_salary,
                ];
            }

            // Calculate variances
            $m1 = $results['month1'];
            $m2 = $results['month2'];

            $variances = [];
            $metrics = ['employee_count', 'gross_salary', 'allowances', 'deductions', 'bpjs_deductions', 'bpjs_employer', 'tax_amount', 'net_salary'];

            foreach ($metrics as $metric) {
                $diff = $m2[$metric] - $m1[$metric];
                $pct = $m1[$metric] > 0 ? ($diff / $m1[$metric]) * 100 : ($m2[$metric] > 0 ? 100 : 0);

                $variances[$metric] = [
                    'difference' => $diff,
                    'percentage' => round($pct, 2),
                ];
            }

            return [
                'month1' => $m1,
                'month2' => $m2,
                'variances' => $variances,
            ];
        });
    }

    public function getAnalytics(int $months = 6): array
    {
        $months = max(1, min(24, $months));
        $cacheKey = CacheConstants::CACHE_KEY_PAYROLL_ANALYTICS.$months.'_'.now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($months) {
            $periodRows = Payroll::query()
                ->leftJoin('payroll_details', 'payroll_details.payroll_id', '=', 'payrolls.id')
                ->whereIn('payrolls.status', [PayrollStatus::APPROVED, PayrollStatus::PAID])
                ->groupBy(DB::raw('DATE(payrolls.salary_month)'))
                ->orderByDesc(DB::raw('DATE(payrolls.salary_month)'))
                ->limit($months)
                ->get([
                    DB::raw('DATE(payrolls.salary_month) as salary_month'),
                    DB::raw('COUNT(DISTINCT payrolls.id) as payroll_count'),
                    DB::raw('COUNT(DISTINCT payroll_details.staff_member_id) as employee_count'),
                    DB::raw('COALESCE(SUM(payroll_details.final_salary), 0) as total_amount'),
                    DB::raw('COALESCE(AVG(payroll_details.final_salary), 0) as average_salary'),
                    DB::raw('COALESCE(SUM(payroll_details.deduction_amount), 0) as total_deductions'),
                ])
                ->sortBy('salary_month')
                ->values();

            $trends = $periodRows
                ->map(function ($row) {
                    $salaryMonth = Carbon::parse($row->salary_month)->startOfMonth();
                    $totalAmount = (float) $row->total_amount;
                    $totalDeductions = (float) $row->total_deductions;

                    return [
                        'salary_month' => $salaryMonth->toDateString(),
                        'label' => $salaryMonth->format('M Y'),
                        'payroll_count' => (int) $row->payroll_count,
                        'employee_count' => (int) $row->employee_count,
                        'total_amount' => round($totalAmount, 2),
                        'average_salary' => round((float) $row->average_salary, 2),
                        'total_deductions' => round($totalDeductions, 2),
                        'deduction_rate' => $totalAmount > 0
                            ? round($totalDeductions / $totalAmount, 4)
                            : 0,
                    ];
                })
                ->values();

            $firstTrend = $trends->first();
            $lastTrend = $trends->last();
            $totalAmount = (float) $trends->sum('total_amount');
            $totalDeductions = (float) $trends->sum('total_deductions');
            $totalEmployeeEntries = (int) $trends->sum('employee_count');

            $salaryGrowthPercentage = 0;
            $headcountChange = 0;
            $deductionRateChange = 0;

            if ($firstTrend && $lastTrend) {
                $firstAmount = (float) ($firstTrend['total_amount'] ?? 0);
                $lastAmount = (float) ($lastTrend['total_amount'] ?? 0);
                $firstDeductionRate = (float) ($firstTrend['deduction_rate'] ?? 0);
                $lastDeductionRate = (float) ($lastTrend['deduction_rate'] ?? 0);

                $salaryGrowthPercentage = $firstAmount > 0
                    ? round((($lastAmount - $firstAmount) / $firstAmount) * 100, 2)
                    : 0;
                $headcountChange = (int) ($lastTrend['employee_count'] ?? 0) - (int) ($firstTrend['employee_count'] ?? 0);
                $deductionRateChange = round($lastDeductionRate - $firstDeductionRate, 4);
            }

            // Enhanced analytics: BPJS contribution trend
            $bpjsTrendRows = Payroll::query()
                ->leftJoin('payroll_details', 'payroll_details.payroll_id', '=', 'payrolls.id')
                ->whereIn('payrolls.status', [PayrollStatus::APPROVED, PayrollStatus::PAID])
                ->groupBy(DB::raw('DATE(payrolls.salary_month)'))
                ->orderByDesc(DB::raw('DATE(payrolls.salary_month)'))
                ->limit($months)
                ->get([
                    DB::raw('DATE(payrolls.salary_month) as salary_month'),
                    DB::raw('COALESCE(SUM(payroll_details.bpjs_tk_employee), 0) as bpjs_tk_employee_total'),
                    DB::raw('COALESCE(SUM(payroll_details.bpjs_tk_employer), 0) as bpjs_tk_employer_total'),
                    DB::raw('COALESCE(SUM(payroll_details.bpjs_kes_employee), 0) as bpjs_kes_employee_total'),
                    DB::raw('COALESCE(SUM(payroll_details.bpjs_kes_employer), 0) as bpjs_kes_employer_total'),
                ])
                ->keyBy('salary_month');

            // Enhanced analytics: top deduction reasons
            $deductionReasonRows = Payroll::query()
                ->leftJoin('payroll_details', 'payroll_details.payroll_id', '=', 'payrolls.id')
                ->whereIn('payrolls.status', [PayrollStatus::APPROVED, PayrollStatus::PAID])
                ->whereDate('payrolls.salary_month', '>=', $firstTrend['salary_month'] ?? now()->subMonths($months)->toDateString())
                ->get([
                    DB::raw('COALESCE(SUM(payroll_details.absent_days), 0) as total_absent_days'),
                    DB::raw('COALESCE(SUM(payroll_details.half_day_count), 0) as total_half_days'),
                    DB::raw('COALESCE(SUM(payroll_details.unpaid_leave_days), 0) as total_unpaid_leave_days'),
                    DB::raw('COALESCE(SUM(payroll_details.deduction_amount), 0) as total_deduction_amount'),
                ])
                ->first();

            $topDeductionReasons = [
                ['reason' => 'absent', 'days' => (int) ($deductionReasonRows->total_absent_days ?? 0)],
                ['reason' => 'half_day', 'days' => (int) ($deductionReasonRows->total_half_days ?? 0)],
                ['reason' => 'unpaid_leave', 'days' => (int) ($deductionReasonRows->total_unpaid_leave_days ?? 0)],
            ];

            usort($topDeductionReasons, fn ($a, $b) => $b['days'] <=> $a['days']);

            // Build enhanced trends with BPJS data
            $enhancedTrends = $trends->map(function ($trend) use ($bpjsTrendRows) {
                $bpjsRow = $bpjsTrendRows->get($trend['salary_month']);

                $bpjsEmployeeTotal = $bpjsRow
                    ? round((float) $bpjsRow->bpjs_tk_employee_total + (float) $bpjsRow->bpjs_kes_employee_total, 2)
                    : 0;
                $bpjsEmployerTotal = $bpjsRow
                    ? round((float) $bpjsRow->bpjs_tk_employer_total + (float) $bpjsRow->bpjs_kes_employer_total, 2)
                    : 0;

                return [
                    ...$trend,
                    'bpjs_employee_total' => $bpjsEmployeeTotal,
                    'bpjs_employer_total' => $bpjsEmployerTotal,
                    'bpjs_combined_total' => round($bpjsEmployeeTotal + $bpjsEmployerTotal, 2),
                ];
            })->values();

            // Average salary trend and headcount vs payroll growth
            $averageSalaryTrend = $enhancedTrends->map(fn ($t) => [
                'salary_month' => $t['salary_month'],
                'label' => $t['label'],
                'average_salary' => $t['average_salary'],
            ])->values()->all();

            $headcountVsPayrollGrowth = $enhancedTrends->map(fn ($t) => [
                'salary_month' => $t['salary_month'],
                'label' => $t['label'],
                'employee_count' => $t['employee_count'],
                'total_amount' => $t['total_amount'],
            ])->values()->all();

            $bpjsContributionTrend = $enhancedTrends->map(fn ($t) => [
                'salary_month' => $t['salary_month'],
                'label' => $t['label'],
                'bpjs_employee_total' => $t['bpjs_employee_total'],
                'bpjs_employer_total' => $t['bpjs_employer_total'],
                'bpjs_combined_total' => $t['bpjs_combined_total'],
            ])->values()->all();

            $totalDeductionsTrend = $enhancedTrends->map(fn ($t) => [
                'salary_month' => $t['salary_month'],
                'label' => $t['label'],
                'total_deductions' => $t['total_deductions'],
            ])->values()->all();

            return [
                'periods_requested' => $months,
                'periods_returned' => $trends->count(),
                'status_scope' => [PayrollStatus::APPROVED->value, PayrollStatus::PAID->value],
                'reporting_period' => [
                    'start_month' => $firstTrend['salary_month'] ?? null,
                    'end_month' => $lastTrend['salary_month'] ?? null,
                    'as_of_timestamp' => now()->toIso8601String(),
                ],
                'summary' => [
                    'total_payroll_batches' => (int) $trends->sum('payroll_count'),
                    'total_employee_entries' => $totalEmployeeEntries,
                    'total_amount' => round($totalAmount, 2),
                    'total_deductions' => round($totalDeductions, 2),
                    'average_salary_across_periods' => $totalEmployeeEntries > 0
                        ? round($totalAmount / $totalEmployeeEntries, 2)
                        : 0,
                    'average_deduction_rate' => $totalAmount > 0
                        ? round($totalDeductions / $totalAmount, 4)
                        : 0,
                ],
                'growth_metrics' => [
                    'salary_growth_percentage' => $salaryGrowthPercentage,
                    'headcount_change' => $headcountChange,
                    'deduction_rate_change' => $deductionRateChange,
                ],
                'trends' => $enhancedTrends->all(),
                'average_salary_trend' => $averageSalaryTrend,
                'total_deductions_trend' => $totalDeductionsTrend,
                'headcount_vs_payroll_growth' => $headcountVsPayrollGrowth,
                'bpjs_contribution_trend' => $bpjsContributionTrend,
                'top_deduction_reasons' => $topDeductionReasons,
            ];
        });
    }

    public function getPayrollStatistics(string $payrollId)
    {
        // Cache key for payroll-specific statistics
        $cacheKey = CacheConstants::CACHE_KEY_PAYROLL_STATISTICS.$payrollId.'_'.now()->format('Y-m-d-H');

        // Cache for 1 hour
        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($payrollId) {
            $payroll = Payroll::findOrFail($payrollId);

            // Get all statistics in optimized queries
            $detailStats = PayrollDetail::where('payroll_id', $payrollId)
                ->selectRaw('
                    COUNT(*) as total_employees,
                    SUM(original_salary) as total_original_salary,
                    SUM(final_salary) as total_final_salary,
                    SUM(original_salary - final_salary) as total_deductions,
                    AVG(final_salary) as average_salary,
                    MAX(final_salary) as highest_salary,
                    MIN(final_salary) as lowest_salary,
                    SUM(attended_days) as total_attended_days,
                    SUM(sick_days) as total_sick_days,
                    SUM(absent_days) as total_absent_days
                ')
                ->first();

            return [
                'payroll_id' => $payroll->id,
                'salary_month' => $payroll->salary_month,
                'status' => $payroll->status,
                'payment_date' => $payroll->payment_date,
                'processed_date' => $payroll->created_at->format('Y-m-d'),
                'total_employees' => $detailStats->total_employees ?? 0,
                'total_amount' => round($detailStats->total_final_salary ?? 0, 2),
                'total_original_salary' => round($detailStats->total_original_salary ?? 0, 2),
                'total_deductions' => round($detailStats->total_deductions ?? 0, 2),
                'average_salary' => round($detailStats->average_salary ?? 0, 2),
                'highest_salary' => round($detailStats->highest_salary ?? 0, 2),
                'lowest_salary' => round($detailStats->lowest_salary ?? 0, 2),
                'total_attended_days' => $detailStats->total_attended_days ?? 0,
                'total_sick_days' => $detailStats->total_sick_days ?? 0,
                'total_absent_days' => $detailStats->total_absent_days ?? 0,
            ];
        });
    }

    public function getPayrollReportRows(array $filters): SupportCollection
    {
        $status = $filters['status'] ?? 'all';
        $periodType = $filters['period_type'] ?? 'monthly';
        $reportType = $filters['report_type'] ?? 'summary';

        if ($reportType === 'detail') {
            $query = PayrollDetail::query()
                ->with([
                    'payroll',
                    'staffMember.user',
                    'staffMember.jobInformation.team',
                ])
                ->whereHas('payroll', function ($payrollQuery) use ($status, $periodType, $filters) {
                    if ($status !== 'all') {
                        $payrollQuery->where('status', $status);
                    }

                    if ($periodType === 'monthly') {
                        $month = Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth();
                        $payrollQuery->whereDate('salary_month', $month->toDateString());
                    } else {
                        $payrollQuery->whereYear('salary_month', (int) $filters['year']);
                    }
                })
                ->orderByDesc(
                    Payroll::select('salary_month')
                        ->whereColumn('payrolls.id', 'payroll_details.payroll_id')
                        ->limit(1)
                )
                ->orderByDesc('final_salary');

            return $query->get()->map(function (PayrollDetail $detail) {
                $payroll = $detail->payroll;
                $employee = $detail->staffMember;
                $jobInformation = $employee?->jobInformation;

                return [
                    'payroll_id' => $payroll?->id,
                    'period' => $payroll ? Carbon::parse($payroll->salary_month)->format('F Y') : '-',
                    'status' => $payroll ? ucfirst($payroll->status->value) : '-',
                    'employee_name' => $employee?->user?->name ?? 'N/A',
                    'employee_code' => $employee?->code ?? 'N/A',
                    'team_name' => $jobInformation?->team?->name ?? 'N/A',
                    'job_title' => $jobInformation?->job_title ?? 'N/A',
                    'original_salary' => (float) ($detail->original_salary ?? 0),
                    'deduction_amount' => (float) (($detail->original_salary ?? 0) - ($detail->final_salary ?? 0)),
                    'final_salary' => (float) ($detail->final_salary ?? 0),
                    'attended_days' => (int) ($detail->attended_days ?? 0),
                    'sick_days' => (int) ($detail->sick_days ?? 0),
                    'absent_days' => (int) ($detail->absent_days ?? 0),
                    'payment_date' => $payroll?->payment_date
                        ? Carbon::parse($payroll->payment_date)->format('Y-m-d')
                        : '-',
                ];
            })->values();
        }

        $query = Payroll::query()
            ->withCount('payrollDetails')
            ->withSum('payrollDetails as total_amount', 'final_salary')
            ->orderBy('salary_month', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($periodType === 'monthly') {
            $month = Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth();
            $query->whereDate('salary_month', $month->toDateString());
        } else {
            $query->whereYear('salary_month', (int) $filters['year']);
        }

        return $query->get()->map(function (Payroll $payroll) {
            return [
                'payroll_id' => $payroll->id,
                'period' => Carbon::parse($payroll->salary_month)->format('F Y'),
                'status' => ucfirst($payroll->status->value),
                'total_employee' => $payroll->payroll_details_count ?? 0,
                'total_amount' => (float) ($payroll->total_amount ?? 0),
                'payment_date' => $payroll->payment_date
                    ? Carbon::parse($payroll->payment_date)->format('Y-m-d')
                    : '-',
                'created_at' => $payroll->created_at
                    ? Carbon::parse($payroll->created_at)->format('Y-m-d H:i:s')
                    : '-',
            ];
        })->values();
    }

    public function getActivityLogs(string $payrollId): SupportCollection
    {
        Payroll::findOrFail($payrollId);

        return PayrollActivityLog::with('actor')
            ->where('payroll_id', $payrollId)
            ->orderByDesc('occurred_at')
            ->get();
    }

    public function getSettingVersionDiff(int $versionId): array
    {
        $version = PayrollSettingVersion::with('updatedBy')->findOrFail($versionId);

        $previousVersion = PayrollSettingVersion::query()
            ->where('payroll_setting_id', $version->payroll_setting_id)
            ->where('version_number', '<', $version->version_number)
            ->orderByDesc('version_number')
            ->first();

        $trackedFields = PayrollSetting::VERSIONED_FIELDS;
        $changes = [];

        if ($previousVersion) {
            foreach ($trackedFields as $field) {
                $oldValue = $previousVersion->{$field};
                $newValue = $version->{$field};

                $normalizedOld = $this->normalizeVersionFieldValue($field, $oldValue);
                $normalizedNew = $this->normalizeVersionFieldValue($field, $newValue);

                if ($normalizedOld !== $normalizedNew) {
                    $changes[] = [
                        'field' => $field,
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                    ];
                }
            }
        }

        return [
            'version_id' => $version->id,
            'version_number' => (int) $version->version_number,
            'effective_at' => $version->effective_at?->toIso8601String(),
            'updated_by' => $version->updatedBy ? [
                'id' => $version->updatedBy->id,
                'name' => $version->updatedBy->name,
            ] : null,
            'previous_version_number' => $previousVersion ? (int) $previousVersion->version_number : null,
            'has_previous' => $previousVersion !== null,
            'changes' => $changes,
        ];
    }

    private function normalizeVersionFieldValue(string $field, mixed $value): string
    {
        if ($field === 'absent_deduction_rate') {
            return number_format((float) $value, 2, '.', '');
        }

        if ($field === 'note_template') {
            return trim((string) ($value ?? ''));
        }

        return (string) ($value ?? '');
    }

    public function getApprovalPolicies(): Collection
    {
        return PayrollApprovalPolicy::query()
            ->orderBy('approval_order')
            ->get();
    }

    public function createApprovalPolicy(array $data): PayrollApprovalPolicy
    {
        return PayrollApprovalPolicy::create($data);
    }

    public function updateApprovalPolicy(int $id, array $data): PayrollApprovalPolicy
    {
        $policy = PayrollApprovalPolicy::findOrFail($id);
        $policy->update($data);

        return $policy;
    }

    public function deleteApprovalPolicy(int $id): void
    {
        $policy = PayrollApprovalPolicy::findOrFail($id);
        $policy->delete();
    }

    public function getApprovalStatus(string $payrollId): array
    {
        $payroll = Payroll::findOrFail($payrollId);

        $approvals = PayrollApproval::with(['policy', 'approver'])
            ->where('payroll_id', $payroll->id)
            ->orderBy('id')
            ->get();

        $allApproved = $approvals->isNotEmpty() && $approvals->every(fn (PayrollApproval $a) => $a->status === PayrollApproval::STATUS_APPROVED);
        $hasRejection = $approvals->contains(fn (PayrollApproval $a) => $a->status === PayrollApproval::STATUS_REJECTED);

        return [
            'payroll_id' => (int) $payroll->id,
            'is_multi_step' => $approvals->isNotEmpty(),
            'all_approved' => $allApproved,
            'has_rejection' => $hasRejection,
            'approvals' => $approvals->map(function (PayrollApproval $approval) {
                return [
                    'id' => $approval->id,
                    'policy_name' => $approval->policy?->name,
                    'required_role' => $approval->policy?->required_role,
                    'approval_order' => $approval->policy?->approval_order,
                    'status' => $approval->status,
                    'approver' => $approval->approver ? [
                        'id' => $approval->approver->id,
                        'name' => $approval->approver->name,
                    ] : null,
                    'notes' => $approval->notes,
                    'approved_at' => $approval->approved_at?->toIso8601String(),
                ];
            })->all(),
        ];
    }

    public function submitApprovalDecision(string $payrollId, array $data, ?int $actorId = null): array
    {
        // Use NOWAIT to fail immediately if another process holds the lock,
        try {
            return DB::transaction(function () use ($payrollId, $data, $actorId) {
                $payroll = Payroll::query()
                    ->whereKey($payrollId)
                    ->lock('for update nowait')
                    ->firstOrFail();

            if (! in_array($payroll->status, [PayrollStatus::PENDING, PayrollStatus::APPROVED], true)) {
                throw new \Exception('Payroll must be pending or approved to submit approval decisions');
            }

            $approvals = PayrollApproval::where('payroll_id', $payroll->id)->get();

            if ($approvals->isEmpty()) {
                throw new \Exception('No approval steps found for this payroll');
            }

            // Find the next pending approval for the actor's role
            $actor = $actorId ? User::find($actorId) : null;
            $targetApproval = null;

            foreach ($approvals as $approval) {
                if ($approval->status !== PayrollApproval::STATUS_PENDING) {
                    continue;
                }

                $requiredRole = $approval->policy?->required_role;
                if ($actor && $requiredRole && $actor->hasRole($requiredRole)) {
                    $targetApproval = $approval;
                    break;
                }
            }

            if (! $targetApproval) {
                throw new \Exception('No pending approval step found for your role');
            }

            $status = $data['status']; // 'approved' or 'rejected'
            $targetApproval->update([
                'status' => $status,
                'approver_id' => $actorId,
                'notes' => $data['notes'] ?? null,
                'approved_at' => $status === PayrollApproval::STATUS_APPROVED ? now() : null,
            ]);

            $this->activityLogger->log(
                $payroll->id,
                'approval_decision',
                'Approval decision submitted',
                sprintf(
                    'Approval step "%s" was %s.',
                    $targetApproval->policy?->name ?? 'Unknown',
                    $status
                ),
                $actorId,
                [
                    'approval_id' => $targetApproval->id,
                    'policy_name' => $targetApproval->policy?->name,
                    'decision' => $status,
                ]
            );

            // Check if all approvals are now approved
            $allApproved = PayrollApproval::where('payroll_id', $payroll->id)
                ->where('status', '!=', PayrollApproval::STATUS_APPROVED)
                ->doesntExist();

            if ($allApproved && $payroll->status === PayrollStatus::PENDING) {
                $payroll->update(['status' => PayrollStatus::APPROVED]);

                $this->activityLogger->log(
                    $payroll->id,
                    'approved',
                    'Payroll approved via multi-step approval',
                    'All required approval steps have been completed.',
                    $actorId
                );

                DB::afterCommit(function () use ($payroll, $actorId) {
                    $actorName = $actorId ? User::find($actorId)?->name : null;
                    $this->emailService->sendPayrollApprovedNotification($payroll, $actorName);
                });
            }

            return $this->getApprovalStatus($payrollId);
        });
        } catch (QueryException $e) {
            if ($this->isLockContentionError($e)) {
                throw new \Exception('This payroll is currently being processed by another user. Please try again in a few moments.');
            }
            throw $e;
        }
    }

    public function getMyPayslipsPaginated(
        int $staffMemberId,
        ?string $search,
        ?int $year,
        int $rowPerPage
    ) {
        return PayrollDetail::query()
            ->select('payroll_details.*')
            ->with([
                'payroll',
                'staffMember.user',
                'staffMember.jobInformation.team',
            ])
            ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
            ->where('staff_member_id', $staffMemberId)
            ->where('payrolls.status', PayrollStatus::PAID)
            ->where('payrolls.status', PayrollStatus::PAID)
            ->when($year, function ($query, $yearValue) {
                $query->whereYear('payrolls.salary_month', $yearValue);
            })
            ->when($search, function ($query, $searchValue) {
                $query->where('payrolls.salary_month', 'like', '%'.$searchValue.'%');
            })
            ->orderByDesc('payrolls.salary_month')
            ->paginate($rowPerPage);
    }

    public function findOwnedPaidPayslipOrFail(string $id, int $staffMemberId)
    {
        $payslip = PayrollDetail::with([
            'payroll.payrollSettingVersion',
            'staffMember.user',
            'staffMember.jobInformation.team',
            'staffMember.bankInformation',
        ])
            ->where('id', $id)
            ->where('staff_member_id', $staffMemberId)
            ->whereHas('payroll', function ($query) {
                $query->where('status', PayrollStatus::PAID);
                $query->where('status', PayrollStatus::PAID);
            })
            ->firstOrFail();

        $targetPeriodId = $payslip->payroll?->attendance_period_id;
        $appliedAdjustments = collect();

        if ($targetPeriodId) {
            $appliedAdjustments = PayrollAdjustment::query()
                ->where('staff_member_id', $payslip->staff_member_id)
                ->where('target_period_id', $targetPeriodId)
                ->where('status', PayrollAdjustment::STATUS_APPLIED)
                ->orderBy('id')
                ->get();
        }

        $payslip->setRelation('appliedAdjustments', $appliedAdjustments);

        return $payslip;
    }

    private function sendCorrectionNotifications(Payroll $payroll, int $correctionCount): void
    {
        $details = PayrollDetail::with(['staffMember.user'])
            ->where('payroll_id', $payroll->id)
            ->get();

        foreach ($details as $detail) {
            $user = $detail->staffMember?->user;
            if ($user) {
                $user->notify(new PayrollCorrected($detail, $correctionCount));
            }
        }
    }

    private function buildGenerateReadiness(Carbon $month): array
    {
        $settings = PayrollSetting::current();
        $monthLabel = $month->translatedFormat('F Y');
        $currentMonth = now()->copy()->startOfMonth();
        $attendancePeriod = $this->attendancePeriodService->ensurePeriodForMonth(
            $month,
            (int) $settings->attendance_cutoff_day
        );
        $this->attendancePeriodService->transitionOpenPeriodsToReview(now());
        $attendancePeriod = $attendancePeriod->fresh();
        $cutoffDate = $month->copy()->day(
            min((int) $settings->attendance_cutoff_day, $month->copy()->endOfMonth()->day)
        );

        $basePayload = [
            'salary_month' => $month->format('Y-m'),
            'cutoff_day' => (int) $settings->attendance_cutoff_day,
            'cutoff_date' => $cutoffDate->toDateString(),
            'attendance_period_id' => $attendancePeriod->id,
            'attendance_period_status' => $attendancePeriod->status,
        ];

        $existingPayroll = Payroll::query()
            ->whereDate('salary_month', $month->toDateString())
            ->first();

        if ($existingPayroll) {
            return [
                'can_generate' => false,
                'reason_code' => 'duplicate_period',
                'message' => sprintf('Payroll for %s already exists.', $monthLabel),
                'meta' => [
                    ...$basePayload,
                    'existing_payroll_id' => $existingPayroll->id,
                    'existing_status' => $existingPayroll->status,
                ],
            ];
        }

        if ($month->greaterThan($currentMonth)) {
            return [
                'can_generate' => false,
                'reason_code' => 'future_month',
                'message' => sprintf(
                    'Payroll for %s cannot be generated yet because it is in the future.',
                    $monthLabel
                ),
                'meta' => $basePayload,
            ];
        }

        if ($month->equalTo($currentMonth) && now()->lt($cutoffDate->copy()->endOfDay())) {
            return [
                'can_generate' => false,
                'reason_code' => 'cutoff_not_reached',
                'message' => sprintf(
                    'Payroll for %s can only be generated after the attendance cut-off date on %s.',
                    $monthLabel,
                    $cutoffDate->translatedFormat('d F Y')
                ),
                'meta' => $basePayload,
            ];
        }

        if ($attendancePeriod->status !== AttendancePeriod::STATUS_REVIEW) {
            return [
                'can_generate' => false,
                'reason_code' => 'period_not_in_review',
                'message' => sprintf(
                    'Attendance period for %s must be in review status before payroll can be generated.',
                    $monthLabel
                ),
                'meta' => $basePayload,
            ];
        }

        $activeEmployeeIds = StaffMemberProfile::query()
            ->whereHas('jobInformation', function ($query) {
                $query->where('status', 'active');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $employeeIdsWithAttendance = Attendance::query()
            ->whereDate('date', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->distinct()
            ->pluck('staff_member_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $activeEmployeesCount = count($activeEmployeeIds);
        $activeEmployeesWithAttendanceCount = count(array_intersect($activeEmployeeIds, $employeeIdsWithAttendance));

        if ($activeEmployeesCount === 0 || $activeEmployeesWithAttendanceCount === 0) {
            return [
                'can_generate' => false,
                'reason_code' => 'attendance_not_ready',
                'message' => sprintf(
                    'Attendance data for %s is not ready yet. Add attendance records for active employees before generating payroll.',
                    $monthLabel
                ),
                'meta' => [
                    ...$basePayload,
                    'active_employee_count' => $activeEmployeesCount,
                    'active_employees_with_attendance_count' => $activeEmployeesWithAttendanceCount,
                    'staff_member_ids_with_attendance' => $employeeIdsWithAttendance,
                ],
            ];
        }

        $blockedEmployees = $this->collectBlockedEmployees($month, $activeEmployeeIds);
        if (! empty($blockedEmployees['blocked_staff_member_ids'])) {
            return [
                'can_generate' => false,
                'reason_code' => 'employees_blocked',
                'message' => sprintf(
                    'Payroll for %s cannot be generated because some employees are still blocked in readiness checks.',
                    $monthLabel
                ),
                'meta' => [
                    ...$basePayload,
                    'active_employee_count' => $activeEmployeesCount,
                    'active_employees_with_attendance_count' => $activeEmployeesWithAttendanceCount,
                    'staff_member_ids_with_attendance' => $employeeIdsWithAttendance,
                    'blocked_employee_count' => count($blockedEmployees['blocked_staff_member_ids']),
                    'blocked_staff_member_ids' => $blockedEmployees['blocked_staff_member_ids'],
                    'blocked_reasons' => $blockedEmployees['blocked_reasons'],
                ],
            ];
        }

        return [
            'can_generate' => true,
            'reason_code' => 'ready',
            'message' => sprintf('Payroll for %s is ready to be generated.', $monthLabel),
            'meta' => [
                ...$basePayload,
                'active_employee_count' => $activeEmployeesCount,
                'active_employees_with_attendance_count' => $activeEmployeesWithAttendanceCount,
                'staff_member_ids_with_attendance' => $employeeIdsWithAttendance,
            ],
        ];
    }

    private function collectBlockedEmployees(Carbon $month, array $activeEmployeeIds): array
    {
        $payload = $this->buildEmployeeReadinessRows($month, $activeEmployeeIds);

        return [
            'blocked_staff_member_ids' => $payload['blocked_staff_member_ids'],
            'blocked_reasons' => $payload['blocked_reasons'],
        ];
    }

    private function buildEmployeeReadinessRows(Carbon $month, array $activeEmployeeIds): array
    {
        $buckets = $this->initializeReadinessBuckets();
        $blockedReasons = $buckets['blocked_reasons'];
        $warningFlags = $buckets['warning_flags'];

        if (empty($activeEmployeeIds)) {
            return $this->buildEmptyReadinessRowsPayload($blockedReasons, $warningFlags);
        }

        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();
        $employees = $this->loadActiveEmployeesForReadiness($activeEmployeeIds);

        if ($employees->isEmpty()) {
            return $this->buildEmptyReadinessRowsPayload($blockedReasons, $warningFlags);
        }

        $lookups = $this->buildReadinessLookups($employees, $startDate, $endDate);
        $rows = [];
        $blockedEmployeeIds = [];

        foreach ($employees as $employee) {
            $employeeReadiness = $this->buildEmployeeReadinessRow($employee, $startDate, $endDate, $lookups);

            $rows[] = $employeeReadiness['row'];

            if ($employeeReadiness['status'] === 'blocked') {
                $blockedEmployeeIds[] = $employeeReadiness['staff_member_id'];
            }

            $this->appendReadinessEmployeeBuckets(
                $employeeReadiness['staff_member_id'],
                $employeeReadiness['blocker_reasons'],
                $employeeReadiness['warning_flags'],
                $blockedReasons,
                $warningFlags
            );
        }

        $normalized = $this->normalizeReadinessBuckets($blockedEmployeeIds, $blockedReasons, $warningFlags);

        return [
            'rows' => $rows,
            'blocked_staff_member_ids' => $normalized['blocked_staff_member_ids'],
            'blocked_reasons' => $normalized['blocked_reasons'],
            'warning_flags' => $normalized['warning_flags'],
        ];
    }

    private function initializeReadinessBuckets(): array
    {
        return [
            'blocked_reasons' => [
                'pending_leave_approval' => [],
                'sick_proof_unresolved' => [],
                'missing_attendance_or_valid_leave' => [],
                'invalid_leave_entitlement' => [],
            ],
            'warning_flags' => [
                'absent_pct_threshold_reached' => [],
                'unresolved_policy_mismatch' => [],
                'high_late_trend' => [],
                'high_half_day_trend' => [],
            ],
        ];
    }

    private function buildEmptyReadinessRowsPayload(array $blockedReasons, array $warningFlags): array
    {
        return [
            'rows' => [],
            'blocked_staff_member_ids' => [],
            'blocked_reasons' => $blockedReasons,
            'warning_flags' => $warningFlags,
        ];
    }

    private function loadActiveEmployeesForReadiness(array $activeEmployeeIds): SupportCollection
    {
        return StaffMemberProfile::query()
            ->with([
                'user',
                'jobInformation.team',
                'jobInformation.attendancePolicy',
            ])
            ->whereIn('id', $activeEmployeeIds)
            ->whereHas('jobInformation', function ($query) {
                $query->where('status', 'active');
            })
            ->get();
    }

    /**
     * @param  SupportCollection<int, StaffMemberProfile>  $employees
     * @return array{
     *   attendance_date_lookup_by_employee: SupportCollection<int, array<string, bool>>,
     *   pending_lookup: array<int, bool>,
     *   sick_proof_lookup: array<int, bool>,
     *   mismatch_lookup: array<int, bool>,
     *   approved_leaves_by_employee: SupportCollection<int, SupportCollection<int, LeaveRequest>>,
     *   entitlements_by_employment_type: SupportCollection<string, SupportCollection<string, LeaveEntitlement>>,
     *   holiday_calendars: SupportCollection<int, HolidayCalendar>
     * }
     */
    private function buildReadinessLookups(SupportCollection $employees, Carbon $startDate, Carbon $endDate): array
    {
        $employeeIds = $employees
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'attendance_date_lookup_by_employee' => $this->getAttendanceDateLookup($employeeIds, $startDate, $endDate),
            'pending_lookup' => $this->getPendingLeaveLookup($employeeIds, $startDate, $endDate),
            'sick_proof_lookup' => $this->getSickProofLookup($employeeIds, $startDate, $endDate),
            'mismatch_lookup' => $this->getMismatchLookup($employeeIds, $startDate, $endDate),
            'approved_leaves_by_employee' => $this->getApprovedLeavesLookup($employeeIds, $startDate, $endDate),
            'entitlements_by_employment_type' => $this->getEntitlementsLookup($employees),
            'holiday_calendars' => $this->getHolidayCalendarsLookup($startDate, $endDate),
        ];
    }

    private function getAttendanceDateLookup(array $employeeIds, Carbon $startDate, Carbon $endDate): SupportCollection
    {
        return Attendance::query()
            ->select(['staff_member_id', 'date'])
            ->whereIn('staff_member_id', $employeeIds)
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->get()
            ->groupBy('staff_member_id')
            ->map(function (SupportCollection $records) {
                $lookup = [];

                foreach ($records as $record) {
                    $lookup[Carbon::parse((string) $record->date)->toDateString()] = true;
                }

                return $lookup;
            });
    }

    private function getPendingLeaveLookup(array $employeeIds, Carbon $startDate, Carbon $endDate): array
    {
        $pendingLeaveApproval = LeaveRequest::query()
            ->whereIn('staff_member_id', $employeeIds)
            ->where('status', 'pending')
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->distinct()
            ->pluck('staff_member_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return array_fill_keys($pendingLeaveApproval, true);
    }

    private function getSickProofLookup(array $employeeIds, Carbon $startDate, Carbon $endDate): array
    {
        $sickProofUnresolved = LeaveRequest::query()
            ->whereIn('staff_member_id', $employeeIds)
            ->where('status', 'approved')
            ->where('leave_type', 'sick_leave')
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->where(function ($query) {
                $query->whereNull('proof_review_status')
                    ->orWhere('proof_review_status', '!=', 'approved')
                    ->orWhereNull('proof_file_path')
                    ->orWhereNull('proof_file_name')
                    ->orWhereNull('proof_mime_type')
                    ->orWhereNull('proof_size_kb')
                    ->orWhereNull('proof_uploaded_at');
            })
            ->distinct()
            ->pluck('staff_member_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return array_fill_keys($sickProofUnresolved, true);
    }

    private function getMismatchLookup(array $employeeIds, Carbon $startDate, Carbon $endDate): array
    {
        $unresolvedPolicyMismatch = AttendancePolicyMismatch::query()
            ->whereIn('staff_member_id', $employeeIds)
            ->whereDate('mismatch_date', '>=', $startDate->toDateString())
            ->whereDate('mismatch_date', '<=', $endDate->toDateString())
            ->whereIn('status', self::UNRESOLVED_MISMATCH_STATUSES)
            ->distinct()
            ->pluck('staff_member_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return array_fill_keys($unresolvedPolicyMismatch, true);
    }

    private function getApprovedLeavesLookup(array $employeeIds, Carbon $startDate, Carbon $endDate): SupportCollection
    {
        return LeaveRequest::query()
            ->whereIn('staff_member_id', $employeeIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->get()
            ->groupBy('staff_member_id');
    }

    private function getEntitlementsLookup(SupportCollection $employees): SupportCollection
    {
        $employmentTypes = $employees
            ->map(fn (StaffMemberProfile $employee) => AttendanceHelper::normalizeEmploymentType((string) ($employee->jobInformation?->employment_type ?? 'full_time')))
            ->unique()
            ->values()
            ->all();

        return LeaveEntitlement::query()
            ->whereIn('employment_type', $employmentTypes)
            ->get()
            ->groupBy('employment_type')
            ->map(fn (SupportCollection $rows) => $rows->keyBy('leave_type'));
    }

    private function getHolidayCalendarsLookup(Carbon $startDate, Carbon $endDate): SupportCollection
    {
        return HolidayCalendar::query()
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->get();
    }

    /**
     * @param  array{
     *   attendance_date_lookup_by_employee: SupportCollection<int, array<string, bool>>,
     *   pending_lookup: array<int, bool>,
     *   sick_proof_lookup: array<int, bool>,
     *   mismatch_lookup: array<int, bool>,
     *   approved_leaves_by_employee: SupportCollection<int, SupportCollection<int, LeaveRequest>>,
     *   entitlements_by_employment_type: SupportCollection<string, SupportCollection<string, LeaveEntitlement>>,
     *   holiday_calendars: SupportCollection<int, HolidayCalendar>
     * }  $lookups
     * @return array{
     *   row: array<string, mixed>,
     *   status: string,
     *   staff_member_id: int,
     *   blocker_reasons: array<int, string>,
     *   warning_flags: array<int, string>
     * }
     */
    private function buildEmployeeReadinessRow(StaffMemberProfile $employee, Carbon $startDate, Carbon $endDate, array $lookups): array
    {
        $employeeId = (int) $employee->id;
        $employmentType = AttendanceHelper::normalizeEmploymentType((string) ($employee->jobInformation?->employment_type ?? 'full_time'));

        $scheduledWeekdays = $this->resolveScheduledWeekdays($employee, $employmentType);
        $scheduledDateLookup = $this->resolveScheduledDateLookup($scheduledWeekdays, $startDate, $endDate);
        $holidayDateLookup = $this->resolveHolidayDateLookup(
            $lookups['holiday_calendars'],
            $employmentType,
            $scheduledDateLookup
        );
        $scheduledWorkingDateLookup = array_diff_key($scheduledDateLookup, $holidayDateLookup);

        $attendanceDateLookup = array_intersect_key(
            (array) ($lookups['attendance_date_lookup_by_employee']->get($employeeId, [])),
            $scheduledWorkingDateLookup
        );

        [$validLeaveCoverageDateLookup, $invalidLeaveCount] = $this->resolveLeaveCoverageAndInvalidCountForEmployee(
            $lookups['approved_leaves_by_employee']->get($employeeId, collect()),
            $lookups['entitlements_by_employment_type']->get($employmentType, collect()),
            $scheduledWorkingDateLookup,
            $startDate,
            $endDate
        );

        $coveredDateLookup = $attendanceDateLookup + $validLeaveCoverageDateLookup;
        $noCoverageDays = max(0, count($scheduledWorkingDateLookup) - count($coveredDateLookup));

        $employeeBlockedReasons = [];

        if (isset($lookups['pending_lookup'][$employeeId])) {
            $employeeBlockedReasons[] = 'pending_leave_approval';
        }

        if (isset($lookups['sick_proof_lookup'][$employeeId])) {
            $employeeBlockedReasons[] = 'sick_proof_unresolved';
        }

        if ($noCoverageDays > 0) {
            $employeeBlockedReasons[] = 'missing_attendance_or_valid_leave';
        }

        if ($invalidLeaveCount > 0) {
            $employeeBlockedReasons[] = 'invalid_leave_entitlement';
        }

        $fairnessSummary = $this->attendanceClassifier->summarizePeriod(
            $employeeId,
            $startDate,
            $endDate
        );

        $employeeWarningFlags = $this->resolveReadinessWarningFlags(
            $fairnessSummary,
            isset($lookups['mismatch_lookup'][$employeeId])
        );

        $status = ! empty($employeeBlockedReasons)
            ? 'blocked'
            : (! empty($employeeWarningFlags) ? 'warning' : 'ready');

        $attendanceSearch = $employee->code ?: ($employee->user?->name ?? '');
        $query = http_build_query([
            'search' => $attendanceSearch,
            'date' => $startDate->toDateString(),
        ]);

        return [
            'row' => [
                'staff_member_id' => $employeeId,
                'employee_code' => $employee->code,
                'employee_name' => $employee->user?->name ?? 'Unknown employee',
                'team_name' => $employee->jobInformation?->team?->name,
                'status' => $status,
                'blocker_reasons' => array_values($employeeBlockedReasons),
                'warning_flags' => array_values($employeeWarningFlags),
                'metrics' => [
                    'scheduled_working_days' => count($scheduledWorkingDateLookup),
                    'covered_days' => count($coveredDateLookup),
                    'no_coverage_days' => $noCoverageDays,
                    'present_days' => (int) ($fairnessSummary['present_days'] ?? 0),
                    'late_days' => (int) ($fairnessSummary['late_days'] ?? 0),
                    'half_day_count' => (int) ($fairnessSummary['half_day_count'] ?? 0),
                    'paid_leave_days' => (int) ($fairnessSummary['paid_leave_days'] ?? 0),
                    'unpaid_leave_days' => (int) ($fairnessSummary['unpaid_leave_days'] ?? 0),
                    'absent_days' => (int) ($fairnessSummary['absent_days'] ?? 0),
                    'invalid_leave_count' => $invalidLeaveCount,
                ],
                'attendance_workspace_url' => '/admin/attendances'.($query ? '?'.$query : ''),
            ],
            'status' => $status,
            'staff_member_id' => $employeeId,
            'blocker_reasons' => array_values($employeeBlockedReasons),
            'warning_flags' => array_values($employeeWarningFlags),
        ];
    }

    private function appendReadinessEmployeeBuckets(
        int $employeeId,
        array $employeeBlockedReasons,
        array $employeeWarningFlags,
        array &$blockedReasons,
        array &$warningFlags
    ): void {
        foreach ($employeeBlockedReasons as $reason) {
            if (! array_key_exists($reason, $blockedReasons)) {
                continue;
            }

            $blockedReasons[$reason][] = $employeeId;
        }

        foreach ($employeeWarningFlags as $flag) {
            if (! array_key_exists($flag, $warningFlags)) {
                continue;
            }

            $warningFlags[$flag][] = $employeeId;
        }
    }

    private function normalizeReadinessBuckets(array $blockedEmployeeIds, array $blockedReasons, array $warningFlags): array
    {
        $normalizedBlockedEmployeeIds = array_values(array_unique($blockedEmployeeIds));
        sort($normalizedBlockedEmployeeIds);

        foreach ($blockedReasons as $reason => $employeeIdList) {
            $blockedReasons[$reason] = array_values(array_unique($employeeIdList));
            sort($blockedReasons[$reason]);
        }

        foreach ($warningFlags as $flag => $employeeIdList) {
            $warningFlags[$flag] = array_values(array_unique($employeeIdList));
            sort($warningFlags[$flag]);
        }

        return [
            'blocked_staff_member_ids' => $normalizedBlockedEmployeeIds,
            'blocked_reasons' => $blockedReasons,
            'warning_flags' => $warningFlags,
        ];
    }

    private function resolveScheduledWeekdays(StaffMemberProfile $employee, string $employmentType): array
    {
        $policyWeekdays = $employee->jobInformation?->attendancePolicy?->default_working_weekdays;

        if (is_array($policyWeekdays) && ! empty($policyWeekdays)) {
            return array_values(array_map(fn ($day) => strtolower((string) $day), $policyWeekdays));
        }

        return AttendanceHelper::DEFAULT_WORKING_WEEKDAYS_BY_EMPLOYMENT_TYPE[$employmentType]
            ?? AttendanceHelper::DEFAULT_WORKING_WEEKDAYS_BY_EMPLOYMENT_TYPE['full_time'];
    }

    private function resolveScheduledDateLookup(array $scheduledWeekdays, Carbon $startDate, Carbon $endDate): array
    {
        $weekdaysLookup = array_fill_keys($scheduledWeekdays, true);
        $lookup = [];

        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $weekday = strtolower($cursor->englishDayOfWeek);
            if (isset($weekdaysLookup[$weekday])) {
                $lookup[$cursor->toDateString()] = true;
            }
            $cursor->addDay();
        }

        return $lookup;
    }

    private function resolveHolidayDateLookup(SupportCollection $holidayCalendars, string $employmentType, array $scheduledDateLookup): array
    {
        if (empty($scheduledDateLookup) || $holidayCalendars->isEmpty()) {
            return [];
        }

        $holidayLookup = [];

        foreach ($holidayCalendars as $holiday) {
            /** @var HolidayCalendar $holiday */
            $dateKey = $holiday->date ? Carbon::parse($holiday->date)->toDateString() : null;

            if (! $dateKey || ! isset($scheduledDateLookup[$dateKey])) {
                continue;
            }

            $appliesTo = $holiday->applies_to;
            if ($appliesTo === null || (is_array($appliesTo) && in_array($employmentType, $appliesTo, true))) {
                $holidayLookup[$dateKey] = true;
            }
        }

        return $holidayLookup;
    }

    private function resolveLeaveCoverageAndInvalidCountForEmployee(
        SupportCollection $approvedLeaves,
        SupportCollection $entitlementsByLeaveType,
        array $scheduledWorkingDateLookup,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        if (empty($scheduledWorkingDateLookup) || $approvedLeaves->isEmpty()) {
            return [[], 0];
        }

        $validCoverageDateLookup = [];
        $invalidLeaveCount = 0;

        foreach ($approvedLeaves as $leave) {
            /** @var LeaveRequest $leave */
            $leaveStart = Carbon::parse($leave->start_date)->startOfDay();
            $leaveEnd = Carbon::parse($leave->end_date)->endOfDay();

            if ($leaveStart->gt($endDate) || $leaveEnd->lt($startDate)) {
                continue;
            }

            if ($leaveStart->lt($startDate)) {
                $leaveStart = $startDate->copy();
            }

            if ($leaveEnd->gt($endDate)) {
                $leaveEnd = $endDate->copy();
            }

            $leaveCoverageLookup = [];
            $cursor = $leaveStart->copy();

            while ($cursor->lte($leaveEnd)) {
                $dateKey = $cursor->toDateString();
                if (isset($scheduledWorkingDateLookup[$dateKey])) {
                    $leaveCoverageLookup[$dateKey] = true;
                }
                $cursor->addDay();
            }

            if (empty($leaveCoverageLookup)) {
                continue;
            }

            $leaveType = $this->resolveLeaveTypeValue($leave);
            $entitlement = $entitlementsByLeaveType->get($leaveType);
            $leaveWorkingDays = count($leaveCoverageLookup);

            if ($this->isApprovedLeavePayrollValidForReadiness($leave, $entitlement, $leaveWorkingDays)) {
                $validCoverageDateLookup += $leaveCoverageLookup;
            } else {
                $invalidLeaveCount++;
            }
        }

        return [$validCoverageDateLookup, $invalidLeaveCount];
    }

    private function resolveLeaveTypeValue(LeaveRequest $leaveRequest): string
    {
        $leaveType = $leaveRequest->leave_type;

        if ($leaveType instanceof \BackedEnum) {
            return (string) $leaveType->value;
        }

        return (string) $leaveType;
    }

    private function isApprovedLeavePayrollValidForReadiness(
        LeaveRequest $leaveRequest,
        ?LeaveEntitlement $entitlement,
        int $leaveWorkingDays
    ): bool {
        if (! $entitlement || ! $entitlement->is_eligible) {
            return false;
        }

        $leaveType = $this->resolveLeaveTypeValue($leaveRequest);

        if ($leaveType === 'sick_leave' && ! $this->hasApprovedSickProofForReadiness($leaveRequest)) {
            return false;
        }

        if ($leaveType === 'emergency_leave' && trim((string) $leaveRequest->reason) === '') {
            return false;
        }

        if ($entitlement->quota_scope === 'per_occurrence' && $entitlement->quota_days !== null) {
            return $leaveWorkingDays <= (float) $entitlement->quota_days;
        }

        if ($entitlement->quota_scope === 'annual' && $entitlement->quota_days !== null) {
            $requestedDays = (float) ($leaveRequest->total_days ?? $leaveWorkingDays);

            return $requestedDays <= (float) $entitlement->quota_days;
        }

        return true;
    }

    private function hasApprovedSickProofForReadiness(LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->proof_file_path !== null
            && $leaveRequest->proof_file_name !== null
            && $leaveRequest->proof_mime_type !== null
            && $leaveRequest->proof_size_kb !== null
            && $leaveRequest->proof_uploaded_at !== null
            && $leaveRequest->proof_review_status === 'approved';
    }

    private function resolveReadinessWarningFlags(array $fairnessSummary, bool $hasUnresolvedPolicyMismatch): array
    {
        $warningFlags = collect($fairnessSummary['warning_flags'] ?? [])
            ->filter(fn ($flag) => is_string($flag) && trim($flag) !== '')
            ->values();

        if ($hasUnresolvedPolicyMismatch && ! $warningFlags->contains('unresolved_policy_mismatch')) {
            $warningFlags->push('unresolved_policy_mismatch');
        }

        $effectiveWorkingDays = max(1, (int) ($fairnessSummary['effective_working_days'] ?? 0));
        $lateRatio = ((int) ($fairnessSummary['late_days'] ?? 0)) / $effectiveWorkingDays;
        $halfDayRatio = ((int) ($fairnessSummary['half_day_count'] ?? 0)) / $effectiveWorkingDays;

        if ($lateRatio >= self::HIGH_LATE_TREND_RATIO) {
            $warningFlags->push('high_late_trend');
        }

        if ($halfDayRatio >= self::HIGH_HALF_DAY_TREND_RATIO) {
            $warningFlags->push('high_half_day_trend');
        }

        return $warningFlags->unique()->values()->all();
    }

    private function buildPayrollReconciliationPayload(Payroll $payroll, array $filters = []): array
    {
        $payroll->loadMissing([
            'payrollDetails.staffMember.user',
            'payrollDetails.staffMember.bankInformation',
        ]);

        $resolutions = PayrollReconciliationResolution::query()
            ->where('payroll_id', $payroll->id)
            ->with('resolvedByUser')
            ->get()
            ->groupBy(fn (PayrollReconciliationResolution $r) => $r->staff_member_id.'|'.$r->exception_type);

        $exceptions = [];
        $criticalEmployeeIds = [];
        $warningEmployeeIds = [];

        foreach ($payroll->payrollDetails as $payrollDetail) {
            /** @var PayrollDetail $payrollDetail */
            $employee = $payrollDetail->staffMember;
            $employeeId = (int) $payrollDetail->staff_member_id;
            $employeeName = $employee?->user?->name ?? 'Unknown employee';
            $employeeCode = $employee?->code;

            $missingBankFields = $this->resolveMissingBankFields($employee?->bankInformation);
            if (! empty($missingBankFields)) {
                $criticalEmployeeIds[] = $employeeId;
                $exceptions[] = [
                    'staff_member_id' => $employeeId,
                    'employee_name' => $employeeName,
                    'employee_code' => $employeeCode,
                    'severity' => 'critical',
                    'type' => 'missing_bank_account',
                    'message' => 'Employee bank account information is incomplete.',
                    'metadata' => [
                        'missing_fields' => $missingBankFields,
                    ],
                ];
            }

            if ((float) $payrollDetail->final_salary <= 0) {
                $criticalEmployeeIds[] = $employeeId;
                $exceptions[] = [
                    'staff_member_id' => $employeeId,
                    'employee_name' => $employeeName,
                    'employee_code' => $employeeCode,
                    'severity' => 'critical',
                    'type' => 'zero_salary',
                    'message' => 'Employee final salary is zero or negative after deductions.',
                    'metadata' => [
                        'original_salary' => (float) $payrollDetail->original_salary,
                        'final_salary' => (float) $payrollDetail->final_salary,
                        'deduction_amount' => (float) $payrollDetail->deduction_amount,
                    ],
                ];
            }

            $originalSalary = (float) $payrollDetail->original_salary;
            $finalSalary = (float) $payrollDetail->final_salary;
            if ($originalSalary > 0 && $finalSalary > 0 && $finalSalary < ($originalSalary * 0.5)) {
                $warningEmployeeIds[] = $employeeId;
                $exceptions[] = [
                    'staff_member_id' => $employeeId,
                    'employee_name' => $employeeName,
                    'employee_code' => $employeeCode,
                    'severity' => 'warning',
                    'type' => 'salary_decrease_anomaly',
                    'message' => sprintf(
                        'Final salary is only %.1f%% of original salary, which may indicate an anomaly.',
                        ($finalSalary / $originalSalary) * 100
                    ),
                    'metadata' => [
                        'original_salary' => $originalSalary,
                        'final_salary' => $finalSalary,
                        'ratio' => round($finalSalary / $originalSalary, 4),
                    ],
                ];
            }

            $deductionRatio = $this->resolveDeductionRatio($payrollDetail);
            if ($deductionRatio > self::DEDUCTION_WARNING_RATIO) {
                $warningEmployeeIds[] = $employeeId;
                $exceptions[] = [
                    'staff_member_id' => $employeeId,
                    'employee_name' => $employeeName,
                    'employee_code' => $employeeCode,
                    'severity' => 'warning',
                    'type' => 'excessive_deduction',
                    'message' => sprintf(
                        'Deduction is %.1f%% of original salary and exceeds the 50%% warning threshold.',
                        $deductionRatio * 100
                    ),
                    'metadata' => [
                        'deduction_ratio' => round($deductionRatio, 4),
                        'threshold_ratio' => self::DEDUCTION_WARNING_RATIO,
                    ],
                ];
            }

            $warningFlags = collect($payrollDetail->warning_flags ?? [])
                ->filter(fn ($flag) => is_string($flag) && trim($flag) !== '')
                ->values();

            foreach ($warningFlags as $flag) {
                $warningEmployeeIds[] = $employeeId;
                $exceptions[] = [
                    'staff_member_id' => $employeeId,
                    'employee_name' => $employeeName,
                    'employee_code' => $employeeCode,
                    'severity' => 'warning',
                    'type' => 'attendance_warning_flag',
                    'message' => sprintf(
                        'Attendance warning flag detected: %s.',
                        ucwords(str_replace('_', ' ', $flag))
                    ),
                    'metadata' => [
                        'flag' => $flag,
                    ],
                ];
            }
        }

        // Attach resolution info to each exception
        $exceptions = array_map(function (array $exception) use ($resolutions) {
            $key = $exception['staff_member_id'].'|'.$exception['type'];
            $resolution = $resolutions->get($key)?->first();

            $exception['resolution'] = $resolution ? [
                'action' => $resolution->resolution_action,
                'reason' => $resolution->reason,
                'resolved_by_name' => $resolution->resolvedByUser?->name ?? 'Unknown',
                'resolved_at' => $resolution->created_at?->toIso8601String(),
            ] : null;

            return $exception;
        }, $exceptions);

        $totalExceptionCount = count($exceptions);
        $criticalCount = count(array_filter($exceptions, fn ($exception) => $exception['severity'] === 'critical'));
        $warningCount = count(array_filter($exceptions, fn ($exception) => $exception['severity'] === 'warning'));
        $unresolvedCriticalCount = count(array_filter(
            $exceptions,
            fn ($exception) => $exception['severity'] === 'critical' && $exception['resolution'] === null
        ));

        $normalizedFilters = $this->normalizeReconciliationFilters($filters);
        $filteredExceptions = $this->filterReconciliationExceptions($exceptions, $normalizedFilters);
        $filteredCriticalCount = count(array_filter($filteredExceptions, fn ($exception) => $exception['severity'] === 'critical'));
        $filteredWarningCount = count(array_filter($filteredExceptions, fn ($exception) => $exception['severity'] === 'warning'));
        $availableTypes = array_values(array_unique(array_map(
            fn ($exception) => (string) ($exception['type'] ?? ''),
            $exceptions
        )));
        sort($availableTypes);

        $criticalEmployeeIds = array_values(array_unique(array_filter($criticalEmployeeIds)));
        sort($criticalEmployeeIds);

        $warningEmployeeIds = array_values(array_unique(array_filter($warningEmployeeIds)));
        sort($warningEmployeeIds);

        return [
            'payroll_id' => (int) $payroll->id,
            'salary_month' => $payroll->salary_month
                ? Carbon::parse($payroll->salary_month)->toDateString()
                : null,
            'status' => $payroll->status,
            'summary' => [
                'total_employees' => $payroll->payrollDetails->count(),
                'total_exception_count' => $totalExceptionCount,
                'filtered_exception_count' => count($filteredExceptions),
                'critical_count' => $criticalCount,
                'unresolved_critical_count' => $unresolvedCriticalCount,
                'warning_count' => $warningCount,
                'filtered_critical_count' => $filteredCriticalCount,
                'filtered_warning_count' => $filteredWarningCount,
                'critical_employee_count' => count($criticalEmployeeIds),
                'warning_employee_count' => count($warningEmployeeIds),
                'critical_staff_member_ids' => $criticalEmployeeIds,
                'warning_staff_member_ids' => $warningEmployeeIds,
            ],
            'available_types' => array_values(array_filter($availableTypes)),
            'applied_filters' => $normalizedFilters,
            'exceptions' => array_values($filteredExceptions),
        ];
    }

    private function normalizeReconciliationFilters(array $filters): array
    {
        $severity = $filters['severity'] ?? null;
        if (! is_string($severity) || ! in_array($severity, ['critical', 'warning'], true)) {
            $severity = null;
        }

        $type = $filters['type'] ?? null;
        if (! is_string($type) || trim($type) === '') {
            $type = null;
        } else {
            $type = strtolower(trim($type));
        }

        return [
            'severity' => $severity,
            'type' => $type,
        ];
    }

    private function filterReconciliationExceptions(array $exceptions, array $filters): array
    {
        return array_values(array_filter($exceptions, function (array $exception) use ($filters): bool {
            if (($filters['severity'] ?? null) !== null && ($exception['severity'] ?? null) !== $filters['severity']) {
                return false;
            }

            if (($filters['type'] ?? null) !== null) {
                $exceptionType = strtolower((string) ($exception['type'] ?? ''));

                if ($exceptionType !== $filters['type']) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function resolveMissingBankFields($bankInformation): array
    {
        if (! $bankInformation) {
            return ['bank_name', 'account_number', 'account_holder_name'];
        }

        $requiredFields = ['bank_name', 'account_number', 'account_holder_name'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            $value = $bankInformation->{$field} ?? null;

            if (! is_string($value) || trim($value) === '') {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }

    private function resolveDeductionRatio(PayrollDetail $payrollDetail): float
    {
        $originalSalary = (float) ($payrollDetail->original_salary ?? 0);
        if ($originalSalary <= 0) {
            return 0.0;
        }

        $deductionAmount = (float) ($payrollDetail->deduction_amount ?? 0);
        if ($deductionAmount <= 0) {
            $deductionAmount = max(0, round($originalSalary - (float) ($payrollDetail->final_salary ?? 0), 2));
        }

        return $deductionAmount / $originalSalary;
    }

    private function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $workingDays = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            if (! $currentDate->isWeekend()) {
                $workingDays++;
            }
            $currentDate->addDay();
        }

        return $workingDays;
    }

    private function resolveWorkingDays(PayrollSetting|PayrollSettingVersion $settings, Carbon $startDate, Carbon $endDate): int
    {
        if ($settings->working_days_mode === 'fixed' && (int) $settings->default_working_days > 0) {
            return (int) $settings->default_working_days;
        }

        return $this->calculateWorkingDays($startDate, $endDate);
    }

    private function applyRounding(float $amount, string $mode, int $unit): float
    {
        if ($mode === 'none' || $unit <= 1) {
            return round($amount, 2);
        }

        $scaled = $amount / $unit;

        $rounded = match ($mode) {
            'floor' => floor($scaled),
            'ceil' => ceil($scaled),
            default => round($scaled),
        };

        return (float) ($rounded * $unit);
    }

    private function buildPayrollNote(PayrollSetting|PayrollSettingVersion $settings, array $context): string
    {
        $template = $settings->note_template ?: PayrollSetting::DEFAULT_NOTE_TEMPLATE;

        return strtr($template, [
            '{working_days}' => (string) $context['working_days'],
            '{attended_days}' => (string) $context['attended_days'],
            '{late_days}' => (string) $context['late_days'],
            '{sick_days}' => (string) $context['sick_days'],
            '{permission_days}' => (string) $context['permission_days'],
            '{absent_days}' => (string) $context['absent_days'],
            '{deduction}' => number_format((float) $context['deduction'], 0, ',', '.'),
        ]);
    }

    /**
     * Determine if a QueryException is caused by a lock contention (NOWAIT conflict).
     * Checks SQLSTATE code for lock-related errors across MySQL drivers.
     */
    private function isLockContentionError(QueryException $e): bool
    {
        $code = $e->getCode();
        // SQLSTATE 40001 = serialization failure (deadlock / lock timeout)
        if ($code === '40001') {
            return true;
        }
        // MySQL NOWAIT-specific: error 3572 (Statement aborted because lock(s) could not be acquired immediately)
        $previous = $e->getPrevious();
        if ($previous && str_contains($previous->getMessage(), 'lock(s) could not be acquired immediately')) {
            return true;
        }
        // SQLite: NOWAIT not supported but catch the pattern for test environments
        if ($code === 'HY000' && str_contains($e->getMessage(), 'database is locked')) {
            return true;
        }
        return false;
    }

    private function applyApprovedAdjustmentsToPayroll(Payroll $payroll, AttendancePeriod $targetPeriod): int
    {
        $payrollDetails = PayrollDetail::query()
            ->select(['id', 'staff_member_id', 'original_salary', 'final_salary'])
            ->where('payroll_id', $payroll->id)
            ->get();

        if ($payrollDetails->isEmpty()) {
            return 0;
        }

        $employeeIds = $payrollDetails
            ->pluck('staff_member_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $approvedAdjustments = PayrollAdjustment::query()
            ->approvedForTargetPeriod((int) $targetPeriod->id)
            ->whereIn('staff_member_id', $employeeIds)
            ->orderBy('id')
            ->get();

        if ($approvedAdjustments->isEmpty()) {
            return 0;
        }

        $approvedAdjustmentsByEmployee = $approvedAdjustments->groupBy('staff_member_id');
        $appliedAdjustmentIds = [];

        foreach ($payrollDetails as $payrollDetail) {
            /** @var PayrollDetail $payrollDetail */
            $employeeAdjustments = $approvedAdjustmentsByEmployee->get((int) $payrollDetail->staff_member_id, collect());

            if ($employeeAdjustments->isEmpty()) {
                continue;
            }

            $totalAmountDelta = round((float) $employeeAdjustments->sum(function (PayrollAdjustment $adjustment) {
                return (float) $adjustment->amount_delta;
            }), 2);

            if ($totalAmountDelta !== 0.0) {
                $adjustedFinalSalary = max(
                    0,
                    round((float) $payrollDetail->final_salary + $totalAmountDelta, 2)
                );

                $payrollDetail->update([
                    'final_salary' => $adjustedFinalSalary,
                ]);
            }

            $appliedAdjustmentIds = array_merge(
                $appliedAdjustmentIds,
                $employeeAdjustments->pluck('id')->all()
            );
        }

        if (! empty($appliedAdjustmentIds)) {
            PayrollAdjustment::query()
                ->whereIn('id', array_values(array_unique($appliedAdjustmentIds)))
                ->update([
                    'status' => PayrollAdjustment::STATUS_APPLIED,
                    'updated_at' => now(),
                ]);
        }

        return count(array_unique($appliedAdjustmentIds));
    }
}
