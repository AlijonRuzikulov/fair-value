<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Number, money and date formatting used across the interface.
 */
class Format
{
    /** 1250000.5 → "1 250 000,50" */
    public static function number(float|int|string|null $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, $decimals, ',', "\u{00A0}");
    }

    /** Amount in UZS with the currency name. */
    public static function money(float|int|string|null $value, bool $withCurrency = true): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $decimals = fmod((float) $value, 1.0) === 0.0 ? 0 : 2;

        return self::number($value, $decimals).($withCurrency ? "\u{00A0}".__('sum') : '');
    }

    /** Large amounts in a compact form: 1.2 bn, 350 mln. */
    public static function compact(float|int|string|null $value): string
    {
        $v = (float) $value;

        return match (true) {
            abs($v) >= 1e12 => self::number($v / 1e12, 1).' '.__('trln'),
            abs($v) >= 1e9 => self::number($v / 1e9, 1).' '.__('bn'),
            abs($v) >= 1e6 => self::number($v / 1e6, 1).' '.__('mln'),
            abs($v) >= 1e3 => self::number($v / 1e3, 0).' '.__('thsd'),
            default => self::number($v),
        };
    }

    public static function date(?CarbonInterface $date): string
    {
        return $date ? $date->format('d.m.Y') : '—';
    }

    public static function datetime(?CarbonInterface $date): string
    {
        return $date ? $date->format('d.m.Y H:i') : '—';
    }

    public static function bytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => self::number($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => self::number($bytes / 1024, 0).' KB',
            default => $bytes.' B',
        };
    }

    /** "+998901590102" → "+998 90 159-01-02" */
    public static function phone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        }
        if (strlen($digits) !== 12) {
            return (string) $phone;
        }

        return sprintf('+%s %s %s-%s-%s',
            substr($digits, 0, 3), substr($digits, 3, 2), substr($digits, 5, 3),
            substr($digits, 8, 2), substr($digits, 10, 2));
    }

    public static function telLink(?string $phone): string
    {
        return 'tel:+'.ltrim(preg_replace('/\D/', '', (string) $phone), '+');
    }
}
