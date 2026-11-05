{{-- The four steps of the valuation portal over the real estate page: done green, the current one in the colour of the menu. --}}
@php
    $done = \App\Support\ValuationProgress::steps($item);
    $current = \App\Support\ValuationProgress::current($done);
@endphp
<ol class="portal-steps" aria-label="{{ __('Steps') }}">
    @foreach ($done as $label => $ok)
        <li @class(['is-done' => $ok, 'is-current' => $loop->index === $current]) @if ($loop->index === $current) aria-current="step" @endif>
            <span class="portal-step-num">@if ($ok)<x-icon name="check" :size="14" />@else{{ $loop->iteration }}@endif</span>
            <span class="portal-step-label">{{ $label }}</span>
        </li>
    @endforeach
</ol>
