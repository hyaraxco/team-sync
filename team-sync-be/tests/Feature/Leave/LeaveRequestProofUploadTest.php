<?php

namespace Tests\Feature\Leave;

use App\Models\StaffMemberProfile;
use App\Models\LeaveRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveRequestProofUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_employee_can_upload_sick_leave_proof_for_own_request(): void
    {
        Storage::fake('public');

        $user = $this->actingAsEmployee('full_time');

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $user->staffMemberProfile->id,
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-15',
            'end_date' => '2026-04-15',
            'total_days' => 1,
            'reason' => 'Doctor advised rest',
            'status' => 'approved',
        ]);

        $response = $this->post(
            "/api/v1/leave-requests/{$leaveRequest->id}/proof",
            [
                'proof_file' => UploadedFile::fake()->create('sick-note.pdf', 120, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.proof_file_name', 'sick-note.pdf')
            ->assertJsonPath('data.proof_review_status', null);

        $leaveRequest->refresh();

        $this->assertNotNull($leaveRequest->proof_file_path);
        $this->assertSame('sick-note.pdf', $leaveRequest->proof_file_name);
        $this->assertNotNull($leaveRequest->proof_uploaded_at);

        Storage::disk('public')->assertExists($leaveRequest->proof_file_path);
    }

    public function test_employee_cannot_upload_proof_for_other_employee_leave_request(): void
    {
        Storage::fake('public');

        $this->actingAsEmployee('full_time');

        $anotherEmployee = $this->createEmployee('full_time');

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $anotherEmployee->id,
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-16',
            'end_date' => '2026-04-16',
            'total_days' => 1,
            'reason' => 'Medical checkup',
            'status' => 'approved',
        ]);

        $this->post(
            "/api/v1/leave-requests/{$leaveRequest->id}/proof",
            [
                'proof_file' => UploadedFile::fake()->create('other-note.pdf', 100, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        )->assertForbidden();
    }

    public function test_employee_cannot_upload_proof_for_non_sick_leave_request(): void
    {
        Storage::fake('public');

        $user = $this->actingAsEmployee('full_time');

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $user->staffMemberProfile->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-17',
            'end_date' => '2026-04-17',
            'total_days' => 1,
            'reason' => 'Family event',
            'status' => 'approved',
        ]);

        $response = $this->post(
            "/api/v1/leave-requests/{$leaveRequest->id}/proof",
            [
                'proof_file' => UploadedFile::fake()->create('annual-note.pdf', 100, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(400);
        $this->assertStringContainsString(
            'only supported for sick leave requests',
            (string) $response->json('message')
        );
    }

    private function actingAsEmployee(string $employmentType): User
    {
        $employee = $this->createEmployee($employmentType);
        $user = $employee->user;
        $user->assignRole(Role::findByName('staff', 'sanctum'));

        Sanctum::actingAs($user);

        return $user;
    }

    private function createEmployee(string $employmentType): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($employmentType) {
            $employee = StaffMemberProfile::factory()->create();

            $employee->jobInformation()->create([
                'employee_id' => $employee->id,
                'job_title' => 'QA Engineer',
                'years_experience' => 3,
                'status' => 'active',
                'employment_type' => $employmentType,
                'work_location' => 'remote',
                'start_date' => '2024-01-01',
                'monthly_salary' => 9000000,
                'skill_level' => 'intermediate',
            ]);

            return $employee;
        });
    }
}
