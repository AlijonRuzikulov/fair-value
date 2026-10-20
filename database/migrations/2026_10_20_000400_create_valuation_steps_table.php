<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The approval of a valuation: the appraiser submits the report, the
 * director approves it (or returns it), the customer signs the act.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valuation_steps', function (Blueprint $table) {
            $table->id();
            $table->morphs('stepable');
            $table->string('action', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valuation_steps');
    }
};
