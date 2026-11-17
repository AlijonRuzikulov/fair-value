<?php

namespace Tests\Feature\Admin;

use App\Models\RealEstate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Final values saved before they followed the reconciliation are kept; missing ones are filled in. */
class FinalValueMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_typed_values_are_kept_and_missing_ones_filled_in(): void
    {
        $values = ['approaches' => json_encode(['comparative', 'income']), 'value_comparative' => 600_000_000, 'value_income' => 500_000_000, 'value_cost' => null, 'report' => null];
        $missing = RealEstate::factory()->residential()->create();
        $typed = RealEstate::factory()->residential()->create();
        $same = RealEstate::factory()->residential()->create();
        // As they were before: written straight to the table, past the rule of the model.
        DB::table('real_estates')->where('id', $missing->id)->update([...$values, 'market_value' => null]);
        DB::table('real_estates')->where('id', $typed->id)->update([...$values, 'market_value' => 545_000_000]);
        DB::table('real_estates')->where('id', $same->id)->update([...$values, 'market_value' => 550_000_000]);

        (require database_path('migrations/2026_11_18_000100_keep_typed_final_values.php'))->up();

        $this->assertSame('550000000.00', $missing->fresh()->market_value);
        $typed = $typed->fresh();
        $this->assertSame('545000000.00', $typed->market_value);
        $this->assertTrue($typed->report['final_typed']);
        $this->assertArrayNotHasKey('final_typed', (array) $same->fresh()->report);

        // The typed one stays when the object is saved again.
        $typed->update(['value_income' => 400_000_000]);
        $this->assertSame('545000000.00', $typed->fresh()->market_value);
        $same->fresh()->update(['value_income' => 400_000_000]);
        $this->assertSame('500000000.00', $same->fresh()->market_value);
    }
}
