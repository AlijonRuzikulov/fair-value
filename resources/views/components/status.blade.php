@props(['status'])
@if ($status)
    <span {{ $attributes->merge(['class' => 'badge badge-'.$status->tone()]) }}>{{ $status->label() }}</span>
@endif
