@props(['title' => null, 'icon' => null, 'actions' => null, 'flush' => false])
<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || $actions)
        <header class="card-head">
            <h2 class="card-title">@if ($icon)<x-icon :name="$icon" />@endif{{ $title }}</h2>
            @if ($actions)<div class="card-actions">{{ $actions }}</div>@endif
        </header>
    @endif
    <div @class(['card-body' => ! $flush])>{{ $slot }}</div>
</section>
