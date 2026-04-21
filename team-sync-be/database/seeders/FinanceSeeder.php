<?php

namespace Database\Seeders;

use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->purgeLegacyDemoAccount('finance@gmail.com');

        $employee = User::withTrashed()->updateOrCreate(
            ['email' => 'dwimeta@teamsync.com'],
            [
                'name' => 'Dwimeta',
                'password' => bcrypt('teamsync'),
                'profile_photo' => 'profile-pictures/female/1.avif',
                'deleted_at' => null,
            ]
        );

        $staffMemberProfile = StaffMemberProfile::withTrashed()->updateOrCreate([
            'code' => 'FIN001',
        ], [
            'user_id' => $employee->id,
            'identity_number' => '333434141',
            'phone' => '081234567891',
            'date_of_birth' => '1995-05-15',
            'gender' => 'female',
            'place_of_birth' => 'Jakarta',
            'address' => 'Jl. Thamrin No. 5',
            'city' => 'Jakarta',
            'postal_code' => '10350',
            'deleted_at' => null,
        ]);

        $staffMemberProfile->jobInformation()->updateOrCreate([
            'employee_id' => $staffMemberProfile->id,
        ], [
            'employee_id' => $staffMemberProfile->id,
            'job_title' => 'Finance Manager',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2024-01-01',
            'monthly_salary' => 12000000,
        ]);

        $staffMemberProfile->bankInformation()->updateOrCreate([
            'employee_id' => $staffMemberProfile->id,
        ], [
            'employee_id' => $staffMemberProfile->id,
            'bank_name' => 'bca',
            'account_number' => '9876543210',
            'account_holder_name' => 'Dwimeta',
        ]);

        $staffMemberProfile->emergencyContacts()->updateOrCreate([
            'employee_id' => $staffMemberProfile->id,
            'email' => 'dwimeta.emergency@teamsync.com',
        ], [
            'employee_id' => $staffMemberProfile->id,
            'full_name' => 'Dwimeta Emergency Contact',
            'phone' => '081234567891',
            'relationship' => 'Family',
            'email' => 'dwimeta.emergency@teamsync.com',
        ]);

        $employee->syncRoles(['finance']);
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
