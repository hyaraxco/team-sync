<?php

namespace App\Enums;

enum PayrollStatus: string
{
    case PROCESSING = 'processing';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::PROCESSING => 'Processing',
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::PAID => 'Paid',
        };
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }
}
