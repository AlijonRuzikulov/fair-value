<?php

namespace App\Support;

/**
 * Codes printed on reports so that banks and courts can check them on the
 * website, e.g. "FV-7K3M-9Q2X". Characters that are easy to confuse
 * (0/O, 1/I/L) are not used.
 */
class VerifyCode
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public static function generate(): string
    {
        do {
            $code = 'FV-'.self::chunk().'-'.self::chunk();
        } while (Registry::findByVerifyCode($code) !== null);

        return $code;
    }

    /** Normalises user input: lower case, spaces, missing dashes. */
    public static function normalize(?string $input): string
    {
        $raw = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $input));
        if (str_starts_with($raw, 'FV')) {
            $raw = substr($raw, 2);
        }
        if (strlen($raw) !== 8) {
            return '';
        }

        return 'FV-'.substr($raw, 0, 4).'-'.substr($raw, 4, 4);
    }

    private static function chunk(): string
    {
        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $out;
    }
}
