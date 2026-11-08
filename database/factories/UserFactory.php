<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => UzbekData::lastName('m').' '.UzbekData::firstName('m'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => UzbekData::phone(),
            'role' => UserRole::Appraiser,
            'position' => 'Baholovchi',
            'certificate_number' => 'BB-'.fake()->numerify('####'),
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin, 'position' => 'Direktor']);
    }

    public function operator(): static
    {
        return $this->state(fn () => ['role' => UserRole::Operator, 'position' => 'Menejer', 'certificate_number' => null]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
