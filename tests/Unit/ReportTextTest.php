<?php

namespace Tests\Unit;

use App\Reports\ReportScript;
use App\Reports\ReportText;
use PHPUnit\Framework\TestCase;

class ReportTextTest extends TestCase
{
    public function test_markup_becomes_paragraphs_lists_tables_and_formulas(): void
    {
        $text = "## Sarlavha\n\nBirinchi qator\ndavomi:\n- bir;\n- **ikki**.\n\nNomi | Qiymati\nYaIM | 107,6\n\n> V = I / R";

        $html = (string) ReportText::html($text, new ReportScript(ReportScript::LATIN));

        $this->assertStringContainsString('<h3>Sarlavha</h3>', $html);
        $this->assertStringContainsString('<p>Birinchi qator davomi:</p><ul><li>bir;</li><li><strong>ikki</strong>.</li></ul>', str_replace("\n", '', $html));
        $this->assertStringContainsString('<th>Nomi</th>', $html);
        $this->assertStringContainsString('<td class="num">107,6</td>', $html);
        $this->assertStringContainsString('<p class="formula">V = I / R</p>', $html);
    }

    public function test_charts_are_drawn_from_the_tables_above(): void
    {
        $text = implode("\n\n", [
            "Ko'rsatkich | O'lchov birligi | 2023 | 2024 | 2025 yil yanvar–sentabr\nEksport | mln AQSh dollari | 24 869,5 | 27 270,1 | 26 683,6\nImport | mln AQSh dollari | 38 659,1 | 39 968,3 | 33 111,0\nSaldo | mln AQSh dollari | −13 789,6 | −12 698,2 | −6 427,4",
            '[diagramma: Eksport; Import = Tashqi savdo]',
            '[diagramma: saldo]',
            "Hudud | Hajmi, mlrd so'm\nO'zbekiston Respublikasi | 1 000\nToshkent shahri | 600\nNavoiy viloyati | 400",
            '[diagramma: Hajmi, mlrd so‘m]',
            '[diagramma: Yalpi ichki mahsulot]',
        ]);

        $html = (string) ReportText::html($text, new ReportScript(ReportScript::LATIN));

        $this->assertSame(3, substr_count($html, '<figure class="chart">'));     // the unknown row draws nothing
        $this->assertStringContainsString('<figcaption>Tashqi savdo</figcaption>', $html);
        $this->assertSame(6, substr_count(explode('<figcaption>Saldo', $html)[0], '<path d="M'));  // 2 series × 3 years
        $this->assertStringContainsString('<figcaption>Saldo, mln AQSh dollari</figcaption>', $html);
        $this->assertStringContainsString('>−6 427,4</text>', explode('<figcaption>Saldo', $html)[1]);  // the latest value at its column
        // Bars over the rows of the column: the country total is left out, the largest region first.
        $bars = explode('<figcaption>Hajmi', $html)[1];
        $this->assertStringNotContainsString('Respublikasi</text>', $bars);
        $this->assertLessThan(strpos($bars, 'Navoiy'), strpos($bars, 'Toshkent shahri'));

        $cyrillic = (string) ReportText::html($text, new ReportScript(ReportScript::CYRILLIC));
        $this->assertStringContainsString('Тошкент шаҳри</text>', $cyrillic);
    }

    public function test_text_is_escaped_and_shown_in_the_chosen_alphabet(): void
    {
        $html = (string) ReportText::html("Bozor qiymati <script>alert(1)</script> o'z", new ReportScript(ReportScript::CYRILLIC));

        $this->assertStringContainsString('Бозор қиймати', $html);
        $this->assertStringContainsString('ўз', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame('o‘z ma’lumot', ReportScript::latin("o'z ma'lumot"));
    }
}
