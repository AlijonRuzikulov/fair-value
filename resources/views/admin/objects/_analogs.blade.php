@php
    use App\Models\Analog;
    use App\Reports\RealEstateReport;
    use App\Support\ComparativeCalculator;
    use App\Support\Format;
    use Illuminate\Support\Facades\Gate;

    $analogs = $item->analogs;
    $options = RealEstateReport::comparativeOptions($item);
    $result = ComparativeCalculator::calculate((float) $item->total_area, $analogs, $options);
    $pending = $analogs->filter->isPending();
    $canEdit = Gate::allows('edit-valuation', $item);
    $adjustmentLabels = [
        'rights' => __('Rights'), 'financing' => __('Financing'), 'bargain' => __('Bargaining'), 'market' => __('Market conditions'),
        'location' => __('Location'), 'area' => __('Area'), 'floor' => __('Floor'), 'material' => __('Walls'),
        'condition' => __('Condition'), 'amenities' => __('Amenities'), 'other' => __('Other'),
    ];
    $percent = fn ($value) => ($value > 0 ? '+' : '').Format::number($value, 2).' %';
    $sites = implode(', ', config('fairvalue.analogs.domains'));
    $number = fn ($value, $decimals = 0) => $value === null ? '' : Format::number($value, $decimals);
@endphp
<x-card id="analogs" :title="__('Comparative approach — analogues')" icon="scale">
    @if ($result['value'] !== null)
        <x-slot:actions><span class="badge badge-brand">{{ Format::money($result['value']) }}</span></x-slot:actions>
    @endif

    @if ($canEdit)
        <div class="analog-tools">
            <form method="post" action="{{ route('admin.analogs.store', [$analogType, $item->id]) }}" class="analog-tool" @if (session('find_analogs')) data-auto-submit @endif>
                @csrf
                <input type="hidden" name="mode" value="search">
                <p class="analog-tool-title"><x-icon name="sparkles" :size="18" /> {{ __('Find automatically') }}</p>
                <p class="field-hint">
                    @if ($aiAvailable)
                        {{ __('AI looks for offers similar to this object (type, area, rooms, district) on :sites and reads them. It takes up to a minute.', ['sites' => $sites]) }}
                    @else
                        {{ __('Needs an AI key: ANTHROPIC_API_KEY in the .env file. Until then, paste links to listings.') }}
                    @endif
                </p>
                <button class="btn btn-primary" type="submit" @disabled(! $aiAvailable) data-busy="{{ __('Searching…') }}"><x-icon name="search" :size="16" /> {{ __('Find analogues') }}</button>
            </form>

            <form method="post" action="{{ route('admin.analogs.store', [$analogType, $item->id]) }}" class="analog-tool">
                @csrf
                <input type="hidden" name="mode" value="links">
                <label class="analog-tool-title" for="analog-links"><x-icon name="external-link" :size="18" /> {{ __('Add by links') }}</label>
                <textarea id="analog-links" class="input" name="links" rows="3" placeholder="https://www.olx.uz/d/obyavlenie/…" required></textarea>
                <p class="field-hint">{{ __('Links to listings or to a page of search results with your filters (:sites), one per line.', ['sites' => $sites]) }}</p>
                <button class="btn" type="submit"><x-icon name="plus" :size="16" /> {{ __('Add links') }}</button>
            </form>
        </div>

        <details class="analog-manual" @if ($errors->hasAny(['title', 'price', 'area', 'currency'])) open @endif>
            <summary><x-icon name="pencil" :size="16" /> {{ __('Add an offer by hand') }}</summary>
            <form method="post" action="{{ route('admin.analogs.store', [$analogType, $item->id]) }}" class="form-grid">
                @csrf
                <input type="hidden" name="mode" value="manual">
                <x-field name="title" :label="__('Name')" span="6" required :placeholder="__('2-room flat, Chilonzor')" />
                <x-field name="url" type="url" :label="__('Link')" span="6" placeholder="https://" />
                <x-field name="price" :label="__('Price')" money span="3" required />
                <x-select name="currency" :label="__('Currency')" :options="['UZS' => __('sum'), 'USD' => 'USD']" value="USD" span="2" />
                <x-field name="area" :label="__('Area, m²')" money span="2" required />
                <x-field name="rooms" type="number" :label="__('Rooms')" span="1" />
                <x-field name="floor" type="number" :label="__('Floor')" span="2" />
                <x-field name="floors_total" type="number" :label="__('Floors')" span="2" />
                <x-field name="location" :label="__('Location')" span="9" />
                <div class="field span-3" style="justify-content: flex-end"><button class="btn btn-primary" type="submit"><x-icon name="plus" :size="16" /> {{ __('Add') }}</button></div>
            </form>
        </details>
    @endif

    @if ($pending->isNotEmpty())
        <div class="analog-progress" role="status" data-analog-fill='@json($pending->map(fn ($a) => route('admin.analogs.fill', $a))->values())' data-failed="{{ __('Some pages could not be read. Refresh the page to see the result.') }}">
            <span class="spinner" aria-hidden="true"></span>
            {{ __('Reading listings') }}: <strong><span data-progress>0</span> / {{ $pending->count() }}</strong>
        </div>
    @endif

    @if ($analogs->isEmpty())
        <x-empty icon="scale" :title="__('No analogues yet')" :text="__('Similar offers are compared with this object: the price per m² is adjusted for bargaining, area, floor, location and condition, and averaged with weights.')" />
    @else
        <form method="post" action="{{ route('admin.analogs.update', [$analogType, $item->id]) }}" id="analogs-form">
            @csrf @method('put')
            <div class="table-wrap">
                <table class="table analog-list">
                    <thead>
                        <tr>
                            <th title="{{ __('Used in the calculation') }}"><x-icon name="check" :size="14" /></th>
                            <th>{{ __('Offer') }}</th>
                            <th>{{ __('Price') }}</th>
                            <th>{{ __('Area, m²') }}</th>
                            <th>{{ __('Floor') }}</th>
                            <th class="t-right">{{ __('Price per m²') }}</th>
                            <th class="t-right">{{ __('Adjusted per m²') }}</th>
                            <th class="t-right">{{ __('Weight') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    @foreach ($analogs as $analog)
                        @php $row = $result['rows'][$analog->id] ?? null; $name = "analogs[{$analog->id}]"; @endphp
                        <tbody @class(['analog-row', 'is-off' => ! $row])>
                            <tr>
                                <td><input type="checkbox" name="{{ $name }}[included]" value="1" @checked($analog->included) @disabled(! $canEdit || $analog->isPending()) aria-label="{{ __('Used in the calculation') }}"></td>
                                <td class="analog-offer">
                                    <span class="analog-no">№{{ $loop->iteration }}</span>
                                    @if ($analog->url)
                                        <a href="{{ $analog->url }}" target="_blank" rel="noopener noreferrer nofollow">{{ $analog->title ?: $analog->host() }} <x-icon name="external-link" :size="12" /></a>
                                    @else
                                        <strong>{{ $analog->title }}</strong>
                                    @endif
                                    <span class="cell-sub">{{ collect([$analog->host(), $analog->rooms ? __(':n rooms', ['n' => $analog->rooms]) : null, $analog->location, $analog->listed_at?->format('d.m.Y')])->filter()->implode(' · ') }}</span>
                                    @if ($analog->isPending())
                                        <span class="badge badge-info">{{ __('Reading…') }}</span>
                                    @elseif ($analog->status === Analog::FAILED)
                                        <span class="badge badge-warning">{{ __('Not read') }}</span>
                                    @endif
                                    @if ($analog->note)<span class="analog-note">{{ $analog->note }}</span>@endif
                                    @if ($canEdit && ! $analog->isPending() && $analog->url)
                                        <details class="analog-paste" @if ($analog->status === Analog::FAILED) open @endif>
                                            <summary>{{ __('Paste the text of the listing') }}</summary>
                                            <p class="field-hint">{{ __('Open the listing in your browser, press Ctrl+A and Ctrl+C (on a phone: select all and copy) and paste it here — the price, area, floor and the rest are read from the text.') }}</p>
                                            <textarea class="input" name="text" rows="4" form="analog-text-{{ $analog->id }}" maxlength="60000" required aria-label="{{ __('Paste the text of the listing') }}"></textarea>
                                            <button class="btn btn-sm btn-primary" type="submit" form="analog-text-{{ $analog->id }}"><x-icon name="file-search" :size="14" /> {{ __('Read the text') }}</button>
                                        </details>
                                    @endif
                                </td>
                                @if ($analog->isPending())
                                    <td colspan="6" class="faint">—</td>
                                @else
                                    <td>
                                        <div class="analog-price">
                                            <input class="input" name="{{ $name }}[price]" value="{{ $number($analog->price) }}" data-money inputmode="decimal" aria-label="{{ __('Price') }}" @disabled(! $canEdit)>
                                            <select class="input" name="{{ $name }}[currency]" aria-label="{{ __('Currency') }}" @disabled(! $canEdit)>
                                                <option value="UZS" @selected($analog->currency !== 'USD')>{{ __('sum') }}</option>
                                                <option value="USD" @selected($analog->currency === 'USD')>USD</option>
                                            </select>
                                        </div>
                                        @if ($analog->currency === 'USD' && $analog->price_uzs)<span class="cell-sub">≈ {{ Format::money($analog->price_uzs) }}</span>@endif
                                    </td>
                                    <td><input class="input w-80" name="{{ $name }}[area]" value="{{ $number($analog->area, 2) }}" data-money inputmode="decimal" aria-label="{{ __('Area, m²') }}" @disabled(! $canEdit)></td>
                                    <td class="analog-floor">
                                        <input class="input" name="{{ $name }}[floor]" value="{{ $analog->floor }}" inputmode="numeric" aria-label="{{ __('Floor') }}" @disabled(! $canEdit)>
                                        <span>/</span>
                                        <input class="input" name="{{ $name }}[floors_total]" value="{{ $analog->floors_total }}" inputmode="numeric" aria-label="{{ __('Floors') }}" @disabled(! $canEdit)>
                                    </td>
                                    <td class="t-right cell-num">{{ $analog->pricePerSquareMeter() ? Format::number($analog->pricePerSquareMeter()) : '—' }}</td>
                                    <td class="t-right cell-num strong">{{ $row ? Format::number($row['adjusted_m2']) : '—' }}</td>
                                    <td class="t-right cell-num">{{ $row ? Format::number($row['weight'] * 100, 1).' %' : '—' }}</td>
                                @endif
                                <td>
                                    @if ($canEdit)
                                        <button class="btn btn-sm btn-icon" type="submit" form="analog-delete-{{ $analog->id }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash-2" :size="15" /></button>
                                    @endif
                                </td>
                            </tr>
                            @unless ($analog->isPending())
                                <tr class="analog-adj">
                                    <td></td>
                                    <td colspan="8"><div class="analog-adj-inner">
                                        <span class="analog-adj-title">{{ __('Adjustments, %') }}:</span>
                                        @foreach ($adjustmentLabels as $key => $label)
                                            @if ($key === 'market' && $analog->currency === 'USD')
                                                <span class="adj-field" title="{{ __('Worked out from the dollar rates on the offer date and on the valuation date.') }}">
                                                    <span>{{ $label }}</span>
                                                    <output class="adj-auto">{{ $row ? $percent($row['steps']['market']['percent']) : '—' }}</output>
                                                </span>
                                            @else
                                                <label class="adj-field">
                                                    <span>{{ $label }}</span>
                                                    <input class="input adj-input" name="{{ $name }}[adjustments][{{ $key }}]" value="{{ rtrim(rtrim(number_format($analog->adjustment($key), 2, '.', ''), '0'), '.') }}" inputmode="decimal" @disabled(! $canEdit)>
                                                </label>
                                            @endif
                                        @endforeach
                                        @if ($row)<span class="adj-total" title="{{ __('Adjustments are applied one after another.') }}">= {{ $percent($row['total']) }}</span>@endif
                                    </div>
                                    <div class="analog-adj-inner analog-more">
                                        <label class="adj-field"><span>{{ __('Offer date') }}</span><input class="input w-140" type="date" name="{{ $name }}[listed_at]" value="{{ $analog->listed_at?->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" @disabled(! $canEdit)></label>
                                        <label class="adj-field"><span>{{ __('Condition') }}</span><input class="input w-140" name="{{ $name }}[condition]" value="{{ $analog->condition }}" list="analog-conditions" @disabled(! $canEdit)></label>
                                        <label class="adj-field"><span>{{ __('Walls') }}</span><input class="input w-120" name="{{ $name }}[wall_material]" value="{{ $analog->wall_material }}" list="analog-walls" @disabled(! $canEdit)></label>
                                        @if ($item instanceof \App\Models\RealEstate)
                                            <span class="adj-field analog-comms" title="{{ __('Engineering communications of the analogue: each one the object has and the analogue lacks adds 1 % (Amenities).') }}">
                                                <span>{{ __('Communications') }}</span>
                                                <input type="hidden" name="{{ $name }}[communications_shown]" value="1">
                                                <span class="analog-comm-list">
                                                    @foreach (\App\Enums\RealEstateFeature::communications() as $comm)
                                                        <label class="comm-chip"><input type="checkbox" name="{{ $name }}[communications][]" value="{{ $comm->value }}" @checked(in_array($comm->value, (array) $analog->communications, true)) @disabled(! $canEdit)><span>{{ $comm->label() }}</span></label>
                                                    @endforeach
                                                </span>
                                            </span>
                                        @endif
                                        @if ($analog->currency === 'USD' && $analog->usd_rate)<span class="faint">USD {{ Format::number($analog->usd_rate, 2) }}</span>@endif
                                        <span class="analog-photo">
                                            @if ($analog->photo_path)
                                                <a href="{{ route('admin.analogs.photo', $analog) }}" target="_blank"><img src="{{ route('admin.analogs.photo', $analog) }}" alt="{{ __('Photo') }}" loading="lazy"></a>
                                                @if ($canEdit)<button class="btn btn-sm btn-icon" type="submit" form="analog-photo-delete-{{ $analog->id }}" title="{{ __('Delete photo') }}" aria-label="{{ __('Delete photo') }}"><x-icon name="x" :size="14" /></button>@endif
                                            @endif
                                            @if ($canEdit)
                                                <label class="btn btn-sm"><x-icon name="image" :size="14" /> {{ $analog->photo_path ? __('Replace photo') : __('Add photo') }}
                                                    <input type="file" name="photo" form="analog-photo-{{ $analog->id }}" accept="image/jpeg,image/png,image/webp" hidden data-submit-on-change>
                                                </label>
                                            @endif
                                        </span>
                                    </div></td>
                                </tr>
                            @endunless
                        </tbody>
                    @endforeach
                </table>
            </div>
            <p class="field-hint" style="margin-top: 10px">{{ __('Adjustments are percentages applied to the price per m² of the analogue one after another, in the order of the elements of comparison (national standard No. 10): positive if the analogue is worse than the object, negative if it is better. Market conditions of a dollar price come from the exchange rates. The suggested values are a starting point — the appraiser checks every one.') }}</p>
            <datalist id="analog-conditions"><option value="{{ __('Excellent') }}"><option value="{{ __('Good') }}"><option value="{{ __('Satisfactory') }}"><option value="{{ __('Needs repair') }}"></datalist>
            <datalist id="analog-walls"><option value="{{ __('Brick') }}"><option value="{{ __('Panel') }}"><option value="{{ __('Monolith') }}"><option value="{{ __('Block') }}"></datalist>
            @if ($canEdit)
                <div class="analog-actions">
                    <label class="adj-field analog-weighting"><span>{{ __('Weights of the analogues') }}</span>
                        <select class="input" name="weighting">
                            <option value="count" @selected($result['weighting'] === 'count')>{{ __('By the number of adjustments') }}</option>
                            <option value="count10" @selected($result['weighting'] === 'count10')>{{ __('By the number of adjustments, in whole tens of percent') }}</option>
                            <option value="size" @selected($result['weighting'] === 'size')>{{ __('By the size of adjustments') }}</option>
                        </select>
                    </label>
                    <button class="btn" type="submit"><x-icon name="refresh-cw" :size="16" /> {{ __('Save and recalculate') }}</button>
                </div>
            @endif
        </form>

        @foreach ($analogs as $analog)
            <form method="post" action="{{ route('admin.analogs.destroy', $analog) }}" id="analog-delete-{{ $analog->id }}" data-confirm="{{ __('Delete this analogue?') }}" hidden>@csrf @method('delete')</form>
            <form method="post" action="{{ route('admin.analogs.photo.store', $analog) }}" id="analog-photo-{{ $analog->id }}" enctype="multipart/form-data" hidden>@csrf</form>
            <form method="post" action="{{ route('admin.analogs.text', $analog) }}" id="analog-text-{{ $analog->id }}" hidden>@csrf</form>
            @if ($analog->photo_path)
                <form method="post" action="{{ route('admin.analogs.photo.destroy', $analog) }}" id="analog-photo-delete-{{ $analog->id }}" hidden>@csrf @method('delete')</form>
            @endif
        @endforeach

        <div class="analog-summary">
            <div class="result-box"><span>{{ __('Analogues used') }}</span><strong>{{ $result['count'] }}</strong></div>
            <div class="result-box"><span>{{ __('Weighted price per m²') }}</span><strong>{{ $result['price_m2'] ? Format::money(round($result['price_m2'])) : '—' }}</strong></div>
            <div @class(['result-box', 'is-warning' => ($result['variation'] ?? 0) > 33])>
                <span>{{ __('Spread of analogues') }}</span>
                <strong>{{ $result['variation'] !== null ? Format::number($result['variation'], 1).' %' : '—' }}</strong>
                @if (($result['variation'] ?? 0) > 33)<small>{{ __('Over 33 %: the offers are too different, check them.') }}</small>@endif
            </div>
            <div class="result-box is-main">
                <span>{{ __('Value by the comparative approach') }}</span>
                <strong>{{ $result['value'] !== null ? Format::money($result['value']) : '—' }}</strong>
                @if ($result['value'] !== null)<small>{{ Format::money(round($result['price_m2'])) }} × {{ Format::number($item->total_area, 2) }} {{ __('m²') }}</small>@endif
            </div>
        </div>

        @if ($canEdit && $result['value'] !== null)
            <form method="post" action="{{ route('admin.analogs.apply', [$analogType, $item->id]) }}" class="analog-apply">
                @csrf
                <span class="muted">{{ __('Now in the object') }}: <strong>{{ $item->value_comparative !== null ? Format::money($item->value_comparative) : '—' }}</strong></span>
                <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save as the comparative value') }}</button>
            </form>
        @endif
    @endif
</x-card>
