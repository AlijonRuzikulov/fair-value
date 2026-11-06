<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields of the valuation portal's pages of movable property and vehicles:
 * the type of the property and the form of ownership; for a vehicle also
 * the appraiser's opinions on the identification and on the restricting
 * conditions. A vehicle is added there before its make and model are known.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('property_type', 150)->nullable()->after('name');
            $table->string('ownership_form', 150)->nullable()->after('property_type');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('brand')->nullable()->change();
            $table->string('model')->nullable()->change();
            $table->string('property_type', 150)->nullable()->after('condition');
            $table->string('ownership_form', 150)->nullable()->after('property_type');
            $table->text('identification')->nullable()->after('ownership_form');
            $table->text('restrictions')->nullable()->after('identification');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['property_type', 'ownership_form']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['property_type', 'ownership_form', 'identification', 'restrictions']);
        });
    }
};
