<?php

namespace App\DTOs\Performance;

class PerformanceReviewDto
{
    public function __construct(
        public readonly int $cycle_id,
        public readonly int $staff_member_id,
        public readonly int $reviewer_id,
        public readonly ?string $status
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            $data['cycle_id'],
            $data['staff_member_id'],
            $data['reviewer_id'],
            $data['status'] ?? 'pending_self'
        );
    }

    public function toArray(): array
    {
        $array = [
            'cycle_id' => $this->cycle_id,
            'staff_member_id' => $this->staff_member_id,
            'reviewer_id' => $this->reviewer_id,
        ];

        if ($this->status !== null) {
            $array['status'] = $this->status;
        }

        return $array;
    }
}
