<?php

namespace App\Reports;

/**
 * Charts drawn from the tables of a report text (inline SVG, prints as shown).
 *
 * The text asks for one with a line of its own: "[diagramma: Eksport; Import]".
 * The names are looked up in the tables above it, the nearest table first:
 * - row labels → columns over the table's columns (the years), one series per row;
 * - a column header → bars over the rows (the regions), largest first.
 * "= Title" after the names replaces the title made from them.
 */
final class ReportChart
{
    private const WIDTH = 660;

    /** Series colours in a fixed order; the first is the report accent. */
    private const SERIES = ['#1b3a5c', '#eb6834', '#1baf7a'];

    /** Columns of a period other than a full year (e.g. "2025 yil yanvar–sentabr"). */
    private const PARTIAL = '#8ea4bd';

    private const INK = '#1d1f24';

    private const MUTED = '#5a5f69';

    private const GRID = '#e1e4e9';

    private const AXIS = '#9aa3b0';

    /**
     * @param  list<list<list<string>>>  $tables  tables of the text so far: rows of cells, the header first
     */
    public static function render(string $spec, array $tables, ReportScript $script): ?string
    {
        [$names, $title] = array_pad(array_map('trim', explode('=', $spec, 2)), 2, '');
        $names = array_values(array_filter(array_map('trim', explode(';', $names)), fn ($n) => $n !== ''));
        if ($names === [] || count($names) > count(self::SERIES)) {
            return null;
        }
        $wanted = array_map([self::class, 'key'], $names);

        foreach (array_reverse($tables) as $table) {
            $head = $table[0];
            $rows = array_slice($table, 1);

            // Rows named → one series per row over the columns.
            $byLabel = [];
            foreach ($rows as $row) {
                $byLabel[self::key($row[0] ?? '')] = $row;
            }
            if (array_diff($wanted, array_keys($byLabel)) === []) {
                $first = 1 + (self::isUnitColumn($rows) ? 1 : 0);
                $unit = $first === 2 ? trim($byLabel[$wanted[0]][1] ?? '') : '';
                $series = array_map(fn ($key) => [
                    'name' => $byLabel[$key][0],
                    'values' => array_map([self::class, 'number'], array_pad(array_slice($byLabel[$key], $first, count($head) - $first), count($head) - $first, '')),
                ], $wanted);
                $title = $title ?: implode(', ', array_column($series, 'name')).($unit !== '' ? ', '.$unit : '');

                return self::columns($title, array_slice($head, $first), $series, $script);
            }

            // A column named → bars over the rows.
            $column = array_search($wanted[0], array_map([self::class, 'key'], $head), true);
            if (count($wanted) === 1 && $column !== false && $column > 0) {
                $items = [];
                foreach ($rows as $row) {
                    $value = self::number($row[$column] ?? '');
                    if ($value !== null) {
                        $items[] = ['label' => $row[0], 'value' => $value];
                    }
                }
                $items = self::withoutTotal($items);
                usort($items, fn ($a, $b) => $b['value'] <=> $a['value']);

                return $items === [] ? null : self::bars($title ?: $head[$column], $items, $script);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $categories
     * @param  list<array{name: string, values: list<float|null>}>  $series
     */
    private static function columns(string $title, array $categories, array $series, ReportScript $script): string
    {
        $count = count($categories);
        $multi = count($series) > 1;
        [$left, $right, $top, $bottom] = [64, 8, $multi ? 30 : 14, 44];
        $height = 230;
        $plotW = self::WIDTH - $left - $right;
        $plotH = $height - $top - $bottom;

        $values = array_filter(array_merge(...array_column($series, 'values')), fn ($v) => $v !== null);
        if ($values === []) {
            return '';
        }
        [$min, $max, $step] = self::scale(min(0, min($values)), max(0, max($values)));
        $y = fn (float $v) => $top + ($max - $v) / ($max - $min) * $plotH;

        $svg = [];
        for ($tick = $min; $tick <= $max + $step / 2; $tick += $step) {
            $ty = round($y($tick), 1);
            $svg[] = '<line x1="'.$left.'" x2="'.(self::WIDTH - $right).'" y1="'.$ty.'" y2="'.$ty.'" stroke="'.(abs($tick) < $step / 2 ? self::AXIS : self::GRID).'" stroke-width="1"/>';
            $svg[] = self::text($left - 6, $ty + 3.5, self::format($tick, $step), 'end', self::MUTED);
        }

        $slot = $plotW / max(1, $count);
        $bar = min(24, ($slot * 0.7 - (count($series) - 1) * 2) / count($series));
        $group = count($series) * $bar + (count($series) - 1) * 2;
        $years = array_filter($categories, fn ($c) => preg_match('/^\d{4}$/', trim($c)));
        foreach ($categories as $i => $category) {
            $x0 = $left + $slot * $i + ($slot - $group) / 2;
            foreach ($series as $s => $one) {
                $value = $one['values'][$i] ?? null;
                if ($value === null) {
                    continue;
                }
                // A single series marks a part-year column (next to full years) with a lighter tint.
                $partial = ! $multi && count($years) > 0 && ! preg_match('/^\d{4}$/', trim($category));
                $svg[] = self::column($x0 + $s * ($bar + 2), $y(0), $y($value), $bar, $partial ? self::PARTIAL : self::SERIES[$s]);
            }
            foreach (self::wrap($script->t($category), 14, 3) as $l => $line) {
                $svg[] = self::text($left + $slot * ($i + .5), $height - $bottom + 15 + $l * 11, $line, 'middle', self::MUTED);
            }
        }

        // The latest value of a single series at the cap of its column (the table above holds the rest).
        if (! $multi) {
            foreach (array_reverse(array_keys($categories)) as $i) {
                $value = $series[0]['values'][$i] ?? null;
                if ($value !== null) {
                    $svg[] = self::text($left + $slot * ($i + .5), $value >= 0 ? $y($value) - 5 : $y($value) + 13, self::format($value, 0.1), 'middle', self::INK, 700);
                    break;
                }
            }
        } else {
            $x = $left;
            foreach ($series as $s => $one) {
                $name = $script->t($one['name']);
                $svg[] = '<rect x="'.$x.'" y="6" width="10" height="10" rx="2" fill="'.self::SERIES[$s].'"/>';
                $svg[] = self::text($x + 14, 15, $name, 'start', self::INK);
                $x += 34 + mb_strlen($name) * 5;
            }
        }

        return self::figure($title, $height, $svg, $script);
    }

    /** @param  list<array{label: string, value: float}>  $items */
    private static function bars(string $title, array $items, ReportScript $script): string
    {
        [$label, $right, $top, $row, $bar] = [178, 70, 6, 19, 12];
        $height = $top * 2 + count($items) * $row;
        [, $max] = self::scale(0, max(array_column($items, 'value')));
        $plotW = self::WIDTH - $label - $right;

        $svg = ['<line x1="'.$label.'" x2="'.$label.'" y1="'.$top.'" y2="'.($height - $top).'" stroke="'.self::AXIS.'" stroke-width="1"/>'];
        foreach ($items as $i => $item) {
            $cy = $top + $row * ($i + .5);
            $w = max(1, $item['value'] / $max * $plotW);
            $svg[] = self::text($label - 8, $cy + 3.5, $script->t($item['label']), 'end', self::INK);
            $svg[] = '<path d="'.self::rounded($label, $cy - $bar / 2, $w, $bar).'" fill="'.self::SERIES[0].'"/>';
            $svg[] = self::text($label + $w + 6, $cy + 3.5, self::format($item['value'], 0.1), 'start', self::MUTED);
        }

        return self::figure($title, $height, $svg, $script);
    }

    /** @param  list<string>  $svg */
    private static function figure(string $title, int $height, array $svg, ReportScript $script): string
    {
        $title = e($script->t($title));

        return '<figure class="chart"><figcaption>'.$title.'</figcaption>'
            .'<svg viewBox="0 0 '.self::WIDTH.' '.$height.'" role="img" aria-label="'.$title.'" font-family="\'PT Sans\', Arial, sans-serif" font-size="10">'
            .implode('', $svg).'</svg></figure>';
    }

    /** A column from the baseline: square at the baseline, the data end rounded by 4. */
    private static function column(float $x, float $base, float $end, float $w, string $fill): string
    {
        $h = abs($base - $end);
        $r = min(4, $w / 2, $h);
        $x = round($x, 1);
        $w = round($w, 1);
        $d = $end <= $base
            ? "M{$x},{$base}V".round($end + $r, 1)."Q{$x},".round($end, 1).' '.round($x + $r, 1).','.round($end, 1).'H'.round($x + $w - $r, 1).'Q'.round($x + $w, 1).','.round($end, 1).' '.round($x + $w, 1).','.round($end + $r, 1)."V{$base}Z"
            : "M{$x},{$base}V".round($end - $r, 1)."Q{$x},".round($end, 1).' '.round($x + $r, 1).','.round($end, 1).'H'.round($x + $w - $r, 1).'Q'.round($x + $w, 1).','.round($end, 1).' '.round($x + $w, 1).','.round($end - $r, 1)."V{$base}Z";

        return '<path d="'.$d.'" fill="'.$fill.'"/>';
    }

    /** A bar from the axis on the left with its right end rounded by 4. */
    private static function rounded(float $x, float $y, float $w, float $h): string
    {
        $r = min(4, $w, $h / 2);
        [$x, $y, $w, $h] = [round($x, 1), round($y, 1), round($w, 1), $h];

        return "M{$x},{$y}H".round($x + $w - $r, 1).'Q'.round($x + $w, 1).",{$y} ".round($x + $w, 1).','.round($y + $r, 1)
            .'V'.round($y + $h - $r, 1).'Q'.round($x + $w, 1).','.round($y + $h, 1).' '.round($x + $w - $r, 1).','.round($y + $h, 1)
            ."H{$x}Z";
    }

    private static function text(float $x, float $y, string $text, string $anchor, string $fill, int $weight = 400): string
    {
        return '<text x="'.round($x, 1).'" y="'.round($y, 1).'" text-anchor="'.$anchor.'" fill="'.$fill.'"'
            .($weight !== 400 ? ' font-weight="'.$weight.'"' : '').'>'.e($text).'</text>';
    }

    /** @return array{float, float, float} the axis from, to and the step between ticks */
    private static function scale(float $min, float $max): array
    {
        $span = max($max - $min, 1e-9);
        $raw = $span / 4;
        $magnitude = 10 ** floor(log10($raw));
        $step = $magnitude * (collect([1, 2, 2.5, 5, 10])->first(fn ($m) => $m * $magnitude >= $raw) ?? 10);

        return [floor($min / $step) * $step, ceil($max / $step) * $step, $step];
    }

    private static function format(float $value, float $step): string
    {
        $decimals = $step < 1 && abs($value - round($value)) > 1e-9 ? 1 : 0;

        return str_replace('-', '−', number_format($value, $decimals, ',', ' '));
    }

    /** @return list<string> */
    private static function wrap(string $text, int $width, int $lines): array
    {
        $out = [''];
        foreach (preg_split('/\s+/u', trim($text)) as $word) {
            $last = count($out) - 1;
            if ($out[$last] !== '' && mb_strlen($out[$last].' '.$word) > $width) {
                $out[] = $word;
            } else {
                $out[$last] = trim($out[$last].' '.$word);
            }
        }

        return array_slice($out, 0, $lines);
    }

    /**
     * A row that adds up the others (the country before its regions) would dwarf them.
     *
     * @param  list<array{label: string, value: float}>  $items
     * @return list<array{label: string, value: float}>
     */
    private static function withoutTotal(array $items): array
    {
        $sum = array_sum(array_column($items, 'value'));

        return array_values(array_filter($items, fn ($item) => count($items) < 3 || $item['value'] < 0.9 * ($sum - $item['value'])));
    }

    /** @param  list<list<string>>  $rows */
    private static function isUnitColumn(array $rows): bool
    {
        return $rows !== [] && collect($rows)->every(fn ($row) => isset($row[1]) && self::number($row[1]) === null);
    }

    public static function number(string $cell): ?float
    {
        $cell = str_replace(['−', ' ', "\u{00A0}", "\u{202F}", '%'], ['-', '', '', '', ''], trim($cell));

        return preg_match('/^[-+]?\d+(,\d+)?$/', $cell) ? (float) str_replace(',', '.', $cell) : null;
    }

    private static function key(string $text): string
    {
        $text = str_replace(['‘', '’', 'ʻ', 'ʼ', '`', '´'], "'", mb_strtolower(trim($text)));

        return preg_replace('/\s+/u', ' ', $text);
    }
}
