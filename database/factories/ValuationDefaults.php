<?php

namespace Database\Factories;

use App\Enums\ValuationApproach;
use App\Enums\ValuationPurpose;
use App\Enums\ValuationStatus;
use App\Models\District;
use App\Models\User;

/**
 * Common attributes for valuation object factories.
 */
trait ValuationDefaults
{
    protected function valuationDefaults(float $value): array
    {
        $district = District::inRandomOrder()->first();
        $status = fake()->randomElement([ValuationStatus::New, ValuationStatus::InProgress, ValuationStatus::InProgress, ValuationStatus::Completed, ValuationStatus::Completed, ValuationStatus::Completed]);
        $approaches = fake()->randomElements(ValuationApproach::values(), fake()->numberBetween(1, 3));
        $completed = $status === ValuationStatus::Completed;
        $date = fake()->dateTimeBetween('-11 months', 'now');

        $values = [];
        foreach ($approaches as $approach) {
            $values['value_'.$approach] = round($value * fake()->randomFloat(2, 0.9, 1.1), -3);
        }

        return [
            'region_id' => $district?->region_id,
            'district_id' => $district?->id,
            'address' => UzbekData::address(),
            'purpose' => fake()->randomElement([ValuationPurpose::Collateral, ValuationPurpose::Collateral, ValuationPurpose::Sale, ValuationPurpose::Notary, ValuationPurpose::Court])->value,
            'approaches' => $approaches,
            ...$values,
            'market_value' => $completed || fake()->boolean() ? round($value, -3) : null,
            'valuation_date' => $date,
            'inspection_date' => $date,
            'contract_number' => fake()->numerify('###/'.date('y')),
            'contract_date' => $date,
            'service_fee' => fake()->randomElement([500000, 800000, 1200000, 1500000, 2500000]),
            'status' => $status,
            'appraiser_id' => User::appraisers()->reorder()->inRandomOrder()->value('id'),
            'created_at' => $date,
            'completed_at' => $completed ? $date : null,
        ];
    }
}
