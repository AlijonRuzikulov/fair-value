<?php

namespace App\Support;

/**
 * Sequential report numbers shared by all object types: "2026/0001".
 */
class ReportNumber
{
    public static function next(): string
    {
        $year = now()->year;
        $max = 0;

        foreach (Registry::valuationModels() as $class) {
            $class::withTrashed()
                ->where('report_number', 'like', $year.'/%')
                ->pluck('report_number')
                ->each(function ($number) use (&$max) {
                    $max = max($max, (int) substr($number, 5));
                });
        }

        return sprintf('%d/%04d', $year, $max + 1);
    }
}
