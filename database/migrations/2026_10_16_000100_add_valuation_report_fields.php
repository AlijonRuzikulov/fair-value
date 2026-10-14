<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Everything the full valuation report needs beyond the object card:
        // description of the building, the task, reasons for the approaches.
        Schema::table('real_estates', function (Blueprint $table) {
            $table->json('report')->nullable()->after('features');
        });

        // Characteristics compared in the adjustment grid and a photo.
        Schema::table('analogs', function (Blueprint $table) {
            $table->string('wall_material')->nullable()->after('condition');
            $table->string('image_url', 1000)->nullable()->after('url');
            $table->string('photo_path')->nullable()->after('image_url');
        });

        // The appraiser's qualification certificate as printed in reports.
        Schema::table('users', function (Blueprint $table) {
            $table->date('certificate_date')->nullable()->after('certificate_number');
            $table->string('certificate_issuer')->nullable()->after('certificate_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['certificate_date', 'certificate_issuer']);
        });
        Schema::table('analogs', function (Blueprint $table) {
            $table->dropColumn(['wall_material', 'image_url', 'photo_path']);
        });
        Schema::table('real_estates', function (Blueprint $table) {
            $table->dropColumn('report');
        });
    }
};
