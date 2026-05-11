<?php

use App\Models\PayrollApprovalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('getApplicablePolicies returns policies where min_amount <= total', function () {
    PayrollApprovalPolicy::create([
        'name' => 'Low Amount Policy',
        'min_amount' => 0,
        'max_amount' => 10000000,
        'required_role' => 'finance',
        'approval_order' => 1,
        'is_active' => true,
    ]);

    PayrollApprovalPolicy::create([
        'name' => 'High Amount Policy',
        'min_amount' => 50000000,
        'max_amount' => null,
        'required_role' => 'finance',
        'approval_order' => 2,
        'is_active' => true,
    ]);

    // Total 5M matches only the low amount policy (min_amount 0 <= 5M <= max_amount 10M)
    $policies = PayrollApprovalPolicy::getApplicablePolicies(5000000);

    expect($policies)->toHaveCount(1)
        ->and($policies->first()->name)->toBe('Low Amount Policy');
});

it('getApplicablePolicies respects max_amount upper bound', function () {
    PayrollApprovalPolicy::create([
        'name' => 'Low Amount Policy',
        'min_amount' => 0,
        'max_amount' => 10000000,
        'required_role' => 'finance',
        'approval_order' => 1,
        'is_active' => true,
    ]);

    // 15M exceeds max_amount of 10M
    $policies = PayrollApprovalPolicy::getApplicablePolicies(15000000);

    expect($policies)->toHaveCount(0);
});

it('getApplicablePolicies includes policies with null max_amount (unbounded)', function () {
    PayrollApprovalPolicy::create([
        'name' => 'Unbounded Policy',
        'min_amount' => 0,
        'max_amount' => null,
        'required_role' => 'finance',
        'approval_order' => 1,
        'is_active' => true,
    ]);

    // Very large amount should still match unbounded policy
    $policies = PayrollApprovalPolicy::getApplicablePolicies(999999999);

    expect($policies)->toHaveCount(1)
        ->and($policies->first()->name)->toBe('Unbounded Policy');
});

it('getApplicablePolicies returns empty when no matching policies', function () {
    PayrollApprovalPolicy::create([
        'name' => 'High Min Policy',
        'min_amount' => 100000000,
        'max_amount' => null,
        'required_role' => 'finance',
        'approval_order' => 1,
        'is_active' => true,
    ]);

    // 5M is below min_amount of 100M
    $policies = PayrollApprovalPolicy::getApplicablePolicies(5000000);

    expect($policies)->toHaveCount(0);
});

it('getApplicablePolicies orders by approval_order', function () {
    PayrollApprovalPolicy::create([
        'name' => 'Final Approver',
        'min_amount' => 0,
        'max_amount' => null,
        'required_role' => 'director',
        'approval_order' => 3,
        'is_active' => true,
    ]);

    PayrollApprovalPolicy::create([
        'name' => 'First Approver',
        'min_amount' => 0,
        'max_amount' => null,
        'required_role' => 'finance',
        'approval_order' => 1,
        'is_active' => true,
    ]);

    PayrollApprovalPolicy::create([
        'name' => 'Second Approver',
        'min_amount' => 0,
        'max_amount' => null,
        'required_role' => 'finance',
        'approval_order' => 2,
        'is_active' => true,
    ]);

    $policies = PayrollApprovalPolicy::getApplicablePolicies(5000000);

    expect($policies)->toHaveCount(3)
        ->and($policies->pluck('name')->toArray())->toBe([
            'First Approver',
            'Second Approver',
            'Final Approver',
        ]);
});

it('getApplicablePolicies excludes inactive policies', function () {
    PayrollApprovalPolicy::create([
        'name' => 'Inactive Policy',
        'min_amount' => 0,
        'max_amount' => null,
        'required_role' => 'finance',
        'approval_order' => 1,
        'is_active' => false,
    ]);

    PayrollApprovalPolicy::create([
        'name' => 'Active Policy',
        'min_amount' => 0,
        'max_amount' => null,
        'required_role' => 'finance',
        'approval_order' => 2,
        'is_active' => true,
    ]);

    $policies = PayrollApprovalPolicy::getApplicablePolicies(5000000);

    expect($policies)->toHaveCount(1)
        ->and($policies->first()->name)->toBe('Active Policy');
});

it('getApplicablePolicies handles exact min_amount boundary', function () {
    PayrollApprovalPolicy::create([
        'name' => 'Exact Boundary',
        'min_amount' => 10000000,
        'max_amount' => 50000000,
        'required_role' => 'finance',
        'approval_order' => 1,
        'is_active' => true,
    ]);

    // Exactly at min_amount boundary
    $policies = PayrollApprovalPolicy::getApplicablePolicies(10000000);

    expect($policies)->toHaveCount(1)
        ->and($policies->first()->name)->toBe('Exact Boundary');
});

it('getApplicablePolicies handles exact max_amount boundary', function () {
    PayrollApprovalPolicy::create([
        'name' => 'Exact Max Boundary',
        'min_amount' => 0,
        'max_amount' => 50000000,
        'required_role' => 'finance',
        'approval_order' => 1,
        'is_active' => true,
    ]);

    // Exactly at max_amount boundary — >= means it should match
    $policies = PayrollApprovalPolicy::getApplicablePolicies(50000000);

    expect($policies)->toHaveCount(1)
        ->and($policies->first()->name)->toBe('Exact Max Boundary');
});
