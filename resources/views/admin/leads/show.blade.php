@php use App\Support\Format; @endphp
<x-layouts.admin :title="$lead->name" :subtitle="__('Website request').' #'.$lead->id" nav="leads" icon="inbox" :back="route('admin.leads.index')">
    <x-slot:actions>
        <a class="btn" href="{{ Format::telLink($lead->phone) }}"><x-icon name="phone" :size="16" /> {{ __('Call') }}</a>
        <a class="btn btn-primary" href="{{ route('admin.clients.create', ['type' => 'individual']) }}"><x-icon name="user-plus" :size="16" /> {{ __('Create client') }}</a>
        @can('delete-records')
            <form method="post" action="{{ route('admin.leads.destroy', $lead) }}" data-confirm="{{ __('Delete this request?') }}">
                @csrf @method('delete')
                <button class="btn btn-danger btn-icon" type="submit" aria-label="{{ __('Delete') }}"><x-icon name="trash-2" :size="16" /></button>
            </form>
        @endcan
    </x-slot:actions>

    <div class="grid grid-main">
        <div class="stack">
            <x-card :title="__('Request')" icon="message-circle">
                <x-slot:actions><x-status :status="$lead->status" /></x-slot:actions>
                <dl class="details">
                    <x-detail :label="__('Name')" :value="$lead->name" />
                    <x-detail :label="__('Phone')"><a href="{{ Format::telLink($lead->phone) }}">{{ Format::phone($lead->phone) }}</a></x-detail>
                    <x-detail :label="__('E-mail')" :value="$lead->email" />
                    <x-detail :label="__('Service')" :value="$services[$lead->service] ?? null" />
                    <x-detail :label="__('Received')" :value="Format::datetime($lead->created_at)" />
                    <x-detail :label="__('Language')" :value="config('fairvalue.locales.'.$lead->locale.'.label')" />
                    <x-detail :label="__('Message')" :value="$lead->message" wide />
                </dl>
            </x-card>
        </div>
        <aside class="stack">
            <x-card :title="__('Processing')" icon="clipboard-check">
                <form method="post" action="{{ route('admin.leads.update', $lead) }}" class="stack" style="gap: 14px">
                    @csrf @method('put')
                    <x-select name="status" :label="__('Status')" :options="\App\Enums\LeadStatus::options()" :value="$lead->status" span="12" />
                    <x-textarea name="notes" :label="__('Notes')" :value="$lead->notes" rows="4" />
                    <button class="btn btn-primary" type="submit">{{ __('Save') }}</button>
                    @if ($lead->handler)<p class="field-hint">{{ __('Last processed by :name', ['name' => $lead->handler->name]) }}</p>@endif
                </form>
            </x-card>
            @include('admin.objects._activity', ['activities' => $lead->activities])
        </aside>
    </div>
</x-layouts.admin>
