<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    private $permissions = [
        'dashboard' => [
            'menu',
            'view',
        ],

        'profile' => [
            'menu',
            'view',
        ],

        'team' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
            'view',
        ],

        'staff-member' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'project' => [
            'menu',
            'statistic',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'task' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
        ],

        'attendance' => [
            'menu',
            'list',
            'my-attendances',
            'my-statistics',
            'check-in',
            'check-out',
            'last-attendance',
        ],

        'attendance-correction' => [
            'list',
            'create',
            'approve',
        ],

        'leave-request' => [
            'menu',
            'list',
            'create',
            'approve',
            'my-requests',
        ],

        'payroll' => [
            'menu',
            'list',
            'create',
            'edit',
            'delete',
            'process',
            'statistics',
        ],

        'analytics' => [
            'menu',
            'view',
            'export',
        ],

        'payslip' => [
            'view',
        ],

        'review-cycle' => [
            'manage',
        ],

        'review-self' => [
            'submit',
        ],

        'review-manager' => [
            'submit',
        ],

        'review' => [
            'calibrate',
            'assign-reviewer',
        ],

        'goal-create' => [
            'own',
        ],

        'goal-assign' => [
            'team',
        ],

        'feedback' => [
            'give',
        ],

        'performance-analytics' => [
            'view',
        ],

        'performance' => [
            'menu',
        ],

        'meeting' => [
            'menu',
            'list',
            'create',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->permissions as $key => $value) {
            foreach ($value as $permission) {
                Permission::firstOrCreate([
                    'name' => $key.'-'.$permission,
                    'guard_name' => 'sanctum',
                ]);
            }
        }
    }
}
