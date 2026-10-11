<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Creates the directory of regions and the first administrator. Set
     * ADMIN_EMAIL and ADMIN_PASSWORD in .env before running the seeder; a
     * random password is generated and printed when none is set.
     */
    public function run(): void
    {
        $this->call(RegionSeeder::class);

        $email = config('fairvalue.admin.email');
        if (! User::where('email', $email)->exists()) {
            $password = config('fairvalue.admin.password') ?: Str::password(12, symbols: false);

            User::create([
                'name' => 'Administrator',
                'email' => $email,
                'role' => UserRole::Admin,
                'position' => 'Direktor',
                'password' => $password,
            ]);

            $this->command?->info("Administrator: {$email} / {$password}");
        }
    }
}
