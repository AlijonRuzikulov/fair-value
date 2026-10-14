<?php

namespace App\Services\Documents;

use App\Models\Attachment;
use App\Models\District;
use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Data read from documents: preparing the files for the AI, cleaning the
 * answer, and matching places to the region and district directories.
 */
final class DocumentData
{
    public const KINDS = ['id_card', 'passport', 'cadastral_extract', 'technical_passport', 'contract', 'photo', 'other'];

    public const PERSON = [
        'last_name', 'first_name', 'middle_name', 'birth_date', 'pinfl', 'passport_series', 'passport_number',
        'passport_issued_by', 'passport_issued_at', 'address',
    ];

    /** Longest side of an image sent to the AI; larger photos are scaled down. */
    private const MAX_SIDE = 2000;

    private const MAX_FILES = 6;

    /**
     * Images and PDFs of the attachments, ready for DocumentAi::read().
     *
     * @param  Collection<int, Attachment>  $attachments
     * @return list<array{name: string, mime: string, data: string}>
     */
    public static function files(Collection $attachments): array
    {
        $files = [];
        foreach ($attachments->take(self::MAX_FILES) as $attachment) {
            if (! Storage::disk('local')->exists($attachment->path)) {
                continue;
            }
            $body = Storage::disk('local')->get($attachment->path);
            if ($attachment->mime === 'application/pdf') {
                if (strlen($body) > 20_000_000) {
                    throw new RuntimeException(__('The file :name is too large to be read.', ['name' => $attachment->name]));
                }
                $files[] = ['name' => $attachment->name, 'mime' => 'application/pdf', 'data' => base64_encode($body)];
            } elseif ($attachment->isImage()) {
                $files[] = ['name' => $attachment->name, 'mime' => 'image/jpeg', 'data' => base64_encode(self::jpeg($body))];
            }
        }

        return $files;
    }

    /** A photo re-encoded as JPEG no larger than MAX_SIDE (also drops any embedded data). */
    private static function jpeg(string $body): string
    {
        $image = @imagecreatefromstring($body);
        if ($image === false) {
            throw new RuntimeException(__('An image could not be opened.'));
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
            imagedestroy($image);
            $image = $resized;
        }
        ob_start();
        imagejpeg($image, null, 85);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /** The AI answer with trimmed values, valid dates and numbers only. */
    public static function normalise(array $data): array
    {
        $text = fn ($v, int $max = 250) => is_string($v) && trim($v) !== '' ? mb_substr(trim(preg_replace('/\s+/u', ' ', $v)), 0, $max) : null;
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
        $number = fn ($v) => is_numeric($v) && (float) $v > 0 ? round((float) $v, 2) : null;
        $integer = fn ($v) => is_numeric($v) && (int) $v > 0 ? (int) $v : null;

        $person = [];
        foreach (self::PERSON as $key) {
            $person[$key] = $text($data['person'][$key] ?? null);
        }
        $person['birth_date'] = $date($person['birth_date']);
        $person['passport_issued_at'] = $date($person['passport_issued_at']);
        $person['pinfl'] = $person['pinfl'] !== null && preg_match('/^\d{14}$/', str_replace(' ', '', $person['pinfl'])) ? str_replace(' ', '', $person['pinfl']) : null;
        $person['passport_series'] = $person['passport_series'] !== null && preg_match('/^[A-Z]{2}$/', strtoupper($person['passport_series'])) ? strtoupper($person['passport_series']) : null;
        $person['passport_number'] = $person['passport_number'] !== null && preg_match('/^\d{7}$/', str_replace(' ', '', $person['passport_number'])) ? str_replace(' ', '', $person['passport_number']) : null;
        foreach (['last_name', 'first_name', 'middle_name'] as $key) {
            $person[$key] = $person[$key] !== null ? mb_convert_case(mb_strtolower($person[$key]), MB_CASE_TITLE) : null;
        }

        $p = $data['property'] ?? [];
        $property = [
            'cadastral_number' => $text($p['cadastral_number'] ?? null, 60),
            'object_type' => $text($p['object_type'] ?? null),
            'name' => $text($p['name'] ?? null),
            'address' => $text($p['address'] ?? null),
            'region' => $text($p['region'] ?? null),
            'district' => $text($p['district'] ?? null),
            'owner_name' => $text($p['owner_name'] ?? null),
            'share' => $text($p['share'] ?? null, 60),
            'rights_type' => $text($p['rights_type'] ?? null),
            'registration_date' => $date($p['registration_date'] ?? null),
            'rights_basis' => $text($p['rights_basis'] ?? null, 500),
            'encumbrances' => $text($p['encumbrances'] ?? null, 500),
            'total_area' => $number($p['total_area'] ?? null),
            'living_area' => $number($p['living_area'] ?? null),
            'useful_area' => $number($p['useful_area'] ?? null),
            'land_area' => $number($p['land_area'] ?? null),
            'floors_total' => $integer($p['floors_total'] ?? null),
            'floor' => $integer($p['floor'] ?? null),
            'rooms' => $integer($p['rooms'] ?? null),
            'year_built' => ($y = $integer($p['year_built'] ?? null)) && $y >= 1800 && $y <= (int) date('Y') ? $y : null,
        ];

        $documents = [];
        foreach ((array) ($data['documents'] ?? []) as $document) {
            if (is_array($document) && is_numeric($document['file'] ?? null) && in_array($document['kind'] ?? null, self::KINDS, true)) {
                $documents[] = ['file' => (int) $document['file'], 'kind' => $document['kind']];
            }
        }

        return ['documents' => $documents, 'person' => $person, 'property' => $property, 'notes' => $text($data['notes'] ?? null, 1000)];
    }

    /** The region and district named in a document, found in the directory. */
    public static function place(?string $region, ?string $district): array
    {
        $full = fn (?string $s) => trim(preg_replace(['/[‘’ʻʼ\'`]/u', '/\s+/u'], ['', ' '], mb_strtolower((string) $s)));
        // Without words such as "viloyati", "shahri", "tumani" — used when the full name does not match.
        $short = fn (?string $s) => trim(preg_replace('/(?<!\pL)(viloyati|viloyat|shahri|shahar|tumani|tuman|oblast|область|вилояти|шаҳри|тумани|район|р-н|г\.)(?!\pL)/u', '', $full($s)));
        $find = function (Collection $rows, ?string $name) use ($full, $short) {
            $columns = ['name_uz', 'name_oz', 'name_ru'];
            if ($full($name) === '') {
                return null;
            }

            return $rows->first(fn ($row) => collect($columns)->contains(fn ($c) => $full($row->{$c}) === $full($name)))
                ?? $rows->first(fn ($row) => collect($columns)->contains(fn ($c) => ($k = $short($row->{$c})) !== '' && $k === $short($name)))
                ?? $rows->first(fn ($row) => collect($columns)->contains(fn ($c) => ($k = $short($row->{$c})) !== '' && mb_strlen($k) > 3 && str_contains($short($name), $k)));
        };

        $foundRegion = $find(Region::all(), $region);
        $foundDistrict = $find(District::query()->when($foundRegion, fn ($q) => $q->where('region_id', $foundRegion->id))->get(), $district);

        return ['region' => $foundRegion ?? $foundDistrict?->region, 'district' => $foundDistrict];
    }
}
