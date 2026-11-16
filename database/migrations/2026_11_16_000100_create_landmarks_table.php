<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landmarks (mo'ljal) of each district: mahallas, massifs, villages,
 * markets, metro stations and the like, taken from OpenStreetMap once and
 * kept, so that the landmark of an object is chosen from a list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('kind', 30);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->unique(['district_id', 'name']);
        });

        Schema::table('districts', function (Blueprint $table) {
            $table->timestamp('landmarks_fetched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->dropColumn('landmarks_fetched_at');
        });
        Schema::dropIfExists('landmarks');
    }
};
