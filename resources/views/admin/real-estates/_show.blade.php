@php $features = $item->featureList(); @endphp
@if ($features)
    <x-card :title="__('Special amenities')" icon="sparkles">
        <div class="chips">
            @foreach ($features as $feature)
                <span class="chip chip-brand"><x-icon name="check" :size="13" /> {{ $feature->label() }}</span>
            @endforeach
        </div>
    </x-card>
@endif
