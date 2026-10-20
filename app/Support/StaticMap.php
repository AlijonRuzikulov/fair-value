<?php

namespace App\Support;

/**
 * A map picture made of tiles placed around a point: works without
 * JavaScript, so it is printed in reports exactly as it is shown.
 */
class StaticMap
{
    private const TILE = 256;

    /**
     * Tiles covering a width × height box centred on the point.
     *
     * @return list<array{url: string, left: int, top: int}>
     */
    public static function tiles(float $lat, float $lng, int $zoom, int $width, int $height): array
    {
        $count = 2 ** $zoom;
        $x = ($lng + 180) / 360 * $count * self::TILE;
        $rad = deg2rad(max(-85.05, min(85.05, $lat)));
        $y = (1 - log(tan($rad) + 1 / cos($rad)) / M_PI) / 2 * $count * self::TILE;

        $left = (int) round($x - $width / 2);
        $top = (int) round($y - $height / 2);
        $template = (string) config('fairvalue.map.tiles');

        $tiles = [];
        for ($ty = intdiv(max(0, $top), self::TILE); $ty * self::TILE < $top + $height && $ty < $count; $ty++) {
            for ($tx = (int) floor($left / self::TILE); $tx * self::TILE < $left + $width; $tx++) {
                $wrapped = (($tx % $count) + $count) % $count;
                $tiles[] = [
                    'url' => strtr($template, ['{z}' => $zoom, '{x}' => $wrapped, '{y}' => $ty, '{s}' => 'abc'[($wrapped + $ty) % 3]]),
                    'left' => $tx * self::TILE - $left,
                    'top' => $ty * self::TILE - $top,
                ];
            }
        }

        return $tiles;
    }

    /** Links to open the point in the map services people use. */
    public static function links(float $lat, float $lng): array
    {
        $ll = round($lat, 6).','.round($lng, 6);

        return [
            'Yandex' => 'https://yandex.uz/maps/?pt='.round($lng, 6).','.round($lat, 6).'&z=17&l=map',
            'Google' => 'https://www.google.com/maps?q='.$ll,
            'OpenStreetMap' => 'https://www.openstreetmap.org/?mlat='.round($lat, 6).'&mlon='.round($lng, 6).'#map=17/'.round($lat, 6).'/'.round($lng, 6),
        ];
    }
}
