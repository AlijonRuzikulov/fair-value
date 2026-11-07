<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The people the system is given to (employees of banks, valuation
 * companies): whose they are, whether they have Premium and until when
 * they may work in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization', 150)->nullable()->after('position');
            $table->boolean('premium')->default(false)->after('is_active');
            $table->date('access_until')->nullable()->after('premium');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['organization', 'premium', 'access_until']);
        });
    }
};
