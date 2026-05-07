<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'sanctum']);
            $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
            $hr = Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'sanctum']);
            $staff = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
            $finance = Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'sanctum']);

            // Superadmin: full access
            $superadmin->syncPermissions(Permission::all());

            // ─── Self-service baseline (all roles inherit) ───────────────────
            $selfServiceBaseline = [
                'profile-menu',
                'profile-view',
                'attendance-my-attendances',
                'attendance-my-statistics',
                'attendance-last-attendance',
                'attendance-check-in',
                'attendance-check-out',
                'attendance-correction-create',
                'leave-request-menu',
                'leave-request-create',
                'leave-request-my-requests',
                'payslip-view',
                'performance-menu',
                'review-self-submit',
                'goal-create-own',
                'feedback-give',
                'meeting-menu',
            ];

            // ─── Staff: self-service + personal workspace ────────────────────
            $staff->syncPermissions(
                Permission::whereIn('name', array_merge($selfServiceBaseline, [
                    'dashboard-menu',
                    'dashboard-view',
                    'team-view',
                    'project-menu',
                    'project-list',
                    'task-menu',
                    'task-list',
                    'task-create',
                    'task-edit',
                    'overtime-create',
                    // Meetings: view list (receive/join)
                    'meeting-list',
                ]))->get()
            );

            // ─── Manager: explicit allowlist (team/project scoped) ───────────
            $manager->syncPermissions(
                Permission::whereIn('name', array_merge($selfServiceBaseline, [
                    // Dashboard
                    'dashboard-menu',
                    'dashboard-view',
                    // Team management
                    'team-menu',
                    'team-statistic',
                    'team-list',
                    'team-create',
                    'team-edit',
                    'team-delete',
                    'team-view',
                    // Project & task management
                    'project-menu',
                    'project-statistic',
                    'project-list',
                    'project-create',
                    'project-edit',
                    'project-delete',
                    'task-menu',
                    'task-list',
                    'task-create',
                    'task-edit',
                    'task-delete',
                    // Attendance: team approval context
                    'attendance-menu',
                    'attendance-list',
                    'attendance-correction-list',
                    'attendance-correction-approve',
                    // Leave: team approval
                    'leave-request-list',
                    'leave-request-approve',
                    // Overtime: team approval
                    'overtime-list',
                    'overtime-create',
                    'overtime-approve',
                    // Performance: team reviews & goals
                    'review-manager-submit',
                    'goal-assign-team',
                    'performance-analytics-view',
                    // Analytics: team-scoped performance & project only
                    'analytics-menu',
                    'analytics-performance-view',
                    'analytics-project-view',
                    // Meetings: view list (team meeting context)
                    'meeting-list',
                ]))->get()
            );

            // ─── HR: workforce, attendance, leave, performance, meetings ─────
            // HR does NOT get payroll operations (Finance owns those).
            // HR gets read-only payroll readiness for coordination.
            $hr->syncPermissions($this->permissionsByPrefixes([
                'dashboard-',
                'team-',
                'staff-member-',
                'project-',
                'task-',
                'attendance-',
                'leave-request-',
                'analytics-',
                'performance-',
                'review-',
                'goal-',
                'feedback-',
                'meeting-',
                'overtime-',
            ], [
                // Exclude: task-delete (admin-only destructive)
                'task-delete',
                // Exclude: Manager-only team review submission
                'review-manager-submit',
                // Exclude: Finance-only analytics
                'analytics-finance-view',
            ])->merge(
                Permission::whereIn('name', [
                    // Payroll: read-only readiness context only
                    'payroll-readiness-view',
                    'thr-list',
                    ...$selfServiceBaseline,
                ])->get()
            )->unique('id')->values());

            // ─── Finance: payroll, THR, payroll analytics ────────────────────
            // Finance does NOT get full staff directory or HR admin.
            $finance->syncPermissions(
                Permission::whereIn('name', array_merge($selfServiceBaseline, [
                    // Dashboard
                    'dashboard-menu',
                    'dashboard-view',
                    // Payroll operations (Finance owns all)
                    'payroll-menu',
                    'payroll-list',
                    'payroll-create',
                    'payroll-edit',
                    'payroll-delete',
                    'payroll-process',
                    'payroll-statistics',
                    'payroll-readiness-view',
                    // THR operations (Finance owns generate/approve/process)
                    'thr-list',
                    'thr-generate',
                    'thr-approve',
                    'thr-process',
                    // Analytics: payroll/finance scoped
                    'analytics-menu',
                    'analytics-view',
                    'analytics-export',
                    'analytics-finance-view',
                    // Overtime: payroll context (list only, no approval)
                    'overtime-list',
                    // Meetings: view list (receive/join)
                    'meeting-list',
                ]))->get()
            );
        });
    }

    private function permissionsAllExcept(array $except): Collection
    {
        return Permission::whereNotIn('name', $except)->get();
    }

    private function permissionsByPrefixes(array $prefixes, array $except = []): Collection
    {
        return Permission::where(function ($q) use ($prefixes) {
            foreach ($prefixes as $prefix) {
                $q->orWhere('name', 'like', $prefix.'%');
            }
        })->when(! empty($except), function ($q) use ($except) {
            $q->whereNotIn('name', $except);
        })->get();
    }
}
