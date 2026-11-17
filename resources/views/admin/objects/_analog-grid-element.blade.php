{{--
    One element of comparison of the grid: what the object and the analogues have, the adjustment and
    the price per m² after it. $key — an element with a typed percentage; null — one that changes nothing
    (the adjustment 1 and the price after $previous).
--}}
<tr class="g-element-row">
    <td>{{ $title }}</td><td class="g-sub">{{ $sub }}</td><td>{{ $subjectText }}</td>
    @foreach ($analogs as $analog)<td>{{ $texts[$analog->id] ?? '—' }}</td>@endforeach
</tr>
<tr class="g-adj">
    <td>{{ __('Adjustment') }}@if ($key), %@endif</td><td></td><td></td>
    @foreach ($analogs as $analog)
        @if ($key)
            <td><input class="input g-num" name="analogs[{{ $analog->id }}][adjustments][{{ $key }}]" value="{{ rtrim(rtrim(number_format($analog->adjustment($key), 4, '.', ''), '0'), '.') }}" inputmode="decimal" aria-label="{{ $title }}, %" @disabled(! $canEdit)></td>
        @else
            <td class="num">1</td>
        @endif
    @endforeach
</tr>
<tr class="g-after">
    <td>{{ __('Value per 1 m² after the adjustment') }}</td><td></td><td></td>
    @foreach ($analogs as $analog)
        @php $row = $result['rows'][$analog->id] ?? null; @endphp
        <td class="num">{{ $row ? \App\Support\Format::number($row['steps'][$key ?? $previous]['value'], 2) : '—' }}</td>
    @endforeach
</tr>
