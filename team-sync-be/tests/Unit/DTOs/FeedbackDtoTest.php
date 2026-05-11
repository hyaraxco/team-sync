<?php

namespace Tests\Unit\DTOs;

use App\DTOs\Performance\FeedbackDto;
use Tests\TestCase;

class FeedbackDtoTest extends TestCase
{
    public function test_from_request_maps_all_fields(): void
    {
        $dto = FeedbackDto::fromRequest($this->payload());

        $this->assertSame(1, $dto->staff_member_id);
        $this->assertSame(2, $dto->given_by);
        $this->assertSame('peer', $dto->feedback_type);
        $this->assertSame('technical', $dto->category);
        $this->assertSame('Great work on the API', $dto->content);
        $this->assertTrue($dto->is_private);
        $this->assertSame(5, $dto->linked_goal_id);
    }

    public function test_from_request_uses_defaults_for_optional_fields(): void
    {
        $dto = FeedbackDto::fromRequest([
            'staff_member_id' => 1,
            'given_by' => 2,
            'feedback_type' => 'peer',
            'content' => 'Good job',
        ]);

        $this->assertNull($dto->category);
        $this->assertFalse($dto->is_private);
        $this->assertNull($dto->linked_goal_id);
    }

    public function test_from_request_falls_back_to_given_by_when_no_auth(): void
    {
        $dto = FeedbackDto::fromRequest([
            'staff_member_id' => 1,
            'given_by' => 99,
            'feedback_type' => 'self',
            'content' => 'Self-assessment',
        ]);

        $this->assertSame(99, $dto->given_by);
    }

    public function test_to_array_includes_optional_fields_when_present(): void
    {
        $dto = FeedbackDto::fromRequest($this->payload());
        $array = $dto->toArray();

        $this->assertSame(1, $array['staff_member_id']);
        $this->assertSame(2, $array['given_by']);
        $this->assertSame('peer', $array['feedback_type']);
        $this->assertSame('Great work on the API', $array['content']);
        $this->assertTrue($array['is_private']);
        $this->assertSame('technical', $array['category']);
        $this->assertSame(5, $array['linked_goal_id']);
    }

    public function test_to_array_omits_null_optional_fields(): void
    {
        $dto = FeedbackDto::fromRequest([
            'staff_member_id' => 1,
            'given_by' => 2,
            'feedback_type' => 'peer',
            'content' => 'Good job',
        ]);
        $array = $dto->toArray();

        $this->assertArrayNotHasKey('category', $array);
        $this->assertArrayNotHasKey('linked_goal_id', $array);
    }

    private function payload(): array
    {
        return [
            'staff_member_id' => 1,
            'given_by' => 2,
            'feedback_type' => 'peer',
            'category' => 'technical',
            'content' => 'Great work on the API',
            'is_private' => true,
            'linked_goal_id' => 5,
        ];
    }
}
