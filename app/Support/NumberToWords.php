<?php

namespace App\Support;

/**
 * Amounts in words in Uzbek (Latin), as written in valuation reports:
 * 514 168 000 → "besh yuz o‘n to‘rt million bir yuz oltmish sakkiz ming".
 * The Cyrillic form is made with Transliterator.
 */
final class NumberToWords
{
    private const ONES = ['', 'bir', 'ikki', 'uch', 'to‘rt', 'besh', 'olti', 'yetti', 'sakkiz', 'to‘qqiz'];

    private const TENS = ['', 'o‘n', 'yigirma', 'o‘ttiz', 'qirq', 'ellik', 'oltmish', 'yetmish', 'sakson', 'to‘qson'];

    private const SCALES = ['', 'ming', 'million', 'milliard', 'trillion'];

    public static function uz(int $number): string
    {
        if ($number === 0) {
            return 'nol';
        }
        if ($number < 0) {
            return 'minus '.self::uz(-$number);
        }

        $words = [];
        // Groups of three digits, the lowest first: 514168000 → [000, 168, 514].
        $digits = (string) $number;
        $groups = array_reverse(str_split(str_pad($digits, (int) ceil(strlen($digits) / 3) * 3, '0', STR_PAD_LEFT), 3));
        foreach ($groups as $scale => $group) {
            $value = (int) $group;
            if ($value === 0) {
                continue;
            }
            $part = self::hundreds($value);
            if (self::SCALES[$scale] !== '') {
                $part .= ' '.self::SCALES[$scale];
            }
            array_unshift($words, $part);
        }

        return implode(' ', $words);
    }

    /** Words for 1–999; hundreds are always counted ("bir yuz"). */
    private static function hundreds(int $value): string
    {
        $parts = [];
        if ($value >= 100) {
            $parts[] = self::ONES[intdiv($value, 100)].' yuz';
        }
        $rest = $value % 100;
        if ($rest >= 10) {
            $parts[] = self::TENS[intdiv($rest, 10)];
        }
        if ($rest % 10) {
            $parts[] = self::ONES[$rest % 10];
        }

        return implode(' ', $parts);
    }
}
