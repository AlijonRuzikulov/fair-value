<?php

namespace App\Support;

/**
 * Cost approach for real estate: the cost of replacing each building with
 * the developer's profit, less physical wear (by age and economic life, or
 * as assessed), functional and external obsolescence; plus the land.
 */
final class CostApproach
{
    /** Economic life in years of capital buildings and of light structures. */
    public const LIFE = 80;

    public const LIGHT_LIFE = 40;

    /** Physical wear by age is not taken above this, in percent. */
    private const MAX_WEAR = 80.0;

    /**
     * @param  array{buildings?: list<array<string, mixed>>, profit?: float|int|string|null, land?: float|int|string|null}  $input
     * @return array{rows: list<array<string, mixed>>, buildings: float, land: float, value: ?float, profit: float}
     */
    public static function calculate(array $input, int $year): array
    {
        $profit = (float) ($input['profit'] ?? 0);
        $rows = [];
        foreach ((array) ($input['buildings'] ?? []) as $building) {
            $quantity = (float) ($building['quantity'] ?? 0);
            $rate = (float) ($building['rate'] ?? 0);
            if ($quantity <= 0 || $rate <= 0) {
                continue;
            }
            $light = (bool) ($building['light'] ?? false);
            $life = (int) ($building['life'] ?? 0) ?: ($light ? self::LIGHT_LIFE : self::LIFE);
            $built = (int) ($building['year_built'] ?? 0);
            $age = $built > 0 ? max(0, $year - $built) : null;

            $replacement = $quantity * $rate * (1 + $profit / 100);
            $physical = isset($building['wear']) && $building['wear'] !== '' && $building['wear'] !== null
                ? (float) $building['wear']
                : ($age !== null ? min(self::MAX_WEAR, $age / $life * 100) : 0.0);
            $functional = (float) ($building['functional'] ?? 0);
            $external = (float) ($building['external'] ?? 0);
            $total = (1 - (1 - $physical / 100) * (1 - $functional / 100) * (1 - $external / 100)) * 100;

            $rows[] = [
                'name' => (string) ($building['name'] ?? ''),
                'unit' => ($building['unit'] ?? 'm2') === 'm3' ? 'm3' : 'm2',
                'quantity' => $quantity,
                'rate' => $rate,
                'light' => $light,
                'year_built' => $built ?: null,
                'age' => $age,
                'life' => $life,
                'replacement' => round($replacement, 2),
                'physical' => round($physical, 2),
                'functional' => $functional,
                'external' => $external,
                'depreciation' => round($total, 2),
                'value' => round($replacement * (1 - $total / 100), 2),
            ];
        }

        $buildings = array_sum(array_column($rows, 'value'));
        $land = (float) ($input['land'] ?? 0);

        return [
            'rows' => $rows,
            'buildings' => round($buildings, 2),
            'land' => $land,
            'profit' => $profit,
            'value' => $rows === [] && $land <= 0 ? null : round($buildings + $land, -3),
        ];
    }
}
