<?php

namespace Tests\Feature\Auth;

use App\Interfaces\AuthRepositoryInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression tests: API responses must never leak raw internal exception messages.
 *
 * These tests mock repository methods to throw unexpected exceptions and verify
 * that the controller returns a generic error message, not the raw exception text.
 */
class ErrorHandlingSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const INTERNAL_SECRET = 'SQLSTATE[HY000]: Connection refused to db-host-internal:3306';

    public function test_me_endpoint_does_not_leak_exception_message(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $mock = $this->mock(AuthRepositoryInterface::class);
        $mock->shouldReceive('me')
            ->once()
            ->andThrow(new \RuntimeException(self::INTERNAL_SECRET));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'AuthController::me error'));

        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(500);
        $response->assertJsonFragment(['message' => 'Internal Server Error']);
        $this->assertStringNotContainsString(self::INTERNAL_SECRET, $response->getContent());
    }

    public function test_logout_endpoint_does_not_leak_exception_message(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $mock = $this->mock(AuthRepositoryInterface::class);
        $mock->shouldReceive('logout')
            ->once()
            ->andThrow(new \RuntimeException(self::INTERNAL_SECRET));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'AuthController::logout error'));

        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(500);
        $response->assertJsonFragment(['message' => 'Internal Server Error']);
        $this->assertStringNotContainsString(self::INTERNAL_SECRET, $response->getContent());
    }

    public function test_update_profile_500_does_not_leak_exception_message(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $mock = $this->mock(AuthRepositoryInterface::class);
        $mock->shouldReceive('updateProfile')
            ->once()
            ->andThrow(new \RuntimeException(self::INTERNAL_SECRET, 0));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'AuthController::updateProfile error'));

        $response = $this->putJson('/api/v1/me', [
            'name' => 'Test User',
        ]);

        $response->assertStatus(500);
        $response->assertJsonFragment(['message' => 'Internal Server Error']);
        $this->assertStringNotContainsString(self::INTERNAL_SECRET, $response->getContent());
    }

    public function test_update_profile_domain_error_returns_safe_message(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $mock = $this->mock(AuthRepositoryInterface::class);
        $mock->shouldReceive('updateProfile')
            ->once()
            ->andThrow(new \Exception('Unauthorized', 401));

        $response = $this->putJson('/api/v1/me', [
            'name' => 'Test User',
        ]);

        // Domain error (401) — message is allowed since it's controlled
        $response->assertStatus(401);
        $response->assertJsonFragment(['message' => 'Unauthorized']);
    }

    public function test_login_invalid_credentials_does_not_leak_internals(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        // Should not contain SQL, stack trace, or file paths
        $content = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('Stack trace', $content);
        $this->assertStringNotContainsString('/app/', $content);
    }
}
