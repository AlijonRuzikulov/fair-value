<?php

namespace Database\Seeders;

use App\Enums\DamageItemType;
use App\Enums\PersonRole;
use App\Enums\RepairOperation;
use App\Models\Activity;
use App\Models\Asset;
use App\Models\CarModel;
use App\Models\Client;
use App\Models\CurrencyRate;
use App\Models\DamageCase;
use App\Models\Lead;
use App\Models\OtherAsset;
use App\Models\RealEstate;
use App\Models\RepairNorm;
use App\Models\SmallPart;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Sample data for trying the system out (composer demo):
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * The real system starts empty (DatabaseSeeder: regions and the first
 * administrator only). Do not run on the production database. Catalogue prices are examples
 * only and must be replaced with real price lists.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        // Sign in as the administrator from DatabaseSeeder (ADMIN_EMAIL / ADMIN_PASSWORD).
        if (! User::where('email', 'dilshod@fairvalue.uz')->exists()) {
            User::factory()->create(['name' => 'Dilshod Karimov', 'email' => 'dilshod@fairvalue.uz']);
            User::factory()->create(['name' => 'Malika Yusupova', 'email' => 'malika@fairvalue.uz']);
            User::factory()->operator()->create(['name' => 'Nilufar Rahimova', 'email' => 'nilufar@fairvalue.uz']);
        }

        $this->rates();
        $this->catalogs();

        $individuals = Client::factory()->count(30)->create();
        $companies = Client::factory()->legal()->count(12)->create();
        $clients = $individuals->concat($companies);

        $objects = collect()
            ->concat(RealEstate::factory()->nonResidential()->count(24)->create())
            ->concat(RealEstate::factory()->residential()->count(18)->create())
            ->concat(Asset::factory()->count(10)->create())
            ->concat(Vehicle::factory()->count(16)->create())
            ->concat(OtherAsset::factory()->count(6)->create())
            ->concat(DamageCase::factory()->count(8)->create());

        $objects->each(function (Model $object) use ($clients) {
            $owner = $clients->random();
            $object->clients()->attach($owner->id, ['role' => PersonRole::Owner->value]);
            if (fake()->boolean(35)) {
                $object->clients()->attach($clients->where('id', '!=', $owner->id)->random()->id, ['role' => PersonRole::Customer->value]);
            }
        });

        DamageCase::all()->each(fn (DamageCase $case) => $this->damageLines($case));

        Lead::factory()->count(12)->create();

        // The log should start with real work, not with generated records.
        Activity::query()->delete();
    }

    private function rates(): void
    {
        // Example values; real rates are downloaded from cbu.uz by "php artisan currency:fetch".
        $base = ['USD' => 11814.05, 'EUR' => 13482.19, 'RUB' => 139.93, 'GBP' => 15620.40, 'CNY' => 1638.72, 'KZT' => 22.41];
        foreach (range(6, 0) as $daysAgo) {
            foreach ($base as $code => $rate) {
                $value = round($rate * (1 + (mt_rand(-40, 40) / 10000) * $daysAgo / 3), 2);
                CurrencyRate::updateOrCreate(
                    ['code' => $code, 'rate_date' => now()->subDays($daysAgo)->toDateString()],
                    ['rate' => $daysAgo === 0 ? $rate : $value, 'diff' => $daysAgo === 0 ? round($rate * 0.0012, 2) : 0, 'nominal' => 1]
                );
            }
        }
    }

    private function catalogs(): void
    {
        if (CarModel::exists()) {
            return;
        }

        $models = [
            ['Chevrolet', 'Cobalt', 'B', 'Sedan', '2013–'], ['Chevrolet', 'Nexia 3', 'B', 'Sedan', '2016–2020'],
            ['Chevrolet', 'Gentra', 'C', 'Sedan', '2013–'], ['Chevrolet', 'Lacetti', 'C', 'Sedan', '2008–2013'],
            ['Chevrolet', 'Spark', 'A', 'Xetchbek', '2010–2022'], ['Chevrolet', 'Malibu 2', 'D', 'Sedan', '2018–'],
            ['Chevrolet', 'Tracker 2', 'J', 'Krossover', '2021–'], ['Chevrolet', 'Onix', 'B', 'Sedan', '2023–'],
            ['Chevrolet', 'Damas', 'M', 'Miniven', '1996–'], ['Chevrolet', 'Labo', 'N', 'Yuk', '1996–'],
            ['Daewoo', 'Matiz', 'A', 'Xetchbek', '2001–2016'], ['Daewoo', 'Nexia', 'B', 'Sedan', '1996–2016'],
            ['BYD', 'Song Plus', 'J', 'Krossover', '2023–'], ['Kia', 'K5', 'D', 'Sedan', '2023–'],
        ];
        foreach ($models as [$brand, $model, $category, $body, $years]) {
            CarModel::create(['brand' => $brand, 'model' => $model, 'category' => $category, 'body_type' => $body, 'years' => $years, 'labor_rate' => in_array($category, ['D', 'J']) ? 150000 : 120000]);
        }

        // Example price list (UZS) for three popular models.
        $parts = [
            'Old bamper' => [1850000, 'Bumper front'], 'Orqa bamper' => [1650000, 'Bumper rear'], 'Kapot' => [2400000, 'Hood'],
            'Old chap qanot' => [950000, 'Fender'], 'Old o‘ng qanot' => [950000, 'Fender'], 'Old chap eshik' => [2900000, 'Door'],
            'Old fara (chap)' => [1350000, 'Headlamp'], 'Old fara (o‘ng)' => [1350000, 'Headlamp'], 'Orqa chiroq' => [520000, 'Lamp'],
            'Old oyna' => [1450000, 'Windshield'], 'Radiator' => [1100000, 'Radiator'], 'Radiator panjarasi' => [380000, 'Grille'],
            'Yon oyna (chap)' => [450000, 'Mirror'], 'Old bamper kuchaytirgichi' => [690000, 'Reinforcement'],
        ];
        foreach (CarModel::whereIn('model', ['Cobalt', 'Gentra', 'Nexia 3'])->get() as $car) {
            $k = ['Cobalt' => 1.0, 'Gentra' => 1.08, 'Nexia 3' => 0.94][$car->model];
            foreach ($parts as $name => [$price]) {
                SparePart::create([
                    'car_model_id' => $car->id,
                    'name' => $name,
                    'part_number' => (string) fake()->numerify('9########'),
                    'unit' => 'pcs',
                    'price' => round($price * $k, -3),
                    'price_date' => now()->startOfMonth(),
                ]);
            }
        }

        foreach ([
            ['Bamper klipsasi (pistonlar)', 'pcs', 3000], ['Bolt M6', 'pcs', 2500], ['O‘z-o‘zidan buraladigan vint', 'pcs', 1500],
            ['Germetik', 'pcs', 45000], ['Emblema', 'pcs', 85000], ['Eshik molding', 'pcs', 120000],
            ['Antifriz', 'l', 28000], ['Fara kronshteyni', 'pcs', 65000],
        ] as [$name, $unit, $price]) {
            SmallPart::create(['name' => $name, 'unit' => $unit, 'price' => $price, 'price_date' => now()->startOfMonth()]);
        }

        // Universal norms (for all models) in standard hours.
        foreach ([
            ['Old bamper', RepairOperation::Replace, 1.2], ['Old bamper', RepairOperation::Paint, 2.5], ['Old bamper', RepairOperation::Repair, 1.5],
            ['Orqa bamper', RepairOperation::Replace, 1.1], ['Orqa bamper', RepairOperation::Paint, 2.5],
            ['Kapot', RepairOperation::Replace, 0.8], ['Kapot', RepairOperation::Paint, 3.0], ['Kapot', RepairOperation::Repair, 2.0],
            ['Old qanot', RepairOperation::Replace, 1.6], ['Old qanot', RepairOperation::Paint, 2.2],
            ['Old eshik', RepairOperation::Replace, 2.0], ['Old eshik', RepairOperation::Paint, 3.0], ['Old eshik', RepairOperation::Repair, 2.5],
            ['Fara', RepairOperation::Replace, 0.5], ['Old oyna', RepairOperation::Replace, 1.5], ['Radiator', RepairOperation::Replace, 1.8],
            ['G‘ildirak burchaklarini sozlash', RepairOperation::Diagnostics, 1.0],
        ] as [$part, $operation, $hours]) {
            RepairNorm::create(['part_name' => $part, 'operation' => $operation, 'hours' => $hours]);
        }
    }

    private function damageLines(DamageCase $case): void
    {
        $parts = SparePart::inRandomOrder()->limit(fake()->numberBetween(2, 4))->get();
        $lines = [];
        foreach ($parts as $part) {
            $lines[] = ['type' => DamageItemType::Part->value, 'name' => $part->name, 'quantity' => 1, 'unit_price' => $part->price];
            $lines[] = ['type' => DamageItemType::Labor->value, 'name' => $part->name, 'operation' => RepairOperation::Replace->value, 'quantity' => 1, 'hours' => fake()->randomElement([0.8, 1.2, 1.6, 2.0]), 'unit_price' => $case->labor_rate];
        }
        $lines[] = ['type' => DamageItemType::SmallPart->value, 'name' => 'Bamper klipsasi (pistonlar)', 'quantity' => 12, 'unit_price' => 3000];
        $lines[] = ['type' => DamageItemType::Paint->value, 'name' => 'Bo‘yoq materiallari', 'quantity' => 1, 'unit_price' => fake()->randomElement([850000, 1200000, 1650000])];
        $case->syncItems($lines);
    }
}
