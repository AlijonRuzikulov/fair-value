<?php

namespace App\Support;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV download that opens correctly in Excel (UTF-8 with BOM, ";" as the
 * separator, which is what Excel expects with Uzbek and Russian settings).
 */
class CsvExport
{
    /**
     * @param  array<string, callable>  $columns  heading => value callback
     */
    public static function download(string $filename, Builder $query, array $columns): StreamedResponse
    {
        // Every export is written to the audit log: what, how many rows, with which filters.
        Activity::record('exported', null, [
            'file' => $filename,
            'rows' => (clone $query)->count(),
            'filters' => array_filter(request()->except(['page']), fn ($v) => $v !== null && $v !== ''),
        ], $filename);

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_keys($columns), ';', escape: '');

            $query->chunk(500, function ($items) use ($out, $columns) {
                foreach ($items as $item) {
                    fputcsv($out, array_map(fn ($cb) => self::cell($cb($item)), array_values($columns)), ';', escape: '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        // Prevent spreadsheet formula injection.
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)) {
            $value = "'".$value;
        }

        return $value;
    }
}
