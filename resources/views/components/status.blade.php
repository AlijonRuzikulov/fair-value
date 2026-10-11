@props(['status'])
@if ($status)
    <span {{ $attributes->merge(['class' => 'badge badge-'.$status->tone()]) }}><span class="badge-dot"></span>{{ $status->label() }}</span>
@endif
