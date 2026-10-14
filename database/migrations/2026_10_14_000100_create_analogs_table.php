<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comparable sale offers ("analogues") of the comparative approach.
        Schema::create('analogs', function (Blueprint $table) {
            $table->id();
            $table->morphs('object');
            $table->string('status', 20)->default('ready'); // pending | ready | failed
            $table->string('source', 40)->nullable();
            $table->text('url')->nullable();
            $table->string('title')->nullable();
            $table->decimal('price', 18, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('usd_rate', 12, 2)->nullable();
            $table->decimal('price_uzs', 18, 2)->nullable();
            $table->decimal('area', 10, 2)->nullable();
            $table->unsignedSmallInteger('rooms')->nullable();
            $table->smallInteger('floor')->nullable();
            $table->unsignedSmallInteger('floors_total')->nullable();
            $table->string('location')->nullable();
            $table->string('condition')->nullable();
            $table->date('listed_at')->nullable();
            $table->json('adjustments')->nullable();
            $table->boolean('included')->default(true);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analogs');
    }
};
