@php use App\Support\Format; @endphp
<ol class="approval">
    @foreach ($stages as [$title, $step, $who])
        @php $done = $step || ($loop->index === 1 && $completedDirectly); @endphp
        <li @class(['is-done' => $done])>
            <span class="approval-mark"><x-icon :name="$done ? 'check' : 'hourglass'" :size="14" /></span>
            <div>
                <strong>{{ $title }}</strong>
                <small>@if ($step){{ Format::datetime($step->created_at) }} · {{ $step->user?->name ?? '—' }}@elseif ($done){{ __('Completed without approval') }} · {{ Format::datetime($item->completed_at) }}@else{{ __('Not yet') }}@if ($who) · {{ $who }}@endif @endif</small>
            </div>
        </li>
    @endforeach
</ol>
