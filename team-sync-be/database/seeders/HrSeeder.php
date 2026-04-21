<?php

namespace Database\Seeders;

use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->purgeLegacyDemoAccount('hr@gmail.com');

        $employee = User::withTrashed()->updateOrCreate(
            ['email' => 'tasyia@teamsync.com'],
            [
                'name' => 'Tasyia',
                'password' => bcrypt('teamsync'),
                'profile_photo' => 'profile-pictures/male/3.avif',
                'deleted_at' => null,
            ]
        );

        $staffMemberProfile = StaffMemberProfile::withTrashed()->updateOrCreate([
            'code' => 'HR001',
        ], [
            'user_id' => $employee->id,
            'identity_number' => '222323131',
            'phone' => '081234567890',
            'date_of_birth' => '2000-01-01',
            'gender' => 'male',
            'place_of_birth' => 'Jakarta',
            'address' => 'Jl. Sudirman No. 1',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'deleted_at' => null,
        ]);

        $staffMemberProfile->jobInformation()->updateOrCreate([
            'employee_id' => $staffMemberProfile->id,
        ], [
            'employee_id' => $staffMemberProfile->id,
            'job_title' => 'HR Specialist',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'remote',
            'start_date' => '2024-01-01',
            'monthly_salary' => 10000000,
        ]);

        $staffMemberProfile->bankInformation()->updateOrCreate([
            'employee_id' => $staffMemberProfile->id,
        ], [
            'employee_id' => $staffMemberProfile->id,
            'bank_name' => 'bca',
            'account_number' => '244422131',
            'account_holder_name' => 'Tasyia',
        ]);

        $staffMemberProfile->emergencyContacts()->updateOrCreate([
            'employee_id' => $staffMemberProfile->id,
            'email' => 'tasyia.emergency@teamsync.com',
        ], [
            'employee_id' => $staffMemberProfile->id,
            'full_name' => 'Tasyia Emergency Contact',
            'phone' => '081234567890',
            'relationship' => 'Family',
            'email' => 'tasyia.emergency@teamsync.com',
        ]);

        $employee->syncRoles(['hr']);
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
