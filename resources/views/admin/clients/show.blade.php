@php
    use App\Support\Format;
    use App\Enums\PersonRole;
    $legal = $client->isLegal();
@endphp
<x-layouts.admin :title="$client->title" :subtitle="$client->type->label()" :nav="'clients.'.$client->type->value" :icon="$legal ? 'building-2' : 'user'" :back="route('admin.clients.index', ['type' => $client->type->value])">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('admin.clients.edit', $client) }}"><x-icon name="pencil" :size="16" /> {{ __('Edit') }}</a>
        @can('delete-records')
            <form method="post" action="{{ route('admin.clients.destroy', $client) }}" data-confirm="{{ __('Delete this client?') }}">
                @csrf @method('delete')
                <button class="btn btn-danger btn-icon" type="submit" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}"><x-icon name="trash-2" :size="16" /></button>
            </form>
        @endcan
    </x-slot:actions>

    <div class="grid grid-main">
        <div class="stack">
            <x-card :title="$legal ? __('Company details') : __('Personal details')" :icon="$legal ? 'building-2' : 'user'">
                <dl class="details">
                    @if ($legal)
                        <x-detail :label="__('Company name')" :value="$client->company_name" wide />
                        <x-detail :label="__('TIN')" :value="$client->inn" />
                        <x-detail :label="__('Director')" :value="$client->director" />
                        <x-detail :label="__('OKED')" :value="$client->oked" />
                        <x-detail :label="__('Bank')" :value="$client->bank_name" />
                        <x-detail :label="__('MFO')" :value="$client->bank_mfo" />
                        <x-detail :label="__('Bank account')" :value="$client->bank_account" />
                    @else
                        <x-detail :label="__('Full name')" :value="$client->title" wide />
                        <x-detail :label="__('Date of birth')" :value="$client->birth_date ? Format::date($client->birth_date) : null" />
                        <x-detail :label="__('PINFL')" :value="$client->pinfl" />
                        <x-detail :label="__('Passport')" :value="$client->document" />
                        <x-detail :label="__('Issued')" :value="trim(($client->passport_issued_by ?? '').($client->passport_issued_at ? ', '.Format::date($client->passport_issued_at) : ''), ', ')" />
                    @endif
                    <x-detail :label="__('Phone')">
                        @if ($client->phone)<a href="{{ Format::telLink($client->phone) }}">{{ Format::phone($client->phone) }}</a>@endif
                        @if ($client->phone2)<br><a href="{{ Format::telLink($client->phone2) }}">{{ Format::phone($client->phone2) }}</a>@endif
                        @if (! $client->phone && ! $client->phone2)<span class="muted">—</span>@endif
                    </x-detail>
                    <x-detail :label="__('E-mail')" :value="$client->email" />
                    <x-detail :label="__('Address')" :value="collect([$client->region?->name, $client->district?->name, $client->address])->filter()->implode(', ')" wide />
                </dl>
            </x-card>

            <x-card :title="__('Valuation objects')" icon="layers" flush>
                @if ($objects->isEmpty())
                    <x-empty icon="layers" :title="__('No objects yet')" :text="__('Objects appear here when the client is added to them as a person.')" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>{{ __('Object') }}</th><th>{{ __('Role') }}</th><th>{{ __('Status') }}</th><th class="t-right">{{ __('Value') }}</th></tr></thead>
                            <tbody>
                                @foreach ($objects as $object)
                                    <tr>
                                        <td><a class="cell-title" href="{{ route($object->routePrefix().'.show', $object->id) }}">{{ $object->title }}</a><span class="cell-sub">{{ $object->typeLabel() }}@if ($object->report_number) · № {{ $object->report_number }}@endif</span></td>
                                        <td>{{ PersonRole::labelFor($object->pivot->role) }}</td>
                                        <td><x-status :status="$object->status" /></td>
                                        <td class="t-right cell-num">{{ $object->market_value !== null ? Format::money($object->market_value) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            @include('admin.objects._attachments', ['owner' => $client])

            @if ($client->notes)
                <x-card :title="__('Notes')" icon="message-circle"><p style="white-space: pre-line">{{ $client->notes }}</p></x-card>
            @endif
        </div>

        <aside class="stack">
            <div class="hero-card">
                <p class="label">{{ __('Objects valued') }}</p>
                <p class="value">{{ $objects->count() }}</p>
                <div class="split">
                    <div class="split-row"><span>{{ __('Completed') }}</span><strong>{{ $objects->where('status', \App\Enums\ValuationStatus::Completed)->count() }}</strong></div>
                    <div class="split-row"><span>{{ __('Total value') }}</span><strong>{{ Format::compact($objects->sum('market_value')) }} {{ __('sum') }}</strong></div>
                    <div class="split-row"><span>{{ __('Client since') }}</span><strong>{{ Format::date($client->created_at) }}</strong></div>
                </div>
            </div>
            <x-card :title="__('Add an object')" icon="plus">
                <div class="stack" style="gap: 8px">
                    <a class="btn" href="{{ route('admin.real-estates.create', ['kind' => 'nonresidential', 'client' => $client->id]) }}"><x-icon name="building" :size="16" /> {{ __('Non-residential') }}</a>
                    <a class="btn" href="{{ route('admin.real-estates.create', ['kind' => 'residential', 'client' => $client->id]) }}"><x-icon name="house" :size="16" /> {{ __('Residential') }}</a>
                    <a class="btn" href="{{ route('admin.vehicles.create', ['client' => $client->id]) }}"><x-icon name="car" :size="16" /> {{ __('Vehicle') }}</a>
                    <a class="btn" href="{{ route('admin.assets.create', ['client' => $client->id]) }}"><x-icon name="boxes" :size="16" /> {{ __('Movable property') }}</a>
                    <a class="btn" href="{{ route('admin.damage-cases.create', ['client' => $client->id]) }}"><x-icon name="wrench" :size="16" /> {{ __('Damage assessment') }}</a>
                    <a class="btn" href="{{ route('admin.other-assets.create', ['client' => $client->id]) }}"><x-icon name="monitor" :size="16" /> {{ __('Other property') }}</a>
                </div>
                <p class="field-hint" style="margin-top: 10px">{{ __('The client is added to the new object as its owner.') }}</p>
            </x-card>
            @include('admin.objects._activity', ['activities' => $client->activities])
        </aside>
    </div>
</x-layouts.admin>
