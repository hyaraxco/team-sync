<?php

use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Support\SensitiveData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
});

it('boot event generates identity_number_hash on save', function () {
    $profile = StaffMemberProfile::factory()->create([
        'identity_number' => '3201234567890001',
    ]);

    expect($profile->identity_number_hash)->not->toBeNull()
        ->and($profile->identity_number_hash)->toBe(hash('sha256', '3201234567890001'));
});

it('identity_number_hash is null when identity_number is null', function () {
    $profile = StaffMemberProfile::factory()->create([
        'identity_number' => null,
    ]);

    expect($profile->identity_number_hash)->toBeNull();
});

it('identity_number_hash changes when identity_number changes', function () {
    $profile = StaffMemberProfile::factory()->create([
        'identity_number' => '3201234567890001',
    ]);

    $originalHash = $profile->identity_number_hash;

    $profile->update(['identity_number' => '3201234567899999']);
    $profile->refresh();

    expect($profile->identity_number_hash)->not->toBe($originalHash)
        ->and($profile->identity_number_hash)->toBe(hash('sha256', '3201234567899999'));
});

it('getFullNameAttribute returns user name', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso']);
    $user->assignRole('staff');

    $profile = StaffMemberProfile::factory()->forUser($user)->create();

    expect($profile->full_name)->toBe('Budi Santoso');
});

it('getEmailAttribute returns user email', function () {
    $user = User::factory()->create(['email' => 'budi@example.com']);
    $user->assignRole('staff');

    $profile = StaffMemberProfile::factory()->forUser($user)->create();

    expect($profile->email)->toBe('budi@example.com');
});

it('getFullNameAttribute returns null when user is missing', function () {
    $profile = new StaffMemberProfile();
    expect($profile->full_name)->toBeNull();
});

it('getEmailAttribute returns null when user is missing', function () {
    $profile = new StaffMemberProfile();
    expect($profile->email)->toBeNull();
});

it('identity_number_hash uses SensitiveData::hash for hashing', function () {
    $identity = '3201234567890001';
    $profile = StaffMemberProfile::factory()->create([
        'identity_number' => $identity,
    ]);

    $expectedHash = SensitiveData::hash($identity);

    expect($profile->identity_number_hash)->toBe($expectedHash);
});

it('identity_number_hash is null when identity_number is empty string', function () {
    $profile = StaffMemberProfile::factory()->create([
        'identity_number' => '',
    ]);

    // SensitiveData::hash returns null for empty strings after trimming
    expect($profile->identity_number_hash)->toBeNull();
});
