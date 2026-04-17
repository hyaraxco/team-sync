<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case SINGLE = 'single';
    case MARRIED = 'married';
    case WIDOWED = 'widowed';
    case DIVORCED = 'divorced';

    public function label(): string
    {
        return match ($this) {
            self::SINGLE => 'Belum Menikah',
            self::MARRIED => 'Menikah',
            self::WIDOWED => 'Janda/Duda',
            self::DIVORCED => 'Cerai',
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
