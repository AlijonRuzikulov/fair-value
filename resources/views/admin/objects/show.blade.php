@php
    use App\Support\Format;
    use App\Enums\PersonRole;
    $values = collect($item->approachList())->mapWithKeys(fn ($a) => [$a->label() => $item->{$a->column()}])->filter(fn ($v) => $v !== null);
    $maxValue = max(1, (float) $values->max());
@endphp
<x-layouts.admin :title="$item->title" :subtitle="$module->singular()" :nav="$nav" :icon="$module->icon()" :back="route($module->routeName('index'), $module->routeParams($item))">
    <x-slot:actions>
        <a class="btn" href="{{ route($module->routeName('print'), $item->id) }}" target="_blank"><x-icon name="printer" :size="16" /> {{ __('Print') }}</a>
        @can('edit-valuation', $item)
            <a class="btn btn-primary" href="{{ route($module->routeName('edit'), $item->id) }}"><x-icon name="pencil" :size="16" /> {{ __('Edit') }}</a>
        @endcan
        @can('delete-records')
            <form method="post" action="{{ route($module->routeName('destroy'), $item->id) }}" data-confirm="{{ __('Delete this record? It can be restored by an administrator.') }}">
                @csrf @method('delete')
                <button class="btn btn-danger btn-icon" type="submit" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash-2" :size="16" /></button>
            </form>
        @endcan
    </x-slot:actions>

    <div class="grid grid-main">
        <div class="stack">
            <x-card :title="__('Object details')" icon="file-text">
                <x-slot:actions><x-status :status="$item->status" /></x-slot:actions>
                <dl class="details">
                    @foreach ($module->facts($item) as $label => $value)
                        @if (filled($value))
                            <x-detail :label="$label" :value="$value" :wide="mb_strlen((string) $value) > 60" />
                        @endif
                    @endforeach
                    <x-detail :label="__('Location')" :value="$item->locationLine()" wide />
                </dl>
            </x-card>

            @includeIf('admin.'.$module->slugName().'._show')

            <x-card :title="__('Persons')" icon="users" flush>
                @if ($item->clients->isEmpty())
                    <x-empty icon="users" :title="__('No persons linked')" />
                @else
                    <ul class="mini-list">
                        @foreach ($item->clients as $client)
                            <li>
                                <span class="mini-icon" style="--c: var({{ $client->isLegal() ? '--c-violet' : '--c-sky' }})"><x-icon :name="$client->isLegal() ? 'building-2' : 'user'" :size="18" /></span>
                                <div class="grow">
                                    <a href="{{ route('admin.clients.show', $client) }}">{{ $client->title }}</a>
                                    <small>{{ collect([$client->document, $client->phone ? Format::phone($client->phone) : null])->filter()->implode(' · ') }}</small>
                                </div>
                                <span class="badge badge-brand">{{ PersonRole::labelFor($client->pivot->role) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @include('admin.objects._attachments', ['owner' => $item])

            @if ($item->notes)
                <x-card :title="__('Notes')" icon="message-circle">
                    <p style="white-space: pre-line">{{ $item->notes }}</p>
                </x-card>
            @endif
        </div>

        <aside class="stack">
            <div class="hero-card">
                <p class="label">{{ $module->usesApproaches() ? __('Market value') : __('Total damage') }}</p>
                <p class="value">{{ $item->market_value !== null ? Format::money($item->market_value) : '—' }}</p>
                @if ($usdValue)
                    <p class="sub">≈ {{ Format::number($usdValue) }} USD · {{ __('at the Central Bank rate') }}</p>
                @endif
                <div class="split">
                    <div class="split-row"><span>{{ __('Valuation date') }}</span><strong>{{ Format::date($item->valuation_date) }}</strong></div>
                    <div class="split-row"><span>{{ __('Purpose') }}</span><strong>{{ $item->purposeLabel() ?? '—' }}</strong></div>
                    @if ($item instanceof \App\Models\RealEstate && $item->pricePerSquareMeter())
                        <div class="split-row"><span>{{ __('Price per m²') }}</span><strong>{{ Format::money($item->pricePerSquareMeter()) }}</strong></div>
                    @endif
                </div>
            </div>

            @if ($module->usesApproaches())
                <x-card :title="__('Approaches')" icon="scale">
                    @if ($values->isEmpty())
                        <div class="chips">
                            @forelse ($item->approachList() as $approach)
                                <span class="chip">{{ $approach->label() }}</span>
                            @empty
                                <span class="muted">{{ __('Approaches are not specified') }}</span>
                            @endforelse
                        </div>
                    @else
                        <div class="approach-bars">
                            @foreach ($values as $label => $value)
                                <div>
                                    <div class="approach-bar-head"><span>{{ $label }}</span><span class="num">{{ Format::money($value) }}</span></div>
                                    <div class="approach-bar-track"><div class="approach-bar-fill" style="width: {{ round((float) $value / $maxValue * 100, 1) }}%"></div></div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            @endif

            <x-card :title="__('Report verification')" icon="qr-code">
                <div class="verify-box">
                    <div class="qr" data-qr="{{ $item->verifyUrl() }}" role="img" aria-label="QR"></div>
                    <div>
                        <p class="muted">{{ __('Verification code') }}</p>
                        <p class="code" style="font-size: 16px">{{ $item->verify_code }}</p>
                        <p class="muted" style="margin-top: 6px">{{ __('Report') }} № <strong style="color: var(--ink)">{{ $item->report_number ?? '—' }}</strong></p>
                        <a class="btn btn-sm" style="margin-top: 10px" href="{{ $item->verifyUrl() }}" target="_blank"><x-icon name="external-link" :size="14" /> {{ __('Public page') }}</a>
                    </div>
                </div>
            </x-card>

            <x-card :title="__('Contract and report')" icon="file-check">
                <dl class="details" style="grid-template-columns: 1fr">
                    <x-detail :label="__('Appraiser')" :value="$item->appraiser?->name" />
                    <x-detail :label="__('Contract')" :value="trim(($item->contract_number ?? '').($item->contract_date ? ' · '.Format::date($item->contract_date) : ''), ' ·')" />
                    <x-detail :label="__('Service fee')" :value="$item->service_fee !== null ? Format::money($item->service_fee) : null" />
                    <x-detail :label="__('Inspection date')" :value="$item->inspection_date ? Format::date($item->inspection_date) : null" />
                    <x-detail :label="__('Completed')" :value="$item->completed_at ? Format::datetime($item->completed_at) : null" />
                    <x-detail :label="__('Created')" :value="Format::datetime($item->created_at).($item->creator ? ' · '.$item->creator->name : '')" />
                </dl>
            </x-card>

            @include('admin.objects._activity', ['activities' => $item->activities])
        </aside>
    </div>

    @push('scripts')
        <script src="{{ asset('js/vendor/qrcode.js') }}"></script>
    @endpush
</x-layouts.admin>
