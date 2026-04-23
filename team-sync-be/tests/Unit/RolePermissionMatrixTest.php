<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('ensures manager role has view-only access to staff members', function () {
    $managerRole = Role::findByName('manager');

    // Manager should have list and view menu permissions
    expect($managerRole->hasPermissionTo('staff-member-list'))->toBeTrue();
    expect($managerRole->hasPermissionTo('staff-member-menu'))->toBeTrue();

    // Manager should NOT have create, edit, or delete permissions
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
