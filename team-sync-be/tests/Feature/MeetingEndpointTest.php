<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Jobs\BroadcastMeetingJob;
use App\Models\Meeting;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MeetingEndpointTest extends TestCase
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

        Queue::fake();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unauthenticated_user_cannot_access_meetings(): void
    {
        $this->getJson('/api/v1/meetings')
            ->assertUnauthorized();
    }

    public function test_staff_cannot_create_meeting(): void
    {
        $this->actingAsRole('staff');

        $this->postJson('/api/v1/meetings', $this->validPayload())
            ->assertForbidden();
    }

    public function test_hr_can_create_meeting(): void
    {
        $hr = $this->actingAsRole('hr');
        $payload = $this->validPayload();

        $response = $this->postJson('/api/v1/meetings', $payload)
            ->assertStatus(201);

        $meetingId = $response->json('data.id');

        $this->assertDatabaseHas('meetings', [
            'id' => $meetingId,
            'title' => $payload['title'],
            'created_by' => $hr->id,
        ]);

        Queue::assertPushedOn('meetings', BroadcastMeetingJob::class, function (BroadcastMeetingJob $job) use ($meetingId) {
            return $job->meetingId === $meetingId
                && $job->notificationType === 'scheduled'
                && $job->timeout === 300
                && $job->tries === 3;
        });
    }

    public function test_create_meeting_validates_required_fields(): void
    {
        $this->actingAsRole('hr');

        $payload = $this->validPayload();
        unset($payload['title']);

        $this->postJson('/api/v1/meetings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_meeting_validates_scheduled_at_is_future(): void
    {
        $this->actingAsRole('hr');

        $payload = $this->validPayload([
            'scheduled_at' => Carbon::now()->subMinute()->toDateTimeString(),
        ]);

        $this->postJson('/api/v1/meetings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_at']);
    }

    public function test_create_meeting_validates_department_values(): void
    {
        $this->actingAsRole('hr');

        $payload = $this->validPayload([
            'departments' => ['invalid-department'],
        ]);

        $this->postJson('/api/v1/meetings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['departments.0']);
    }

    public function test_create_meeting_with_teams_creates_pivot(): void
    {
        $this->actingAsRole('hr');

        $firstTeam = Team::factory()->create();
        $secondTeam = Team::factory()->create();

        $response = $this->postJson('/api/v1/meetings', $this->validPayload([
            'team_ids' => [$firstTeam->id, $secondTeam->id],
        ]))->assertStatus(201);

        $meetingId = $response->json('data.id');

        $this->assertDatabaseHas('meeting_team', [
            'meeting_id' => $meetingId,
            'team_id' => $firstTeam->id,
        ]);

        $this->assertDatabaseHas('meeting_team', [
            'meeting_id' => $meetingId,
            'team_id' => $secondTeam->id,
        ]);
    }

    public function test_hr_can_list_meetings_paginated(): void
    {
        $hr = $this->actingAsRole('hr');

        Meeting::factory()->count(3)->create([
            'created_by' => $hr->id,
        ]);

        $this->getJson('/api/v1/meetings/all/paginated?row_per_page=10')
            ->assertOk();
    }

    public function test_manager_can_list_meetings_with_meeting_list_permission(): void
    {
        $this->actingAsRole('manager');

        $this->getJson('/api/v1/meetings/all/paginated?row_per_page=10')
            ->assertOk();
    }

    public function test_any_role_can_view_upcoming_meetings(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/meetings/upcoming')
            ->assertOk();
    }

    public function test_hr_can_view_single_meeting(): void
    {
        $hr = $this->actingAsRole('hr');

        $meeting = Meeting::factory()->create([
            'created_by' => $hr->id,
        ]);

        $this->getJson("/api/v1/meetings/{$meeting->id}")
            ->assertOk();
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Quarterly Planning Meeting',
            'description' => 'Discuss roadmap and milestones.',
            'scheduled_at' => Carbon::now()->addDay()->toDateTimeString(),
            'duration_minutes' => 60,
            'location' => 'https://meet.example.com/room-1',
            'departments' => [Department::DEVELOPMENT->value, Department::DESIGN->value],
        ], $overrides);
    }
}
