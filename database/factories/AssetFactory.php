<?php

namespace Database\Factories;

use App\Enums\AssetCategory;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    use ValuationDefaults;

    public function definition(): array
    {
        [$name, $category, $maker] = fake()->randomElement([
            ['CNC tokarlik stanogi', AssetCategory::Machinery, 'Haas'],
            ['Dizel generator 100 kVt', AssetCategory::Equipment, 'Cummins'],
            ['Ekskavator-yuklagich', AssetCategory::Special, 'JCB'],
            ['Traktor', AssetCategory::Agricultural, 'Belarus'],
            ['Ofis mebellari to‘plami', AssetCategory::Furniture, 'Yangi Avlod'],
            ['Serverlar va kompyuterlar', AssetCategory::Computers, 'Dell'],
            ['Tikuv uskunalari liniyasi', AssetCategory::Machinery, 'Juki'],
            ['Omborxona tovar-moddiy zaxiralari', AssetCategory::Goods, null],
        ]);

        return [
            'name' => $name,
            'category' => $category->value,
            'inventory_number' => fake()->numerify('INV-#####'),
            'manufacturer' => $maker,
            'model' => strtoupper(fake()->bothify('??-###')),
            'serial_number' => strtoupper(fake()->bothify('SN########')),
            'year_made' => fake()->numberBetween(2008, 2024),
            'quantity' => fake()->randomElement([1, 1, 1, 2, 5, 10]),
            'unit' => 'pcs',
            'condition' => fake()->randomElement(['Yaxshi', 'Qoniqarli', 'Ta’mir talab']),
            ...$this->valuationDefaults(fake()->numberBetween(15, 900) * 1_000_000),
        ];
    }
}
