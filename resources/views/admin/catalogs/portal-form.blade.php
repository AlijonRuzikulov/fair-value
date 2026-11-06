{{-- Adding a record of a price list or of the norms in the classic look, as on the valuation portal: the fields one under the other, Cancel and Save. --}}
@php $back = route($module->routeName('index')); @endphp
<x-layouts.admin :title="$item->exists ? __('Edit') : __('Add')" :nav="$module->nav()" :icon="$module->icon()">
    <section class="card portal-card catalog-portal">
        <header class="portal-card-head"><h2>{{ $module->title() }} {{ $item->exists ? __('Edit') : __('Add') }}</h2></header>
        <form method="post" class="portal-card-body" action="{{ $item->exists ? route($module->routeName('update'), $item->id) : route($module->routeName('store')) }}">
            @csrf
            @if ($item->exists) @method('put') @endif
            <div class="form-grid catalog-portal-fields">
                @foreach ($module->portalFields() as $name => $field)
                    @php
                        [$label, $type] = $field;
                        $options = $field[2] ?? [];
                        $required = $field[4] ?? false;
                        $value = $item->{$name} instanceof \BackedEnum ? $item->{$name}->value : $item->{$name};
                    @endphp
                    @switch($type)
                        @case('select')
                            <x-select :name="$name" :label="$label" :options="$options" :value="$item->exists ? $value : null" span="12" :required="$required"
                                :placeholder="$name === 'car_model_id' ? __('All models') : ($required ? $label : '')" />
                            @break
                        @case('textarea')
                            <x-textarea :name="$name" :label="$label" :value="$value" span="12" rows="2" />
                            @break
                        @case('money')
                            <x-field :name="$name" :label="$label" :value="$value" money span="12" :required="$required" />
                            @break
                        @default
                            <x-field :name="$name" :type="$type" :label="$label" :value="$value" span="12" :required="$required" />
                    @endswitch
                @endforeach
            </div>
            <div class="portal-actions portal-bottom">
                <a class="btn btn-fill-danger" href="{{ $back }}">{{ __('Cancel') }}</a>
                <button class="btn btn-success" type="submit">{{ __('Save') }}</button>
            </div>
        </form>
    </section>
</x-layouts.admin>
