<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * Sequential contract and report numbers shared by all object types, in
 * the format the company sets: "{n}-{yy}" gives 668-26, "{yyyy}/{nnnn}"
 * gives 2026/0001, "20/{n}-{yy}" gives 20/551-26. The sequence continues
 * from the largest number of the same format, so a number typed by hand
 * (when moving from another system) is followed by the next one.
 */
class DocumentNumber
{
    public const FORMATS = ['contract' => 'contract_number_format', 'report' => 'report_number_format'];

    private const COLUMNS = ['contract' => 'contract_number', 'report' => 'report_number'];

    public static function next(string $kind, ?CarbonInterface $date = null): string
    {
        $date ??= now();
        $format = self::format($kind);
        $regex = self::regex($format, $date);
        $column = self::COLUMNS[$kind];

        $max = 0;
        foreach (Registry::valuationModels() as $class) {
            $class::withTrashed()->whereNotNull($column)->pluck($column)->each(function ($number) use ($regex, &$max) {
                if (preg_match($regex, trim((string) $number), $m)) {
                    $max = max($max, (int) $m[1]);
                }
            });
        }

        return self::render($format, $max + 1, $date);
    }

    public static function format(string $kind): string
    {
        $format = trim((string) Setting::get(self::FORMATS[$kind]));

        return self::valid($format) ? $format : (string) config('fairvalue.company.'.self::FORMATS[$kind]);
    }

    /** A format must have exactly one place for the sequence number. */
    public static function valid(string $format): bool
    {
        return preg_match_all('/\{n(nnn)?\}/', $format) === 1;
    }

    public static function render(string $format, int $n, CarbonInterface $date): string
    {
        return strtr($format, [
            '{nnnn}' => str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            '{n}' => (string) $n,
            '{yyyy}' => $date->format('Y'),
            '{yy}' => $date->format('y'),
        ]);
    }

    private static function regex(string $format, CarbonInterface $date): string
    {
        $quoted = preg_quote($format, '/');

        return '/^'.strtr($quoted, [
            preg_quote('{nnnn}', '/') => '(\d+)',
            preg_quote('{n}', '/') => '(\d+)',
            preg_quote('{yyyy}', '/') => $date->format('Y'),
            preg_quote('{yy}', '/') => $date->format('y'),
        ]).'$/u';
    }
}
