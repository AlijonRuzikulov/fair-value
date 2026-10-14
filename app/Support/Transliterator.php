<?php

namespace App\Support;

/**
 * Uzbek Latin → Uzbek Cyrillic transliteration.
 *
 * Used to generate lang/oz.json from lang/uz.json and Cyrillic names of
 * directory entries. Words that do not follow the rules (mostly Russian
 * loanwords) are listed in self::WORDS; translation placeholders such as
 * ":count" and words in self::KEEP are left untouched.
 */
class Transliterator
{
    /** Whole-word exceptions (lower case). */
    private const WORDS = [
        'litsenziya' => 'лицензия', 'litsenziyasi' => 'лицензияси', 'litsenziyalangan' => 'лицензияланган',
        'sotsial' => 'социал', 'tsex' => 'цех', 'kompyuter' => 'компьютер', 'kompyuterlar' => 'компьютерлар',
        'aprel' => 'апрель', 'iyun' => 'июнь', 'iyul' => 'июль', 'sentabr' => 'сентябрь', 'oktabr' => 'октябрь',
        'noyabr' => 'ноябрь', 'dekabr' => 'декабрь', 'fevral' => 'февраль', 'yanvar' => 'январь',
        'pensiya' => 'пенсия', 'yevro' => 'евро', 'yeyish' => 'ейиш', 'panel' => 'панель', 'dvigatel' => 'двигатель', 'model' => 'модель', 'modeli' => 'модели',
        'modellar' => 'моделлар', 'modelini' => 'моделини', 'modellari' => 'моделлари', 'aktsiya' => 'акция',
        'aksiyalar' => 'акциялар', 'aksiya' => 'акция', 'aksiyadorlik' => 'акциядорлик', 'kvitansiya' => 'квитанция',
        'konferensiya' => 'конференция', 'stansiya' => 'станция', 'funksiya' => 'функция', 'funksiyalar' => 'функциялар',
        'distansion' => 'дистанцион', 'reyting' => 'рейтинг', 'parol' => 'пароль', 'parolni' => 'паролни',
        'paroli' => 'пароли', 'parollar' => 'пароллар', 'profil' => 'профиль', 'profilni' => 'профилни',
        'fayl' => 'файл', 'fayllar' => 'файллар', 'faylni' => 'файлни', 'faylga' => 'файлга',
        'mebel' => 'мебель', 'kalkulyator' => 'калькулятор', 'kalkulyatorlar' => 'калькуляторлар',
        'avtomobil' => 'автомобиль', 'avtomobillar' => 'автомобиллар', 'avtomobilni' => 'автомобилни',
        'avtomobilning' => 'автомобилнинг', 'servis' => 'сервис', 'portfel' => 'портфель',
        'dizel' => 'дизель', 'gibrid' => 'гибрид', 'elektr' => 'электр', 'elektromobil' => 'электромобиль',
        'kafolat' => 'кафолат', 'kafolatlaymiz' => 'кафолатлаймиз', 'senariy' => 'сценарий',
        'ob\'ekt' => 'объект', 'obyekt' => 'объект', 'obyektlar' => 'объектлар', 'obyekti' => 'объекти',
        'obyektni' => 'объектни', 'obyektning' => 'объектнинг', 'obyektlari' => 'объектлари',
        'obyektlarni' => 'объектларни', 'obyektlarning' => 'объектларнинг', 'obyektga' => 'объектга',
        'subyekt' => 'субъект', 'aksessuar' => 'аксессуар', 'yanvardan' => 'январдан',
        'sertifikat' => 'сертификат', 'sertifikati' => 'сертификати', 'sertifikatlar' => 'сертификатлар',
        'region' => 'регион', 'shtamp' => 'штамп', 'press' => 'пресс',
    ];

    /** Loanword stems (lower case) that keep their Russian spelling; suffixes are transliterated as usual. */
    private const STEMS = [
        'konditsioner' => 'кондиционер', 'mototsikl' => 'мотоцикл', 'operatsion' => 'операцион',
        'potensial' => 'потенциал', 'spetsifikatsiya' => 'спецификация', 'funksional' => 'функционал',
        'funksiya' => 'функция', 'aksiya' => 'акция', 'litsenziya' => 'лицензия', 'sotsial' => 'социал',
        'jshshir' => 'ЖШШИР', 'aqsh' => 'АҚШ', 'yth' => 'ЙТҲ', 'stir' => 'СТИР',
    ];

    /** Words that stay in Latin script. */
    private const KEEP = [
        'Fair', 'Value', 'FAIR', 'VALUE', 'FV', 'USD', 'EUR', 'RUB', 'GBP', 'CNY', 'KZT', 'UZS', 'QR', 'PDF',
        'CSV', 'Excel', 'Word', 'Telegram', 'Instagram', 'Facebook', 'E-mail', 'email', 'VIN', 'ID', 'OKED',
        'Chevrolet', 'Daewoo', 'Nexia', 'Cobalt', 'Spark', 'Gentra', 'Lacetti', 'Malibu', 'Damas', 'Labo',
        'Matiz', 'Tracker', 'Onix', 'Captiva', 'Equinox', 'Tahoe', 'Isuzu', 'MAN', 'BYD', 'Kia', 'Hyundai',
        'Toyota', 'Lada', 'GM', 'Cyrl', 'Latn', 'TEO', 'IT', 'HTML', 'CBU', 'Google', 'Yandex', 'MChJ', 'LLC',
        'HTTPS', 'https', 'IP', 'README', 'cron', 'SQLite', 'MySQL',
    ];

    /** Two-letter combinations first, then single letters. */
    private const MAP = [
        'sh' => 'ш', 'ch' => 'ч', 'yo' => 'ё', 'yu' => 'ю', 'ya' => 'я', 'ye' => 'е',
        'a' => 'а', 'b' => 'б', 'd' => 'д', 'e' => 'е', 'f' => 'ф', 'g' => 'г', 'h' => 'ҳ',
        'i' => 'и', 'j' => 'ж', 'k' => 'к', 'l' => 'л', 'm' => 'м', 'n' => 'н', 'o' => 'о',
        'p' => 'п', 'q' => 'қ', 'r' => 'р', 's' => 'с', 't' => 'т', 'u' => 'у', 'v' => 'в',
        'x' => 'х', 'y' => 'й', 'z' => 'з', 'c' => 'с', 'w' => 'в',
    ];

    private const VOWELS = 'aeiouAEIOU';

    public static function toCyrillic(string $text): string
    {
        // Normalise the different apostrophes used for o‘, g‘ and the glottal stop.
        $text = str_replace(['‘', '’', 'ʻ', 'ʼ', '`', '´'], "'", $text);

        // Protect HTML tags, :placeholders, URLs, e-mails, {braces}, the word
        // "e-mail", codes such as "FV-7K3M-9Q2X", settings such as
        // "APP_DEBUG=false", the ".env" file and folders such as "storage/".
        return preg_replace_callback(
            '/(<[^>]+>|:[a-z_]+|https?:\/\/\S+|\S+@\S+|\{[^}]*\}|&[a-z]+;|\b[Ee]-mail\b|\b(?=[A-Z0-9-]*\d)[A-Z0-9]+(?:-[A-Z0-9]+)+\b|\b[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)+(?:=[a-z0-9]+)?\b|\.env\b|\b[a-z]+\/(?:[a-z]+\/)*(?=[\s),.]|$))|([A-Za-z\']+)/u',
            fn ($m) => $m[1] !== '' ? $m[1] : self::word($m[2]),
            $text
        );
    }

    private static function word(string $word): string
    {
        if (in_array($word, self::KEEP, true) || trim($word, "'") === '') {
            return $word;
        }
        // A kept name with an Uzbek suffix: Telegram’ga → Telegram’га.
        if (preg_match("/^([A-Za-z]+)'([a-z]+)$/", $word, $m) && in_array($m[1], self::KEEP, true)) {
            return $m[1].'’'.self::word($m[2]);
        }
        // Roman numerals like "II", "XX" stay Latin.
        if (preg_match('/^[IVX]{2,}$/', $word)) {
            return $word;
        }

        $lower = mb_strtolower($word);
        if (isset(self::WORDS[$lower])) {
            return self::matchCase($word, self::WORDS[$lower]);
        }

        foreach (self::STEMS as $stem => $cyrillic) {
            if (str_starts_with($lower, $stem)) {
                $lower = $cyrillic.substr($lower, strlen($stem));
                break;
            }
        }

        // "-tsiya" → "-ция": registratsiya, deklaratsiya, operatsiya.
        $lower = preg_replace('/tsiya/', 'ция', $lower);

        $out = '';
        $len = strlen($lower);
        $i = 0;
        while ($i < $len) {
            $ch = $lower[$i];
            $next = $lower[$i + 1] ?? '';
            $prev = $i > 0 ? $lower[$i - 1] : '';

            // Already converted multibyte chunk (from the "tsiya" rule).
            if (ord($ch) > 127) {
                $bytes = (ord($ch) & 0xE0) === 0xC0 ? 2 : ((ord($ch) & 0xF0) === 0xE0 ? 3 : 4);
                $out .= substr($lower, $i, $bytes);
                $i += $bytes;

                continue;
            }

            if ($ch === "'") {
                // A glottal stop between letters becomes "ъ".
                $out .= ($i > 0 && $i < $len - 1) ? 'ъ' : "'";
                $i++;

                continue;
            }
            if (($ch === 'o' || $ch === 'g') && $next === "'") {
                $out .= $ch === 'o' ? 'ў' : 'ғ';
                $i += 2;

                continue;
            }
            if ($ch === 's' && $next === "'" && ($lower[$i + 2] ?? '') === 'h') {
                $out .= 'сҳ';
                $i += 3;

                continue;
            }
            // "e" is written "э" at the start of a word and after a vowel.
            if ($ch === 'e' && ($i === 0 || ($prev !== '' && str_contains(self::VOWELS, $prev)))) {
                $out .= 'э';
                $i++;

                continue;
            }
            // "yo‘" is й + ў, not ё + glottal stop: Yangiyo‘l → Янгийўл.
            if ($ch === 'y' && $next === 'o' && ($lower[$i + 2] ?? '') === "'") {
                $out .= 'й';
                $i++;

                continue;
            }
            // "ts" at the start of a word: tsex → цех.
            if ($ch === 't' && $next === 's' && $i === 0) {
                $out .= 'ц';
                $i += 2;

                continue;
            }

            $pair = $ch.$next;
            if ($next !== '' && isset(self::MAP[$pair])) {
                $out .= self::MAP[$pair];
                $i += 2;

                continue;
            }
            $out .= self::MAP[$ch] ?? $ch;
            $i++;
        }

        return self::matchCase($word, $out);
    }

    /** Applies the letter case of the Latin original to the Cyrillic result. */
    private static function matchCase(string $original, string $converted): string
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $original);
        if ($letters === '') {
            return $converted;
        }
        if (strlen($letters) > 1 && strtoupper($letters) === $letters) {
            return mb_strtoupper($converted);
        }
        if (ctype_upper($letters[0])) {
            return mb_strtoupper(mb_substr($converted, 0, 1)).mb_substr($converted, 1);
        }

        return $converted;
    }
}
