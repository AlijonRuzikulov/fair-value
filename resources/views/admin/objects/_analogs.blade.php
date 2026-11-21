@php
    use App\Models\Analog;
    use App\Reports\RealEstateReport;
    use App\Support\ComparativeCalculator;
    use App\Support\Format;
    use Illuminate\Support\Facades\Gate;

    $analogs = $item->analogs;
    $options = RealEstateReport::comparativeOptions($item);
    $result = ComparativeCalculator::calculate($item->comparedArea(), $analogs, $options);
    $pending = $analogs->filter->isPending();
    $canEdit = Gate::allows('edit-valuation', $item);
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
                <p class="field-hint">{{ __('Links to listings or to a page of search results with your filters (:sites), one per line.', ['sites' => $sites]) }} {{ __('Links to other sites are added too: paste the text of the listing under them.') }}</p>
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
        <x-empty icon="scale" :title="__('No analogues yet')" :text="__('Similar offers are compared with this object element by element; their adjusted prices per m² are averaged with weights.')" />
    @else
        <form method="post" action="{{ route('admin.analogs.update', [$analogType, $item->id]) }}" id="analogs-form">
            @csrf @method('put')
            @include('admin.objects._analog-grid')
            @if ($canEdit)
                <div class="analog-actions">
                    <label class="adj-field analog-weighting"><span>{{ __('Weights of the analogues') }}</span>
                        <select class="input" name="weighting">
                            <option value="count" @selected($result['weighting'] === 'count')>{{ __('By the number of adjustments') }}</option>
                            <option value="count10" @selected($result['weighting'] === 'count10')>{{ __('By the number of adjustments, in whole tens of percent') }}</option>
                            <option value="size" @selected($result['weighting'] === 'size')>{{ __('By the size of adjustments') }}</option>
                        </select>
                    </label>
                    <label class="adj-field analog-index" title="{{ __('Market conditions follow the prices from the offer date to the valuation date: (1 + index)^months. Empty — not used.') }}"><span>{{ __('Price index, % per month') }}</span>
                        <input class="input" name="market_index" value="{{ $options['index'] !== null ? rtrim(rtrim(number_format($options['index'], 2, '.', ''), '0'), '.') : '' }}" inputmode="decimal" placeholder="—">
                    </label>
                    <button class="btn btn-success" type="submit"><x-icon name="refresh-cw" :size="16" /> {{ __('Save and recalculate') }}</button>
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

        @if ($canEdit && $result['value'] !== null)
            <form method="post" action="{{ route('admin.analogs.apply', [$analogType, $item->id]) }}" class="analog-apply">
                @csrf
                <span class="muted">{{ __('Now in the object') }}: <strong>{{ $item->value_comparative !== null ? Format::money($item->value_comparative) : '—' }}</strong></span>
                <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save as the comparative value') }}</button>
            </form>
        @endif
    @endif
</x-card>
