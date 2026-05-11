<?php

namespace Tests\Unit\DTOs;

use App\DTOs\TeamDto;
use App\Models\Team;
use Tests\TestCase;

class TeamDtoTest extends TestCase
{
    public function test_from_array_maps_all_fields(): void
    {
        $dto = TeamDto::fromArray($this->payload());

        $this->assertSame('Backend Team', $dto->name);
        $this->assertSame(8, $dto->expected_size);
        $this->assertSame('Core backend team', $dto->description);
        $this->assertSame('team-icons/activity.png', $dto->icon);
        $this->assertSame('development', $dto->department);
        $this->assertSame('active', $dto->status);
        $this->assertSame('1', $dto->team_lead_id);
        $this->assertSame(['Write code', 'Review PRs'], $dto->responsibilities);
    }

    public function test_from_array_uses_defaults_for_optional_fields(): void
    {
        $dto = TeamDto::fromArray([
            'name' => 'Design Team',
            'department' => 'design',
        ]);

        $this->assertNull($dto->expected_size);
        $this->assertNull($dto->description);
        $this->assertNull($dto->icon);
        $this->assertSame('active', $dto->status);
        $this->assertNull($dto->team_lead_id);
        $this->assertSame([], $dto->responsibilities);
    }

    public function test_to_array_preserves_values(): void
    {
        $dto = TeamDto::fromArray($this->payload());
        $array = $dto->toArray();

        $this->assertSame('Backend Team', $array['name']);
        $this->assertSame(8, $array['expected_size']);
        $this->assertSame('Core backend team', $array['description']);
        $this->assertSame('team-icons/activity.png', $array['icon']);
        $this->assertSame('development', $array['department']);
        $this->assertSame('active', $array['status']);
        $this->assertSame('1', $array['team_lead_id']);
        $this->assertSame(['Write code', 'Review PRs'], $array['responsibilities']);
    }

    public function test_from_array_for_update_merges_with_existing_team(): void
    {
        $team = $this->makeTeam([
            'name' => 'Old Team',
            'expected_size' => 5,
            'description' => 'Old description',
            'icon' => 'team-icons/old.png',
            'department' => 'development',
            'status' => 'active',
            'team_lead_id' => 10,
            'responsibilities' => ['Old task'],
        ]);

        $dto = TeamDto::fromArrayForUpdate([
            'name' => 'New Team',
            'expected_size' => 12,
        ], $team);

        $this->assertSame('New Team', $dto->name);
        $this->assertSame(12, $dto->expected_size);
        // Unchanged fields retain existing values
        $this->assertSame('development', $dto->department);
        $this->assertSame('active', $dto->status);
        $this->assertSame('10', $dto->team_lead_id);
        $this->assertSame(['Old task'], $dto->responsibilities);
    }

    public function test_from_array_for_update_uses_all_provided_data(): void
    {
        $team = $this->makeTeam([
            'name' => 'Old Team',
        ]);

        $dto = TeamDto::fromArrayForUpdate($this->payload(), $team);

        $this->assertSame('Backend Team', $dto->name);
        $this->assertSame(8, $dto->expected_size);
        $this->assertSame('development', $dto->department);
    }

    private function makeTeam(array $attributes): Team
    {
        $team = new Team();
        foreach ($attributes as $key => $value) {
            $team->{$key} = $value;
        }

        return $team;
    }

    private function payload(): array
    {
        return [
            'name' => 'Backend Team',
            'expected_size' => 8,
            'description' => 'Core backend team',
            'icon' => 'team-icons/activity.png',
            'department' => 'development',
            'status' => 'active',
            'team_lead_id' => '1',
            'responsibilities' => ['Write code', 'Review PRs'],
        ];
    }
}
