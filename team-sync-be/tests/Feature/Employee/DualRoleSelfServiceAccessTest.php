<?php

namespace Tests\Feature\Employee;

use App\Models\AttendancePeriod;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\MinimalPayrollE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class DualRoleSelfServiceAccessTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected Carbon $leaveDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MinimalPayrollE2ESeeder::class);
        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Ensure attendance period covering leave date is open for leave requests
        // (MinimalPayrollE2ESeeder sets attendance_cutoff_day=1 which transitions current period to 'review')
        $leaveDate = now()->addDays(1);
        // Find next weekday (Mon-Fri) to avoid leave_has_no_working_days error
        while ($leaveDate->isWeekend()) {
            $leaveDate->addDay();
        }
        $this->leaveDate = $leaveDate;

        AttendancePeriod::updateOrCreate(
            [
                'start_date' => $leaveDate->copy()->startOfMonth()->toDateString(),
                'end_date' => $leaveDate->copy()->endOfMonth()->toDateString(),
            ],
            [
                'status' => 'open',
                'cutoff_date' => $leaveDate->copy()->endOfMonth()->toDateString(),
            ]
        );
    }

    public function test_internal_admin_roles_can_access_personal_attendance_and_leave_endpoints(): void
    {
        foreach (['tasyia@teamsync.com', 'dwimeta@teamsync.com', 'yudhis@teamsync.com'] as $email) {
            $user = User::where('email', $email)->firstOrFail();

            Sanctum::actingAs($user);

            $this->getJson('/api/v1/my-attendances')
                ->assertOk();

            $this->getJson('/api/v1/my-leave-requests')
                ->assertOk();

            $payload = [
                'leave_type' => 'annual_leave',
                'start_date' => $this->leaveDate->toDateString(),
                'end_date' => $this->leaveDate->toDateString(),
                'reason' => 'Personal leave for '.$email,
                'emergency_contact' => '081234567890',
            ];

            $this->postJson('/api/v1/leave-requests', $payload)
                ->assertCreated();

            $this->assertDatabaseHas('leave_requests', [
                'staff_member_id' => $user->staffMemberProfile->id,
                'reason' => 'Personal leave for '.$email,
            ]);
        }
    }
}
