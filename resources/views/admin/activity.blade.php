@php
    use App\Models\Activity;
    use App\Support\FieldNames;
    use App\Support\Format;

    $categoryIcons = ['auth' => 'log-in', 'data' => 'pencil', 'access' => 'download', 'system' => 'server'];
    $show = function (mixed $value, mixed $key = null): string {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? __('yes') : __('no');
        }
        if (is_array($value)) {
            return mb_strimwidth(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 160, '…');
        }

        $codes = [
            'wrong_password' => __('wrong password'), 'unknown_email' => __('unknown e-mail'),
            'deactivated' => __('account deactivated'), 'password_changed' => __('password changed on another device'),
            'ended_by_admin' => __('ended by the administrator'), 'search' => __('automatic search'),
            'links' => __('links'), 'manual' => __('by hand'),
        ];

        if ($key === 'reason' && is_string($value) && ! isset($codes[$value])) {
            return __($value);
        }

        return $codes[$value] ?? mb_strimwidth((string) $value, 0, 160, '…');
    };
@endphp
<x-layouts.admin :title="__('Audit log')" :subtitle="__('Sign-ins, data entered and taken out, the system')" nav="activity" icon="history">
    <x-slot:actions>
        <a class="btn" href="{{ route('admin.activity.export', request()->query()) }}"><x-icon name="file-spreadsheet" :size="16" /> {{ __('Excel') }}</a>
        <form method="post" action="{{ route('admin.activity.verify') }}">
            @csrf
            <button class="btn" type="submit" data-busy="{{ __('Checking…') }}"><x-icon name="shield-check" :size="16" /> {{ __('Check integrity') }}</button>
        </form>
    </x-slot:actions>

    @if ($check)
        <div @class(['audit-integrity', 'is-bad' => ! $check['ok']])>
            <x-icon :name="$check['ok'] ? 'shield-check' : 'shield-alert'" :size="18" />
            <span>
                @if ($check['ok'])
                    {{ __('Integrity confirmed: :count records were not changed or deleted.', ['count' => $check['checked']]) }}
                @else
                    {{ __('The audit log was changed outside the system (record #:id): :reason', ['id' => $check['broken_id'], 'reason' => __((string) $check['reason'])]) }}
                @endif
                <small>{{ __('Checked: :date', ['date' => Format::datetime(\Illuminate\Support\Carbon::parse($check['checked_at']))]) }}</small>
            </span>
        </div>
    @endif

    <section class="card">
        <nav class="tabs" aria-label="{{ __('Category') }}">
            <a href="{{ request()->fullUrlWithQuery(['category' => null, 'event' => null, 'page' => null]) }}" @class(['tab', 'is-active' => ! $category])>{{ __('All') }}</a>
            @foreach (Activity::CATEGORIES as $cat)
                <a href="{{ request()->fullUrlWithQuery(['category' => $cat, 'event' => null, 'page' => null]) }}" @class(['tab', 'is-active' => $category === $cat])>
                    <x-icon :name="$categoryIcons[$cat]" :size="15" /> {{ Activity::categoryLabel($cat) }}
                    @if ($counts[$cat] ?? 0)<span class="tab-count" title="{{ __('in 24 hours') }}">{{ $counts[$cat] }}</span>@endif
                </a>
            @endforeach
        </nav>
        <form method="get" class="card-body" action="{{ route('admin.activity.index') }}">
            @if ($category)<input type="hidden" name="category" value="{{ $category }}">@endif
            <div class="filters" data-filters>
                <x-field name="q" :label="__('Search')" :value="request('q')" span="3" :placeholder="__('Record, e-mail, address…')" />
                <x-select name="user_id" :label="__('Employee')" :options="$users" :value="request('user_id')" placeholder="{{ __('All') }}" span="2" />
                <x-select name="event" :label="__('Action')" :options="$category ? array_intersect_key($events, array_filter(Activity::EVENTS, fn ($c) => $c === $category)) : $events" :value="request('event')" placeholder="{{ __('All') }}" span="2" />
                <x-field name="ip" label="IP" :value="request('ip')" span="1" />
                <x-field name="from" type="date" :label="__('From')" :value="request('from')" span="1" />
                <x-field name="to" type="date" :label="__('To')" :value="request('to')" span="1" />
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
                <table class="table audit-table">
                    <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Who') }}</th><th>{{ __('Action') }}</th><th>{{ __('Record') }}</th><th>{{ __('Details') }}</th><th>{{ __('From where') }}</th></tr></thead>
                    <tbody>
                        @foreach ($activities as $activity)
                            <tr>
                                <td class="nowrap">{{ Format::datetime($activity->created_at) }}<span class="cell-sub">#{{ $activity->id }}</span></td>
                                <td>
                                    {{ $activity->actorLabel() }}
                                    @if ($activity->user && $activity->user_email)<span class="cell-sub">{{ $activity->user_email }}</span>@endif
                                </td>
                                <td><span class="audit-badge audit-{{ $activity->category }} audit-ev-{{ $activity->event }}">{{ $activity->eventLabel() }}</span></td>
                                <td class="audit-record">{{ $activity->subjectText() ?: '—' }}<span class="cell-sub">{{ $activity->subject_type ? FieldNames::subject($activity->subject_type) : '' }}</span></td>
                                <td class="audit-details">
                                    @if ($activity->changes)
                                        @php $changes = $activity->changes; @endphp
                                        @if (in_array($activity->event, ['updated', 'settings'], true))
                                            <details>
                                                <summary>{{ collect(array_keys($changes))->map(fn ($f) => FieldNames::label($f))->implode(', ') }}</summary>
                                                <dl>
                                                    @foreach ($changes as $field => $change)
                                                        <dt>{{ FieldNames::label($field) }}</dt>
                                                        <dd><s>{{ $show(is_array($change) ? ($change['old'] ?? null) : null) }}</s> → {{ $show(is_array($change) ? ($change['new'] ?? $change) : $change) }}</dd>
                                                    @endforeach
                                                </dl>
                                            </details>
                                        @else
                                            <span class="audit-kv">
                                                @foreach ($changes as $key => $value)
                                                    <span><b>{{ FieldNames::label((string) $key) }}:</b> {{ $show($value, $key) }}</span>
                                                @endforeach
                                            </span>
                                        @endif
                                    @else
                                        <span class="faint">—</span>
                                    @endif
                                </td>
                                <td class="audit-where">
                                    <span class="num">{{ $activity->ip_address ?? '—' }}</span>
                                    @if ($activity->deviceLabel())<span class="cell-sub">{{ $activity->deviceLabel() }}</span>@endif
                                    @if ($activity->url && $activity->url !== 'console')<span class="cell-sub" title="{{ $activity->url }}">{{ mb_strimwidth($activity->url, 0, 48, '…') }}</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $activities->links() }}
        @endif
    </section>
</x-layouts.admin>
