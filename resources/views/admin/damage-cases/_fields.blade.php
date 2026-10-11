<div class="field span-6">
    <label for="f-car_model_id">{{ __('Car model from the list') }}</label>
    <select id="f-car_model_id" name="car_model_id" class="input" data-car-model>
        <option value="">—</option>
        @foreach ($carModels as $car)
            <option value="{{ $car->id }}" data-title="{{ $car->title }}" data-rate="{{ (float) $car->labor_rate }}" @selected((string) old('car_model_id', $item->car_model_id) === (string) $car->id)>{{ $car->title }}@if ($car->years) ({{ $car->years }})@endif</option>
        @endforeach
    </select>
    <p class="field-hint">{{ __('Spare part prices and repair norms are offered for the selected model.') }}</p>
</div>
<x-field name="vehicle_name" :label="__('Vehicle')" :value="$item->vehicle_name" span="6" required data-vehicle-name :placeholder="__('Brand and model')" />
<x-field name="plate_number" :label="__('State number')" :value="$item->plate_number" span="3" placeholder="01 A 123 BC" />
<x-field name="vin" :label="__('Body number (VIN)')" :value="$item->vin" span="3" />
<x-field name="year_made" type="number" :label="__('Year of manufacture')" :value="$item->year_made" span="3" />
<x-field name="color" :label="__('Color')" :value="$item->color" span="3" />
<x-field name="mileage" :label="__('Mileage')" :value="$item->mileage" money :suffix="__('km')" span="3" />
<x-field name="incident_date" type="date" :label="__('Incident date')" :value="$item->incident_date" span="3" />
<x-field name="incident_place" :label="__('Incident place')" :value="$item->incident_place" span="6" />
<x-textarea name="incident_description" :label="__('Circumstances and damage')" :value="$item->incident_description" rows="3" />
