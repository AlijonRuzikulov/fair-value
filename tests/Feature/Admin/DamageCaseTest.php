<?php

namespace Tests\Feature\Admin;

use App\Models\CarModel;
use App\Models\DamageCase;
use App\Models\RepairNorm;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DamageCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_damage_is_calculated_from_lines(): void
    {
        $user = User::factory()->create();
        $car = CarModel::create(['brand' => 'Chevrolet', 'model' => 'Cobalt', 'labor_rate' => 120000]);

        $this->actingAs($user)->post('/admin/damage-cases', [
            'car_model_id' => $car->id,
            'vehicle_name' => 'Chevrolet Cobalt',
            'plate_number' => '01 A 123 BC',
            'wear_percent' => '20',
            'labor_rate' => '120 000',
            'commodity_loss' => '1 000 000',
            'status' => 'completed',
            'items' => [
                ['type' => 'part', 'name' => 'Old bamper', 'quantity' => '1', 'unit_price' => '2 000 000'],
                ['type' => 'part', 'name' => 'Fara', 'quantity' => '2', 'unit_price' => '500 000'],
                ['type' => 'labor', 'name' => 'Old bamper', 'operation' => 'replace', 'quantity' => '1', 'hours' => '1,5', 'unit_price' => '120 000'],
                ['type' => 'small_part', 'name' => 'Klips', 'quantity' => '10', 'unit_price' => '3 000'],
                ['type' => 'paint', 'name' => 'Bo‘yoq', 'quantity' => '1', 'unit_price' => '800 000'],
                ['type' => 'other', 'name' => 'Evakuator', 'quantity' => '1', 'unit_price' => '300 000'],
            ],
        ])->assertRedirect();

        $case = DamageCase::sole();
        // Parts 3 000 000 − 20 % wear (600 000) + labour 180 000 + small 30 000 + paint 800 000 + other 300 000 + loss 1 000 000
        $this->assertSame('3000000.00', $case->parts_total);
        $this->assertSame('600000.00', $case->wear_total);
        $this->assertSame('180000.00', $case->labor_total);
        $this->assertSame('4710000.00', $case->market_value);
        $this->assertCount(6, $case->items);
        $this->assertNotNull($case->report_number);

        $this->get('/admin/damage-cases/'.$case->id)->assertOk()->assertSee("4\u{00A0}710\u{00A0}000");
        $this->get('/admin/damage-cases/'.$case->id.'/print')->assertOk()->assertSee($case->verify_code);
    }

    public function test_catalogue_lookup_returns_parts_of_the_model_and_universal_norms(): void
    {
        $user = User::factory()->create();
        $cobalt = CarModel::create(['brand' => 'Chevrolet', 'model' => 'Cobalt']);
        $nexia = CarModel::create(['brand' => 'Chevrolet', 'model' => 'Nexia 3']);
        SparePart::create(['car_model_id' => $cobalt->id, 'name' => 'Kapot Cobalt', 'unit' => 'pcs', 'price' => 2400000]);
        SparePart::create(['car_model_id' => $nexia->id, 'name' => 'Kapot Nexia', 'unit' => 'pcs', 'price' => 2200000]);
        RepairNorm::create(['part_name' => 'Kapot', 'operation' => 'paint', 'hours' => 3]);

        $parts = $this->actingAs($user)->getJson('/admin/spare-parts/lookup?q=Kapot&car_model_id='.$cobalt->id)->assertOk()->json();
        $this->assertSame(['Kapot Cobalt'], array_column($parts, 'name'));

        $norms = $this->getJson('/admin/repair-norms/lookup?q=Kapot&car_model_id='.$cobalt->id)->json();
        $this->assertEquals(3, $norms[0]['hours']);
        $this->assertSame('labor', $norms[0]['kind']);
    }

    public function test_price_list_is_imported_from_excel_csv(): void
    {
        $user = User::factory()->create();
        CarModel::create(['brand' => 'Chevrolet', 'model' => 'Cobalt']);
        $csv = "Nomi;Katalog raqami;O‘lchov;Narxi;Model\n"
            ."Old bamper;96543210;pcs;1 850 000;Chevrolet Cobalt\n"
            ."Kapot;;pcs;2 400 000,50;\n"
            ."Noma’lum;;pcs;100;Lada Vesta\n";
        $file = UploadedFile::fake()->createWithContent('parts.csv', "\xEF\xBB\xBF".$csv);

        $this->actingAs($user)->post('/admin/spare-parts/import', ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('spare_parts', ['name' => 'Old bamper', 'part_number' => '96543210', 'price' => 1850000]);
        $this->assertDatabaseHas('spare_parts', ['name' => 'Kapot', 'car_model_id' => null, 'price' => 2400000.5]);
        $this->assertDatabaseMissing('spare_parts', ['name' => 'Noma’lum']);
    }
}
