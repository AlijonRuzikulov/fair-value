<section class="page-hero">
    <div class="container">
        <nav class="crumbs" aria-label="{{ __('Breadcrumbs') }}">
            <a href="{{ route('home') }}">{{ __('Home') }}</a>
            @foreach ($crumbs ?? [] as [$label, $url])
                <span>/</span><a href="{{ $url }}">{{ $label }}</a>
            @endforeach
            <span>/</span><span>{{ $title }}</span>
        </nav>
        <h1>{{ $title }}</h1>
        @if (! empty($lead))<p>{{ $lead }}</p>@endif
    </div>
</section>
