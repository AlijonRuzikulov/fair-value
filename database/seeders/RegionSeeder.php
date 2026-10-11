<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Region;
use App\Support\Transliterator;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        if (Region::exists()) {
            return;
        }

        foreach (require database_path('data/regions.php') as $r => [$nameUz, $nameRu, $districts]) {
            $region = Region::create([
                'name_uz' => $nameUz,
                'name_oz' => Transliterator::toCyrillic($nameUz),
                'name_ru' => $nameRu,
                'sort' => $r + 1,
            ]);

            foreach ($districts as $d => [$uz, $ru]) {
                $isCity = str_ends_with($uz, ' shahri');
                District::create([
                    'region_id' => $region->id,
                    'name_uz' => $isCity ? $uz : $uz.' tumani',
                    'name_oz' => Transliterator::toCyrillic($isCity ? $uz : $uz.' tumani'),
                    'name_ru' => $isCity ? $ru : $ru.' район',
                    // Cities first, then districts in alphabetical order.
                    'sort' => $isCity ? $d : 100,
                ]);
            }
        }
    }
}
