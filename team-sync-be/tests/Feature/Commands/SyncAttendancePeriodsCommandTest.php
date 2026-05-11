<?php

namespace Tests\Feature\Commands;

use App\Services\Attendance\AttendancePeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class SyncAttendancePeriodsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_syncs_attendance_period_lifecycle(): void
    {
        $mockService = Mockery::mock(AttendancePeriodService::class);
        $mockService->shouldReceive('syncLifecycle')
            ->once()
            ->with(null)
            ->andReturn([
                'current_period_id' => 1,
                'review_transitioned' => 2,
            ]);

        $this->app->instance(AttendancePeriodService::class, $mockService);

        $exitCode = Artisan::call('attendance-periods:sync');

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Attendance period lifecycle synchronized', $output);
        $this->assertStringContainsString('Current period id: 1', $output);
        $this->assertStringContainsString('Periods moved to review: 2', $output);
    }

    public function test_command_passes_date_option_to_service(): void
    {
        $mockService = Mockery::mock(AttendancePeriodService::class);
        $mockService->shouldReceive('syncLifecycle')
            ->once()
            ->with('2026-05-01')
            ->andReturn([
                'current_period_id' => 3,
                'review_transitioned' => 0,
            ]);

        $this->app->instance(AttendancePeriodService::class, $mockService);

        $exitCode = Artisan::call('attendance-periods:sync', ['--date' => '2026-05-01']);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Current period id: 3', Artisan::output());
    }
}
