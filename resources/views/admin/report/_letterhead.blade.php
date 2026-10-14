{{-- Company letterhead: names in Uzbek and Russian, logo, requisites. --}}
<div class="letterhead">
    <div class="side">{{ $company['legal_name'] ?: $company['name'] }}</div>
    <div class="logo"><x-logo-mark :size="96" /></div>
    <div class="side">{{ $company['legal_name_ru'] ?: $company['name'] }}</div>
</div>
@if ($requisites)<div class="requisites">{{ $t($requisites) }}</div>@endif
