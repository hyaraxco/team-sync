<?php

namespace Tests\Unit\DTOs;

use App\DTOs\Performance\GoalDto;
use Tests\TestCase;

class GoalDtoTest extends TestCase
{
    public function test_from_request_maps_all_fields(): void
    {
        $dto = GoalDto::fromRequest($this->payload());

        $this->assertSame(1, $dto->staff_member_id);
        $this->assertSame('Improve API performance', $dto->title);
        $this->assertSame('Reduce response time by 50%', $dto->description);
        $this->assertSame('key_result', $dto->goal_type);
        $this->assertSame('technical', $dto->category);
        $this->assertSame('200ms', $dto->target_value);
        $this->assertSame('350ms', $dto->current_value);
        $this->assertSame('milliseconds', $dto->unit);
        $this->assertSame(0.4, $dto->weight);
        $this->assertSame('2025-01-01', $dto->start_date);
        $this->assertSame('2025-06-30', $dto->due_date);
        $this->assertSame('in_progress', $dto->status);
        $this->assertSame(30, $dto->completion_percentage);
        $this->assertSame(5, $dto->created_by);
        $this->assertSame(6, $dto->assigned_by);
        $this->assertSame(10, $dto->linked_review_id);
    }

    public function test_from_request_uses_defaults_for_optional_fields(): void
    {
        $dto = GoalDto::fromRequest([
            'staff_member_id' => 1,
            'title' => 'Learn Laravel',
            'goal_type' => 'objective',
            'start_date' => '2025-01-01',
            'due_date' => '2025-12-31',
            'created_by' => 5,
        ]);

        $this->assertNull($dto->description);
        $this->assertNull($dto->category);
        $this->assertNull($dto->target_value);
        $this->assertNull($dto->current_value);
        $this->assertNull($dto->unit);
        $this->assertNull($dto->weight);
        $this->assertSame('not_started', $dto->status);
        $this->assertSame(0, $dto->completion_percentage);
        $this->assertSame(5, $dto->created_by);
        $this->assertNull($dto->assigned_by);
        $this->assertNull($dto->linked_review_id);
    }

    public function test_from_request_falls_back_to_created_by_when_no_auth(): void
    {
        $dto = GoalDto::fromRequest([
            'staff_member_id' => 1,
            'title' => 'Goal',
            'goal_type' => 'objective',
            'start_date' => '2025-01-01',
            'due_date' => '2025-12-31',
            'created_by' => 99,
        ]);

        $this->assertSame(99, $dto->created_by);
    }

    public function test_weight_is_cast_to_float(): void
    {
        $dto = GoalDto::fromRequest(array_merge($this->payload(), [
            'weight' => '0.75',
        ]));

        $this->assertSame(0.75, $dto->weight);
    }

    public function test_to_array_includes_optional_fields_when_present(): void
    {
        $dto = GoalDto::fromRequest($this->payload());
        $array = $dto->toArray();

        $this->assertSame(1, $array['staff_member_id']);
        $this->assertSame('Improve API performance', $array['title']);
        $this->assertSame('Reduce response time by 50%', $array['description']);
        $this->assertSame('key_result', $array['goal_type']);
        $this->assertSame('technical', $array['category']);
        $this->assertSame('200ms', $array['target_value']);
        $this->assertSame('350ms', $array['current_value']);
        $this->assertSame('milliseconds', $array['unit']);
        $this->assertSame(0.4, $array['weight']);
        $this->assertSame('in_progress', $array['status']);
        $this->assertSame(30, $array['completion_percentage']);
        $this->assertSame(5, $array['created_by']);
        $this->assertSame(6, $array['assigned_by']);
        $this->assertSame(10, $array['linked_review_id']);
    }

    public function test_to_array_omits_null_optional_fields(): void
    {
        $dto = GoalDto::fromRequest([
            'staff_member_id' => 1,
            'title' => 'Learn Laravel',
            'goal_type' => 'objective',
            'start_date' => '2025-01-01',
            'due_date' => '2025-12-31',
            'created_by' => 5,
        ]);
        $array = $dto->toArray();

        $this->assertArrayNotHasKey('description', $array);
        $this->assertArrayNotHasKey('category', $array);
        $this->assertArrayNotHasKey('target_value', $array);
        $this->assertArrayNotHasKey('current_value', $array);
        $this->assertArrayNotHasKey('unit', $array);
        $this->assertArrayNotHasKey('weight', $array);
        $this->assertArrayNotHasKey('assigned_by', $array);
        $this->assertArrayNotHasKey('linked_review_id', $array);
        // status and completion_percentage have defaults, so they are always present
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('completion_percentage', $array);
    }

    private function payload(): array
    {
        return [
            'staff_member_id' => 1,
            'title' => 'Improve API performance',
            'description' => 'Reduce response time by 50%',
            'goal_type' => 'key_result',
            'category' => 'technical',
            'target_value' => '200ms',
            'current_value' => '350ms',
            'unit' => 'milliseconds',
            'weight' => 0.4,
            'start_date' => '2025-01-01',
            'due_date' => '2025-06-30',
            'status' => 'in_progress',
            'completion_percentage' => 30,
            'created_by' => 5,
            'assigned_by' => 6,
            'linked_review_id' => 10,
        ];
    }
}
