<?php

namespace Tests\Unit\DTOs;

use App\DTOs\Performance\PerformanceReviewDto;
use Tests\TestCase;

class PerformanceReviewDtoTest extends TestCase
{
    public function test_from_request_maps_all_fields(): void
    {
        $dto = PerformanceReviewDto::fromRequest($this->payload());

        $this->assertSame(1, $dto->cycle_id);
        $this->assertSame(10, $dto->staff_member_id);
        $this->assertSame(20, $dto->reviewer_id);
        $this->assertSame('in_progress', $dto->status);
    }

    public function test_from_request_uses_default_status_when_not_provided(): void
    {
        $dto = PerformanceReviewDto::fromRequest([
            'cycle_id' => 1,
            'staff_member_id' => 10,
            'reviewer_id' => 20,
        ]);

        $this->assertSame('pending_self', $dto->status);
    }

    public function test_to_array_includes_status_when_present(): void
    {
        $dto = PerformanceReviewDto::fromRequest($this->payload());
        $array = $dto->toArray();

        $this->assertSame(1, $array['cycle_id']);
        $this->assertSame(10, $array['staff_member_id']);
        $this->assertSame(20, $array['reviewer_id']);
        $this->assertSame('in_progress', $array['status']);
    }

    public function test_to_array_omits_status_when_null(): void
    {
        $dto = new PerformanceReviewDto(
            cycle_id: 1,
            staff_member_id: 10,
            reviewer_id: 20,
            status: null,
        );
        $array = $dto->toArray();

        $this->assertArrayNotHasKey('status', $array);
        $this->assertArrayHasKey('cycle_id', $array);
        $this->assertArrayHasKey('staff_member_id', $array);
        $this->assertArrayHasKey('reviewer_id', $array);
    }

    private function payload(): array
    {
        return [
            'cycle_id' => 1,
            'staff_member_id' => 10,
            'reviewer_id' => 20,
            'status' => 'in_progress',
        ];
    }
}
