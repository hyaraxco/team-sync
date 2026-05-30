<?php

namespace Tests\Feature;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    // ─── Staff: self-service only ────────────────────────────────────

    public function test_staff_has_self_service_permissions(): void
    {
        $staff = Role::findByName('staff', 'sanctum');

        $expected = [
            'dashboard-menu', 'dashboard-view', 'dashboard-self-view',
            'profile-menu', 'profile-view',
            'attendance-my-attendances', 'attendance-my-statistics',
            'attendance-check-in', 'attendance-check-out', 'attendance-last-attendance',
            'attendance-correction-create',
            'leave-request-menu', 'leave-request-create', 'leave-request-my-requests',
            'payslip-view', 'performance-menu', 'review-self-submit',
            'goal-create-own',
            'meeting-menu', 'meeting-list',
            'team-view', 'project-menu', 'project-list',
            'task-menu', 'task-list', 'task-edit',
            'overtime-create',
        ];

        foreach ($expected as $permission) {
            $this->assertTrue(
                $staff->hasPermissionTo($permission),
                "Staff should have '{$permission}'"
            );
        }
    }

    public function test_staff_lacks_admin_permissions(): void
    {
        $staff = Role::findByName('staff', 'sanctum');

        $forbidden = [
            // Permission overhaul (2026-05-30): staff no longer creates tasks
            'task-create', 'task-delete',
            'staff-member-menu', 'staff-member-list', 'staff-member-create',
            'staff-member-edit', 'staff-member-delete',
            'analytics-menu', 'analytics-view', 'analytics-hr-view', 'analytics-finance-view',
            'payroll-menu', 'payroll-list', 'payroll-create', 'payroll-process',
            'thr-list', 'thr-generate', 'thr-approve', 'thr-process',
            'attendance-menu', 'attendance-list',
            'settings-hr-manage', 'settings-finance-manage', 'settings-system-manage',
            'dashboard-hr-view', 'dashboard-team-view', 'dashboard-finance-view', 'dashboard-system-view',
            'feedback-give',
            'license-view', 'license-manage',
        ];

        foreach ($forbidden as $permission) {
            $this->assertFalse(
                $staff->hasPermissionTo($permission),
                "Staff should NOT have '{$permission}'"
            );
        }
    }

    // ─── Manager: team-scoped only ───────────────────────────────────

    public function test_manager_has_team_scoped_permissions(): void
    {
        $manager = Role::findByName('manager', 'sanctum');

        $expected = [
            'dashboard-menu', 'dashboard-view', 'dashboard-team-view',
            'team-menu', 'team-list', 'team-create', 'team-edit', 'team-delete', 'team-view',
            'project-menu', 'project-list', 'project-create', 'project-edit', 'project-delete',
            // Permission overhaul (2026-05-30): manager has task view only;
            // task CRUD is delegated to project leader.
            'task-menu', 'task-list',
            'attendance-menu', 'attendance-list',
            'attendance-correction-list', 'attendance-correction-approve',
            'leave-request-list', 'leave-request-approve',
            'overtime-list', 'overtime-approve',
            'review-manager-submit', 'goal-assign-team', 'performance-analytics-view',
            'analytics-menu', 'analytics-team-view', 'analytics-performance-view', 'analytics-project-view',
        ];

        foreach ($expected as $permission) {
            $this->assertTrue(
                $manager->hasPermissionTo($permission),
                "Manager should have '{$permission}'"
            );
        }
    }

    public function test_manager_lacks_company_wide_permissions(): void
    {
        $manager = Role::findByName('manager', 'sanctum');

        $forbidden = [
            // Permission overhaul (2026-05-30): manager delegates task CRUD to project leader
            'task-create', 'task-edit', 'task-delete',
            'staff-member-menu', 'staff-member-list', 'staff-member-create',
            'staff-member-edit', 'staff-member-delete',
            'payroll-menu', 'payroll-list', 'payroll-create', 'payroll-process',
            'thr-list', 'thr-generate', 'thr-approve', 'thr-process',
            'analytics-hr-view', 'analytics-finance-view',
            'dashboard-hr-view', 'dashboard-finance-view', 'dashboard-system-view',
            'settings-hr-manage', 'settings-finance-manage', 'settings-system-manage',
            'license-view', 'license-manage',
        ];

        foreach ($forbidden as $permission) {
            $this->assertFalse(
                $manager->hasPermissionTo($permission),
                "Manager should NOT have '{$permission}'"
            );
        }
    }

    // ─── HR: workforce admin, NO payroll ops ─────────────────────────

    public function test_hr_has_workforce_permissions(): void
    {
        $hr = Role::findByName('hr', 'sanctum');

        $expected = [
            'dashboard-menu', 'dashboard-view', 'dashboard-hr-view', 'dashboard-self-view',
            'staff-member-menu', 'staff-member-list', 'staff-member-create',
            'staff-member-edit', 'staff-member-delete',
            'team-menu', 'team-list',
            'attendance-menu', 'attendance-list',
            'leave-request-menu', 'leave-request-list', 'leave-request-approve',
            'analytics-menu', 'analytics-view', 'analytics-hr-view',
            'analytics-performance-view', 'analytics-project-view',
            'review-cycle-manage', 'review-calibrate', 'review-assign-reviewer',
            'meeting-menu', 'meeting-list', 'meeting-create',
            'settings-hr-manage',
            'payroll-readiness-view', 'thr-list',
        ];

        foreach ($expected as $permission) {
            $this->assertTrue(
                $hr->hasPermissionTo($permission),
                "HR should have '{$permission}'"
            );
        }
    }

    public function test_hr_lacks_payroll_operations(): void
    {
        $hr = Role::findByName('hr', 'sanctum');

        $forbidden = [
            // Permission overhaul (2026-05-30): HR is read-only for projects/tasks
            'project-create', 'project-edit', 'project-delete',
            'task-create', 'task-edit', 'task-delete',
            'payroll-menu', 'payroll-list', 'payroll-create', 'payroll-edit',
            'payroll-delete', 'payroll-process', 'payroll-statistics',
            'thr-generate', 'thr-approve', 'thr-process',
            'dashboard-finance-view', 'dashboard-system-view', 'dashboard-team-view',
            'analytics-finance-view', 'analytics-team-view',
            'settings-finance-manage', 'settings-system-manage',
            'license-view', 'license-manage',
        ];

        foreach ($forbidden as $permission) {
            $this->assertFalse(
                $hr->hasPermissionTo($permission),
                "HR should NOT have '{$permission}'"
            );
        }
    }

    // ─── Finance: payroll owner, NO full staff directory ─────────────

    public function test_finance_has_payroll_permissions(): void
    {
        $finance = Role::findByName('finance', 'sanctum');

        $expected = [
            'dashboard-menu', 'dashboard-view', 'dashboard-finance-view',
            // Permission overhaul (2026-05-30): finance has staff-level project/task access
            'project-menu', 'project-list',
            'task-menu', 'task-list', 'task-edit',
            'payroll-menu', 'payroll-list', 'payroll-create', 'payroll-edit',
            'payroll-delete', 'payroll-process', 'payroll-statistics', 'payroll-readiness-view',
            'thr-list', 'thr-generate', 'thr-approve', 'thr-process',
            'analytics-menu', 'analytics-view', 'analytics-export', 'analytics-finance-view',
            'overtime-list',
            'settings-finance-manage',
        ];

        foreach ($expected as $permission) {
            $this->assertTrue(
                $finance->hasPermissionTo($permission),
                "Finance should have '{$permission}'"
            );
        }
    }

    public function test_finance_lacks_hr_and_staff_directory(): void
    {
        $finance = Role::findByName('finance', 'sanctum');

        $forbidden = [
            // Permission overhaul (2026-05-30): finance cannot create/delete tasks
            'task-create', 'task-delete',
            'project-create', 'project-edit', 'project-delete',
            'staff-member-menu', 'staff-member-list', 'staff-member-create',
            'staff-member-edit', 'staff-member-delete',
            'analytics-hr-view', 'analytics-performance-view', 'analytics-project-view',
            'attendance-menu', 'attendance-list',
            'leave-request-list', 'leave-request-approve',
            'review-cycle-manage', 'review-calibrate',
            'dashboard-hr-view', 'dashboard-team-view', 'dashboard-system-view',
            'settings-hr-manage', 'settings-system-manage',
            'license-view', 'license-manage',
        ];

        foreach ($forbidden as $permission) {
            $this->assertFalse(
                $finance->hasPermissionTo($permission),
                "Finance should NOT have '{$permission}'"
            );
        }
    }

    // ─── Superadmin: full access ─────────────────────────────────────

    public function test_superadmin_has_all_permissions(): void
    {
        $superadmin = Role::findByName('superadmin', 'sanctum');

        $criticalPermissions = [
            'dashboard-system-view', 'dashboard-hr-view', 'dashboard-finance-view', 'dashboard-team-view',
            'settings-system-manage', 'settings-hr-manage', 'settings-finance-manage',
            'license-view', 'license-manage',
            'staff-member-menu', 'staff-member-list',
            'payroll-menu', 'payroll-create', 'payroll-process',
            'analytics-hr-view', 'analytics-finance-view', 'analytics-team-view',
            'thr-generate', 'thr-approve', 'thr-process',
        ];

        foreach ($criticalPermissions as $permission) {
            $this->assertTrue(
                $superadmin->hasPermissionTo($permission),
                "Superadmin should have '{$permission}'"
            );
        }
    }
}
