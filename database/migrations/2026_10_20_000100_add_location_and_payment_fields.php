<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where real estate is on the map, its cadastral (book) value, built-up
 * area and landmark; and how much of the service fee has been paid.
 */
return new class extends Migration
{
    private const VALUATION_TABLES = ['real_estates', 'assets', 'vehicles', 'other_assets', 'damage_cases'];

    public function up(): void
    {
        Schema::table('real_estates', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('landmark', 150)->nullable();
            $table->decimal('cadastral_value', 18, 2)->nullable();
            $table->decimal('built_up_area', 12, 2)->nullable();
        });

        foreach (self::VALUATION_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->decimal('paid_amount', 14, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::VALUATION_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('paid_amount');
            });
        }

        Schema::table('real_estates', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'landmark', 'cadastral_value', 'built_up_area']);
        });
    }
};
