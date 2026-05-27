<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HybridScheduleOverrideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'staff_member_id'       => $this->staff_member_id,
            'staff_member'          => new StaffMemberProfileResource($this->whenLoaded('staffMember')),
            'date'                  => $this->date?->toDateString(),
            'planned_work_mode'     => $this->planned_work_mode,
            'current_schedule_mode' => $this->resolveCurrentScheduleMode(),
            'reason'                => $this->reason,
            'status'                => $this->status,
            'review_notes'          => $this->review_notes,
            'approved_at'           => $this->approved_at?->toIso8601String(),
            'created_at'            => $this->created_at,
        ];
    }

    /**
     * Derive what the employee's base schedule says for the override date's weekday.
     * Requires staffMember.hybridWorkSchedules to be eager-loaded.
     */
    private function resolveCurrentScheduleMode(): ?string
    {
        if (! $this->date || ! $this->relationLoaded('staffMember')) {
            return null;
        }

        $weekday = strtolower(Carbon::parse($this->date)->englishDayOfWeek);

        if (! in_array($weekday, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], true)) {
            return null;
        }

        $targetDate = Carbon::parse($this->date);

        $schedule = $this->staffMember?->hybridWorkSchedules
            ?->filter(function ($s) use ($targetDate) {
                return $s->effective_from?->lte($targetDate)
                    && ($s->effective_until === null || $s->effective_until?->gte($targetDate));
            })
            ->sortByDesc('effective_from')
            ->first();

        return $schedule?->{$weekday};
    }
}
