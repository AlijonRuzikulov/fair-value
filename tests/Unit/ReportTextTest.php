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

    public function test_text_is_escaped_and_shown_in_the_chosen_alphabet(): void
    {
        $html = (string) ReportText::html("Bozor qiymati <script>alert(1)</script> o'z", new ReportScript(ReportScript::CYRILLIC));

        $this->assertStringContainsString('Бозор қиймати', $html);
        $this->assertStringContainsString('ўз', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame('o‘z ma’lumot', ReportScript::latin("o'z ma'lumot"));
    }
}
