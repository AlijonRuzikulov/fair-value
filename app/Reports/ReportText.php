<?php

namespace App\Reports;

use App\Models\Setting;
use Illuminate\Support\HtmlString;

/**
 * Standard sections of the valuation report (terms, statements, macro
 * data, methodology…) that are the same in every report. The company edits
 * them on the "Report texts" page; the defaults are in resources/report/texts.
 *
 * Markup: a blank line starts a new paragraph; "- " starts a list item;
 * "## " a heading; "> " a centred formula; lines with " | " make a table
 * (the first line is the header); **bold**.
 */
final class ReportText
{
    public const KEYS = ['terms', 'compliance', 'sources', 'macro', 'market', 'hbu', 'methodology', 'reconciliation', 'quality', 'assumptions', 'contract'];

    /** Words in braces that the contract text may use; they are replaced with the data of the valuation. */
    public const CONTRACT_PLACEHOLDERS = ['object', 'address', 'purpose', 'value_type', 'fee', 'fee_words', 'vat', 'insurance', 'membership', 'deadline', 'customer', 'company', 'city'];

    /** @return array<string, string> section key → title shown on the editing page */
    public static function titles(): array
    {
        return [
            'terms' => __('Basic terms and definitions'),
            'assumptions' => __('Assumptions and limiting conditions (cover letter)'),
            'compliance' => __('Statement of compliance with the national standards'),
            'sources' => __('Sources of information'),
            'macro' => __('Macroeconomic indicators'),
            'market' => __('Analysis of the real estate market'),
            'hbu' => __('Highest and best use analysis'),
            'methodology' => __('Valuation approaches and methods'),
            'reconciliation' => __('Reconciliation of the results'),
            'quality' => __('Statement on the quality of the report'),
            'contract' => __('Service contract: terms'),
        ];
    }

    public static function default(string $key): string
    {
        $path = resource_path("report/texts/{$key}.txt");

        return is_file($path) ? trim((string) file_get_contents($path)) : '';
    }

    /** The company's text of a section, or the default one. */
    public static function get(string $key): string
    {
        return trim((string) Setting::get('report_text_'.$key, '')) ?: self::default($key);
    }

    public static function html(string $text, ReportScript $script): HtmlString
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $html = [];

        foreach (preg_split("/\n\\s*\n/", $text) as $block) {
            $lines = array_values(array_filter(array_map('rtrim', explode("\n", $block)), fn ($l) => trim($l) !== ''));
            if ($lines === []) {
                continue;
            }

            if (count($lines) > 1 && count(array_filter($lines, fn ($l) => str_contains($l, ' | '))) === count($lines)) {
                $html[] = self::table($lines, $script);

                continue;
            }

            // Only one of the two is collected at a time: a list item ends the paragraph and vice versa.
            $list = [];
            $paragraph = [];
            $flush = function () use (&$list, &$paragraph, &$html) {
                if ($paragraph) {
                    $html[] = '<p>'.implode(' ', $paragraph).'</p>';
                    $paragraph = [];
                }
                if ($list) {
                    $html[] = '<ul>'.implode('', array_map(fn ($item) => "<li>{$item}</li>", $list)).'</ul>';
                    $list = [];
                }
            };
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, '- ') || str_starts_with($line, '• ')) {
                    if ($paragraph) {
                        $flush();
                    }
                    $list[] = self::inline(mb_substr($line, 2), $script);
                } elseif (str_starts_with($line, '## ')) {
                    $flush();
                    $html[] = '<h3>'.self::inline(mb_substr($line, 3), $script).'</h3>';
                } elseif (str_starts_with($line, '> ')) {
                    $flush();
                    $html[] = '<p class="formula">'.self::inline(mb_substr($line, 2), $script).'</p>';
                } else {
                    if ($list) {
                        $flush();
                    }
                    $paragraph[] = self::inline($line, $script);
                }
            }
            $flush();
        }

        return new HtmlString(implode("\n", $html));
    }

    /** @param list<string> $lines */
    private static function table(array $lines, ReportScript $script): string
    {
        $rows = array_map(fn ($line) => array_map('trim', explode('|', trim($line, " |\t"))), $lines);
        $head = array_shift($rows);
        $width = count($head);
        $numeric = fn (string $cell) => (bool) preg_match('/^[−\-+]?[\d\s.,]+%?$/u', $cell);

        $out = '<table class="grid"><thead><tr>';
        foreach ($head as $cell) {
            $out .= '<th>'.self::inline($cell, $script).'</th>';
        }
        $out .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $out .= '<tr>';
            foreach (array_pad(array_slice($row, 0, $width), $width, '') as $cell) {
                $out .= '<td'.($numeric($cell) ? ' class="num"' : '').'>'.self::inline($cell, $script).'</td>';
            }
            $out .= '</tr>';
        }

        return $out.'</tbody></table>';
    }

    private static function inline(string $text, ReportScript $script): string
    {
        $html = e($script->t($text));

        return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html);
    }
}
