<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    use ValuationDefaults;

    public function definition(): array
    {
        [$brand, $model, $price] = fake()->randomElement([
            ['Chevrolet', 'Cobalt', 150], ['Chevrolet', 'Nexia 3', 135], ['Chevrolet', 'Gentra', 165],
            ['Chevrolet', 'Malibu 2', 380], ['Chevrolet', 'Tracker 2', 260], ['Chevrolet', 'Damas', 95],
            ['Chevrolet', 'Onix', 175], ['Chevrolet', 'Spark', 110], ['BYD', 'Song Plus', 420], ['Kia', 'K5', 450],
        ]);
        $year = fake()->numberBetween(2012, 2025);

        return [
            'brand' => $brand,
            'model' => $model,
            'year_made' => $year,
            'body_type' => fake()->randomElement(['Sedan', 'Xetchbek', 'Krossover', 'Miniven']),
            'color' => fake()->randomElement(['Oq', 'Qora', 'Kumushrang', 'Kulrang', 'Ko‘k']),
            'vin' => UzbekData::vin(),
            'engine_number' => strtoupper(fake()->bothify('F15S3######')),
            'plate_number' => UzbekData::plate(),
            'tech_passport' => 'AAF '.fake()->numerify('#######'),
            'mileage' => fake()->numberBetween(5, 260) * 1000,
            'engine_volume' => fake()->randomElement([1.2, 1.5, 1.5, 2.0]),
            'fuel_type' => fake()->randomElement([FuelType::Petrol, FuelType::Methane, FuelType::Propane])->value,
            'transmission' => fake()->randomElement(['manual', 'automatic']),
            'condition' => fake()->randomElement(['Yaxshi', 'Qoniqarli']),
            ...$this->valuationDefaults($price * 1_000_000 * (1 - (2025 - $year) * 0.045)),
        ];
    }
}
