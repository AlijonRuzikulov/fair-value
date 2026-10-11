<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => UzbekData::firstName('m').' '.UzbekData::lastName('m'),
            'phone' => UzbekData::phone(),
            'service' => fake()->randomElement(['real-estate', 'vehicles', 'damage', 'business', 'equipment']),
            'message' => fake()->randomElement(['Kvartirani bank krediti uchun baholatmoqchiman.', 'Avtomobil YTH dan keyin zarar baholash kerak.', 'Narxi va muddati qancha?', null]),
            'status' => fake()->randomElement(LeadStatus::cases()),
            'locale' => 'uz',
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
