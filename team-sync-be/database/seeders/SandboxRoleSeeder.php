<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\License;
use App\Models\PayrollSetting;
use App\Models\StaffMemberProfile;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\E2E\E2ESuperadminSeeder;
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
                    PerformanceReviewSectionSeeder::class,
                    ManagerSeeder::class,
                    EmployeeSeeder::class,
                    HrSeeder::class,
                    FinanceSeeder::class,
                    E2ESuperadminSeeder::class,
                    TaxBracketSeeder::class,
                    BpjsRateSeeder::class,
                    PtkpAmountSeeder::class,
                ]);
            });

            foreach ([
                ['Engineering', 'development', 'yudhis@teamsync.com', 'agung@teamsync.com'],
                ['Operations', 'management', 'tasyia@teamsync.com', 'dwimeta@teamsync.com'],
            ] as [$name, $department, $leaderEmail, $memberEmail]) {
                $leader = User::where('email', $leaderEmail)->firstOrFail();
                $team = Team::create([
                    'name' => $name,
                    'department' => $department,
                    'status' => 'active',
                    'expected_size' => 2,
                    'description' => 'Isolated role scope fixture',
                    'icon' => 'team-icons/activity.png',
                    'responsibilities' => ['Delivery', 'Quality', 'Review'],
                    'team_lead_id' => $leader->id,
                ]);
                foreach ([$leaderEmail, $memberEmail] as $email) {
                    $profile = User::where('email', $email)->firstOrFail()->staffMemberProfile;
                    TeamMember::create([
                        'team_id' => $team->id,
                        'staff_member_id' => $profile->id,
                        'joined_at' => now()->subYear(),
                    ]);
                    $profile->jobInformation()->update(['team_id' => $team->id]);
                }
            }

            $project = Project::create([
                'name' => 'Team Sync HRIS Platform',
                'type' => 'web_development',
                'priority' => 'high',
                'status' => 'active',
                'start_date' => now()->subYear()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'description' => 'Isolated project membership fixture',
                'project_leader_id' => User::where('email', 'yudhis@teamsync.com')->firstOrFail()->staffMemberProfile->id,
            ]);
            foreach (Team::all() as $team) {
                ProjectTeam::create([
                    'project_id' => $project->id,
                    'team_id' => $team->id,
                    'assigned_at' => now()->subYear(),
                ]);
            }

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
                'current_users' => 5,
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
                            'check_in' => $this->utc('08:00:00', $day),
                            'check_out' => $this->utc('17:00:00', $day),
                            'status' => 'present',
                            'notes' => 'Sandbox payroll role fixture',
                        ]);
                    }
                    $day->addDay();
                }
            }
            $this->callWith(BpjsPayrollFixtureSeeder::class, ['period' => now()->format('Y-m')]);

            $this->call(\Database\Seeders\E2E\HistoricalPayslipSeeder::class);
            \App\Models\PerformanceReviewCycle::create([
                'name' => 'E2E Review Cycle P4',
                'cycle_type' => 'quarterly',
                'start_date' => now()->startOfMonth(),
                'end_date' => now()->addMonths(3)->endOfMonth(),
                'review_period_start' => now()->startOfMonth(),
                'review_period_end' => now()->addMonths(3)->endOfMonth(),
                'status' => 'active',
                'created_by' => User::where('email', 'tasyia@teamsync.com')->firstOrFail()->id,
            ]);
        });
    }

    /** Attendance timestamps are stored in UTC; seeders express company wall-clock times. */
    private function utc(string $time, Carbon $date, string $timezone = 'Asia/Jakarta'): string
    {
        return Carbon::parse($date->format('Y-m-d').' '.$time, $timezone)->setTimezone('UTC')->toDateTimeString();
    }
}
