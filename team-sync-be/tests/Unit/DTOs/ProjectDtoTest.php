<?php

namespace Tests\Unit\DTOs;

use App\DTOs\ProjectDto;
use App\Models\Project;
use Tests\TestCase;

class ProjectDtoTest extends TestCase
{
    public function test_from_array_maps_all_fields(): void
    {
        $dto = ProjectDto::fromArray($this->payload());

        $this->assertSame('Website Redesign', $dto->name);
        $this->assertSame('internal', $dto->type);
        $this->assertSame('high', $dto->priority);
        $this->assertSame('active', $dto->status);
        $this->assertSame('2025-01-01', $dto->start_date);
        $this->assertSame('2025-06-30', $dto->end_date);
        $this->assertSame('Redesign the website', $dto->description);
        $this->assertSame('projects/photo.webp', $dto->photo);
        $this->assertSame(50000000.0, $dto->budget);
        $this->assertSame(1, $dto->project_leader_id);
    }

    public function test_from_array_uses_defaults_for_optional_fields(): void
    {
        $dto = ProjectDto::fromArray([
            'name' => 'Project X',
            'type' => 'client',
            'priority' => 'medium',
            'status' => 'draft',
            'start_date' => '2025-01-01',
        ]);

        $this->assertNull($dto->end_date);
        $this->assertNull($dto->description);
        $this->assertNull($dto->photo);
        $this->assertNull($dto->budget);
        $this->assertNull($dto->project_leader_id);
    }

    public function test_from_array_casts_budget_to_float(): void
    {
        $dto = ProjectDto::fromArray(array_merge($this->payload(), [
            'budget' => 50000000,
        ]));

        $this->assertIsFloat($dto->budget);
        $this->assertSame(50000000.0, $dto->budget);
    }

    public function test_to_array_preserves_values(): void
    {
        $dto = ProjectDto::fromArray($this->payload());
        $array = $dto->toArray();

        $this->assertSame('Website Redesign', $array['name']);
        $this->assertSame('internal', $array['type']);
        $this->assertSame('high', $array['priority']);
        $this->assertSame('active', $array['status']);
        $this->assertSame('2025-01-01', $array['start_date']);
        $this->assertSame('2025-06-30', $array['end_date']);
        $this->assertSame('Redesign the website', $array['description']);
        $this->assertSame('projects/photo.webp', $array['photo']);
        $this->assertSame(50000000.0, $array['budget']);
        $this->assertSame(1, $array['project_leader_id']);
    }

    public function test_from_array_for_update_merges_with_existing_project(): void
    {
        $project = $this->makeProject([
            'name' => 'Old Project',
            'type' => 'internal',
            'priority' => 'low',
            'status' => 'draft',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'description' => 'Old description',
            'photo' => 'projects/old.webp',
            'budget' => 10000000.0,
            'project_leader_id' => 5,
        ]);

        $dto = ProjectDto::fromArrayForUpdate([
            'name' => 'New Project',
            'budget' => 20000000,
        ], $project);

        $this->assertSame('New Project', $dto->name);
        $this->assertSame(20000000.0, $dto->budget);
        // Unchanged fields retain existing values
        $this->assertSame('internal', $dto->type);
        $this->assertSame('low', $dto->priority);
        $this->assertSame('draft', $dto->status);
        $this->assertSame('Old description', $dto->description);
        $this->assertSame('projects/old.webp', $dto->photo);
        $this->assertSame(5, $dto->project_leader_id);
    }

    public function test_from_array_for_update_uses_all_provided_data(): void
    {
        $project = $this->makeProject([
            'name' => 'Old Project',
        ]);

        $dto = ProjectDto::fromArrayForUpdate($this->payload(), $project);

        $this->assertSame('Website Redesign', $dto->name);
        $this->assertSame('active', $dto->status);
        $this->assertSame(50000000.0, $dto->budget);
    }

    private function makeProject(array $attributes): Project
    {
        $project = new Project;
        foreach ($attributes as $key => $value) {
            $project->{$key} = $value;
        }

        return $project;
    }

    private function payload(): array
    {
        return [
            'name' => 'Website Redesign',
            'type' => 'internal',
            'priority' => 'high',
            'status' => 'active',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
            'description' => 'Redesign the website',
            'photo' => 'projects/photo.webp',
            'budget' => 50000000,
            'project_leader_id' => 1,
        ];
    }
}
