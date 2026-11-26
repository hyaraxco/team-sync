<?php

namespace App\Jobs;

use App\Services\Payroll\PayrollGenerationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GeneratePayrollJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public string $salaryMonth;

    public ?int $initiatedBy;

    public ?int $companyId;

    public int $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(string $salaryMonth, ?int $initiatedBy = null, ?int $companyId = null)
    {
        $this->salaryMonth = $salaryMonth;
        $this->initiatedBy = $initiatedBy;
        $this->companyId = $companyId;
    }

    public function uniqueId(): string
    {
        return sprintf('generate-payroll:%s:%s', $this->companyId ?? 'default', $this->salaryMonth);
    }

    /**
     * Execute the job.
     */
    public function handle(PayrollGenerationService $generationService): void
    {
        try {
            Log::info('Starting payroll generation', [
                'salary_month' => $this->salaryMonth,
                'initiated_by' => $this->initiatedBy,
                'company_id' => $this->companyId,
            ]);

            $payroll = $generationService->generatePayroll($this->salaryMonth, $this->initiatedBy, $this->companyId);

            Log::info('Payroll generation completed', [
                'salary_month' => $this->salaryMonth,
                'payroll_id' => $payroll->id,
                'total_details' => $payroll->payrollDetails()->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Payroll generation failed', [
                'salary_month' => $this->salaryMonth,
                'initiated_by' => $this->initiatedBy,
                'company_id' => $this->companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600; // 10 minutes for large datasets
}
