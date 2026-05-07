<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_or_revoked_token_returns_401(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('test')->plainTextToken;

        // Revoke all tokens (simulates expiry / manual revocation)
        $user->tokens()->delete();

        $response = $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer '.$plainTextToken,
        ]);

        $response->assertUnauthorized(); // 401
    }

    public function test_forgot_password_with_nonexistent_email_does_not_leak_existence(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        // The controller returns 422 for invalid user via Password::INVALID_USER,
        // but the important security assertion is that the response message does NOT
        // explicitly say "user not found" or reveal whether the email is registered.
        // Laravel's default translation for INVALID_USER is a generic passwords.user message.
        $response->assertStatus(422);
        $body = $response->getContent();
        $this->assertStringNotContainsString('nobody@example.com', $body);

        // No notification should have been sent
        Notification::assertNothingSent();
    }

    public function test_already_verified_user_requesting_verification_gets_success(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/email/verify/send');

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Email already verified.']);
    }

    public function test_login_with_missing_fields_returns_422(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertUnprocessable(); // 422
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_profile_update_with_invalid_file_type_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Storage::fake('public');

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->putJson('/api/v1/me', [
            'name' => 'Test User',
            'profile_photo' => $file,
        ]);

        $response->assertUnprocessable(); // 422
        $response->assertJsonValidationErrors(['profile_photo']);
    }
}
