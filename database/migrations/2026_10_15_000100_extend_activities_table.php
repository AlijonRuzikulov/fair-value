<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit log: who (with the e-mail at that moment), from where (IP,
        // browser, address) and a hash chain that shows later tampering.
        Schema::table('activities', function (Blueprint $table) {
            $table->string('category', 20)->default('data')->after('event')->index();
            $table->string('user_email')->nullable()->after('user_id');
            $table->string('user_agent', 255)->nullable()->after('ip_address');
            $table->string('url', 500)->nullable()->after('user_agent');
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64)->nullable();
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['event']);
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'user_email', 'user_agent', 'url', 'prev_hash', 'hash']);
        });
    }
};
