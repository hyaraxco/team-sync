<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordResetAndEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_link_notification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/v1/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertCount(1);
    }

    public function test_reset_password_updates_password_and_invalidates_old_token(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password-123'),
        ]);

        $token = Password::broker()->createToken($user);
        Sanctum::actingAs($user);
        $this->assertNotNull($user->createToken('old-token')->plainTextToken);

        $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(password_verify('new-password-123', $user->fresh()->password));
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable();
    }

    public function test_authenticated_user_can_request_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/email/verify/send')
            ->assertOk();

        Notification::assertCount(1);
    }

    public function test_guest_can_request_verification_email_by_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/v1/email/verification-notification', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertCount(1);
    }

    public function test_signed_verification_link_marks_email_as_verified(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $this->get($url)
            ->assertRedirect('http://localhost:5173/auth/verify-email?status=success');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_invalid_verification_link_redirects_with_invalid_status(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get('/api/v1/email/verify/'.$user->id.'/invalid-hash')
            ->assertRedirect('http://localhost:5173/auth/verify-email?status=invalid');
    }
}
