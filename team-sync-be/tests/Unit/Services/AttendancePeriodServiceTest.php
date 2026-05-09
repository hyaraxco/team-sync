<?php

namespace Tests\Unit\Services;

use App\Models\AttendancePeriod;
use App\Services\Attendance\AttendancePeriodService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendancePeriodServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendancePeriodService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttendancePeriodService;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ensurePeriodForMonth
    // ─────────────────────────────────────────────────────────────────────────

    public function test_ensure_period_creates_new_period_for_month(): void
    {
        $period = $this->service->ensurePeriodForMonth('2026-06-01');

        $this->assertInstanceOf(AttendancePeriod::class, $period);
        $this->assertEquals('2026-06-01', $period->start_date->format('Y-m-d'));
        $this->assertEquals('open', $period->status);
    }

    public function test_ensure_period_returns_existing_period_if_already_exists(): void
    {
        $existing = AttendancePeriod::factory()->create([
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
        ]);

        $period = $this->service->ensurePeriodForMonth('2026-07-01');

        $this->assertEquals($existing->id, $period->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // canSubmitCorrection
    // ─────────────────────────────────────────────────────────────────────────

    public function test_can_submit_correction_returns_true_for_open_period(): void
    {
        $now = now();
        AttendancePeriod::factory()->create([
            'start_date' => $now->copy()->startOfMonth(),
            'end_date' => $now->copy()->endOfMonth(),
            'status' => 'open',
        ]);

        $this->assertTrue($this->service->canSubmitCorrection($now));
    }

    public function test_can_submit_correction_returns_false_for_locked_period(): void
    {
        $now = now();
        AttendancePeriod::factory()->create([
            'start_date' => $now->copy()->startOfMonth(),
            'end_date' => $now->copy()->endOfMonth(),
            'status' => 'locked',
        ]);

        $this->assertFalse($this->service->canSubmitCorrection($now));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // lockPeriod
    // ─────────────────────────────────────────────────────────────────────────

    public function test_lock_period_transitions_to_locked(): void
    {
        $period = AttendancePeriod::factory()->create([
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'review',
        ]);

        $locked = $this->service->lockPeriod($period);

        $this->assertEquals('locked', $locked->status);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // periodForDate
    // ─────────────────────────────────────────────────────────────────────────

    public function test_period_for_date_returns_matching_period(): void
    {
        $period = AttendancePeriod::factory()->create([
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'open',
        ]);

        $result = $this->service->periodForDate('2026-10-15');

        $this->assertNotNull($result);
        $this->assertEquals($period->id, $result->id);
    }

    public function test_period_for_date_returns_null_when_no_period(): void
    {
        $result = $this->service->periodForDate('2099-01-01');

        $this->assertNull($result);
    }
}
