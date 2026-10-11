<?php

namespace App\Support;

/**
 * Reads CSV files saved from Excel: UTF-8 (with or without BOM) or
 * Windows-1251, separated by ";" or ",". The first row is a header.
 */
class CsvImport
{
    /** @return list<list<string>> */
    public static function rows(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        $header = true;
        while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            if ($header) {
                $header = false;

                continue;
            }
            if ($row === [null] || implode('', $row) === '') {
                continue;
            }
            $rows[] = array_map(fn ($cell) => trim((string) $cell), $row);
        }
        fclose($stream);

        return $rows;
    }
}
