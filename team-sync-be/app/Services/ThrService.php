<?php

namespace App\Services;

use App\Interfaces\ThrPayrollRepositoryInterface;
use App\Models\ThrPayroll;
use App\Models\User;
use App\Notifications\ThrPaymentNotification;
use App\Services\Payroll\ThrCalculationService;
use App\Services\Payroll\PayrollTaxIntegrationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ThrService
{
    public function __construct(
        private readonly ThrPayrollRepositoryInterface $repository,
        private readonly ThrCalculationService $calculationService,
        private readonly PayrollTaxIntegrationService $taxIntegration,
    ) {}

    public function getAllPaginated(?int $year, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getAllPaginated($year, $status, $perPage);
    }

    public function getById(int $id): ThrPayroll
    {
        return $this->repository->getById($id);
    }

    public function getDetails(int $thrPayrollId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getDetails($thrPayrollId, $perPage);
    }

    public function getYearSummary(int $year): array
    {
        return $this->repository->getYearSummary($year);
    }

    /**
     * Generate THR payroll for a specific religion event.
     *
     * @return array{success: bool, message: string, thr_payroll: ?ThrPayroll}
     */
    public function generate(array $validated, User $creator): array
    {
        $religionEvent = $validated['religion_event'];
        $year = (int) $validated['year'];
        $holidayDate = Carbon::parse($validated['religion_holiday_date']);
        $paymentDeadline = $this->calculationService->calculatePaymentDeadline($holidayDate);

        // Check if already exists
        $existing = $this->repository->getByYearAndEvent($year, $religionEvent);
        if ($existing) {
            return [
                'success' => false,
                'message' => 'THR for '.ThrPayroll::eventLabel($religionEvent)." {$year} already exists (ID: {$existing->id}, Status: {$existing->status})",
                'thr_payroll' => null,
            ];
        }

        // Get eligible employees
        $employees = $this->calculationService->getEligibleEmployees($religionEvent);

        if ($employees->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No eligible employees found for '.ThrPayroll::eventLabel($religionEvent),
                'thr_payroll' => null,
            ];
        }

        return $this->taxIntegration->transaction(function () use ($religionEvent, $year, $holidayDate, $paymentDeadline, $employees, $creator, $validated) {
            $dueDate = isset($validated['income_due_date']) ? Carbon::parse($validated['income_due_date']) : $holidayDate->copy()->subDays(10);
            // Create THR payroll record
            $thrPayroll = $this->repository->create([
                'year' => $year,
                'religion_event' => $religionEvent,
                'religion_holiday_date' => $holidayDate,
                'payment_deadline' => $paymentDeadline,
                'income_due_date' => $dueDate,
                'planned_credit_date' => $validated['planned_credit_date'] ?? null,
                'status' => ThrPayroll::STATUS_PROCESSING,
                'created_by' => $creator->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Calculate THR for each employee
            $details = [];
            $skipped = 0;

            foreach ($employees as $employee) {
                $result = $this->calculationService->calculateForEmployee($employee, $holidayDate);

                if (! $result['eligible']) {
                    $skipped++;

                    continue;
                }

                $details[] = [
                    'staff_member_id' => $employee->id,
                    'religion' => $employee->religion,
                    'monthly_salary' => $employee->jobInformation->monthly_salary,
                    'join_date' => $employee->jobInformation->start_date,
                    'tenure_months' => $result['tenure_months'],
                    'proration_factor' => $result['proration_factor'],
                    'gross_thr_amount' => $result['gross_thr_amount'],
                    'pph21_amount' => $result['pph21_amount'],
                    'net_thr_amount' => $result['net_thr_amount'],
                    'ptkp_status' => $employee->ptkp_status,
                    'has_npwp' => ! empty($employee->npwp),
                    'tax_calculation_meta' => json_encode($result['tax_calculation_meta']),
                ];
            }

            if (empty($details)) {
                // Rollback — no eligible employees after calculation
                $thrPayroll->delete();

                return [
                    'success' => false,
                    'message' => "All {$employees->count()} employees were ineligible (skipped: {$skipped})",
                    'thr_payroll' => null,
                ];
            }

            // Bulk insert details
            $this->repository->bulkCreateDetails($thrPayroll->id, $details);
            $this->taxIntegration->refreshThr($thrPayroll);

            // Update totals and status
            $this->repository->updateTotals($thrPayroll);
            $thrPayroll = $this->repository->updateStatus($thrPayroll, ThrPayroll::STATUS_PENDING);

            return [
                'success' => true,
                'message' => "THR generated successfully. {$thrPayroll->total_employees} employees processed, {$skipped} skipped.",
                'thr_payroll' => $thrPayroll,
            ];
        });
    }

    /**
     * Approve THR payroll.
     *
     * @return array{success: bool, message: string, thr_payroll: ?ThrPayroll}
     */
    public function approve(int $id, User $approver): array
    {
        return $this->taxIntegration->transaction(function () use ($id, $approver) {
        $thrPayroll = ThrPayroll::whereKey($id)->lockForUpdate()->firstOrFail();

        if ($thrPayroll->status !== ThrPayroll::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => 'Only pending THR payrolls can be approved',
                'thr_payroll' => null,
            ];
        }

        $this->taxIntegration->refreshThr($thrPayroll);
        $thrPayroll = $this->repository->updateStatus($thrPayroll, ThrPayroll::STATUS_APPROVED, [
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'THR payroll approved successfully',
            'thr_payroll' => $thrPayroll,
        ];
        });
    }

    /**
     * Mark THR as paid and notify employees.
     *
     * @return array{success: bool, message: string, thr_payroll: ?ThrPayroll}
     */
    public function markAsPaid(int $id, string $paymentDate, User $actor): array
    {
        return $this->taxIntegration->transaction(function () use ($id, $paymentDate, $actor) {
        $thrPayroll = ThrPayroll::whereKey($id)->lockForUpdate()->firstOrFail();

        if ($thrPayroll->status !== ThrPayroll::STATUS_APPROVED) {
            return [
                'success' => false,
                'message' => 'Only approved THR payrolls can be marked as paid',
                'thr_payroll' => null,
            ];
        }

        $settled = $this->taxIntegration->settleThr($thrPayroll, $paymentDate);
        $thrPayroll->refresh();
        if (! $settled || $thrPayroll->status !== ThrPayroll::STATUS_APPROVED || ! $thrPayroll->approved_by || ! $thrPayroll->approved_at) {
            return [
                'success' => false,
                'message' => 'Settlement changed the approved calculation. Approve the recalculated THR before retrying payment.',
                'thr_payroll' => $thrPayroll,
            ];
        }
        $thrPayroll = $this->repository->updateStatus($thrPayroll, ThrPayroll::STATUS_PAID, [
            'payment_date' => $paymentDate,
        ]);

        // Notify all employees
        DB::afterCommit(fn () => $this->notifyEmployees($thrPayroll));

        return [
            'success' => true,
            'message' => 'THR marked as paid. Notifications sent to employees.',
            'thr_payroll' => $thrPayroll,
        ];
        });
    }

    /**
     * Reopen an approved/paid THR payroll back to pending for correction.
     *
     * @return array{success: bool, message: string, thr_payroll: ?ThrPayroll}
     */
    public function reopen(int $id, User $actor): array
    {
        return $this->taxIntegration->transaction(function () use ($id, $actor) {
        $thrPayroll = ThrPayroll::whereKey($id)->lockForUpdate()->firstOrFail();

        if ($thrPayroll->status === ThrPayroll::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => 'THR payroll is already in pending status',
                'thr_payroll' => null,
            ];
        }

        if ($thrPayroll->status === ThrPayroll::STATUS_PROCESSING) {
            return [
                'success' => false,
                'message' => 'Processing THR payroll cannot be reopened',
                'thr_payroll' => null,
            ];
        }

        if ($thrPayroll->status !== ThrPayroll::STATUS_APPROVED) {
            return [
                'success' => false,
                'message' => 'Only unpaid approved THR can be reopened; paid history requires a separate sourced correction',
                'thr_payroll' => null,
            ];
        }

        $thrPayroll = $this->repository->updateStatus($thrPayroll, ThrPayroll::STATUS_PENDING, [
            'approved_by' => null,
            'approved_at' => null,
            'payment_date' => null,
        ]);

        return [
            'success' => true,
            'message' => 'THR payroll reopened for correction successfully',
            'thr_payroll' => $thrPayroll,
        ];
        });
    }

    /**
     * Get simulation/preview of THR generation without persisting.
     */
    public function simulate(string $religionEvent, int $year, string $holidayDate): array
    {
        $holiday = Carbon::parse($holidayDate);
        $paymentDeadline = $this->calculationService->calculatePaymentDeadline($holiday);
        $employees = $this->calculationService->getEligibleEmployees($religionEvent);

        $eligible = [];
        $ineligible = [];
        $totalGross = 0;

        foreach ($employees as $employee) {
            $result = $this->calculationService->calculateForEmployee($employee, $holiday);

            if ($result['eligible']) {
                $eligible[] = [
                    'staff_member_id' => $employee->id,
                    'name' => $employee->user?->name ?? $employee->full_name,
                    'religion' => $employee->religion,
                    'monthly_salary' => (float) $employee->jobInformation->monthly_salary,
                    'tenure_months' => $result['tenure_months'],
                    'proration_factor' => $result['proration_factor'],
                    'gross_thr_amount' => $result['gross_thr_amount'],
                    'pph21_amount' => null,
                    'net_thr_amount' => null,
                ];
                $totalGross += $result['gross_thr_amount'];
            } else {
                $ineligible[] = [
                    'staff_member_id' => $employee->id,
                    'name' => $employee->user?->name ?? $employee->full_name,
                    'reason' => $result['ineligibility_reason'],
                ];
            }
        }

        return [
            'calculation_status' => 'gross_only',
            'calculation_notice' => 'Gross entitlement preview only. Tax and net pay require generated source IDs and complete period history.',
            'religion_event' => $religionEvent,
            'event_label' => ThrPayroll::eventLabel($religionEvent),
            'year' => $year,
            'religion_holiday_date' => $holiday->format('Y-m-d'),
            'payment_deadline' => $paymentDeadline->format('Y-m-d'),
            'eligible_count' => count($eligible),
            'ineligible_count' => count($ineligible),
            'total_gross_amount' => round($totalGross, 0),
            'total_tax_amount' => null,
            'total_net_amount' => null,
            'eligible_employees' => $eligible,
            'ineligible_employees' => $ineligible,
        ];
    }

    private function notifyEmployees(ThrPayroll $thrPayroll): void
    {
        $details = $thrPayroll->details()->with('staffMember.user')->get();

        foreach ($details as $detail) {
            $user = $detail->staffMember?->user;
            if ($user) {
                $user->notify(new ThrPaymentNotification($thrPayroll, $detail));
            }
        }
    }
}
