<?php

namespace Tests\Unit\DTOs;

use App\DTOs\ProjectTaskDto;
use App\Models\ProjectTask;
use Tests\TestCase;

class ProjectTaskDtoTest extends TestCase
{
    public function test_from_array_maps_all_fields(): void
    {
        $dto = ProjectTaskDto::fromArray($this->payload());

        $this->assertSame(1, $dto->project_id);
        $this->assertSame('Build API', $dto->name);
        $this->assertSame('Create REST endpoints', $dto->description);
        $this->assertSame(5, $dto->assignee_id);
        $this->assertSame('high', $dto->priority);
        $this->assertSame('in_progress', $dto->status);
        $this->assertSame('Needs more details', $dto->rejected_reason);
        $this->assertSame('2025-06-01', $dto->due_date);
    }

    public function test_from_array_uses_defaults_for_optional_fields(): void
    {
        $dto = ProjectTaskDto::fromArray([
            'project_id' => 1,
            'name' => 'Build API',
            'priority' => 'high',
            'status' => 'todo',
        ]);

        $this->assertNull($dto->description);
        $this->assertNull($dto->assignee_id);
        $this->assertNull($dto->rejected_reason);
        $this->assertNull($dto->due_date);
    }

    public function test_to_array_preserves_payload_shape(): void
    {
        $dto = ProjectTaskDto::fromArray($this->payload());

        $this->assertSame($this->payload(), $dto->toArray());
    }

    public function test_from_array_for_update_merges_with_existing_task(): void
    {
        $task = $this->makeProjectTask([
            'project_id' => 10,
            'name' => 'Old Task',
            'description' => 'Old desc',
            'assignee_id' => 3,
            'priority' => 'low',
            'status' => 'todo',
            'rejected_reason' => null,
            'due_date' => '2025-07-01',
        ]);

        $dto = ProjectTaskDto::fromArrayForUpdate([
            'name' => 'New Task',
            'status' => 'in_progress',
        ], $task);

        $this->assertSame(10, $dto->project_id);
        $this->assertSame('New Task', $dto->name);
        $this->assertSame('in_progress', $dto->status);
        // Unchanged fields retain existing values
        $this->assertSame('Old desc', $dto->description);
        $this->assertSame(3, $dto->assignee_id);
        $this->assertSame('low', $dto->priority);
    }

    public function test_from_array_for_update_uses_all_provided_data(): void
    {
        $task = $this->makeProjectTask([
            'project_id' => 10,
            'name' => 'Old Task',
        ]);

        $dto = ProjectTaskDto::fromArrayForUpdate($this->payload(), $task);

        $this->assertSame(1, $dto->project_id);
        $this->assertSame('Build API', $dto->name);
        $this->assertSame('high', $dto->priority);
    }

    private function makeProjectTask(array $attributes): ProjectTask
    {
        $task = new ProjectTask();
        foreach ($attributes as $key => $value) {
            $task->{$key} = $value;
        }

        return $task;
    }

    private function payload(): array
    {
        return [
            'project_id' => 1,
            'name' => 'Build API',
            'description' => 'Create REST endpoints',
            'assignee_id' => 5,
            'priority' => 'high',
            'status' => 'in_progress',
            'rejected_reason' => 'Needs more details',
            'due_date' => '2025-06-01',
        ];
    }
}
