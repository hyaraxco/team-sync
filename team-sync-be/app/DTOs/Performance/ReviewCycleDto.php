<?php

namespace App\DTOs\Performance;
 
use Illuminate\Support\Facades\Auth;
 



class ReviewCycleDto
{
    public function __construct(
        public readonly string $name,
        public readonly string $cycle_type,
        public readonly string $start_date,
        public readonly string $end_date,
        public readonly string $review_period_start,
        public readonly string $review_period_end,
        public readonly ?string $status,
        public readonly ?string $self_assessment_deadline,
        public readonly ?string $manager_assessment_deadline,
        public readonly ?string $calibration_deadline,
        public readonly ?int $created_by
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            $data['name'],
            $data['cycle_type'],
            $data['start_date'],
            $data['end_date'],
            $data['review_period_start'],
            $data['review_period_end'],
            $data['status'] ?? 'draft',
            $data['self_assessment_deadline'] ?? null,
            $data['manager_assessment_deadline'] ?? null,
            $data['calibration_deadline'] ?? null,
            Auth::id() ?? $data['created_by'] ?? null
        );
    }

    public function toArray(): array
    {
        $array = [
            'name' => $this->name,
            'cycle_type' => $this->cycle_type,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'review_period_start' => $this->review_period_start,
            'review_period_end' => $this->review_period_end,
        ];

        if ($this->status !== null) $array['status'] = $this->status;
        if ($this->self_assessment_deadline !== null) $array['self_assessment_deadline'] = $this->self_assessment_deadline;
        if ($this->manager_assessment_deadline !== null) $array['manager_assessment_deadline'] = $this->manager_assessment_deadline;
        if ($this->calibration_deadline !== null) $array['calibration_deadline'] = $this->calibration_deadline;
        if ($this->created_by !== null) $array['created_by'] = $this->created_by;

        return $array;
    }
}
