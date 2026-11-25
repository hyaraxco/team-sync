<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\License;
use App\Models\PayrollSetting;
use App\Models\StaffMemberProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SandboxRoleSeeder extends Seeder
{
    public function run(): void
    {
        $path = realpath((string) config('database.connections.sqlite.database'));
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || $path === false || ! str_starts_with($path, '/scratch/')
            || ! is_file($path) || is_link(config('database.connections.sqlite.database'))) {
            throw new RuntimeException('SandboxRoleSeeder requires a regular SQLite database inside /scratch in testing.');
        }

        if (filesize($path) !== 0) {
            throw new RuntimeException('SandboxRoleSeeder refuses an existing nonempty database.');
        }

        if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
            throw new RuntimeException('SandboxRoleSeeder could not migrate the fresh database.');
        }

        DB::transaction(function () {
            StaffMemberProfile::withoutSyncingToSearch(function () {
                $this->call([
                    PermissionSeeder::class,
                    RoleSeeder::class,
                    RolePermissionSeeder::class,
                    AttendancePolicySeeder::class,
                    LeaveEntitlementSeeder::class,
                    ManagerSeeder::class,
                    EmployeeSeeder::class,
                    HrSeeder::class,
                    FinanceSeeder::class,
                    TaxBracketSeeder::class,
                    BpjsRateSeeder::class,
                    PtkpAmountSeeder::class,
                ]);
            });

            License::create([
                'license_hash' => hash('sha256', 'e2e-test-license-key'),
                'license_key' => 'e2e-test-license-key',
                'company_name' => 'E2E Test Company',
                'contact_email' => 'e2e@teamsync.com',
                'issued_at' => now()->subYear(),
                'expires_at' => now()->addYear(),
                'is_active' => true,
                'features' => ['attendance', 'leave', 'payroll', 'analytics', 'performance', 'overtime', 'thr'],
                'max_users' => 100,
                'current_users' => 4,
                'activated_at' => now(),
                'last_validated_at' => now(),
                'signature' => base64_encode('e2e-fake-signature'),
            ]);
            PayrollSetting::current()->update(['attendance_cutoff_day' => 1]);

            foreach (StaffMemberProfile::all() as $profile) {
                $day = now()->startOfMonth();
                $end = now()->endOfMonth();
                while ($day->lte($end)) {
                    if (! $day->isWeekend()) {
                        Attendance::create([
                            'staff_member_id' => $profile->id,
                            'date' => $day->toDateString(),
                            'check_in' => $day->format('Y-m-d').' 08:00:00',
                            'check_out' => $day->format('Y-m-d').' 17:00:00',
                            'status' => 'present',
                            'notes' => 'Sandbox payroll role fixture',
                        ]);
                    }
                    $day->addDay();
                }
            }
            $this->callWith(BpjsPayrollFixtureSeeder::class, ['period' => now()->format('Y-m')]);
        });
    }
}
