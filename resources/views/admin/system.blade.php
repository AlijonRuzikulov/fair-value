@php
    use App\Support\Format;
    $statusIcon = ['ok' => 'circle-check', 'warn' => 'triangle-alert', 'fail' => 'circle-x'];
    $problems = collect($checks)->whereIn('status', ['warn', 'fail'])->count();
    $statLabels = [
        'login' => __('Sign-ins'), 'login_failed' => __('Failed sign-ins'), 'login_locked' => __('Blocked sign-ins'),
        'created' => __('Records created'), 'updated' => __('Records changed'), 'deleted' => __('Records deleted'),
        'viewed' => __('Personal data viewed'), 'exported' => __('Exports to Excel'), 'downloaded' => __('Files opened'),
        'printed' => __('Certificates printed'), 'verified' => __('Report checks on the website'),
    ];
@endphp
<x-layouts.admin :title="__('System status')" :subtitle="$problems ? __(':count items need attention', ['count' => $problems]) : __('Everything works')" nav="system" icon="activity">
    <div class="grid grid-main">
        <div class="stack">
            <x-card :title="__('Checks')" icon="shield-check" flush>
                <ul class="check-list-sys">
                    @foreach ($checks as $check)
                        <li class="sys-{{ $check['status'] }}">
                            <x-icon :name="$statusIcon[$check['status']]" :size="18" />
                            <div class="grow">
                                <strong>{{ $check['label'] }}</strong>
                                @if ($check['hint'])<small>{{ $check['hint'] }}</small>@endif
                            </div>
                            <span class="sys-value">{{ $check['value'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <x-card :title="__('Active sessions')" icon="monitor" flush>
                @if ($sessions === null)
                    <x-empty icon="monitor" :title="__('Sessions are not kept in the database')" :text="__('Set SESSION_DRIVER=database to see who is signed in.')" />
                @elseif ($sessions->isEmpty())
                    <x-empty icon="monitor" :title="__('Nobody is signed in')" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>{{ __('Employee') }}</th><th>IP</th><th>{{ __('Device') }}</th><th>{{ __('Last action') }}</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($sessions as $session)
                                    <tr>
                                        <td>{{ $session->user?->name ?? '—' }}<span class="cell-sub">{{ $session->user?->email }}</span></td>
                                        <td class="num">{{ $session->ip }}</td>
                                        <td>{{ $session->device ?? '—' }}</td>
                                        <td class="nowrap">{{ $session->last_activity->diffForHumans() }}</td>
                                        <td class="t-right">
                                            @if ($session->id !== session()->getId())
                                                <form method="post" action="{{ route('admin.system.sessions.destroy', $session->id) }}" data-confirm="{{ __('Sign this employee out of this device?') }}">
                                                    @csrf @method('delete')
                                                    <button class="btn btn-sm" type="submit"><x-icon name="log-out" :size="14" /> {{ __('End') }}</button>
                                                </form>
                                            @else
                                                <span class="badge badge-brand">{{ __('You') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            <x-card :title="__('Security events')" icon="shield-alert" flush>
                <x-slot:actions><a class="btn btn-sm" href="{{ route('admin.activity.index', ['category' => 'auth']) }}">{{ __('Audit log') }}</a></x-slot:actions>
                @if ($security->isEmpty())
                    <x-empty icon="shield-check" :title="__('No security events')" />
                @else
                    <ul class="mini-list">
                        @foreach ($security as $event)
                            <li>
                                <span class="mini-icon" style="--c: var({{ in_array($event->event, ['login_failed', 'login_locked'], true) ? '--c-rose' : '--c-amber' }})"><x-icon :name="$event->event === 'system' ? 'server' : 'lock'" :size="16" /></span>
                                <div class="grow">
                                    <strong>{{ $event->eventLabel() }}</strong> · {{ $event->subjectText() ?: $event->actorLabel() }}
                                    <small>{{ Format::datetime($event->created_at) }} · {{ $event->ip_address ?? '—' }}</small>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card :title="__('Recent errors')" icon="triangle-alert" flush>
                @if ($recentErrors === [])
                    <x-empty icon="circle-check" :title="__('No errors')" />
                @else
                    <ul class="mini-list sys-errors">
                        @foreach (array_slice($recentErrors, 0, 15) as $error)
                            <li>
                                <div class="grow">
                                    <strong>{{ $error['type'] }}</strong>: {{ $error['message'] }}
                                    <small>{{ $error['time'] }} · {{ $error['url'] }} · {{ $error['where'] }}</small>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <aside class="stack">
            <x-card :title="__('Activity')" icon="activity" flush>
                <table class="table sys-stats">
                    <thead><tr><th></th><th class="t-right">{{ __('24 hours') }}</th><th class="t-right">{{ __('7 days') }}</th></tr></thead>
                    <tbody>
                        @foreach ($statLabels as $event => $label)
                            <tr @class(['is-alert' => in_array($event, ['login_failed', 'login_locked'], true) && $stats[$event]['day'] > 0])>
                                <td><a href="{{ route('admin.activity.index', ['event' => $event]) }}">{{ $label }}</a></td>
                                <td class="t-right cell-num">{{ $stats[$event]['day'] }}</td>
                                <td class="t-right cell-num">{{ $stats[$event]['week'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>

            <x-card :title="__('Server')" icon="server">
                <dl class="details" style="grid-template-columns: 1fr">
                    @foreach ($versions as $label => $value)
                        <x-detail :label="$label" :value="$value" />
                    @endforeach
                </dl>
            </x-card>
        </aside>
    </div>
</x-layouts.admin>
