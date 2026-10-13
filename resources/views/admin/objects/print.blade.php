@php
    use App\Models\Setting;
    use App\Support\Format;
    use App\Enums\PersonRole;
    $locale = app()->getLocale();
    $isDamage = ! $module->usesApproaches();
@endphp
<!DOCTYPE html>
<html lang="{{ config('fairvalue.locales.'.$locale.'.html', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $item->report_number ? __('Report').' № '.$item->report_number : $item->title }}</title>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <style>
        @page { size: A4; margin: 16mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Manrope', Arial, sans-serif; font-size: 12.5px; line-height: 1.5; color: #111827; background: #e5e7eb; }
        .sheet { width: 210mm; min-height: 297mm; margin: 24px auto; padding: 16mm 14mm; background: #fff; box-shadow: 0 10px 40px rgba(0,0,0,.12); }
        .toolbar { position: sticky; top: 0; display: flex; justify-content: center; gap: 8px; padding: 12px; background: #111827; }
        .toolbar button, .toolbar a { padding: 8px 16px; border: 0; border-radius: 8px; background: #4f46e5; color: #fff; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #374151; }
        header { display: flex; justify-content: space-between; gap: 20px; padding-bottom: 14px; border-bottom: 2px solid #4f46e5; }
        .brand { display: flex; gap: 12px; align-items: center; }
        .brand strong { display: block; font-size: 18px; letter-spacing: .1em; text-transform: uppercase; }
        .brand small { display: block; color: #6b7280; }
        .company { text-align: right; color: #4b5563; font-size: 11.5px; }
        h1 { margin: 22px 0 4px; font-family: 'Lora', Georgia, serif; font-size: 22px; text-align: center; }
        .subtitle { text-align: center; color: #4b5563; margin: 0 0 18px; }
        h2 { margin: 18px 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: #4338ca; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px 8px; border: 1px solid #d1d5db; vertical-align: top; text-align: left; }
        th { background: #f9fafb; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #4b5563; }
        .facts td:first-child { width: 38%; color: #4b5563; background: #fafafa; }
        .r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .total { margin-top: 16px; padding: 14px 16px; border: 2px solid #4f46e5; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .total span { font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .total strong { font-size: 20px; font-variant-numeric: tabular-nums; }
        .bottom { display: flex; justify-content: space-between; gap: 24px; margin-top: 28px; align-items: flex-end; }
        .sign p { margin: 0 0 22px; }
        .sign .line { display: inline-block; min-width: 180px; border-bottom: 1px solid #111; }
        .qr-box { text-align: center; font-size: 10.5px; color: #4b5563; }
        .qr-box .qr { width: 110px; height: 110px; margin: 0 auto 6px; }
        .qr-box .qr svg { width: 100%; height: 100%; }
        .code { font-family: ui-monospace, Menlo, Consolas, monospace; font-weight: 700; color: #111; font-size: 12px; }
        .note { margin-top: 18px; font-size: 10.5px; color: #6b7280; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    <a href="{{ route($module->routeName('show'), $item->id) }}">{{ __('Back') }}</a>
</div>
<div class="sheet">
    <header>
        <div class="brand">
            <x-logo-mark :size="44" />
            <div>
                <strong>{{ Setting::get('name') }}</strong>
                <small>{{ Setting::get('legal_name') }}</small>
            </div>
        </div>
        <div class="company">
            @if (Setting::get('license'))<div>{{ __('License') }}: {{ Setting::get('license') }}</div>@endif
            @if (Setting::get('address'))<div>{{ Setting::get('address') }}</div>@endif
            <div>{{ Setting::get('phone') }} · {{ Setting::get('email') }}</div>
        </div>
    </header>

    <h1>{{ $isDamage ? __('Certificate of damage assessment') : __('Certificate of valuation results') }}</h1>
    <p class="subtitle">
        {{ __('Report') }} № <strong>{{ $item->report_number ?? '—' }}</strong>
        · {{ __('Valuation date') }}: <strong>{{ Format::date($item->valuation_date) }}</strong>
    </p>

    <h2>{{ __('Object of valuation') }}</h2>
    <table class="facts">
        @foreach ($module->facts($item) as $label => $value)
            @if (filled($value))
                <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
            @endif
        @endforeach
        <tr><td>{{ __('Location') }}</td><td>{{ $item->locationLine() ?: '—' }}</td></tr>
    </table>

    @if ($item->clients->isNotEmpty())
        <h2>{{ __('Persons') }}</h2>
        <table>
            <tr><th>{{ __('Role') }}</th><th>{{ __('Name') }}</th><th>{{ __('Document') }}</th></tr>
            @foreach ($item->clients as $client)
                <tr><td>{{ PersonRole::labelFor($client->pivot->role) }}</td><td>{{ $client->title }}</td><td>{{ $client->document ?? '—' }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($isDamage)
        @if ($item->items->isNotEmpty())
            <h2>{{ __('Damage calculation') }}</h2>
            <table>
                <tr><th>{{ __('Name') }}</th><th class="r">{{ __('Qty') }}</th><th class="r">{{ __('Norm-h') }}</th><th class="r">{{ __('Price / rate') }}</th><th class="r">{{ __('Amount') }}</th></tr>
                @foreach ($item->items as $line)
                    <tr>
                        <td>{{ $line->name }}<br><small style="color:#6b7280">{{ $line->type->label() }}@if ($line->operation) · {{ $line->operationLabel() }}@endif</small></td>
                        <td class="r">{{ Format::number($line->quantity, fmod((float) $line->quantity, 1.0) ? 2 : 0) }}</td>
                        <td class="r">{{ $line->hours !== null ? Format::number($line->hours, 2) : '—' }}</td>
                        <td class="r">{{ Format::number($line->unit_price) }}</td>
                        <td class="r">{{ Format::number($line->amount) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
        <table style="margin-top: 10px">
            <tr><td>{{ __('Spare parts') }}</td><td class="r">{{ Format::money($item->parts_total) }}</td></tr>
            <tr><td>{{ __('Wear') }} ({{ Format::number($item->wear_percent, 2) }}%)</td><td class="r">−{{ Format::money($item->wear_total) }}</td></tr>
            <tr><td>{{ __('Small parts') }}</td><td class="r">{{ Format::money($item->small_parts_total) }}</td></tr>
            <tr><td>{{ __('Repair work') }}</td><td class="r">{{ Format::money($item->labor_total) }}</td></tr>
            <tr><td>{{ __('Paint materials') }}</td><td class="r">{{ Format::money($item->paint_total) }}</td></tr>
            <tr><td>{{ __('Other expenses') }}</td><td class="r">{{ Format::money($item->other_total) }}</td></tr>
            <tr><td>{{ __('Loss of commodity value') }}</td><td class="r">{{ Format::money($item->commodity_loss) }}</td></tr>
        </table>
    @else
        <h2>{{ __('Valuation') }}</h2>
        <table class="facts">
            <tr><td>{{ __('Purpose of valuation') }}</td><td>{{ $item->purposeLabel() ?? '—' }}</td></tr>
            @foreach ($item->approachList() as $approach)
                <tr><td>{{ $approach->label() }}</td><td class="r" style="text-align:left">{{ $item->{$approach->column()} !== null ? Format::money($item->{$approach->column()}) : __('applied') }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="total">
        <span>{{ $isDamage ? __('Total damage') : __('Market value') }}</span>
        <strong>{{ $item->market_value !== null ? Format::money($item->market_value) : '—' }}</strong>
    </div>

    <div class="bottom">
        <div class="sign">
            <p>{{ __('Appraiser') }}: <span class="line">&nbsp;</span> {{ $item->appraiser?->name }}</p>
            @if ($item->appraiser?->certificate_number)<p style="margin-top:-14px;color:#6b7280">{{ __('Qualification certificate') }} № {{ $item->appraiser->certificate_number }}</p>@endif
            <p>{{ __('Director') }}: <span class="line">&nbsp;</span> {{ Setting::get('director') }}</p>
            <p style="color:#6b7280">{{ __('Stamp') }}</p>
        </div>
        <div class="qr-box">
            <div class="qr" data-qr="{{ $item->verifyUrl() }}"></div>
            <div>{{ __('Check authenticity') }}:</div>
            <div class="code">{{ $item->verify_code }}</div>
            <div>{{ parse_url(route('verify'), PHP_URL_HOST) }}/verify</div>
        </div>
    </div>

    <p class="note">{{ __('This certificate summarises the results of the valuation report and is not a substitute for it. The authenticity of the document can be checked by scanning the QR code or entering the code on our website.') }}</p>
</div>
<script src="{{ asset('js/vendor/qrcode.js') }}"></script>
<script src="{{ asset('js/admin.js') }}"></script>
</body>
</html>
