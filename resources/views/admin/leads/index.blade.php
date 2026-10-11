@php use App\Support\Format; use App\Enums\LeadStatus; @endphp
<x-layouts.admin :title="__('Website requests')" nav="leads" icon="inbox">
    <section class="card">
        <form method="get" class="card-body" action="{{ route('admin.leads.index') }}">
            @if ($status)<input type="hidden" name="status" value="{{ $status->value }}">@endif
            <div class="filters">
                <div class="field span-8">
                    <label for="q">{{ __('Search') }}</label>
                    <div class="search-box">
                        <input id="q" class="input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Name, phone, message…') }}">
                        <button type="submit" aria-label="{{ __('Search') }}"><x-icon name="search" :size="16" /></button>
                    </div>
                </div>
            </div>
        </form>
    </section>

    <section class="card">
        <nav class="tabs">
            <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" @class(['tab', 'is-active' => ! $status])>{{ __('All') }} <span class="tab-count">{{ $counts->sum() }}</span></a>
            @foreach (LeadStatus::cases() as $case)
                <a href="{{ request()->fullUrlWithQuery(['status' => $case->value, 'page' => null]) }}" @class(['tab', 'is-active' => $status === $case])>{{ $case->label() }} <span class="tab-count">{{ $counts[$case->value] ?? 0 }}</span></a>
            @endforeach
        </nav>

        @if ($leads->isEmpty())
            <x-empty icon="inbox" :title="__('No requests')" :text="__('Requests from the contact form on the website appear here.')" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Service') }}</th>
                            <th>{{ __('Message') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Received') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leads as $lead)
                            <tr>
                                <td class="muted num">{{ $lead->id }}</td>
                                <td><a class="cell-title" href="{{ route('admin.leads.show', $lead) }}">{{ $lead->name }}</a></td>
                                <td class="nowrap"><a href="{{ Format::telLink($lead->phone) }}">{{ Format::phone($lead->phone) }}</a></td>
                                <td>{{ $services[$lead->service] ?? '—' }}</td>
                                <td style="max-width: 320px"><span class="muted">{{ \Illuminate\Support\Str::limit($lead->message, 90) ?: '—' }}</span></td>
                                <td><x-status :status="$lead->status" /></td>
                                <td class="nowrap">{{ Format::datetime($lead->created_at) }}<span class="cell-sub">{{ $lead->handler?->name }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $leads->links() }}
        @endif
    </section>
</x-layouts.admin>
