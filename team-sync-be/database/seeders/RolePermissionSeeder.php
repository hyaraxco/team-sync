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
            $manager = Role::firstOrCreate(['name' => 'manager']);
            $hr = Role::firstOrCreate(['name' => 'hr']);
            $staff = Role::firstOrCreate(['name' => 'staff']);
            $finance = Role::firstOrCreate(['name' => 'finance']);

            $selfServiceBaseline = [
                'profile-menu',
                'profile-view',
                'attendance-my-attendances',
                'attendance-last-attendance',
                'attendance-check-in',
                'attendance-check-out',
                'attendance-correction-create',
                'leave-request-menu',
                'leave-request-create',
                'leave-request-my-requests',
                'payslip-view',
                // Performance Management Baseline
                'performance-menu',
                'review-self-submit',
                'goal-create-own',
                'feedback-give',
                'meeting-menu',
            ];

            $staffSpecific = array_merge($selfServiceBaseline, [
                'team-view',
            ]);

            $manager->syncPermissions(
                $this->permissionsAllExcept(array_merge($staffSpecific, [
                    'leave-request-menu',
                    'leave-request-create',
                    'leave-request-my-requests',
                    'payroll-menu',
                    'payroll-list',
                    'payroll-create',
                    'payroll-edit',
                    'payroll-delete',
                    'payroll-process',
                    'payroll-statistics',
                    // HR-only: Manager should NOT calibrate or manage review cycles
                    'review-calibrate',
                    'review-cycle-manage',
                    'review-assign-reviewer',
                    // HR-only: Manager should NOT create/edit/delete staff members (view-only)
                    'staff-member-create',
                    'staff-member-edit',
                    'staff-member-delete',
                    'meeting-list',
                    'meeting-create',
                ]))->merge(
                    Permission::whereIn('name', $selfServiceBaseline)->get()
                )->unique('id')->values()
            );

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
            ], array_merge($staffSpecific, [
                'task-delete',
                // Manager-only: HR should NOT see Team Reviews
                'review-manager-submit',
            ]))->merge(
                Permission::whereIn('name', [
                    'payroll-menu',
                    'payroll-list',
                    'payroll-create',
                    ...$selfServiceBaseline,
                ])->get()
            )->unique('id')->values());

            $staff->syncPermissions(
                Permission::whereIn('name', array_merge($selfServiceBaseline, [
                    'dashboard-menu',
                    'dashboard-view',
                    'staff-member-list',
                    'team-view',
                    'project-menu',
                    'project-list',
                    'task-menu',
                    'task-create',
                    'task-list',
                    'task-edit',
                ]))->get()
            );

            $finance->syncPermissions(
                Permission::whereIn('name', [
                    'dashboard-menu',
                    'dashboard-view',
                    'staff-member-menu',
                    'staff-member-list',
                    'payroll-menu',
                    'payroll-list',
                    'payroll-edit',
                    'payroll-process',
                    'payroll-statistics',
                    'analytics-menu',
                    'analytics-view',
                    'analytics-export',
                    ...$selfServiceBaseline,
                ])->get()
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
