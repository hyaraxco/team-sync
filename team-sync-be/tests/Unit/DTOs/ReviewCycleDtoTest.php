<?php

namespace Tests\Unit\DTOs;

use App\DTOs\Performance\ReviewCycleDto;
use Tests\TestCase;

class ReviewCycleDtoTest extends TestCase
{
    public function test_from_request_maps_all_fields(): void
    {
        $dto = ReviewCycleDto::fromRequest($this->payload());

        $this->assertSame('Q1 2025 Review', $dto->name);
        $this->assertSame('quarterly', $dto->cycle_type);
        $this->assertSame('2025-01-01', $dto->start_date);
        $this->assertSame('2025-03-31', $dto->end_date);
        $this->assertSame('2025-01-01', $dto->review_period_start);
        $this->assertSame('2025-03-31', $dto->review_period_end);
        $this->assertSame('active', $dto->status);
        $this->assertSame('2025-02-15', $dto->self_assessment_deadline);
        $this->assertSame('2025-02-28', $dto->manager_assessment_deadline);
        $this->assertSame('2025-03-15', $dto->calibration_deadline);
        $this->assertSame(1, $dto->template_id);
        $this->assertSame(3, $dto->created_by);
    }

    public function test_from_request_uses_draft_status_when_not_provided(): void
    {
        $dto = ReviewCycleDto::fromRequest([
            'name' => 'Annual Review',
            'cycle_type' => 'annual',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'review_period_start' => '2025-01-01',
            'review_period_end' => '2025-12-31',
            'created_by' => 5,
        ]);

        $this->assertSame('draft', $dto->status);
        $this->assertNull($dto->self_assessment_deadline);
        $this->assertNull($dto->manager_assessment_deadline);
        $this->assertNull($dto->calibration_deadline);
        $this->assertNull($dto->template_id);
        $this->assertSame(5, $dto->created_by);
    }

    public function test_to_array_includes_optional_fields_when_present(): void
    {
        $dto = ReviewCycleDto::fromRequest($this->payload());
        $array = $dto->toArray();

        $this->assertSame('Q1 2025 Review', $array['name']);
        $this->assertSame('quarterly', $array['cycle_type']);
        $this->assertSame('active', $array['status']);
        $this->assertSame('2025-02-15', $array['self_assessment_deadline']);
        $this->assertSame('2025-02-28', $array['manager_assessment_deadline']);
        $this->assertSame('2025-03-15', $array['calibration_deadline']);
        $this->assertSame(1, $array['template_id']);
        $this->assertSame(3, $array['created_by']);
    }

    public function test_to_array_omits_null_optional_fields(): void
    {
        $dto = ReviewCycleDto::fromRequest([
            'name' => 'Annual Review',
            'cycle_type' => 'annual',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'review_period_start' => '2025-01-01',
            'review_period_end' => '2025-12-31',
            'created_by' => 5,
        ]);
        $array = $dto->toArray();

        // status defaults to 'draft', so it's always present
        $this->assertSame('draft', $array['status']);
        $this->assertArrayNotHasKey('self_assessment_deadline', $array);
        $this->assertArrayNotHasKey('manager_assessment_deadline', $array);
        $this->assertArrayNotHasKey('calibration_deadline', $array);
        $this->assertArrayNotHasKey('template_id', $array);
        $this->assertArrayHasKey('created_by', $array);
    }

    public function test_template_id_is_cast_to_int(): void
    {
        $dto = ReviewCycleDto::fromRequest(array_merge($this->payload(), [
            'template_id' => '7',
        ]));

        $this->assertSame(7, $dto->template_id);
    }

    private function payload(): array
    {
        return [
            'name' => 'Q1 2025 Review',
            'cycle_type' => 'quarterly',
            'start_date' => '2025-01-01',
            'end_date' => '2025-03-31',
            'review_period_start' => '2025-01-01',
            'review_period_end' => '2025-03-31',
            'status' => 'active',
            'self_assessment_deadline' => '2025-02-15',
            'manager_assessment_deadline' => '2025-02-28',
            'calibration_deadline' => '2025-03-15',
            'template_id' => 1,
            'created_by' => 3,
        ];
    }
}
