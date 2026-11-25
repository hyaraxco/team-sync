<?php

namespace Tests\Feature\Payroll;

use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Support\SensitiveData;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class NikTaxpayerAcceptanceEndpointTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->activateTestLicense();
    }

    private function endpoint(int $employeeId): string
    {
        return '/api/v1/staff-members/'.$employeeId.'/accept-nik-taxpayer';
    }

    /** @test */
    public function test_finance_can_accept_nik(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $nik = '3201010101010001';
        $employee = StaffMemberProfile::factory()->create([
            'identity_number' => $nik,
            'identity_number_hash' => SensitiveData::hash($nik),
            'npwp' => null,
        ]);

        $response = $this->actingAs($finance)->postJson($this->endpoint($employee->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['accepted_at']]);

        $employee->refresh();
        $this->assertSame(SensitiveData::hash($nik), $employee->nik_taxpayer_accepted_hash);
        $this->assertSame($finance->id, $employee->nik_taxpayer_accepted_by);
        $this->assertTrue($employee->hasTaxpayerId());
    }

    /** @test */
    public function test_hr_cannot_accept_nik(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        $employee = StaffMemberProfile::factory()->create([
            'identity_number' => '3201010101010001',
            'identity_number_hash' => SensitiveData::hash('3201010101010001'),
        ]);

        $response = $this->actingAs($hr)->postJson($this->endpoint($employee->id));

        $response->assertForbidden();

        $employee->refresh();
        $this->assertNull($employee->nik_taxpayer_accepted_hash);
    }

    /** @test */
    public function test_staff_cannot_accept_nik(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $employee = StaffMemberProfile::factory()->create([
            'identity_number' => '3201010101010001',
            'identity_number_hash' => SensitiveData::hash('3201010101010001'),
        ]);

        $response = $this->actingAs($staff)->postJson($this->endpoint($employee->id));

        $response->assertForbidden();
    }

    /** @test */
    public function test_rejects_employee_without_identity_number(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $employee = StaffMemberProfile::factory()->create([
            'identity_number' => null,
            'identity_number_hash' => null,
        ]);

        $response = $this->actingAs($finance)->postJson($this->endpoint($employee->id));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function test_nik_change_invalidates_acceptance(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $oldNik = '3201010101010001';
        $newNik = '3201010101010002';
        $employee = StaffMemberProfile::factory()->create([
            'identity_number' => $oldNik,
            'identity_number_hash' => SensitiveData::hash($oldNik),
            'npwp' => null,
        ]);

        // Accept with old NIK
        $this->actingAs($finance)->postJson($this->endpoint($employee->id))->assertOk();
        $this->assertTrue($employee->fresh()->hasTaxpayerId());

        // Simulate NIK change (the booted() hook recomputes hash on save)
        $employee->update(['identity_number' => $newNik]);
        $employee->refresh();

        // Stale acceptance — hasTaxpayerId should be false
        $this->assertFalse($employee->hasTaxpayerId());

        // Re-accept with new NIK
        $this->actingAs($finance)->postJson($this->endpoint($employee->id))->assertOk();
        $employee->refresh();
        $this->assertSame(SensitiveData::hash($newNik), $employee->nik_taxpayer_accepted_hash);
        $this->assertTrue($employee->hasTaxpayerId());
    }

    /** @test */
    public function test_idempotent_acceptance(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $nik = '3201010101010001';
        $employee = StaffMemberProfile::factory()->create([
            'identity_number' => $nik,
            'identity_number_hash' => SensitiveData::hash($nik),
            'npwp' => null,
        ]);

        // First acceptance
        $this->actingAs($finance)->postJson($this->endpoint($employee->id))->assertOk();
        $firstAcceptedAt = $employee->fresh()->nik_taxpayer_accepted_at;

        // Second acceptance — idempotent, same response
        $this->actingAs($finance)->postJson($this->endpoint($employee->id))->assertOk();
        $employee->refresh();
        $this->assertEquals($firstAcceptedAt, $employee->nik_taxpayer_accepted_at);
    }

    /** @test */
    public function test_nonexistent_employee_returns_404(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $response = $this->actingAs($finance)->postJson($this->endpoint(999999));

        $response->assertNotFound();
    }
}
