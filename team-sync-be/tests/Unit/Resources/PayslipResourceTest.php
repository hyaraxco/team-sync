<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\PayslipResource;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PayslipResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payslip_resource_aligns_gross_tax_and_insurance_with_payroll_detail(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
            'code' => 'EMP-001',
        ]);

        $payroll = Payroll::factory()->create([
            'status' => 'paid',
            'salary_month' => '2026-05-01',
            'payment_date' => '2026-05-25',
        ]);

        $detail = PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $profile->id,
            'original_salary' => 8_000_000,
            'overtime_amount' => 1_500_000,
            'overtime_hours' => 10,
            'deduction_amount' => 200_000,
            'pph21_amount' => 142_500,
            'bpjs_tk_employee' => 240_000,
            'bpjs_kes_employee' => 80_000,
            'final_salary' => 9_037_500,
        ]);

        $resource = new PayslipResource($detail);
        $payload = $resource->toArray(Request::create('/'));

        $this->assertSame(8_000_000.0, $payload['basic_salary']);
        $this->assertSame(1_500_000.0, $payload['overtime_amount']);
        $this->assertSame(9_500_000.0, $payload['gross_salary']); // 8M + 1.5M overtime
        $this->assertSame(142_500.0, $payload['tax']);
        $this->assertSame(320_000.0, $payload['insurance']);
        $this->assertSame(200_000.0, $payload['other_deductions']);
        $this->assertSame(662_500.0, $payload['total_deductions']); // 200k + 142.5k + 320k
        $this->assertSame(9_037_500.0, $payload['net_salary']);
    }
}
