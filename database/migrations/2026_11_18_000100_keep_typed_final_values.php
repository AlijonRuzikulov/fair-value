<?php

use App\Models\RealEstate;
use App\Reports\RealEstateReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The final market value of real estate now follows the reconciliation of the
 * approaches. A value saved before that and different from the reconciled one
 * was typed by an appraiser: it is marked so that it is kept. An object with
 * approach values and no final value gets the reconciled one.
 */
return new class extends Migration
{
    public function up(): void
    {
        RealEstate::query()->withoutGlobalScopes()->select(['id', 'approaches', 'report', 'value_cost', 'value_comparative', 'value_income', 'market_value'])
            ->chunkById(200, function ($objects) {
                foreach ($objects as $object) {
                    $reconciled = RealEstateReport::reconciledValue($object);
                    if ($object->market_value === null) {
                        if ($reconciled !== null) {
                            DB::table('real_estates')->where('id', $object->id)->update(['market_value' => $reconciled]);
                        }
                    } elseif ($reconciled === null || abs((float) $object->market_value - $reconciled) >= 0.005) {
                        DB::table('real_estates')->where('id', $object->id)
                            ->update(['report' => json_encode([...(array) $object->report, 'final_typed' => true], JSON_UNESCAPED_UNICODE)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // The marks are harmless; the values stay as they are.
    }
};
