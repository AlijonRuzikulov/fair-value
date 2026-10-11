@if ($paginator->hasPages())
    <nav class="pagination" aria-label="{{ __('Pagination') }}">
        <p class="pagination-info">{{ __(':from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</p>
        <ul>
            <li>
                @if ($paginator->onFirstPage())
                    <span class="page-link is-disabled"><x-icon name="chevron-left" :size="16" /></span>
                @else
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous') }}"><x-icon name="chevron-left" :size="16" /></a>
                @endif
            </li>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="page-link is-gap">…</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="page-link is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next') }}"><x-icon name="chevron-right" :size="16" /></a>
                @else
                    <span class="page-link is-disabled"><x-icon name="chevron-right" :size="16" /></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
