<?php

namespace Tests\Unit\Services;

use App\Models\Payroll;
use App\Models\PayrollActivityLog;
use App\Models\User;
use App\Services\PayrollActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    private PayrollActivityLogger $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PayrollActivityLogger;
    }

    public function test_log_creates_activity_log_record_with_all_fields(): void
    {
        $payroll = Payroll::factory()->create();
        $actor = User::factory()->create();

        $result = $this->service->log(
            payrollId: (int) $payroll->id,
            eventType: 'payroll_generated',
            title: 'Payroll Generated',
            description: 'Monthly payroll for May 2026',
            actorId: (int) $actor->id,
            metadata: ['month' => '2026-05', 'employee_count' => 50]
        );

        $this->assertInstanceOf(PayrollActivityLog::class, $result);
        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'actor_id' => $actor->id,
            'event_type' => 'payroll_generated',
            'title' => 'Payroll Generated',
            'description' => 'Monthly payroll for May 2026',
        ]);

        $this->assertEquals($payroll->id, $result->payroll_id);
        $this->assertEquals($actor->id, $result->actor_id);
        $this->assertEquals('payroll_generated', $result->event_type);
        $this->assertEquals('Payroll Generated', $result->title);
        $this->assertEquals('Monthly payroll for May 2026', $result->description);
        $this->assertEquals(['month' => '2026-05', 'employee_count' => 50], $result->metadata);
        $this->assertNotNull($result->occurred_at);
    }

    public function test_log_handles_null_metadata_by_storing_null(): void
    {
        $payroll = Payroll::factory()->create();

        $result = $this->service->log(
            payrollId: (int) $payroll->id,
            eventType: 'status_changed',
            title: 'Status Changed',
            description: null,
            actorId: null,
            metadata: []
        );

        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'metadata' => null,
        ]);

        $this->assertNull($result->metadata);
    }

    public function test_log_handles_null_description_and_actor(): void
    {
        $payroll = Payroll::factory()->create();

        $result = $this->service->log(
            payrollId: (int) $payroll->id,
            eventType: 'approval',
            title: 'Approved'
        );

        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'approval',
            'title' => 'Approved',
            'description' => null,
            'actor_id' => null,
        ]);

        $this->assertNull($result->description);
        $this->assertNull($result->actor_id);
    }

    public function test_log_sets_occurred_at_to_now(): void
    {
        $payroll = Payroll::factory()->create();

        $result = $this->service->log(
            payrollId: (int) $payroll->id,
            eventType: 'test_event',
            title: 'Test'
        );

        $this->assertNotNull($result->occurred_at);
        $this->assertEquals(now()->format('Y-m-d'), $result->occurred_at->format('Y-m-d'));
    }

    public function test_log_stores_non_empty_metadata(): void
    {
        $payroll = Payroll::factory()->create();
        $metadata = ['key' => 'value', 'nested' => ['data' => true]];

        $result = $this->service->log(
            payrollId: (int) $payroll->id,
            eventType: 'test',
            title: 'Test',
            metadata: $metadata
        );

        $this->assertEquals($metadata, $result->metadata);

        $dbRecord = PayrollActivityLog::find($result->id);
        $this->assertEquals($metadata, $dbRecord->metadata);
    }
}
