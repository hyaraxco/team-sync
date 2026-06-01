<?php

namespace Tests\Feature\StaffMember;

use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Support\SensitiveData;
use Database\Seeders\MinimalPayrollE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffMemberProfileEducationSeniorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MinimalPayrollE2ESeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_hr_can_store_last_education_and_seniority_level_when_creating_staff_member(): void
    {
        $hr = User::where('email', 'tasyia@teamsync.com')->firstOrFail();
        Sanctum::actingAs($hr);

        $payload = [
            'name' => 'Education Candidate',
            'email' => 'education.candidate@teamsync.com',
            'password' => env('TEST_STAFF_PASSWORD', 'teamsync123'),
            'roles' => ['staff'],
            'identity_number' => '9988776655443322',
            'phone' => '081299887766',
            'date_of_birth' => '1998-10-10',
            'gender' => 'female',
            'place_of_birth' => 'Bandung',
            'address' => 'Jl. Pendidikan No. 10',
            'city' => 'Bandung',
            'postal_code' => '40123',
            'last_education' => 's1',
            'seniority_level' => 'mid',
            'job_title' => 'QA Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2025-01-01',
            'monthly_salary' => 9000000,
            'bank_name' => 'bca',
            'account_number' => '555000111222',
            'account_holder_name' => 'Education Candidate',
            'emergency_contacts' => [
                [
                    'full_name' => 'Emergency Contact',
                    'relationship' => 'Sibling',
                    'phone' => '081233344455',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/staff-members', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.last_education', 's1')
            ->assertJsonPath('data.seniority_level', 'mid');

        $this->assertDatabaseHas('staff_member_profiles', [
            'identity_number_hash' => SensitiveData::hash('9988776655443322'),
            'last_education' => 's1',
            'seniority_level' => 'mid',
        ]);

        $storedProfile = DB::table('staff_member_profiles')
            ->where('identity_number_hash', SensitiveData::hash('9988776655443322'))
            ->first();
        $storedBankInfo = DB::table('bank_information')
            ->where('account_number_hash', SensitiveData::hash('555000111222'))
            ->first();

        $this->assertNotNull($storedProfile);
        $this->assertNotNull($storedBankInfo);
        $this->assertNotSame('9988776655443322', $storedProfile->identity_number);
        $this->assertNotSame('555000111222', $storedBankInfo->account_number);
        $this->assertNotSame('Education Candidate', $storedBankInfo->account_holder_name);
    }

    public function test_hr_can_update_last_education_and_seniority_level(): void
    {
        $hr = User::where('email', 'tasyia@teamsync.com')->firstOrFail();
        Sanctum::actingAs($hr);

        $profile = StaffMemberProfile::where('code', 'EMP001')->firstOrFail();
        $emergencyContact = $profile->emergencyContacts()->firstOrFail();

        $response = $this->putJson('/api/v1/staff-members/'.$profile->id, [
            'name' => 'Agung Updated',
            'email' => 'agung@teamsync.com',
            'identity_number' => $profile->identity_number,
            'phone' => $profile->phone,
            'date_of_birth' => $profile->date_of_birth?->format('Y-m-d'),
            'gender' => $profile->gender,
            'place_of_birth' => $profile->place_of_birth,
            'address' => $profile->address,
            'city' => $profile->city,
            'postal_code' => $profile->postal_code,
            'last_education' => 's2',
            'seniority_level' => 'senior',
            'job_title' => $profile->jobInformation->job_title,
            'status' => $profile->jobInformation->status,
            'employment_type' => $profile->jobInformation->employment_type,
            'work_location' => $profile->jobInformation->work_location,
            'start_date' => $profile->jobInformation->start_date?->format('Y-m-d'),
            'monthly_salary' => $profile->jobInformation->monthly_salary,
            'bank_name' => $profile->bankInformation->bank_name,
            'account_number' => $profile->bankInformation->account_number,
            'account_holder_name' => $profile->bankInformation->account_holder_name,
            'emergency_contacts' => [
                [
                    'id' => $emergencyContact->id,
                    'full_name' => $emergencyContact->full_name,
                    'relationship' => $emergencyContact->relationship,
                    'phone' => $emergencyContact->phone,
                    'email' => $emergencyContact->email,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.last_education', 's2')
            ->assertJsonPath('data.seniority_level', 'senior');

        $this->assertDatabaseHas('staff_member_profiles', [
            'id' => $profile->id,
            'last_education' => 's2',
            'seniority_level' => 'senior',
        ]);
    }

    public function test_validation_rejects_invalid_education_and_seniority_values(): void
    {
        $hr = User::where('email', 'tasyia@teamsync.com')->firstOrFail();
        Sanctum::actingAs($hr);

        $response = $this->postJson('/api/v1/staff-members', [
            'name' => 'Invalid Candidate',
            'email' => 'invalid.education@teamsync.com',
            'password' => env('TEST_STAFF_PASSWORD', 'teamsync123'),
            'roles' => ['staff'],
            'identity_number' => '1122334455667788',
            'phone' => '081200000001',
            'date_of_birth' => '1997-01-01',
            'gender' => 'male',
            'place_of_birth' => 'Jakarta',
            'address' => 'Jl. Invalid',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'last_education' => 'kindergarten',
            'seniority_level' => 'principal',
            'job_title' => 'Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2025-01-01',
            'monthly_salary' => 8000000,
            'bank_name' => 'bca',
            'account_number' => '888777666555',
            'account_holder_name' => 'Invalid Candidate',
            'emergency_contacts' => [
                [
                    'full_name' => 'Invalid Emergency',
                    'relationship' => 'Friend',
                    'phone' => '081211122233',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['last_education', 'seniority_level']);
    }
}
