<?php

namespace App\Support;

class PersonName
{
    public static function normalize(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($collapsed);
    }

    public static function isValid(string $value): bool
    {
        $name = self::normalize($value);

        if ($name === '' || mb_strlen($name) < 3 || mb_strlen($name) > 255) {
            return false;
        }

        if (preg_match('/\d/u', $name) || str_contains($name, '@')) {
            return false;
        }

        $parts = explode(' ', $name);
        if (count($parts) < 2) {
            return false;
        }

        $hasFullWord = false;
        foreach ($parts as $part) {
            if (preg_match('/^[\p{L}]\.?$/u', $part) === 1) {
                continue;
            }

            $letterCount = preg_match_all('/\p{L}/u', $part);
            if (preg_match('/^[\p{L}]+(?:[\'’\-][\p{L}]+)*\.?$/u', $part) === 1 && $letterCount >= 2) {
                $hasFullWord = true;

                continue;
            }

            return false;
        }

        return $hasFullWord;
    }
}
