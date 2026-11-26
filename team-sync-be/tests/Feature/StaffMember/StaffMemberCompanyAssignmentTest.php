<?php

namespace Tests\Feature\StaffMember;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Services\Attendance\AttendanceClassifier;
use Carbon\Carbon;
use Database\Seeders\AttendancePolicySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffMemberCompanyAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            AttendancePolicySeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_created_profile_belongs_to_the_current_company(): void
    {
        $company = Company::factory()->create();

        $profile = StaffMemberProfile::withoutSyncingToSearch(
            fn () => StaffMemberProfile::factory()->create()
        );

        $this->assertSame($company->id, $profile->company_id);
        $this->assertSame($company->id, $profile->user->company_id);
    }

    public function test_profile_inherits_its_users_company_over_the_default(): void
    {
        Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $other->id]);

        $profile = StaffMemberProfile::withoutSyncingToSearch(
            fn () => StaffMemberProfile::factory()->for($user)->create()
        );

        $this->assertSame($other->id, $profile->company_id);
    }

    public function test_staff_created_through_the_api_is_classified_in_the_company_timezone(): void
    {
        $company = Company::factory()->create(['timezone' => 'America/Los_Angeles']);

        $hr = User::factory()->create();
        $hr->assignRole('hr');
        Sanctum::actingAs($hr);

        $this->postJson('/api/v1/staff-members', [
            'name' => 'Timezone Candidate',
            'email' => 'timezone.candidate@teamsync.com',
            'password' => 'DummyPassword123!',
            'roles' => ['staff'],
            'identity_number' => '9988776655443399',
            'phone' => '081299887700',
            'date_of_birth' => '1998-10-10',
            'gender' => 'female',
            'place_of_birth' => 'Bandung',
            'address' => 'Jl. Waktu No. 10',
            'city' => 'Bandung',
            'postal_code' => '40123',
            'job_title' => 'QA Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2025-01-01',
            'monthly_salary' => 9000000,
            'bank_name' => 'bca',
            'account_number' => '555000999888',
            'account_holder_name' => 'Timezone Candidate',
            'emergency_contacts' => [
                ['full_name' => 'Emergency Contact', 'relationship' => 'Sibling', 'phone' => '081233344400'],
            ],
        ])->assertCreated();

        $profile = StaffMemberProfile::whereHas('user', fn ($q) => $q->where('email', 'timezone.candidate@teamsync.com'))->sole();
        $this->assertSame($company->id, $profile->company_id);

        // 09:00 Los Angeles wall clock stored as UTC; Jakarta would read this as 23:00 and mark it absent.
        Attendance::create([
            'staff_member_id' => $profile->id,
            'date' => '2026-04-06',
            'check_in' => Carbon::parse('2026-04-06 09:00:00', 'America/Los_Angeles')->utc()->toDateTimeString(),
            'worked_minutes' => null,
        ]);

        $classified = app(AttendanceClassifier::class)->classify($profile->id, '2026-04-06');

        $this->assertSame('present', $classified['status']);
    }
}
