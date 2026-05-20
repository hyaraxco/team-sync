<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GeneratePayrollJob;
use App\Models\Payroll;
use App\Services\Payroll\PayrollGenerationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GeneratePayrollJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_implements_should_queue_and_should_be_unique(): void
    {
        $job = new GeneratePayrollJob('2026-05');

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
    }

    public function test_job_has_correct_retry_count_and_timeout(): void
    {
        $job = new GeneratePayrollJob('2026-05');

        $this->assertSame(3, $job->tries);
        $this->assertSame(600, $job->timeout);
    }

    public function test_unique_id_is_based_on_salary_month(): void
    {
        $job = new GeneratePayrollJob('2026-05');

        $this->assertSame('generate-payroll:2026-05', $job->uniqueId());
    }

    public function test_unique_id_changes_with_salary_month(): void
    {
        $job1 = new GeneratePayrollJob('2026-05');
        $job2 = new GeneratePayrollJob('2026-06');

        $this->assertNotSame($job1->uniqueId(), $job2->uniqueId());
    }

    public function test_job_stores_salary_month_and_initiated_by(): void
    {
        $job = new GeneratePayrollJob('2026-05', 42);

        $this->assertSame('2026-05', $job->salaryMonth);
        $this->assertSame(42, $job->initiatedBy);
    }

    public function test_job_allows_null_initiated_by(): void
    {
        $job = new GeneratePayrollJob('2026-05');

        $this->assertSame('2026-05', $job->salaryMonth);
        $this->assertNull($job->initiatedBy);
    }

    public function test_handle_delegates_to_generation_service(): void
    {
        // Create a real Payroll model in DB
        $payroll = Payroll::factory()->create([
            'salary_month' => '2026-05-01',
        ]);

        $mockService = Mockery::mock(PayrollGenerationService::class);
        $mockService->shouldReceive('generatePayroll')
            ->once()
            ->with('2026-05', 42)
            ->andReturn($payroll);

        $job = new GeneratePayrollJob('2026-05', 42);
        $job->handle($mockService);

        $this->assertTrue(true); // If no exception, delegation worked
    }

    public function test_handle_passes_salary_month_and_initiated_by_to_service(): void
    {
        $payroll = Payroll::factory()->create([
            'salary_month' => '2026-06-01',
        ]);

        $mockService = Mockery::mock(PayrollGenerationService::class);
        $mockService->shouldReceive('generatePayroll')
            ->once()
            ->with('2026-06', null)
            ->andReturn($payroll);

        $job = new GeneratePayrollJob('2026-06');
        $job->handle($mockService);

        // Mockery enforces shouldHaveReceived in tearDown
        $this->assertTrue(true);
    }

    public function test_handle_rethrows_exception_on_failure(): void
    {
        $mockService = Mockery::mock(PayrollGenerationService::class);
        $mockService->shouldReceive('generatePayroll')
            ->once()
            ->andThrow(new \RuntimeException('Database connection lost'));

        $job = new GeneratePayrollJob('2026-05', 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection lost');

        $job->handle($mockService);
    }

    public function test_job_is_queued_on_default_queue(): void
    {
        $job = new GeneratePayrollJob('2026-05');

        $this->assertNull($job->queue); // Default queue
    }

    public function test_unique_for_is_one_hour(): void
    {
        $job = new GeneratePayrollJob('2026-05');

        $this->assertSame(3600, $job->uniqueFor);
    }
}
