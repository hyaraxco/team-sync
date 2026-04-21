<?php

namespace Database\Seeders;

use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class ManagerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->purgeLegacyDemoAccount('manager@gmail.com');

        $manager = User::withTrashed()->updateOrCreate(
            ['email' => 'yudhis@teamsync.com'],
            [
                'name' => 'Yudhis',
                'password' => bcrypt('teamsync'),
                'profile_photo' => 'profile-pictures/male/1.avif',
                'deleted_at' => null,
            ]
        );

        $staffMemberProfile = StaffMemberProfile::withTrashed()->updateOrCreate([
            'code' => 'MGR001',
        ], [
            'user_id' => $manager->id,
            'identity_number' => '111212121',
            'npwp' => '01.234.567.8-901.000',
            'bpjs_ketenagakerjaan' => '00112233445',
            'bpjs_kesehatan' => '0001122334455',
            'ptkp_status' => 'K/1',
            'phone' => '081234567899',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'religion' => 'islam',
            'marital_status' => 'married',
            'blood_type' => 'O',
            'place_of_birth' => 'Jakarta',
            'address' => 'Jl. Gatot Subroto No. 1',
            'city' => 'Jakarta',
            'postal_code' => '12950',
            'deleted_at' => null,
        ]);

        // Ensure seeded bank account number stays unique globally.
        $managerAccountNumber = '9'.str_pad((string) $staffMemberProfile->id, 9, '0', STR_PAD_LEFT);

        $staffMemberProfile->jobInformation()->updateOrCreate([
            'employee_id' => $staffMemberProfile->id,
        ], [
            'employee_id' => $staffMemberProfile->id,
            'job_title' => 'Manager',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2024-01-01',
            'monthly_salary' => 15000000,
        ]);

        $staffMemberProfile->bankInformation()->updateOrCreate([
            'employee_id' => $staffMemberProfile->id,
        ], [
            'employee_id' => $staffMemberProfile->id,
            'bank_name' => 'bca',
            'account_number' => $managerAccountNumber,
            'account_holder_name' => 'Yudhis',
        ]);

        $staffMemberProfile->emergencyContacts()->updateOrCreate([
            'employee_id' => $staffMemberProfile->id,
            'email' => 'yudhis.emergency@teamsync.com',
        ], [
            'employee_id' => $staffMemberProfile->id,
            'full_name' => 'Yudhis Emergency Contact',
            'phone' => '081234567899',
            'relationship' => 'Family',
            'email' => 'yudhis.emergency@teamsync.com',
        ]);

        $manager->syncRoles(['manager']);
    }

    private function purgeLegacyDemoAccount(string $email): void
    {
        $legacyUser = User::withTrashed()->where('email', $email)->first();

        if (! $legacyUser) {
            return;
        }

        $legacyUser->forceDelete();
    }
}
