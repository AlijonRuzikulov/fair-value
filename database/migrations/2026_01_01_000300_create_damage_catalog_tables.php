<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Автолар рўйхати" — vehicle models that parts and norms refer to.
        Schema::create('car_models', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('model');
            $table->string('category', 40)->nullable();
            $table->string('body_type', 40)->nullable();
            $table->string('years', 40)->nullable();
            $table->decimal('labor_rate', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['brand', 'model']);
        });

        // "Авто эҳтиёт қисмлар"
        Schema::create('spare_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('part_number', 60)->nullable()->index();
            $table->string('unit', 20)->default('pcs');
            $table->decimal('price', 14, 2)->default(0);
            $table->date('price_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // "Кичик эҳтиёт қисмлар" — fasteners, clips, seals and other consumables.
        Schema::create('small_parts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit', 20)->default('pcs');
            $table->decimal('price', 14, 2)->default(0);
            $table->date('price_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // "Автотранспорт қисмларини таъмир муддати нормалари" — norm hours per operation.
        Schema::create('repair_norms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('part_name');
            $table->string('operation', 30)->default('replace');
            $table->decimal('hours', 8, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_norms');
        Schema::dropIfExists('small_parts');
        Schema::dropIfExists('spare_parts');
        Schema::dropIfExists('car_models');
    }
};
