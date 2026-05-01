<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 0. Seed company (multi-tenancy foundation)
            CompanySeeder::class,

            // 1. Seed roles and permissions first
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,

            // 2. Seed specific users (Manager, HR, Finance, Employee)
            ManagerSeeder::class,
            EmployeeSeeder::class,
            HrSeeder::class,
            FinanceSeeder::class,
            TaxBracketSeeder::class,
            BpjsRateSeeder::class,
            PtkpAmountSeeder::class,
            AttendancePolicySeeder::class,
            LeaveEntitlementSeeder::class,

            // 3. Seed performance review sections & outcome rules
            PerformanceReviewSectionSeeder::class,
            PerformanceDataSeeder::class,
            PerformanceOutcomeRuleSeeder::class,

            // 4. Seed additional users, teams, projects with complete relational data
            DemoDataSeeder::class,

            // 5. Seed review templates (requires sections to exist)
            PerformanceReviewTemplateSeeder::class,
            ReviewerRuleSeeder::class,

            // 6. Seed interaction data (attendance, goals, feedback, tasks, leave, payroll settings)
            DemoInteractionSeeder::class,

            // Legacy seeders (disabled — replaced by DemoDataSeeder)
            // StaffMemberProfileSeeder::class,
            // TeamSeeder::class,
        ]);
    }
}
