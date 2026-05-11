<?php

namespace Tests\Unit\DTOs;

use App\DTOs\JobInformationDto;
use App\Models\JobInformation;
use Tests\TestCase;

class JobInformationDtoTest extends TestCase
{
    public function test_from_array_maps_all_fields(): void
    {
        $dto = JobInformationDto::fromArray($this->payload());

        $this->assertSame('1', $dto->staff_member_id);
        $this->assertSame('Senior Engineer', $dto->job_title);
        $this->assertSame('2', $dto->team_id);
        $this->assertSame('active', $dto->status);
        $this->assertSame('full_time', $dto->employment_type);
        $this->assertSame('office', $dto->work_location);
        $this->assertSame('2024-01-01', $dto->start_date);
        $this->assertSame(12500000.0, $dto->monthly_salary);
    }

    public function test_from_array_uses_null_for_optional_team_id(): void
    {
        $dto = JobInformationDto::fromArray([
            'staff_member_id' => '1',
            'job_title' => 'Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2024-01-01',
            'monthly_salary' => 5000000,
        ]);

        $this->assertNull($dto->team_id);
    }

    public function test_from_array_casts_salary_to_float(): void
    {
        $dto = JobInformationDto::fromArray([
            'staff_member_id' => '1',
            'job_title' => 'Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2024-01-01',
            'monthly_salary' => 12500000,
        ]);

        $this->assertIsFloat($dto->monthly_salary);
        $this->assertSame(12500000.0, $dto->monthly_salary);
    }

    public function test_to_array_preserves_payload_values(): void
    {
        $dto = JobInformationDto::fromArray($this->payload());
        $array = $dto->toArray();

        $this->assertSame('1', $array['staff_member_id']);
        $this->assertSame('Senior Engineer', $array['job_title']);
        $this->assertSame('2', $array['team_id']);
        $this->assertSame('active', $array['status']);
        $this->assertSame('full_time', $array['employment_type']);
        $this->assertSame('office', $array['work_location']);
        $this->assertSame('2024-01-01', $array['start_date']);
        $this->assertSame(12500000.0, $array['monthly_salary']);
    }

    public function test_from_array_for_update_merges_with_existing_job(): void
    {
        $job = $this->makeJobInformation([
            'staff_member_id' => 10,
            'job_title' => 'Junior Engineer',
            'team_id' => 5,
            'status' => 'probation',
            'employment_type' => 'contract',
            'work_location' => 'remote',
            'start_date' => '2023-06-15',
            'monthly_salary' => 5000000.0,
        ]);

        $dto = JobInformationDto::fromArrayForUpdate([
            'job_title' => 'Senior Engineer',
            'monthly_salary' => 10000000,
        ], $job);

        $this->assertSame('10', $dto->staff_member_id);
        $this->assertSame('Senior Engineer', $dto->job_title);
        $this->assertSame('5', $dto->team_id);
        $this->assertSame('probation', $dto->status);
        $this->assertSame('contract', $dto->employment_type);
        $this->assertSame('remote', $dto->work_location);
        $this->assertSame(10000000.0, $dto->monthly_salary);
    }

    public function test_from_array_for_update_uses_all_provided_data(): void
    {
        $job = $this->makeJobInformation([
            'staff_member_id' => 10,
            'job_title' => 'Junior Engineer',
        ]);

        $dto = JobInformationDto::fromArrayForUpdate($this->payload(), $job);

        $this->assertSame('1', $dto->staff_member_id);
        $this->assertSame('Senior Engineer', $dto->job_title);
        $this->assertSame('active', $dto->status);
    }

    private function makeJobInformation(array $attributes): JobInformation
    {
        $job = new JobInformation();
        foreach ($attributes as $key => $value) {
            $job->{$key} = $value;
        }

        return $job;
    }

    private function payload(): array
    {
        return [
            'staff_member_id' => '1',
            'job_title' => 'Senior Engineer',
            'team_id' => '2',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2024-01-01',
            'monthly_salary' => 12500000,
        ];
    }
}
