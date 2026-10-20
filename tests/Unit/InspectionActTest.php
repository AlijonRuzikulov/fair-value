<?php

namespace Tests\Unit;

use App\Reports\InspectionAct;
use PHPUnit\Framework\TestCase;

class InspectionActTest extends TestCase
{
    public function test_the_condition_follows_the_wear_scale(): void
    {
        $this->assertSame('Yaxshi', InspectionAct::conditionFor(0));
        $this->assertSame('Yaxshi', InspectionAct::conditionFor(20));
        $this->assertSame('Qoniqarli', InspectionAct::conditionFor(20.5));
        $this->assertSame('Qoniqarsiz', InspectionAct::conditionFor(60));
        $this->assertSame('Eskirgan', InspectionAct::conditionFor(61));
        $this->assertSame('Yaroqsiz', InspectionAct::conditionFor(100));
    }

    public function test_the_wear_of_the_building_is_weighted_by_the_known_elements_only(): void
    {
        $this->assertSame(100, array_sum(array_column(InspectionAct::ELEMENTS, 1)), 'The typical shares add up to the whole building.');

        $act = InspectionAct::build([
            'walls' => "Pishgan g'isht",
            'act' => ['elements' => ['walls' => ['wear' => 30], 'roof_cover' => ['wear' => 60], 'finishing' => ['condition' => 'Yaxshi', 'description' => 'Oddiy']]],
        ]);

        $this->assertSame(round((26 * 30 + 5 * 60) / 31, 1), $act['wear']);
        $rows = array_column($act['rows'], null, 'title');
        $this->assertSame("Pishgan g'isht", $rows["Devorlar va to'siqlar"]['description']);
        $this->assertSame('Qoniqarli', $rows["Devorlar va to'siqlar"]['condition']);
        $this->assertSame('Oddiy', $rows['Pardozlash ishlari']['description']);
        $this->assertNull($rows['Poydevor']['wear']);
        $this->assertTrue($act['filled']);

        $this->assertNull(InspectionAct::build([])['wear']);
        $this->assertFalse(InspectionAct::build([])['filled']);
    }
}
