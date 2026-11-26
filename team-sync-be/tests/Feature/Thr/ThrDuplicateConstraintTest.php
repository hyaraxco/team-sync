<?php

namespace Tests\Feature\Thr;

use App\Models\Company;
use App\Models\ThrPayroll;
use Database\Seeders\CompanySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The unique key on thr_payrolls is (year, religion_event, company_id).
 * MySQL treats NULL as distinct, so when company_id was never written the
 * constraint never fired and duplicate THR rows could be created.
 */
class ThrDuplicateConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CompanySeeder::class);
    }

    public function test_unique_key_rejects_a_second_row_for_the_same_event_and_company(): void
    {
        $companyId = Company::current()->id;

        $this->makeThr($companyId);

        $this->expectException(QueryException::class);

        $this->makeThr($companyId);
    }

    public function test_generated_thr_records_carry_the_company_id(): void
    {
        $thr = $this->makeThr(Company::current()->id);

        $this->assertNotNull($thr->company_id);
        $this->assertSame(Company::current()->id, $thr->company_id);
    }

    private function makeThr(int $companyId): ThrPayroll
    {
        return ThrPayroll::create([
            'year' => 2026,
            'religion_event' => 'idul_fitri',
            'religion_holiday_date' => '2026-03-20',
            'payment_deadline' => '2026-03-13',
            'income_due_date' => '2026-03-10',
            'status' => ThrPayroll::STATUS_PROCESSING,
            'company_id' => $companyId,
        ]);
    }
}
