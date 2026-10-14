{{-- Requisites of a person: labels in the report's alphabet, values as typed. --}}
@forelse (\App\Reports\RealEstateReport::personLines($client) as [$label, $value])
    @if ($label){{ $t($label) }}: @endif{{ $label === 'Manzili' ? $t($value) : $value }}@if (! $loop->last)<br>@endif
@empty
    —
@endforelse
