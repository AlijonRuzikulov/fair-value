<?php

namespace App\Reports;

use App\Support\Transliterator;

/**
 * The alphabet a report is printed in. Texts are written in Uzbek Latin;
 * the Cyrillic report is made by transliteration. What people typed
 * (names, addresses) is shown as it was typed.
 */
final class ReportScript
{
    public const LATIN = 'latn';

    public const CYRILLIC = 'cyrl';

    public function __construct(public readonly string $script = self::CYRILLIC) {}

    public static function make(?string $script): self
    {
        return new self($script === self::LATIN ? self::LATIN : self::CYRILLIC);
    }

    public function isCyrillic(): bool
    {
        return $this->script === self::CYRILLIC;
    }

    /** A text of the report (Uzbek Latin with plain apostrophes) in the chosen alphabet. */
    public function t(?string $text): string
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        return $this->isCyrillic() ? Transliterator::toCyrillic($text) : self::latin($text);
    }

    /** A value typed by a person: unchanged, or "—" when empty. */
    public function v(mixed $value): string
    {
        return filled($value) ? (string) $value : '—';
    }

    /** Typographic Uzbek Latin: o' and g' with a turned comma, the glottal stop with an apostrophe. */
    public static function latin(string $text): string
    {
        $text = preg_replace("/([oOgG])['‘’ʻʼ`]/u", '$1‘', $text);

        return preg_replace("/(\\pL)['ʼ`](\\pL)/u", '$1’$2', $text);
    }
}
