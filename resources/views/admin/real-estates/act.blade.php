{{-- The act window opened as a page (without the window, or after a mistake in it). --}}
<x-layouts.admin :title="__('Act').' · '.$item->title" :nav="'realestate.'.$item->kind->value" icon="house" :back="route('admin.real-estates.show', $item->id)">
    <div class="card act-page">
        @include('admin.real-estates._act')
    </div>
</x-layouts.admin>
