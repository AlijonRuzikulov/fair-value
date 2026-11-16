<?php

namespace Tests\Unit;

use App\Reports\ReportScript;
use App\Support\Transliterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransliteratorTest extends TestCase
{
    public static function words(): array
    {
        return [
            ['Ko‘chmas mulk', 'Кўчмас мулк'],
            ['Yetkazilgan zarar', 'Етказилган зарар'],
            ['Ekspert xulosasi', 'Эксперт хулосаси'],
            ['Ta’mir', 'Таъмир'],
            ['Yangiyo‘l', 'Янгийўл'],
            ['Qoraqalpog‘iston', 'Қорақалпоғистон'],
            ['Registratsiya', 'Регистрация'],
            ['JShShIR', 'ЖШШИР'],
            ['F.I.Sh.', 'Ф.И.Ш.'],
            [':count ta obyekt', ':count та объект'],
            ['<em>haqiqiy qiymat</em>', '<em>ҳақиқий қиймат</em>'],
            ['Fair Value baholash', 'Fair Value баҳолаш'],
            ['masalan FV-7K3M-9Q2X', 'масалан FV-7K3M-9Q2X'],
            ['E-mail manzili', 'E-mail манзили'],
            ['Saytda APP_DEBUG=false qo‘ying.', 'Сайтда APP_DEBUG=false қўйинг.'],
            ['.env dagi ADMIN_PASSWORD', '.env даги ADMIN_PASSWORD'],
            ['storage/ va bootstrap/cache/ papkalari', 'storage/ ва bootstrap/cache/ папкалари'],
            ['Telegram’ga xabar (IP :ip)', 'Telegram’га хабар (IP :ip)'],
            ['www.olx.uz, e-auksion.uz saytlari', 'www.olx.uz, e-auksion.uz сайтлари'],
            ['10-son MBSga muvofiq', '10-сон МБСга мувофиқ'],
        ];
    }

    #[DataProvider('words')]
    public function test_latin_is_converted_to_cyrillic(string $latin, string $cyrillic): void
    {
        $this->assertSame($cyrillic, Transliterator::toCyrillic($latin));
    }

    public static function cyrillicNames(): array
    {
        return [
            ['Юнусобод 1-Мавзе', 'Yunusobod 1-Mavze'],
            ['Ўртаовул МФЙ', 'O‘rtaovul MFY'],
            ['Гўс деҳқон бозори', 'Go‘s dehqon bozori'],
            ['Еттисой', 'Yettisoy'],
            ['Ёғду', 'Yog‘du'],
            ['Мустақиллик кўчаси', 'Mustaqillik ko‘chasi'],
            ['Маъмурий бино', 'Ma’muriy bino'],
            ['Акция', 'Aksiya'],
            ['ЧОРСУ бозори', 'CHORSU bozori'],
            ['Feruza massivi', 'Feruza massivi'],
        ];
    }

    #[DataProvider('cyrillicNames')]
    public function test_cyrillic_is_converted_to_latin(string $cyrillic, string $latin): void
    {
        $this->assertSame($latin, Transliterator::toLatin($cyrillic));
    }

    public function test_a_name_is_shown_in_the_alphabet_of_the_language(): void
    {
        $this->assertSame('Yunusobod 1-Mavze', Transliterator::forLocale('Юнусобод 1-Мавзе', 'uz'));
        $this->assertSame('Юнусобод 1-Мавзе', Transliterator::forLocale('Юнусобод 1-Мавзе', 'oz'));
        $this->assertSame('Феруза массиви', Transliterator::forLocale('Feruza massivi', 'oz'));
        $this->assertSame('Юнусобод 1-Мавзе', Transliterator::forLocale('Юнусобод 1-Мавзе', 'ru'));
        $this->assertSame('', Transliterator::forLocale(null, 'uz'));
        // The report writes the name in its own alphabet.
        $this->assertSame('Go‘s dehqon bozori', ReportScript::make(ReportScript::LATIN)->name('Гўс деҳқон бозори'));
        $this->assertSame('Феруза массиви', ReportScript::make(ReportScript::CYRILLIC)->name('Feruza massivi'));
    }
}
