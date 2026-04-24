<?php

namespace Tests\Feature\Notification;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_my_notifications_endpoint(): void
    {
        $this->getJson('/api/v1/my-notifications')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_access_my_notifications_unread_count_endpoint(): void
    {
        $this->getJson('/api/v1/my-notifications/unread-count')
            ->assertUnauthorized();
    }

    public function test_it_returns_latest_notifications_for_authenticated_user_with_default_limit(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $baseTime = CarbonImmutable::create(2026, 4, 13, 10, 0, 0, 'UTC');

        for ($index = 1; $index <= 7; $index++) {
            $this->createNotification($user, "Notif {$index}", $baseTime->addMinutes($index));
        }

        $this->createNotification($otherUser, 'Other user notification', $baseTime->addMinutes(99));

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/my-notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Notifications Retrieved Successfully')
            ->assertJsonCount(5, 'data');

        $payload = $response->json('data');

        $this->assertIsArray($payload);
        $this->assertSame('Notif 7', $payload[0]['title']);
        $this->assertSame('Notif 3', $payload[4]['title']);
        $this->assertFalse(collect($payload)->contains(fn (array $item): bool => $item['title'] === 'Other user notification'));
    }

    public function test_it_respects_custom_limit_parameter(): void
    {
        $user = User::factory()->create();
        $baseTime = CarbonImmutable::create(2026, 4, 13, 11, 0, 0, 'UTC');

        for ($index = 1; $index <= 4; $index++) {
            $this->createNotification($user, "Limit {$index}", $baseTime->addMinutes($index));
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/my-notifications?limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $payload = $response->json('data');

        $this->assertSame('Limit 4', $payload[0]['title']);
        $this->assertSame('Limit 3', $payload[1]['title']);
    }

    public function test_it_validates_limit_parameter_range(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/my-notifications?limit=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_it_returns_unread_count_for_authenticated_user_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $baseTime = CarbonImmutable::create(2026, 4, 13, 11, 30, 0, 'UTC');

        $this->createNotification($user, 'Unread 1', $baseTime->addMinutes(1));
        $this->createNotification($user, 'Unread 2', $baseTime->addMinutes(2));
        $this->createNotification($user, 'Read 1', $baseTime->addMinutes(3), $baseTime->addMinutes(4));
        $this->createNotification($otherUser, 'Other user unread', $baseTime->addMinutes(5));

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/my-notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Unread Notification Count Retrieved Successfully')
            ->assertJsonPath('data.unread_count', 2);

        $this->assertIsInt($response->json('data.unread_count'));
    }

    public function test_unauthenticated_user_cannot_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notificationId = $this->createNotification(
            $user,
            'Unread notification',
            CarbonImmutable::create(2026, 4, 13, 12, 0, 0, 'UTC')
        );

        $this->postJson("/api/v1/my-notifications/{$notificationId}/mark-as-read")
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notificationId = $this->createNotification(
            $user,
            'Needs read',
            CarbonImmutable::create(2026, 4, 13, 12, 15, 0, 'UTC')
        );

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/my-notifications/{$notificationId}/mark-as-read")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Notification Marked As Read')
            ->assertJsonPath('data.id', $notificationId)
            ->assertJsonPath('data.is_read', true);

        $storedNotification = $user->notifications()->findOrFail($notificationId);
        $this->assertNotNull($storedNotification->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notificationId = $this->createNotification(
            $otherUser,
            'Other owner notification',
            CarbonImmutable::create(2026, 4, 13, 12, 30, 0, 'UTC')
        );

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/my-notifications/{$notificationId}/mark-as-read")
            ->assertNotFound()
            ->assertJsonPath('message', 'Notification Not Found');
    }

    private function createNotification(
        User $user,
        string $title,
        CarbonImmutable $createdAt,
        ?CarbonImmutable $readAt = null
    ): string {
        $notificationId = (string) Str::uuid();

        $user->notifications()->create([
            'id' => $notificationId,
            'type' => 'App\\Notifications\\PayrollPaid',
            'data' => [
                'title' => $title,
                'body' => "Body for {$title}",
                'action_url' => '/admin/my-payroll/1',
                'category' => 'payroll',
            ],
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $notificationId;
    }
}
