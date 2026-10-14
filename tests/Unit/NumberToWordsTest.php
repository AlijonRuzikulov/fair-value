<?php

namespace Tests\Unit;

use App\Support\NumberToWords;
use App\Support\Transliterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NumberToWordsTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            [0, 'nol'],
            [7, 'yetti'],
            [10, 'o‘n'],
            [14, 'o‘n to‘rt'],
            [100, 'bir yuz'],
            [1000, 'bir ming'],
            [2026, 'ikki ming yigirma olti'],
            [1_000_000, 'bir million'],
            [514_168_000, 'besh yuz o‘n to‘rt million bir yuz oltmish sakkiz ming'],
            [3_000_000_090, 'uch milliard to‘qson'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_numbers_are_written_in_words(int $number, string $words): void
    {
        $this->assertSame($words, NumberToWords::uz($number));
    }

    public function test_words_can_be_shown_in_cyrillic(): void
    {
        $this->assertSame('беш юз ўн тўрт миллион бир юз олтмиш саккиз минг', Transliterator::toCyrillic(NumberToWords::uz(514_168_000)));
    }
}
