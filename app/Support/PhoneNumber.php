<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        // Common Nigerian local format: 0803... -> 234803...
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '234'.substr($digits, 1);
        }

        // +234... already stripped to 234...
        if (str_starts_with($digits, '234') && strlen($digits) >= 13) {
            return $digits;
        }

        return $digits;
    }

    public static function matches(?string $stored, ?string $input): bool
    {
        $a = self::normalize($stored);
        $b = self::normalize($input);

        return $a !== '' && $a === $b;
    }
}
