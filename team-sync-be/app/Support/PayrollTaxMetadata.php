<?php

namespace App\Support;

final class PayrollTaxMetadata
{
    public static function hash(mixed $value): string
    {
        return hash('sha256', json_encode(self::normalize($value), JSON_THROW_ON_ERROR));
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            $item = self::normalize($item);
        }
        return $value;
    }
}
