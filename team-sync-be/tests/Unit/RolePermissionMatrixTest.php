<?php

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('ensures manager role has NO staff directory access (deferred until team-scoped API)', function () {
    $managerRole = Role::findByName('manager');

    // Manager should NOT have any staff-member permissions (PRD: deferred)
    expect($managerRole->hasPermissionTo('staff-member-menu'))->toBeFalse();
    expect($managerRole->hasPermissionTo('staff-member-list'))->toBeFalse();
    expect($managerRole->hasPermissionTo('staff-member-create'))->toBeFalse();
    expect($managerRole->hasPermissionTo('staff-member-edit'))->toBeFalse();
    expect($managerRole->hasPermissionTo('staff-member-delete'))->toBeFalse();
});

it('ensures hr role has full access to staff members', function () {
    $hrRole = Role::findByName('hr');

    expect($hrRole->hasPermissionTo('staff-member-list'))->toBeTrue();
    expect($hrRole->hasPermissionTo('staff-member-create'))->toBeTrue();
    expect($hrRole->hasPermissionTo('staff-member-edit'))->toBeTrue();
    expect($hrRole->hasPermissionTo('staff-member-delete'))->toBeTrue();
});

it('ensures manager role does not have review calibration and management permissions', function () {
    $managerRole = Role::findByName('manager');

    expect($managerRole->hasPermissionTo('review-calibrate'))->toBeFalse();
    expect($managerRole->hasPermissionTo('review-cycle-manage'))->toBeFalse();
    expect($managerRole->hasPermissionTo('review-assign-reviewer'))->toBeFalse();
});

it('ensures superadmin role has all seeded permissions', function () {
    $superadminRole = Role::findByName('superadmin');

    expect($superadminRole->permissions->count())
        ->toBeGreaterThan(0)
        ->and($superadminRole->permissions->count())
        ->toBe(Permission::count());
});

it('ensures only superadmin gets license management permissions by default', function () {
    $superadminRole = Role::findByName('superadmin');
    $hrRole = Role::findByName('hr');
    $managerRole = Role::findByName('manager');

    expect($superadminRole->hasPermissionTo('license-view'))->toBeTrue()
        ->and($superadminRole->hasPermissionTo('license-manage'))->toBeTrue()
        ->and($hrRole->hasPermissionTo('license-view'))->toBeFalse()
        ->and($managerRole->hasPermissionTo('license-manage'))->toBeFalse();
});

it('ensures finance owns all THR operations (generate/approve/process)', function () {
    $hrRole = Role::findByName('hr');
    $financeRole = Role::findByName('finance');
    $superadminRole = Role::findByName('superadmin');

    // Finance owns THR: generate, approve, process
    expect($financeRole->hasPermissionTo('thr-list'))->toBeTrue()
        ->and($financeRole->hasPermissionTo('thr-generate'))->toBeTrue()
        ->and($financeRole->hasPermissionTo('thr-approve'))->toBeTrue()
        ->and($financeRole->hasPermissionTo('thr-process'))->toBeTrue();

    // HR: read-only THR list, no operations
    expect($hrRole->hasPermissionTo('thr-list'))->toBeTrue()
        ->and($hrRole->hasPermissionTo('thr-generate'))->toBeFalse()
        ->and($hrRole->hasPermissionTo('thr-approve'))->toBeFalse()
        ->and($hrRole->hasPermissionTo('thr-process'))->toBeFalse();

    // Superadmin: full
    expect($superadminRole->hasPermissionTo('thr-process'))->toBeTrue();
});

it('ensures finance does NOT have staff directory access', function () {
    $financeRole = Role::findByName('finance');

    expect($financeRole->hasPermissionTo('staff-member-menu'))->toBeFalse();
    expect($financeRole->hasPermissionTo('staff-member-list'))->toBeFalse();
});

it('ensures hr has payroll-readiness-view but not payroll-create', function () {
    $hrRole = Role::findByName('hr');

    expect($hrRole->hasPermissionTo('payroll-readiness-view'))->toBeTrue();
    expect($hrRole->hasPermissionTo('payroll-create'))->toBeFalse();
    expect($hrRole->hasPermissionTo('payroll-process'))->toBeFalse();
});

it('ensures manager uses explicit allowlist (no payroll, no license, no staff directory)', function () {
    $managerRole = Role::findByName('manager');

    expect($managerRole->hasPermissionTo('payroll-menu'))->toBeFalse();
    expect($managerRole->hasPermissionTo('payroll-create'))->toBeFalse();
    expect($managerRole->hasPermissionTo('license-view'))->toBeFalse();
    expect($managerRole->hasPermissionTo('license-manage'))->toBeFalse();
    expect($managerRole->hasPermissionTo('staff-member-menu'))->toBeFalse();

    // Manager should have team/project/task management
    expect($managerRole->hasPermissionTo('team-menu'))->toBeTrue();
    expect($managerRole->hasPermissionTo('project-menu'))->toBeTrue();
    expect($managerRole->hasPermissionTo('task-menu'))->toBeTrue();
    expect($managerRole->hasPermissionTo('review-manager-submit'))->toBeTrue();
});

// ─── Analytics audience-scoped permissions ────────────────────────────────────

it('ensures HR gets analytics-hr-view but NOT analytics-finance-view', function () {
    $hrRole = Role::findByName('hr');

    expect($hrRole->hasPermissionTo('analytics-hr-view'))->toBeTrue();
    expect($hrRole->hasPermissionTo('analytics-performance-view'))->toBeTrue();
    expect($hrRole->hasPermissionTo('analytics-project-view'))->toBeTrue();
    expect($hrRole->hasPermissionTo('analytics-finance-view'))->toBeFalse();
});

it('ensures Finance gets analytics-finance-view but NOT analytics-hr-view', function () {
    $financeRole = Role::findByName('finance');

    expect($financeRole->hasPermissionTo('analytics-finance-view'))->toBeTrue();
    expect($financeRole->hasPermissionTo('analytics-hr-view'))->toBeFalse();
    expect($financeRole->hasPermissionTo('analytics-performance-view'))->toBeFalse();
    expect($financeRole->hasPermissionTo('analytics-project-view'))->toBeFalse();
});

it('ensures Manager gets analytics-performance-view and analytics-project-view only', function () {
    $managerRole = Role::findByName('manager');

    expect($managerRole->hasPermissionTo('analytics-menu'))->toBeTrue();
    expect($managerRole->hasPermissionTo('analytics-performance-view'))->toBeTrue();
    expect($managerRole->hasPermissionTo('analytics-project-view'))->toBeTrue();
    expect($managerRole->hasPermissionTo('analytics-hr-view'))->toBeFalse();
    expect($managerRole->hasPermissionTo('analytics-finance-view'))->toBeFalse();
});

it('ensures Staff has NO analytics permissions', function () {
    $staffRole = Role::findByName('staff');

    expect($staffRole->hasPermissionTo('analytics-menu'))->toBeFalse();
    expect($staffRole->hasPermissionTo('analytics-view'))->toBeFalse();
    expect($staffRole->hasPermissionTo('analytics-hr-view'))->toBeFalse();
    expect($staffRole->hasPermissionTo('analytics-finance-view'))->toBeFalse();
});

// ─── Dashboard audience-scoped permissions ───────────────────────────────────

it('ensures HR gets dashboard-hr-view for company-wide stats', function () {
    $hrRole = Role::findByName('hr');

    expect($hrRole->hasPermissionTo('dashboard-hr-view'))->toBeTrue();
    expect($hrRole->hasPermissionTo('dashboard-view'))->toBeTrue();
});

it('ensures Finance does NOT get dashboard-hr-view', function () {
    $financeRole = Role::findByName('finance');

    expect($financeRole->hasPermissionTo('dashboard-hr-view'))->toBeFalse();
    expect($financeRole->hasPermissionTo('dashboard-view'))->toBeTrue();
});

it('ensures Manager does NOT get dashboard-hr-view', function () {
    $managerRole = Role::findByName('manager');

    expect($managerRole->hasPermissionTo('dashboard-hr-view'))->toBeFalse();
    expect($managerRole->hasPermissionTo('dashboard-view'))->toBeTrue();
});

it('ensures Staff does NOT get dashboard-hr-view', function () {
    $staffRole = Role::findByName('staff');

    expect($staffRole->hasPermissionTo('dashboard-hr-view'))->toBeFalse();
    expect($staffRole->hasPermissionTo('dashboard-view'))->toBeTrue();
});
