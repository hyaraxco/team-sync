<?php

namespace App\Enums;

enum PtkpStatus: string
{
    case TK_0 = 'TK/0';
    case TK_1 = 'TK/1';
    case TK_2 = 'TK/2';
    case TK_3 = 'TK/3';
    case K_0 = 'K/0';
    case K_1 = 'K/1';
    case K_2 = 'K/2';
    case K_3 = 'K/3';
    case K_I_0 = 'K/I/0';
    case K_I_1 = 'K/I/1';
    case K_I_2 = 'K/I/2';
    case K_I_3 = 'K/I/3';

    public function label(): string
    {
        return match ($this) {
            self::TK_0 => 'TK/0 - Tidak Kawin, Tanpa Tanggungan',
            self::TK_1 => 'TK/1 - Tidak Kawin, 1 Tanggungan',
            self::TK_2 => 'TK/2 - Tidak Kawin, 2 Tanggungan',
            self::TK_3 => 'TK/3 - Tidak Kawin, 3 Tanggungan',
            self::K_0 => 'K/0 - Kawin, Tanpa Tanggungan',
            self::K_1 => 'K/1 - Kawin, 1 Tanggungan',
            self::K_2 => 'K/2 - Kawin, 2 Tanggungan',
            self::K_3 => 'K/3 - Kawin, 3 Tanggungan',
            self::K_I_0 => 'K/I/0 - Kawin, Penghasilan Istri Digabung, Tanpa Tanggungan',
            self::K_I_1 => 'K/I/1 - Kawin, Penghasilan Istri Digabung, 1 Tanggungan',
            self::K_I_2 => 'K/I/2 - Kawin, Penghasilan Istri Digabung, 2 Tanggungan',
            self::K_I_3 => 'K/I/3 - Kawin, Penghasilan Istri Digabung, 3 Tanggungan',
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
