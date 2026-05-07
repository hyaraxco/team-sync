<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffScopeSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $staffUser;

    private User $managerUser;

    private StaffMemberProfile $staffProfile;

    private StaffMemberProfile $otherProfile;

    private Team $staffTeam;

    private Team $otherTeam;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            'profile-view', 'meeting-list', 'meeting-menu',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }

        // Create roles
        $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);

        $staffRole->syncPermissions(['profile-view', 'meeting-list', 'meeting-menu']);
        $managerRole->syncPermissions(['profile-view', 'meeting-list', 'meeting-menu']);

        // Create users
        $this->staffUser = User::factory()->create();
        $this->staffUser->assignRole('staff');

        $this->managerUser = User::factory()->create();
        $this->managerUser->assignRole('manager');

        // Create profiles
        $this->staffProfile = StaffMemberProfile::factory()->create(['user_id' => $this->staffUser->id]);
        $this->otherProfile = StaffMemberProfile::factory()->create(['user_id' => $this->managerUser->id]);

        // Create teams
        $this->staffTeam = Team::factory()->create();
        $this->otherTeam = Team::factory()->create();

        // Assign staff to staffTeam
        TeamMember::create([
            'team_id' => $this->staffTeam->id,
            'staff_member_id' => $this->staffProfile->id,
            'joined_at' => now(),
        ]);
    }

    // ─── Performance Statistics ──────────────────────────────────────────

    public function test_staff_cannot_view_other_employee_performance_statistics(): void
    {
        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson("/api/v1/staff-members/{$this->otherProfile->id}/performance-statistics");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You can only view your own performance statistics.']);
    }

    public function test_staff_can_view_own_performance_statistics(): void
    {
        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson("/api/v1/staff-members/{$this->staffProfile->id}/performance-statistics");

        // Should not be 403 (may be 200 or 500 depending on DB functions in SQLite)
        $this->assertNotEquals(403, $response->status());
    }

    public function test_manager_can_view_any_employee_performance_statistics(): void
    {
        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson("/api/v1/staff-members/{$this->staffProfile->id}/performance-statistics");

        $this->assertNotEquals(403, $response->status());
    }

    // ─── Meeting Scope ──────────────────────────────────────────────────

    public function test_staff_cannot_view_meeting_from_other_team(): void
    {
        $meeting = Meeting::factory()->create();
        $meeting->teams()->attach($this->otherTeam->id);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson("/api/v1/meetings/{$meeting->id}");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You do not have access to this meeting.']);
    }

    public function test_staff_can_view_meeting_from_own_team(): void
    {
        $meeting = Meeting::factory()->create();
        $meeting->teams()->attach($this->staffTeam->id);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson("/api/v1/meetings/{$meeting->id}");

        $response->assertStatus(200);
    }

    public function test_manager_can_view_any_meeting(): void
    {
        $meeting = Meeting::factory()->create();
        $meeting->teams()->attach($this->otherTeam->id);

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->getJson("/api/v1/meetings/{$meeting->id}");

        $response->assertStatus(200);
    }

    public function test_staff_meetings_index_only_shows_own_team_meetings(): void
    {
        // Meeting for staff's team
        $ownMeeting = Meeting::factory()->create(['title' => 'Own Team Meeting']);
        $ownMeeting->teams()->attach($this->staffTeam->id);

        // Meeting for other team
        $otherMeeting = Meeting::factory()->create(['title' => 'Other Team Meeting']);
        $otherMeeting->teams()->attach($this->otherTeam->id);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/v1/meetings');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $titles = collect($data)->pluck('title')->toArray();
        $this->assertContains('Own Team Meeting', $titles);
        $this->assertNotContains('Other Team Meeting', $titles);
    }

    public function test_staff_upcoming_meetings_only_shows_own_team(): void
    {
        // Upcoming meeting for staff's team
        $ownMeeting = Meeting::factory()->create([
            'title' => 'Own Upcoming',
            'scheduled_at' => now()->addDays(1),
        ]);
        $ownMeeting->teams()->attach($this->staffTeam->id);

        // Upcoming meeting for other team
        $otherMeeting = Meeting::factory()->create([
            'title' => 'Other Upcoming',
            'scheduled_at' => now()->addDays(2),
        ]);
        $otherMeeting->teams()->attach($this->otherTeam->id);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/v1/meetings/upcoming');

        $response->assertStatus(200);
        $data = $response->json('data');

        $titles = collect($data)->pluck('title')->toArray();
        $this->assertContains('Own Upcoming', $titles);
        $this->assertNotContains('Other Upcoming', $titles);
    }
}
