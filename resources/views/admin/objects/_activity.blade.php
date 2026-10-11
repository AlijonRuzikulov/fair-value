@if ($activities->isNotEmpty())
    <x-card :title="__('History')" icon="history">
        <ul class="timeline">
            @foreach ($activities as $activity)
                <li>
                    <strong>{{ $activity->eventLabel() }}</strong>
                    <small>{{ \App\Support\Format::datetime($activity->created_at) }} · {{ $activity->user?->name ?? __('System') }}</small>
                    @if ($activity->event === 'updated' && $activity->changes)
                        <ul class="change-list">
                            @foreach (array_slice($activity->changes, 0, 4) as $field => $change)
                                <li><b>{{ \App\Support\FieldNames::label($field) }}</b></li>
                            @endforeach
                            @if (count($activity->changes) > 4)<li>+{{ count($activity->changes) - 4 }}</li>@endif
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
