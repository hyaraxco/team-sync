<?php

namespace Tests\Unit\Services;

use App\Interfaces\ThrPayrollRepositoryInterface;
use App\Models\JobInformation;
use App\Models\StaffMemberProfile;
use App\Models\ThrPayroll;
use App\Models\User;
use App\Services\Payroll\ThrCalculationService;
use App\Services\ThrService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ThrServiceTest extends TestCase
{
    use RefreshDatabase;

    private ThrService $service;

    private ThrPayrollRepositoryInterface $repository;

    private ThrCalculationService $calculationService;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        Role::create(['name' => 'staff', 'guard_name' => 'sanctum']);

        $this->repository = $this->createMock(ThrPayrollRepositoryInterface::class);
        $this->calculationService = $this->createMock(ThrCalculationService::class);

        $this->service = new ThrService($this->repository, $this->calculationService);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Generate
    // ─────────────────────────────────────────────────────────────────────────

    public function test_generate_creates_thr_payroll_with_eligible_employees(): void
    {
        $creator = User::factory()->create();
        $employee = StaffMemberProfile::factory()->create();

        JobInformation::factory()->active()->create([
            'staff_member_id' => $employee->id,
            'start_date' => Carbon::now()->subMonths(12)->toDateString(),
            'monthly_salary' => 10_000_000,
        ]);

        $validated = [
            'religion_event' => ThrPayroll::EVENT_IDUL_FITRI,
            'year' => 2026,
            'religion_holiday_date' => '2026-06-15',
            'notes' => 'THR for Idul Fitri',
        ];

        $this->calculationService
            ->method('calculatePaymentDeadline')
            ->willReturn(Carbon::parse('2026-06-08'));

        $this->repository
            ->method('getByYearAndEvent')
            ->willReturn(null);

        $this->calculationService
            ->method('getEligibleEmployees')
            ->willReturn(new EloquentCollection([$employee]));

        $this->calculationService
            ->method('calculateForEmployee')
            ->willReturn([
                'eligible' => true,
                'tenure_months' => 12,
                'proration_factor' => 1.0,
                'gross_thr_amount' => 10_000_000,
                'pph21_amount' => 500_000,
                'net_thr_amount' => 9_500_000,
                'tax_calculation_meta' => ['method' => 'annualization_difference'],
                'ineligibility_reason' => null,
            ]);

        $thrPayroll = ThrPayroll::factory()->make([
            'id' => 999,
            'year' => 2026,
            'religion_event' => ThrPayroll::EVENT_IDUL_FITRI,
            'status' => ThrPayroll::STATUS_PROCESSING,
        ]);

        $this->repository
            ->method('create')
            ->willReturn($thrPayroll);

        $this->repository
            ->method('bulkCreateDetails')
            ->willReturn(1);

        $this->repository
            ->method('updateTotals')
            ->willReturn($thrPayroll);

        $pendingPayroll = ThrPayroll::factory()->pending()->make(['id' => 999]);
        $this->repository
            ->method('updateStatus')
            ->willReturn($pendingPayroll);

        $result = $this->service->generate($validated, $creator);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['thr_payroll']);
        $this->assertStringContainsString('THR generated successfully', $result['message']);
    }

    public function test_generate_rejects_duplicate_thr_for_same_event_and_year(): void
    {
        $creator = User::factory()->create();

        $validated = [
            'religion_event' => ThrPayroll::EVENT_IDUL_FITRI,
            'year' => 2026,
            'religion_holiday_date' => '2026-06-15',
        ];

        $this->calculationService
            ->method('calculatePaymentDeadline')
            ->willReturn(Carbon::parse('2026-06-08'));

        $existing = ThrPayroll::factory()->pending()->create([
            'year' => 2026,
            'religion_event' => ThrPayroll::EVENT_IDUL_FITRI,
        ]);

        $this->repository
            ->method('getByYearAndEvent')
            ->willReturn($existing);

        $result = $this->service->generate($validated, $creator);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists', $result['message']);
    }

    public function test_generate_rejects_when_no_eligible_employees(): void
    {
        $creator = User::factory()->create();

        $validated = [
            'religion_event' => ThrPayroll::EVENT_IDUL_FITRI,
            'year' => 2026,
            'religion_holiday_date' => '2026-06-15',
        ];

        $this->calculationService
            ->method('calculatePaymentDeadline')
            ->willReturn(Carbon::parse('2026-06-08'));

        $this->repository
            ->method('getByYearAndEvent')
            ->willReturn(null);

        $this->calculationService
            ->method('getEligibleEmployees')
            ->willReturn(new EloquentCollection());

        $result = $this->service->generate($validated, $creator);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No eligible employees found', $result['message']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Approve
    // ─────────────────────────────────────────────────────────────────────────

    public function test_approve_pending_thr_payroll(): void
    {
        $approver = User::factory()->create();

        $thrPayroll = ThrPayroll::factory()->pending()->create();

        $this->repository
            ->method('getById')
            ->willReturn($thrPayroll);

        $approvedPayroll = ThrPayroll::factory()->approved()->make(['id' => $thrPayroll->id]);
        $this->repository
            ->method('updateStatus')
            ->willReturn($approvedPayroll);

        $result = $this->service->approve($thrPayroll->id, $approver);

        $this->assertTrue($result['success']);
        $this->assertEquals('THR payroll approved successfully', $result['message']);
    }

    public function test_approve_rejects_non_pending_thr_payroll(): void
    {
        $approver = User::factory()->create();

        $thrPayroll = ThrPayroll::factory()->approved()->create();

        $this->repository
            ->method('getById')
            ->willReturn($thrPayroll);

        $result = $this->service->approve($thrPayroll->id, $approver);

        $this->assertFalse($result['success']);
        $this->assertEquals('Only pending THR payrolls can be approved', $result['message']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mark as Paid
    // ─────────────────────────────────────────────────────────────────────────

    public function test_mark_as_paid_approves_paid_thr_payroll(): void
    {
        $actor = User::factory()->create();
        $thrPayroll = ThrPayroll::factory()->approved()->create();

        $this->repository
            ->method('getById')
            ->willReturn($thrPayroll);

        $paidPayroll = ThrPayroll::factory()->paid()->make(['id' => $thrPayroll->id]);
        $this->repository
            ->method('updateStatus')
            ->willReturn($paidPayroll);

        $result = $this->service->markAsPaid($thrPayroll->id, '2026-06-01', $actor);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('THR marked as paid', $result['message']);
    }

    public function test_mark_as_paid_rejects_non_approved_thr_payroll(): void
    {
        $actor = User::factory()->create();

        $thrPayroll = ThrPayroll::factory()->pending()->create();

        $this->repository
            ->method('getById')
            ->willReturn($thrPayroll);

        $result = $this->service->markAsPaid($thrPayroll->id, '2026-06-01', $actor);

        $this->assertFalse($result['success']);
        $this->assertEquals('Only approved THR payrolls can be marked as paid', $result['message']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Simulate
    // ─────────────────────────────────────────────────────────────────────────

    public function test_simulate_returns_preview_without_persisting(): void
    {
        $employee = StaffMemberProfile::factory()->create([
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-012.345',
        ]);

        JobInformation::factory()->active()->create([
            'staff_member_id' => $employee->id,
            'start_date' => Carbon::now()->subMonths(12)->toDateString(),
            'monthly_salary' => 10_000_000,
        ]);

        // Reload to get the jobInformation relationship
        $employee->load('jobInformation', 'user');

        $this->calculationService
            ->method('calculatePaymentDeadline')
            ->willReturn(Carbon::parse('2026-06-08'));

        $this->calculationService
            ->method('getEligibleEmployees')
            ->willReturn(new EloquentCollection([$employee]));

        $this->calculationService
            ->method('calculateForEmployee')
            ->willReturn([
                'eligible' => true,
                'tenure_months' => 12,
                'proration_factor' => 1.0,
                'gross_thr_amount' => 10_000_000,
                'pph21_amount' => 500_000,
                'net_thr_amount' => 9_500_000,
                'tax_calculation_meta' => null,
                'ineligibility_reason' => null,
            ]);

        $result = $this->service->simulate(
            ThrPayroll::EVENT_IDUL_FITRI,
            2026,
            '2026-06-15'
        );

        $this->assertEquals(ThrPayroll::EVENT_IDUL_FITRI, $result['religion_event']);
        $this->assertEquals('Idul Fitri', $result['event_label']);
        $this->assertEquals(2026, $result['year']);
        $this->assertEquals('2026-06-15', $result['religion_holiday_date']);
        $this->assertEquals('2026-06-08', $result['payment_deadline']);
        $this->assertEquals(1, $result['eligible_count']);
        $this->assertEquals(0, $result['ineligible_count']);
        $this->assertEquals(10_000_000, $result['total_gross_amount']);
        $this->assertEquals(500_000, $result['total_tax_amount']);
        $this->assertEquals(9_500_000, $result['total_net_amount']);
    }

    public function test_simulate_separates_eligible_and_ineligible(): void
    {
        $eligibleEmployee = StaffMemberProfile::factory()->create();
        $ineligibleEmployee = StaffMemberProfile::factory()->create();

        JobInformation::factory()->active()->create([
            'staff_member_id' => $eligibleEmployee->id,
            'start_date' => Carbon::now()->subMonths(12)->toDateString(),
            'monthly_salary' => 10_000_000,
        ]);

        JobInformation::factory()->create([
            'staff_member_id' => $ineligibleEmployee->id,
            'status' => 'inactive',
            'start_date' => Carbon::now()->subMonths(12)->toDateString(),
            'monthly_salary' => 10_000_000,
        ]);

        // Reload to get the jobInformation relationship
        $eligibleEmployee->load('jobInformation', 'user');
        $ineligibleEmployee->load('jobInformation', 'user');

        $this->calculationService
            ->method('calculatePaymentDeadline')
            ->willReturn(Carbon::parse('2026-06-08'));

        $this->calculationService
            ->method('getEligibleEmployees')
            ->willReturn(new EloquentCollection([$eligibleEmployee, $ineligibleEmployee]));

        $this->calculationService
            ->method('calculateForEmployee')
            ->willReturnCallback(function (StaffMemberProfile $employee) use ($eligibleEmployee) {
                if ($employee->id === $eligibleEmployee->id) {
                    return [
                        'eligible' => true,
                        'tenure_months' => 12,
                        'proration_factor' => 1.0,
                        'gross_thr_amount' => 10_000_000,
                        'pph21_amount' => 500_000,
                        'net_thr_amount' => 9_500_000,
                        'tax_calculation_meta' => null,
                        'ineligibility_reason' => null,
                    ];
                }

                return [
                    'eligible' => false,
                    'tenure_months' => 0,
                    'proration_factor' => 0,
                    'gross_thr_amount' => 0,
                    'pph21_amount' => 0,
                    'net_thr_amount' => 0,
                    'tax_calculation_meta' => null,
                    'ineligibility_reason' => 'Employee is not active',
                ];
            });

        $result = $this->service->simulate(
            ThrPayroll::EVENT_IDUL_FITRI,
            2026,
            '2026-06-15'
        );

        $this->assertEquals(1, $result['eligible_count']);
        $this->assertEquals(1, $result['ineligible_count']);
        $this->assertCount(1, $result['eligible_employees']);
        $this->assertCount(1, $result['ineligible_employees']);
        $this->assertEquals('Employee is not active', $result['ineligible_employees'][0]['reason']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delegation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_get_by_id_delegates_to_repository(): void
    {
        $thrPayroll = ThrPayroll::factory()->create();

        $this->repository
            ->method('getById')
            ->willReturn($thrPayroll);

        $result = $this->service->getById($thrPayroll->id);

        $this->assertEquals($thrPayroll->id, $result->id);
    }

    public function test_get_all_paginated_delegates_to_repository(): void
    {
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), 0, 15, 1
        );

        $this->repository
            ->method('getAllPaginated')
            ->willReturn($paginator);

        $result = $this->service->getAllPaginated(2026, 'pending');

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
    }

    public function test_get_year_summary_delegates_to_repository(): void
    {
        $summary = [
            'total_thr' => 50_000_000,
            'total_tax' => 2_500_000,
        ];

        $this->repository
            ->method('getYearSummary')
            ->willReturn($summary);

        $result = $this->service->getYearSummary(2026);

        $this->assertEquals($summary, $result);
    }
}
