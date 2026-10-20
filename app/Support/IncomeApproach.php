<?php

namespace App\Support;

/**
 * Income approach by direct capitalisation: the yearly rent of the area,
 * less vacancy and collection losses and operating expenses, divided by
 * the capitalisation rate.
 */
final class IncomeApproach
{
    /**
     * @param  array{area?: mixed, rent?: mixed, vacancy?: mixed, opex?: mixed, cap?: mixed}  $input
     * @return array{area: float, rent: float, vacancy: float, opex: float, cap: float, pgi: float, egi: float, noi: float, value: ?float}
     */
    public static function calculate(array $input): array
    {
        $area = (float) ($input['area'] ?? 0);
        $rent = (float) ($input['rent'] ?? 0);
        $vacancy = (float) ($input['vacancy'] ?? 0);
        $opex = (float) ($input['opex'] ?? 0);
        $cap = (float) ($input['cap'] ?? 0);

        $pgi = $area * $rent * 12;
        $egi = $pgi * (1 - $vacancy / 100);
        $noi = $egi * (1 - $opex / 100);

        return [
            'area' => $area, 'rent' => $rent, 'vacancy' => $vacancy, 'opex' => $opex, 'cap' => $cap,
            'pgi' => round($pgi, 2), 'egi' => round($egi, 2), 'noi' => round($noi, 2),
            'value' => $noi > 0 && $cap > 0 ? round($noi / ($cap / 100), -3) : null,
        ];
    }
}
