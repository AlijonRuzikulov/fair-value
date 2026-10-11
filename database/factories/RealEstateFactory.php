<?php

namespace Database\Factories;

use App\Enums\RealEstateFeature;
use App\Enums\RealEstateKind;
use App\Models\RealEstate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RealEstate>
 */
class RealEstateFactory extends Factory
{
    use ValuationDefaults;

    public function definition(): array
    {
        $residential = fake()->boolean();
        $area = $residential ? fake()->randomFloat(1, 38, 160) : fake()->randomFloat(1, 60, 1800);
        $pricePerM2 = $residential ? fake()->numberBetween(6, 14) * 1_000_000 : fake()->numberBetween(4, 11) * 1_000_000;
        $rooms = fake()->numberBetween(1, 5);

        return [
            'kind' => $residential ? RealEstateKind::Residential : RealEstateKind::NonResidential,
            'name' => $residential
                ? fake()->randomElement(["{$rooms} xonali kvartira", 'Xususiy turar-joy uyi', "{$rooms} xonali kvartira (yangi bino)", 'Hovli joy'])
                : fake()->randomElement(['Savdo do‘koni', 'Ofis binosi', 'Ombor binosi', 'Ishlab chiqarish sexi', 'Avtoservis binosi', 'Restoran binosi', 'Ma’muriy bino', 'Noturar bino (41-sonli savdo do‘koni)']),
            'cadastral_number' => UzbekData::cadastral(),
            'total_area' => $area,
            'useful_area' => round($area * 0.86, 1),
            'living_area' => $residential ? round($area * 0.62, 1) : null,
            'land_area' => fake()->boolean(40) ? fake()->randomFloat(1, 100, 2500) : null,
            'floors_total' => fake()->numberBetween(1, 12),
            'floor' => $residential ? fake()->numberBetween(1, 9) : null,
            'rooms' => $residential ? $rooms : null,
            'year_built' => fake()->numberBetween(1965, 2024),
            'wall_material' => fake()->randomElement(['G‘isht', 'Panel', 'Monolit', 'Paxsa']),
            'condition' => fake()->randomElement(['A’lo', 'Yaxshi', 'Qoniqarli']),
            'features' => fake()->randomElements(RealEstateFeature::values(), fake()->numberBetween(2, 7)),
            ...$this->valuationDefaults($area * $pricePerM2),
        ];
    }

    public function residential(): static
    {
        return $this->state(fn () => ['kind' => RealEstateKind::Residential]);
    }

    public function nonResidential(): static
    {
        return $this->state(fn () => ['kind' => RealEstateKind::NonResidential]);
    }
}
