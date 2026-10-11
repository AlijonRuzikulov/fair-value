<?php

namespace Database\Factories;

use App\Enums\OtherAssetCategory;
use App\Models\OtherAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OtherAsset>
 */
class OtherAssetFactory extends Factory
{
    use ValuationDefaults;

    public function definition(): array
    {
        [$name, $category] = fake()->randomElement([
            ['Korxona ustav fondidagi 30% ulush', OtherAssetCategory::Shares],
            ['"Navro‘z Savdo" MChJ biznesi', OtherAssetCategory::Business],
            ['Tovar belgisi (brend)', OtherAssetCategory::Intangible],
            ['Yer uchastkasidan foydalanish huquqi, 0,12 ga', OtherAssetCategory::LandRights],
            ['Qurilishi tugallanmagan ko‘p qavatli uy', OtherAssetCategory::Unfinished],
            ['Dasturiy ta’minot', OtherAssetCategory::Intangible],
        ]);

        return [
            'name' => $name,
            'category' => $category->value,
            'description' => null,
            ...$this->valuationDefaults(fake()->numberBetween(80, 4000) * 1_000_000),
        ];
    }
}
