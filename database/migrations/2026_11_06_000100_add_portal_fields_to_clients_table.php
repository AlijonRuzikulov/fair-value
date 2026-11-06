<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields of a legal entity on the form of the valuation portal: the kind
 * of the organisation, its form of management, the position of its head
 * and whether it pays the single tax.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('organization', 100)->nullable()->after('company_name');
            $table->string('management', 100)->nullable()->after('organization');
            $table->string('director_position')->nullable()->after('director');
            $table->boolean('single_tax')->default(false)->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['organization', 'management', 'director_position', 'single_tax']);
        });
    }
};
