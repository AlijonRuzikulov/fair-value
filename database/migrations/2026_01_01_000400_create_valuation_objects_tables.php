<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Янги кўчмас мулк" — residential and non-residential real estate.
        Schema::create('real_estates', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->index();
            $table->string('name');
            $table->string('cadastral_number', 60)->nullable()->index();
            $table->decimal('total_area', 12, 2)->nullable();
            $table->decimal('useful_area', 12, 2)->nullable();
            $table->decimal('living_area', 12, 2)->nullable();
            $table->decimal('land_area', 12, 2)->nullable();
            $table->unsignedSmallInteger('floors_total')->nullable();
            $table->unsignedSmallInteger('floor')->nullable();
            $table->unsignedSmallInteger('rooms')->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->string('wall_material')->nullable();
            $table->string('condition')->nullable();
            $table->json('features')->nullable();
            $this->valuationColumns($table);
        });

        // "Мол-мулклар" — equipment, machinery and other movable property.
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 40)->nullable()->index();
            $table->string('inventory_number', 60)->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number', 80)->nullable();
            $table->unsignedSmallInteger('year_made')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 20)->default('pcs');
            $table->string('condition')->nullable();
            $this->valuationColumns($table);
        });

        // "Автотранспорт"
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('model');
            $table->unsignedSmallInteger('year_made')->nullable();
            $table->string('body_type', 40)->nullable();
            $table->string('color', 40)->nullable();
            $table->string('vin', 40)->nullable()->index();
            $table->string('engine_number', 40)->nullable();
            $table->string('chassis_number', 40)->nullable();
            $table->string('plate_number', 20)->nullable()->index();
            $table->string('tech_passport', 30)->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->decimal('engine_volume', 6, 1)->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->string('transmission', 20)->nullable();
            $table->string('condition')->nullable();
            $this->valuationColumns($table);
        });

        // "Бошқа мол-мулк" — business, shares, intangibles and the rest.
        Schema::create('other_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 40)->nullable()->index();
            $table->text('description')->nullable();
            $this->valuationColumns($table);
        });

        // "Етказилган зарар" — damage to a vehicle after an accident.
        Schema::create('damage_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vehicle_name');
            $table->string('plate_number', 20)->nullable()->index();
            $table->string('vin', 40)->nullable();
            $table->unsignedSmallInteger('year_made')->nullable();
            $table->string('color', 40)->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->date('incident_date')->nullable();
            $table->string('incident_place')->nullable();
            $table->text('incident_description')->nullable();
            $table->decimal('wear_percent', 5, 2)->default(0);
            $table->decimal('labor_rate', 12, 2)->default(0);
            $table->decimal('commodity_loss', 16, 2)->default(0);
            $table->decimal('parts_total', 16, 2)->default(0);
            $table->decimal('wear_total', 16, 2)->default(0);
            $table->decimal('small_parts_total', 16, 2)->default(0);
            $table->decimal('labor_total', 16, 2)->default(0);
            $table->decimal('paint_total', 16, 2)->default(0);
            $table->decimal('other_total', 16, 2)->default(0);
            $this->valuationColumns($table);
        });

        Schema::create('damage_case_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_case_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name');
            $table->string('operation', 30)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('hours', 8, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('amount', 16, 2)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // "Шахслар" — clients linked to any valuation object with a role.
        Schema::create('client_object', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->morphs('object');
            $table->string('role', 20)->default('owner');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_object');
        Schema::dropIfExists('damage_case_items');
        Schema::dropIfExists('damage_cases');
        Schema::dropIfExists('other_assets');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('real_estates');
    }

    /**
     * Columns shared by every valuation object: location, approaches,
     * contract, report and workflow status.
     */
    private function valuationColumns(Blueprint $table): void
    {
        $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
        $table->string('address')->nullable();
        $table->string('purpose', 40)->nullable();
        $table->json('approaches')->nullable();
        $table->decimal('value_cost', 18, 2)->nullable();
        $table->decimal('value_comparative', 18, 2)->nullable();
        $table->decimal('value_income', 18, 2)->nullable();
        $table->decimal('market_value', 18, 2)->nullable();
        $table->date('valuation_date')->nullable();
        $table->date('inspection_date')->nullable();
        $table->string('contract_number', 60)->nullable();
        $table->date('contract_date')->nullable();
        $table->decimal('service_fee', 14, 2)->nullable();
        $table->string('report_number', 60)->nullable()->index();
        $table->string('verify_code', 20)->unique();
        $table->string('status', 20)->default('new')->index();
        $table->timestamp('completed_at')->nullable();
        $table->foreignId('appraiser_id')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->text('notes')->nullable();
        $table->timestamps();
        $table->softDeletes();
    }
};
