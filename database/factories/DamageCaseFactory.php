<?php

namespace Database\Factories;

use App\Enums\ValuationPurpose;
use App\Models\CarModel;
use App\Models\DamageCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DamageCase>
 */
class DamageCaseFactory extends Factory
{
    use ValuationDefaults;

    public function definition(): array
    {
        $car = CarModel::inRandomOrder()->first();
        $defaults = $this->valuationDefaults(0);

        return [
            'car_model_id' => $car?->id,
            'vehicle_name' => $car?->title ?? 'Chevrolet Cobalt',
            'plate_number' => UzbekData::plate(),
            'vin' => UzbekData::vin(),
            'year_made' => fake()->numberBetween(2015, 2025),
            'color' => fake()->randomElement(['Oq', 'Qora', 'Kumushrang']),
            'mileage' => fake()->numberBetween(10, 180) * 1000,
            'incident_date' => fake()->dateTimeBetween('-6 months', '-3 days'),
            'incident_place' => fake()->randomElement(['Toshkent sh., Amir Temur ko‘chasi', 'Toshkent sh., Kichik halqa yo‘li', 'Samarqand sh., Registon ko‘chasi']),
            'incident_description' => 'Yo‘l-transport hodisasi natijasida old qismi shikastlangan.',
            'wear_percent' => fake()->randomElement([0, 10, 15, 20, 25]),
            'labor_rate' => (float) $car?->labor_rate ?: 120000,
            'commodity_loss' => fake()->randomElement([0, 0, 1500000, 2800000]),
            ...array_merge($defaults, [
                'purpose' => ValuationPurpose::Damage->value,
                'approaches' => ['cost'],
                'value_cost' => null,
                'value_comparative' => null,
                'value_income' => null,
                'market_value' => null,
            ]),
        ];
    }
}
