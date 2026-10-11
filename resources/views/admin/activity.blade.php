@php use App\Support\Format; use App\Support\FieldNames; @endphp
<x-layouts.admin :title="__('Activity log')" nav="activity" icon="history">
    <section class="card">
        <form method="get" class="card-body" action="{{ route('admin.activity.index') }}">
            <div class="filters" data-filters>
                <x-select name="user_id" :label="__('Employee')" :options="$users" :value="request('user_id')" placeholder="{{ __('All') }}" span="3" />
                <x-select name="event" :label="__('Action')" :options="['created' => __('Created'), 'updated' => __('Updated'), 'deleted' => __('Deleted'), 'login' => __('Signed in')]" :value="request('event')" placeholder="{{ __('All') }}" span="3" />
                <x-field name="from" type="date" :label="__('From')" :value="request('from')" span="2" />
                <x-field name="to" type="date" :label="__('To')" :value="request('to')" span="2" />
                <div class="filter-actions span-2">
                    <button class="btn btn-dark" type="submit"><x-icon name="filter" :size="16" /> {{ __('Filter') }}</button>
                    <a class="btn btn-ghost btn-icon" href="{{ route('admin.activity.index') }}" aria-label="{{ __('Reset') }}"><x-icon name="x" :size="16" /></a>
                </div>
            </div>
            <button type="button" class="btn btn-sm filter-toggle" data-filter-toggle><x-icon name="sliders-horizontal" :size="14" /> {{ __('Filters') }}</button>
        </form>
    </section>
    <section class="card">
        @if ($activities->isEmpty())
            <x-empty icon="history" :title="__('No records')" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Action') }}</th><th>{{ __('Record') }}</th><th>{{ __('Changed fields') }}</th><th>IP</th></tr></thead>
                    <tbody>
                        @foreach ($activities as $activity)
                            <tr>
                                <td class="nowrap">{{ Format::datetime($activity->created_at) }}</td>
                                <td>{{ $activity->user?->name ?? __('System') }}</td>
                                <td><span @class(['badge', 'badge-success' => $activity->event === 'created', 'badge-warning' => $activity->event === 'updated', 'badge-muted' => ! in_array($activity->event, ['created', 'updated'])])>{{ $activity->eventLabel() }}</span></td>
                                <td style="max-width: 320px">{{ $activity->subject_label ?: '—' }}<span class="cell-sub">{{ $activity->subject_type ? FieldNames::subject($activity->subject_type) : '' }}</span></td>
                                <td style="max-width: 320px"><span class="muted">{{ $activity->changes ? collect(array_keys($activity->changes))->map(fn ($f) => FieldNames::label($f))->implode(', ') : '—' }}</span></td>
                                <td class="muted num">{{ $activity->ip_address }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $activities->links() }}
        @endif
    </section>
</x-layouts.admin>
