<?php

namespace App\Enums;

enum Religion: string
{
    case ISLAM = 'islam';
    case KRISTEN = 'kristen';
    case KATOLIK = 'katolik';
    case HINDU = 'hindu';
    case BUDHA = 'budha';
    case KONGHUCU = 'konghucu';

    public function label(): string
    {
        return match ($this) {
            self::ISLAM => 'Islam',
            self::KRISTEN => 'Kristen Protestan',
            self::KATOLIK => 'Katolik',
            self::HINDU => 'Hindu',
            self::BUDHA => 'Budha',
            self::KONGHUCU => 'Konghucu',
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
