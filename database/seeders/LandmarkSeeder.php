<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The landmarks (mo'ljal) of each district as the appraisers' portal lists
 * them: database/data/landmarks.json, region → district → names, keyed by
 * the Latin names of database/data/regions.php. Kept with the kind "portal";
 * a landmark of the same name taken before from OpenStreetMap becomes a
 * portal one and keeps its place on the map.
 */
class LandmarkSeeder extends Seeder
{
    public function run(): void
    {
        if (! DB::table('landmarks')->where('kind', 'portal')->exists()) {
            self::import();
        }
    }

    /** Adds the landmarks of the list to the districts; returns how many districts got a list. */
    public static function import(): int
    {
        $path = database_path('data/landmarks.json');
        if (! is_file($path) || ! DB::table('districts')->exists()) {
            return 0;
        }

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $districts = DB::table('districts')->join('regions', 'regions.id', '=', 'districts.region_id')
            ->get(['districts.id', 'districts.name_uz as district', 'regions.name_uz as region']);
        $now = now();
        $done = 0;

        foreach ($districts as $district) {
            $names = $data[$district->region][$district->district] ?? [];
            if (! $names) {
                continue;
            }
            $rows = array_map(fn (string $name) => [
                'district_id' => $district->id,
                'name' => $name,
                'kind' => 'portal',
                'created_at' => $now,
                'updated_at' => $now,
            ], $names);
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('landmarks')->upsert($chunk, ['district_id', 'name'], ['kind', 'updated_at']);
            }
            $done++;
        }

        return $done;
    }
}
