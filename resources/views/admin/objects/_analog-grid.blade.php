{{--
    The comparative approach as the valuation portal shows it: the object and every analogue in columns,
    the elements of comparison in rows, each with what the object and the analogues have, the adjustment
    and the price per m² after it; then the number of adjustments, the weights and the value with the land.
    Fields the appraiser types are in the cells; the rest is worked out (ComparativeCalculator).
--}}
@php
    use App\Enums\RealEstateFeature;
    use App\Support\ComparativeCalculator;
    use App\Support\Format;

    $f = \App\Reports\RealEstateReport::values($item);
    $subject = $result['subject'];
    // Typed by the appraiser; what is worked out shows as the placeholder, so it follows the areas.
    $typedSubject = (array) (((array) $item->report)['comparative'] ?? []);
    $used = $analogs->filter(fn ($a) => isset($result['rows'][$a->id]));
    $n2 = fn ($v, $d = 2) => $v === null ? '—' : Format::number($v, $d);
    $typed = fn ($v, $d = 4) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, $d, '.', ''), '0'), '.');
    $row = fn ($analog) => $result['rows'][$analog->id] ?? null;
    // The price per m² after an element (the one before it for the elements that change nothing).
    $after = fn ($analog, $key) => ($r = $row($analog)) ? $n2($r['steps'][$key]['value']) : '—';
    $pct = fn ($analog, $key) => ($r = $row($analog)) ? Format::number($r['steps'][$key]['percent'], 2).' %' : '—';
    $comms = collect(RealEstateFeature::communications())->mapWithKeys(fn ($c) => [$c->value => $c->label()]);
    $commList = fn (?array $list) => $list === null ? __('No information') : (collect($list)->map(fn ($k) => $comms[$k] ?? $k)->implode(', ') ?: '—');
    $same = fn ($text) => $analogs->mapWithKeys(fn ($a) => [$a->id => $text]);
    $disabled = ! $canEdit;
    $wearOf = fn ($text) => ($w = ComparativeCalculator::wear($text)) === null ? '—' : Format::number($w, 0).' %';
    $conditions = \App\Reports\RealEstateReport::OPTIONS['repair'] ?? ['Yaxshi', 'Qoniqarli', 'Qoniqarsiz'];
    // An element that changes nothing: what everyone has, the adjustment 1 and the price as it was.
    $plain = [
        'lease' => [__('Encumbrances of the object by lease contracts'), __('Presence'), __('No information')],
        'servitude' => [__('Servitudes and additional social encumbrances'), __('Presence'), __('none')],
    ];
    $span = $analogs->count() + 3;
@endphp
<div class="table-wrap analog-grid-wrap">
    <table class="table analog-grid">
        <thead>
            <tr>
                <th class="g-element">{{ __('Elements of comparison') }}</th>
                <th class="g-sub"></th>
                <th class="g-subject">{{ __('Object of valuation') }}</th>
                @foreach ($analogs as $analog)
                    <th @class(['g-analog', 'is-off' => ! $row($analog)])>
                        <span class="g-no">{{ __('analogue :n', ['n' => $loop->iteration]) }}</span>
                        <span class="g-title">@if ($analog->url)<a href="{{ $analog->url }}" target="_blank" rel="noopener noreferrer nofollow">{{ $analog->title ?: $analog->host() }}</a>@else{{ $analog->title ?: '—' }}@endif</span>
                        @if ($analog->isPending())<span class="badge badge-info">{{ __('Reading…') }}</span>@elseif ($analog->status === \App\Models\Analog::FAILED)<span class="badge badge-warning">{{ __('Not read') }}</span>@endif
                        @if ($analog->note)<span class="analog-note">{{ $analog->note }}</span>@endif
                        <span class="g-tools">
                            <label class="g-use" title="{{ __('Used in the calculation') }}"><input type="checkbox" name="analogs[{{ $analog->id }}][included]" value="1" @checked($analog->included) @disabled($disabled || $analog->isPending())> {{ __('used') }}</label>
                            @if ($canEdit)
                                <label class="btn btn-sm btn-icon" title="{{ $analog->photo_path ? __('Replace photo') : __('Add photo') }}"><x-icon name="image" :size="14" />
                                    <input type="file" name="photo" form="analog-photo-{{ $analog->id }}" accept="image/jpeg,image/png,image/webp" hidden data-submit-on-change>
                                </label>
                                <button class="btn btn-sm btn-icon" type="submit" form="analog-delete-{{ $analog->id }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash-2" :size="14" /></button>
                            @endif
                        </span>
                        @if ($analog->photo_path)<a class="g-photo" href="{{ route('admin.analogs.photo', $analog) }}" target="_blank"><img src="{{ route('admin.analogs.photo', $analog) }}" alt="{{ __('Photo') }}" loading="lazy"></a>@endif
                        @if ($canEdit && ! $analog->isPending() && $analog->url)
                            <details class="analog-paste" @if ($analog->status === \App\Models\Analog::FAILED) open @endif>
                                <summary>{{ __('Paste the text of the listing') }}</summary>
                                <textarea class="input" name="text" rows="4" form="analog-text-{{ $analog->id }}" maxlength="60000" required aria-label="{{ __('Paste the text of the listing') }}"></textarea>
                                <button class="btn btn-sm btn-primary" type="submit" form="analog-text-{{ $analog->id }}">{{ __('Read the text') }}</button>
                            </details>
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{-- The offer itself. --}}
            <tr>
                <td>{{ __('Sale price') }}</td><td class="g-sub">{{ __('Value in US dollars') }}</td><td></td>
                @foreach ($analogs as $analog)
                    <td>
                        <div class="g-price">
                            <input class="input" name="analogs[{{ $analog->id }}][price]" value="{{ $analog->price !== null ? $typed($analog->price, 2) : '' }}" inputmode="decimal" aria-label="{{ __('Price') }}" @disabled($disabled || $analog->isPending())>
                            <select class="input" name="analogs[{{ $analog->id }}][currency]" aria-label="{{ __('Currency') }}" @disabled($disabled || $analog->isPending())>
                                <option value="USD" @selected($analog->currency === 'USD')>USD</option>
                                <option value="UZS" @selected($analog->currency !== 'USD')>{{ __('sum') }}</option>
                            </select>
                        </div>
                        @if ($analog->currency !== 'USD' && ($r = $row($analog)) && $r['usd'])<span class="g-note">≈ {{ $n2($r['usd']) }} USD</span>@endif
                    </td>
                @endforeach
            </tr>
            <tr>
                <td>{{ __('Exchange rate (US dollar) of the Central Bank on the date of listing or sale') }}</td><td></td><td></td>
                @foreach ($analogs as $analog)<td class="num">{{ $n2($analog->usd_rate) }}</td>@endforeach
            </tr>
            <tr>
                <td>{{ __('Price in Uzbek sum') }}</td><td></td><td></td>
                @foreach ($analogs as $analog)<td class="num">{{ $n2($analog->price_uzs) }}</td>@endforeach
            </tr>
            <tr>
                <td>{{ __('Source of the information used') }}</td><td class="g-sub">{{ __('Source') }}</td><td></td>
                @foreach ($analogs as $analog)<td>{{ $analog->host() ?? __('Other') }}</td>@endforeach
            </tr>
            <tr>
                <td>{{ __('Link kept in the database') }}</td><td></td><td></td>
                @foreach ($analogs as $analog)<td class="g-link">@if ($analog->url)<a href="{{ $analog->url }}" target="_blank" rel="noopener noreferrer nofollow">{{ \Illuminate\Support\Str::limit($analog->url, 60) }}</a>@else—@endif</td>@endforeach
            </tr>
            <tr>
                <td>{{ __('Total land area') }}</td><td></td><td class="num">{{ $n2($item->land_area) }}</td>
                @foreach ($analogs as $analog)<td><input class="input g-num" name="analogs[{{ $analog->id }}][land_area]" value="{{ $typed($analog->adjustments['land_area'] ?? null, 2) }}" inputmode="decimal" aria-label="{{ __('Total land area') }}" @disabled($disabled)></td>@endforeach
            </tr>
            <tr>
                <td>{{ __('Total area') }}</td><td></td><td class="num">{{ $n2($item->total_area) }}</td>
                @foreach ($analogs as $analog)<td><input class="input g-num" name="analogs[{{ $analog->id }}][area]" value="{{ $typed($analog->area, 2) }}" inputmode="decimal" aria-label="{{ __('Total area') }}" @disabled($disabled || $analog->isPending())></td>@endforeach
            </tr>
            <tr class="g-price-row">
                <td>{{ __('Price per 1 m²') }}</td><td></td><td></td>
                @foreach ($analogs as $analog)<td class="num">{{ $n2($analog->pricePerSquareMeter()) }}</td>@endforeach
            </tr>

            {{-- Rights and conditions of the deal. --}}
            @include('admin.objects._analog-grid-element', ['title' => __('Property rights transferred'), 'sub' => __('Type of right'), 'subjectText' => __('Ownership'), 'texts' => $same(__('Ownership')), 'key' => 'rights'])
            @foreach ($plain as [$title, $sub, $text])
                @include('admin.objects._analog-grid-element', ['title' => $title, 'sub' => $sub, 'subjectText' => $text, 'texts' => $same($text), 'key' => null, 'previous' => 'rights'])
            @endforeach
            @include('admin.objects._analog-grid-element', ['title' => __('Rights to the land plot of the object'), 'sub' => __('Type of right'), 'subjectText' => $f['land_rights'] ?? '—', 'texts' => $same($f['land_rights'] ?? '—'), 'key' => null, 'previous' => 'rights'])
            @include('admin.objects._analog-grid-element', ['title' => __('Financing conditions'), 'sub' => __('Type of payment'), 'subjectText' => __('Prepayment'), 'texts' => $same(__('Prepayment')), 'key' => 'financing'])
            @include('admin.objects._analog-grid-element', ['title' => __('Form of payment'), 'sub' => __('Payment form'), 'subjectText' => __('Cash or transfer (sum, c.u.…)'), 'texts' => $same(__('Cash or transfer (sum, c.u.…)')), 'key' => null, 'previous' => 'financing'])
            <tr class="g-element-row">
                <td>{{ __('Sale conditions') }}</td><td class="g-sub">{{ __('State') }}</td><td></td>
                @foreach ($analogs as $analog)
                    @php $bargain = $analog->adjustment('bargain'); @endphp
                    <td>
                        <select class="input" name="analogs[{{ $analog->id }}][adjustments][bargain]" aria-label="{{ __('Sale conditions') }}" @disabled($disabled)>
                            <option value="{{ (float) config('fairvalue.analogs.bargain') }}" @selected(abs($bargain - (float) config('fairvalue.analogs.bargain')) < 0.0001)>{{ __('Asking price') }}</option>
                            <option value="0" @selected(abs($bargain) < 0.0001)>{{ __('Closed deal') }}</option>
                            @if (abs($bargain) >= 0.0001 && abs($bargain - (float) config('fairvalue.analogs.bargain')) >= 0.0001)<option value="{{ $bargain }}" selected>{{ Format::number($bargain, 2) }} %</option>@endif
                        </select>
                    </td>
                @endforeach
            </tr>
            <tr class="g-adj"><td>{{ __('Adjustment to the source') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ Format::number(1 + $analog->adjustment('bargain') / 100, 2) }}</td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'bargain') }}</td>@endforeach</tr>
            <tr><td>{{ __('Non-market relation of the sale price to the rent rate') }}</td><td></td><td>{{ __('None') }}</td>@foreach ($analogs as $analog)<td>{{ __('None') }}</td>@endforeach</tr>
            @include('admin.objects._analog-grid-element', ['title' => __('Subsidies or privileges for development'), 'sub' => '', 'subjectText' => __('None'), 'texts' => $same(__('None')), 'key' => null, 'previous' => 'bargain'])
            @include('admin.objects._analog-grid-element', ['title' => __('Costs after the purchase'), 'sub' => __('Costs of dismantling part of a building or a house, of removing the pollution of the land plot'), 'subjectText' => __('None'), 'texts' => $same(__('None')), 'key' => null, 'previous' => 'bargain'])

            {{-- Market conditions: the date of the offer and the dollar rate of that day against today's. --}}
            <tr class="g-element-row">
                <td>{{ __('Market conditions') }}</td><td class="g-sub">{{ __('Date of listing or sale') }}</td>
                <td>{{ $item->valuation_date?->format('d/m/Y') ?? now()->format('d/m/Y') }}</td>
                @foreach ($analogs as $analog)<td><input class="input" type="date" name="analogs[{{ $analog->id }}][listed_at]" value="{{ $analog->listed_at?->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" aria-label="{{ __('Date of listing or sale') }}" @disabled($disabled)></td>@endforeach
            </tr>
            <tr><td></td><td class="g-sub">{{ __('Exchange rate') }}</td><td class="num">{{ $n2($options['usd_rate'] ?? null) }}</td>@foreach ($analogs as $analog)<td class="num">{{ $n2($analog->usd_rate) }}</td>@endforeach</tr>
            <tr class="g-adj"><td>{{ __('Adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ ($r = $row($analog)) ? Format::number(1 + $r['steps']['market']['percent'] / 100, 4) : '—' }}</td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'market') }}</td>@endforeach</tr>

            {{-- Location: typed as a percentage. --}}
            <tr class="g-element-row">
                <td>{{ __('Location of the object') }}</td><td class="g-sub">{{ __('District where the object is') }}</td><td>{{ $item->locationLine() ?: '—' }}</td>
                @foreach ($analogs as $analog)<td><input class="input" name="analogs[{{ $analog->id }}][location]" value="{{ $analog->location }}" maxlength="250" aria-label="{{ __('District where the object is') }}" @disabled($disabled)></td>@endforeach
            </tr>
            <tr class="g-adj"><td>{{ __('Adjustment') }}, %</td><td></td><td></td>@foreach ($analogs as $analog)<td><input class="input g-num" name="analogs[{{ $analog->id }}][adjustments][location]" value="{{ $typed($analog->adjustment('location')) }}" inputmode="decimal" aria-label="{{ __('Location') }}, %" @disabled($disabled)></td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'location') }}</td>@endforeach</tr>

            {{-- Size: the difference of the size coefficients. --}}
            <tr class="g-element-row">
                <td>{{ __('Adjustment by the difference of the physical description (size of the building)') }}</td><td class="g-sub">{{ __('Total useful area') }}</td>
                <td class="num">{{ $n2($item->useful_area ?? $item->total_area) }}</td>
                @foreach ($analogs as $analog)<td class="num">{{ $n2($analog->area) }}</td>@endforeach
            </tr>
            <tr>
                <td></td><td class="g-sub">{{ __('Adjustment coefficient') }}</td>
                <td><input class="input g-num" name="comparative[size_coef]" value="{{ $typed($typedSubject['size_coef'] ?? null) }}" placeholder="{{ $typed(ComparativeCalculator::sizeCoefficient((float) $item->total_area)) }}" inputmode="decimal" aria-label="{{ __('Adjustment coefficient') }}" @disabled($disabled)></td>
                @foreach ($analogs as $analog)<td><input class="input g-num" name="analogs[{{ $analog->id }}][size_coef]" value="{{ $typed($analog->adjustments['size_coef'] ?? null) }}" placeholder="{{ $typed(ComparativeCalculator::sizeCoefficient((float) $analog->area)) }}" inputmode="decimal" aria-label="{{ __('Adjustment coefficient') }}" @disabled($disabled)></td>@endforeach
            </tr>
            <tr class="g-adj"><td>{{ __('Adjustment for the size') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ ($r = $row($analog)) ? Format::number($r['steps']['area']['percent'] / 100, 2) : '—' }}</td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'area') }}</td>@endforeach</tr>

            {{-- Walls, architecture: typed percentages. --}}
            <tr class="g-element-row">
                <td>{{ __('Building materials') }}</td><td class="g-sub">{{ __('Walls') }}</td><td>{{ $f['walls'] ?? ($item->wall_material ?: '—') }}</td>
                @foreach ($analogs as $analog)<td><input class="input" name="analogs[{{ $analog->id }}][wall_material]" value="{{ $analog->wall_material }}" maxlength="250" aria-label="{{ __('Walls') }}" @disabled($disabled)></td>@endforeach
            </tr>
            <tr class="g-adj"><td>{{ __('Adjustment') }}, %</td><td></td><td></td>@foreach ($analogs as $analog)<td><input class="input g-num" name="analogs[{{ $analog->id }}][adjustments][material]" value="{{ $typed($analog->adjustment('material')) }}" inputmode="decimal" aria-label="{{ __('Walls') }}, %" @disabled($disabled)></td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'material') }}</td>@endforeach</tr>
            <tr class="g-element-row">
                <td>{{ __('Architectural style') }}</td><td class="g-sub">{{ __('Architectural style') }}</td><td>{{ $f['architecture'] ?? '—' }}</td>
                @foreach ($analogs as $analog)<td>{{ $f['architecture'] ?? '—' }}</td>@endforeach
            </tr>
            <tr class="g-adj"><td>{{ __('Adjustment') }}, %</td><td></td><td></td>@foreach ($analogs as $analog)<td><input class="input g-num" name="analogs[{{ $analog->id }}][adjustments][architecture]" value="{{ $typed($analog->adjustment('architecture')) }}" inputmode="decimal" aria-label="{{ __('Architectural style') }}, %" @disabled($disabled)></td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'architecture') }}</td>@endforeach</tr>

            {{-- Condition: the wear of the repair state. --}}
            <tr class="g-element-row">
                <td>{{ __('By the condition of the building') }}</td><td class="g-sub">{{ __('Repair state') }}</td><td>{{ $f['repair'] ?? '—' }}</td>
                @foreach ($analogs as $analog)
                    <td>
                        <input class="input" name="analogs[{{ $analog->id }}][condition]" value="{{ $analog->condition }}" list="analog-repair" maxlength="250" aria-label="{{ __('Repair state') }}" @disabled($disabled)>
                    </td>
                @endforeach
            </tr>
            <tr><td></td><td></td><td class="num">{{ $wearOf($f['repair'] ?? null) }}</td>@foreach ($analogs as $analog)<td class="num">{{ $wearOf($analog->condition) }}</td>@endforeach</tr>
            <tr class="g-adj"><td>{{ __('Adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ ($r = $row($analog)) ? Format::number(1 + $r['steps']['condition']['percent'] / 100, 3) : '—' }}</td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'condition') }}</td>@endforeach</tr>

            <tr class="g-element-row">
                <td>{{ __('Functional usefulness') }}</td><td class="g-sub">{{ __('Name of the object') }}</td><td>{{ $item->typeLabel() }}</td>
                @foreach ($analogs as $analog)<td>{{ $item->typeLabel() }}</td>@endforeach
            </tr>
            <tr class="g-adj"><td>{{ __('Adjustment') }}, %</td><td></td><td></td>@foreach ($analogs as $analog)<td><input class="input g-num" name="analogs[{{ $analog->id }}][adjustments][function]" value="{{ $typed($analog->adjustment('function')) }}" inputmode="decimal" aria-label="{{ __('Functional usefulness') }}, %" @disabled($disabled)></td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'function') }}</td>@endforeach</tr>

            {{-- Land: its price per m², the coefficients of the land areas and what is taken off. --}}
            <tr class="g-element-row">
                <td>{{ __('Size of the land plot and the area of the buildings on it') }}</td><td class="g-sub">{{ __('Price of 1 m² of land') }}</td>
                <td><input class="input g-num" name="comparative[land_price]" value="{{ $subject['land_price'] > 0 ? $typed($subject['land_price'], 2) : '' }}" inputmode="decimal" aria-label="{{ __('Price of 1 m² of land') }}" @disabled($disabled)></td>
                @foreach ($analogs as $analog)<td class="num">{{ $subject['land_price'] > 0 ? $n2($subject['land_price']) : '—' }}</td>@endforeach
            </tr>
            <tr><td></td><td class="g-sub">{{ __('Total land area, m²') }}</td><td class="num">{{ $n2($item->land_area) }}</td>@foreach ($analogs as $analog)<td class="num">{{ $n2($analog->adjustments['land_area'] ?? null) }}</td>@endforeach</tr>
            <tr>
                <td></td><td class="g-sub">{{ __('Adjustment for the size of the total land area') }}</td>
                <td><input class="input g-num" name="comparative[land_coef]" value="{{ $typed($subject['land_coef']) }}" inputmode="decimal" aria-label="{{ __('Adjustment for the size of the total land area') }}" @disabled($disabled)></td>
                @foreach ($analogs as $analog)<td><input class="input g-num" name="analogs[{{ $analog->id }}][land_coef]" value="{{ $typed($analog->adjustments['land_coef'] ?? null) }}" inputmode="decimal" aria-label="{{ __('Adjustment for the size of the total land area') }}" @disabled($disabled)></td>@endforeach
            </tr>
            <tr class="g-adj"><td>{{ __('Adjustment in percent') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ ($r = $row($analog)) ? Format::number($r['steps']['land']['percent'] / 100, 2) : '—' }}</td>@endforeach</tr>
            <tr class="g-adj"><td>{{ __('Adjustment in sum') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ ($r = $row($analog)) ? $n2($r['land']['sum']) : '—' }}</td>@endforeach</tr>
            <tr><td>{{ __('Value of 1 m² of land after the adjustment') }}</td><td></td><td class="num">{{ $subject['land_price'] > 0 ? $n2($subject['land_price']) : '—' }}</td>@foreach ($analogs as $analog)<td class="num">{{ ($r = $row($analog)) && $subject['land_price'] > 0 ? $n2($r['land']['after']) : '—' }}</td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² of the total area after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'land') }}</td>@endforeach</tr>

            <tr class="g-element-row">
                <td>{{ __('Quality of construction') }}</td><td class="g-sub">{{ __('Completion of construction') }}</td><td class="num">{{ $subject['completion'] !== null ? Format::number($subject['completion'], 0).' %' : '—' }}</td>
                @foreach ($analogs as $analog)<td><input class="input g-num" name="analogs[{{ $analog->id }}][completion]" value="{{ $typed($analog->adjustments['completion'] ?? null) }}" inputmode="decimal" placeholder="100" aria-label="{{ __('Completion of construction') }}, %" @disabled($disabled)></td>@endforeach
            </tr>
            <tr class="g-adj"><td>{{ __('Adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $pct($analog, 'completion') }}</td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'completion') }}</td>@endforeach</tr>
            @include('admin.objects._analog-grid-element', ['title' => __('Improvement'), 'sub' => __('State'), 'subjectText' => $f['landscaping'] ?? __('Improved'), 'texts' => $same(__('Improved')), 'key' => null, 'previous' => 'completion'])

            {{-- Utilities: one percent for each engineering communication. --}}
            <tr class="g-element-row">
                <td>{{ __('Amenities of the object') }}</td><td class="g-sub">{{ __('Engineering communications') }}</td><td>{{ $commList($subject['communications']) }}</td>
                @foreach ($analogs as $analog)
                    <td class="g-comms">
                        <input type="hidden" name="analogs[{{ $analog->id }}][communications_shown]" value="1">
                        @foreach ($comms as $value => $label)
                            <label class="comm-chip"><input type="checkbox" name="analogs[{{ $analog->id }}][communications][]" value="{{ $value }}" @checked(in_array($value, (array) $analog->communications, true)) @disabled($disabled)><span>{{ $label }}</span></label>
                        @endforeach
                    </td>
                @endforeach
            </tr>
            <tr><td></td><td></td><td class="num">{{ count($subject['communications']) }} %</td>@foreach ($analogs as $analog)<td class="num">{{ $analog->communications === null ? '—' : count($analog->communications).' %' }}</td>@endforeach</tr>
            <tr class="g-adj"><td>{{ __('Adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ ($r = $row($analog)) ? Format::number(1 + $r['steps']['amenities']['percent'] / 100, 2) : '—' }}</td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'amenities') }}</td>@endforeach</tr>

            {{-- Elements the portal lists without changing the price; "other" takes a typed percentage. --}}
            @foreach ([
                [__('Ecology of the plot'), __('State'), __('No information available')],
                [__('Characteristics that influence the income of the object'), __('Operating costs'), __('No information available')],
                [__('Quality of management'), __('State'), __('No information available')],
                [__('Reliability of the tenants'), __('State'), __('No information available')],
                [__('Discounts on the rent'), __('State'), __('No information available')],
                [__('Conditions of the lease contract'), __('State'), __('No information available')],
                [__('End of the lease contract'), __('State'), __('No information available')],
                [__('Extension of the lease'), __('State'), __('No information available')],
                [__('Agreement of the object with the conditions of its highest and best use'), __('Agreement of the object'), __('More effective in the present kind of activity')],
            ] as [$title, $sub, $text])
                @include('admin.objects._analog-grid-element', ['title' => $title, 'sub' => $sub, 'subjectText' => $text, 'texts' => $same($text), 'key' => null, 'previous' => 'amenities'])
            @endforeach
            <tr class="g-element-row">
                <td>{{ __('Components that are not real estate') }}</td><td class="g-sub">{{ __('State') }}</td><td>{{ __('No information available') }}</td>
                @foreach ($analogs as $analog)<td>{{ __('No information available') }}</td>@endforeach
            </tr>
            <tr class="g-adj"><td>{{ __('Adjustment') }}, %</td><td></td><td></td>@foreach ($analogs as $analog)<td><input class="input g-num" name="analogs[{{ $analog->id }}][adjustments][other]" value="{{ $typed($analog->adjustment('other')) }}" inputmode="decimal" aria-label="{{ __('Other') }}, %" @disabled($disabled)></td>@endforeach</tr>
            <tr class="g-after"><td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ $after($analog, 'other') }}</td>@endforeach</tr>

            {{-- The weights. --}}
            <tr class="g-sum"><td>{{ __('Number of adjustments') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ ($r = $row($analog)) ? $r['count'] : '—' }}</td>@endforeach</tr>
            <tr class="g-sum"><td>{{ __('Shares of the coefficient') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ ($r = $row($analog)) ? Format::number($r['weight'] * 100, 8).' %' : '—' }}</td>@endforeach</tr>
            <tr class="g-sum"><td>{{ __('Price after the adjustments with the shares taken into account') }}</td><td></td><td></td>@foreach ($analogs as $analog)<td class="num">{{ ($r = $row($analog)) ? $n2($r['weighted']) : '—' }}</td>@endforeach</tr>
            <tr class="g-final"><td colspan="2">{{ __('Final value of 1 m² of the object of valuation') }}</td><td colspan="{{ $span - 2 }}" class="num">{{ $result['price_m2'] !== null ? $n2($result['price_m2']).' '.__('sum') : '—' }}</td></tr>
            <tr class="g-final"><td colspan="2">{{ __('Value of 1 m² of the land plot') }}</td><td colspan="{{ $span - 2 }}" class="num">{{ $subject['land_price'] > 0 ? $n2($subject['land_price']) : '—' }}</td></tr>
            <tr class="g-final"><td colspan="2">{{ __('Total area of the object of valuation, m²') }}</td><td colspan="{{ $span - 2 }}" class="num">{{ $n2($item->total_area) }}</td></tr>
            <tr class="g-final"><td colspan="2">{{ __('Final value of the object of valuation') }}</td><td colspan="{{ $span - 2 }}" class="num">{{ $result['building'] !== null ? $n2($result['building']).' '.__('sum') : '—' }}</td></tr>
            <tr class="g-final"><td colspan="2">{{ __('Total area of the land plot, m²') }}</td><td colspan="{{ $span - 2 }}" class="num">{{ $n2($item->land_area) }}</td></tr>
            <tr class="g-final"><td colspan="2">{{ __('Value of the land plot') }}</td><td colspan="{{ $span - 2 }}" class="num">{{ $n2($result['land_value']) }}</td></tr>
            <tr class="g-final g-total"><td colspan="2">{{ __('Total value of the object of valuation') }}</td><td colspan="{{ $span - 2 }}" class="num">{{ $result['value'] !== null ? $n2($result['value']).' '.__('sum') : '—' }}</td></tr>
        </tbody>
    </table>
</div>
<datalist id="analog-repair">@foreach ($conditions as $option)<option value="{{ $option }}">@endforeach</datalist>
