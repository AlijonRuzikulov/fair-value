{{-- One field of the report data, with suggestions where the report has typical values. --}}
@php $list = array_key_exists($name, \App\Reports\RealEstateReport::OPTIONS) ? 'opt-'.$name : null; @endphp
@if ($type === 'textarea')
    <x-textarea :name="$name" :label="$label" :value="$value" rows="2" :span="$span" />
@elseif ($type === 'number')
    <x-field :name="$name" :label="$label" :value="$value" money :span="$span" />
@else
    <x-field :name="$name" :label="$label" :value="$value" :span="$span" :list="$list" autocomplete="off" />
@endif
