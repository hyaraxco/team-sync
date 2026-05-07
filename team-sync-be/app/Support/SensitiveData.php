<?php

namespace App\Support;

class SensitiveData
{
    public static function hash(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        return hash('sha256', $normalized);
    }
}
