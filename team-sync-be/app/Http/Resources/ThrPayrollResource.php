<?php

namespace App\Http\Resources;

use App\Models\ThrPayroll;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThrPayrollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'religion_event' => $this->religion_event,
            'event_label' => ThrPayroll::eventLabel($this->religion_event),
            'religion_holiday_date' => $this->religion_holiday_date?->format('Y-m-d'),
            'payment_deadline' => $this->payment_deadline?->format('Y-m-d'),
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'status' => $this->status,
            'total_employees' => $this->total_employees,
            'total_thr_amount' => (float) $this->total_thr_amount,
            'total_tax_amount' => (float) $this->total_tax_amount,
            'total_net_amount' => (float) $this->total_net_amount,
            'notes' => $this->notes,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'approver' => $this->whenLoaded('approver', fn () => [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ]),
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
