<?php

namespace App\Support;

/**
 * Parses numbers typed by people: "1 250 000", "1 250 000,50", "12.5".
 */
class NumberInput
{
    /** Returns a numeric string, null for empty input, or the input unchanged when it is not a string. */
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $clean = str_replace([' ', "\u{00A0}", "\u{202F}", "'"], '', trim($value));
        if ($clean === '') {
            return null;
        }

        // A comma is a decimal separator unless a dot is present too.
        return str_contains($clean, '.') ? str_replace(',', '', $clean) : str_replace(',', '.', $clean);
    }

    public static function float(?string $value): ?float
    {
        $clean = self::normalize($value);

        return is_numeric($clean) ? (float) $clean : null;
    }
}
