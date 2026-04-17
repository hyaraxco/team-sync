<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->purgeLegacyDemoAccount('employee@gmail.com');

        $employee = User::withTrashed()->updateOrCreate(
            ['email' => 'agung@teamsync.com'],
            [
                'name' => 'Agung Ramadhan',
                'password' => bcrypt('teamsync'),
                'profile_photo' => 'profile-pictures/male/2.avif',
                'deleted_at' => null,
            ]
        );

        $employeeProfile = EmployeeProfile::withTrashed()->updateOrCreate([
            'code' => 'EMP001',
        ], [
            'user_id' => $employee->id,
            'identity_number' => '1234567890',
            'npwp' => '02.345.678.9-012.000',
            'bpjs_ketenagakerjaan' => '11223344556',
            'bpjs_kesehatan' => '1122334455667',
            'ptkp_status' => 'TK/0',
            'phone' => '085325483259',
            'date_of_birth' => '2000-01-01',
            'gender' => 'male',
            'religion' => 'islam',
            'marital_status' => 'single',
            'blood_type' => 'A',
            'place_of_birth' => 'Jakarta',
            'address' => 'Jl. Sudirman No. 1',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'deleted_at' => null,
        ]);

        $employeeProfile->jobInformation()->updateOrCreate([
            'employee_id' => $employeeProfile->id,
        ], [
            'employee_id' => $employeeProfile->id,
            'job_title' => 'Software Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'remote',
            'start_date' => '2024-01-01',
            'monthly_salary' => 10000000,
        ]);

        $employeeProfile->bankInformation()->updateOrCreate([
            'employee_id' => $employeeProfile->id,
        ], [
            'employee_id' => $employeeProfile->id,
            'bank_name' => 'bca',
            'account_number' => '1234567890',
            'account_holder_name' => 'Agung Ramadhan',
        ]);

        $employeeProfile->emergencyContacts()->updateOrCreate([
            'employee_id' => $employeeProfile->id,
            'email' => 'agung.emergency@teamsync.com',
        ], [
            'employee_id' => $employeeProfile->id,
            'full_name' => 'Agung Emergency Contact',
            'phone' => '081234567890',
            'relationship' => 'Family',
            'email' => 'agung.emergency@teamsync.com',
        ]);

        $employee->syncRoles(['employee']);
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
