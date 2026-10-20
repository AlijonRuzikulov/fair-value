<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engineering communications of an analogue (gas, water, heating…), compared
 * with those of the object for the adjustment for amenities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analogs', function (Blueprint $table) {
            $table->json('communications')->nullable()->after('wall_material');
        });
    }

    public function down(): void
    {
        Schema::table('analogs', function (Blueprint $table) {
            $table->dropColumn('communications');
        });
    }
};
