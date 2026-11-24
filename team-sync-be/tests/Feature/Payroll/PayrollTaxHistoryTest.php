<?php

use App\Exceptions\PayrollStateException;
use App\Models\PayrollSetting;
use App\Models\PayrollTaxEntry;
use App\Models\PayrollTaxYear;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Services\Payroll\PayrollTaxPeriodService;
use App\Services\Payroll\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\PtkpAmountSeeder::class, \Database\Seeders\TaxBracketSeeder::class]);
    PayrollSetting::query()->create(PayrollSetting::defaults());
    $this->employee = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->for(User::factory())->create([
        'code' => 'TAX-001', 'ptkp_status' => 'TK/0', 'npwp' => '123456789012345',
    ]));
    $this->tax = app(PayrollTaxPeriodService::class);
});

it('allocates two pending sources exactly once in either generation order', function (bool $thrFirst) {
    $sources = [['payroll', 11, '2026-03-25', 10_000_000], ['thr', 12, '2026-03-15', 10_000_000]];
    if ($thrFirst) {
        $sources = array_reverse($sources);
    }
    foreach ($sources as [$type, $id, $due, $gross]) {
        $this->tax->persistDraft($this->employee, $type, $id, $due, null, $gross, 0, $gross);
    }
    $entries = PayrollTaxEntry::query()->orderBy('allocation_key')->get();
    $required = (new TaxCalculationService)->calculateMonthlyTer(20_000_000, 'TK/0', true)['pph21_monthly'];
    expect($entries->sum(fn ($entry) => $entry->calculation['pph21_monthly']))->toEqual($required)
        ->and($entries->first()->source_type)->toBe('thr')
        ->and($entries->sum('actual_withholding'))->toEqual(0);
})->with([true, false]);

it('rejects active government credits instead of charging them to the employee', function () {
    expect(fn () => $this->tax->persistDraft($this->employee, 'payroll', 1, '2026-03-25', null,
        10_000_000, 0, 10_000_000, ['government_borne_tax' => 200_000]))
        ->toThrow(PayrollStateException::class, 'Active-source government-borne tax is not supported');
    expect(PayrollTaxEntry::query()->exists())->toBeFalse();
});

it('ripples earlier changes to later drafts even when their approval must be invalidated', function () {
    $this->tax->persistDraft($this->employee, 'payroll', 1, '2026-03-25', null, 10_000_000, 0, 10_000_000);
    $result = $this->tax->persistDraft($this->employee, 'thr', 2, '2026-03-15', null, 8_000_000, 0, 8_000_000);
    expect($result['meta']['affected_sources'])->toHaveCount(1)
        ->and($result['meta']['affected_sources'][0]['source_type'])->toBe('payroll');
});

it('blocks out of order settlement and counts settled actual only once', function () {
    $this->tax->persistDraft($this->employee, 'thr', 2, '2026-03-15', null, 10_000_000, 0, 10_000_000);
    $this->tax->persistDraft($this->employee, 'payroll', 1, '2026-03-25', null, 10_000_000, 0, 10_000_000);
    expect(fn () => $this->tax->settle('payroll', 1, '2026-03-25'))->toThrow(PayrollStateException::class);
    $this->tax->settle('thr', 2, '2026-03-15');
    $this->tax->settle('payroll', 1, '2026-03-25');
    $required = (new TaxCalculationService)->calculateMonthlyTer(20_000_000, 'TK/0', true)['pph21_monthly'];
    expect((float) PayrollTaxEntry::query()->sum('actual_withholding'))->toEqual($required);
    expect(fn () => $this->tax->persistDraft($this->employee, 'thr', 2, '2026-03-15', null, 1, 0, 1))->toThrow(PayrollStateException::class);
});

it('prepares early credit without withholding and requires reapproval before retry', function () {
    $this->tax->persistDraft($this->employee, 'payroll', 1, '2026-03-25', null, 10_000_000, 0, 10_000_000);
    $first = $this->tax->prepareSettlement('payroll', 1, '2026-02-28');
    expect($first['ready'])->toBeFalse()
        ->and($first['calculation']['meta']['period'])->toBe('2026-02')
        ->and(PayrollTaxEntry::query()->first()->actual_withholding)->toBeNull();
    expect($this->tax->prepareSettlement('payroll', 1, '2026-02-28')['ready'])->toBeTrue();
    $this->tax->settle('payroll', 1, '2026-02-28');
    expect(PayrollTaxEntry::query()->first()->settled_at)->not->toBeNull();
});

it('keeps overdue salary in its due month and detects stale identity', function () {
    $result = $this->tax->persistDraft($this->employee, 'payroll', 1, '2026-03-25', null, 10_000_000, 0, 10_000_000);
    expect($result['meta']['period'])->toBe('2026-03');
    $this->tax->assertFresh('payroll', 1, '2026-04-02');
    $this->employee->update(['npwp' => null]);
    expect(fn () => $this->tax->assertFresh('payroll', 1))->toThrow(PayrollStateException::class);
});

it('fails final tax with unknown employment or missing history instead of assuming zero', function () {
    expect(fn () => $this->tax->calculate($this->employee, 'payroll', 1, '2026-12-25', null, 10_000_000, 0, 10_000_000))
        ->toThrow(PayrollStateException::class);
});

it('reprices an attempted credit leaving the final employment period without settling', function () {
    $zero = ['gross_income' => 0, 'employee_pension_contributions' => 0, 'deductible_donations' => 0,
        'withheld' => 0, 'government_borne_tax' => 0, 'provenance' => 'explicit synthetic zero'];
    PayrollTaxYear::query()->create([
        'employee_id' => $this->employee->id, 'year' => 2026, 'provenance' => 'synthetic cessation',
        'profile' => ['employment_start_date' => '2026-01-01', 'employment_end_date' => '2026-06-30',
            'subjective_start_date' => '2026-01-01', 'subjective_end_date' => null],
        'history' => array_fill_keys(['2026-01', '2026-02', '2026-03', '2026-04'], $zero),
    ]);
    // May remains internal so moving recognition there cannot overlap an imported month.
    $this->tax->persistDraft($this->employee, 'thr', 2, '2026-05-01', null, 0, 0, 0);
    $this->tax->settle('thr', 2, '2026-05-01');
    $before = $this->tax->persistDraft($this->employee, 'payroll', 1, '2026-06-25', null, 10_000_000, 0, 10_000_000);
    $prepared = $this->tax->prepareSettlement('payroll', 1, '2026-05-31');
    expect($prepared['ready'])->toBeFalse()
        ->and($prepared['calculation']['method'])->toBe('ter_2024')
        ->and($prepared['calculation']['meta']['context_hash'])->not->toBe($before['meta']['context_hash'])
        ->and(PayrollTaxEntry::query()->where('source_type', 'payroll')->first()->settled_at)->toBeNull();
    expect($this->tax->prepareSettlement('payroll', 1, '2026-05-31')['ready'])->toBeTrue();
});

it('does not invalidate unchanged recognition for a later actual credit date', function () {
    $this->tax->persistDraft($this->employee, 'payroll', 1, '2026-03-25', null, 10_000_000, 0, 10_000_000);
    expect($this->tax->prepareSettlement('payroll', 1, '2026-04-02')['ready'])->toBeTrue();
    $this->tax->settle('payroll', 1, '2026-04-02');
    expect(PayrollTaxEntry::query()->first()->period)->toBe('2026-03');
});

it('imports explicit history atomically and refuses replacement after drafts exist', function () {
    $path = tempnam(sys_get_temp_dir(), 'tax-history-');
    $row = [
        'employee_id' => $this->employee->id, 'year' => 2026,
        'provenance' => 'Synthetic Finance ledger 2026',
        'employment_start_date' => '2026-01-01', 'employment_end_date' => null,
        'subjective_start_date' => '2026-01-01', 'subjective_end_date' => null,
        'history' => ['2026-01' => [
            'gross_income' => 10_000_000, 'employee_pension_contributions' => 300_000,
            'deductible_donations' => 0, 'withheld' => 200_000,
            'government_borne_tax' => 0, 'provenance' => 'Synthetic January payment',
        ]],
    ];
    try {
        file_put_contents($path, json_encode([$row, $row], JSON_THROW_ON_ERROR));
        $this->artisan('payroll:import-tax-history', ['path' => $path])->assertFailed();
        expect(PayrollTaxYear::query()->count())->toBe(0);

        $fractionalRow = $row;
        $fractionalRow['history']['2026-01']['withheld'] = 200_000.25;
        file_put_contents($path, json_encode([$fractionalRow], JSON_THROW_ON_ERROR));
        $this->artisan('payroll:import-tax-history', ['path' => $path])->assertFailed();
        expect(PayrollTaxYear::query()->count())->toBe(0);

        $otherEmployee = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->for(User::factory())->create([
            'code' => 'TAX-002', 'ptkp_status' => 'TK/0', 'npwp' => '987654321012345',
        ]));
        $this->tax->persistDraft($otherEmployee, 'payroll', 2, '2026-02-25', null, 10_000_000, 0, 10_000_000);
        $blockedRow = array_replace($row, ['employee_id' => $otherEmployee->id]);
        file_put_contents($path, json_encode([$row, $blockedRow], JSON_THROW_ON_ERROR));
        $this->artisan('payroll:import-tax-history', ['path' => $path])
            ->expectsOutput('Import history before creating active drafts; deactivate drafts and refresh/reapprove after import.')
            ->assertFailed();
        expect(PayrollTaxYear::query()->count())->toBe(0);

        file_put_contents($path, json_encode([$row], JSON_THROW_ON_ERROR));
        $this->artisan('payroll:import-tax-history', ['path' => $path])->assertSuccessful();
        $stored = PayrollTaxYear::query()->sole();
        expect($stored->history['2026-01']['withheld'])->toBe(200_000);

        $this->tax->persistDraft($this->employee, 'payroll', 1, '2026-02-25', null, 10_000_000, 0, 10_000_000);
        $row['history']['2026-01']['withheld'] = 0;
        file_put_contents($path, json_encode([$row], JSON_THROW_ON_ERROR));
        $this->artisan('payroll:import-tax-history', ['path' => $path])->assertFailed();
        expect($stored->fresh()->history['2026-01']['withheld'])->toBe(200_000);
    } finally {
        unlink($path);
    }
});
