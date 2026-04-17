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
            $employee = Role::firstOrCreate(['name' => 'employee']);
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
            ];

            $employeeSpecific = array_merge($selfServiceBaseline, [
                'team-view',
            ]);

            $manager->syncPermissions(
                $this->permissionsAllExcept(array_merge($employeeSpecific, [
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
                ]))->merge(
                    Permission::whereIn('name', $selfServiceBaseline)->get()
                )->unique('id')->values()
            );

            $hr->syncPermissions($this->permissionsByPrefixes([
                'dashboard-',
                'team-',
                'employee-',
                'project-',
                'task-',
                'attendance-',
                'leave-request-',
                'analytics-',
            ], array_merge($employeeSpecific, [
                'task-delete',
            ]))->merge(
                Permission::whereIn('name', [
                    'payroll-menu',
                    'payroll-list',
                    'payroll-create',
                    ...$selfServiceBaseline,
                ])->get()
            )->unique('id')->values());

            $employee->syncPermissions(
                Permission::whereIn('name', [
                    'dashboard-menu',
                    'dashboard-view',
                    'profile-menu',
                    'profile-view',
                    'employee-list',
                    'team-view',
                    'attendance-my-attendances',
                    'attendance-check-in',
                    'attendance-check-out',
                    'attendance-last-attendance',
                    'attendance-correction-create',
                    'leave-request-menu',
                    'leave-request-create',
                    'leave-request-my-requests',
                    'payslip-view',
                    'project-menu',
                    'project-list',
                    'task-menu',
                    'task-create',
                    'task-list',
                    'task-edit',
                ])->get()
            );

            $finance->syncPermissions(
                Permission::whereIn('name', [
                    'dashboard-menu',
                    'dashboard-view',
                    'employee-menu',
                    'employee-list',
                    'attendance-menu',
                    'attendance-list',
                    'leave-request-list',
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
                $q->orWhere('name', 'like', $prefix . '%');
            }
        })->when(! empty($except), function ($q) use ($except) {
            $q->whereNotIn('name', $except);
        })->get();
    }
}
