<x-layouts.admin :title="$item->exists ? __('Edit').': '.mb_strtolower($module->singular()) : __('New').': '.mb_strtolower($module->singular())" :subtitle="$module->title()" :nav="$module->nav()" :icon="$module->icon()" :back="route($module->routeName('index'))">
    <form method="post" class="form-layout" action="{{ $item->exists ? route($module->routeName('update'), $item->id) : route($module->routeName('store')) }}">
        @csrf
        @if ($item->exists) @method('put') @endif
        <x-card :title="$module->singular()" :icon="$module->icon()">
            <div class="form-grid">
                @foreach ($module->fields() as $name => $field)
                    @php
                        [$label, $type] = $field;
                        $options = $field[2] ?? [];
                        $span = $field[3] ?? 6;
                        $required = $field[4] ?? false;
                        $value = $item->{$name} instanceof \BackedEnum ? $item->{$name}->value : $item->{$name};
                    @endphp
                    @switch($type)
                        @case('select')
                            <x-select :name="$name" :label="$label" :options="$options" :value="$value" :span="$span" :required="$required"
                                :placeholder="$required ? null : ($name === 'car_model_id' ? __('All models') : '—')" />
                            @break
                        @case('textarea')
                            <x-textarea :name="$name" :label="$label" :value="$value" :span="$span" rows="2" />
                            @break
                        @case('money')
                            <x-field :name="$name" :label="$label" :value="$value" money :suffix="__('sum')" :span="$span" :required="$required" />
                            @break
                        @default
                            <x-field :name="$name" :type="$type" :label="$label" :value="$value" :span="$span" :required="$required" />
                    @endswitch
                @endforeach
            </div>
        </x-card>
        <div class="card form-actions">
            <a class="btn btn-ghost" href="{{ route($module->routeName('index')) }}">{{ __('Cancel') }}</a>
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-layouts.admin>
