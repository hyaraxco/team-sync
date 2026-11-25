<?php

namespace Tests\Feature\Commands;

use App\Models\Payroll;
use App\Models\PayrollBpjsPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImportPayrollBpjsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_replaces_draft_facts_but_cannot_change_paid_period(): void
    {
        $facts = $this->facts();
        $this->import($facts);
        $facts['jkk_class'] = 5;
        $this->import($facts);
        $this->assertSame(5, PayrollBpjsPeriod::firstOrFail()->facts['jkk_class']);

        Payroll::factory()->create(['salary_month' => '2026-04-01', 'status' => 'paid']);
        $facts['jkk_class'] = 2;
        $this->import($facts, 1);
        $this->assertSame(5, PayrollBpjsPeriod::firstOrFail()->facts['jkk_class']);
    }

    #[DataProvider('unsupportedInputs')]
    public function test_import_rejects_unverified_or_unsupported_inputs(string $key, mixed $value): void
    {
        $facts = $this->facts();
        data_set($facts, $key, $value);
        $this->import($facts, 1);
        $this->assertDatabaseCount('payroll_bpjs_periods', 0);
    }

    public static function unsupportedInputs(): array
    {
        return [
            'micro/small employer does not use an invented floor' => ['employer_class', 'micro_small'],
            'ordinary employer cannot use alternate floor' => ['employees.42.floor_type', 'micro_small'],
            'BPU is not PPU' => ['employees.42.coverage', 'bpu'],
            'relief is not the standard employer scheme' => ['employer_class', 'jkk_relief'],
            'missing fixed allowance data is not zero' => ['employees.42.fixed_allowances', null],
            'missing minimum wage is not zero' => ['employees.42.health_floor', null],
            'unknown assigned class is not class I' => ['jkk_class', 0],
        ];
    }

    private function import(array $facts, int $expectedExit = 0): void
    {
        $path = tempnam(sys_get_temp_dir(), 'test-bpjs-');
        try {
            file_put_contents($path, json_encode($facts, JSON_THROW_ON_ERROR));
            $this->artisan('payroll:import-bpjs', ['month' => '2026-04', 'path' => $path])->assertExitCode($expectedExit);
        } finally {
            unlink($path);
        }
    }

    private function facts(): array
    {
        return [
            'employer_class' => 'ordinary',
            'jkk_class' => 3,
            'jkk_assignment_ref' => 'Synthetic assignment',
            'employees' => [42 => [
                'coverage' => 'private_salaried_ppu_all_five',
                'basic_wage' => 5_000_000,
                'fixed_allowances' => 0,
                'health_floor' => 4_000_000,
                'floor_type' => 'ump',
                'region' => 'Synthetic jurisdiction',
                'floor_ref' => 'Synthetic decree',
            ]],
        ];
    }
}
