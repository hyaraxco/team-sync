<?php

namespace Database\Seeders\E2E;

use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Deterministic Superadmin employee for role E2E coverage.
 *
 * E2E-only: run after RolePermissionSeeder so the superadmin role exists with its permissions.
 * Never registered in DatabaseSeeder, DemoDataSeeder or production seed paths.
 */
class E2ESuperadminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('E2ESuperadminSeeder is restricted to local or testing environments.');
        }

        $user = User::withTrashed()->updateOrCreate(
            ['email' => 'superadmin@teamsync.com'],
            [
                'name' => 'Superadmin',
                'password' => bcrypt('teamsync'),
                'deleted_at' => null,
            ]
        );

        $profile = StaffMemberProfile::withTrashed()->updateOrCreate([
            'code' => 'SUP001',
        ], [
            'user_id' => $user->id,
            'identity_number' => '444545151',
            'phone' => '081234567892',
            'date_of_birth' => '1990-06-01',
            'gender' => 'male',
            'place_of_birth' => 'Jakarta',
            'address' => 'Jl. Sudirman No. 2',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'deleted_at' => null,
        ]);

        $profile->jobInformation()->updateOrCreate([
            'staff_member_id' => $profile->id,
        ], [
            'staff_member_id' => $profile->id,
            'job_title' => 'System Administrator',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2024-01-01',
            'monthly_salary' => 15000000,
        ]);

        $profile->bankInformation()->updateOrCreate([
            'staff_member_id' => $profile->id,
        ], [
            'staff_member_id' => $profile->id,
            'bank_name' => 'bca',
            'account_number' => '555656161',
            'account_holder_name' => 'Superadmin',
        ]);

        // RBAC comes from the role, never from job_title.
        $user->syncRoles(['superadmin']);
    }
}
