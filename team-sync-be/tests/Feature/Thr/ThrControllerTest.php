<?php

namespace Tests\Feature\Thr;

use App\Models\BpjsRate;
use App\Models\PtkpAmount;
use App\Models\StaffMemberProfile;
use App\Models\TaxBracket;
use App\Models\ThrPayroll;
use App\Models\ThrPayrollDetail;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class ThrControllerTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private User $hrUser;

    private User $financeUser;

    private User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTaxInfrastructure();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->hrUser = User::factory()->create();
        $this->hrUser->assignRole('hr');

        $this->financeUser = User::factory()->create();
        $this->financeUser->assignRole('finance');

        $this->staffUser = User::factory()->create();
        $this->staffUser->assignRole('staff');
    }

    public function test_unauthenticated_user_cannot_access_thr(): void
    {
        $this->getJson('/api/v1/thr')->assertStatus(401);
    }

    public function test_user_without_permission_cannot_list_thr(): void
    {
        $this->actingAs($this->staffUser)
            ->getJson('/api/v1/thr')
            ->assertStatus(403);
    }

    public function test_user_without_permission_cannot_generate_thr(): void
    {
        $this->actingAs($this->staffUser)
            ->postJson('/api/v1/thr/generate', [])
            ->assertStatus(403);
    }

    public function test_hr_can_list_thr_payrolls(): void
    {
        ThrPayroll::factory()->pending()->create(['year' => 2026]);

        $this->actingAs($this->hrUser)
            ->getJson('/api/v1/thr')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_hr_can_show_thr_payroll(): void
    {
        $thr = ThrPayroll::factory()->pending()->create();

        $this->actingAs($this->hrUser)
            ->getJson("/api/v1/thr/{$thr->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $thr->id);
    }

    public function test_finance_can_generate_thr(): void
    {
        $this->createEmployee(salary: 10_000_000, startDate: now()->subMonths(12), religion: 'islam');

        $this->actingAs($this->financeUser)
            ->postJson('/api/v1/thr/generate', [
                'religion_event' => 'idul_fitri',
                'year' => 2026,
                'religion_holiday_date' => now()->addMonths(2)->format('Y-m-d'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.religion_event', 'idul_fitri')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_hr_cannot_generate_thr(): void
    {
        $this->createEmployee(salary: 10_000_000, startDate: now()->subMonths(12), religion: 'islam');

        $this->actingAs($this->hrUser)
            ->postJson('/api/v1/thr/generate', [
                'religion_event' => 'idul_fitri',
                'year' => 2026,
                'religion_holiday_date' => now()->addMonths(2)->format('Y-m-d'),
            ])
            ->assertStatus(403);
    }

    public function test_generate_thr_validates_required_fields(): void
    {
        $this->actingAs($this->financeUser)
            ->postJson('/api/v1/thr/generate', [])
            ->assertStatus(422);
    }

    public function test_cannot_generate_duplicate_thr_for_same_event_and_year(): void
    {
        $this->createEmployee(salary: 10_000_000, startDate: now()->subMonths(12), religion: 'islam');

        ThrPayroll::factory()->create([
            'year' => 2026,
            'religion_event' => 'idul_fitri',
        ]);

        $this->actingAs($this->financeUser)
            ->postJson('/api/v1/thr/generate', [
                'religion_event' => 'idul_fitri',
                'year' => 2026,
                'religion_holiday_date' => now()->addMonths(2)->format('Y-m-d'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_hr_cannot_approve_pending_thr(): void
    {
        $thr = ThrPayroll::factory()->pending()->create();

        $this->actingAs($this->hrUser)
            ->postJson("/api/v1/thr/{$thr->id}/approve")
            ->assertStatus(403);
    }

    public function test_finance_can_approve_pending_thr(): void
    {
        $thr = ThrPayroll::factory()->pending()->create();

        $this->actingAs($this->financeUser)
            ->postJson("/api/v1/thr/{$thr->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_cannot_approve_non_pending_thr(): void
    {
        $thr = ThrPayroll::factory()->approved()->create();

        $this->actingAs($this->financeUser)
            ->postJson("/api/v1/thr/{$thr->id}/approve")
            ->assertStatus(400);
    }

    public function test_hr_cannot_mark_approved_thr_as_paid(): void
    {
        $thr = ThrPayroll::factory()->approved()->create();

        $this->actingAs($this->hrUser)
            ->postJson("/api/v1/thr/{$thr->id}/mark-as-paid", [
                'payment_date' => now()->format('Y-m-d'),
            ])
            ->assertStatus(403);
    }

    public function test_finance_can_mark_approved_thr_as_paid(): void
    {
        $thr = ThrPayroll::factory()->approved()->create();

        $this->actingAs($this->financeUser)
            ->postJson("/api/v1/thr/{$thr->id}/mark-as-paid", [
                'payment_date' => now()->format('Y-m-d'),
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_cannot_mark_non_approved_thr_as_paid(): void
    {
        $thr = ThrPayroll::factory()->pending()->create();

        $this->actingAs($this->financeUser)
            ->postJson("/api/v1/thr/{$thr->id}/mark-as-paid", [
                'payment_date' => now()->format('Y-m-d'),
            ])
            ->assertStatus(400);
    }

    public function test_finance_can_simulate_thr(): void
    {
        $this->createEmployee(salary: 10_000_000, startDate: now()->subMonths(12), religion: 'islam');

        $this->actingAs($this->financeUser)
            ->postJson('/api/v1/thr/simulate', [
                'religion_event' => 'idul_fitri',
                'year' => 2026,
                'religion_holiday_date' => now()->addMonths(2)->format('Y-m-d'),
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'religion_event',
                    'eligible_count',
                    'ineligible_count',
                    'total_gross_amount',
                    'total_tax_amount',
                    'total_net_amount',
                    'eligible_employees',
                ],
            ]);
    }

    public function test_hr_cannot_simulate_thr(): void
    {
        $this->actingAs($this->hrUser)
            ->postJson('/api/v1/thr/simulate', [
                'religion_event' => 'idul_fitri',
                'year' => 2026,
                'religion_holiday_date' => now()->addMonths(2)->format('Y-m-d'),
            ])
            ->assertStatus(403);
    }

    public function test_hr_can_get_year_summary(): void
    {
        ThrPayroll::factory()->pending()->create(['year' => 2026, 'religion_event' => 'idul_fitri']);
        ThrPayroll::factory()->paid()->create(['year' => 2026, 'religion_event' => 'natal']);

        $this->actingAs($this->hrUser)
            ->getJson('/api/v1/thr/year-summary?year=2026')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.total_events', 2);
    }

    public function test_hr_can_get_thr_details(): void
    {
        $thr = ThrPayroll::factory()->pending()->create();
        $employee = $this->createEmployee(salary: 10_000_000, startDate: now()->subMonths(12));

        ThrPayrollDetail::factory()->create([
            'thr_payroll_id' => $thr->id,
            'staff_member_id' => $employee->id,
        ]);

        $this->actingAs($this->hrUser)
            ->getJson("/api/v1/thr/{$thr->id}/details")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createEmployee(
        float $salary,
        Carbon $startDate,
        string $religion = 'islam'
    ): StaffMemberProfile {
        $user = User::factory()->create();

        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
            'religion' => $religion,
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-012.000',
        ]);

        $profile->jobInformation()->create([
            'monthly_salary' => $salary,
            'start_date' => $startDate,
            'status' => 'active',
            'employment_type' => 'permanent',
            'job_title' => 'Staff',
            'team_id' => null,
            'work_location' => 'office',
        ]);

        $profile->load('jobInformation');

        return $profile;
    }

    private function seedTaxInfrastructure(): void
    {
        TaxBracket::create(['order' => 1, 'min_income' => 0, 'max_income' => 60_000_000, 'rate' => 5]);
        TaxBracket::create(['order' => 2, 'min_income' => 60_000_000, 'max_income' => 250_000_000, 'rate' => 15]);
        TaxBracket::create(['order' => 3, 'min_income' => 250_000_000, 'max_income' => 500_000_000, 'rate' => 25]);
        TaxBracket::create(['order' => 4, 'min_income' => 500_000_000, 'max_income' => 5_000_000_000, 'rate' => 30]);
        TaxBracket::create(['order' => 5, 'min_income' => 5_000_000_000, 'max_income' => null, 'rate' => 35]);

        PtkpAmount::create(['status' => 'TK/0', 'annual_amount' => 54_000_000]);
        PtkpAmount::create(['status' => 'K/0', 'annual_amount' => 58_500_000]);

        BpjsRate::create(['component' => 'jht', 'employee_rate' => 2, 'employer_rate' => 3.7, 'max_salary_base' => null]);
        BpjsRate::create(['component' => 'jp', 'employee_rate' => 1, 'employer_rate' => 2, 'max_salary_base' => 9_559_600]);
        BpjsRate::create(['component' => 'jkk', 'employee_rate' => 0, 'employer_rate' => 0.24, 'max_salary_base' => null]);
        BpjsRate::create(['component' => 'jkm', 'employee_rate' => 0, 'employer_rate' => 0.3, 'max_salary_base' => null]);
        BpjsRate::create(['component' => 'bpjs_kesehatan', 'employee_rate' => 1, 'employer_rate' => 4, 'max_salary_base' => 12_000_000]);
    }
}
