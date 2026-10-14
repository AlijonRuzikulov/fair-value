<?php

namespace App\Reports;

use App\Enums\PersonRole;
use App\Enums\RealEstateKind;
use App\Enums\ValuationApproach;
use App\Models\Analog;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\RealEstate;
use App\Models\Setting;
use App\Services\CurrencyService;
use App\Support\ComparativeCalculator;
use App\Support\NumberToWords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The full valuation report of a real estate object ("Baholash hisoboti"):
 * the values the appraiser fills in on the report form (with sensible
 * defaults) and everything the printed report shows, worked out from the
 * object, its persons, analogues, approaches and the company settings.
 */
final class RealEstateReport
{
    /** Report data by step of the preparation page: field → type (text, textarea, date, number, select). */
    public const FORM = [
        'object' => [
            'function' => 'text', 'ownership_form' => 'text', 'rights' => 'text', 'rights_basis' => 'text', 'land_rights' => 'text',
            'encumbrances' => 'text', 'ecology' => 'text', 'development' => 'text',
            'land_category' => 'text', 'geology' => 'text', 'hydrogeology' => 'text', 'topography' => 'text',
            'land_improvements' => 'text', 'land_hbu' => 'text',
        ],
        'inspection' => [
            'building_volume' => 'number', 'completion' => 'number', 'foundation' => 'text', 'walls' => 'text',
            'roof_cover' => 'text', 'roof_slab' => 'text', 'floor_type' => 'text', 'windows_doors' => 'text',
            'repair' => 'text', 'architecture' => 'text', 'landscaping' => 'text', 'surroundings' => 'text', 'convertibility' => 'textarea',
        ],
        'approaches' => [
            'reason_comparative' => 'textarea', 'reason_cost' => 'textarea', 'reason_income' => 'textarea', 'hbu_conclusion' => 'textarea',
        ],
        'report' => [
            'script' => 'select', 'report_date' => 'date', 'letter_number' => 'text', 'value_type' => 'text',
            'currency_text' => 'text', 'use_period' => 'text', 'report_format' => 'text', 'intended_users' => 'text',
            'deadline' => 'text', 'initial_info' => 'textarea', 'special_assumptions' => 'textarea', 'limiting_conditions' => 'textarea',
            'use_restrictions' => 'textarea',
        ],
    ];

    /** Typical construction of common buildings: one click fills the inspection step. */
    public const PRESETS = [
        'panel' => [
            'foundation' => 'Temirbeton bloklar', 'walls' => 'Temirbeton panellar', 'roof_slab' => 'Temirbeton plitalar',
            'roof_cover' => 'Rulonli (ruberoid)', 'floor_type' => 'Linoleum, laminat', 'windows_doors' => 'Plastik derazalar, metall eshik',
            'architecture' => 'Tipik loyiha', 'landscaping' => 'Obodonlashtirilgan', 'surroundings' => "Ko'p qavatli turar joy binolari",
        ],
        'brick' => [
            'foundation' => 'Lentasimon temirbeton', 'walls' => "Pishgan g'isht", 'roof_slab' => 'Temirbeton plitalar',
            'roof_cover' => 'Rulonli', 'floor_type' => 'Laminat, kafel', 'windows_doors' => 'Plastik derazalar, metall eshik',
            'architecture' => 'Oddiy', 'landscaping' => 'Obodonlashtirilgan', 'surroundings' => "Ko'p qavatli turar joy binolari",
        ],
        'monolith' => [
            'foundation' => 'Monolit temirbeton plita', 'walls' => 'Monolit temirbeton karkas, gazoblok', 'roof_slab' => 'Monolit temirbeton',
            'roof_cover' => 'Membranali', 'floor_type' => 'Laminat, kafel', 'windows_doors' => 'Alyumin-plastik derazalar, metall eshik',
            'architecture' => 'Zamonaviy', 'landscaping' => 'Obodonlashtirilgan, avtoturargoh', 'surroundings' => 'Yangi turar joy majmualari',
        ],
        'house' => [
            'foundation' => 'Lentasimon beton', 'walls' => "Pishgan g'isht", 'roof_slab' => "Yog'och to'sinlar",
            'roof_cover' => 'Metallocherepitsa', 'floor_type' => 'Beton, laminat', 'windows_doors' => "Plastik derazalar, yog'och eshiklar",
            'architecture' => 'Oddiy', 'landscaping' => 'Hovli obodonlashtirilgan', 'surroundings' => 'Xususiy turar joy uylari',
        ],
        'office' => [
            'foundation' => 'Temirbeton', 'walls' => "G'isht, temirbeton karkas", 'roof_slab' => 'Temirbeton plitalar',
            'roof_cover' => 'Rulonli', 'floor_type' => 'Kafel, laminat', 'windows_doors' => 'Alyumin-plastik vitrajlar',
            'architecture' => 'Zamonaviy', 'landscaping' => 'Obodonlashtirilgan, avtoturargoh', 'surroundings' => 'Turli maqsadli binolar',
        ],
        'warehouse' => [
            'foundation' => 'Temirbeton ustunli', 'walls' => 'Sendvich-panellar, metall karkas', 'roof_slab' => 'Metall fermalar',
            'roof_cover' => 'Profnastil', 'floor_type' => 'Beton', 'windows_doors' => 'Metall darvozalar',
            'architecture' => 'Sanoat binosi', 'landscaping' => 'Asfaltlangan maydon', 'surroundings' => 'Sanoat va ombor binolari',
        ],
    ];

    /** Suggestions offered in the fields (the appraiser may type anything). */
    public const OPTIONS = [
        'function' => ["Ko'p qavatli turar joy", 'Turar joy (hovli)', 'Noturar joy (savdo)', 'Noturar joy (ofis)', 'Ombor', 'Ishlab chiqarish binosi'],
        'ownership_form' => ['Xususiy mulk', 'Davlat mulki', 'Jamoa mulki'],
        'rights' => ['Egalik huquqi', 'Mulk huquqi', 'Ijara huquqi', 'Doimiy foydalanish huquqi'],
        'land_rights' => ['Doimiy foydalanish', 'Meros qilib qoldiriladigan umrbod egalik', 'Ijara', 'Xususiy mulk'],
        'encumbrances' => ['Mavjud emas', 'Ipoteka', 'Garov', 'Taqiq (arest)'],
        'foundation' => ['Beton', 'Lentasimon temirbeton', 'Temirbeton bloklar', 'Monolit temirbeton plita', 'Temirbeton ustunli'],
        'walls' => ["Pishgan g'isht", 'Temirbeton panellar', 'Monolit temirbeton', 'Gazoblok', 'Paxsa', 'Sendvich-panellar'],
        'roof_cover' => ['Rulonli', 'Shifer', 'Metallocherepitsa', 'Profnastil', 'Membranali'],
        'roof_slab' => ['Temirbeton plitalar', 'Monolit temirbeton', "Yog'och to'sinlar", 'Metall fermalar'],
        'floor_type' => ['Beton', 'Laminat', 'Linoleum', 'Kafel', 'Parket', "Yog'och"],
        'windows_doors' => ['Plastik', 'Alyumin-plastik', "Yog'och", 'Metall eshik'],
        'repair' => ["A'lo (yangi ta'mir)", 'Yaxshi', 'Qoniqarli', "Ta'mir talab"],
        'architecture' => ['Oddiy', 'Tipik loyiha', 'Zamonaviy'],
        'landscaping' => ['Obodonlashtirilgan', 'Qisman obodonlashtirilgan', 'Obodonlashtirilmagan'],
        'surroundings' => ['Turar joy binolari', "Ko'p qavatli turar joy binolari", 'Xususiy turar joy uylari', 'Turli maqsadli binolar', 'Sanoat binolari'],
    ];

    /** Reconciliation criteria (national standard No. 6), in the order of the scores. */
    public const CRITERIA = [
        "Tahlil va hisob-kitoblar o'tkazilgan ma'lumotlarning ishonchliligi va yetarliligi",
        "Obyektga xos bo'lgan narx omillarini hisobga olish qobiliyati",
        'Xaridor va sotuvchining haqiqiy niyatlarini aks ettirish qobiliyati',
        'Hisoblanayotgan qiymat turiga muvofiqligi',
    ];

    private const PURPOSES = [
        'collateral' => "garovga qo'yish uchun", 'sale' => 'oldi-sotdi uchun', 'notary' => 'notarial harakatlar uchun',
        'court' => 'sud uchun', 'insurance' => "sug'urtalash uchun", 'charter_capital' => 'ustav kapitaliga kiritish uchun',
        'accounting' => 'buxgalteriya hisobi uchun', 'lease' => 'ijaraga berish uchun', 'compensation' => 'kompensatsiya uchun',
        'damage' => 'zararni aniqlash uchun',
    ];

    private const COMMUNICATIONS = [
        'electricity' => "elektr energiya ta'minoti", 'gas' => "gaz ta'minoti", 'water' => "suv ta'minoti",
        'sewerage' => 'kanalizatsiya', 'heating' => "issiqlik ta'minoti", 'hot_water' => "issiq suv ta'minoti",
    ];

    /** Report values: what the appraiser saved, defaults for the rest. */
    public static function values(RealEstate $object): array
    {
        $saved = array_filter((array) $object->report, fn ($v) => $v !== null && $v !== '' && $v !== []);

        return [...self::defaults($object), ...$saved];
    }

    public static function defaults(RealEstate $object): array
    {
        $residential = $object->kind === RealEstateKind::Residential;
        $applied = array_map(fn ($a) => $a->value, $object->approachList());
        $pledgee = $object->clients->firstWhere('pivot.role', PersonRole::Pledgee->value);

        return [
            'script' => Setting::get('report_script') === ReportScript::CYRILLIC ? ReportScript::CYRILLIC : ReportScript::LATIN,
            'report_date' => ($object->completed_at ?? now())->toDateString(),
            'letter_number' => $object->contract_number,
            'value_type' => 'Bozor qiymati',
            'currency_text' => "O'zbek so'mi, UZS",
            'use_period' => '12 oy',
            'report_format' => "Elektron ko'rinishda",
            'intended_users' => $pledgee?->title ?? 'Mavjud emas',
            'deadline' => 'Barcha hujjatlar taqdim etilgandan keyin 3 ish kuni',
            'initial_info' => "Kadastr hujjatlari to'g'risida to'liq ma'lumot; mulk egasining shaxsini tasdiqlovchi hujjat.",
            'special_assumptions' => 'Mavjud emas',
            'limiting_conditions' => 'Mavjud emas',
            'use_restrictions' => 'Baholash hisobotini tarqatish va chop qilish taqiqlanadi (buyurtmachi bundan mustasno). Hisobotdan baholash xulosasida belgilangan maqsaddagina foydalanish mumkin.',

            'function' => $residential ? (($object->floors_total ?? 0) > 1 ? "Ko'p qavatli turar joy" : 'Turar joy') : 'Noturar joy',
            'ownership_form' => 'Xususiy mulk',
            'rights' => 'Egalik huquqi',
            'rights_basis' => null,
            'land_rights' => 'Doimiy foydalanish',
            'encumbrances' => 'Mavjud emas',
            'ecology' => "Ma'lumot topilmadi",
            'development' => "Ma'lumot berilmagan",

            'land_category' => $residential ? 'Turar joy binosi' : 'Noturar joy binosi',
            'geology' => "Ma'lumot yo'q",
            'hydrogeology' => "Ma'lumot yo'q",
            'topography' => "Ma'lumot yo'q",
            'land_improvements' => "Taqdim qilingan hujjatlarda ma'lumot mavjud emas",
            'land_hbu' => 'Joriy foydalanish (yaxshilashlar bilan)',

            'building_volume' => null,
            'completion' => 100,
            'foundation' => 'Beton',
            'walls' => $object->wall_material ?: null,
            'roof_cover' => 'Rulonli',
            'roof_slab' => 'Temirbeton',
            'floor_type' => 'Beton',
            'windows_doors' => 'Plastik',
            'repair' => $object->condition ?: null,
            'architecture' => 'Oddiy',
            'landscaping' => 'Obodonlashtirilgan',
            'surroundings' => $residential ? 'Turar joy binolari' : 'Turli maqsadli binolar',
            'convertibility' => "Baholash obyektidan foydalanishning joriy turi eng maqbul deb e'tirof etildi.",

            'reason_comparative' => in_array('comparative', $applied, true)
                ? "Baholash obyekti segmentida baholash sanasiga yaqin sanadagi sotuv takliflari to'g'risida yetarli ma'lumot mavjud. 10-son MBS talablariga muvofiq kamida uchta o'xshash analog tanlab olindi, shu sababli sotuvlarni taqqoslash usuli qo'llanildi."
                : "Baholash obyektiga o'xshash obyektlarning sotuvlari to'g'risida yetarli va ishonchli ma'lumot mavjud emasligi sababli qiyosiy yondashuv qo'llanilmadi.",
            'reason_cost' => in_array('cost', $applied, true)
                ? "Obyektni tiklash (almashtirish) xarajatlari va eskirishi to'g'risida yetarli ma'lumot mavjud bo'lganligi sababli xarajat yondashuvi qo'llanildi."
                : "Xarajat yondashuvi obyektning bozor qiymatini aniq ko'rsata olmaydi, chunki xaridor obyekt uchun uni qayta qurish xarajatlaridan ko'p yoki kam to'lashi mumkin; shuningdek loyiha-smeta hujjatlari taqdim etilmagan. Shu sababli xarajat yondashuvidan voz kechildi.",
            'reason_income' => in_array('income', $applied, true)
                ? "Obyekt ijaraga berilishi va barqaror daromad keltirishi mumkinligi hamda ijara stavkalari to'g'risida ishonchli ma'lumot mavjudligi sababli daromad yondashuvi qo'llanildi."
                : "Baholash davrida obyekt joylashgan hududda obyekt mansub bo'lgan bozor segmentiga xos ijara stavkalari to'g'risida ishonchli ma'lumot mavjud emasligi sababli daromad yondashuvi qo'llanilmadi.",
            'hbu_conclusion' => 'Obyektning eng samarali foydalanilishi iqtisodiy nuqtai nazardan joriy faoliyat turi hisoblanadi.',

            'weighting' => 'count',
            'scores' => collect(self::CRITERIA)->keys()->mapWithKeys(fn ($i) => [$i => collect(ValuationApproach::cases())
                ->mapWithKeys(fn ($a) => [$a->value => in_array($a->value, $applied, true) ? 1 : 0])->all()])->all(),
            'attachments' => [],
            'company_documents' => true,
            'include_macro' => true,
            'include_methodology' => true,
        ];
    }

    /**
     * What the report still lacks: label → done.
     *
     * @return array<string, bool>
     */
    public static function checklist(RealEstate $object): array
    {
        $object->loadMissing(['clients', 'appraiser', 'analogs', 'attachments']);
        $applied = $object->approachList();
        $included = $object->analogs->filter(fn (Analog $a) => $a->included && $a->isUsable())->count();

        return [
            __('Owner or customer is linked') => $object->clients->isNotEmpty(),
            __('Appraiser with a qualification certificate') => filled($object->appraiser?->certificate_number),
            __('Valuation and inspection dates') => $object->valuation_date !== null && $object->inspection_date !== null,
            __('Total area and cadastral number') => $object->total_area > 0 && filled($object->cadastral_number),
            __('At least three analogues for the comparative approach') => ! in_array(ValuationApproach::Comparative, $applied, true) || $included >= 3,
            __('Values of the applied approaches') => $applied !== [] && collect($applied)->every(fn (ValuationApproach $a) => $object->{$a->column()} !== null),
            __('Market value') => $object->market_value !== null,
            __('Report number (given when the valuation is completed)') => filled($object->report_number),
            __('Photos of the object') => $object->attachments->contains(fn (Attachment $a) => $a->isImage()),
            __('Company details (TIN, bank account)') => filled(Setting::get('inn')) && filled(Setting::get('bank_account')),
        ];
    }

    /**
     * Steps of the preparation page and whether each is complete.
     *
     * @return array<string, bool>
     */
    public static function steps(RealEstate $object): array
    {
        $object->loadMissing(['clients', 'appraiser', 'analogs', 'attachments']);
        $applied = $object->approachList();
        $included = $object->analogs->filter(fn (Analog $a) => $a->included && $a->isUsable())->count();

        return [
            'documents' => $object->clients->isNotEmpty() && filled($object->cadastral_number),
            'object' => $object->total_area > 0 && filled($object->address) && $object->region_id !== null,
            'inspection' => $object->inspection_date !== null && $object->attachments->contains(fn (Attachment $a) => $a->isImage()),
            'analogs' => ! in_array(ValuationApproach::Comparative, $applied, true) || ($included >= 3 && $object->value_comparative !== null),
            'approaches' => $applied !== [] && collect($applied)->every(fn (ValuationApproach $a) => $object->{$a->column()} !== null) && $object->market_value !== null,
            'report' => filled($object->appraiser?->certificate_number) && filled(Setting::get('inn')) && filled(Setting::get('bank_account')),
        ];
    }

    /** The dollar rate on the valuation date and the weighting for the comparative approach. */
    public static function comparativeOptions(RealEstate $object, bool $download = false): array
    {
        return [
            'usd_rate' => app(CurrencyService::class)->rateOn('USD', $object->valuation_date ?? now(), $download),
            'weighting' => ((array) $object->report)['weighting'] ?? 'count',
        ];
    }

    /** Images attached to the object, each marked as a photo, a document or left out. */
    public static function attachmentRoles(RealEstate $object, array $values): Collection
    {
        $saved = (array) ($values['attachments'] ?? []);

        return $object->attachments->filter->isImage()->sortBy('id')->values()->map(function (Attachment $file) use ($saved) {
            $guess = preg_match('/pasport|passport|паспорт|kadastr|кадастр|guvohnoma|гувоҳнома|ko.?chirma|кўчирма|hujjat|ҳужжат|document|scan|скан|shartnoma|шартнома|id.?card/iu', $file->name) ? 'document' : 'photo';
            $file->setAttribute('report_role', in_array($saved[$file->id] ?? null, ['photo', 'document', 'skip'], true) ? $saved[$file->id] : $guess);

            return $file;
        });
    }

    /**
     * Everything the printed report shows.
     *
     * @return array<string, mixed>
     */
    public static function build(RealEstate $object, ?string $script = null): array
    {
        $object->loadMissing(['region', 'district', 'appraiser', 'clients', 'attachments', 'analogs']);
        $f = self::values($object);
        $s = ReportScript::make($script ?? $f['script']);
        $nameColumn = $s->isCyrillic() ? 'name_oz' : 'name_uz';
        $currency = app(CurrencyService::class);

        $owner = $object->clients->firstWhere('pivot.role', PersonRole::Owner->value) ?? $object->clients->first();
        $customer = $object->clients->firstWhere('pivot.role', PersonRole::Customer->value) ?? $owner;

        $region = $object->region?->getAttribute($nameColumn) ?? $object->region?->name_uz;
        $district = $object->district?->getAttribute($nameColumn) ?? $object->district?->name_uz;
        $location = collect([$region, $district, $object->address])->filter()->implode(', ');

        $valuationDate = $object->valuation_date ?? now();
        $rates = collect(['USD', 'EUR', 'RUB'])->mapWithKeys(fn ($code) => [$code => $currency->rateOn($code, $valuationDate, false)]);

        // Comparative approach.
        $options = ['usd_rate' => $rates['USD'], 'weighting' => $f['weighting']];
        $analogs = $object->analogs->filter(fn (Analog $a) => $a->included && $a->isUsable())->values();
        $comparative = ComparativeCalculator::calculate((float) $object->total_area, $analogs, $options);
        $usable = $object->analogs->filter->isUsable();

        // Reconciliation of the approaches.
        $applied = $object->approachList();
        $scores = [];
        foreach (ValuationApproach::cases() as $approach) {
            $scores[$approach->value] = array_map(
                fn ($i) => in_array($approach, $applied, true) ? max(0, (float) ($f['scores'][$i][$approach->value] ?? 0)) : 0.0,
                array_keys(self::CRITERIA)
            );
        }
        $totalScore = array_sum(array_map('array_sum', $scores));
        $weights = [];
        $reconciled = 0.0;
        foreach (ValuationApproach::cases() as $approach) {
            $value = $object->{$approach->column()};
            $weights[$approach->value] = $totalScore > 0 && $value !== null ? array_sum($scores[$approach->value]) / $totalScore : 0.0;
            $reconciled += $weights[$approach->value] * (float) $value;
        }
        $weightSum = array_sum($weights);
        if ($weightSum > 0 && abs($weightSum - 1) > 0.0001) {
            // Approaches with scores but no value are left out; the rest are scaled to 100 %.
            $weights = array_map(fn ($w) => $w / $weightSum, $weights);
            $reconciled /= $weightSum;
        }
        $final = $object->market_value !== null ? (float) $object->market_value : ($reconciled > 0 ? round($reconciled, -3) : null);

        $features = array_map(fn ($f) => $f->value, $object->featureList());
        $communications = array_values(array_intersect_key(self::COMMUNICATIONS, array_flip($features)));

        $attachments = self::attachmentRoles($object, $f);
        $company = self::company();

        // Title phrase: the place and the type are report text, the owner's name stays as typed.
        $title = [
            ['t', ($location ?: '—').' manzilida joylashgan '],
            ...($owner ? [['raw', $owner->title], ['t', self::dative($owner->title).' qarashli ']] : []),
            ['t', ($object->kind === RealEstateKind::Residential ? 'turar joy' : 'noturar joy').'ni '.(isset(self::PURPOSES[$object->purpose]) ? self::PURPOSES[$object->purpose].' ' : '')."bozor qiymatini aniqlash to'g'risida"],
        ];

        return [
            's' => $s,
            'f' => $f,
            'item' => $object,
            'owner' => $owner,
            'customer' => $customer,
            'company' => $company,
            'appraiser' => $object->appraiser,
            'location' => $location,
            'title' => $title,
            'purposePhrase' => self::PURPOSES[$object->purpose] ?? '',
            'valuationDate' => $valuationDate,
            'reportDate' => Carbon::parse($f['report_date']),
            'rates' => $rates,
            'analogs' => $analogs,
            'usableAnalogs' => $usable,
            'comparative' => $comparative,
            'grid' => self::grid($object, $f, $analogs, $comparative, $rates['USD'], $location, $communications),
            'commentary' => self::commentary($analogs, $comparative, $f),
            'market' => self::market($usable, $rates['USD']),
            'applied' => $applied,
            'scores' => $scores,
            'weights' => $weights,
            'reconciled' => $reconciled,
            'final' => $final,
            'finalWords' => $final !== null ? NumberToWords::uz((int) round($final)) : null,
            'communications' => $communications,
            'photos' => $attachments->where('report_role', 'photo')->values(),
            'documents' => $attachments->where('report_role', 'document')->values(),
            'companyDocuments' => $f['company_documents'] ? self::companyDocuments() : [],
        ];
    }

    /** Company details printed in the header and the task table. */
    public static function company(): array
    {
        $keys = ['name', 'legal_name', 'legal_name_ru', 'director', 'license', 'inn', 'oked', 'phone', 'email', 'address', 'bank_name', 'bank_account', 'bank_mfo', 'membership', 'report_city'];

        return collect($keys)->mapWithKeys(fn ($key) => [$key => Setting::get($key)])->all();
    }

    /** @return list<array{name: string, path: string}> */
    public static function companyDocuments(): array
    {
        $documents = json_decode((string) Setting::get('report_documents', '[]'), true);

        return array_values(array_filter(is_array($documents) ? $documents : [], fn ($d) => is_array($d) && isset($d['path'], $d['name'])));
    }

    /** Uzbek dative suffix for a name: "Aliyev" → "ga", "Tursunbek" → "ka". */
    public static function dative(string $name): string
    {
        $last = mb_strtolower(mb_substr(trim($name), -1));

        return match ($last) {
            'k' => 'ka',
            'q' => 'qa',
            default => 'ga',
        };
    }

    /**
     * Rows of the adjustment grid: the element of comparison, what the object
     * and each analogue have, and the adjustment applied (if the element can
     * be adjusted).
     *
     * @param  Collection<int, Analog>  $analogs
     * @return list<array{title: string, key: ?string, subject: string, analogs: array<int, string>}>
     */
    private static function grid(RealEstate $object, array $f, Collection $analogs, array $result, ?float $usdRate, string $location, array $communications): array
    {
        $same = fn (string $value) => $analogs->mapWithKeys(fn (Analog $a) => [$a->id => $value])->all();
        $of = fn (callable $value) => $analogs->mapWithKeys(fn (Analog $a) => [$a->id => (string) ($value($a) ?? '—')])->all();
        $floor = fn ($floor, $total) => $floor ? $floor.($total ? ' / '.$total : '') : '—';
        $date = fn ($d) => $d ? Carbon::parse($d)->format('d.m.Y') : '—';
        $rate = fn ($r) => $r ? number_format((float) $r, 2, ',', ' ') : '—';

        $rows = [
            ['title' => 'Beriladigan mulkiy huquqlar', 'key' => 'rights', 'subject' => $f['rights'], 'analogs' => $same($f['rights'])],
            ['title' => "Ijara shartnomasi bo'yicha yuklamalar", 'key' => null, 'subject' => "Ma'lumot mavjud emas", 'analogs' => $same("Ma'lumot mavjud emas")],
            ['title' => "Servitutlar va ijtimoiy qo'shimcha yuklamalar", 'key' => null, 'subject' => $f['encumbrances'], 'analogs' => $same('Mavjud emas')],
            ['title' => 'Yer uchastkasiga huquqlar', 'key' => null, 'subject' => $f['land_rights'], 'analogs' => $same($f['land_rights'])],
            ['title' => 'Moliyalashtirish shartlari', 'key' => 'financing', 'subject' => "Oldindan to'lov", 'analogs' => $same("Oldindan to'lov")],
            ['title' => "Haq to'lash shakli", 'key' => null, 'subject' => "Naqd yoki pul ko'chirish", 'analogs' => $same("Naqd yoki pul ko'chirish")],
            ['title' => 'Sotuv shartlari (savdolashish)', 'key' => 'bargain', 'subject' => 'Bitim narxi', 'analogs' => $same('Taklif narxi')],
            ['title' => 'Xariddan keyingi xarajatlar', 'key' => null, 'subject' => 'Mavjud emas', 'analogs' => $same('Mavjud emas')],
            ['title' => 'Bozor shartlari (sana, USD kursi)', 'key' => 'market',
                'subject' => $date($object->valuation_date).' · '.$rate($usdRate),
                'analogs' => $of(fn (Analog $a) => $date($a->listed_at ?? $a->created_at).($a->currency === 'USD' ? ' · '.$rate($a->usd_rate) : ''))],
            ['title' => 'Joylashgan yeri', 'key' => 'location', 'subject' => $location, 'analogs' => $of(fn (Analog $a) => $a->location)],
            ['title' => 'Umumiy maydoni, m²', 'key' => 'area', 'subject' => number_format((float) $object->total_area, 2, ',', ' '), 'analogs' => $of(fn (Analog $a) => number_format((float) $a->area, 2, ',', ' '))],
            ['title' => 'Qavati / qavatlar soni', 'key' => 'floor', 'subject' => $floor($object->floor, $object->floors_total), 'analogs' => $of(fn (Analog $a) => $floor($a->floor, $a->floors_total))],
            ['title' => 'Qurilish materiallari (devorlar)', 'key' => 'material', 'subject' => (string) ($f['walls'] ?? '—'), 'analogs' => $of(fn (Analog $a) => $a->wall_material)],
            ['title' => 'Arxitekturaviy uslub', 'key' => null, 'subject' => $f['architecture'], 'analogs' => $same($f['architecture'])],
            ['title' => "Bino holati (ta'mir)", 'key' => 'condition', 'subject' => (string) ($f['repair'] ?? '—'), 'analogs' => $of(fn (Analog $a) => $a->condition)],
            ['title' => 'Funksional foydaliligi', 'key' => null, 'subject' => $f['function'], 'analogs' => $same($f['function'])],
            ['title' => 'Qurilish tugallanganligi', 'key' => null, 'subject' => $f['completion'].' %', 'analogs' => $same('100 %')],
            ['title' => 'Obodonlashtirilganligi', 'key' => null, 'subject' => $f['landscaping'], 'analogs' => $same($f['landscaping'])],
            ['title' => 'Qulayliklari (muhandislik kommunikatsiyalari)', 'key' => 'amenities', 'subject' => $communications ? implode(', ', $communications) : '—', 'analogs' => $same('Markazlashgan')],
            ['title' => 'Uchastka ekologiyasi', 'key' => null, 'subject' => "Ma'lumot mavjud emas", 'analogs' => $same("Ma'lumot mavjud emas")],
            ['title' => "Daromadga ta'sir qiluvchi tavsiflar", 'key' => null, 'subject' => "Ma'lumot mavjud emas", 'analogs' => $same("Ma'lumot mavjud emas")],
            ['title' => 'Eng samarali foydalanishga muvofiqligi', 'key' => null, 'subject' => 'Joriy foydalanish samarali', 'analogs' => $same('Joriy foydalanish samarali')],
            ['title' => "Ko'chmas mulk hisoblanmaydigan komponentlar", 'key' => null, 'subject' => 'Mavjud emas', 'analogs' => $same('Mavjud emas')],
        ];

        $hasOther = collect($result['rows'])->contains(fn ($row) => abs($row['steps']['other']['percent'] ?? 0) >= 0.005);
        if ($hasOther) {
            $rows[] = ['title' => 'Boshqa farqlar', 'key' => 'other', 'subject' => '—', 'analogs' => $of(fn (Analog $a) => $a->note)];
        }

        return $rows;
    }

    /**
     * Explanation of every adjustment ("Sharh"), as the national standard
     * requires the report to justify them.
     *
     * @param  Collection<int, Analog>  $analogs
     * @return list<array{title: string, text: string}>
     */
    private static function commentary(Collection $analogs, array $result, array $f): array
    {
        if ($analogs->isEmpty()) {
            return [];
        }

        $number = fn ($v, $d = 0) => number_format((float) $v, $d, ',', ' ');
        $no = $analogs->values()->mapWithKeys(fn (Analog $a, $i) => [$a->id => $i + 1]);
        $list = function (string $key) use ($analogs, $result, $number, $no) {
            return $analogs->map(fn (Analog $a) => '№'.$no[$a->id].' — '.$number($result['rows'][$a->id]['steps'][$key]['percent'], 2).' % ('.$number($result['rows'][$a->id]['steps'][$key]['value']).' so\'m/m²)')->implode('; ');
        };
        $applied = fn (string $key) => $analogs->contains(fn (Analog $a) => abs($result['rows'][$a->id]['steps'][$key]['percent'] ?? 0) >= 0.005);

        $texts = [
            'rights' => ['Beriladigan mulkiy huquqlar', "Baholash obyekti va analoglar bo'yicha beriladigan huquqlar bir xil ({$f['rights']}) bo'lganligi sababli tuzatish kiritilmadi.", 'Beriladigan mulkiy huquqlardagi farq uchun tuzatish kiritildi: '],
            'financing' => ['Moliyalashtirish shartlari', "Barcha analoglar bo'yicha to'lov oldindan, bozor shartlarida amalga oshirilishi nazarda tutilganligi sababli tuzatish kiritilmadi.", 'Moliyalashtirish shartlaridagi farq uchun tuzatish kiritildi: '],
            'bargain' => ['Sotuv shartlari', "Analoglar narxi taklif narxi bo'lganligi sababli sotuvchi va xaridor o'rtasidagi savdolashuvni hisobga olgan holda tuzatish kiritilmadi.", "Analoglar narxi taklif narxi bo'lib, bitim narxiga o'tish uchun savdolashuvga tuzatish kiritildi: "],
            'market' => ['Bozor shartlari', "Analoglar baholash sanasiga yaqin sanada taklif qilinganligi va narxlar so'mda ko'rsatilganligi sababli tuzatish kiritilmadi.", "Taklif sanasi va baholash sanasidagi valyuta kurslari (O'zbekiston Respublikasi Markaziy banki) farqi hisobga olinib tuzatish kiritildi: "],
            'location' => ['Joylashgan yeri', "Analoglar baholash obyekti bilan bir hududda, o'xshash joylashuvda bo'lganligi sababli tuzatish kiritilmadi.", "Joylashuvdagi farqlar (manzil nufuzi, markaziy yo'lga yaqinligi, tuman markazigacha masofa) hisobga olinib tuzatish kiritildi: "],
            'area' => ['Umumiy maydoni (katta-kichikligi)', "Analoglar maydoni baholash obyekti maydoniga yaqin bo'lganligi sababli tuzatish kiritilmadi.", "Maydoni katta obyektlarning 1 m² narxi past bo'lishi hisobga olinib, maydondagi farq uchun tuzatish kiritildi: "],
            'floor' => ['Qavati', "Analoglar va baholash obyekti qavati o'xshash bo'lganligi sababli tuzatish kiritilmadi.", "Birinchi va oxirgi qavatlar narxi o'rta qavatlardan past bo'lishi hisobga olinib tuzatish kiritildi: "],
            'material' => ['Qurilish materiallari', "Analoglar devor materiali baholash obyektiniki bilan o'xshash bo'lganligi sababli tuzatish kiritilmadi.", 'Devor materialidagi farq uchun tuzatish kiritildi: '],
            'condition' => ["Bino holati (ta'mir)", "Analoglar ta'mir holati baholash obyektiniki bilan o'xshash bo'lganligi sababli tuzatish kiritilmadi.", "Ta'mir holatidagi farq KMK 2.01.16-97 bo'yicha eskirish darajalari asosida hisobga olindi ((100 − obyekt eskirishi) / (100 − analog eskirishi)): "],
            'amenities' => ['Qulayliklari', "Analoglar va baholash obyektida muhandislik kommunikatsiyalari bir xil bo'lganligi sababli tuzatish kiritilmadi.", 'Muhandislik kommunikatsiyalari va qulayliklardagi farq uchun tuzatish kiritildi: '],
            'other' => ['Boshqa farqlar', '', 'Boshqa farqlar uchun tuzatish kiritildi: '],
        ];

        $out = [];
        foreach ($texts as $key => [$title, $none, $some]) {
            if ($applied($key)) {
                $out[] = ['title' => $title, 'text' => $some.$list($key).'.'];
            } elseif ($none !== '') {
                $out[] = ['title' => $title, 'text' => $none];
            }
        }

        $counts = $analogs->map(fn (Analog $a) => '№'.$no[$a->id].' — '.$result['rows'][$a->id]['count'])->implode('; ');
        $weights = $analogs->map(fn (Analog $a) => '№'.$no[$a->id].' — '.$number($result['rows'][$a->id]['weight'] * 100, 2).' %')->implode('; ');
        $out[] = ['title' => 'Vaznlar', 'text' => $result['weighting'] === 'count'
            ? "Kiritilgan tuzatishlar soni (savdolashishdan tashqari): {$counts}. Vaznlar tuzatishlar soniga teskari proporsional aniqlandi — kamroq tuzatish kiritilgan analog baholash obyektiga ko'proq o'xshash: {$weights}."
            : "Vaznlar kiritilgan tuzatishlarning umumiy miqdoriga (mutlaq qiymatlar yig'indisiga) teskari proporsional aniqlandi — kichikroq tuzatish kiritilgan analog baholash obyektiga ko'proq o'xshash: {$weights}."];

        if ($result['variation'] !== null) {
            $out[] = ['title' => 'Natijalarning bir xilligi', 'text' => 'Tuzatishlardan keyingi 1 m² narxlarining variatsiya koeffitsiyenti '.$number($result['variation'], 1).' % ni tashkil etdi'.($result['variation'] <= 33 ? " (33 % dan kam) — analoglar to'plami bir xil va natija ishonchli." : ' (33 % dan ko\'p) — analoglar o\'rtasida sezilarli farq mavjud.')];
        }

        return $out;
    }

    /**
     * Market of the object's segment, from the offers found: how many, the
     * range and the median price per m².
     *
     * @param  Collection<int, Analog>  $usable
     */
    private static function market(Collection $usable, ?float $usdRate): ?array
    {
        if ($usable->isEmpty()) {
            return null;
        }
        $prices = $usable->map->pricePerSquareMeter()->sort()->values();

        return [
            'count' => $prices->count(),
            'min' => $prices->first(),
            'max' => $prices->last(),
            'median' => $prices->median(),
            'usd' => $usdRate,
            'bars' => $usable->values()->map(fn (Analog $a, $i) => ['label' => '№'.($i + 1), 'value' => $a->pricePerSquareMeter(), 'included' => $a->included])->all(),
        ];
    }

    /**
     * Owner, customer or pledgee details printed in the task table: the label
     * is report text, the value is shown as typed.
     *
     * @return list<array{0: ?string, 1: string}>
     */
    public static function personLines(?Client $client): array
    {
        if (! $client) {
            return [];
        }
        if ($client->isLegal()) {
            $lines = [
                [null, $client->company_name],
                ['Manzili', $client->address],
                ['STIR', $client->inn],
                ['Rahbari', $client->director],
                ['H/r', $client->bank_account ? $client->bank_account.($client->bank_name ? ', '.$client->bank_name : '').($client->bank_mfo ? ', MFO '.$client->bank_mfo : '') : null],
            ];
        } else {
            $passport = trim($client->passport_series.' '.$client->passport_number);
            $lines = [
                [null, $client->title],
                ['Manzili', $client->address],
                ['JShShIR', $client->pinfl],
                ['Pasport', $passport !== '' ? $passport.($client->passport_issued_by ? ', '.$client->passport_issued_by : '').($client->passport_issued_at ? ', '.$client->passport_issued_at->format('d.m.Y') : '') : null],
            ];
        }

        return array_values(array_filter($lines, fn ($line) => filled($line[1])));
    }
}
