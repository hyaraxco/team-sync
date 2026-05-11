<?php

namespace Tests\Unit\DTOs;

use App\DTOs\UserDto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDtoTest extends TestCase
{
    use RefreshDatabase;

    public function test_from_array_maps_all_fields(): void
    {
        $dto = UserDto::fromArray($this->payload());

        $this->assertSame('John Doe', $dto->name);
        $this->assertSame('john@example.com', $dto->email);
        $this->assertSame('secret123', $dto->password);
        $this->assertSame('users/photo.jpg', $dto->profile_photo);
        $this->assertSame(['admin', 'manager'], $dto->roles);
    }

    public function test_from_array_uses_defaults_for_optional_fields(): void
    {
        $dto = UserDto::fromArray([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'pass',
        ]);

        $this->assertNull($dto->profile_photo);
        $this->assertSame([], $dto->roles);
    }

    public function test_to_array_preserves_payload_shape(): void
    {
        $dto = UserDto::fromArray($this->payload());

        $this->assertSame($this->payload(), $dto->toArray());
    }

    public function test_from_array_for_update_merges_with_existing_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'profile_photo' => 'users/old.jpg',
        ]);

        $dto = UserDto::fromArrayForUpdate([
            'name' => 'New Name',
        ], $user);

        $this->assertSame('New Name', $dto->name);
        $this->assertSame('old@example.com', $dto->email);
        // Password is hashed by the factory, so the original password
        // is not stored — the DTO inherits the hashed value
        $this->assertSame($user->password, $dto->password);
        $this->assertSame('users/old.jpg', $dto->profile_photo);
    }

    public function test_from_array_for_update_allows_setting_profile_photo_to_null(): void
    {
        $user = User::factory()->create([
            'profile_photo' => 'users/old.jpg',
        ]);

        $dto = UserDto::fromArrayForUpdate([
            'profile_photo' => null,
        ], $user);

        $this->assertNull($dto->profile_photo);
    }

    public function test_from_array_for_update_uses_all_provided_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $dto = UserDto::fromArrayForUpdate($this->payload(), $user);

        $this->assertSame('John Doe', $dto->name);
        $this->assertSame('john@example.com', $dto->email);
        $this->assertSame(['admin', 'manager'], $dto->roles);
    }

    private function payload(): array
    {
        return [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
            'profile_photo' => 'users/photo.jpg',
            'roles' => ['admin', 'manager'],
        ];
    }
}
