<?php

namespace Tests\Feature\Notification;

use App\Models\AttendancePeriod;
use App\Models\StaffMemberProfile;
use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LeaveCrossRoleNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private int $profileSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_employee_leave_submission_notifies_related_manager_and_hr(): void
    {
        Carbon::setTestNow('2026-04-15 09:00:00');
        $this->openAttendancePeriodForCurrentMonth();

        [$managerUser] = $this->createUserWithRoleAndProfile('manager', 'Leave Manager');
        [$hrUser] = $this->createUserWithRoleAndProfile('hr', 'Leave HR');

        $team = Team::factory()->active()->create([
            'team_lead_id' => $managerUser->id,
        ]);

        [$employeeUser, $staffMemberProfile] = $this->createUserWithRoleAndProfile(
            'staff',
            'Leave Employee',
            $team->id
        );

        Sanctum::actingAs($employeeUser);

        $response = $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-20',
            'end_date' => '2026-04-21',
            'reason' => 'Family event',
            'emergency_contact' => '08123456789',
        ])->assertCreated();

        $leaveRequestId = (int) $response->json('data.id');

        $managerNotification = $this->latestNotification($managerUser);
        $this->assertSame('Leave Request Needs Approval', $managerNotification['title']);
        $this->assertSame($leaveRequestId, (int) ($managerNotification['data']['leave_request_id'] ?? 0));
        $this->assertSame($staffMemberProfile->id, (int) ($managerNotification['data']['staff_member_id'] ?? 0));

        $hrNotification = $this->latestNotification($hrUser);
        $this->assertSame('Leave Request Needs Approval', $hrNotification['title']);
        $this->assertSame($leaveRequestId, (int) ($hrNotification['data']['leave_request_id'] ?? 0));

        $employeeNotification = $this->latestNotification($employeeUser);
        $this->assertSame('Leave Request Submitted', $employeeNotification['title']);
        $this->assertSame($leaveRequestId, (int) ($employeeNotification['data']['leave_request_id'] ?? 0));
    }

    public function test_employee_proof_upload_notifies_related_manager_and_hr(): void
    {
        Storage::fake('public');

        [$managerUser] = $this->createUserWithRoleAndProfile('manager', 'Proof Manager');
        [$hrUser] = $this->createUserWithRoleAndProfile('hr', 'Proof HR');

        $team = Team::factory()->active()->create([
            'team_lead_id' => $managerUser->id,
        ]);

        [$employeeUser, $staffMemberProfile] = $this->createUserWithRoleAndProfile(
            'staff',
            'Proof Employee',
            $team->id
        );

        $leaveRequest = LeaveRequest::create([
            'staff_member_id' => $staffMemberProfile->id,
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-15',
            'end_date' => '2026-04-15',
            'total_days' => 1,
            'reason' => 'Medical checkup',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($employeeUser);

        $this->post(
            "/api/v1/leave-requests/{$leaveRequest->id}/proof",
            [
                'proof_file' => UploadedFile::fake()->create('fit-note.pdf', 120, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        )->assertOk();

        $managerNotification = $this->latestNotification($managerUser);
        $this->assertSame('Sick Leave Proof Uploaded', $managerNotification['title']);
        $this->assertSame($leaveRequest->id, (int) ($managerNotification['data']['leave_request_id'] ?? 0));
        $this->assertSame('fit-note.pdf', (string) ($managerNotification['data']['proof_file_name'] ?? ''));

        $hrNotification = $this->latestNotification($hrUser);
        $this->assertSame('Sick Leave Proof Uploaded', $hrNotification['title']);
        $this->assertSame($leaveRequest->id, (int) ($hrNotification['data']['leave_request_id'] ?? 0));
    }

    public function test_hr_proof_review_notifies_related_employee(): void
    {
        [$hrUser] = $this->createUserWithRoleAndProfile('hr', 'Reviewer HR');
        [$employeeUser, $staffMemberProfile] = $this->createUserWithRoleAndProfile('staff', 'Reviewed Employee');

        $leaveRequest = LeaveRequest::create([
            'staff_member_id' => $staffMemberProfile->id,
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-10',
            'total_days' => 1,
            'reason' => 'Sick leave',
            'status' => 'approved',
            'proof_file_path' => 'leave-proofs/proof.pdf',
            'proof_file_name' => 'proof.pdf',
            'proof_mime_type' => 'application/pdf',
            'proof_size_kb' => 128,
            'proof_uploaded_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($hrUser);

        $this->postJson("/api/v1/leave-requests/{$leaveRequest->id}/proof-review", [
            'proof_review_status' => 'approved',
            'proof_review_notes' => 'Validated by HR',
        ])->assertOk();

        $employeeNotification = $this->latestNotification($employeeUser);
        $this->assertSame('Sick Leave Proof Approved', $employeeNotification['title']);
        $this->assertSame($leaveRequest->id, (int) ($employeeNotification['data']['leave_request_id'] ?? 0));
        $this->assertSame('approved', (string) ($employeeNotification['data']['review_status'] ?? ''));
    }

    /**
     * @return array{0: User, 1: StaffMemberProfile}
     */
    private function createUserWithRoleAndProfile(string $role, string $name, ?int $teamId = null): array
    {
        $sequence = $this->profileSequence++;

        $user = User::factory()->create([
            'name' => $name,
            'email' => sprintf('%s.%d@example.test', str_replace(' ', '.', strtolower($role)), $sequence),
        ]);
        $user->syncRoles([$role]);

        $profile = StaffMemberProfile::withoutSyncingToSearch(function () use ($user, $role, $sequence, $teamId) {
            $profile = StaffMemberProfile::factory()->forUser($user)->create([
                'code' => sprintf('%s%03d', strtoupper(substr($role, 0, 3)), $sequence),
                'identity_number' => str_pad((string) (89000000000000 + $sequence), 14, '0', STR_PAD_LEFT),
            ]);

            $profile->jobInformation()->create([
                'job_title' => 'Software Engineer',
                'team_id' => $teamId,
                'years_experience' => 3,
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'remote',
                'start_date' => now()->subYear()->toDateString(),
                'monthly_salary' => 10000000,
                'skill_level' => 'intermediate',
            ]);

            return $profile;
        });

        return [$user, $profile];
    }

    /**
     * @return array<string, mixed>
     */
    private function latestNotification(User $user): array
    {
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/my-notifications?limit=10')
            ->assertOk();

        $payload = $response->json('data');

        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);

        return $payload[0];
    }

    private function openAttendancePeriodForCurrentMonth(): void
    {
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        AttendancePeriod::create([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cutoff_date' => now()->endOfMonth()->toDateString(),
            'status' => AttendancePeriod::STATUS_OPEN,
        ]);
    }
}
