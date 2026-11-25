<?php

namespace Tests\Unit\Services\Payroll;

use App\Exceptions\PayrollStateException;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Services\Payroll\NikTaxpayerAcceptanceService;
use App\Support\SensitiveData;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NikTaxpayerAcceptanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private NikTaxpayerAcceptanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->service = app(NikTaxpayerAcceptanceService::class);
    }

    /** @test */
    public function test_has_taxpayer_id_true_when_npwp_filled_regardless_of_nik_acceptance(): void
    {
        $employee = StaffMemberProfile::factory()->create([
            'npwp' => '12.345.678.9-012.345',
            'nik_taxpayer_accepted_hash' => null,
        ]);

        $this->assertTrue($employee->hasTaxpayerId());
    }

    /** @test */
    public function test_has_taxpayer_id_false_when_no_npwp_and_no_acceptance(): void
    {
        $nik = '3201010101010001';
        $employee = StaffMemberProfile::factory()->create([
            'identity_number' => $nik,
            'identity_number_hash' => SensitiveData::hash($nik),
            'npwp' => null,
            'nik_taxpayer_accepted_hash' => null,
        ]);

        $this->assertFalse($employee->hasTaxpayerId());
    }

    /** @test */
    public function test_rejects_employee_without_identity_number(): void
    {
        $employee = StaffMemberProfile::factory()->create([
            'identity_number' => null,
            'identity_number_hash' => null,
        ]);

        $this->expectException(PayrollStateException::class);
        $this->expectExceptionMessage('no identity number');

        $this->service->accept($employee->id, $employee->user_id);
    }

    /** @test */
    public function test_accepts_nik_and_records_hash_and_actor(): void
    {
        $nik = '3201010101010001';
        $employee = StaffMemberProfile::factory()->create([
            'identity_number' => $nik,
            'identity_number_hash' => SensitiveData::hash($nik),
            'npwp' => null,
        ]);

        $actor = User::factory()->create();
        $result = $this->service->accept($employee->id, $actor->id);

        $this->assertNotNull($result->accepted_at);

        $employee->refresh();
        $this->assertSame(SensitiveData::hash($nik), $employee->nik_taxpayer_accepted_hash);
        $this->assertSame($actor->id, $employee->nik_taxpayer_accepted_by);
        $this->assertTrue($employee->hasTaxpayerId());
    }

    /** @test */
    public function test_idempotent_when_already_accepted(): void
    {
        $nik = '3201010101010001';
        $hash = SensitiveData::hash($nik);
        $originalActor = User::factory()->create();
        $employee = StaffMemberProfile::factory()->create([
            'identity_number' => $nik,
            'identity_number_hash' => $hash,
            'npwp' => null,
            'nik_taxpayer_accepted_hash' => $hash,
            'nik_taxpayer_accepted_at' => now()->subDay(),
            'nik_taxpayer_accepted_by' => $originalActor->id,
        ]);

        $newActor = User::factory()->create();
        $result = $this->service->accept($employee->id, $newActor->id);

        $this->assertNotNull($result->accepted_at);

        $employee->refresh();
        // Actor unchanged — no re-write
        $this->assertSame($originalActor->id, $employee->nik_taxpayer_accepted_by);
    }

    /** @test */
    public function test_nik_change_invalidates_acceptance(): void
    {
        $oldNik = '3201010101010001';
        $newNik = '3201010101010002';
        $originalActor = User::factory()->create();
        $employee = StaffMemberProfile::factory()->create([
            'identity_number' => $newNik,
            'identity_number_hash' => SensitiveData::hash($newNik),
            'npwp' => null,
            'nik_taxpayer_accepted_hash' => SensitiveData::hash($oldNik),
            'nik_taxpayer_accepted_at' => now()->subDay(),
            'nik_taxpayer_accepted_by' => $originalActor->id,
        ]);

        // Old acceptance is stale — hasTaxpayerId should be false
        $this->assertFalse($employee->hasTaxpayerId());

        // Re-accept with new identity
        $newActor = User::factory()->create();
        $this->service->accept($employee->id, $newActor->id);

        $employee->refresh();
        $this->assertSame(SensitiveData::hash($newNik), $employee->nik_taxpayer_accepted_hash);
        $this->assertSame($newActor->id, $employee->nik_taxpayer_accepted_by);
        $this->assertTrue($employee->hasTaxpayerId());
    }
}
