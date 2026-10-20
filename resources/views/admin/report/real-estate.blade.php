@php
    use App\Enums\ValuationApproach;
    use App\Reports\ReportText;
    use Illuminate\Support\Carbon;

    /** @var \App\Reports\ReportScript $s */
    $t = fn (?string $text) => $s->t($text);
    $num = fn ($value, int $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals, ',', ' ');
    $sum = fn ($value) => $value === null ? '—' : number_format((float) $value, 0, ',', ' ').' '.$t("so'm");
    $date = fn ($value) => $value ? Carbon::parse($value)->format('d.m.Y').' '.$t('yil') : '—';
    $pct = fn ($value) => ($value > 0 ? '+' : '').number_format((float) $value, 2, ',', ' ').' %';
    $phrase = fn (array $parts) => collect($parts)->map(fn ($p) => $p[0] === 't' ? $t($p[1]) : $p[1])->implode('');
    $text = fn (string $key) => ReportText::html(ReportText::get($key), $s);
    $cssText = fn (?string $value) => str_replace(['\\', '"', "\n", '<', '>'], ['\\\\', '\"', ' ', '', ''], (string) $value);

    $reportNo = $item->report_number ?: '—';
    $companyName = $company['legal_name'] ?: $company['name'];
    $comparativeApplied = in_array(ValuationApproach::Comparative, $applied, true);
    $hasGrid = $comparativeApplied && $analogs->isNotEmpty();
    $approachShort = ['cost' => 'Kxar', 'income' => 'Kdar', 'comparative' => 'Kqiy'];
    $approachNames = ['cost' => 'Xarajat yondashuvi', 'income' => 'Daromad yondashuvi', 'comparative' => 'Qiyosiy yondashuv'];
    $requisites = collect([
        $company['address'],
        $company['phone'] ? $t('Tel').': '.$company['phone'] : null,
        $company['email'] ? 'E-mail: '.$company['email'] : null,
        $company['bank_account'] ? $t('H/r').': '.$company['bank_account'] : null,
        $company['bank_name'],
        $company['bank_mfo'] ? $t('MFO').': '.$company['bank_mfo'] : null,
        $company['inn'] ? $t('STIR').': '.$company['inn'] : null,
        $company['oked'] ? $t('XXTUT').': '.$company['oked'] : null,
    ])->filter()->implode(', ');
    $appraiserLine = $appraiser ? collect([
        $appraiser->name,
        $appraiser->certificate_number ? $t('malaka sertifikati').' № '.$appraiser->certificate_number : null,
        $appraiser->certificate_date ? $appraiser->certificate_date->format('d.m.Y') : null,
        $appraiser->certificate_issuer ? $t($appraiser->certificate_issuer) : null,
    ])->filter()->implode(', ') : '—';
    $sections = [
        ['s1', 'I', 'Umumlashtiruvchi qism (ilova xat)'],
        ['s2', 'II', "Asosiy atamalar, ta'riflar va standart tamoyillari"],
        ['s3', 'III', 'Baholashga oid vazifa, asosiy faktlar va xulosalar'],
        ['s4', 'IV', "Mamlakat va mintaqadagi umumiy iqtisodiy vaziyat, bozor tahlili"],
        ['s5', 'V', 'Baholash obyektining tavsifi'],
        ['s6', 'VI', 'Eng samarali foydalanish tahlili'],
        ['s7', 'VII', "Baholash yondashuvlari va usullarining tanlanishi va qo'llanilishi"],
        ['s8', 'VIII', 'Natijalarni muvofiqlashtirish va yakuniy qiymat'],
        ['s9', 'IX', "Baholash to'g'risidagi hisobotning sifati to'g'risida bayonot"],
        ['s10', 'X', 'Ilovalar'],
    ];
    $chunks = $analogs->values()->chunk(4);
    $numberOf = $analogs->values()->mapWithKeys(fn ($a, $i) => [$a->id => $i + 1]);
@endphp
<!DOCTYPE html>
<html lang="{{ $s->isCyrillic() ? 'uz-Cyrl' : 'uz-Latn' }}" data-preparing="{{ $toolbar['preparing'] ?? '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $t('Baholash hisoboti') }} № {{ $reportNo }} — {{ $item->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/document-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/report.css') }}">
    <style>
        {{-- $cssText escapes quotes and backslashes and drops < >, so the values cannot leave the CSS string. --}}
        @page {
            @top-left { content: "{!! $cssText($companyName) !!}"; }
            @top-right { content: "{!! $cssText($t('Baholash hisoboti').' № '.$reportNo.' · '.$reportDate->format('d.m.Y')) !!}"; }
        }
    </style>
    <script src="{{ asset('js/report.js') }}"></script>
    <script src="{{ asset('js/vendor/qrcode.js') }}"></script>
    <script src="{{ asset('js/vendor/paged.polyfill.min.js') }}"></script>
</head>
<body data-toolbar='@json($toolbar ?? [])'>

{{-- ============================== Cover (full page) ============================== --}}
@php
    $coverPhoto = $photos->first();
    $perM2 = $final !== null && $item->total_area > 0 ? $final / (float) $item->total_area : null;
    $usdValue = $final !== null && ($rates['USD'] ?? 0) > 0 ? $final / $rates['USD'] : null;
    $words = $finalWords ? mb_strtoupper(mb_substr($finalWords, 0, 1)).mb_substr($finalWords, 1).' so‘m' : null;
    $valueOf = fn (string $pattern) => str_replace(':type', mb_strtolower($t($f['value_type'])), $t($pattern));
    // The mark on the cover: the initials of the company name on a dark square.
    $mark = collect(preg_split('/\s+/u', trim(preg_replace('/[^\p{L}\s]/u', ' ', (string) $company['name']))))
        ->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $contacts = collect([
        $company['phone'] ? $t('Tel').'.: '.$company['phone'] : null,
        $company['inn'] ? $t('STIR').' '.$company['inn'] : null,
    ])->filter()->implode(' · ');
@endphp
<section class="cover">
    <header class="cover-head">
        <div class="cover-brand">
            <svg viewBox="0 0 52 52" aria-hidden="true"><rect width="52" height="52" fill="#1b3a5c"/><text x="26" y="34" text-anchor="middle" font-family="PT Serif, Georgia, serif" font-size="21" font-weight="700" fill="#fff">{{ $mark }}</text></svg>
            <div><b>{{ $companyName }}</b><small>{{ $t('Mol-mulkni baholash xizmatlari') }}</small></div>
        </div>
        <div class="cover-req">
            @if ($company['address']){{ $t($company['address']) }}<br>@endif
            {{ $contacts }}
        </div>
    </header>

    <div class="cover-title">
        <span class="cover-kicker">{{ mb_strtoupper($t('Baholash hisoboti')) }}</span>
        <h1>{{ $t($item->name) }}</h1>
        <p class="cover-sub">{{ $phrase($title) }}</p>
        <div class="cover-meta">
            <span>№ <b>{{ $reportNo }}</b></span>
            <span>{{ $t('Hisobot sanasi') }}: <b>{{ $reportDate->format('d.m.Y') }}</b></span>
            <span>{{ $t('Baholash sanasi') }}: <b>{{ $valuationDate->format('d.m.Y') }}</b></span>
        </div>
    </div>

    @if ($coverPhoto)
        <figure class="cover-photo"><img src="{{ route('admin.attachments.show', $coverPhoto) }}" alt=""></figure>
    @endif

    <table class="cover-facts">
        <tr><td>{{ $t('Manzil') }}</td><td>{{ $t($location) }}</td></tr>
        <tr><td>{{ $t('Kadastr raqami') }}</td><td>{{ $s->v($item->cadastral_number) }}</td></tr>
        <tr><td>{{ $t('Umumiy maydoni') }}</td><td>{{ $num($item->total_area) }} m²</td></tr>
        <tr><td>{{ $t('Buyurtmachi') }}</td><td>{{ $s->v($customer?->title) }}</td></tr>
        <tr><td>{{ $t('Baholash maqsadi') }}</td><td>{{ $item->purpose ? $t($item->purposeLabel()) : '—' }}</td></tr>
        <tr><td>{{ $t('Qiymat turi') }}</td><td>{{ $t($f['value_type']) }}</td></tr>
    </table>

    @unless ($coverPhoto)<div class="cover-spacer"></div>@endunless

    <div class="cover-value">
        <div class="box">
            <span>{{ $valueOf('Baholash obyektining :type') }}</span>
            <strong>{{ $sum($final) }}</strong>
            @if ($words)<em>{{ $t($words) }}</em>@endif
        </div>
        @if ($qr ?? true)
            <div class="cover-qr">
                <div class="qr" data-qr="{{ $item->verifyUrl() }}"></div>
                <span>{{ $t('Tekshirish kodi') }}<b>{{ $item->verify_code }}</b></span>
            </div>
        @endif
    </div>

    <footer class="cover-foot">
        <span>{{ $t($company['report_city'] ?: 'Toshkent') }} · {{ $reportDate->format('Y') }}</span>
        @if ($appraiser)
            <span>{{ $t('Baholovchi') }}: {{ $appraiser->name }}@if ($appraiser->certificate_number), {{ $t('malaka sertifikati') }} № {{ $appraiser->certificate_number }}@endif</span>
        @endif
    </footer>
</section>

{{-- ============================== Executive summary ============================== --}}
<h2 class="section" id="summary">{{ $t('Asosiy xulosalar') }}</h2>
@if ($qr ?? true)
    {{-- Repeated at the foot of every following page. --}}
    <div class="qr-run"><div class="qr-foot">
        <div class="qr" data-qr="{{ $item->verifyUrl() }}"></div>
        <span>{{ $t('Tekshirish kodi') }}<b>{{ $item->verify_code }}</b>{{ preg_replace('#^https?://([^/]+).*$#', '$1', $item->verifyUrl()) }}</span>
    </div></div>
@endif
<p class="summary-lead">{{ $t("Baholash obyekti ko'zdan kechirildi, hujjatlari o'rganildi va baholash milliy standartlariga muvofiq qiymati aniqlandi. To'liq asoslash hisobotning keyingi bo'limlarida keltirilgan.") }}</p>
<div class="summary-value">
    <span>{{ $valuationDate->format('d.m.Y') }} {{ $valueOf('holatiga :type') }}</span>
    <strong>{{ $sum($final) }}</strong>
    @if ($words)<em>{{ $t($words) }}</em>@endif
    <dl>
        <div><dt>{{ $t('1 m² uchun') }}:</dt><dd>{{ $sum($perM2) }}</dd></div>
        @if ($usdValue)<div><dt>{{ $t('AQSh dollarida') }}:</dt><dd>≈ {{ $num($usdValue, 0) }} $</dd></div>@endif
        <div><dt>{{ $t('USD kursi') }}:</dt><dd>{{ $num($rates['USD'] ?? null) }}</dd></div>
    </dl>
</div>

<h3 class="sub">{{ $t("Baholash to'g'risidagi asosiy ma'lumotlar") }}</h3>
<table class="facts">
    <tr><td>{{ $t('Baholash obyekti') }}</td><td>{{ $t($item->name) }}</td></tr>
    <tr><td>{{ $t('Manzili') }}</td><td>{{ $t($location) }}</td></tr>
    <tr><td>{{ $t('Kadastr raqami') }}</td><td>{{ $s->v($item->cadastral_number) }}</td></tr>
    <tr><td>{{ $t('Maydoni / qavati') }}</td><td>{{ $num($item->total_area) }} m²@if ($item->floor) · {{ $item->floor }}/{{ $item->floors_total ?: '—' }} {{ $t('qavat') }}@endif</td></tr>
    <tr><td>{{ $t('Baholanayotgan huquq') }}</td><td>{{ $t($f['rights']) }}</td></tr>
    <tr><td>{{ $t('Mulk egasi / buyurtmachi') }}</td><td>{{ $s->v($owner?->title) }} / {{ $s->v($customer?->title) }}</td></tr>
    <tr><td>{{ $t('Baholash maqsadi') }}</td><td>{{ $item->purpose ? $t($item->purposeLabel()) : '—' }}</td></tr>
    <tr><td>{{ $t('Qiymat turi') }}</td><td>{{ $t($f['value_type']) }}</td></tr>
    <tr><td>{{ $t("Baholash sanasi / ko'zdan kechirish") }}</td><td>{{ $valuationDate->format('d.m.Y') }} / {{ $item->inspection_date?->format('d.m.Y') ?? '—' }}</td></tr>
    <tr><td>{{ $t('Baholash uchun asos') }}</td><td>@if ($item->contract_number){{ $date($item->contract_date) }} {{ $t('dagi') }} № {{ $item->contract_number }} {{ $t('sonli shartnoma') }}@else — @endif</td></tr>
    <tr><td>{{ $t('Natijadan foydalanish muddati') }}</td><td>{{ $t($f['use_period']) }}</td></tr>
</table>

<h3 class="sub">{{ $t('Yondashuvlar natijalari') }}</h3>
<table class="grid">
    <thead><tr><th>{{ $t('Yondashuv') }}</th><th class="num">{{ $t("Qiymat, so'm") }}</th><th class="num">{{ $t('Vazni') }}</th></tr></thead>
    <tbody>
        @foreach (ValuationApproach::cases() as $approach)
            @php $value = $item->{$approach->column()}; @endphp
            @if (in_array($approach, $applied, true) && $value !== null)
                <tr><td>{{ $t($approachNames[$approach->value]) }}</td><td class="num">{{ $num($value, 0) }}</td><td class="num">{{ $num($weights[$approach->value] * 100, 0) }} %</td></tr>
            @else
                <tr class="off"><td>{{ $t($approachNames[$approach->value]) }}</td><td class="num" colspan="2">{{ $t("qo'llanilmadi") }}</td></tr>
            @endif
        @endforeach
        <tr class="total"><td>{{ $valueOf('Yakuniy :type (yaxlitlangan)') }}</td><td class="num">{{ $num($final, 0) }}</td><td class="num">100 %</td></tr>
    </tbody>
</table>

{{-- ============================== Contents ============================== --}}
<h2 class="section" id="toc">{{ $t('Mundarija') }}</h2>
<ul class="toc">
    <li><a href="#summary"><span class="no"></span><span>{{ $t('Asosiy xulosalar') }}</span></a></li>
    @foreach ($sections as [$id, $no, $name])
        <li><a href="#{{ $id }}"><span class="no">{{ $no }}</span><span>{{ $t($name) }}</span></a></li>
        @if ($id === 's7' && $hasGrid)
            <li class="sub"><a href="#s7-grid"><span class="no">7.3</span><span>{{ $t('Qiyosiy yondashuv yordamida qiymatni hisoblash') }}</span></a></li>
        @endif
    @endforeach
</ul>

{{-- ============================== I. Cover letter ============================== --}}
<h2 class="section" id="s1"><span class="sec-no">I</span>{{ $t('Umumlashtiruvchi qism (ilova xat)') }}</h2>
@include('admin.report._letterhead')
<div class="letter-head">
    <div><b>№ {{ $s->v($f['letter_number']) }}</b><br>{{ $date($reportDate) }}</div>
    @if ($customer)
        <div class="to">{{ $t('Buyurtmachi') }}: <b>{{ $customer->title }}</b>@if ($customer->address)<br>{{ $t('Manzili') }}: {{ $t($customer->address) }}@endif</div>
    @endif
</div>
<p>
    {{ $owner ? $owner->title.$t(\App\Reports\RealEstateReport::dative($owner->title).' tegishli') : '' }}
    {{ $t($location.' manzilida joylashgan') }}
    @if ($item->cadastral_number){{ $item->cadastral_number }} {{ $t('raqamli kadastr asosida') }}@endif
    «{{ $t($item->name) }}» ({{ $t('keyingi o‘rinlarda — «baholash obyekti»)ni baholash quyidagi shartlarda amalga oshirildi:') }}
</p>
<ul>
    @if ($item->contract_number)<li><b>{{ $t('baholash uchun asos') }}:</b> {{ $date($item->contract_date) }} {{ $t('dagi') }} №{{ $item->contract_number }} {{ $t('sonli shartnoma') }};</li>@endif
    @if ($item->purpose)<li><b>{{ $t('baholash maqsadi') }}:</b> {{ $t($item->purposeLabel()) }};</li>@endif
    <li><b>{{ $t('baholash sanasi') }}:</b> {{ $date($valuationDate) }} {{ $t('holatiga') }};</li>
    <li><b>{{ $t('baholanayotgan qiymat turi') }}:</b> {{ $t($f['value_type']) }};</li>
    <li><b>{{ $t("qo'llanilgan yondashuvlar") }}:</b> {{ $applied ? collect($applied)->map(fn ($a) => $t($approachNames[$a->value]))->implode(', ') : '—' }}.</li>
</ul>
<h3>{{ $t('Eng muhim farazlar va cheklovchi shartlar') }}</h3>
{{ $text('assumptions') }}
<p>{{ $t("Taqdim qilingan ma'lumotlarga asosan olib borilgan hisob-kitob natijalariga ko'ra baholash obyektining tavsiya etilayotgan qiymati yaxlitlanganda quyidagicha:") }}</p>
<div class="value-box">
    <strong>{{ $sum($final) }}</strong>
    @if ($finalWords)<span>({{ $t(mb_strtoupper(mb_substr($finalWords, 0, 1)).mb_substr($finalWords, 1).' so‘m') }})</span>@endif
</div>
<p><i>{{ $t("Baholovchilar tomonidan aniqlangan qiymat O'zbekiston Respublikasining «Baholash faoliyati to'g'risida»gi Qonunining 18-moddasiga asosan tavsiya xususiyatiga ega bo'lib, mulkni tasarruf etuvchi uning qiymatini o'z manfaatlaridan kelib chiqqan holda belgilaydi. Ushbu ilova xat baholash hisobotisiz haqiqiy emas.") }}</i></p>
<p>{{ $t("Baholash hisobotida biz tomonimizdan aniqlangan baholash qiymatlari yoki yuritilgan fikrlar to'g'risida savollar tug'ilsa, bizga murojaat etishingizni so'raymiz.") }}</p>
@include('admin.report._signatures', ['director' => true, 'appraiserSign' => false])

{{-- ============================== II. Terms ============================== --}}
<h2 class="section" id="s2"><span class="sec-no">II</span>{{ $t("Asosiy atamalar, ta'riflar va standart tamoyillari") }}</h2>
{{ $text('terms') }}

{{-- ============================== III. Task ============================== --}}
<h2 class="section" id="s3"><span class="sec-no">III</span>{{ $t('Baholashga oid vazifa, asosiy faktlar va xulosalar') }}</h2>
<table class="facts">
    <tr><td>{{ $t('Baholanayotgan aktivning nomi va tavsifi') }}</td><td>{{ $t($item->name) }}. {{ $t('Umumiy maydoni') }} {{ $num($item->total_area) }} {{ $t('kv. m') }}@if ($item->useful_area), {{ $t('foydali maydoni') }} {{ $num($item->useful_area) }} {{ $t('kv. m') }}@endif.</td></tr>
    <tr><td>{{ $t("Baholash obyektiga bo'lgan huquqlar") }}</td><td>{{ $t($f['rights']) }}</td></tr>
    <tr><td>{{ $t('Baholash obyektining joylashgan joyi') }}</td><td>{{ $t($location) }}</td></tr>
    <tr><td>{{ $t('Mulk egasi') }}</td><td>@include('admin.report._person', ['client' => $owner])</td></tr>
    <tr><td>{{ $t('Buyurtmachi va uning rekvizitlari') }}</td><td>@include('admin.report._person', ['client' => $customer])</td></tr>
    <tr><td>{{ $t('Baholovchi tashkilot va uning rekvizitlari') }}</td><td>{{ $companyName }}@if ($requisites)<br>{{ $t($requisites) }}@endif @if ($company['membership'])<br>{{ $t($company['membership']) }}@endif @if ($company['license'])<br>{{ $t($company['license']) }}@endif</td></tr>
    <tr><td>{{ $t('Baholash maqsadi') }}</td><td>{{ $item->purpose ? $t($item->purposeLabel()) : '—' }}. {{ $t("Taraflarning majburiyatlari baholash xizmatini ko'rsatish shartnomasi asosida belgilanadi.") }}</td></tr>
    <tr><td>{{ $t('Baholash natijalaridan foydalanish muddati') }}</td><td>{{ $t($f['use_period']) }}</td></tr>
    <tr><td>{{ $t('Baholash sanasi') }}</td><td>{{ $date($valuationDate) }}</td></tr>
    <tr><td>{{ $t("Obyekt ko'zdan kechirilgan sana") }}</td><td>{{ $date($item->inspection_date) }}</td></tr>
    <tr><td>{{ $t('Baholash maqsadiga muvofiq qiymat turi') }}</td><td>{{ $t($f['value_type']) }}</td></tr>
    <tr><td>{{ $t("Baholashda qo'llanilgan valyuta") }}</td><td>{{ $t($f['currency_text']) }}</td></tr>
    <tr><td>{{ $t('Muhim va maxsus farazlar') }}</td><td>{{ $t($f['special_assumptions']) }}</td></tr>
    <tr><td>{{ $t('Cheklovchi shartlar') }}</td><td>{{ $t($f['limiting_conditions']) }}</td></tr>
    <tr><td>{{ $t("Buyurtmachi taqdim etgan boshlang'ich axborot") }}</td><td>{{ $t($f['initial_info']) }}</td></tr>
    <tr><td>{{ $t('Baholash ishlarini bajarish muddati') }}</td><td>{{ $t($f['deadline']) }}</td></tr>
    <tr><td>{{ $t('Hisobotdan foydalanish, uni tarqatish va chop qilishga cheklovlar') }}</td><td>{{ $t($f['use_restrictions']) }}</td></tr>
    <tr><td>{{ $t('Taxminiy foydalanuvchilar') }}</td><td>{{ $t($f['intended_users']) }}</td></tr>
    <tr><td>{{ $t("Hisobotni tuzish formati") }}</td><td>{{ $t($f['report_format']) }}</td></tr>
    <tr><td>{{ $t('Baholovchi') }}</td><td>{{ $appraiserLine }}</td></tr>
    <tr><td>{{ $t("Baholash o'tkazish uchun asos") }}</td><td>@if ($item->contract_number){{ $date($item->contract_date) }} {{ $t('dagi') }} №{{ $item->contract_number }} {{ $t('sonli shartnoma') }}@else — @endif</td></tr>
    <tr><td>{{ $t('Baholash sanasidagi valyuta kurslari (O‘zbekiston Respublikasi Markaziy banki)') }}</td><td>
        @foreach (['USD' => '1 AQSh dollari', 'EUR' => '1 Yevro', 'RUB' => '1 Rossiya rubli'] as $code => $label)
            {{ $t($label) }} = {{ $num($rates[$code] ?? null) }} {{ $t("so'm") }}@if (! $loop->last)<br>@endif
        @endforeach
    </td></tr>
    <tr><td>{{ $t("Eng samarali foydalanish to'g'risidagi xulosa") }}</td><td>{{ $t($f['hbu_conclusion']) }}</td></tr>
</table>

<h3 class="sub">3.1. {{ $t('Baholash milliy standartlari talablariga muvofiqligi to‘g‘risida bayonot') }}</h3>
{{ $text('compliance') }}

<h3 class="sub">3.2. {{ $t("Baholashda foydalanilgan ma'lumotlar va ularning manbalari") }}</h3>
{{ $text('sources') }}

{{-- ============================== IV. Economy and market ============================== --}}
<h2 class="section" id="s4"><span class="sec-no">IV</span>{{ $t('Mamlakat va mintaqadagi umumiy iqtisodiy vaziyat, bozor tahlili') }}</h2>
@if ($f['include_macro'])
    {{ $text('macro') }}
@endif
<h3 class="sub">{{ $t("Ko'chmas mulk bozorining tahlili") }}</h3>
{{ $text('market') }}
@if ($market)
    <h3 class="sub">{{ $t('Baholash obyekti segmentidagi takliflar') }}</h3>
    <p>{{ str_replace(':n', $market['count'], $t('Baholash sanasiga yaqin sanada baholash obyekti segmentida (o‘xshash turdagi, maydoni va joylashuvi yaqin obyektlar) :n ta sotuv taklifi o‘rganildi. 1 m² uchun taklif narxlari quyidagicha:')) }}</p>
    <div class="stats">
        <div class="stat"><span>{{ $t('Takliflar soni') }}</span><strong>{{ $market['count'] }}</strong></div>
        <div class="stat"><span>{{ $t('Eng past, 1 m²') }}</span><strong>{{ $sum($market['min']) }}</strong></div>
        <div class="stat"><span>{{ $t('Mediana, 1 m²') }}</span><strong>{{ $sum($market['median']) }}</strong></div>
        <div class="stat"><span>{{ $t('Eng yuqori, 1 m²') }}</span><strong>{{ $sum($market['max']) }}</strong></div>
    </div>
    @php $barMax = max(1, collect($market['bars'])->max('value')); @endphp
    <div class="bars">
        @foreach ($market['bars'] as $bar)
            <div @class(['bar', 'off' => ! $bar['included']])><span>{{ $bar['label'] }}</span><i style="width: {{ round($bar['value'] / $barMax * 100, 1) }}%"></i><b>{{ $num($bar['value'], 0) }}</b></div>
        @endforeach
    </div>
    @if ($market['usd'])
        <p class="muted">{{ $t('Mediana narx AQSh dollarida') }}: {{ $num($market['median'] / $market['usd'], 0) }} {{ $t('AQSh dollari / m²') }}. {{ $t('Kulrang ustunlar — hisob-kitobga kiritilmagan takliflar.') }}</p>
    @endif
@endif

{{-- ============================== V. Object ============================== --}}
<h2 class="section" id="s5"><span class="sec-no">V</span>{{ $t('Baholash obyektining tavsifi') }}</h2>
<table class="facts">
    <tr><td>{{ $t('Baholash obyekti tarkibi') }}</td><td>{{ $t($item->name) }}@if ($item->cadastral_number), {{ $t('kadastr raqami') }} {{ $item->cadastral_number }}@endif, {{ $t('umumiy maydoni') }} {{ $num($item->total_area) }} {{ $t('kv. m') }}</td></tr>
    <tr><td>{{ $t('Vazifasi va joriy foydalanish') }}</td><td>{{ $t($f['function']) }}</td></tr>
    <tr><td>{{ $t('Mulkdorning rekvizitlari') }}</td><td>@include('admin.report._person', ['client' => $owner])</td></tr>
    <tr><td>{{ $t("Mavjud va baholanayotgan huquqlar, yuklamalar va cheklashlar") }}</td><td>{{ $t($f['rights']) }}. {{ $t('Cheklashlar va yuklamalar') }}: {{ $t($f['encumbrances']) }}</td></tr>
    <tr><td>{{ $t("Qiymatga ta'sir ko'rsatuvchi ekologik xavf-xatarlar") }}</td><td>{{ $t($f['ecology']) }}</td></tr>
    <tr><td>{{ $t('Rivojlanish rejalari (yangi qurilish, rekonstruksiya, ta‘mirlash)') }}</td><td>{{ $t($f['development']) }}</td></tr>
</table>
<h3 class="sub">{{ $t('Yer uchastkasining tavsifi') }}</h3>
<table class="facts">
    <tr><td>{{ $t('Maydoni') }}</td><td>{{ $item->land_area ? $num($item->land_area).' '.$t('kv. m') : '—' }}</td></tr>
    <tr><td>{{ $t('Yer uchastkasiga huquq') }}</td><td>{{ $t($f['land_rights']) }}</td></tr>
    <tr><td>{{ $t('Muhandislik-geologik tavsifi') }}</td><td>{{ $t($f['geology']) }}</td></tr>
    <tr><td>{{ $t("Gidrogeologik tavsifi, to'siqlar") }}</td><td>{{ $t($f['hydrogeology']) }}</td></tr>
    <tr><td>{{ $t('Topografik tavsifi') }}</td><td>{{ $t($f['topography']) }}</td></tr>
    <tr><td>{{ $t('Foydalanishga ruxsat etilgan yerlar toifasi') }}</td><td>{{ $t($f['land_category']) }}</td></tr>
    <tr><td>{{ $t('Muhandislik kommunikatsiyalari') }}</td><td>{{ $communications ? $t(implode(', ', $communications)) : '—' }}</td></tr>
    <tr><td>{{ $t('Obodonlashtirish elementlari') }}</td><td>{{ $t($f['land_improvements']) }}</td></tr>
    <tr><td>{{ $t('Qiymat yer uchastkasidan foydalanishning qaysi variantiga muvofiq') }}</td><td>{{ $t($f['land_hbu']) }}</td></tr>
</table>
<h3 class="sub">5.1. {{ $t('Identifikatsiya tavsifi') }}</h3>
<p>{{ $t('Baholash obyektini identifikatsiya qilish maqsadida obyekt') }} {{ $date($item->inspection_date) }} {{ $t("joyiga chiqib ko'rildi, kadastr hujjatlarida keltirilgan ma'lumotlar bilan solishtirildi va fotosuratga olindi (fotosuratlar ilovada keltirilgan). Natijada quyidagilar aniqlandi:") }}</p>
<table class="facts">
    <tr><td>{{ $t('Obyekt nomi') }}</td><td>{{ $t($item->name) }}</td></tr>
    <tr><td>{{ $t('Funksional maqsadi va foydalanilishi') }}</td><td>{{ $t($f['function']) }}</td></tr>
    <tr><td>{{ $t('Joylashgan joyi') }}</td><td>{{ $t($location) }}@if ($item->landmark) ({{ $t("mo'ljal") }}: {{ $t($item->landmark) }})@endif</td></tr>
    @if ($item->hasLocation())<tr><td>{{ $t('Geografik koordinatalari') }}</td><td>{{ number_format($item->latitude, 6, '.', '') }}, {{ number_format($item->longitude, 6, '.', '') }}</td></tr>@endif
    <tr><td>{{ $t('Umumiy maydoni') }}</td><td>{{ $num($item->total_area) }} {{ $t('kv. m') }}</td></tr>
    @if ($item->built_up_area)<tr><td>{{ $t('Qurilish osti maydoni') }}</td><td>{{ $num($item->built_up_area) }} {{ $t('kv. m') }}</td></tr>@endif
    @if ($item->useful_area)<tr><td>{{ $t('Foydali maydoni') }}</td><td>{{ $num($item->useful_area) }} {{ $t('kv. m') }}</td></tr>@endif
    @if ($item->living_area)<tr><td>{{ $t('Yashash maydoni') }}</td><td>{{ $num($item->living_area) }} {{ $t('kv. m') }}</td></tr>@endif
    @if ($item->rooms)<tr><td>{{ $t('Xonalar soni') }}</td><td>{{ $item->rooms }}</td></tr>@endif
    <tr><td>{{ $t('Qurilgan yili') }}</td><td>{{ $item->year_built ? $item->year_built.' '.$t('yil') : '—' }}</td></tr>
    <tr><td>{{ $t('Qurilish hajmi') }}</td><td>{{ $f['building_volume'] ? $num($f['building_volume']).' '.$t('m.kub') : '—' }}</td></tr>
    <tr><td>{{ $t('Beriladigan huquq') }}</td><td>{{ $t($f['rights']) }}</td></tr>
    <tr><td>{{ $t('Muhandislik kommunikatsiyalari') }}</td><td>{{ $communications ? $t(implode(', ', $communications)) : '—' }}</td></tr>
    <tr><td>{{ $t('Qavatliligi') }}</td><td>{{ $s->v($item->floors_total) }}</td></tr>
    <tr><td>{{ $t('Qavati') }}</td><td>{{ $s->v($item->floor) }}</td></tr>
    <tr><td>{{ $t('Baholash obyektiga bo‘lgan cheklovlar') }}</td><td>{{ $t($f['encumbrances']) }}</td></tr>
    <tr><td>{{ $t('Qurilish tugallanganligi') }}</td><td>{{ $num($f['completion'], 0) }} %</td></tr>
    <tr><th colspan="2" class="c">{{ $t('Konstruktiv elementlar') }}</th></tr>
    <tr><td>{{ $t('Poydevor') }}</td><td>{{ $t($f['foundation']) }}</td></tr>
    <tr><td>{{ $t('Devorlar') }}</td><td>{{ $f['walls'] ? $t($f['walls']) : '—' }}</td></tr>
    <tr><td>{{ $t('Tom qoplamasi') }}</td><td>{{ $t($f['roof_cover']) }}</td></tr>
    <tr><td>{{ $t('Tom yopma') }}</td><td>{{ $t($f['roof_slab']) }}</td></tr>
    <tr><td>{{ $t('Pol') }}</td><td>{{ $t($f['floor_type']) }}</td></tr>
    <tr><td>{{ $t('Deraza va eshiklar') }}</td><td>{{ $t($f['windows_doors']) }}</td></tr>
    <tr><td>{{ $t("Ta'mir holati") }}</td><td>{{ $f['repair'] ? $t($f['repair']) : '—' }}</td></tr>
    <tr><td>{{ $t("Boshqa faoliyatga o'zgartirish qulayligi") }}</td><td>{{ $t($f['convertibility']) }}</td></tr>
    <tr><td>{{ $t('Atrofdagi imoratlar') }}</td><td>{{ $t($f['surroundings']) }}</td></tr>
</table>
@if ($item->hasLocation())
    <h3 class="sub">5.2. {{ $t('Baholash obyektining xaritadagi joylashuvi') }}</h3>
    <figure class="location-map">
        <x-static-map :lat="$item->latitude" :lng="$item->longitude" :width="640" :height="300" :lazy="false" />
        <figcaption>{{ $t($location) }}</figcaption>
    </figure>
@endif

{{-- ============================== VI. HBU ============================== --}}
<h2 class="section" id="s6"><span class="sec-no">VI</span>{{ $t('Eng samarali foydalanish tahlili') }}</h2>
{{ $text('hbu') }}
<p><b>{{ $t('Xulosa') }}:</b> {{ $t($f['hbu_conclusion']) }}</p>

{{-- ============================== VII. Approaches ============================== --}}
<h2 class="section" id="s7"><span class="sec-no">VII</span>{{ $t("Baholash yondashuvlari va usullarining tanlanishi va qo'llanilishi") }}</h2>
@if ($f['include_methodology'])
    <h3 class="sub">7.1. {{ $t('Baholash yondashuvlari va usullariga qisqacha sharh') }}</h3>
    {{ $text('methodology') }}
@endif
<h3 class="sub">7.2. {{ $t("Qo'llanilgan baholash yondashuvlarining tanlanishi va asoslanishi") }}</h3>
<table class="facts">
    <tr><td>{{ $t('Qiyosiy yondashuv') }}</td><td>{{ $t($f['reason_comparative']) }}</td></tr>
    <tr><td>{{ $t('Xarajat yondashuvi') }}</td><td>{{ $t($f['reason_cost']) }}</td></tr>
    <tr><td>{{ $t('Daromad yondashuvi') }}</td><td>{{ $t($f['reason_income']) }}</td></tr>
</table>

@if ($hasGrid)
    <h3 class="sub" id="s7-grid">7.3. {{ $t('Qiyosiy yondashuv yordamida qiymatni hisoblash') }}</h3>
    <p>{{ $t("10-son MBSga muvofiq baholash obyekti qiymati sotuvlarni to'g'ridan-to'g'ri qiyoslash usuli bilan aniqlandi. Taqqoslash birligi sifatida 1 m² umumiy maydon narxi olindi. Baholash maqsadi va natijalardan foydalanish mo'ljalidan kelib chiqib, ko'chmas mulk sotuvi bo'yicha internet saytlarida joylashtirilgan takliflar o'rganildi va baholash obyektiga asosiy iqtisodiy, fizik va texnik tavsiflari bo'yicha o'xshash bo'lgan quyidagi analoglar tanlab olindi:") }}</p>
    <table class="grid">
        <thead><tr><th class="c">№</th><th>{{ $t('Axborot manbai') }}</th><th>{{ $t('Analog obyekt manzili') }}</th><th class="num">{{ $t('Maydoni, m²') }}</th><th class="num">{{ $t('Taklif narxi') }}</th><th class="num">{{ $t("Narxi, so'm") }}</th><th class="num">{{ $t("1 m² narxi, so'm") }}</th></tr></thead>
        <tbody>
            @foreach ($analogs as $analog)
                <tr>
                    <td class="c">{{ $numberOf[$analog->id] }}</td>
                    <td>{{ $analog->host() ?? $t('Boshqa') }}</td>
                    <td>{{ $analog->location ? $t($analog->location) : $t($analog->title ?? '—') }}</td>
                    <td class="num">{{ $num($analog->area) }}</td>
                    <td class="num">{{ $num($analog->price, 0) }} {{ $analog->currency === 'USD' ? '$' : $t("so'm") }}</td>
                    <td class="num">{{ $num($analog->price_uzs, 0) }}</td>
                    <td class="num">{{ $num($analog->pricePerSquareMeter(), 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach ($chunks as $chunk)
        <table class="adj">
            <thead>
                <tr><th class="el">{{ $t('Taqqoslash elementlari') }}</th><th class="what"></th><th>{{ $t('Baholash obyekti') }}</th>
                    @foreach ($chunk as $analog)<th>{{ $t('Analog') }} №{{ $numberOf[$analog->id] }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                <tr class="group"><td class="el">{{ $t('Sotish bahosi') }}</td><td class="what">{{ $t('Taklif narxi') }}</td><td></td>
                    @foreach ($chunk as $analog)<td class="num">{{ $num($analog->price, 0) }} {{ $analog->currency === 'USD' ? '$' : $t("so'm") }}</td>@endforeach</tr>
                <tr><td></td><td class="what">{{ $t('USD kursi (taklif sanasi)') }}</td><td></td>
                    @foreach ($chunk as $analog)<td class="num">{{ $analog->currency === 'USD' ? $num($analog->usd_rate) : '—' }}</td>@endforeach</tr>
                <tr><td></td><td class="what">{{ $t("So'mdagi narxi") }}</td><td></td>
                    @foreach ($chunk as $analog)<td class="num">{{ $num($analog->price_uzs, 0) }}</td>@endforeach</tr>
                <tr><td></td><td class="what">{{ $t('Manba') }}</td><td></td>
                    @foreach ($chunk as $analog)<td>@if ($analog->url)<a href="{{ $analog->url }}">{{ $analog->host() }}</a>@else{{ $t('Boshqa') }}@endif</td>@endforeach</tr>
                <tr><td></td><td class="what">{{ $t('Umumiy maydoni, m²') }}</td><td class="num">{{ $num($item->total_area) }}</td>
                    @foreach ($chunk as $analog)<td class="num">{{ $num($analog->area) }}</td>@endforeach</tr>
                <tr class="total"><td></td><td class="what">{{ $t("1 m² narxi, so'm") }}</td><td></td>
                    @foreach ($chunk as $analog)<td class="num">{{ $num($comparative['rows'][$analog->id]['price_m2']) }}</td>@endforeach</tr>

                @foreach ($grid as $row)
                    @php
                        $applies = $row['key'] && $chunk->contains(fn ($a) => abs($comparative['rows'][$a->id]['steps'][$row['key']]['percent'] ?? 0) >= 0.005);
                    @endphp
                    <tr class="group">
                        <td class="el">{{ $t($row['title']) }}</td>
                        <td class="what">{{ $t('Tavsif') }}</td>
                        <td>{{ $t($row['subject']) }}</td>
                        @foreach ($chunk as $analog)<td>{{ $t($row['analogs'][$analog->id] ?? '—') }}</td>@endforeach
                    </tr>
                    <tr>
                        <td></td><td class="what">{{ $t('Tuzatish') }}</td><td></td>
                        @foreach ($chunk as $analog)
                            @php $p = $row['key'] ? ($comparative['rows'][$analog->id]['steps'][$row['key']]['percent'] ?? 0) : 0; @endphp
                            <td class="num pct">{{ $pct($p) }}</td>
                        @endforeach
                    </tr>
                    @if ($applies)
                        <tr>
                            <td></td><td class="what">{{ $t("Tuzatishdan keyingi 1 m² narxi") }}</td><td></td>
                            @foreach ($chunk as $analog)<td class="num after">{{ $num($comparative['rows'][$analog->id]['steps'][$row['key']]['value']) }}</td>@endforeach
                        </tr>
                    @endif
                @endforeach

                <tr class="group total"><td class="el">{{ $t('Yakuniy qism') }}</td><td class="what">{{ $t('Jami tuzatish') }}</td><td></td>
                    @foreach ($chunk as $analog)<td class="num">{{ $pct($comparative['rows'][$analog->id]['total']) }}</td>@endforeach</tr>
                <tr class="total"><td></td><td class="what">{{ $t('Tuzatishlar soni') }}</td><td></td>
                    @foreach ($chunk as $analog)<td class="num">{{ $comparative['rows'][$analog->id]['count'] }}</td>@endforeach</tr>
                <tr class="total"><td></td><td class="what">{{ $t("Tuzatilgan 1 m² narxi, so'm") }}</td><td></td>
                    @foreach ($chunk as $analog)<td class="num">{{ $num($comparative['rows'][$analog->id]['adjusted_m2']) }}</td>@endforeach</tr>
                <tr class="total"><td></td><td class="what">{{ $t('Vazni') }}</td><td></td>
                    @foreach ($chunk as $analog)<td class="num">{{ $num($comparative['rows'][$analog->id]['weight'] * 100) }} %</td>@endforeach</tr>
                <tr class="total"><td></td><td class="what">{{ $t('Vazn hisobga olingan narx') }}</td><td></td>
                    @foreach ($chunk as $analog)<td class="num">{{ $num($comparative['rows'][$analog->id]['adjusted_m2'] * $comparative['rows'][$analog->id]['weight']) }}</td>@endforeach</tr>
            </tbody>
        </table>
    @endforeach
    <table class="adj">
        <tr class="result"><td>{{ $t('Baholash obyektining 1 m² uchun yakuniy qiymati') }}</td><td class="num">{{ $sum($comparative['price_m2']) }}</td></tr>
        <tr class="result"><td>{{ $t('Baholash obyektining umumiy maydoni') }}</td><td class="num">{{ $num($item->total_area) }} {{ $t('kv. m') }}</td></tr>
        <tr class="result"><td>{{ $t('Qiyosiy yondashuv bo‘yicha qiymat (yaxlitlangan)') }}</td><td class="num">{{ $sum($comparative['value']) }}</td></tr>
    </table>

    <h3 class="sub">{{ $t('Kiritilgan tuzatishlarga sharh') }}</h3>
    <div class="comment">
        <p>{{ $t("Tuzatishlar 10-son MBS 1-§ 23-bandida belgilangan taqqoslash elementlari bo'yicha ketma-ket kiritildi: har bir tuzatish oldingi tuzatishdan keyingi narxga qo'llanildi (narx × (1 + tuzatish / 100)). Analoglarning manbalari va tavsiflari ilovada keltirilgan.") }}</p>
        @foreach ($commentary as $note)
            <p><b>{{ $t($note['title']) }}.</b> {{ $t($note['text']) }}</p>
        @endforeach
        <p><b>{{ $t('Yakuniy qiymat') }}.</b> {{ $t("Baholash obyektining 1 m² uchun qiymati tuzatilgan narxlarning vaznli o'rtachasi sifatida aniqlandi va baholash obyektining umumiy maydoniga ko'paytirildi") }}: {{ $num($comparative['price_m2']) }} × {{ $num($item->total_area) }} = {{ $sum($comparative['value']) }}.</p>
    </div>
@elseif ($comparativeApplied)
    <p class="muted">{{ $t("Qiyosiy yondashuv bo'yicha hisob-kitob uchun analoglar kiritilmagan.") }}</p>
@endif

@foreach ([ValuationApproach::Cost, ValuationApproach::Income] as $approach)
    @if (in_array($approach, $applied, true))
        <h3 class="sub">{{ $t($approachNames[$approach->value]) }}</h3>
        @if ($approach === ValuationApproach::Cost && $costApproach && $costApproach['rows'])
            <p>{{ $t("Xarajat yondashuvida baholash obyektining qiymati binolarni qayta tiklash (o'rnini bosish) qiymatidan jismoniy, funksional va tashqi eskirishni chegirib, yer uchastkasiga bo'lgan huquq qiymatini qo'shish yo'li bilan aniqlandi. Jismoniy eskirish bino yoshi va iqtisodiy xizmat muddati nisbati bo'yicha (yoki ko'zdan kechirish natijasida baholangan holda) hisoblandi; quruvchi foydasi") }} {{ $num($costApproach['profit'], 0) }} %.</p>
            <table class="grid">
                <thead><tr><th>{{ $t('Bino (inshoot)') }}</th><th class="num">{{ $t('Maydon / hajm') }}</th><th class="num">{{ $t("Birlik narxi, so'm") }}</th><th class="num">{{ $t("Qayta tiklash qiymati, so'm") }}</th><th class="num">{{ $t('Yoshi / xizmat muddati') }}</th><th class="num">{{ $t('Jismoniy eskirish, %') }}</th><th class="num">{{ $t('Funksional / tashqi, %') }}</th><th class="num">{{ $t("Qiymati, so'm") }}</th></tr></thead>
                <tbody>
                    @foreach ($costApproach['rows'] as $row)
                        <tr>
                            <td>{{ $t($row['name'] ?: 'Bino') }}@if ($row['light'])<br><small>{{ $t('yengil konstruktiv element') }}</small>@endif</td>
                            <td class="num">{{ $num($row['quantity']) }} {{ $row['unit'] === 'm3' ? 'm³' : 'm²' }}</td>
                            <td class="num">{{ $num($row['rate'], 0) }}</td>
                            <td class="num">{{ $num($row['replacement'], 0) }}</td>
                            <td class="num">{{ $row['age'] !== null ? $row['age'] : '—' }} / {{ $row['life'] }}</td>
                            <td class="num">{{ $num($row['physical']) }}</td>
                            <td class="num">{{ $num($row['functional']) }} / {{ $num($row['external']) }}</td>
                            <td class="num">{{ $num($row['value'], 0) }}</td>
                        </tr>
                    @endforeach
                    @if ($costApproach['land'] > 0)<tr><td colspan="7">{{ $t("Yer uchastkasiga bo'lgan huquq qiymati") }}</td><td class="num">{{ $num($costApproach['land'], 0) }}</td></tr>@endif
                    <tr class="total"><td colspan="7"><b>{{ $t("Xarajat yondashuvi bo'yicha qiymat (yaxlitlangan)") }}</b></td><td class="num"><b>{{ $num($costApproach['value'], 0) }}</b></td></tr>
                </tbody>
            </table>
        @elseif ($approach === ValuationApproach::Income && $incomeApproach && $incomeApproach['value'])
            <p>{{ $t("Daromad yondashuvida to'g'ridan-to'g'ri kapitallashtirish usuli qo'llanildi: obyektdan bir yilda olinishi mumkin bo'lgan sof operatsion daromad kapitallashtirish stavkasiga bo'lindi.") }}</p>
            <table class="facts">
                <tr><td>{{ $t('Ijaraga beriladigan maydon') }}</td><td>{{ $num($incomeApproach['area']) }} {{ $t('kv. m') }}</td></tr>
                <tr><td>{{ $t("1 m² uchun oylik ijara haqi") }}</td><td>{{ $sum($incomeApproach['rent']) }}</td></tr>
                <tr><td>{{ $t('Potensial yalpi daromad (yiliga)') }}</td><td>{{ $sum($incomeApproach['pgi']) }}</td></tr>
                <tr><td>{{ $t("Bo'sh turish va yo'qotishlar") }}</td><td>{{ $num($incomeApproach['vacancy']) }} %</td></tr>
                <tr><td>{{ $t('Samarali yalpi daromad') }}</td><td>{{ $sum($incomeApproach['egi']) }}</td></tr>
                <tr><td>{{ $t('Operatsion xarajatlar') }}</td><td>{{ $num($incomeApproach['opex']) }} %</td></tr>
                <tr><td>{{ $t('Sof operatsion daromad') }}</td><td>{{ $sum($incomeApproach['noi']) }}</td></tr>
                <tr><td>{{ $t('Kapitallashtirish stavkasi') }}</td><td>{{ $num($incomeApproach['cap']) }} %</td></tr>
                <tr><td><b>{{ $t("Daromad yondashuvi bo'yicha qiymat") }}</b></td><td><b>{{ $sum($incomeApproach['value']) }}</b></td></tr>
            </table>
        @endif
        <p>{{ $t($approachNames[$approach->value].' bo‘yicha aniqlangan qiymat') }}: <b>{{ $sum($item->{$approach->column()}) }}</b>.</p>
    @endif
@endforeach

{{-- ============================== VIII. Reconciliation ============================== --}}
<h2 class="section" id="s8"><span class="sec-no">VIII</span>{{ $t('Natijalarni muvofiqlashtirish va yakuniy qiymat') }}</h2>
{{ $text('reconciliation') }}
<table class="grid">
    <thead><tr><th>{{ $t('Mezonlar') }}</th>@foreach (ValuationApproach::cases() as $approach)<th class="c">{{ $t($approachNames[$approach->value]) }} ({{ $t($approachShort[$approach->value]) }})</th>@endforeach</tr></thead>
    <tbody>
        @foreach (\App\Reports\RealEstateReport::CRITERIA as $i => $criterion)
            <tr><td>{{ $t($criterion) }}</td>@foreach (ValuationApproach::cases() as $approach)<td class="c">{{ $num($scores[$approach->value][$i], 0) }}</td>@endforeach</tr>
        @endforeach
        <tr class="total"><td><b>{{ $t('Ballar yig‘indisi') }}</b></td>@foreach (ValuationApproach::cases() as $approach)<td class="c"><b>{{ $num(array_sum($scores[$approach->value]), 0) }}</b></td>@endforeach</tr>
    </tbody>
</table>
<table class="grid">
    <thead><tr><th>{{ $t('Yondashuv') }}</th><th class="num">{{ $t('Aniqlangan qiymat') }}</th><th class="num">{{ $t('Solishtirma og‘irlik') }}</th><th class="num">{{ $t('Og‘irlik hisobga olingan qiymat') }}</th></tr></thead>
    <tbody>
        @foreach (ValuationApproach::cases() as $approach)
            @php $value = $item->{$approach->column()}; $applies = in_array($approach, $applied, true); @endphp
            <tr>
                <td>{{ $t($approachNames[$approach->value]) }}</td>
                <td class="num">{{ $applies && $value !== null ? $sum($value) : $t("Qo'llanilmadi") }}</td>
                <td class="num">{{ $num($weights[$approach->value], 3) }}</td>
                <td class="num">{{ $applies && $value !== null ? $sum($value * $weights[$approach->value]) : '—' }}</td>
            </tr>
        @endforeach
        <tr class="total"><td colspan="3"><b>{{ $t('Muvofiqlashtirilgan qiymat') }}</b> ({{ $t('Kyak = Kxar × C1 + Kdar × C2 + Kqiy × C3') }})</td><td class="num"><b>{{ $sum($reconciled) }}</b></td></tr>
    </tbody>
</table>
<div class="final">
    <span>{{ str_replace(':type', mb_strtolower($t($f['value_type'])), $t('Baholash obyektining baholash sanasiga aniqlangan :type yaxlitlanganda')) }}</span>
    <strong>{{ $sum($final) }}</strong>
    @if ($finalWords)<em>({{ $t(mb_strtoupper(mb_substr($finalWords, 0, 1)).mb_substr($finalWords, 1).' so‘m') }})</em>@endif
</div>

{{-- ============================== IX. Quality ============================== --}}
<h2 class="section" id="s9"><span class="sec-no">IX</span>{{ $t("Baholash to'g'risidagi hisobotning sifati to'g'risida bayonot") }}</h2>
{{ $text('quality') }}
@include('admin.report._signatures', ['director' => true, 'appraiserSign' => true])

{{-- ============================== X. Appendices ============================== --}}
<h2 class="section" id="s10"><span class="sec-no">X</span>{{ $t('Ilovalar') }}</h2>
<ul>
    <li>{{ $t("Ko'chmas mulkni ko'zdan kechirish to'g'risidagi dalolatnoma") }};</li>
    @if ($hasGrid)<li>{{ $t('Qiyosiy yondashuv analoglari ma‘lumotlari') }};</li>@endif
    @if ($photos->isNotEmpty())<li>{{ $t('Obyekt fotosuratlari') }} ({{ $photos->count() }});</li>@endif
    @foreach ($documents as $document)<li>{{ $document->name }};</li>@endforeach
    @foreach ($companyDocuments as $document)<li>{{ $document['name'] }};</li>@endforeach
</ul>

<h3 class="sub center">{{ mb_strtoupper($t("Ko'chmas mulkni ko'zdan kechirish to'g'risida dalolatnoma")) }}</h3>
<table class="facts">
    <tr><td>{{ $t("Ko'zdan kechirilgan sana") }}</td><td>{{ $date($item->inspection_date) }}</td></tr>
    <tr><td>{{ $t('Mulk egasi') }}</td><td>{{ $s->v($owner?->title) }}</td></tr>
    <tr><td>{{ $t('Baholovchi') }}</td><td>{{ $s->v($appraiser?->name) }}</td></tr>
    @if ($act['participants'])<tr><td>{{ $t("Ko'zdan kechirishda ishtirok etganlar") }}</td><td>{{ $t($act['participants']) }}</td></tr>@endif
</table>
<p>{{ $t($location.' manzilida joylashgan') }}@if ($item->cadastral_number), {{ $t('kadastr raqami') }} {{ $item->cadastral_number }}@endif {{ $t("bo'lgan ko'chmas mulkni ko'zdan kechirish jarayonida quyidagilar aniqlandi:") }}</p>
<table class="grid">
    <thead><tr><th>{{ $t('Nomi') }}</th><th class="c">{{ $t('Qurilgan yili') }}</th><th class="c">{{ $t('Qavatliligi / qavati') }}</th><th class="num">{{ $t('Umumiy maydoni, m²') }}</th><th class="c">{{ $t('Qurilish tugallanganligi') }}</th></tr></thead>
    <tbody>
        <tr><td>{{ $t($item->name) }}</td><td class="c">{{ $s->v($item->year_built) }}</td><td class="c">{{ $s->v($item->floors_total) }} / {{ $s->v($item->floor) }}</td><td class="num">{{ $num($item->total_area) }}</td><td class="c">{{ $num($f['completion'], 0) }} %</td></tr>
    </tbody>
</table>
<table class="grid">
    <thead><tr><th class="c">№</th><th>{{ $t('Konstruktiv element') }}</th><th>{{ $t('Tavsifi') }}</th><th>{{ $t('Texnik holati') }}</th><th class="num">{{ $t('Ulushi, %') }}</th><th class="num">{{ $t('Eskirish, %') }}</th></tr></thead>
    <tbody>
        @foreach ($act['rows'] as $row)
            <tr>
                <td class="c">{{ $loop->iteration }}</td>
                <td>{{ $t($row['title']) }}</td>
                <td>{{ $row['description'] ? $t($row['description']) : '—' }}</td>
                <td>{{ $row['condition'] ? $t($row['condition']) : '—' }}</td>
                <td class="num">{{ $num($row['share'], fmod($row['share'], 1.0) ? 1 : 0) }}</td>
                <td class="num">{{ $row['wear'] !== null ? $num($row['wear'], fmod($row['wear'], 1.0) ? 1 : 0) : '—' }}</td>
            </tr>
        @endforeach
        @if ($act['wear'] !== null)
            <tr class="total"><td colspan="5">{{ $t("Binoning jismoniy eskirishi (elementlar ulushi bo'yicha vaznli o'rtacha)") }}</td><td class="num">{{ $num($act['wear'], 1) }}</td></tr>
        @endif
    </tbody>
</table>
<p><b>{{ $t('Muhandislik kommunikatsiyalari') }}:</b> {{ $communications ? $t(implode(', ', $communications)) : '—' }}. <b>{{ $t("Ta'mir holati") }}:</b> {{ $f['repair'] ? $t($f['repair']) : '—' }}.</p>
@if ($act['defects'])<p><b>{{ $t('Aniqlangan kamchiliklar') }}:</b> {{ $t($act['defects']) }}</p>@endif
@include('admin.report._signatures', ['director' => false, 'appraiserSign' => true, 'ownerSign' => $owner])

@if ($hasGrid)
    <h3 class="sub" style="break-before: page">{{ $t('Qiyosiy yondashuv analoglari ma‘lumotlari') }}</h3>
    @foreach ($analogs as $analog)
        <div class="analog-card">
            @if ($analog->photo_path)
                <img src="{{ route('admin.analogs.photo', $analog) }}" alt="{{ $t('Analog') }} №{{ $numberOf[$analog->id] }}">
            @else
                <div class="nophoto">{{ $t('Analog') }} №{{ $numberOf[$analog->id] }}</div>
            @endif
            <table>
                <tr><td>{{ $t('Analog') }}</td><td>№{{ $numberOf[$analog->id] }}@if ($analog->title) — {{ $t($analog->title) }}@endif</td></tr>
                <tr><td>{{ $t('Manzil') }}</td><td>{{ $analog->location ? $t($analog->location) : '—' }}</td></tr>
                <tr><td>{{ $t('Umumiy maydoni (m²)') }}</td><td>{{ $num($analog->area) }}</td></tr>
                <tr><td>{{ $t('Narxi') }}</td><td>{{ $num($analog->price, 0) }} {{ $analog->currency === 'USD' ? $t('AQSh dollari') : $t("so'm") }}@if ($analog->currency === 'USD') ({{ $sum($analog->price_uzs) }})@endif</td></tr>
                <tr><td>{{ $t('Qavati / uy qavatliligi') }}</td><td>{{ $analog->floor ?: '—' }} / {{ $analog->floors_total ?: '—' }}</td></tr>
                <tr><td>{{ $t('Qurilish turi') }}</td><td>{{ $analog->wall_material ? $t($analog->wall_material) : '—' }}</td></tr>
                <tr><td>{{ $t("Ta'mir holati") }}</td><td>{{ $analog->condition ? $t($analog->condition) : '—' }}</td></tr>
                <tr><td>{{ $t('Taklif sanasi') }}</td><td>{{ $analog->listed_at ? $analog->listed_at->format('d.m.Y') : '—' }}</td></tr>
                <tr><td>{{ $t('Manba') }}</td><td>@if ($analog->url)<a href="{{ $analog->url }}">{{ $analog->url }}</a>@else — @endif</td></tr>
            </table>
        </div>
    @endforeach
@endif

@if ($photos->isNotEmpty())
    <h3 class="sub" style="break-before: page">{{ $t('Obyekt fotosuratlari') }}</h3>
    <div class="photo-grid">
        @foreach ($photos as $photo)
            <figure><img src="{{ route('admin.attachments.show', $photo) }}" alt="{{ $photo->name }}"><figcaption>{{ $loop->iteration }}-{{ $t('rasm') }}</figcaption></figure>
        @endforeach
    </div>
@endif

@foreach ($documents as $document)
    <figure class="doc-page"><img src="{{ route('admin.attachments.show', $document) }}" alt="{{ $document->name }}"></figure>
@endforeach

@foreach ($companyDocuments as $index => $document)
    <figure class="doc-page"><img src="{{ route('admin.report-documents.show', $index) }}" alt="{{ $document['name'] }}"></figure>
@endforeach
</body>
</html>
