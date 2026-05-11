<?php

use App\Models\BankInformation;
use App\Models\StaffMemberProfile;
use App\Support\SensitiveData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
});

it('boot event generates account_number_hash on save', function () {
    $bank = BankInformation::factory()->create([
        'account_number' => '1234567890',
    ]);

    expect($bank->account_number_hash)->not->toBeNull()
        ->and($bank->account_number_hash)->toBe(hash('sha256', '1234567890'));
});

it('account_number_hash is null when account_number is null', function () {
    $bank = BankInformation::factory()->create([
        'account_number' => null,
    ]);

    expect($bank->account_number_hash)->toBeNull();
});

it('account_number_hash changes when account_number changes', function () {
    $bank = BankInformation::factory()->create([
        'account_number' => '1234567890',
    ]);

    $originalHash = $bank->account_number_hash;

    $bank->update(['account_number' => '9876543210']);
    $bank->refresh();

    expect($bank->account_number_hash)->not->toBe($originalHash)
        ->and($bank->account_number_hash)->toBe(hash('sha256', '9876543210'));
});

it('account_number_hash uses SensitiveData::hash for hashing', function () {
    $accountNumber = '5551234567';
    $bank = BankInformation::factory()->create([
        'account_number' => $accountNumber,
    ]);

    $expectedHash = SensitiveData::hash($accountNumber);

    expect($bank->account_number_hash)->toBe($expectedHash);
});

it('account_number_hash is null when account_number is empty string', function () {
    $bank = BankInformation::factory()->create([
        'account_number' => '',
    ]);

    // SensitiveData::hash returns null for empty strings after trimming
    expect($bank->account_number_hash)->toBeNull();
});

it('account_number_hash trims whitespace before hashing', function () {
    $bank = BankInformation::factory()->create([
        'account_number' => '  1234567890  ',
    ]);

    // SensitiveData::hash trims the value before hashing
    expect($bank->account_number_hash)->toBe(hash('sha256', '1234567890'));
});
