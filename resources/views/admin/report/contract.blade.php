@php
    /** @var \App\Reports\ReportScript $s */
    $t = fn (?string $text) => $s->t($text);
    $money = fn ($value) => \App\Reports\ContractPack::money($value === null ? null : (float) $value);
    $date = fn ($value) => $value ? $value->format('d.m.Y').' '.$t('yil') : '—';
    $companyName = $company['legal_name'] ?: $company['name'];
    $vatNote = $fee['rate'] > 0 ? $t('QQS bilan') : $t('QQS siz');
    $requisites = collect([
        $company['address'],
        $company['phone'] ? $t('Tel').': '.$company['phone'] : null,
        $company['email'] ? 'E-mail: '.$company['email'] : null,
        $company['bank_account'] ? $t('H/r').': '.$company['bank_account'] : null,
        $company['bank_name'],
        $company['bank_mfo'] ? $t('MFO').': '.$company['bank_mfo'] : null,
        $company['inn'] ? $t('STIR').': '.$company['inn'] : null,
        $company['oked'] ? $t('XXTUT').': '.$company['oked'] : null,
    ])->filter();
    $customerName = $customer?->title ?? '—';
    $passport = $customer && ! $customer->isLegal() ? trim($customer->passport_series.' '.$customer->passport_number) : '';
    $city = $t($company['report_city'] ?: 'Toshkent');
    $verify = $item->verifyUrl();
@endphp
<!DOCTYPE html>
<html lang="{{ $s->isCyrillic() ? 'uz-Cyrl' : 'uz-Latn' }}" data-preparing="{{ $toolbar['preparing'] ?? '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $t('Shartnoma') }} № {{ $contractNumber }} — {{ $customerName }}</title>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/contract.css') }}">
    <script src="{{ asset('js/report.js') }}"></script>
    <script src="{{ asset('js/vendor/qrcode.js') }}"></script>
    <script src="{{ asset('js/vendor/paged.polyfill.min.js') }}"></script>
</head>
<body data-toolbar='@json($toolbar)' @class(['with-qr' => $qr])>
@if ($qr)<div class="qr-run" data-qr="{{ $verify }}"></div>@endif

{{-- A signature: the role, a line (or the QR code of the document) and the short name. --}}
@php
    $sign = function (string $role, string $name, bool $stamp = false) use ($t, $qr, $verify) {
        $line = $qr ? '<span class="sign-qr" data-qr="'.e($verify).'"></span>' : '<span class="sign-line"></span>';

        return '<div class="sign"><span class="sign-role">'.e($t($role)).'</span>'.$line.'<span class="sign-name">'.e($name).'</span>'
            .($stamp && ! $qr ? '<span class="stamp">'.e($t("M.O'.")).'</span>' : '').'</div>';
    };
    $customerBlock = function () use ($customer, $customerName, $passport, $t) {
        if (! $customer) {
            return '—';
        }
        $lines = [e($customerName)];
        if ($customer->isLegal()) {
            foreach (['Manzil' => $customer->address, 'STIR' => $customer->inn, 'H/r' => $customer->bank_account, 'Bank' => $customer->bank_name, 'MFO' => $customer->bank_mfo, 'Telefon' => $customer->phone] as $label => $value) {
                if (filled($value)) {
                    $lines[] = e($t($label)).': '.e($value);
                }
            }
        } else {
            foreach (['Manzil' => $customer->address, 'Telefon' => $customer->phone, 'Pasport' => $passport, 'Kim tomonidan berilgan' => $customer->passport_issued_by, 'Berilgan sana' => $customer->passport_issued_at?->format('d.m.Y'), 'JShShIR' => $customer->pinfl] as $label => $value) {
                if (filled($value)) {
                    $lines[] = e($t($label)).': '.e($value);
                }
            }
        }

        return implode('<br>', $lines);
    };
@endphp

{{-- ============================== Contract ============================== --}}
@if (in_array('contract', $documents, true))
<section class="doc">
    <div class="doc-brand"><span>{{ $companyName }}</span><span>{{ $t('Baholash xizmatlari') }}</span></div>
    <h1>{{ $t("Baholash obyektini baholash bo'yicha") }}<br><span class="spaced">{{ $t('SHARTNOMA') }}</span> № {{ $contractNumber }}</h1>
    <div class="place-date"><span>{{ $city }}</span><span>{{ $date($contractDate) }}</span></div>

    <p class="parties">
        <strong>{{ $companyName }}</strong> ({{ $t("keyingi o'rinlarda Bajaruvchi deb yuritiladi") }}) {{ $t('nomidan jamiyat Nizomi asosida ish yurituvchi direktor') }} {{ $company['director'] ?: '—' }} {{ $t('bir tomondan, va') }}
        <strong>{{ $customerName }}</strong>@if ($representative) ({{ $t('vakili') }}: {{ $representative->title }})@endif
        ({{ $t("keyingi o'rinlarda Buyurtmachi deb yuritiladi") }}) {{ $t('ikkinchi tomondan, quyidagilar haqida ushbu shartnomani tuzdilar:') }}
    </p>

    <div class="terms">{{ $terms }}</div>

    <h3>10. {{ $t('Taraflarning yuridik manzillari va bank rekvizitlari') }}</h3>
    <div class="parties-grid">
        <div>
            <h4>{{ $t('BAJARUVCHI') }}</h4>
            <p><strong>{{ $companyName }}</strong><br>{!! $requisites->map(fn ($line) => e($t($line)))->implode('<br>') !!}</p>
            {!! $sign('Direktor', $directorShort, true) !!}
        </div>
        <div>
            <h4>{{ $t('BUYURTMACHI') }}</h4>
            <p>{!! $customerBlock() !!}</p>
            {!! $sign('Buyurtmachi', $customerShort) !!}
        </div>
    </div>
</section>
@endif

{{-- ============================== Act of acceptance ============================== --}}
@if (in_array('act', $documents, true))
<section class="doc">
    <p class="doc-ref">{{ $date($contractDate) }} {{ $t('dagi') }} № {{ $contractNumber }} {{ $t('sonli shartnoma ijrosi yuzasidan') }}</p>
    <h1>{{ $t('Ishni topshirish-qabul qilish') }}<br><span class="spaced">{{ $t('DALOLATNOMASI') }}</span></h1>
    <div class="place-date"><span>{{ $city }}</span><span>{{ $date($reportDate) }}</span></div>

    <p>
        {{ $t('Biz, quyida imzo chekuvchilar, “Bajaruvchi”') }} <strong>{{ $companyName }}</strong> {{ $t('direktori') }} {{ $company['director'] ?: '—' }} {{ $t('va “Buyurtmachi”') }}
        <strong>{{ $customerName }}</strong>@if ($passport) ({{ $t('pasport') }} {{ $passport }}@if ($customer?->passport_issued_at), {{ $t('berilgan sana') }} {{ $customer->passport_issued_at->format('d.m.Y') }}@endif)@endif
        {{ $t('ushbu dalolatnomani tuzdik.') }}
    </p>
    <p>{{ $t('“Buyurtmachi”ning baholash obyekti bo‘yicha quyidagi ishlari bajarildi:') }}</p>

    <table class="grid">
        <thead>
            <tr>
                <th>{{ $t('Baholash hisobotining raqami va sanasi') }}</th>
                <th>{{ $t('Manzili') }}</th>
                <th>{{ $t('Obyekt nomi') }}</th>
                <th>{{ $t('Baholash summasi') }}</th>
                <th>{{ $t('Baholash maqsadi') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>№ {{ $reportNumber }}<br>{{ $date($reportDate) }}</td>
                <td>{{ $t($location ?: '—') }}</td>
                <td>{{ $t($item->title) }}</td>
                <td class="num">{{ $money($value) }} {{ $t("so'm") }}@if ($valueWords)<br><small>({{ $t($valueWords) }} {{ $t("so'm") }})</small>@endif</td>
                <td>{{ $t($purpose) }}</td>
            </tr>
        </tbody>
    </table>

    <p>{{ $t('Bajarilgan ishni “Buyurtmachi” e’tirozsiz va to‘liq, 100 foiz qabul qildi. Bajarilgan ishlar bo‘yicha taraflarning e’tirozlari yo‘q.') }}</p>
    <p>{{ $t('Dalolatnomaga asosan “Buyurtmachi” qabul qilgan bajarilgan ishlar hajmi') }} ({{ $vatNote }}): <strong>{{ $money($fee['total']) }} ({{ $t($fee['words']) }}) {{ $t("so'm") }}</strong>@if ($fee['rate'] > 0), {{ $t('shu jumladan QQS') }} {{ rtrim(rtrim(number_format($fee['rate'], 2, ',', ''), '0'), ',') }} %: {{ $money($fee['vat']) }} {{ $t("so'm") }}@endif.</p>
    <p>{{ $t('Ushbu dalolatnoma to‘g‘ri tuzilgan va o‘zaro hisob-kitob qilish uchun asos hisoblanadi.') }}</p>

    <div class="signs-row">
        <div><h4>{{ $t('Topshirdi “Bajaruvchi”') }}</h4>{!! $sign('Direktor', $directorShort, true) !!}</div>
        <div><h4>{{ $t('Qabul qildi “Buyurtmachi”') }}</h4>{!! $sign('Buyurtmachi', $customerShort) !!}</div>
    </div>
</section>
@endif

{{-- ============================== Invoice ============================== --}}
@if (in_array('invoice', $documents, true))
<section class="doc invoice-doc">
    <h1><span class="spaced">{{ $t('HISOB VARAQ-FAKTURA') }}</span></h1>
    <p class="center">№ {{ $contractNumber }} — {{ $date($reportDate) }}<br>{{ $date($contractDate) }} {{ $t('dagi') }} № {{ $contractNumber }} {{ $t('sonli shartnomaga asosan') }}</p>

    <div class="parties-grid boxed">
        <div>
            <h4>{{ $t('BAJARUVCHI') }}</h4>
            <p><strong>{{ $companyName }}</strong><br>{!! $requisites->map(fn ($line) => e($t($line)))->implode('<br>') !!}</p>
        </div>
        <div>
            <h4>{{ $t('BUYURTMACHI') }}</h4>
            <p>{!! $customerBlock() !!}</p>
        </div>
    </div>

    <table class="grid invoice">
        <thead>
            <tr>
                <th rowspan="2" class="c">№</th>
                <th rowspan="2">{{ $t("Tovar (ish, xizmat) nomi") }}</th>
                <th rowspan="2" class="c">{{ $t("O'lchov birligi") }}</th>
                <th rowspan="2" class="c">{{ $t('Miqdori') }}</th>
                <th rowspan="2" class="num">{{ $t('Narxi (QQSsiz)') }}</th>
                <th rowspan="2" class="num">{{ $t('Qiymati (QQSsiz)') }}</th>
                <th colspan="2" class="c">{{ $t('QQS') }}</th>
                <th rowspan="2" class="num">{{ $t("Jami (so'm)") }}</th>
            </tr>
            <tr><th class="c">{{ $t('stavkasi, %') }}</th><th class="num">{{ $t('summasi') }}</th></tr>
        </thead>
        <tbody>
            <tr>
                <td class="c">1</td>
                <td>{{ $t('Baholash xizmati') }} (№ {{ $contractNumber }})</td>
                <td class="c">{{ $t('xizmat') }}</td>
                <td class="c">1</td>
                <td class="num">{{ $money($fee['net']) }}</td>
                <td class="num">{{ $money($fee['net']) }}</td>
                <td class="c">{{ $fee['rate'] > 0 ? rtrim(rtrim(number_format($fee['rate'], 2, ',', ''), '0'), ',') : $t('QQSsiz') }}</td>
                <td class="num">{{ $fee['rate'] > 0 ? $money($fee['vat']) : $t('QQSsiz') }}</td>
                <td class="num">{{ $money($fee['total']) }}</td>
            </tr>
            <tr class="total">
                <td></td><td colspan="4">{{ $t('Jami') }}</td>
                <td class="num">{{ $money($fee['net']) }}</td><td></td>
                <td class="num">{{ $fee['rate'] > 0 ? $money($fee['vat']) : '—' }}</td>
                <td class="num">{{ $money($fee['total']) }}</td>
            </tr>
        </tbody>
    </table>
    <p><strong>{{ $t("Jami to'lashga") }} ({{ $vatNote }}): {{ $money($fee['total']) }} ({{ $t($fee['words']) }}) {{ $t("so'm") }}.</strong></p>

    <div class="signs-row three">
        <div>{!! $sign('Direktor', $directorShort, true) !!}</div>
        <div>{!! $sign('Bosh hisobchi', $company['chief_accountant'] ? \App\Reports\ContractPack::shortPersonName($company['chief_accountant']) : '') !!}</div>
        <div>{!! $sign('Qabul qildi (oluvchining imzosi)', $customerShort) !!}</div>
    </div>
</section>
@endif

{{-- ============================== Assignment ============================== --}}
@if (in_array('assignment', $documents, true))
<section class="doc">
    <p class="doc-ref">{{ $date($contractDate) }} {{ $t('dagi') }} № {{ $contractNumber }} {{ $t('sonli shartnomaga ilova') }}</p>
    <h1>{{ $customerName }}{{ $t(\App\Reports\RealEstateReport::dative($customerName)) }} {{ $t("qarashli mol-mulkni baholash bo'yicha") }}<br><span class="spaced">{{ $t('VAZIFA') }}</span></h1>
    <div class="place-date"><span>{{ $city }}</span><span>{{ $date($contractDate) }}</span></div>

    <table class="facts">
        <tr><td>{{ $t('Baholanayotgan aktivning nomi va tavsifi') }}</td><td>{{ $t($description) }}</td></tr>
        <tr><td>{{ $t("Baholash obyektiga bo'lgan huquqlar") }}</td><td>{{ $t($f['rights'] ?? '—') }}</td></tr>
        <tr><td>{{ $t('Baholash obyektining joylashgan joyi') }}</td><td>{{ $t($location ?: '—') }}</td></tr>
        <tr><td>{{ $t('Buyurtmachi va uning rekvizitlari') }}</td><td>{!! $customerBlock() !!}</td></tr>
        <tr><td>{{ $t('Baholovchi tashkilot va uning rekvizitlari') }}</td><td>{{ $companyName }}<br>{{ $t($requisites->implode(', ')) }}@if ($company['membership'])<br>{{ $t($company['membership']) }}@endif</td></tr>
        <tr><td>{{ $t('Baholovchi') }}</td><td>{{ $item->appraiser?->name ?? '—' }}@if ($item->appraiser?->certificate_number), {{ $t('malaka sertifikati') }} № {{ $item->appraiser->certificate_number }}@endif</td></tr>
        <tr><td>{{ $t('Baholash maqsadi') }}</td><td>{{ $t($purpose) }}. {{ $t("Taraflarning majburiyatlari baholash xizmatini ko'rsatish shartnomasi asosida belgilanadi.") }}</td></tr>
        <tr><td>{{ $t('Baholash natijalaridan foydalanish muddati') }}</td><td>{{ $t($f['use_period'] ?? '—') }}</td></tr>
        <tr><td>{{ $t('Baholash sanasi') }}</td><td>{{ $date($valuationDate) }}</td></tr>
        <tr><td>{{ $t('Baholash maqsadiga muvofiq qiymat turi') }}</td><td>{{ $t($f['value_type'] ?? '—') }}</td></tr>
        <tr><td>{{ $t('Cheklovchi shartlar') }}</td><td>{{ $t($f['limiting_conditions'] ?? '—') }}</td></tr>
        <tr><td>{{ $t("Buyurtmachi taqdim etishi lozim bo'lgan boshlang'ich axborot") }}</td><td>{{ $t($f['initial_info'] ?? '—') }}</td></tr>
        <tr><td>{{ $t('Baholash ishlarini bajarish muddati') }}</td><td>{{ $t($f['deadline'] ?? '—') }}</td></tr>
        <tr><td>{{ $t('Baholovchi asoslanadigan axborot va uning manbalari') }}</td><td>{{ $t("Baholovchi baholash jarayonida barcha axborot va manbalardan foydalanishi hamda ularni tekshirishi mumkin.") }}</td></tr>
        <tr><td>{{ $t('Hisobot shakli') }}</td><td>{{ $t($f['report_format'] ?? '—') }}</td></tr>
        <tr><td>{{ $t("Baholash o'tkazish uchun asos") }}</td><td>{{ $date($contractDate) }} {{ $t('dagi') }} № {{ $contractNumber }} {{ $t('sonli shartnoma') }}</td></tr>
    </table>
    <p class="muted">{{ $t('Taqdim etilgan boshlang‘ich axborot Buyurtmachining imzosi bilan tasdiqlanadi.') }}</p>

    <div class="signs-row">
        <div><h4>{{ $t('Bajaruvchi') }}</h4>{!! $sign('Direktor', $directorShort, true) !!}</div>
        <div><h4>{{ $t('Buyurtmachi') }}</h4>{!! $sign('Buyurtmachi', $customerShort) !!}</div>
    </div>
</section>
@endif
</body>
</html>
