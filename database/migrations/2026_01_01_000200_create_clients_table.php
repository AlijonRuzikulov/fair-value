<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();

            // Individuals (жисмоний шахс)
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('pinfl', 14)->nullable()->index();
            $table->string('passport_series', 4)->nullable();
            $table->string('passport_number', 12)->nullable();
            $table->string('passport_issued_by')->nullable();
            $table->date('passport_issued_at')->nullable();

            // Legal entities (юридик шахс)
            $table->string('company_name')->nullable();
            $table->string('inn', 9)->nullable()->index();
            $table->string('director')->nullable();
            $table->string('oked', 10)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_mfo', 5)->nullable();
            $table->string('bank_account', 20)->nullable();

            $table->string('phone', 30)->nullable();
            $table->string('phone2', 30)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
