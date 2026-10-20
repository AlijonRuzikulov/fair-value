<?php

namespace App\Reports;

/**
 * The act of inspection of real estate ("Ko'chmas mulkni ko'zdan kechirish
 * to'g'risida dalolatnoma"): who was present, the technical condition and
 * physical wear of each structural element, and the defects found.
 *
 * The physical wear of the building is the average of the elements' wear
 * weighted by their shares in its cost (the method of the rules for the
 * physical wear of buildings, VSN 53-86). The shares are those of a typical
 * brick residential building and are corrected for other buildings.
 */
final class InspectionAct
{
    /**
     * Element → [title in the report, typical share in the cost of the building %,
     * field of the report that describes it, name on the form].
     */
    public const ELEMENTS = [
        'foundation' => ['Poydevor', 4, 'foundation', 'Foundation'],
        'walls' => ["Devorlar va to'siqlar", 26, 'walls', 'Walls and partitions'],
        'roof_slab' => ['Oraliq va tom yopmalari', 14, 'roof_slab', 'Floor and roof slabs'],
        'roof_cover' => ['Tom qoplamasi', 5, 'roof_cover', 'Roof covering'],
        'floor_type' => ['Pollar', 10, 'floor_type', 'Floor coverings'],
        'windows_doors' => ['Deraza va eshiklar', 8, 'windows_doors', 'Windows and doors'],
        'finishing' => ['Pardozlash ishlari', 6, null, 'Finishing'],
        'utilities' => ['Ichki muhandislik tizimlari', 22, null, 'Internal utilities'],
        'other' => ['Boshqa elementlar (zinapoya, balkon va h.k.)', 5, null, 'Other elements (stairs, balconies…)'],
    ];

    /** Technical condition → [the wear it stands for, % from–to, name on the form]. */
    public const CONDITIONS = [
        'Yaxshi' => [0, 20, 'Good'],
        'Qoniqarli' => [21, 40, 'Satisfactory'],
        'Qoniqarsiz' => [41, 60, 'Unsatisfactory'],
        'Eskirgan' => [61, 80, 'Dilapidated'],
        'Yaroqsiz' => [81, 100, 'Unfit'],
    ];

    /** @return array{participants: ?string, defects: ?string, elements: array<string, array{condition: ?string, share: float, wear: ?float, description: ?string}>} */
    public static function defaults(): array
    {
        return [
            'participants' => null,
            'defects' => null,
            'elements' => array_map(fn ($element) => ['condition' => null, 'share' => (float) $element[1], 'wear' => null, 'description' => null], self::ELEMENTS),
        ];
    }

    /**
     * The act ready to print: one row per element with its description from
     * the report, the condition (from the wear when only the wear is given)
     * and the weighted physical wear of the building.
     *
     * @param  array<string, mixed>  $values  the report values
     * @return array{participants: ?string, defects: ?string, rows: list<array{title: string, description: ?string, condition: ?string, share: float, wear: ?float}>, wear: ?float, filled: bool}
     */
    public static function build(array $values): array
    {
        $act = array_replace_recursive(self::defaults(), (array) ($values['act'] ?? []));
        $rows = [];
        foreach (self::ELEMENTS as $key => [$title, , $field]) {
            $element = (array) ($act['elements'][$key] ?? []);
            $wear = isset($element['wear']) && $element['wear'] !== '' ? (float) $element['wear'] : null;
            $rows[] = [
                'title' => $title,
                'description' => $field !== null ? ($values[$field] ?? null) : ($element['description'] ?? null),
                'condition' => ($element['condition'] ?? null) ?: ($wear !== null ? self::conditionFor($wear) : null),
                'share' => (float) ($element['share'] ?? 0),
                'wear' => $wear,
            ];
        }

        return [
            'participants' => $act['participants'] ?: null,
            'defects' => $act['defects'] ?: null,
            'rows' => $rows,
            'wear' => self::weightedWear($rows),
            'filled' => collect($rows)->contains(fn ($row) => $row['wear'] !== null || $row['condition'] !== null),
        ];
    }

    /**
     * The wear of the building: Σ share × wear / Σ share over the elements whose wear is known.
     *
     * @param  list<array{share: float, wear: ?float}>  $rows
     */
    public static function weightedWear(array $rows): ?float
    {
        $known = array_filter($rows, fn ($row) => $row['wear'] !== null && $row['share'] > 0);
        $shares = array_sum(array_column($known, 'share'));

        return $shares > 0 ? round(array_sum(array_map(fn ($row) => $row['share'] * $row['wear'], $known)) / $shares, 1) : null;
    }

    public static function conditionFor(float $wear): string
    {
        foreach (self::CONDITIONS as $condition => [, $to]) {
            if ($wear <= $to) {
                return $condition;
            }
        }

        return array_key_last(self::CONDITIONS);
    }
}
