<?php

namespace Database\Factories;

use App\Enums\ClientType;
use App\Models\Client;
use App\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(['m', 'm', 'f']);
        $district = District::inRandomOrder()->first();

        return [
            'type' => ClientType::Individual,
            'last_name' => UzbekData::lastName($gender),
            'first_name' => UzbekData::firstName($gender),
            'middle_name' => UzbekData::middleName($gender),
            'birth_date' => fake()->dateTimeBetween('-70 years', '-20 years'),
            'pinfl' => fake()->numerify(fake()->randomElement(['3', '4', '5', '6']).'#############'),
            'passport_series' => fake()->randomElement(['AA', 'AB', 'AC', 'AD', 'KA']),
            'passport_number' => fake()->numerify('#######'),
            'passport_issued_by' => 'IIV',
            'passport_issued_at' => fake()->dateTimeBetween('-10 years', '-1 month'),
            'phone' => UzbekData::phone(),
            'region_id' => $district?->region_id,
            'district_id' => $district?->id,
            'address' => UzbekData::address(),
        ];
    }

    public function legal(): static
    {
        return $this->state(fn () => [
            'type' => ClientType::Legal,
            'last_name' => null,
            'first_name' => null,
            'middle_name' => null,
            'birth_date' => null,
            'pinfl' => null,
            'passport_series' => null,
            'passport_number' => null,
            'passport_issued_by' => null,
            'passport_issued_at' => null,
            'company_name' => UzbekData::company(),
            'inn' => fake()->numerify(fake()->randomElement(['2', '3']).'########'),
            'director' => UzbekData::lastName('m').' '.UzbekData::firstName('m'),
            'oked' => fake()->numerify('#####'),
            'bank_name' => fake()->randomElement(['"Kapitalbank" ATB', '"Hamkorbank" ATB', '"Ipoteka-bank" ATIB', '"Asaka" AT banki', 'O‘zmilliybank']),
            'bank_mfo' => fake()->numerify('00###'),
            'bank_account' => '2020800'.fake()->numerify('#############'),
        ]);
    }
}
