<?php

namespace Tests\Feature\Commands;

use App\Services\Attendance\AttendancePolicyMismatchLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class EscalateAttendanceMismatchesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_escalates_mismatches_and_reports_count(): void
    {
        $mockService = Mockery::mock(AttendancePolicyMismatchLifecycleService::class);
        $mockService->shouldReceive('escalatePendingReviewMismatches')
            ->once()
            ->with(null)
            ->andReturn(5);

        $this->app->instance(AttendancePolicyMismatchLifecycleService::class, $mockService);

        $exitCode = Artisan::call('attendance-mismatches:escalate');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Escalated mismatches: 5', Artisan::output());
    }

    public function test_command_passes_date_option_to_service(): void
    {
        $mockService = Mockery::mock(AttendancePolicyMismatchLifecycleService::class);
        $mockService->shouldReceive('escalatePendingReviewMismatches')
            ->once()
            ->with('2026-05-01')
            ->andReturn(3);

        $this->app->instance(AttendancePolicyMismatchLifecycleService::class, $mockService);

        $exitCode = Artisan::call('attendance-mismatches:escalate', ['--date' => '2026-05-01']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Escalated mismatches: 3', Artisan::output());
    }

    public function test_command_returns_success_when_no_mismatches_to_escalate(): void
    {
        $mockService = Mockery::mock(AttendancePolicyMismatchLifecycleService::class);
        $mockService->shouldReceive('escalatePendingReviewMismatches')
            ->once()
            ->andReturn(0);

        $this->app->instance(AttendancePolicyMismatchLifecycleService::class, $mockService);

        $exitCode = Artisan::call('attendance-mismatches:escalate');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Escalated mismatches: 0', Artisan::output());
    }
}
