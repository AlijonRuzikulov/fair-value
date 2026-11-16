<?php

use Database\Seeders\LandmarkSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The portal's own list of landmarks for each district (see LandmarkSeeder),
 * so that the landmark box lists them at once, without OpenStreetMap. A new
 * database gets them from the seeder, after the regions.
 */
return new class extends Migration
{
    public function up(): void
    {
        LandmarkSeeder::import();
    }

    public function down(): void
    {
        DB::table('landmarks')->where('kind', 'portal')->delete();
    }
};
