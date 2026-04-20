<?php

namespace App\DTOs\Performance;
 
use Illuminate\Support\Facades\Auth;
 

class GoalDto
{
    public function __construct(
        public readonly int $employee_id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $goal_type,
        public readonly ?string $category,
        public readonly ?string $target_value,
        public readonly ?string $current_value,
        public readonly ?string $unit,
        public readonly ?float $weight,
        public readonly string $start_date,
        public readonly string $due_date,
        public readonly ?string $status,
        public readonly ?int $completion_percentage,
        public readonly ?int $created_by,
        public readonly ?int $assigned_by,
        public readonly ?int $linked_review_id
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            $data['employee_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['goal_type'],
            $data['category'] ?? null,
            $data['target_value'] ?? null,
            $data['current_value'] ?? null,
            $data['unit'] ?? null,
            isset($data['weight']) ? (float)$data['weight'] : null,
            $data['start_date'],
            $data['due_date'],
            $data['status'] ?? 'not_started',
            $data['completion_percentage'] ?? 0,
            Auth::id() ?? $data['created_by'] ?? null,
            $data['assigned_by'] ?? null,
            $data['linked_review_id'] ?? null
        );
    }

    public function toArray(): array
    {
        $array = [
            'employee_id' => $this->employee_id,
            'title' => $this->title,
            'goal_type' => $this->goal_type,
            'start_date' => $this->start_date,
            'due_date' => $this->due_date,
        ];

        if ($this->description !== null) $array['description'] = $this->description;
        if ($this->category !== null) $array['category'] = $this->category;
        if ($this->target_value !== null) $array['target_value'] = $this->target_value;
        if ($this->current_value !== null) $array['current_value'] = $this->current_value;
        if ($this->unit !== null) $array['unit'] = $this->unit;
        if ($this->weight !== null) $array['weight'] = $this->weight;
        if ($this->status !== null) $array['status'] = $this->status;
        if ($this->completion_percentage !== null) $array['completion_percentage'] = $this->completion_percentage;
        if ($this->created_by !== null) $array['created_by'] = $this->created_by;
        if ($this->assigned_by !== null) $array['assigned_by'] = $this->assigned_by;
        if ($this->linked_review_id !== null) $array['linked_review_id'] = $this->linked_review_id;

        return $array;
    }
}
