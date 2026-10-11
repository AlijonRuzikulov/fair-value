@props(['label', 'value' => null, 'wide' => false])
<div @class(['detail', 'detail-wide' => $wide])>
    <dt>{{ $label }}</dt>
    <dd>@if ($slot->isNotEmpty()){{ $slot }}@elseif (filled($value)){{ $value }}@else<span class="muted">—</span>@endif</dd>
</div>
