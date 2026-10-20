{{-- The cost and income approaches below the object, when they are applied. --}}
@if (array_intersect(array_map(fn ($a) => $a->value, $item->approachList()), ['cost', 'income']))
    <div class="stack" style="margin-top: 20px">
        @include('admin.real-estates._approaches')
    </div>
@endif
