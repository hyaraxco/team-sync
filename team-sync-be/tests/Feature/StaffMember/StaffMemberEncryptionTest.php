<?php

namespace Tests\Feature\StaffMember;

use App\Models\BankInformation;
use App\Models\StaffMemberProfile;
use App\Support\SensitiveData;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffMemberEncryptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_staff_member_profile_encrypted_fields_are_not_stored_as_plaintext(): void
    {
        $plaintextIdentity = '3201012345678901';
        $plaintextNpwp = '12.345.678.9-012.345';
        $plaintextBpjsTk = '12345678901';
        $plaintextBpjsKs = '1234567890123';

        $profile = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->create([
            'identity_number' => $plaintextIdentity,
            'npwp' => $plaintextNpwp,
            'bpjs_ketenagakerjaan' => $plaintextBpjsTk,
            'bpjs_kesehatan' => $plaintextBpjsKs,
        ]));

        $raw = DB::table('staff_member_profiles')->where('id', $profile->id)->first();

        $this->assertNotSame($plaintextIdentity, $raw->identity_number);
        $this->assertNotSame($plaintextNpwp, $raw->npwp);
        $this->assertNotSame($plaintextBpjsTk, $raw->bpjs_ketenagakerjaan);
        $this->assertNotSame($plaintextBpjsKs, $raw->bpjs_kesehatan);
    }

    public function test_bank_information_encrypted_fields_are_not_stored_as_plaintext(): void
    {
        $plaintextBankName = 'bca';
        $plaintextAccountNumber = '1234567890';
        $plaintextAccountHolder = 'John Doe';

        $profile = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->create());

        $bankInfo = BankInformation::factory()->create([
            'staff_member_id' => $profile->id,
            'bank_name' => $plaintextBankName,
            'account_number' => $plaintextAccountNumber,
            'account_holder_name' => $plaintextAccountHolder,
        ]);

        $raw = DB::table('bank_information')->where('id', $bankInfo->id)->first();

        $this->assertNotSame($plaintextBankName, $raw->bank_name);
        $this->assertNotSame($plaintextAccountNumber, $raw->account_number);
        $this->assertNotSame($plaintextAccountHolder, $raw->account_holder_name);
    }

    public function test_updated_sensitive_fields_remain_encrypted_in_database(): void
    {
        $profile = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->create([
            'identity_number' => '1111111111111111',
            'npwp' => '11.111.111.1-111.111',
            'bpjs_ketenagakerjaan' => '11111111111',
            'bpjs_kesehatan' => '1111111111111',
        ]));

        $bankInfo = BankInformation::factory()->create([
            'staff_member_id' => $profile->id,
            'bank_name' => 'bri',
            'account_number' => '1111111111',
            'account_holder_name' => 'Original Name',
        ]);

        // Update profile sensitive fields
        $updatedIdentity = '9999999999999999';
        $updatedNpwp = '99.999.999.9-999.999';

        StaffMemberProfile::withoutSyncingToSearch(function () use ($profile, $updatedIdentity, $updatedNpwp) {
            $profile->update([
                'identity_number' => $updatedIdentity,
                'npwp' => $updatedNpwp,
            ]);
        });

        // Update bank sensitive fields
        $updatedAccountNumber = '9999999999';
        $updatedAccountHolder = 'Updated Name';

        $bankInfo->update([
            'account_number' => $updatedAccountNumber,
            'account_holder_name' => $updatedAccountHolder,
        ]);

        $rawProfile = DB::table('staff_member_profiles')->where('id', $profile->id)->first();
        $rawBank = DB::table('bank_information')->where('id', $bankInfo->id)->first();

        $this->assertNotSame($updatedIdentity, $rawProfile->identity_number);
        $this->assertNotSame($updatedNpwp, $rawProfile->npwp);
        $this->assertNotSame($updatedAccountNumber, $rawBank->account_number);
        $this->assertNotSame($updatedAccountHolder, $rawBank->account_holder_name);
    }

    public function test_identity_number_hash_matches_sensitive_data_hash(): void
    {
        $plaintextIdentity = '3201012345678901';

        $profile = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->create([
            'identity_number' => $plaintextIdentity,
        ]));

        $raw = DB::table('staff_member_profiles')->where('id', $profile->id)->first();

        $this->assertSame(
            SensitiveData::hash($plaintextIdentity),
            $raw->identity_number_hash
        );
    }

    public function test_account_number_hash_matches_sensitive_data_hash(): void
    {
        $plaintextAccountNumber = '1234567890';

        $profile = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->create());

        $bankInfo = BankInformation::factory()->create([
            'staff_member_id' => $profile->id,
            'account_number' => $plaintextAccountNumber,
        ]);

        $raw = DB::table('bank_information')->where('id', $bankInfo->id)->first();

        $this->assertSame(
            SensitiveData::hash($plaintextAccountNumber),
            $raw->account_number_hash
        );
    }

    public function test_hash_columns_update_when_sensitive_fields_change(): void
    {
        $profile = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->create([
            'identity_number' => '1111111111111111',
        ]));

        $bankInfo = BankInformation::factory()->create([
            'staff_member_id' => $profile->id,
            'account_number' => '1111111111',
        ]);

        // Update to new values
        $newIdentity = '9999999999999999';
        $newAccountNumber = '9999999999';

        StaffMemberProfile::withoutSyncingToSearch(function () use ($profile, $newIdentity) {
            $profile->update(['identity_number' => $newIdentity]);
        });

        $bankInfo->update(['account_number' => $newAccountNumber]);

        $rawProfile = DB::table('staff_member_profiles')->where('id', $profile->id)->first();
        $rawBank = DB::table('bank_information')->where('id', $bankInfo->id)->first();

        $this->assertSame(SensitiveData::hash($newIdentity), $rawProfile->identity_number_hash);
        $this->assertSame(SensitiveData::hash($newAccountNumber), $rawBank->account_number_hash);
    }
}
