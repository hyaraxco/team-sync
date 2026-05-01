<?php

namespace App\Console\Commands;

use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\OvertimeRecord;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillCompanyTenantCommand extends Command
{
    protected $signature = 'tenant:backfill
        {--dry-run : Preview updates without writing changes}';

    protected $description = 'Assign all existing records to the default company';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $company = Company::where('slug', 'team-sync-pro')->first();

        if (! $company) {
            $this->error('Default company "team-sync-pro" not found. Run CompanySeeder first.');

            return self::FAILURE;
        }

        $tables = [
            'Users' => User::class,
            'Staff Member Profiles' => StaffMemberProfile::class,
            'Teams' => Team::class,
            'Payrolls' => Payroll::class,
            'Attendance Periods' => AttendancePeriod::class,
            'Payroll Settings' => PayrollSetting::class,
            'Overtime Records' => OvertimeRecord::class,
        ];

        $this->info(sprintf(
            '%s tenant backfill to company "%s" (ID: %d).',
            $isDryRun ? '[DRY-RUN]' : 'Running',
            $company->name,
            $company->id
        ));

        $totalUpdated = 0;

        foreach ($tables as $label => $modelClass) {
            $count = $modelClass::whereNull('company_id')->count();

            if ($count === 0) {
                $this->line("  {$label}: 0 records to update (skipped)");

                continue;
            }

            if (! $isDryRun) {
                $modelClass::whereNull('company_id')->update(['company_id' => $company->id]);
            }

            $this->line("  {$label}: {$count} records " . ($isDryRun ? 'would be updated' : 'updated'));
            $totalUpdated += $count;
        }

        $this->newLine();

        if ($isDryRun) {
            $this->info("Dry-run completed. {$totalUpdated} total records would be assigned.");
        } else {
            $this->info("Backfill completed. {$totalUpdated} total records assigned to \"{$company->name}\".");
        }

        return self::SUCCESS;
    }
}
