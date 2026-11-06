{{-- The owner in the portal's lists: a person in blue; a company with its head (". . ." when not known) and its name in orange. --}}
@php $owner = $item->owner(); @endphp
@if ($owner)
    <a class="row-owner" href="{{ route('admin.clients.show', $owner) }}">
        @if ($owner->isLegal())
            <span class="row-owner-head">{{ $owner->director ?: '. . .' }}</span>
            <span class="row-owner-company">{{ $owner->title }}</span>
        @else
            <span class="row-owner-head">{{ $owner->title }}</span>
        @endif
    </a>
@else
    <span class="muted">—</span>
@endif
