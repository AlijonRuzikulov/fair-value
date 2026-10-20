<?php

namespace App\Reports;

use App\Enums\PersonRole;
use App\Models\Client;
use App\Models\RealEstate;
use App\Models\Setting;
use App\Support\NumberToWords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Documents signed with the customer of a valuation: the service contract,
 * the act of acceptance of the work, the invoice and the valuation
 * assignment. Texts are in Uzbek Latin and printed in the chosen alphabet;
 * names and requisites typed by people are printed as they were typed.
 */
final class ContractPack
{
    public const DOCUMENTS = ['contract', 'act', 'invoice', 'assignment'];

    /**
     * @param  Model  $item  a valuation object (RealEstate, Vehicle, Asset…)
     * @param  list<string>  $documents
     */
    public static function build(Model $item, ReportScript $s, bool $qr, array $documents = self::DOCUMENTS): array
    {
        $item->loadMissing(['clients', 'region', 'district', 'appraiser']);
        $f = $item instanceof RealEstate ? RealEstateReport::values($item) : self::genericValues($item);

        $company = RealEstateReport::company() + [
            'insurance_policy' => Setting::get('insurance_policy'),
            'chief_accountant' => Setting::get('chief_accountant'),
        ];
        $owner = $item->clients->firstWhere('pivot.role', PersonRole::Owner->value) ?? $item->clients->first();
        $customer = $item->clients->firstWhere('pivot.role', PersonRole::Customer->value) ?? $owner;
        $representative = $item->clients->firstWhere('pivot.role', PersonRole::Representative->value);

        $nameColumn = $s->isCyrillic() ? 'name_oz' : 'name_uz';
        $location = collect([
            $item->region?->getAttribute($nameColumn) ?? $item->region?->name_uz,
            $item->district?->getAttribute($nameColumn) ?? $item->district?->name_uz,
            $item->address,
        ])->filter()->implode(', ');

        $fee = self::fee((float) $item->service_fee);
        $value = $item->market_value !== null ? (float) $item->market_value : null;
        $contractDate = Carbon::parse($item->contract_date ?? $item->created_at ?? now());
        $reportDate = Carbon::parse($f['report_date'] ?? $item->completed_at ?? now());
        $purpose = $item->purposeLabel() ?? '—';

        $placeholders = [
            'object' => [$item->title, true],
            'address' => [$location ?: '—', true],
            'purpose' => [$purpose, true],
            'value_type' => [$f['value_type'], true],
            'fee' => [self::money($fee['total']), false],
            'fee_words' => [self::capital(NumberToWords::uz((int) round($fee['total']))), true],
            'vat' => [$fee['rate'] > 0 ? 'QQS bilan' : 'QQS siz', true],
            'insurance' => [rtrim((string) $company['insurance_policy'], '. ') ?: '—', true],
            'membership' => [rtrim((string) $company['membership'], '. ') ?: '—', true],
            'deadline' => [$f['deadline'], true],
            'customer' => [$customer?->title ?? '—', false],
            'company' => [$company['legal_name'] ?: $company['name'], false],
            'city' => [$company['report_city'] ?: 'Toshkent', true],
        ];

        return [
            's' => $s,
            'qr' => $qr,
            'documents' => array_values(array_intersect(self::DOCUMENTS, $documents)),
            'item' => $item,
            'f' => $f,
            'company' => $company,
            'customer' => $customer,
            'representative' => $representative,
            'location' => $location,
            'purpose' => $purpose,
            'contractNumber' => $item->contract_number ?: '—',
            'contractDate' => $contractDate,
            'reportNumber' => $item->report_number ?: '—',
            'reportDate' => $reportDate,
            'valuationDate' => Carbon::parse($item->valuation_date ?? $reportDate),
            'fee' => $fee + ['words' => self::capital(NumberToWords::uz((int) round($fee['total'])))],
            'value' => $value,
            'valueWords' => $value !== null ? self::capital(NumberToWords::uz((int) round($value))) : null,
            'terms' => self::terms($s, $placeholders),
            'description' => self::description($item),
            'customerShort' => self::shortName($customer),
            'directorShort' => self::shortPersonName((string) $company['director']),
        ];
    }

    /**
     * The service fee includes VAT when the company pays it.
     *
     * @return array{total: float, net: float, vat: float, rate: float}
     */
    public static function fee(float $total): array
    {
        $rate = max(0.0, (float) Setting::get('vat_rate'));
        $vat = $rate > 0 ? round($total * $rate / (100 + $rate), 2) : 0.0;

        return ['total' => $total, 'net' => round($total - $vat, 2), 'vat' => $vat, 'rate' => $rate];
    }

    public static function money(?float $amount): string
    {
        return $amount === null ? '—' : number_format($amount, 2, ',', ' ');
    }

    /** "Sh.G.Jurayev" for a person, the director for a company. */
    public static function shortName(?Client $client): string
    {
        if (! $client) {
            return '';
        }
        if ($client->isLegal()) {
            return self::shortPersonName((string) $client->director) ?: (string) $client->company_name;
        }

        return self::initials([$client->first_name, $client->middle_name]).$client->last_name;
    }

    /** "SAYDALIYEV ASLIDDIN ILXOM O'G'LI" → "A.I.Saydaliyev"; a name already short is kept. */
    public static function shortPersonName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        if (count($parts) < 2 || str_contains($name, '.')) {
            return trim($name);
        }
        $last = mb_convert_case(mb_strtolower(array_shift($parts)), MB_CASE_TITLE);

        return self::initials(array_slice($parts, 0, 2)).$last;
    }

    /** Initials with the Uzbek digraphs kept whole: Shokirjon → "Sh.", O'tkir → "O‘.". */
    private static function initials(array $names): string
    {
        $out = '';
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $initial = preg_match("/^(sh|ch|ng|[og]['‘’ʻʼ`])/iu", $name, $m) ? $m[1] : mb_substr($name, 0, 1);
            $out .= mb_strtoupper(mb_substr($initial, 0, 1)).str_replace(["'", '’', 'ʻ', 'ʼ', '`'], '‘', mb_strtolower(mb_substr($initial, 1))).'.';
        }

        return $out;
    }

    /** The contract terms with the placeholders filled in; typed names are not transliterated. */
    private static function terms(ReportScript $s, array $placeholders): HtmlString
    {
        $raw = [];
        $text = preg_replace_callback('/\{([a-z_]+)\}/', function (array $m) use ($placeholders, &$raw) {
            if (! isset($placeholders[$m[1]])) {
                return $m[0];
            }
            [$value, $transliterate] = $placeholders[$m[1]];
            if ($transliterate) {
                return (string) $value;
            }
            $raw[] = (string) $value;

            // Private-use marks survive the transliteration and are replaced after it.
            return "\u{E000}".(count($raw) - 1)."\u{E001}";
        }, ReportText::get('contract'));

        $html = (string) ReportText::html((string) $text, $s);

        return new HtmlString(preg_replace_callback("/\u{E000}(\\d+)\u{E001}/u", fn (array $m) => e($raw[(int) $m[1]] ?? ''), $html));
    }

    /** What is valued, for the assignment: the name and, for real estate, the areas. */
    private static function description(Model $item): string
    {
        $text = (string) $item->title;
        if ($item instanceof RealEstate) {
            $areas = collect([
                'umumiy maydoni' => $item->total_area,
                'foydali maydoni' => $item->useful_area,
                'qurilish osti maydoni' => $item->built_up_area,
                'yer maydoni' => $item->land_area,
            ])->filter(fn ($area) => $area !== null && (float) $area > 0)
                ->map(fn ($area, $label) => $label.' '.number_format((float) $area, 2, ',', ' ').' kv. m');
            if ($areas->isNotEmpty()) {
                $text .= ': '.$areas->implode(', ');
            }
        }

        return $text.'.';
    }

    /** Assignment data for objects other than real estate. */
    private static function genericValues(Model $item): array
    {
        return [
            'report_date' => ($item->completed_at ?? now())->toDateString(),
            'value_type' => 'Bozor qiymati',
            'rights' => 'Mulk huquqi',
            'use_period' => '6 oy',
            'report_format' => "Elektron ko'rinishda",
            'deadline' => 'Barcha hujjatlar taqdim etilgandan keyin 3 ish kuni',
            'initial_info' => 'Obyektga oid hujjatlar; mulk egasining shaxsini tasdiqlovchi hujjat.',
            'limiting_conditions' => 'Mavjud emas',
        ];
    }

    private static function capital(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
