<?php

namespace Tests\Feature\Payroll;

use App\Enums\PayrollStatus;
use App\Exceptions\PayrollReconciliationBlockedException;
use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollSetting;
use App\Models\PayrollSettingVersion;
use App\Models\PayrollTaxEntry;
use App\Models\PayrollTaxYear;
use App\Models\StaffMemberProfile;
use App\Models\ThrPayroll;
use App\Models\ThrPayrollDetail;
use App\Models\User;
use App\Services\Payroll\PayrollTaxIntegrationService;
use App\Services\ThrService;
use Database\Seeders\BpjsRateSeeder;
use Database\Seeders\PtkpAmountSeeder;
use Database\Seeders\TaxBracketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PayrollSettlementReapprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_payroll_early_credit_commits_pending_recalculation_then_pays_after_reapproval(): void
    {
        [$payroll, $thr, $actor] = $this->sources();
        $integration = app(PayrollTaxIntegrationService::class);
        $integration->refreshThr($thr);
        $repository = app(PayrollRepositoryInterface::class);
        $repository->approvePayroll((string) $payroll->id, $actor->id);
        $before = (float) $payroll->payrollDetails()->sole()->pph21_amount;

        try {
            $repository->markAsPaid((string) $payroll->id, '2026-12-10', $actor->id);
            $this->fail('Changed settlement was paid under the old approval.');
        } catch (PayrollReconciliationBlockedException $error) {
            $this->assertStringContainsString('Settlement changed', $error->getMessage());
        }

        $payroll->refresh();
        $detail = $payroll->payrollDetails()->sole();
        $entry = PayrollTaxEntry::where('source_type', 'payroll')->where('source_id', $detail->id)->sole();
        $this->assertSame(PayrollStatus::PENDING, $payroll->status);
        $this->assertNull($payroll->payment_date);
        $this->assertSame('2026-12-10', $payroll->planned_credit_date->toDateString());
        $this->assertNotEquals($before, (float) $detail->pph21_amount);
        $this->assertEquals(642700, (float) $detail->pph21_amount);
        $this->assertNull($entry->settled_at);
        $this->assertNull($entry->actual_withholding);

        $repository->approvePayroll((string) $payroll->id, $actor->id);
        $paid = $repository->markAsPaid((string) $payroll->id, '2026-12-10', $actor->id);
        $this->assertSame(PayrollStatus::PAID, $paid->status);
        $this->assertNotNull($entry->fresh()->settled_at);
        $this->assertEquals(642700, (float) $entry->fresh()->actual_withholding);
        $this->assertEquals((float) $detail->pph21_amount, (float) $paid->payrollDetails()->sole()->pph21_amount);
    }

    public function test_final_thr_early_credit_commits_pending_recalculation_then_pays_after_reapproval(): void
    {
        [$payroll, $thr, $actor] = $this->sources();
        $thr->update(['income_due_date' => '2026-12-28']);
        app(PayrollTaxIntegrationService::class)->refreshPayroll($payroll);
        $service = app(ThrService::class);
        $this->assertTrue($service->approve($thr->id, $actor)['success']);
        $before = (float) $thr->details()->sole()->pph21_amount;

        $result = $service->markAsPaid($thr->id, '2026-12-10', $actor);
        $this->assertFalse($result['success']);
        $thr->refresh();
        $detail = $thr->details()->sole();
        $entry = PayrollTaxEntry::where('source_type', 'thr')->where('source_id', $detail->id)->sole();
        $this->assertSame(ThrPayroll::STATUS_PENDING, $thr->status);
        $this->assertNull($thr->approved_by);
        $this->assertNull($thr->approved_at);
        $this->assertNull($thr->payment_date);
        $this->assertNotEquals($before, (float) $detail->pph21_amount);
        $this->assertEquals(635000, (float) $detail->pph21_amount);
        $this->assertNull($entry->settled_at);
        $this->assertNull($entry->actual_withholding);

        $this->assertTrue($service->approve($thr->id, $actor)['success']);
        $paid = $service->markAsPaid($thr->id, '2026-12-10', $actor);
        $this->assertTrue($paid['success']);
        $this->assertSame(ThrPayroll::STATUS_PAID, $thr->fresh()->status);
        $this->assertNotNull($entry->fresh()->settled_at);
        $this->assertEquals(635000, (float) $entry->fresh()->actual_withholding);
    }

    public function test_reordered_nested_metadata_preserves_payroll_and_thr_approvals(): void
    {
        [$payroll, $thr, $actor] = $this->sources();
        $integration = app(PayrollTaxIntegrationService::class);
        $integration->refreshThr($thr);
        app(PayrollRepositoryInterface::class)->approvePayroll((string) $payroll->id, $actor->id);
        app(ThrService::class)->approve($thr->id, $actor);
        $reorder = function (array $value) use (&$reorder): array {
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $reorder($item);
                }
            }
            return array_is_list($value) ? $value : array_reverse($value, true);
        };
        foreach (PayrollTaxEntry::all() as $entry) {
            $entry->update(['input' => $reorder($entry->input), 'calculation' => $reorder($entry->calculation)]);
        }
        $detail = $payroll->payrollDetails()->sole();
        $detail->update(['tax_calculation_meta' => json_encode($reorder(json_decode($detail->tax_calculation_meta, true)), JSON_THROW_ON_ERROR)]);
        $thrDetail = $thr->details()->sole();
        $thrDetail->update(['tax_calculation_meta' => $reorder($thrDetail->tax_calculation_meta)]);

        $this->assertTrue($integration->settleThr($thr, '2026-12-15'));
        $this->assertSame(ThrPayroll::STATUS_APPROVED, $thr->fresh()->status);
        $this->assertSame(PayrollStatus::PENDING, $payroll->fresh()->status);
        app(PayrollRepositoryInterface::class)->approvePayroll((string) $payroll->id, $actor->id);
        $this->assertTrue($integration->settlePayroll($payroll, '2026-12-25'));
        $this->assertSame(PayrollStatus::APPROVED, $payroll->fresh()->status);
    }

    public function test_verified_departure_after_payment_refreshes_final_tax_and_preserves_paid_history(): void
    {
        [$payroll, $thr, $actor] = $this->sources();
        $year = PayrollTaxYear::sole();
        $history = array_intersect_key($year->history, array_flip(['2026-01', '2026-02', '2026-03', '2026-04']));
        $year->update(['history' => $history]);
        $payroll->update(['salary_month' => '2026-06-01']);
        $thr->update(['income_due_date' => '2026-05-15']);
        $actor->assignRole('finance');
        \App\Models\PayrollApprovalPolicy::create(['name' => 'Finance review', 'min_amount' => 0,
            'required_role' => 'finance', 'approval_order' => 1, 'is_active' => true]);
        $thrService = app(ThrService::class);
        $this->assertTrue($thrService->approve($thr->id, $actor)['success']);
        $this->assertTrue($thrService->markAsPaid($thr->id, '2026-05-15', $actor)['success']);
        $settled = PayrollTaxEntry::where('source_type', 'thr')->sole();
        $paidSnapshot = $settled->getAttributes();
        $repository = app(PayrollRepositoryInterface::class);
        $repository->approvePayroll((string) $payroll->id, $actor->id);
        $approval = $payroll->approvals()->whereNull('superseded_at')->sole();
        $detail = $payroll->payrollDetails()->sole();
        $before = (float) $detail->pph21_amount;
        $row = array_merge($year->profile, ['employee_id' => $year->employee_id, 'year' => 2026,
            'history' => $history, 'provenance' => 'Verified synthetic June departure', 'employment_end_date' => '2026-06-30']);
        $path = tempnam(sys_get_temp_dir(), 'tax-profile-');
        try {
            file_put_contents($path, json_encode([$row], JSON_THROW_ON_ERROR));
            $this->artisan('payroll:import-tax-history', ['path' => $path, '--update-profile' => true])->assertSuccessful();
            $detail->refresh();
            $meta = json_decode($detail->tax_calculation_meta, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('final_period_actual', $meta['method']);
            $this->assertNotEquals($before, (float) $detail->pph21_amount);
            $this->assertEquals($meta['meta']['cash_base'] - (float) $detail->bpjs_tk_employee
                - (float) $detail->bpjs_kes_employee - (float) $detail->pph21_amount, (float) $detail->final_salary);
            $this->assertSame(PayrollStatus::PENDING, $payroll->fresh()->status);
            $this->assertNotNull($approval->fresh()->superseded_at);
            $this->assertSame('approved', $approval->fresh()->status);
            $this->assertSame($paidSnapshot, $settled->fresh()->getAttributes());
            $this->assertStringContainsString('Synthetic complete Jan-Nov ledger', $year->fresh()->provenance);
            $this->assertStringContainsString($row['provenance'], $year->fresh()->provenance);
            $this->assertSame($history, $year->fresh()->history);

            $repository->approvePayroll((string) $payroll->id, $actor->id);
            $currentApproval = $payroll->approvals()->whereNull('superseded_at')->sole();
            $yearSnapshot = $year->fresh()->getAttributes();
            $detailSnapshot = $detail->fresh()->getAttributes();
            $approvalSnapshot = $currentApproval->getAttributes();
            $this->travel(1)->minutes();
            $this->artisan('payroll:import-tax-history', ['path' => $path, '--update-profile' => true])->assertSuccessful();
            $this->assertSame($yearSnapshot, $year->fresh()->getAttributes());
            $this->assertSame($detailSnapshot, $detail->fresh()->getAttributes());
            $this->assertSame($approvalSnapshot, $currentApproval->fresh()->getAttributes());
            $this->assertSame(PayrollStatus::APPROVED, $payroll->fresh()->status);
            $this->travelBack();

            foreach ([['employment_end_date' => '2026-05-31'], ['history' => []]] as $invalid) {
                file_put_contents($path, json_encode([array_replace($row, $invalid)], JSON_THROW_ON_ERROR));
                $this->artisan('payroll:import-tax-history', ['path' => $path, '--update-profile' => true])->assertFailed();
                $this->assertSame('2026-06-30', $year->fresh()->profile['employment_end_date']);
                $this->assertSame($history, $year->fresh()->history);
                $this->assertSame($paidSnapshot, $settled->fresh()->getAttributes());
            }
        } finally {
            unlink($path);
        }
    }

    private function sources(): array
    {
        Mail::fake();
        Notification::fake();
        $this->seed([BpjsRateSeeder::class, PtkpAmountSeeder::class, TaxBracketSeeder::class]);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $settings = PayrollSetting::current();
        $settings->update(['rounding_mode' => 'none']);
        $version = $settings->resolveActiveVersion();
        $actor = User::factory()->create();
        $employee = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->for(User::factory())->create([
            'ptkp_status' => 'TK/0', 'npwp' => '123456789012345',
        ]));
        $employee->bankInformation()->create(['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder_name' => 'Synthetic Employee']);
        $history = [];
        for ($month = 1; $month <= 11; $month++) {
            $history[sprintf('2026-%02d', $month)] = [
                'gross_income' => 10000000, 'employee_pension_contributions' => 300000,
                'deductible_donations' => 0, 'withheld' => 200000, 'government_borne_tax' => 0,
                'provenance' => 'Synthetic monthly withholding fixture',
            ];
        }
        PayrollTaxYear::create([
            'employee_id' => $employee->id, 'year' => 2026, 'provenance' => 'Synthetic complete Jan-Nov ledger',
            'profile' => ['employment_start_date' => '2026-01-01', 'employment_end_date' => null,
                'subjective_start_date' => '2026-01-01', 'subjective_end_date' => null],
            'history' => $history,
        ]);
        $payroll = Payroll::create(['salary_month' => '2026-12-01', 'status' => 'pending', 'payroll_setting_version_id' => $version->id]);
        PayrollDetail::create(['payroll_id' => $payroll->id, 'staff_member_id' => $employee->id,
            'original_salary' => 10000000, 'final_salary' => 10000000, 'attended_days' => 22, 'sick_days' => 0, 'absent_days' => 0]);
        $thr = ThrPayroll::create(['year' => 2026, 'religion_event' => 'natal', 'religion_holiday_date' => '2026-12-25',
            'payment_deadline' => '2026-12-18', 'income_due_date' => '2026-12-15', 'status' => 'pending', 'created_by' => $actor->id]);
        ThrPayrollDetail::create(['thr_payroll_id' => $thr->id, 'staff_member_id' => $employee->id,
            'religion' => 'kristen', 'monthly_salary' => 10000000, 'join_date' => '2026-01-01',
            'tenure_months' => 12, 'proration_factor' => 1, 'gross_thr_amount' => 10000000,
            'pph21_amount' => 0, 'net_thr_amount' => 10000000, 'ptkp_status' => 'TK/0', 'has_taxpayer_id' => true]);

        return [$payroll, $thr, $actor];
    }
}
