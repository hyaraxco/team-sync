<?php

namespace App\DTOs\Performance;

use Illuminate\Support\Facades\Auth;

class FeedbackDto
{
    public function __construct(
        public readonly int $staff_member_id,
        public readonly int $given_by,
        public readonly string $feedback_type,
        public readonly ?string $category,
        public readonly string $content,
        public readonly bool $is_private,
        public readonly ?int $linked_goal_id
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            $data['staff_member_id'],
            Auth::id() ?? $data['given_by'], // fallback for testing or manual creation
            $data['feedback_type'],
            $data['category'] ?? null,
            $data['content'],
            $data['is_private'] ?? false,
            $data['linked_goal_id'] ?? null
        );
    }

    public function toArray(): array
    {
        $array = [
            'staff_member_id' => $this->staff_member_id,
            'given_by' => $this->given_by,
            'feedback_type' => $this->feedback_type,
            'content' => $this->content,
            'is_private' => $this->is_private,
        ];

        if ($this->category !== null) {
            $array['category'] = $this->category;
        }
        if ($this->linked_goal_id !== null) {
            $array['linked_goal_id'] = $this->linked_goal_id;
        }

        return $array;
    }
}
